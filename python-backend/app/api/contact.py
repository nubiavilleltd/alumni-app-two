"""Public contact-form endpoint."""

from __future__ import annotations

from typing import Any

from fastapi import APIRouter, Request
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
from app.db.session import Database
from app.integrations.mail import Mailer
from app.schemas.auth import StatusResponse
from app.schemas.contact import ContactCreateRequest, ContactResponse
from app.services.contact import ContactService

router = APIRouter(prefix="/api", tags=["Contact"])


@router.post(
    "/contact_us",
    response_model=ContactResponse | StatusResponse,
    openapi_extra=request_body_schema(ContactCreateRequest),
)
async def contact_us(request: Request) -> JSONResponse:
    """Accept a bounded, rate-limited public contact message.

    The legacy reusable application key grants nothing here; delivery is throttled
    per peer and validation is strict, while the message and manager notifications
    are server-controlled.
    """
    await enforce_rate_limit(
        request,
        "contact-us",
        limit=5,
        window_seconds=60 * 60,
        unavailable_message="Contact service is temporarily unavailable",
    )
    try:
        model = ContactCreateRequest.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(
                status=400,
                message="All fields are required: firstName, lastName, email, message",
                code="contact_invalid_request",
            ),
            400,
        )

    database: Database = request.app.state.database
    mailer: Mailer | None = getattr(request.app.state, "mailer", None)

    def execute(session: Any) -> ContactResponse:
        return ContactService(session, mailer).submit(model)

    result = await run_in_threadpool(with_session, database, execute)
    return json_response(result, 200)
