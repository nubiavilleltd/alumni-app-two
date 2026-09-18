"""Web Push subscription and VAPID key endpoints."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from starlette.concurrency import run_in_threadpool

from app.api.common import body_mapping, enforce_rate_limit, json_response, with_session
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.db.session import Database
from app.schemas.auth import StatusResponse
from app.schemas.push import PushSubscriptionRequest, VapidKeyResponse
from app.services.push import PushService

router = APIRouter(prefix="/chat_api", tags=["push notifications"])


@router.get("/get_vapid_key", response_model=VapidKeyResponse | StatusResponse)
async def get_vapid_key(request: Request) -> JSONResponse:
    """Return the VAPID public key so clients can register a browser subscription."""
    settings: Settings = request.app.state.settings
    public_key = settings.vapid_public_key
    if not public_key:
        return json_response(
            StatusResponse(status=503, message="Push notifications are not configured"), 503
        )
    return json_response(
        VapidKeyResponse(status=200, message="VAPID key", public_key=public_key), 200
    )


@router.post("/register_push_subscription", response_model=StatusResponse)
async def register_push_subscription(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    """Store or refresh the current account's browser push subscription."""
    try:
        subscription = PushSubscriptionRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="Invalid push subscription", code="push_invalid"),
            400,
        )
    await enforce_rate_limit(
        request,
        "register-push-subscription",
        principal.user_id,
        limit=30,
        window_seconds=15 * 60,
        unavailable_message="Push service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    await run_in_threadpool(
        with_session,
        database,
        lambda session: PushService(session, settings).register_subscription(
            principal.user_id, subscription
        ),
    )
    return json_response(StatusResponse(status=200, message="Push subscription registered"), 200)
