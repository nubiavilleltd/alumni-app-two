# ADR 0002: Uvicorn behind an environment-managed reverse proxy

- Status: Accepted for foundation
- Date: 2026-09-08

## Context

The migration requires route-by-route cutover, dependency-aware readiness, graceful connection shutdown, and no direct exposure of development documentation in production.

## Decision

Deploy the ASGI entry point `app.main:app` with Uvicorn behind the approved reverse proxy or gateway. The deployment environment controls worker count, TLS termination, forwarded-header trust, timeouts, and process restarts. The application exposes `/health/live` without dependency access and `/health/ready` with a bounded database query. OpenAPI and interactive documentation are disabled by default in production.

The service must be started from the locked environment. Development may use one worker; staging and production worker counts require load-test evidence and database-pool capacity calculations.

## Consequences

The application remains portable while the gateway can direct bounded legacy routes to PHP or FastAPI. Proxy trust and production worker sizing remain cutover gates and are not proven by the local smoke test.

