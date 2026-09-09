"""PII-safe fixed-window authentication rate limiting."""

from __future__ import annotations

import hashlib
import math
import threading
import time
from collections.abc import Callable, Sequence
from dataclasses import dataclass
from typing import Protocol, cast

from redis import Redis
from redis.exceptions import RedisError

from app.core.config import Settings
from app.core.errors import RateLimitBackendError, RateLimitExceededError

_REDIS_WINDOW_SCRIPT = """
local count = redis.call('INCR', KEYS[1])
if count == 1 then
  redis.call('PEXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('PTTL', KEYS[1])
return {count, ttl}
"""


class RateLimiter(Protocol):
    """Rate-limit backend used by public authentication routes."""

    def check(self, key: str, limit: int, window_seconds: int) -> None:
        """Consume one allowance or raise a bounded domain error."""

    def close(self) -> None:
        """Release backend resources."""


class _RedisClient(Protocol):
    """Small typed surface required from the Redis client."""

    def eval(self, script: str, numkeys: int, *keys_and_args: object) -> Sequence[object]:
        """Execute one atomic Redis script."""

    def close(self) -> None:
        """Release pooled connections."""


def _build_redis_client(redis_url: str) -> _RedisClient:
    """Create the concrete Redis client behind a narrow typed boundary."""
    return cast(
        _RedisClient,
        Redis.from_url(
            redis_url,
            decode_responses=False,
            socket_connect_timeout=2,
            socket_timeout=2,
        ),
    )


@dataclass(slots=True)
class _MemoryWindow:
    count: int
    resets_at: float


class MemoryRateLimiter:
    """Thread-safe local limiter allowed only outside production."""

    def __init__(self, clock: Callable[[], float] = time.monotonic) -> None:
        self._windows: dict[str, _MemoryWindow] = {}
        self._lock = threading.Lock()
        self._clock = clock

    def check(self, key: str, limit: int, window_seconds: int) -> None:
        """Consume one allowance in a process-local fixed window."""
        now = self._clock()
        with self._lock:
            window = self._windows.get(key)
            if window is None or window.resets_at <= now:
                self._windows[key] = _MemoryWindow(1, now + window_seconds)
                return
            window.count += 1
            if window.count > limit:
                raise RateLimitExceededError(math.ceil(window.resets_at - now))

    def close(self) -> None:
        """Release local counters."""
        with self._lock:
            self._windows.clear()


class RedisRateLimiter:
    """Atomic shared limiter for multi-worker production deployments."""

    def __init__(self, settings: Settings) -> None:
        redis_url = settings.redis_url.get_secret_value() if settings.redis_url else None
        if not redis_url:
            raise RateLimitBackendError("Redis rate limiting is not configured")
        self._prefix = settings.rate_limit_key_prefix
        self._client = _build_redis_client(redis_url)

    def check(self, key: str, limit: int, window_seconds: int) -> None:
        """Atomically consume one allowance in Redis."""
        try:
            result = self._client.eval(
                _REDIS_WINDOW_SCRIPT,
                1,
                f"{self._prefix}:{key}",
                window_seconds * 1000,
            )
            raw_count, raw_ttl_ms = result[0], result[1]
            if not isinstance(raw_count, (str, bytes, bytearray, int)) or not isinstance(
                raw_ttl_ms, (str, bytes, bytearray, int)
            ):
                raise TypeError("Redis rate-limit script returned invalid values")
            count, ttl_ms = int(raw_count), int(raw_ttl_ms)
        except (RedisError, OSError, TypeError, ValueError, IndexError) as exc:
            raise RateLimitBackendError("Redis rate limiting is unavailable") from exc
        if count > limit:
            raise RateLimitExceededError(max(1, math.ceil(ttl_ms / 1000)))

    def close(self) -> None:
        """Close the Redis connection pool."""
        self._client.close()


def build_rate_limiter(settings: Settings) -> RateLimiter:
    """Build the configured backend, forbidding local counters in production settings."""
    if settings.authentication_rate_limit_backend == "redis":
        return RedisRateLimiter(settings)
    return MemoryRateLimiter()


def rate_limit_key(scope: str, *components: object) -> str:
    """Hash request attributes so Redis keys never contain identities or tokens."""
    material = "\x1f".join(str(component).strip().casefold() for component in components)
    digest = hashlib.sha256(material.encode("utf-8")).hexdigest()
    return f"{scope}:{digest}"
