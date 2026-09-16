"""Member lifecycle HTTP endpoints."""

from __future__ import annotations

import ipaddress
from typing import Annotated, Any

from fastapi import APIRouter, Depends, Query, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import UploadFile
from starlette.exceptions import HTTPException

from app.api.common import (
    body_mapping,
    enforce_rate_limit,
    json_response,
    request_body_schema,
    with_session,
)
from app.authorization.dependencies import AccessPrincipal, optional_access, require_access
from app.core.config import Settings
from app.db.session import Database
from app.integrations.alumni_import import (
    MAX_ALUMNI_IMPORT_BYTES,
    MAX_ALUMNI_MULTIPART_BYTES,
    AlumniImportFileError,
    parse_alumni_import_file,
    parse_alumni_import_json,
    parse_chapter_id,
)
from app.integrations.geography_import import (
    MAX_GEOGRAPHY_IMPORT_BYTES,
    MAX_GEOGRAPHY_MULTIPART_BYTES,
    GeographyImportFileError,
    parse_geography_import,
)
from app.integrations.mail import Mailer
from app.integrations.uploads import (
    AvatarStorage,
    AvatarUploadError,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.members import (
    AdministrativeMemberListResponse,
    AlumniImportResponse,
    AlumniStatsResponse,
    BirthdayListResponse,
    ChapterListResponse,
    CityListResponse,
    GetBirthdaysRequest,
    GetChaptersRequest,
    GetMembersRequest,
    GetProfileVisibilityRequest,
    GetSetupParametersRequest,
    GetUserProfileRequest,
    GetVouchersRequest,
    GetZoneMembersRequest,
    ManageCityRequest,
    ManageCityResponse,
    ManageMemberAccountRequest,
    ManageMemberAccountResponse,
    ManageZoneRequest,
    ManageZoneResponse,
    MemberApprovalRequest,
    MemberApprovalResponse,
    MemberDirectoryResponse,
    MemberZone,
    MyZoneResponse,
    PendingVouchesResponse,
    ProfileVisibilityResponse,
    SetupParametersResponse,
    UpdateProfileRequest,
    UpdateProfileResponse,
    UpdateProfileVisibilityRequest,
    UploadZonesCitiesResponse,
    UserChapterResponse,
    UserProfileResponse,
    VouchActionRequest,
    VouchActionResponse,
    VoucherListResponse,
    ZoneListResponse,
    ZoneMemberListResponse,
)
from app.services.members import (
    AlumniImportError,
    AlumniStatsError,
    BirthdayLookupError,
    ChapterLookupError,
    GeographyManagementError,
    MemberAccountError,
    MemberApprovalError,
    MemberDirectoryError,
    MemberProfileError,
    MemberService,
    ProfileUpdateError,
    ProfileVisibilityError,
    SetupParametersError,
    VoucherError,
    ZoneMembershipError,
)

router = APIRouter(prefix="/api", tags=["members"])


def _birthday_request(
    *,
    scope: str | None,
    days: str | None,
    month: str | None,
    limit: str | None,
    include_self: str | None,
) -> GetBirthdaysRequest:
    """Validate raw query strings so GET and POST return one legacy-style 400 contract."""
    return GetBirthdaysRequest.model_validate(
        {
            "scope": scope,
            "days": days,
            "month": month,
            "limit": limit,
            "include_self": include_self,
        }
    )


async def _alumni_stats_response(
    request: Request,
    principal: AccessPrincipal,
) -> JSONResponse:
    """Share the GET/POST aggregate implementation and one rate-limit bucket."""
    await enforce_rate_limit(
        request,
        "get-alumni-stats",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_alumni_stats(principal.user_id),
        )
    except AlumniStatsError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.get(
    "/get_alumni_stats",
    response_model=AlumniStatsResponse | StatusResponse,
)
async def get_alumni_stats(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return bounded member-directory summary counts."""
    return await _alumni_stats_response(request, principal)


@router.post(
    "/get_alumni_stats",
    response_model=AlumniStatsResponse | StatusResponse,
)
async def get_alumni_stats_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Preserve the legacy POST access pattern for the same aggregate response."""
    return await _alumni_stats_response(request, principal)


async def _birthday_response(
    request: Request,
    principal: AccessPrincipal,
    *,
    scope: str | None,
    days: str | None,
    month: str | None,
    limit: str | None,
    include_self: str | None,
) -> JSONResponse:
    """Share the protected birthday implementation and rate-limit bucket."""
    try:
        birthday_request = _birthday_request(
            scope=scope,
            days=days,
            month=month,
            limit=limit,
            include_self=include_self,
        )
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid birthday filters",
                code="birthdays_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-birthdays",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_birthdays(
                principal.user_id,
                birthday_request,
            ),
        )
    except BirthdayLookupError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return JSONResponse(status_code=200, content=result.model_dump(mode="json"))


@router.get(
    "/get_birthdays",
    response_model=BirthdayListResponse | StatusResponse,
)
async def get_birthdays(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
    scope: Annotated[str | None, Query()] = None,
    days: Annotated[str | None, Query()] = None,
    month: Annotated[str | None, Query()] = None,
    limit: Annotated[str | None, Query()] = None,
    include_self: Annotated[str | None, Query()] = None,
) -> JSONResponse:
    """Return a bounded birthday window using query parameters."""
    return await _birthday_response(
        request,
        principal,
        scope=scope,
        days=days,
        month=month,
        limit=limit,
        include_self=include_self,
    )


@router.post(
    "/get_birthdays",
    response_model=BirthdayListResponse | StatusResponse,
)
async def get_birthdays_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
    scope: Annotated[str | None, Query()] = None,
    days: Annotated[str | None, Query()] = None,
    month: Annotated[str | None, Query()] = None,
    limit: Annotated[str | None, Query()] = None,
    include_self: Annotated[str | None, Query()] = None,
) -> JSONResponse:
    """Preserve the active frontend's bodyless POST compatibility call."""
    return await _birthday_response(
        request,
        principal,
        scope=scope,
        days=days,
        month=month,
        limit=limit,
        include_self=include_self,
    )


