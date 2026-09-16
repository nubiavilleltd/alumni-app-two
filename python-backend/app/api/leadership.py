"""Public leadership feed and current-policy administration routes."""

from __future__ import annotations

from typing import Annotated, Any

from fastapi import APIRouter, Depends, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import UploadFile

from app.api.common import (
    body_mapping,
    enforce_rate_limit,
    json_response,
    request_body_schema,
    with_session,
)
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.db.session import Database
from app.integrations.uploads import (
    AvatarUploadError,
    LeadershipStorage,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.leadership import (
    LeadershipCreateRequest,
    LeadershipDeleteRequest,
    LeadershipDetailResponse,
    LeadershipFilters,
    LeadershipListResponse,
    LeadershipMutationResponse,
    LeadershipReorderRequest,
    LeadershipUpdateRequest,
)
from app.services.leadership import LeadershipError, LeadershipService

router = APIRouter(prefix="/api", tags=["leadership"])


async def _input(request: Request) -> tuple[dict[str, Any], UploadFile | None]:
    if "application/json" in request.headers.get("content-type", "").lower():
        return await body_mapping(request), None
    form = await request.form()
    payload: dict[str, Any] = {}
    upload: UploadFile | None = None
    for key, value in form.multi_items():
        if key == "leadership_photo" and isinstance(value, UploadFile):
            upload = value
        elif isinstance(value, str):
            payload[key] = value
    return payload, upload


async def _prepared(upload: UploadFile | None) -> Any:
    if upload is None:
        return None
    try:
        return prepare_avatar(upload.filename, await upload.read(5 * 1024 * 1024 + 1))
    finally:
        await upload.close()


@router.post(
    "/get_leadership",
    response_model=LeadershipListResponse | LeadershipDetailResponse | StatusResponse,
    openapi_extra=request_body_schema(LeadershipFilters, required=False),
)
async def get_leadership(request: Request) -> JSONResponse:
    try:
        filters = LeadershipFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid leadership filters", code="leadership_filters_invalid"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-leadership",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Leadership service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session, database, lambda session: LeadershipService(session).get(filters)
        )
    except LeadershipError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


async def _manage(
    request: Request,
    principal: AccessPrincipal,
    operation: str,
    input_data: tuple[dict[str, Any], UploadFile | None] | None = None,
) -> JSONResponse:
    payload, upload = input_data or await _input(request)
    try:
        if operation == "create":
            parsed: Any = LeadershipCreateRequest.model_validate(payload)
        elif operation == "update":
            parsed = LeadershipUpdateRequest.model_validate(payload)
        elif operation == "delete":
            parsed = LeadershipDeleteRequest.model_validate(payload)
        elif operation == "reorder":
            parsed = LeadershipReorderRequest.model_validate(payload)
        else:
            raise KeyError(operation)
        prepared = await _prepared(upload)
        if operation in {"delete", "reorder"} and prepared is not None:
            raise AvatarUploadError("Images are not accepted for this operation")
    except (KeyError, ValidationError, AvatarUploadError):
        if upload is not None:
            await upload.close()
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid leadership fields or image",
                code="leadership_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-leadership",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Leadership service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:

        def call(session: Any) -> LeadershipMutationResponse:
            service = LeadershipService(session, LeadershipStorage(settings.upload_root))
            if operation == "create":
                return service.create(principal.user_id, parsed, prepared)
            if operation == "update":
                return service.update(principal.user_id, parsed, prepared)
            if operation == "delete":
                return service.delete(principal.user_id, parsed)
            return service.reorder(principal.user_id, parsed)

        result = await run_in_threadpool(with_session, database, call)
    except LeadershipError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Leadership image storage is temporarily unavailable",
                code="leadership_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_leader",
    response_model=LeadershipMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(LeadershipCreateRequest),
)
async def create_leader(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, "create")


@router.post(
    "/manage_leader",
    response_model=LeadershipMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(LeadershipUpdateRequest),
)
async def manage_leader(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, upload = await _input(request)
    operation = str(payload.get("function_type") or "").casefold()
    if operation not in {"update", "delete", "reorder"}:
        if upload is not None:
            await upload.close()
        return json_response(
            StatusResponse(
                status=400,
                message="function_type must be update, delete, or reorder",
                code="leadership_invalid_request",
            ),
            400,
        )
    return await _manage(request, principal, operation, (payload, upload))
