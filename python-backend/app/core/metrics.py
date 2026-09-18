"""Prometheus request metrics with bounded label cardinality."""

from __future__ import annotations

from prometheus_client import (
    CONTENT_TYPE_LATEST,
    CollectorRegistry,
    Counter,
    Histogram,
    generate_latest,
)
from starlette.types import ASGIApp, Message, Receive, Scope, Send

REGISTRY = CollectorRegistry()
REQUEST_COUNT = Counter(
    "http_requests_total",
    "Total HTTP requests by method, route template, and status.",
    ["method", "path", "status"],
    registry=REGISTRY,
)
REQUEST_DURATION = Histogram(
    "http_request_duration_seconds",
    "HTTP request latency in seconds.",
    ["method", "path"],
    registry=REGISTRY,
)


def _route_label(scope: Scope) -> str:
    """Return the registered route template, never a raw unbounded request path."""
    route = scope.get("route")
    if route is not None:
        template = getattr(route, "path", None)
        if template:
            return str(template)
    return "unmatched"


class MetricsMiddleware:
    """Observe request count and latency using bounded route-template labels."""

    def __init__(self, app: ASGIApp) -> None:
        self.app = app

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        method = scope.get("method", "UNKNOWN")
        path = _route_label(scope)
        status_code = 0

        async def send_with_metrics(message: Message) -> None:
            nonlocal status_code
            if message["type"] == "http.response.start":
                status_code = message.get("status", 0)
            await send(message)

        try:
            with REQUEST_DURATION.labels(method=method, path=path).time():
                await self.app(scope, receive, send_with_metrics)
        finally:
            REQUEST_COUNT.labels(method=method, path=path, status=str(status_code)).inc()


def render_metrics() -> bytes:
    """Render the collected metrics in the Prometheus text exposition format."""
    return generate_latest(REGISTRY)


__all__ = ["CONTENT_TYPE_LATEST", "MetricsMiddleware", "render_metrics"]
