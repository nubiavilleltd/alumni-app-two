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
    "/api/update_user_account": (
        "This unsafe legacy account-write route was replaced by /api/update_profile"
    ),
    "/api/update_user_role": "This endpoint was replaced by /api/manage_user_account",
    "/api/manage_user_roles": (
        "Free-form system roles were removed; use /api/manage_user_account for reviewed roles"
    ),
    "/api/create_role": (
        "Free-form role definitions were removed; use /api/manage_user_account for reviewed roles"
    ),
    "/api/manage_role": (
        "Free-form role definitions were removed; use /api/manage_user_account for reviewed roles"
    ),
    "/api/get_roles": (
        "Dynamic role definitions are not an authorization source; use the reviewed "
        "/api/manage_user_account role contract"
    ),
    "/api/change_user_password1": "This endpoint was replaced by /api/change_user_password",
    "/api/test_qr": "The fixed demo QR route was removed",
    "/api/verify_otp": "This endpoint was replaced by /api/verify_email",
    "/api/resend_otp": "This endpoint was replaced by /api/resend_verify_email",
    "/api/user_tokens": (
        "The insecure push-token route was removed; an authenticated self-service "
        "push-token contract is required before this feature can return"
    ),
    "/api/create_privacy_policy": "Privacy policy is a static page; no editable storage exists",
    "/api/manage_privacy_policy": "Privacy policy is a static page; no editable storage exists",
    "/api/get_privacy_policy": "Privacy policy is a static page; no editable storage exists",
    "/api/create_market": (
        "The legacy market table was removed; use /api/create_listing on the marketplace"
    ),
    "/api/manage_market": (
        "The legacy market table was removed; use /api/manage_listing on the marketplace"
    ),
    "/api/get_market": (
        "The legacy market table was removed; use /api/get_listings on the marketplace"
    ),
}

RETIRED_DYNAMIC_ENDPOINTS = {
    "/api/deactivate_staff/{user_id}": (
        "This destructive legacy route was replaced by /api/manage_user_account"
    ),
}


def _retired_response(message: str) -> JSONResponse:
    body = StatusResponse(
        status=status.HTTP_410_GONE,
        code="endpoint_replaced",
        message=message,
    )
    return JSONResponse(
        status_code=status.HTTP_410_GONE, content=body.model_dump(exclude_none=True)
    )


async def retired_endpoint(request: Request) -> JSONResponse:
    """Return a bounded migration response without executing legacy side effects."""
    return _retired_response(RETIRED_ENDPOINTS[request.url.path])


async def retired_deactivate_staff(user_id: int) -> JSONResponse:
    """Retire the unauthorised delete-after-deactivate legacy operation."""
    del user_id
    return _retired_response(RETIRED_DYNAMIC_ENDPOINTS["/api/deactivate_staff/{user_id}"])


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

for retired_path in RETIRED_DYNAMIC_ENDPOINTS:
    operation_suffix = (
        retired_path.removeprefix("/api/")
        .replace("{", "")
        .replace("}", "")
        .replace("-", "_")
        .lower()
    )
    for retired_method in ("GET", "POST"):
        router.add_api_route(
            retired_path.removeprefix("/api"),
            retired_deactivate_staff,
            methods=[retired_method],
            response_model=StatusResponse,
            deprecated=True,
            operation_id=f"retired_{operation_suffix}_{retired_method.lower()}",
        )
