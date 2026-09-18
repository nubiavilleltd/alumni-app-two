"""Social identity login/signup/link/unlink endpoints."""

from __future__ import annotations

from typing import Annotated, Any

from fastapi import APIRouter, Depends, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from sqlalchemy.orm import Session
from starlette.concurrency import run_in_threadpool

from app.api.common import body_mapping, enforce_rate_limit, json_response, with_session
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.db.session import Database
from app.integrations.social import (
    FacebookIdentityProvider,
    GoogleIdentityProvider,
    SocialIdentity,
    SocialVerificationError,
)
from app.schemas.auth import LoginResponse, StatusResponse
from app.schemas.social import SocialLoginRequest, SocialSignupResponse, SocialUnlinkRequest
from app.services.auth import AccountStateError, AuthService, SocialAccountError

router = APIRouter(prefix="/socials", tags=["social authentication"])


def _verify_identity(
    settings: Settings,
    provider: str,
    id_token: str | None,
    access_token: str | None,
) -> SocialIdentity:
    """Verify the provider token server-side, never trusting client claims."""
    if provider == "google":
        if not settings.google_client_id:
            raise SocialVerificationError("Social login is not configured", 503)
        return GoogleIdentityProvider(settings.google_client_id).verify(id_token or "")
    facebook_secret = settings.facebook_app_secret_value()
    if not settings.facebook_app_id or not facebook_secret:
        raise SocialVerificationError("Social login is not configured", 503)
    return FacebookIdentityProvider(settings.facebook_app_id, facebook_secret).verify(
        access_token or ""
    )


async def _social_auth(
    request: Request,
    *,
    signup: bool,
) -> JSONResponse:
    try:
        payload = SocialLoginRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message='provider must be "google" or "facebook"',
                code="social_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "social-login",
        payload.provider,
        limit=10,
        window_seconds=15 * 60,
    )
    settings: Settings = request.app.state.settings
    database: Database = request.app.state.database
    try:
        identity = _verify_identity(
            settings, payload.provider, payload.id_token, payload.access_token
        )
    except SocialVerificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    def _signup(session: Session) -> SocialSignupResponse:
        return AuthService(session, settings).social_signup(identity)

    def _login(session: Session) -> LoginResponse:
        return AuthService(session, settings).social_login(identity)

    try:
        if signup:
            result = await run_in_threadpool(with_session, database, _signup)
            return json_response(result, 201)
        result = await run_in_threadpool(with_session, database, _login)  # type: ignore[arg-type]
        return json_response(result, 200)
    except AccountStateError as exc:
        return json_response(
            StatusResponse(status=exc.body_status, message=exc.message, user_id=exc.user_id),
            exc.http_status,
        )
    except SocialAccountError as exc:
        body: dict[str, Any] = {"status": exc.body_status, "message": exc.message}
        if exc.user_id is not None:
            body["user_id"] = exc.user_id
        body.update(exc.extra)
        return JSONResponse(status_code=exc.http_status, content=body)


@router.post(
    "/social_login",
    response_model=LoginResponse | StatusResponse,
)
async def social_login(request: Request) -> JSONResponse:
    return await _social_auth(request, signup=False)


@router.post(
    "/social_signup",
    response_model=SocialSignupResponse | StatusResponse,
)
async def social_signup(request: Request) -> JSONResponse:
    return await _social_auth(request, signup=True)


@router.post("/link", response_model=StatusResponse)
async def social_link(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    try:
        payload = SocialLoginRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid social link request", code="social_invalid_request"
            ),
            400,
        )
    settings: Settings = request.app.state.settings
    database: Database = request.app.state.database
    try:
        identity = _verify_identity(
            settings, payload.provider, payload.id_token, payload.access_token
        )
    except SocialVerificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )
    try:
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).social_link(principal.user_id, identity),
        )
    except SocialAccountError as exc:
        return json_response(
            StatusResponse(status=exc.body_status, message=exc.message), exc.http_status
        )
    return json_response(
        StatusResponse(status=200, message=f"{identity.provider} account linked successfully"), 200
    )


@router.post("/unlink", response_model=StatusResponse)
async def social_unlink(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    try:
        payload = SocialUnlinkRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid social unlink request", code="social_invalid_request"
            ),
            400,
        )
    settings: Settings = request.app.state.settings
    database: Database = request.app.state.database
    try:
        await run_in_threadpool(
            with_session,
            database,
            lambda session: AuthService(session, settings).social_unlink(
                principal.user_id, payload.provider
            ),
        )
    except SocialAccountError as exc:
        return json_response(
            StatusResponse(status=exc.body_status, message=exc.message), exc.http_status
        )
    return json_response(
        StatusResponse(status=200, message=f"{payload.provider} account unlinked successfully"), 200
    )
