"""Public announcement feed and current-policy content-administration routes."""

from __future__ import annotations

from typing import Annotated, Any, cast

from fastapi import APIRouter, Depends, Query, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from sqlalchemy.orm import Session
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
    AnnouncementStorage,
    AvatarUploadError,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.announcements import (
    AnnouncementCreateRequest,
    AnnouncementDeleteRequest,
    AnnouncementFilters,
    AnnouncementListResponse,
    AnnouncementMutationResponse,
    AnnouncementUpdateRequest,
)
from app.schemas.auth import StatusResponse
from app.services.announcements import AnnouncementError, AnnouncementService

router = APIRouter(prefix="/api", tags=["announcements"])


async def _announcement_input(request: Request) -> tuple[dict[str, Any], UploadFile | None]:
    if "application/json" in request.headers.get("content-type", "").lower():
        return await body_mapping(request), None
    form = await request.form()
    payload: dict[str, Any] = {}
    image: UploadFile | None = None
    for key, value in form.multi_items():
        if key in {"image", "images"} and isinstance(value, UploadFile) and image is None:
            image = value
        elif isinstance(value, str):
            payload[key] = value
    return payload, image


async def _list(request: Request, filters: AnnouncementFilters) -> JSONResponse:
    await enforce_rate_limit(
        request,
        "get-announcements",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Announcement service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    result = await run_in_threadpool(
        with_session,
        database,
        lambda session: AnnouncementService(session).list_announcements(filters),
    )
    return json_response(result, 200)


@router.get("/get_announcements", response_model=AnnouncementListResponse | StatusResponse)
async def get_announcements_get(
    request: Request,
    announcement_id: Annotated[int | None, Query(alias="id", gt=0)] = None,
    created_by: Annotated[int | None, Query(gt=0)] = None,
    announcement_type: Annotated[str | None, Query(alias="type")] = None,
    chapter_id: Annotated[int | None, Query(gt=0)] = None,
    year: str | None = None,
    page: Annotated[int, Query(ge=1, le=10_000)] = 1,
    limit: Annotated[int, Query(ge=1, le=100)] = 100,
) -> JSONResponse:
    try:
        filters = AnnouncementFilters.model_validate(
            {
                "id": announcement_id,
                "created_by": created_by,
                "type": announcement_type,
                "chapter_id": chapter_id,
                "year": year,
                "page": page,
                "limit": limit,
            }
        )
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid announcement filters",
                code="announcement_filters_invalid",
            ),
            400,
        )
    return await _list(request, filters)


@router.post(
    "/get_announcements",
    response_model=AnnouncementListResponse | StatusResponse,
    openapi_extra=request_body_schema(AnnouncementFilters, required=False),
)
async def get_announcements_post(request: Request) -> JSONResponse:
    try:
        filters = AnnouncementFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid announcement filters",
                code="announcement_filters_invalid",
            ),
            400,
        )
    return await _list(request, filters)


async def _manage(
    request: Request,
    principal: AccessPrincipal,
    *,
    create: bool,
    input_data: tuple[dict[str, Any], UploadFile | None] | None = None,
) -> JSONResponse:
    payload, upload = input_data or await _announcement_input(request)
    try:
        model = AnnouncementCreateRequest if create else AnnouncementUpdateRequest
        operation = model.model_validate(payload)
        image = (
            prepare_avatar(upload.filename, await upload.read(5 * 1024 * 1024 + 1))
            if upload
            else None
        )
    except (ValidationError, AvatarUploadError) as exc:
        message = (
            str(exc).replace("Avatar", "Announcement image")
            if isinstance(exc, AvatarUploadError)
            else "Invalid announcement fields"
        )
        return json_response(
            StatusResponse(status=400, message=message, code="announcement_invalid_request"), 400
        )
    finally:
        if upload is not None:
            await upload.close()
    await enforce_rate_limit(
        request,
        "manage-announcement",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Announcement service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        storage = AnnouncementStorage(settings.upload_root)
        if create:
            create_operation = cast(AnnouncementCreateRequest, operation)

            def call(session: Session) -> AnnouncementMutationResponse:
                return AnnouncementService(session, storage).create(
                    principal.user_id, create_operation, image
                )
        else:
            update_operation = cast(AnnouncementUpdateRequest, operation)

            def call(session: Session) -> AnnouncementMutationResponse:
                return AnnouncementService(session, storage).update(
                    principal.user_id, update_operation, image
                )

        result = await run_in_threadpool(
            with_session,
            database,
            call,
        )
    except AnnouncementError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Announcement image storage is temporarily unavailable",
                code="announcement_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_announcement",
    response_model=AnnouncementMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(AnnouncementCreateRequest),
)
async def create_announcement(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, create=True)


@router.post(
    "/manage_announcement",
    response_model=AnnouncementMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(AnnouncementUpdateRequest),
)
async def manage_announcement(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, upload = await _announcement_input(request)
    if payload.get("function_type") == "delete":
        if upload is not None:
            await upload.close()
            return json_response(
                StatusResponse(
                    status=400,
                    message="Images are not accepted when deleting an announcement",
                    code="announcement_invalid_request",
                ),
                400,
            )
        try:
            deletion = AnnouncementDeleteRequest.model_validate(payload)
        except ValidationError:
            return json_response(
                StatusResponse(
                    status=400,
                    message="Invalid announcement fields",
                    code="announcement_invalid_request",
                ),
                400,
            )
        await enforce_rate_limit(
            request,
            "manage-announcement",
            principal.user_id,
            limit=30,
            window_seconds=5 * 60,
            unavailable_message="Announcement service is temporarily unavailable",
        )
        database: Database = request.app.state.database
        settings: Settings = request.app.state.settings
        try:
            result = await run_in_threadpool(
                with_session,
                database,
                lambda session: AnnouncementService(
                    session, AnnouncementStorage(settings.upload_root)
                ).delete(principal.user_id, deletion.id),
            )
        except AnnouncementError as exc:
            return json_response(
                StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
                exc.http_status,
            )
        return json_response(result, 200)
    return await _manage(request, principal, create=False, input_data=(payload, upload))
