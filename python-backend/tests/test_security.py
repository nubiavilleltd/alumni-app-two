"""Goal 10 CORS allowlist behaviour."""

from __future__ import annotations

from fastapi.testclient import TestClient

from app.core.config import Settings
from app.main import create_app


def _client(cors_origins: list[str]) -> TestClient:
    return TestClient(create_app(Settings(environment="test", cors_origins=cors_origins)))


def test_cors_allows_configured_origin() -> None:
    """A preflight from an allowlisted origin returns the matching CORS headers."""
    with _client(["https://app.example.test"]) as client:
        response = client.options(
            "/health/live",
            headers={
                "Origin": "https://app.example.test",
                "Access-Control-Request-Method": "GET",
            },
        )
        assert response.status_code == 200
        assert response.headers["access-control-allow-origin"] == "https://app.example.test"


def test_cors_rejects_unlisted_and_null_origins() -> None:
    """Origins outside the allowlist (including the null origin) receive no CORS grant."""
    with _client(["https://app.example.test"]) as client:
        for origin in ("https://evil.example.test", "null"):
            response = client.options(
                "/health/live",
                headers={"Origin": origin, "Access-Control-Request-Method": "GET"},
            )
            assert "access-control-allow-origin" not in response.headers


def test_cors_disabled_by_default_allows_same_origin_only() -> None:
    """With no allowlist configured, no cross-origin access is granted."""
    with _client([]) as client:
        response = client.options(
            "/health/live",
            headers={"Origin": "https://app.example.test", "Access-Control-Request-Method": "GET"},
        )
        assert "access-control-allow-origin" not in response.headers


def test_cors_does_not_echo_credentials() -> None:
    """Bearer-authenticated endpoints never advertise cookie credential support."""
    with _client(["https://app.example.test"]) as client:
        response = client.options(
            "/health/live",
            headers={
                "Origin": "https://app.example.test",
                "Access-Control-Request-Method": "GET",
            },
        )
        assert response.headers.get("access-control-allow-credentials") != "true"


def test_security_headers_are_present_on_api_responses() -> None:
    """Every API response carries a minimal security-header baseline."""
    with _client([]) as client:
        response = client.get("/health/live")
        assert response.headers["x-content-type-options"] == "nosniff"
        assert response.headers["x-frame-options"] == "DENY"
        assert response.headers["referrer-policy"] == "no-referrer"
        assert "geolocation=()" in response.headers["permissions-policy"]
        assert "frame-ancestors 'none'" in response.headers["content-security-policy"]


def test_security_headers_do_not_override_route_headers() -> None:
    """A route-provided header is not duplicated by the middleware baseline."""
    with _client([]) as client:
        response = client.get("/health/live")
        assert len(response.headers.get_list("x-content-type-options")) == 1
