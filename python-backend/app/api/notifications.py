"""Authenticated notification routes backed by the current SQL data model."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query, Request
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
from app.db.session import Database
from app.schemas.auth import StatusResponse
from app.schemas.notifications import (
    GetNotificationsRequest,
    MarkNotificationReadRequest,
    NotificationListResponse,
    NotificationReadResponse,
)
from app.services.notifications import NotificationError, NotificationService

router = APIRouter(prefix="/api", tags=["notifications"])


async def _list_notifications(
    request: Request,
    principal: AccessPrincipal,
    filters: GetNotificationsRequest,
) -> JSONResponse:
    await enforce_rate_limit(
        request,
        "get-notifications",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Notification service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: NotificationService(session).list_notifications(
                principal.user_id, filters
            ),
        )
    except NotificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.get(
    "/get_notifications",
    response_model=NotificationListResponse | StatusResponse,
    operation_id="get_notifications_get",
)
async def get_notifications_get(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
    page: Annotated[int, Query(ge=1)] = 1,
    limit: Annotated[int, Query(ge=1, le=100)] = 20,
    unread_only: bool = False,
) -> JSONResponse:
    """Return the current member's bounded notification page."""
    return await _list_notifications(
        request,
        principal,
        GetNotificationsRequest(page=page, limit=limit, unread_only=unread_only),
    )


@router.post(
    "/get_notifications",
    response_model=NotificationListResponse | StatusResponse,
    openapi_extra=request_body_schema(GetNotificationsRequest, required=False),
    operation_id="get_notifications_post",
)
async def get_notifications_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Preserve the legacy POST path while ignoring caller-selected ownership."""
    try:
        filters = GetNotificationsRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid notification filters",
                code="notification_filters_invalid",
            ),
            400,
        )
    return await _list_notifications(request, principal, filters)


@router.post(
    "/mark_notification_read",
    response_model=NotificationReadResponse | StatusResponse,
    openapi_extra=request_body_schema(MarkNotificationReadRequest, required=False),
)
async def mark_notification_read(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Mark one current-member notification or every bounded unread row."""
    try:
        mark_request = MarkNotificationReadRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="Invalid notification ID",
                code="notification_mark_invalid",
            ),
            400,
        )
    await enforce_rate_limit(
        request,
        "mark-notification-read",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Notification service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: NotificationService(session).mark_read(
                principal.user_id, mark_request.notification_id
            ),
        )
    except NotificationError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)