async def _chapter_response(
    request: Request,
    principal: AccessPrincipal | None,
    user_id: int | None,
) -> JSONResponse:
    """Share public list and protected assignment handling across GET and POST."""
    if user_id is not None and principal is None:
        principal = require_access(request)
    rate_identity: object = principal.user_id if principal is not None else "public"
    await enforce_rate_limit(
        request,
        "get-chapters",
        rate_identity,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_chapters(
                principal.user_id if principal is not None else None,
                user_id,
            ),
        )
    except ChapterLookupError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    if isinstance(result, UserChapterResponse) and result.chapter is None:
        content = result.model_dump(mode="json", exclude_none=True)
        content["chapter"] = None
        return JSONResponse(status_code=200, content=content)
    return json_response(result, 200)


@router.get(
    "/get_chapters",
    response_model=ChapterListResponse | UserChapterResponse | StatusResponse,
)
async def get_chapters(
    request: Request,
    principal: Annotated[AccessPrincipal | None, Depends(optional_access)],
    user_id: Annotated[int | None, Query(gt=0)] = None,
) -> JSONResponse:
    """List enabled chapters publicly or return an authorized user assignment."""
    return await _chapter_response(request, principal, user_id)


@router.post(
    "/get_chapters",
    response_model=ChapterListResponse | UserChapterResponse | StatusResponse,
    openapi_extra=request_body_schema(GetChaptersRequest, required=False),
)
async def get_chapters_post(
    request: Request,
    principal: Annotated[AccessPrincipal | None, Depends(optional_access)],
) -> JSONResponse:
    """Preserve the legacy optional JSON/form user assignment lookup."""
    try:
        chapter_request = GetChaptersRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="user_id must be a positive integer",
                code="chapter_invalid_request",
            ),
            400,
        )
    return await _chapter_response(request, principal, chapter_request.user_id)


