"""Public event reads and current-policy event administration routes."""

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
    EventStorage,
    UploadStorageError,
    prepare_avatar,
)
from app.schemas.auth import StatusResponse
from app.schemas.events import (
    EventAttendeeFilters,
    EventAttendeeListResponse,
    EventCreateRequest,
    EventDeleteRequest,
    EventDetailResponse,
    EventFilters,
    EventListResponse,
    EventMutationResponse,
    EventRegistrationFormCreateRequest,
    EventRegistrationFormManageRequest,
    EventRegistrationFormMutationResponse,
    EventRegistrationFormsRequest,
    EventRegistrationFormsResponse,
    EventRegistrationRequest,
    EventRegistrationSubmissionDetailRequest,
    EventRegistrationSubmissionDetailResponse,
    EventRegistrationSubmissionFilters,
    EventRegistrationSubmissionResponse,
    EventRegistrationSubmissionsResponse,
    EventRegistrationWithFormsRequest,
    EventRsvpManageRequest,
    EventRsvpResponse,
    EventUpdateRequest,
)
from app.services.events import EventError, EventService

router = APIRouter(prefix="/api", tags=["events"])


async def _input(request: Request) -> tuple[dict[str, Any], UploadFile | None]:
    if "application/json" in request.headers.get("content-type", "").lower():
        return await body_mapping(request), None
    form = await request.form()
    payload: dict[str, Any] = {}
    banner: UploadFile | None = None
    for key, value in form.multi_items():
        if key == "event_banner" and isinstance(value, UploadFile):
            banner = value
        elif isinstance(value, str):
            payload[key] = value
    for key in {"end_date", "start_time", "end_time", "chapter_id", "year"}:
        if payload.get(key) == "":
            payload[key] = None
    return payload, banner


async def _prepared(banner: UploadFile | None) -> Any:
    if banner is None:
        return None
    try:
        return prepare_avatar(banner.filename, await banner.read(5 * 1024 * 1024 + 1))
    finally:
        await banner.close()


@router.post(
    "/get_events",
    response_model=EventListResponse | EventDetailResponse | StatusResponse,
    openapi_extra=request_body_schema(EventFilters, required=False),
)
async def get_events(request: Request) -> JSONResponse:
    try:
        filters = EventFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid event filters", code="event_filters_invalid"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-events",
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session, database, lambda session: EventService(session).get(filters)
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


