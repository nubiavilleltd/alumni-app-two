"""Shared HTTP compatibility helpers for migrated API routes."""

from __future__ import annotations

from collections.abc import Callable
from typing import Any

import structlog
from fastapi import HTTPException, Request, status
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from sqlalchemy.orm import Session
from starlette.concurrency import run_in_threadpool

from app.core.errors import RateLimitBackendError, RateLimitExceededError
from app.core.rate_limit import RateLimiter, rate_limit_key
from app.db.session import Database

logger = structlog.get_logger(__name__)


def with_session[ResultT](database: Database, operation: Callable[[Session], ResultT]) -> ResultT:
    """Run a complete use case within one worker thread and one session."""
    sessions = database.sessions()
    session = next(sessions)
    try:
        return operation(session)
    finally:
        sessions.close()


async def body_mapping(request: Request) -> dict[str, Any]:
    """Read either a JSON object or an HTML form without logging secrets."""
    content_type = request.headers.get("content-type", "").lower()
    if "application/json" in content_type:
        try:
            body = await request.json()
        except (ValueError, UnicodeDecodeError):
            return {}
        return body if isinstance(body, dict) else {}
    form = await request.form()
    return {key: value for key, value in form.multi_items() if isinstance(value, str)}


def json_response(model: BaseModel, http_status: int) -> JSONResponse:
    """Serialize a Pydantic contract using JSON-safe values."""
    return JSONResponse(
        status_code=http_status, content=model.model_dump(mode="json", exclude_none=True)
    )


def request_body_schema(model: type[BaseModel], *, required: bool = True) -> dict[str, Any]:
    """Describe the JSON and form encodings supported by compatibility routes."""
    schema = model.model_json_schema()
    return {
        "requestBody": {
            "required": required,
            "content": {
                "application/json": {"schema": schema},
                "application/x-www-form-urlencoded": {"schema": schema},
                "multipart/form-data": {"schema": schema},
            },
        }
    }


async def enforce_rate_limit(
    request: Request,
    scope: str,
    *components: object,
    limit: int,
    window_seconds: int,
    unavailable_message: str = "Authentication service is unavailable",
) -> None:
    """Consume a PII-safe route allowance without blocking the event loop."""
    limiter: RateLimiter = request.app.state.rate_limiter
    peer = request.client.host if request.client else "unknown-peer"
    key = rate_limit_key(scope, peer, *components)
    try:
        await run_in_threadpool(limiter.check, key, limit, window_seconds)
    except RateLimitExceededError as exc:
        logger.warning(
            "api_rate_limit_exceeded",
            scope=scope,
            retry_after_seconds=exc.retry_after_seconds,
        )
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail="Too many requests",
            headers={"Retry-After": str(exc.retry_after_seconds)},
        ) from exc
    except RateLimitBackendError as exc:
        logger.error("api_rate_limit_backend_unavailable", scope=scope)
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=unavailable_message,
        ) from exc
