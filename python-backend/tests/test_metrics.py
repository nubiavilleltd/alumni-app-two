"""Goal 10 Prometheus metrics endpoint gating."""

from __future__ import annotations

from fastapi.testclient import TestClient

from app.core.config import Settings
from app.main import create_app

_COLLECTOR_TOKEN = "collector-token"


def _client(*, enabled: bool, token: str | None = _COLLECTOR_TOKEN) -> TestClient:
    settings = Settings(environment="test", metrics_enabled=enabled, metrics_token=token)
    return TestClient(create_app(settings))


def test_metrics_disabled_by_default_returns_not_found() -> None:
    with _client(enabled=False) as client:
        response = client.get("/metrics")
        assert response.status_code == 404


def test_metrics_enabled_requires_token() -> None:
    with _client(enabled=True) as client:
        assert client.get("/metrics").status_code == 401
        assert client.get("/metrics", headers={"authorization": "Bearer wrong"}).status_code == 401


def test_metrics_enabled_with_bearer_token() -> None:
    with _client(enabled=True) as client:
        response = client.get("/metrics", headers={"authorization": "Bearer collector-token"})
        assert response.status_code == 200
        assert response.headers["content-type"].startswith("text/plain")
        assert "http_requests_total" in response.text


def test_metrics_enabled_with_header_token() -> None:
    with _client(enabled=True) as client:
        response = client.get("/metrics", headers={"x-metrics-token": "collector-token"})
        assert response.status_code == 200


def test_metrics_without_configured_token_is_not_found() -> None:
    with _client(enabled=True, token=None) as client:
        assert (
            client.get("/metrics", headers={"authorization": "Bearer anything"}).status_code == 404
        )
