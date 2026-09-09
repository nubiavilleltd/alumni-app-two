# ADR 0001: Synchronous SQLAlchemy and PyMySQL during compatibility migration

- Status: Accepted
- Date: 2026-09-08

## Context

The source application uses synchronous `mysqli` access to a MySQL-compatible schema. The first migration phase must preserve table and column behaviour, make transaction boundaries explicit, and run on the verified Python 3.13 Windows toolchain.

## Decision

Use SQLAlchemy 2.x in synchronous mode with PyMySQL. Database work runs in ordinary synchronous FastAPI route dependencies or services, which FastAPI dispatches outside the event loop. Route modules do not execute SQL directly. Repositories own queries; services own transactions, authorization, and side effects.

Connections use `pool_pre_ping`, bounded pool and overflow sizes, a bounded acquisition timeout, a 30-minute recycle interval, UTF-8 MB4, and driver connect/read/write timeouts. Configuration has no database URL default, so a missing environment variable cannot silently reach any database.

## Consequences

This minimizes behavioural differences during compatibility work and avoids a native database-driver build on Windows. An asynchronous driver may replace PyMySQL only after representative workload benchmarks show a material benefit and the decision is updated. Mixing synchronous and asynchronous database access is not permitted.