async def _city_list_response(request: Request) -> JSONResponse:
    """Share the public city catalogue across GET and current frontend POST."""
    await enforce_rate_limit(
        request,
        "get-cities",
        "public",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    result = await run_in_threadpool(
        with_session,
        database,
        lambda session: MemberService(session, settings).get_cities(),
    )
    return json_response(result, 200)


@router.get(
    "/get_cities",
    response_model=CityListResponse | StatusResponse,
)
async def get_cities(request: Request) -> JSONResponse:
    """List public city/zone metadata without a reusable application key."""
    return await _city_list_response(request)


@router.post(
    "/get_cities",
    response_model=CityListResponse | StatusResponse,
)
async def get_cities_post(request: Request) -> JSONResponse:
    """Preserve the active frontend POST pattern for the public city catalogue."""
    return await _city_list_response(request)


async def _zone_list_response(request: Request) -> JSONResponse:
    """Share privacy-aware welfare zones across GET and current frontend POST."""
    await enforce_rate_limit(
        request,
        "get-zones",
        "public",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    result = await run_in_threadpool(
        with_session,
        database,
        lambda session: MemberService(session, settings).get_zones(),
    )
    content = result.model_dump(mode="json", exclude_none=True)
    for index, zone in enumerate(result.data):
        if zone.coordinator is None:
            content["data"][index]["coordinator"] = None
    return JSONResponse(status_code=200, content=content)


@router.get(
    "/get_zones",
    response_model=ZoneListResponse | StatusResponse,
)
async def get_zones(request: Request) -> JSONResponse:
    """List public zones without coordinator email or private profile fields."""
    return await _zone_list_response(request)


@router.post(
    "/get_zones",
    response_model=ZoneListResponse | StatusResponse,
)
async def get_zones_post(request: Request) -> JSONResponse:
    """Preserve the active welfare frontend POST access pattern."""
    return await _zone_list_response(request)


@router.post(
    "/manage_zone",
    response_model=ManageZoneResponse | StatusResponse,
    openapi_extra=request_body_schema(ManageZoneRequest),
)
async def manage_zone(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Apply one authorized, integrity-checked zone mutation."""
    try:
        zone_request = ManageZoneRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Valid action and required zone fields are required",
                code="zone_management_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-zone",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).manage_zone(
                principal.user_id,
                zone_request,
            ),
        )
    except GeographyManagementError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/manage_city",
    response_model=ManageCityResponse | StatusResponse,
    openapi_extra=request_body_schema(ManageCityRequest),
)
async def manage_city(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Apply one authorized, integrity-checked city mutation."""
    try:
        city_request = ManageCityRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Valid action and required city fields are required",
                code="city_management_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-city",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).manage_city(
                principal.user_id,
                city_request,
            ),
        )
    except GeographyManagementError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


def _alumni_import_openapi() -> dict[str, Any]:
    """Describe the two bounded import encodings and their allowlisted fields."""
    record_properties = {
        "email": {"type": "string", "format": "email", "maxLength": 150},
        "last_name": {"type": "string", "maxLength": 50},
        "first_name": {"type": "string", "maxLength": 80},
        "name_in_school": {"type": "string", "maxLength": 200},
        "phone": {"type": "string", "maxLength": 20},
        "alternative_phone": {"type": "string", "maxLength": 20},
        "birth_date": {"type": "string", "format": "date"},
        "graduation_year": {"type": "integer", "minimum": 1966},
        "house_color": {"type": "string", "maxLength": 50},
        "is_coordinator": {"type": "boolean", "description": "Recorded but never granted"},
        "residential_address": {"type": "string", "maxLength": 5000},
        "area": {"type": "string", "maxLength": 100},
        "city": {"type": "string", "maxLength": 100},
        "employment_status": {"type": "string", "maxLength": 100},
        "occupation": {"type": "string", "maxLength": 5000},
        "industry_sector": {"type": "string", "maxLength": 5000},
        "years_of_experience": {"type": "string", "maxLength": 50},
        "is_volunteer": {"type": "boolean"},
        "timestamp": {"type": "string", "description": "Accepted as source metadata only"},
    }
    return {
        "requestBody": {
            "required": True,
            "content": {
                "application/json": {
                    "schema": {
                        "type": "object",
                        "additionalProperties": False,
                        "required": ["chapter_id", "records"],
                        "properties": {
                            "chapter_id": {"type": "integer", "minimum": 1},
                            "records": {
                                "type": "array",
                                "minItems": 1,
                                "maxItems": 500,
                                "items": {
                                    "type": "object",
                                    "additionalProperties": False,
                                    "required": [
                                        "email",
                                        "last_name",
                                        "first_name",
                                        "graduation_year",
                                        "city",
                                    ],
                                    "properties": record_properties,
                                },
                            },
                        },
                    }
                },
                "multipart/form-data": {
                    "schema": {
                        "type": "object",
                        "additionalProperties": False,
                        "required": ["chapter_id", "file"],
                        "properties": {
                            "chapter_id": {"type": "integer", "minimum": 1},
                            "file": {"type": "string", "format": "binary"},
                        },
                    }
                },
            },
        }
    }


async def _bounded_alumni_json(request: Request) -> bytes:
    """Read a JSON body with the same in-app limit as uploaded roster files."""
    content_length = request.headers.get("content-length")
    if content_length:
        try:
            if int(content_length) > MAX_ALUMNI_IMPORT_BYTES:
                raise AlumniImportFileError(
                    "alumni_import_too_large",
                    "The import request must not exceed 2 MB",
                    413,
                )
        except ValueError as exc:
            raise AlumniImportFileError(
                "alumni_import_invalid_request",
                "The import request is invalid",
                400,
            ) from exc
    chunks: list[bytes] = []
    total = 0
    async for chunk in request.stream():
        total += len(chunk)
        if total > MAX_ALUMNI_IMPORT_BYTES:
            raise AlumniImportFileError(
                "alumni_import_too_large",
                "The import request must not exceed 2 MB",
                413,
            )
        chunks.append(chunk)
    return b"".join(chunks)


async def _alumni_import_upload(
    request: Request,
) -> tuple[int, str | None, str | None, bytes]:
    """Read exactly one chapter field and one bounded roster file."""
    content_length = request.headers.get("content-length")
    if content_length:
        try:
            if int(content_length) > MAX_ALUMNI_MULTIPART_BYTES:
                raise AlumniImportFileError(
                    "alumni_import_too_large",
                    "The uploaded file must not exceed 2 MB",
                    413,
                )
        except ValueError as exc:
            raise AlumniImportFileError(
                "alumni_import_invalid_request",
                "The upload request is invalid",
                400,
            ) from exc
    try:
        form = await request.form(
            max_files=1,
            max_fields=1,
            max_part_size=MAX_ALUMNI_IMPORT_BYTES + 1,
        )
    except HTTPException as exc:
        raise AlumniImportFileError(
            "alumni_import_invalid_request",
            "The upload request is invalid",
            400,
        ) from exc
    items = list(form.multi_items())
    if len(items) != 2 or {name for name, _value in items} != {"chapter_id", "file"}:
        raise AlumniImportFileError(
            "alumni_import_invalid_request",
            "Exactly one chapter_id and one CSV or XLSX file are required",
            400,
        )
    chapter_value: object | None = None
    upload: UploadFile | None = None
    for name, value in items:
        if name == "chapter_id" and isinstance(value, str):
            chapter_value = value
        elif name == "file" and isinstance(value, UploadFile):
            upload = value
        else:
            raise AlumniImportFileError(
                "alumni_import_invalid_request",
                "The upload request is invalid",
                400,
            )
    if chapter_value is None or upload is None:
        raise AlumniImportFileError(
            "alumni_import_invalid_request",
            "Exactly one chapter_id and one CSV or XLSX file are required",
            400,
        )
    chapter_id = parse_chapter_id(chapter_value)
    try:
        content = await upload.read(MAX_ALUMNI_IMPORT_BYTES + 1)
    finally:
        await upload.close()
    if len(content) > MAX_ALUMNI_IMPORT_BYTES:
        raise AlumniImportFileError(
            "alumni_import_too_large",
            "The uploaded file must not exceed 2 MB",
            413,
        )
    return chapter_id, upload.filename, upload.content_type, content


def _alumni_import_peer_ip(request: Request) -> str:
    """Fit the direct peer into the unchanged legacy IPv4-sized account column."""
    peer = request.client.host if request.client else ""
    try:
        normalized = ipaddress.ip_address(peer).compressed
    except ValueError:
        return "unavailable"
    return normalized if len(normalized) <= 15 else "unavailable"


@router.post(
    "/import_alumni",
    response_model=AlumniImportResponse | StatusResponse,
    openapi_extra=_alumni_import_openapi(),
)
async def import_alumni(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Apply one bounded, current-role-authorized alumni roster atomically."""
    await enforce_rate_limit(
        request,
        "import-alumni",
        principal.user_id,
        limit=5,
        window_seconds=15 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    content_type = request.headers.get("content-type", "").split(";", 1)[0].casefold()
    try:
        if content_type == "application/json":
            content = await _bounded_alumni_json(request)
            chapter_id, rows = await run_in_threadpool(parse_alumni_import_json, content)
        elif content_type == "multipart/form-data":
            chapter_id, filename, declared_type, content = await _alumni_import_upload(request)
            rows = await run_in_threadpool(
                parse_alumni_import_file,
                filename,
                content,
                declared_type,
            )
        else:
            raise AlumniImportFileError(
                "alumni_import_unsupported_media_type",
                "Use application/json or multipart/form-data",
                415,
            )
    except AlumniImportFileError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).import_alumni(
                principal.user_id,
                chapter_id,
                rows,
                _alumni_import_peer_ip(request),
            ),
        )
    except AlumniImportError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


def _geography_import_openapi() -> dict[str, Any]:
    """Describe the single allowlisted binary field accepted by the bulk route."""
    return {
        "requestBody": {
            "required": True,
            "content": {
                "multipart/form-data": {
                    "schema": {
                        "type": "object",
                        "additionalProperties": False,
                        "required": ["file"],
                        "properties": {"file": {"type": "string", "format": "binary"}},
                    }
                }
            },
        }
    }


async def _geography_import_upload(request: Request) -> tuple[str | None, str | None, bytes]:
    """Read one bounded multipart file only after authentication and throttling."""
    content_type = request.headers.get("content-type", "")
    if "multipart/form-data" not in content_type.casefold():
        raise GeographyImportFileError(
            "geography_import_multipart_required",
            "Upload the geography file as multipart form field 'file'",
            415,
        )
    content_length = request.headers.get("content-length")
    if content_length:
        try:
            if int(content_length) > MAX_GEOGRAPHY_MULTIPART_BYTES:
                raise GeographyImportFileError(
                    "geography_import_too_large",
                    "The uploaded file must not exceed 2 MB",
                    413,
                )
        except ValueError as exc:
            raise GeographyImportFileError(
                "geography_import_invalid_request",
                "The upload request is invalid",
                400,
            ) from exc
    try:
        form = await request.form(
            max_files=1,
            max_fields=0,
            max_part_size=MAX_GEOGRAPHY_IMPORT_BYTES + 1,
        )
    except HTTPException as exc:
        raise GeographyImportFileError(
            "geography_import_invalid_request",
            "The upload request is invalid",
            400,
        ) from exc

    items = list(form.multi_items())
    if len(items) != 1 or items[0][0] != "file" or not isinstance(items[0][1], UploadFile):
        raise GeographyImportFileError(
            "geography_import_file_required",
            "Exactly one CSV or XLSX file field is required",
            400,
        )
    upload = items[0][1]
    try:
        content = await upload.read(MAX_GEOGRAPHY_IMPORT_BYTES + 1)
    finally:
        await upload.close()
    if len(content) > MAX_GEOGRAPHY_IMPORT_BYTES:
        raise GeographyImportFileError(
            "geography_import_too_large",
            "The uploaded file must not exceed 2 MB",
            413,
        )
    return upload.filename, upload.content_type, content


@router.post(
    "/upload_zones_cities",
    response_model=UploadZonesCitiesResponse | StatusResponse,
    openapi_extra=_geography_import_openapi(),
)
async def upload_zones_cities(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Apply one bounded, current-role-authorized geography import atomically."""
    await enforce_rate_limit(
        request,
        "upload-zones-cities",
        principal.user_id,
        limit=10,
        window_seconds=15 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    try:
        filename, content_type, content = await _geography_import_upload(request)
        rows = await run_in_threadpool(
            parse_geography_import,
            filename,
            content,
            content_type,
        )
    except GeographyImportFileError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).upload_zones_cities(
                principal.user_id,
                rows,
            ),
        )
    except GeographyManagementError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


def _zone_members_json(result: ZoneMemberListResponse) -> JSONResponse:
    """Preserve an explicit null coordinator while omitting private optional fields."""
    content = result.model_dump(mode="json", exclude_none=True)
    if result.zone.coordinator is None:
        content["zone"]["coordinator"] = None
    return JSONResponse(status_code=200, content=content)


async def _zone_members_response(
    request: Request,
    principal: AccessPrincipal,
    zone_request: GetZoneMembersRequest,
) -> JSONResponse:
    """Share one protected roster contract and rate-limit bucket across methods."""
    await enforce_rate_limit(
        request,
        "get-users-by-zone",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_zone_members(
                principal.user_id,
                zone_request,
            ),
        )
    except ZoneMembershipError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return _zone_members_json(result)


@router.get(
    "/get_users_by_zone",
    response_model=ZoneMemberListResponse | StatusResponse,
)
async def get_users_by_zone(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
    zone_id: int | None = None,
    zone: Annotated[str | None, Query(max_length=100)] = None,
    page: int = 1,
    limit: int = 50,
) -> JSONResponse:
    """Return an authenticated privacy-filtered roster by zone ID or exact name."""
    try:
        zone_request = GetZoneMembersRequest.model_validate(
            {"zone_id": zone_id, "zone": zone, "page": page, "limit": limit}
        )
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid zone member filters",
                code="zone_members_invalid_request",
            ),
            400,
        )
    return await _zone_members_response(request, principal, zone_request)


