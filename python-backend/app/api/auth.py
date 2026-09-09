"""Legacy-compatible authentication HTTP endpoints."""

from __future__ import annotations

from contextlib import suppress
from typing import Annotated

from fastapi import APIRouter, Depends, Request, status
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from starlette.concurrency import run_in_threadpool

from app.api.common import (
    body_mapping,
    enforce_rate_limit,
    json_response,
    request_body_schema,
    with_session,
)
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.core.errors import (
    AccessCodeError,
    AccountVerificationError,
    InvalidCredentialsError,
    InvalidRefreshTokenError,
    InvalidResetTokenError,
    MailDeliveryError,
    PasswordChangeError,
    TokenConfigurationError,
)
from app.db.session import Database
from app.integrations.mail import Mailer
from app.schemas.auth import (
    BooleanStatusResponse,
    ChangePasswordRequest,
    ForgotPasswordRequest,
    LoginRequest,
    LoginResponse,
    LogoutRequest,
    RefreshRequest,
    RefreshResponse,
    ResendVerificationRequest,
    ResetPasswordRequest,
    StatusResponse,
    VerifyAccessCodeRequest,
    VerifyEmailRequest,
)
from app.services.auth import AccountStateError, AuthService

router = APIRouter(prefix="/api", tags=["authentication"])


@router.post(
    "/login",
    response_model=LoginResponse | StatusResponse,
    openapi_extra=request_body_schema(LoginRequest),
)
async def login(request: Request) -> JSONResponse:
    """Authenticate credentials from JSON or form data and issue a token pair."""
    body = await body_mapping(request)
    try:
        credentials = LoginRequest.model_validate(body)
    except ValidationError as exc:
        invalid_email = any(
            error.get("loc") == ("identity",) and error.get("type") == "value_error"
            for error in exc.errors()
        )
        if invalid_email:
            return json_response(StatusResponse(status=422, message="Invalid email format"), 422)
        return json_response(
            StatusResponse(status=400, message="Kindly provide your email and password"),
            400,
        )

    await enforce_rate_limit(
        request,
        "login",
        credentials.identity,
        limit=10,
        window_seconds=15 * 60,
    )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).login(
                str(credentials.identity), credentials.password
            ),
        )
    except InvalidCredentialsError as exc:
        return json_response(StatusResponse(status=401, message=exc.message, code=exc.code), 401)
    except AccountStateError as exc:
        return json_response(
            StatusResponse(
                status=exc.body_status,
                message=exc.message,
                user_id=exc.user_id,
            ),
            exc.http_status,
        )
    except TokenConfigurationError:
        return json_response(
            StatusResponse(status=503, message="Authentication service is unavailable"),
            status.HTTP_503_SERVICE_UNAVAILABLE,
        )
    http_status = 406 if result.status == 407 else 200
    return json_response(result, http_status)


@router.post("/refresh_token", response_model=RefreshResponse | StatusResponse)
async def refresh_token(request: Request) -> JSONResponse:
    """Rotate a valid one-time refresh token."""
    try:
        refresh_request = RefreshRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(StatusResponse(status=400, message="refresh_token is required"), 400)
    await enforce_rate_limit(
        request,
        "refresh",
        refresh_request.refresh_token,
        limit=30,
        window_seconds=5 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).refresh(refresh_request.refresh_token),
        )
    except InvalidRefreshTokenError as exc:
        return json_response(StatusResponse(status=401, message=exc.message, code=exc.code), 401)
    except TokenConfigurationError:
        return json_response(
            StatusResponse(status=503, message="Authentication service is unavailable"),
            status.HTTP_503_SERVICE_UNAVAILABLE,
        )
    return json_response(result, 200)


@router.post("/logout", response_model=StatusResponse)
async def logout(request: Request) -> JSONResponse:
    """Idempotently revoke a refresh token when supplied."""
    try:
        logout_request = LogoutRequest.model_validate(await body_mapping(request))
    except ValidationError:
        logout_request = LogoutRequest()
    if not logout_request.refresh_token:
        return json_response(StatusResponse(status=200, message="Logged out successfully"), 200)
    await enforce_rate_limit(
        request,
        "logout",
        logout_request.refresh_token,
        limit=60,
        window_seconds=5 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    await run_in_threadpool(
        with_session,
        database,
        lambda session: AuthService(session, settings).logout(logout_request.refresh_token),
    )
    return json_response(StatusResponse(status=200, message="Logged out successfully"), 200)


@router.post(
    "/forgot_password",
    response_model=BooleanStatusResponse,
    openapi_extra=request_body_schema(ForgotPasswordRequest),
)
async def forgot_password(request: Request) -> JSONResponse:
    """Initiate finite password recovery with a non-enumerating response."""
    body = await body_mapping(request)
    if not str(body.get("identity", "")).strip():
        return json_response(
            BooleanStatusResponse(status=False, message="Email address is required"),
            400,
        )
    try:
        recovery = ForgotPasswordRequest.model_validate(body)
    except ValidationError:
        return json_response(
            BooleanStatusResponse(status=False, message="Invalid email format"),
            422,
        )
    await enforce_rate_limit(
        request,
        "forgot-password",
        recovery.identity,
        limit=5,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    with suppress(MailDeliveryError):
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings, mailer).request_password_reset(
                str(recovery.identity)
            ),
        )
    return json_response(
        BooleanStatusResponse(
            status=True,
            message=(
                "Email sent, if your account is found in our system, you'll receive a "
                "password reset email."
            ),
        ),
        200,
    )


