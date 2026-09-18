"""Public vacancy reads and authenticated member-owned vacancy writes."""

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
    UploadStorageError,
    VacancyStorage,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.vacancies import (
    VacancyCreateRequest,
    VacancyDeleteRequest,
    VacancyDetailResponse,
    VacancyFilters,
    VacancyListResponse,
    VacancyMutationResponse,
    VacancyUpdateRequest,
)
from app.services.vacancies import VacancyError, VacancyService

router = APIRouter(prefix="/api", tags=["vacancies"])


async def _input(request: Request) -> tuple[dict[str, Any], UploadFile | None]:
    if "application/json" in request.headers.get("content-type", "").lower():
        return await body_mapping(request), None
    form = await request.form()
    payload: dict[str, Any] = {}
    flyer: UploadFile | None = None
    for key, value in form.multi_items():
        if key == "flyer" and isinstance(value, UploadFile):
            flyer = value
        elif isinstance(value, str):
            payload[key] = value
    return payload, flyer


async def _prepared(flyer: UploadFile | None) -> Any:
    if flyer is None:
        return None
    try:
        return prepare_avatar(flyer.filename, await flyer.read(5 * 1024 * 1024 + 1))
    finally:
        await flyer.close()


@router.post(
    "/get_vacancies",
    response_model=VacancyListResponse | VacancyDetailResponse | StatusResponse,
    openapi_extra=request_body_schema(VacancyFilters, required=False),
)
async def get_vacancies(request: Request) -> JSONResponse:
    try:
        filters = VacancyFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid vacancy filters", code="vacancy_filters_invalid"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-vacancies",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Vacancy service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session, database, lambda session: VacancyService(session).get(filters)
        )
    except VacancyError as exc:
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
    payload, flyer = input_data or await _input(request)
    try:
        model: Any = {
            "create": VacancyCreateRequest,
            "update": VacancyUpdateRequest,
            "delete": VacancyDeleteRequest,
        }[operation]
        parsed = model.model_validate(payload)
        prepared = await _prepared(flyer)
        if operation == "delete" and prepared is not None:
            raise AvatarUploadError("A flyer is not accepted when deleting a vacancy")
    except (KeyError, ValidationError, AvatarUploadError):
        if flyer is not None:
            await flyer.close()
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid vacancy fields or flyer",
                code="vacancy_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-vacancy",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Vacancy service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:

        def call(session: Any) -> VacancyMutationResponse:
            service = VacancyService(session, VacancyStorage(settings.upload_root))
            if operation == "create":
                return service.create(principal.user_id, parsed, prepared)
            if operation == "update":
                return service.update(principal.user_id, parsed, prepared)
            return service.delete(principal.user_id, parsed)

        result = await run_in_threadpool(with_session, database, call)
    except VacancyError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Vacancy flyer storage is temporarily unavailable",
                code="vacancy_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_vacancy",
    response_model=VacancyMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(VacancyCreateRequest),
)
async def create_vacancy(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, "create")


@router.post(
    "/manage_vacancy",
    response_model=VacancyMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(VacancyUpdateRequest),
)
async def manage_vacancy(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, flyer = await _input(request)
    operation = str(payload.get("function_type") or "").casefold()
    if operation not in {"update", "delete"}:
        if flyer is not None:
            await flyer.close()
        return json_response(
            StatusResponse(
                status=400,
                message="function_type must be update or delete",
                code="vacancy_invalid_request",
            ),
            400,
        )
    return await _manage(request, principal, operation, (payload, flyer))