@router.post(
    "/get_users_by_zone",
    response_model=ZoneMemberListResponse | StatusResponse,
    openapi_extra=request_body_schema(GetZoneMembersRequest, required=True),
)
async def get_users_by_zone_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Accept bounded JSON/form selectors for compatibility with hidden legacy clients."""
    try:
        zone_request = GetZoneMembersRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid zone member filters",
                code="zone_members_invalid_request",
            ),
            400,
        )
    return await _zone_members_response(request, principal, zone_request)


def _my_zone_json(result: MyZoneResponse) -> JSONResponse:
    """Preserve explicit coordinator null in the resolved self-zone response."""
    content = result.model_dump(mode="json", exclude_none=True)
    if result.city is None:
        content["city"] = None
    if isinstance(result.zone, MemberZone) and result.zone.coordinator is None:
        content["zone"]["coordinator"] = None
    return JSONResponse(status_code=200, content=content)


async def _my_zone_response(request: Request, principal: AccessPrincipal) -> JSONResponse:
    """Share the protected self-zone lookup across GET and POST."""
    await enforce_rate_limit(
        request,
        "get-my-zone",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_my_zone(principal.user_id),
        )
    except ZoneMembershipError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return _my_zone_json(result)


@router.get(
    "/get_my_zone",
    response_model=MyZoneResponse | StatusResponse,
)
async def get_my_zone(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return the active principal's deterministic city-to-zone assignment."""
    return await _my_zone_response(request, principal)


