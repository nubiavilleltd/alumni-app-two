"""Member lifecycle HTTP endpoints."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Request
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
from app.core.config import Settings
from app.db.session import Database
from app.schemas.auth import StatusResponse
from app.schemas.members import GetUserProfileRequest, UserProfileResponse
from app.services.members import MemberProfileError, MemberService

router = APIRouter(prefix="/api", tags=["members"])


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
