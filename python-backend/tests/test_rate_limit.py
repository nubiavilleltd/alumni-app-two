"""Authentication rate-limit backend and HTTP boundary tests."""

from __future__ import annotations

from collections.abc import Iterator, Sequence

import pytest
from fastapi.testclient import TestClient
from redis.exceptions import ConnectionError as RedisConnectionError

import app.core.rate_limit as rate_limit_module
from app.core.config import Settings
from app.core.errors import RateLimitBackendError, RateLimitExceededError
from app.core.rate_limit import (
    MemoryRateLimiter,
    RedisRateLimiter,
    build_rate_limiter,
    rate_limit_key,
)
from app.main import create_app


class _RecordingRedis:
    """Minimal Redis double that records the atomic script call."""

    def __init__(self, results: Iterator[object]) -> None:
        self.results = results
        self.calls: list[tuple[object, ...]] = []
        self.closed = False

    def eval(self, *args: object) -> Sequence[object]:
        """Return the next configured script result."""
        self.calls.append(args)
        result = next(self.results)
        if isinstance(result, BaseException):
            raise result
        if not isinstance(result, Sequence):
            raise TypeError("Configured Redis result must be a sequence")
        return result

    def close(self) -> None:
        """Record resource cleanup."""
        self.closed = True


class _RejectingLimiter:
    """Injected limiter that deterministically fails a request."""

    def __init__(self, failure: Exception) -> None:
        self.failure = failure
        self.calls: list[tuple[str, int, int]] = []
        self.closed = False

    def check(self, key: str, limit: int, window_seconds: int) -> None:
        """Record the bounded values and raise the configured failure."""
        self.calls.append((key, limit, window_seconds))
        raise self.failure

    def close(self) -> None:
        """Record lifespan cleanup."""
        self.closed = True


def _redis_limiter(
    monkeypatch: pytest.MonkeyPatch,
    *results: object,
) -> tuple[RedisRateLimiter, _RecordingRedis]:
    client = _RecordingRedis(iter(results))

    def build_client(_redis_url: str) -> _RecordingRedis:
        return client

    monkeypatch.setattr(rate_limit_module, "_build_redis_client", build_client)
    settings = Settings(
        environment="test",
        authentication_rate_limit_backend="redis",
        redis_url="redis://localhost:6379/0",
    )
    return RedisRateLimiter(settings), client


def test_memory_limiter_enforces_and_resets_fixed_window(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Local development counters enforce a limit and reset after the window."""
    readings = iter((100.0, 101.0, 102.0, 111.0))
    limiter = MemoryRateLimiter(clock=lambda: next(readings))

    limiter.check("login:key", limit=2, window_seconds=10)
    limiter.check("login:key", limit=2, window_seconds=10)
    with pytest.raises(RateLimitExceededError) as captured:
        limiter.check("login:key", limit=2, window_seconds=10)
    assert captured.value.retry_after_seconds == 8

    limiter.check("login:key", limit=2, window_seconds=10)


def test_rate_limit_keys_are_normalized_and_hide_sensitive_values() -> None:
    """Shared counter keys never expose emails, passwords, or bearer material."""
    sensitive = "Member.Name+private@example.com"
    token = "opaque-refresh-token-value"
    key = rate_limit_key("login", sensitive, token)

    assert key == rate_limit_key("login", sensitive.upper(), token.upper())
    assert sensitive.casefold() not in key
    assert token not in key
    assert key.startswith("login:")
    assert len(key.removeprefix("login:")) == 64


def test_redis_limiter_uses_atomic_script_and_prefix(monkeypatch: pytest.MonkeyPatch) -> None:
    """Redis uses one atomic operation with a millisecond expiry."""
    limiter, client = _redis_limiter(monkeypatch, (1, 30_000), (3, 12_500))

    limiter.check("login:digest", limit=2, window_seconds=30)
    with pytest.raises(RateLimitExceededError) as captured:
        limiter.check("login:digest", limit=2, window_seconds=30)
    assert captured.value.retry_after_seconds == 13
    assert client.calls[0][1:] == (1, "alumni:rate-limit:login:digest", 30_000)

    limiter.close()
    assert client.closed is True


def test_redis_limiter_fails_closed_when_backend_is_unavailable(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Redis transport failures become a sanitized domain failure."""
    limiter, _client = _redis_limiter(monkeypatch, RedisConnectionError("private details"))

    with pytest.raises(RateLimitBackendError, match="unavailable"):
        limiter.check("login:digest", limit=2, window_seconds=30)


def test_build_rate_limiter_selects_configured_backend(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """The factory selects local or shared storage from validated settings."""
    assert isinstance(build_rate_limiter(Settings(environment="test")), MemoryRateLimiter)
    configured, _client = _redis_limiter(monkeypatch, (1, 1_000))
    settings = Settings(
        environment="test",
        authentication_rate_limit_backend="redis",
        redis_url="redis://localhost:6379/0",
    )
    assert isinstance(configured, RedisRateLimiter)
    assert isinstance(build_rate_limiter(settings), RedisRateLimiter)


@pytest.mark.parametrize(
    ("failure", "expected_status", "expected_message"),
    [
        (RateLimitExceededError(17), 429, "Too many requests"),
        (
            RateLimitBackendError("private backend details"),
            503,
            "Authentication service is unavailable",
        ),
    ],
)
def test_login_rate_limit_http_boundary_is_sanitized_and_closes_backend(
    failure: Exception,
    expected_status: int,
    expected_message: str,
) -> None:
    """Public routes expose bounded failures and close the injected backend."""
    limiter = _RejectingLimiter(failure)
    with TestClient(create_app(Settings(environment="test"), rate_limiter=limiter)) as client:
        response = client.post(
            "/api/login",
            json={"identity": "member@example.com", "password": "synthetic-password"},
        )

    assert response.status_code == expected_status
    assert response.json()["error"]["message"] == expected_message
    assert limiter.calls[0][1:] == (10, 15 * 60)
    assert "member@example.com" not in limiter.calls[0][0]
    assert limiter.closed is True
    if expected_status == 429:
        assert response.headers["Retry-After"] == "17"
