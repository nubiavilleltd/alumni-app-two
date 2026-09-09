# ADR 0003: Authentication cutover and legacy compatibility

- Status: accepted
- Date: 2026-09-08

## Context

The PHP API accepts a shared `X-API-Key`, uses 24-hour symmetric JWT access tokens,
stores SHA-256 hashes of seven-day JWT refresh tokens, and does not rotate refresh
tokens. The database contains Ion Auth bcrypt hashes with `$2y$` prefixes and cost
factors 8 and 10. The legacy forgotten-password configuration permits reset codes
without an expiry.

## Decision

The value-free inventory of the supplied SQL export found 83 user password rows:

| Exported format | Count | Login treatment | Successful-login treatment |
| --- | ---: | --- | --- |
| bcrypt `$2y$`, cost 8 | 82 | Verify | Rehash to Argon2id |
| Unclassified 40-character hexadecimal | 1 | Reject | Require password recovery |
| Argon2id | 0 | Verify when introduced by FastAPI | Rehash when policy changes |
| Any other format | 0 observed | Reject | Investigate; never guess the algorithm |

The 40-character hexadecimal value is not labelled as SHA-1 because its algorithm
and derivation cannot be proven from its shape alone.

- Accept legacy `$2y$`, `$2b$`, and `$2a$` bcrypt password hashes during login.
- Upgrade a verified legacy hash to Argon2id in the same transaction.
- Remove the shared application API key as an authorization factor.
- Return the same 401 response for an unknown email and an incorrect password.
- Check protected account state only after password verification.
- Sign 15-minute access JWTs with RS256 or ES256 and validate issuer, audience,
  token type, lifetime, and required claims.
- Use opaque 384-bit refresh tokens, store only SHA-256 hashes, rotate them on every
  use, cap active tokens at five per user, and revoke all active tokens when a
  revoked token is replayed.
- Do not accept legacy JWT refresh tokens after cutover; users sign in again.
- Replace non-expiring reset codes with opaque 30-minute tokens, store only their
  SHA-256 hashes, consume them once under a row lock, and revoke all refresh
  sessions after password recovery.
- Rate-limit every migrated authentication route with a fixed-window key derived
  from the route, peer address, and a SHA-256 digest of bounded identity or token
  material. Raw identities and tokens never enter counter keys or logs.
- Permit thread-safe process-local counters only in development and tests. Require
  shared Redis counters in production, perform each increment/expiry atomically,
  and fail closed with HTTP 503 when Redis is unavailable.
- Emit structured, PII-free events for exhausted limits and backend failures. Trust
  the peer address only after the production reverse-proxy forwarding policy is
  approved and tested.

The existing login response fields and the HTTP 406/body status 407 onboarding
signal are retained for client compatibility. Invalid account credentials change
from legacy 404/400 variants to a uniform 401 response. Refresh responses now
include a rotated `refresh_token`. The unsafe `check_reset_password` reset-key
disclosure route is retired rather than copied.

## Consequences

Deployments must supply an asymmetric private signing key and matching public
verification key. A rolling cutover requires clients to persist the refresh token
returned by every successful refresh. Legacy sessions are intentionally invalidated.

The aggregate evidence is reproducible with
`scripts/inventory_password_hashes.py`; `docs/password-hash-inventory.json` contains
counts only and deliberately contains no password hashes.
