"""Request-scoped correlation identifiers."""

import re
from uuid import uuid4

import structlog.contextvars
from starlette.types import ASGIApp, Message, Receive, Scope, Send

CORRELATION_HEADER = b"x-correlation-id"
VALID_CORRELATION_ID = re.compile(r"^[A-Za-z0-9._-]{1,128}$")


class CorrelationIdMiddleware:
    """Bind a validated correlation ID and return it in every HTTP response."""

    def __init__(self, app: ASGIApp) -> None:
        self.app = app

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        supplied = next(
            (
                value.decode("ascii", errors="ignore")
                for key, value in scope.get("headers", [])
                if key.lower() == CORRELATION_HEADER
            ),
            "",
        )
        correlation_id = supplied if VALID_CORRELATION_ID.fullmatch(supplied) else uuid4().hex
        structlog.contextvars.clear_contextvars()
        structlog.contextvars.bind_contextvars(correlation_id=correlation_id)

        async def send_with_correlation_id(message: Message) -> None:
            if message["type"] == "http.response.start":
                headers = list(message.get("headers", []))
                headers.append((CORRELATION_HEADER, correlation_id.encode("ascii")))
                message["headers"] = headers
            await send(message)

        try:
            await self.app(scope, receive, send_with_correlation_id)
        finally:
            structlog.contextvars.clear_contextvars()
