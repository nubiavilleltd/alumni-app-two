"""Operational health endpoint tests."""

import os
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

import pytest
from fastapi import FastAPI, HTTPException, Query
from fastapi.testclient import TestClient
from sqlalchemy import text

from app.core.config import Settings
from app.db.session import Database
from app.main import create_app


def test_liveness_does_not_require_database() -> None:
    """Liveness remains healthy when dependencies are not configured."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.get("/health/live")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_readiness_fails_safely_without_database() -> None:
    """Readiness reports missing configuration without leaking details."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.get("/health/ready")
    assert response.status_code == 503
    assert response.json() == {"status": "not_ready", "database": "not_configured"}


def test_correlation_id_is_preserved_when_valid() -> None:
    """A caller-supplied safe correlation ID is echoed for tracing."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.get("/health/live", headers={"X-Correlation-ID": "request-123"})
    assert response.headers["X-Correlation-ID"] == "request-123"


def test_unsafe_correlation_id_is_replaced() -> None:
    """Control characters and oversized identifiers must not reach response headers."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.get("/health/live", headers={"X-Correlation-ID": "unsafe value"})
    generated = response.headers["X-Correlation-ID"]
    assert generated != "unsafe value"
    assert len(generated) == 32


def test_production_hides_openapi_routes() -> None:
    """Production does not expose interactive schema routes."""
    settings = Settings(
        environment="production",
        authentication_rate_limit_backend="redis",
        redis_url="redis://localhost:6379/0",
    )
    with TestClient(create_app(settings)) as client:
        assert client.get("/docs").status_code == 404
        assert client.get("/openapi.json").status_code == 404


def test_http_errors_use_the_common_error_envelope() -> None:
    """HTTP errors do not leak framework-specific response shapes."""
    app = create_app(Settings(environment="test"))

    async def forbidden() -> None:
        raise HTTPException(status_code=403, detail="Denied")

    app.add_api_route("/forbidden", forbidden)
    with TestClient(app) as client:
        response = client.get("/forbidden")
    assert response.status_code == 403
    assert response.json() == {"error": {"code": "http_error", "message": "Denied"}}


def test_validation_errors_use_the_common_error_envelope() -> None:
    """Validation failures expose bounded field details in the common envelope."""
    app = create_app(Settings(environment="test"))

    async def bounded(limit: int = Query(ge=1)) -> dict[str, int]:
        return {"limit": limit}

    app.add_api_route("/bounded", bounded)
    with TestClient(app) as client:
        response = client.get("/bounded", params={"limit": 0})
    body = response.json()
    assert response.status_code == 422
    assert body["error"]["code"] == "validation_error"
    assert body["error"]["details"][0]["location"] == ["query", "limit"]


def test_database_engine_requires_explicit_configuration() -> None:
    """Database construction never falls back to a production-like default."""
    database = Database(Settings(environment="test"))
    with pytest.raises(RuntimeError, match="not configured"):
        database.engine()


def test_database_unavailability_is_sanitized() -> None:
    """Connection failures return a stable reason without credentials or exception text."""
    settings = Settings(
        environment="test",
        database_url="mysql+pymysql://nobody:nope@127.0.0.1:9/missing",
        database_connect_timeout_seconds=1,
    )
    assert Database(settings).check().reason == "unavailable"


@pytest.mark.integration
def test_readiness_queries_sanitized_database() -> None:
    """Readiness executes a real query against an explicitly supplied test database."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")
    settings = Settings(environment="test", database_url=database_url)
    with TestClient(create_app(settings)) as client:
        response = client.get("/health/ready")
    assert response.status_code == 200
    assert response.json() == {"status": "ready", "database": "ready"}


@pytest.mark.integration
def test_sessions_query_and_close_against_sanitized_database() -> None:
    """The session factory can query the isolated schema and reuse its engine."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")
    database = Database(Settings(environment="test", database_url=database_url))
    assert database.engine() is database.engine()
    sessions = database.sessions()
    session = next(sessions)
    assert session.execute(text("SELECT COUNT(*) FROM information_schema.tables")).scalar_one() > 0
    sessions.close()
    database.dispose()


def test_non_http_scope_bypasses_correlation_middleware() -> None:
    """Application lifespan traffic is passed through unchanged."""
    app = create_app(Settings(environment="test"))

    @asynccontextmanager
    async def empty_lifespan(_app: FastAPI) -> AsyncIterator[None]:
        yield

    app.router.lifespan_context = empty_lifespan
    with TestClient(app):
        pass
