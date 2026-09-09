"""Process liveness and dependency readiness endpoints."""

from typing import Literal

from fastapi import APIRouter, Request, Response, status
from pydantic import BaseModel

from app.db.session import Database

router = APIRouter(prefix="/health", tags=["health"])


class LivenessResponse(BaseModel):
    """Response returned when the ASGI process can serve requests."""

    status: Literal["ok"] = "ok"


class ReadinessResponse(BaseModel):
    """Dependency readiness without connection or exception details."""

    status: Literal["ready", "not_ready"]
    database: Literal["ready", "not_configured", "unavailable"]


@router.get("/live", response_model=LivenessResponse)
async def liveness() -> LivenessResponse:
    """Report process liveness without touching external dependencies."""
    return LivenessResponse()


@router.get("/ready", response_model=ReadinessResponse)
def readiness(request: Request, response: Response) -> ReadinessResponse:
    """Report whether the configured SQL database accepts a query."""
    database: Database = request.app.state.database
    check = database.check()
    if not check.ready:
        response.status_code = status.HTTP_503_SERVICE_UNAVAILABLE
        return ReadinessResponse(status="not_ready", database=check.reason)
    return ReadinessResponse(status="ready", database="ready")
