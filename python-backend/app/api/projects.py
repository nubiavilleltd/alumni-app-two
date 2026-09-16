"""Public project presentation and current-policy project administration routes."""

from __future__ import annotations

import json
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
    ProjectStorage,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.projects import (
    ProjectCreateRequest,
    ProjectDeleteRequest,
    ProjectDetailResponse,
    ProjectFilters,
    ProjectListResponse,
    ProjectMutationResponse,
    ProjectUpdateRequest,
)
from app.services.projects import ProjectError, ProjectService

router = APIRouter(prefix="/api", tags=["projects"])


async def _project_input(request: Request) -> tuple[dict[str, Any], list[UploadFile]]:
    if "application/json" in request.headers.get("content-type", "").lower():
        return await body_mapping(request), []
    form = await request.form()
    payload: dict[str, Any] = {}
    images: list[UploadFile] = []
    for key, value in form.multi_items():
        if key in {"images", "images[]"} and isinstance(value, UploadFile):
            images.append(value)
        elif isinstance(value, str):
            payload[key] = value
    raw_removed = payload.get("remove_images")
    if isinstance(raw_removed, str):
        try:
            decoded = json.loads(raw_removed)
        except json.JSONDecodeError:
            decoded = None
        if isinstance(decoded, list) and all(isinstance(item, str) for item in decoded):
            payload["remove_images"] = decoded
    for field in {"target_amount", "start_date", "end_date", "year", "conducted_by"}:
        if payload.get(field) == "":
            payload[field] = None
    return payload, images


async def _prepared_images(images: list[UploadFile]) -> list[Any]:
    if len(images) > 6:
        raise AvatarUploadError("Project may contain at most 6 images")
    try:
        return [
            prepare_avatar(image.filename, await image.read(5 * 1024 * 1024 + 1))
            for image in images
        ]
    finally:
        for image in images:
            await image.close()


async def _get_projects(request: Request, filters: ProjectFilters) -> JSONResponse:
    await enforce_rate_limit(
        request,
        "get-projects",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Project service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session, database, lambda session: ProjectService(session).get(filters)
        )
    except ProjectError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_projects",
    response_model=ProjectListResponse | ProjectDetailResponse | StatusResponse,
    openapi_extra=request_body_schema(ProjectFilters, required=False),
)
async def get_projects_post(request: Request) -> JSONResponse:
    try:
        filters = ProjectFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid project filters", code="project_filters_invalid"
            ),
            400,
        )
    return await _get_projects(request, filters)


async def _manage(
    request: Request,
    principal: AccessPrincipal,
    *,
    create: bool,
    input_data: tuple[dict[str, Any], list[UploadFile]] | None = None,
) -> JSONResponse:
    payload, images = input_data or await _project_input(request)
    try:
        model = ProjectCreateRequest if create else ProjectUpdateRequest
        operation = model.model_validate(payload)
        prepared = await _prepared_images(images)
    except (ValidationError, AvatarUploadError):
        for image in images:
            await image.close()
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid project fields or images",
                code="project_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-project",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Project service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        storage = ProjectStorage(settings.upload_root)
        if create:
            assert isinstance(operation, ProjectCreateRequest)

            def call(session: Any) -> ProjectMutationResponse:
                return ProjectService(session, storage).create(
                    principal.user_id, operation, prepared
                )
        else:
            assert isinstance(operation, ProjectUpdateRequest)

            def call(session: Any) -> ProjectMutationResponse:
                return ProjectService(session, storage).update(
                    principal.user_id, operation, prepared
                )

        result = await run_in_threadpool(with_session, database, call)
    except ProjectError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Project image storage is temporarily unavailable",
                code="project_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_project",
    response_model=ProjectMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ProjectCreateRequest),
)
async def create_project(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, create=True)


@router.post(
    "/manage_project",
    response_model=ProjectMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ProjectUpdateRequest),
)
async def manage_project(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, images = await _project_input(request)
    if payload.get("function_type") == "delete":
        try:
            if images:
                raise AvatarUploadError("Images are not accepted when deleting a project")
            deletion = ProjectDeleteRequest.model_validate(payload)
        except (ValidationError, AvatarUploadError):
            for image in images:
                await image.close()
            return json_response(
                StatusResponse(
                    status=400, message="Invalid project fields", code="project_invalid_request"
                ),
                400,
            )
        await enforce_rate_limit(
            request,
            "manage-project",
            principal.user_id,
            limit=30,
            window_seconds=5 * 60,
            unavailable_message="Project service is temporarily unavailable",
        )
        database: Database = request.app.state.database
        settings: Settings = request.app.state.settings
        try:
            result = await run_in_threadpool(
                with_session,
                database,
                lambda session: ProjectService(
                    session, ProjectStorage(settings.upload_root)
                ).delete(principal.user_id, deletion),
            )
        except ProjectError as exc:
            return json_response(
                StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
                exc.http_status,
            )
        return json_response(result, 200)
    return await _manage(request, principal, create=False, input_data=(payload, images))
