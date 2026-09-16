"""Public bounded marketplace listing reads."""

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
    MarketplaceStorage,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.marketplace import (
    MarketplaceCreateRequest,
    MarketplaceDeleteRequest,
    MarketplaceFilters,
    MarketplaceItemResponse,
    MarketplaceListResponse,
    MarketplaceMutationResponse,
    MarketplaceUpdateRequest,
)
from app.services.marketplace import MarketplaceError, MarketplaceService

router = APIRouter(prefix="/api", tags=["marketplace"])


async def _listing_input(
    request: Request,
) -> tuple[dict[str, Any], list[UploadFile]]:
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
    return payload, images


async def _prepared_images(images: list[UploadFile]) -> list[Any]:
    if len(images) > 6:
        raise AvatarUploadError("Marketplace listing may contain at most 6 images")
    try:
        return [
            prepare_avatar(image.filename, await image.read(5 * 1024 * 1024 + 1))
            for image in images
        ]
    finally:
        for image in images:
            await image.close()


async def _get_listings(request: Request, filters: MarketplaceFilters) -> JSONResponse:
    await enforce_rate_limit(
        request,
        "get-listings",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Marketplace service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session, database, lambda session: MarketplaceService(session).get(filters)
        )
    except MarketplaceError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_listings",
    response_model=MarketplaceListResponse | MarketplaceItemResponse | StatusResponse,
    openapi_extra=request_body_schema(MarketplaceFilters, required=False),
)
async def get_listings_post(request: Request) -> JSONResponse:
    try:
        filters = MarketplaceFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid marketplace filters",
                code="marketplace_filters_invalid",
            ),
            400,
        )
    return await _get_listings(request, filters)


async def _manage(
    request: Request,
    principal: AccessPrincipal,
    *,
    create: bool,
    input_data: tuple[dict[str, Any], list[UploadFile]] | None = None,
) -> JSONResponse:
    payload, images = input_data or await _listing_input(request)
    try:
        model = MarketplaceCreateRequest if create else MarketplaceUpdateRequest
        operation = model.model_validate(payload)
        prepared = await _prepared_images(images)
    except (ValidationError, AvatarUploadError):
        for image in images:
            await image.close()
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid marketplace listing fields or images",
                code="marketplace_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-listing",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Marketplace service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        storage = MarketplaceStorage(settings.upload_root)
        if create:
            assert isinstance(operation, MarketplaceCreateRequest)

            def call(session: Any) -> MarketplaceMutationResponse:
                return MarketplaceService(session, storage).create(
                    principal.user_id, operation, prepared, payload
                )
        else:
            assert isinstance(operation, MarketplaceUpdateRequest)

            def call(session: Any) -> MarketplaceMutationResponse:
                return MarketplaceService(session, storage).update(
                    principal.user_id, operation, prepared, payload
                )

        result = await run_in_threadpool(with_session, database, call)
    except MarketplaceError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Marketplace image storage is temporarily unavailable",
                code="marketplace_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_listing",
    response_model=MarketplaceMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(MarketplaceCreateRequest),
)
async def create_listing(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, create=True)


@router.post(
    "/manage_listing",
    response_model=MarketplaceMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(MarketplaceUpdateRequest),
)
async def manage_listing(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, images = await _listing_input(request)
    if payload.get("function_type") == "delete":
        try:
            if images:
                raise AvatarUploadError("Images are not accepted when deleting a listing")
            deletion = MarketplaceDeleteRequest.model_validate(payload)
        except (ValidationError, AvatarUploadError):
            for image in images:
                await image.close()
            return json_response(
                StatusResponse(
                    status=400,
                    message="Invalid marketplace listing fields",
                    code="marketplace_invalid_request",
                ),
                400,
            )
        await enforce_rate_limit(
            request,
            "manage-listing",
            principal.user_id,
            limit=30,
            window_seconds=5 * 60,
            unavailable_message="Marketplace service is temporarily unavailable",
        )
        database: Database = request.app.state.database
        settings: Settings = request.app.state.settings
        try:
            result = await run_in_threadpool(
                with_session,
                database,
                lambda session: MarketplaceService(
                    session, MarketplaceStorage(settings.upload_root)
                ).delete(principal.user_id, deletion),
            )
        except MarketplaceError as exc:
            return json_response(
                StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
                exc.http_status,
            )
        return json_response(result, 200)
    return await _manage(request, principal, create=False, input_data=(payload, images))
