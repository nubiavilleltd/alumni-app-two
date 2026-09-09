"""Explicit tombstones for legacy routes that must not be copied."""

from fastapi import APIRouter, Request, status
from fastapi.responses import JSONResponse

from app.schemas.auth import StatusResponse

router = APIRouter(prefix="/api", tags=["retired legacy endpoints"])

RESET_ROUTE = "/api/reset_" + "password"
RETIRED_ENDPOINTS = {
    "/api/check_reset_password": f"This endpoint was replaced by {RESET_ROUTE}",
    "/api/getAPIKey2": "Server application credentials are no longer available to clients",
    "/api/trackUser": "The unauthenticated tracking and debug route was removed",
    "/api/sendUserOTP": "This endpoint was replaced by /api/resend_verify_email",
    "/api/change_user_password1": "This endpoint was replaced by /api/change_user_password",
    "/api/test_qr": "The fixed demo QR route was removed",
    "/api/verify_otp": "This endpoint was replaced by /api/verify_email",
    "/api/resend_otp": "This endpoint was replaced by /api/resend_verify_email",
}


async def retired_endpoint(request: Request) -> JSONResponse:
    """Return a bounded migration response without executing legacy side effects."""
    message = RETIRED_ENDPOINTS[request.url.path]
    body = StatusResponse(
        status=status.HTTP_410_GONE,
        code="endpoint_replaced",
        message=message,
    )
    return JSONResponse(
        status_code=status.HTTP_410_GONE, content=body.model_dump(exclude_none=True)
    )


for retired_path in RETIRED_ENDPOINTS:
    operation_suffix = retired_path.removeprefix("/api/").replace("-", "_").lower()
    for retired_method in ("GET", "POST"):
        router.add_api_route(
            retired_path.removeprefix("/api"),
            retired_endpoint,
            methods=[retired_method],
            response_model=StatusResponse,
            deprecated=True,
            operation_id=f"retired_{operation_suffix}_{retired_method.lower()}",
        )
