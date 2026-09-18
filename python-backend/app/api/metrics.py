"""Authenticated collector-only Prometheus metrics endpoint."""

from __future__ import annotations

import hmac

from fastapi import APIRouter, Request
from fastapi.responses import PlainTextResponse

from app.core.metrics import CONTENT_TYPE_LATEST, render_metrics

router = APIRouter(tags=["Operations"])


@router.get("/metrics", include_in_schema=False)
async def metrics(request: Request) -> PlainTextResponse:
    """Expose Prometheus metrics only when enabled and authorized with the collector token.

    Disabled by default; when enabled, the shared token is accepted as a Bearer token
    or via the ``X-Metrics-Token`` header and compared in constant time.
    """
    settings = request.app.state.settings
    if not settings.metrics_enabled:
        return PlainTextResponse("Not Found", status_code=404)

    expected = settings.metrics_token_value()
    if not expected:
        return PlainTextResponse("Not Found", status_code=404)

    authorization = request.headers.get("authorization", "")
    supplied = request.headers.get("x-metrics-token", "")
    if authorization.startswith("Bearer "):
        supplied = authorization.removeprefix("Bearer ").strip()

    if not supplied or not hmac.compare_digest(supplied, expected):
        return PlainTextResponse("Unauthorized", status_code=401)

    return PlainTextResponse(render_metrics().decode("ascii"), media_type=CONTENT_TYPE_LATEST)