@router.post(
    "/get_my_zone",
    response_model=MyZoneResponse | StatusResponse,
)
async def get_my_zone_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Preserve a no-body POST compatibility path for the self-zone lookup."""
    return await _my_zone_response(request, principal)


async def _voucher_list_response(
    request: Request,
    voucher_request: GetVouchersRequest,
) -> JSONResponse:
    """Share bounded public voucher discovery across GET and POST."""
    await enforce_rate_limit(
        request,
        "get-vouchers",
        voucher_request.graduation_year or "all",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    result = await run_in_threadpool(
        with_session,
        database,
        lambda session: MemberService(session, settings).get_vouchers(voucher_request),
    )
    return json_response(result, 200)


@router.get(
    "/get_vouchers",
    response_model=VoucherListResponse | StatusResponse,
)
async def get_vouchers(
    request: Request,
    graduation_year: str | None = None,
) -> JSONResponse:
    """List active voucher candidates without exposing public contact details."""
    try:
        voucher_request = GetVouchersRequest.model_validate({"graduation_year": graduation_year})
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="graduation_year must be between 1966 and the current year",
                code="voucher_list_invalid_request",
            ),
            400,
        )
    return await _voucher_list_response(request, voucher_request)


@router.post(
    "/get_vouchers",
    response_model=VoucherListResponse | StatusResponse,
    openapi_extra=request_body_schema(GetVouchersRequest, required=False),
)
async def get_vouchers_post(request: Request) -> JSONResponse:
    """Preserve the current frontend POST access pattern with optional year filtering."""
    try:
        voucher_request = GetVouchersRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="graduation_year must be between 1966 and the current year",
                code="voucher_list_invalid_request",
            ),
            400,
        )
    return await _voucher_list_response(request, voucher_request)


async def _pending_vouches_response(
    request: Request,
    principal: AccessPrincipal,
) -> JSONResponse:
    """Share one current-voucher ownership check across GET and POST."""
    await enforce_rate_limit(
        request,
        "voucher-pending",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_pending_vouches(principal.user_id),
        )
    except VoucherError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.get(
    "/voucher_pending",
    response_model=PendingVouchesResponse | StatusResponse,
)
async def voucher_pending(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return pending registrations assigned to the current voucher."""
    return await _pending_vouches_response(request, principal)