@router.post(
    "/reset_password",
    response_model=StatusResponse,
    openapi_extra=request_body_schema(ResetPasswordRequest),
)
async def reset_password(request: Request) -> JSONResponse:
    """Consume a one-time recovery token and revoke existing refresh sessions."""
    body = await body_mapping(request)
    code = str(body.get("code", "")).strip()
    new_password = str(body.get("new_password", ""))
    confirmation = str(body.get("new_password_confirm", ""))
    if not code:
        return json_response(StatusResponse(status=400, message="Reset code is required"), 400)
    if not new_password:
        return json_response(StatusResponse(status=400, message="New password is required"), 400)
    if new_password != confirmation:
        return json_response(StatusResponse(status=400, message="Passwords do not match"), 400)
    try:
        recovery = ResetPasswordRequest.model_validate(body)
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="Password must be at least 8 characters"),
            400,
        )
    await enforce_rate_limit(
        request,
        "reset-password",
        recovery.code,
        limit=10,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).reset_password(
                recovery.code, recovery.new_password
            ),
        )
    except InvalidResetTokenError as exc:
        return json_response(StatusResponse(status=400, message=exc.message, code=exc.code), 400)
    return json_response(
        StatusResponse(status=200, message="Password has been reset successfully"),
        200,
    )


@router.post(
    "/change_user_password",
    response_model=StatusResponse,
    openapi_extra=request_body_schema(ChangePasswordRequest),
)
async def change_user_password(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Change only the authenticated member's password and revoke refresh sessions."""
    body = await body_mapping(request)
    old_password = str(body.get("old_password", ""))
    new_password = str(body.get("new_password", ""))
    confirmation = str(body.get("confirm_password", ""))
    if not old_password or not new_password or not confirmation:
        return json_response(
            StatusResponse(
                status=400,
                message="old_password, new_password and confirm_password are required",
            ),
            400,
        )
    if new_password != confirmation:
        return json_response(
            StatusResponse(
                status=400,
                message="new_password and confirm_password do not match",
            ),
            400,
        )
    try:
        change = ChangePasswordRequest.model_validate(body)
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="New password must be at least 8 characters"),
            400,
        )
    await enforce_rate_limit(
        request,
        "change-password",
        principal.user_id,
        limit=5,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).change_password(
                principal.user_id,
                change.old_password,
                change.new_password,
            ),
        )
    except PasswordChangeError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(StatusResponse(status=200, message="Password updated successfully"), 200)


@router.post(
    "/resend_verify_email",
    response_model=StatusResponse,
    openapi_extra=request_body_schema(ResendVerificationRequest),
)
async def resend_verify_email(request: Request) -> JSONResponse:
    """Replace and deliver an unverified member's finite email code."""
    try:
        resend = ResendVerificationRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(StatusResponse(status=400, message="user_id is required"), 400)
    await enforce_rate_limit(
        request,
        "resend-verification",
        resend.user_id,
        limit=5,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    try:
        message = await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings, mailer).resend_email_verification(
                resend.user_id
            ),
        )
    except AccountVerificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except MailDeliveryError:
        return json_response(
            StatusResponse(status=503, message="Verification email could not be sent"),
            503,
        )
    return json_response(StatusResponse(status=200, message=message), 200)


@router.post(
    "/verify_email",
    response_model=StatusResponse,
    openapi_extra=request_body_schema(VerifyEmailRequest),
)
async def verify_email(request: Request) -> JSONResponse:
    """Consume a finite one-time email verification code."""
    try:
        verification = VerifyEmailRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="user_id and verify_code are required"),
            400,
        )
    await enforce_rate_limit(
        request,
        "verify-email",
        verification.user_id,
        limit=10,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    try:
        message = await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings, mailer).verify_email(
                verification.user_id,
                verification.verify_code,
            ),
        )
    except AccountVerificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(StatusResponse(status=200, message=message), 200)


@router.post(
    "/verify_user_access_code",
    response_model=StatusResponse,
    openapi_extra=request_body_schema(VerifyAccessCodeRequest),
)
async def verify_user_access_code(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Verify only the authenticated member's code using current database state."""
    try:
        verification = VerifyAccessCodeRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(StatusResponse(status=400, message="access_code is required"), 400)
    await enforce_rate_limit(
        request,
        "verify-access-code",
        principal.user_id,
        limit=10,
        window_seconds=60 * 60,
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).verify_access_code(
                principal.user_id,
                verification.access_code,
            ),
        )
    except AccessCodeError as exc:
        return json_response(StatusResponse(status=400, message=exc.message, code=exc.code), 400)
    return json_response(
        StatusResponse(status=200, message="Access code verified successfully"), 200
    )