async def _manage(
    request: Request,
    principal: AccessPrincipal,
    create: bool,
    input_data: tuple[dict[str, Any], UploadFile | None] | None = None,
) -> JSONResponse:
    payload, banner = input_data or await _input(request)
    try:
        parsed: Any = (EventCreateRequest if create else EventUpdateRequest).model_validate(payload)
        prepared = await _prepared(banner)
    except (ValidationError, AvatarUploadError):
        if banner is not None:
            await banner.close()
        return json_response(
            StatusResponse(
                status=400, message="Invalid event fields or banner", code="event_invalid_request"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-event",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:

        def call(session: Any) -> EventMutationResponse:
            service = EventService(session, EventStorage(settings.upload_root))
            return (
                service.create(principal.user_id, parsed, prepared)
                if create
                else service.update(principal.user_id, parsed, prepared)
            )

        result = await run_in_threadpool(with_session, database, call)
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Event banner storage is temporarily unavailable",
                code="event_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.post(
    "/create_event",
    response_model=EventMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(EventCreateRequest),
)
async def create_event(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _manage(request, principal, True)


@router.post(
    "/manage_event",
    response_model=EventMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(EventUpdateRequest),
)
async def manage_event(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    payload, banner = await _input(request)
    if str(payload.get("function_type") or "").casefold() == "delete":
        try:
            if banner is not None:
                raise AvatarUploadError("A banner is not accepted when deleting an event")
            deletion = EventDeleteRequest.model_validate(payload)
        except (ValidationError, AvatarUploadError):
            if banner is not None:
                await banner.close()
            return json_response(
                StatusResponse(
                    status=400, message="Invalid event fields", code="event_invalid_request"
                ),
                400,
            )
        await enforce_rate_limit(
            request,
            "manage-event",
            principal.user_id,
            limit=30,
            window_seconds=5 * 60,
            unavailable_message="Event service is temporarily unavailable",
        )
        database: Database = request.app.state.database
        settings: Settings = request.app.state.settings
        try:
            result = await run_in_threadpool(
                with_session,
                database,
                lambda session: EventService(session, EventStorage(settings.upload_root)).delete(
                    principal.user_id, deletion
                ),
            )
        except EventError as exc:
            return json_response(
                StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
                exc.http_status,
            )
        return json_response(result, 200)
    return await _manage(request, principal, False, (payload, banner))


@router.post(
    "/register_event",
    response_model=EventRsvpResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationRequest),
)
async def register_event(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Register only the current active principal for an available public event."""
    try:
        registration = EventRegistrationRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid event registration", code="event_rsvp_invalid_request"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "register-event",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).register(principal.user_id, registration),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/manage_event_rsvp",
    response_model=EventRsvpResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRsvpManageRequest),
)
async def manage_event_rsvp(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Update or cancel only the current principal's existing event RSVP."""
    try:
        rsvp_request = EventRsvpManageRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid RSVP update", code="event_rsvp_invalid_request"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-event-rsvp",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).manage_rsvp(principal.user_id, rsvp_request),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_event_attendees",
    response_model=EventAttendeeListResponse | StatusResponse,
    openapi_extra=request_body_schema(EventAttendeeFilters),
)
async def get_event_attendees(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Return attendee contact data only to a freshly authorized event administrator."""
    try:
        filters = EventAttendeeFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid attendee filters",
                code="event_attendees_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-event-attendees",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).attendees(principal.user_id, filters),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/create_event_registration_form",
    response_model=EventRegistrationFormMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationFormCreateRequest),
)
async def create_event_registration_form(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Create a bounded event form only after a current database event-admin check."""
    try:
        payload = EventRegistrationFormCreateRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid event registration form",
                code="event_form_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "create-event-registration-form",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).create_registration_form(
                principal.user_id, payload
            ),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/manage_event_registration_form",
    response_model=EventRegistrationFormMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationFormManageRequest),
)
async def manage_event_registration_form(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Archive or version event forms/questions with a current database permission check."""
    try:
        payload = EventRegistrationFormManageRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid event registration form operation",
                code="event_form_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "manage-event-registration-form",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).manage_registration_form(
                principal.user_id, payload
            ),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_event_registration_forms",
    response_model=EventRegistrationFormsResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationFormsRequest),
)
async def get_event_registration_forms(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Return active forms only to a current active member for a registrable event."""
    try:
        payload = EventRegistrationFormsRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid event registration form query",
                code="event_form_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-event-registration-forms",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).get_registration_forms(
                principal.user_id, payload
            ),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/register_event_with_forms",
    response_model=EventRegistrationSubmissionResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationWithFormsRequest),
)
async def register_event_with_forms(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Save a current member's RSVP and structured answers in the same transaction."""
    try:
        payload = EventRegistrationWithFormsRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400, message="Invalid event registration", code="event_form_answers_invalid"
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "register-event-with-forms",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).register_with_forms(principal.user_id, payload),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_event_registration_submissions",
    response_model=EventRegistrationSubmissionsResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationSubmissionFilters),
)
async def get_event_registration_submissions(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """List attendee PII and answer presence only for a current event administrator."""
    try:
        payload = EventRegistrationSubmissionFilters.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid event registration query",
                code="event_form_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-event-registration-submissions",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).registration_submissions(
                principal.user_id, payload
            ),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/get_event_registration_submission_detail",
    response_model=EventRegistrationSubmissionDetailResponse | StatusResponse,
    openapi_extra=request_body_schema(EventRegistrationSubmissionDetailRequest),
)
async def get_event_registration_submission_detail(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Return one event-scoped, historical answer snapshot only to an event administrator."""
    try:
        payload = EventRegistrationSubmissionDetailRequest.model_validate(
            await body_mapping(request)
        )
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid event registration detail query",
                code="event_form_invalid_request",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "get-event-registration-submission-detail",
        principal.user_id,
        limit=60,
        window_seconds=5 * 60,
        unavailable_message="Event service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: EventService(session).registration_submission_detail(
                principal.user_id, payload
            ),
        )
    except EventError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)