@router.post(
    "/voucher_pending",
    response_model=PendingVouchesResponse | StatusResponse,
)
async def voucher_pending_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Preserve the active frontend POST access pattern for pending attestations."""
    return await _pending_vouches_response(request, principal)


@router.post(
    "/vouch_action",
    response_model=VouchActionResponse | StatusResponse,
    openapi_extra=request_body_schema(VouchActionRequest),
)
async def vouch_action(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Approve or deny only a pending vouch assigned to the current voucher."""
    try:
        action_request = VouchActionRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="vouch_id and action (approve|deny) are required",
                code="vouch_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "vouch-action",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings, mailer).decide_vouch(
                principal.user_id,
                action_request,
            ),
        )
    except VoucherError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_setup_parameters",
    response_model=SetupParametersResponse | StatusResponse,
    openapi_extra=request_body_schema(GetSetupParametersRequest),
)
async def get_setup_parameters(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return one explicit setup row to a current active account."""
    try:
        setup_request = GetSetupParametersRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="action_type parameter is required",
                code="setup_parameters_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-setup-parameters",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_setup_parameters(
                principal.user_id,
                setup_request.action_type,
            ),
        )
    except SetupParametersError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


def _profile_update_request_schema() -> dict[str, Any]:
    """Describe JSON/form fields plus the multipart avatar upload."""
    extra = request_body_schema(UpdateProfileRequest)
    multipart_schema = UpdateProfileRequest.model_json_schema()
    multipart_schema.setdefault("properties", {})["avatar"] = {
        "type": "string",
        "format": "binary",
    }
    extra["requestBody"]["content"]["multipart/form-data"]["schema"] = multipart_schema
    return extra


async def _profile_update_input(
    request: Request,
) -> tuple[dict[str, Any], UploadFile | None]:
    """Reconstruct nested profile form keys without accepting arbitrary upload fields."""
    content_type = request.headers.get("content-type", "").lower()
    if "application/json" in content_type:
        return await body_mapping(request), None
    form = await request.form()
    payload: dict[str, Any] = {}
    profile: dict[str, str] = {}
    avatar: UploadFile | None = None
    for key, value in form.multi_items():
        if key == "avatar" and isinstance(value, UploadFile):
            avatar = value
        elif isinstance(value, str):
            if key.startswith("profile[") and key.endswith("]"):
                profile[key[8:-1]] = value
            else:
                payload[key] = value
    if profile:
        payload["profile"] = profile
    return payload, avatar


@router.post(
    "/update_profile",
    response_model=UpdateProfileResponse | StatusResponse,
    openapi_extra=_profile_update_request_schema(),
)
async def update_profile(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Update allowlisted self or downward-target profile fields and an optional avatar."""
    payload, upload = await _profile_update_input(request)
    try:
        profile_request = UpdateProfileRequest.model_validate(payload)
        prepared_avatar = None
        if upload is not None:
            prepared_avatar = prepare_avatar(
                upload.filename,
                await upload.read(5 * 1024 * 1024 + 1),
            )
    except (ValidationError, AvatarUploadError) as exc:
        message = str(exc) if isinstance(exc, AvatarUploadError) else "Invalid profile fields"
        return json_response(
            StatusResponse(
                status=400,
                message=message,
                code="profile_update_invalid_request",
            ),
            400,
        )
    finally:
        if upload is not None:
            await upload.close()
    if (
        not profile_request.user_changes()
        and not profile_request.profile_changes()
        and prepared_avatar is None
    ):
        return json_response(
            StatusResponse(
                status=400,
                message="No fields provided to update",
                code="profile_update_empty",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "update-profile",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    storage = AvatarStorage(settings.upload_root)
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).update_profile(
                principal.user_id,
                profile_request,
                prepared_avatar,
                storage,
            ),
        )
    except ProfileUpdateError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Avatar storage is temporarily unavailable",
                code="profile_update_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/get_users_by_action",
    response_model=(MemberDirectoryResponse | AdministrativeMemberListResponse | StatusResponse),
    openapi_extra=request_body_schema(GetMembersRequest, required=False),
)
async def get_users_by_action(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return a bounded privacy-aware directory or downward admin listing."""
    try:
        members_request = GetMembersRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid member-list filters",
                code="members_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-users-by-action",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_members(
                principal.user_id,
                members_request,
            ),
        )
    except MemberDirectoryError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_profile_visibility",
    response_model=ProfileVisibilityResponse | StatusResponse,
    openapi_extra=request_body_schema(GetProfileVisibilityRequest, required=False),
)
async def get_profile_visibility(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return self or authorized target visibility settings."""
    try:
        visibility_request = GetProfileVisibilityRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="user_id must be a positive integer",
                code="visibility_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-profile-visibility",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_profile_visibility(
                principal.user_id,
                visibility_request.user_id,
            ),
        )
    except ProfileVisibilityError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/update_profile_visibility",
    response_model=ProfileVisibilityResponse | StatusResponse,
    openapi_extra=request_body_schema(UpdateProfileVisibilityRequest),
)
async def update_profile_visibility(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Merge allowlisted self or authorized target visibility settings."""
    try:
        visibility_request = UpdateProfileVisibilityRequest.model_validate(
            await body_mapping(request)
        )
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="At least one valid visibility field is required",
                code="visibility_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "update-profile-visibility",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).update_profile_visibility(
                principal.user_id,
                visibility_request.user_id,
                visibility_request.is_visible,
                visibility_request.field_changes(),
            ),
        )
    except ProfileVisibilityError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/manage_user_account",
    response_model=ManageMemberAccountResponse | StatusResponse,
    openapi_extra=request_body_schema(ManageMemberAccountRequest),
)
async def manage_user_account(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Apply one authorized account-state or reviewed role transition."""
    try:
        account_request = ManageMemberAccountRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Exactly one valid action or user_role is required",
                code="account_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-user-account",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings, mailer).manage_member_account(
                principal.user_id,
                account_request.user_id,
                account_request.action,
                account_request.user_role,
            ),
        )
    except MemberAccountError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/approve_user",
    response_model=MemberApprovalResponse | StatusResponse,
    openapi_extra=request_body_schema(MemberApprovalRequest),
)
async def approve_user(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Approve or reject a verified member through current database authorization."""
    try:
        approval_request = MemberApprovalRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="user_id and action (approve|reject) are required",
                code="approval_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "approve-user",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer = request.app.state.mailer
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings, mailer).decide_member_approval(
                principal.user_id,
                approval_request.user_id,
                approval_request.action,
                approval_request.reject_reason,
            ),
        )
    except MemberApprovalError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_user_profile",
    response_model=UserProfileResponse | StatusResponse,
    openapi_extra=request_body_schema(GetUserProfileRequest, required=False),
)
async def get_user_profile(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Return the authenticated member's profile or an authorized selected profile."""
    try:
        profile_request = GetUserProfileRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="user_id must be a positive integer"),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-user-profile",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: MemberService(session, settings).get_user_profile(
                principal.user_id,
                profile_request.user_id,
            ),
        )
    except MemberProfileError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)
