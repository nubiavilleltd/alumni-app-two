# Alumni Portal FastAPI — Threat Model

Status: repository-grounded working document (Goal 10). Verified 2026-09-18.

This model covers the FastAPI service only (`python-backend/`). It is a living
reference for the pre-cutover security review; it does not replace a penetration test
or a provider-specific review.

## Scope and assets

| Asset | Where | Sensitivity |
| --- | --- | --- |
| Member identity and profiles | `users`, `user_profiles` | High — PII (name, phone, email, birth date, address, employment) |
| Authentication material | `users.password`, `jwt_refresh_tokens`, access JWTs | High |
| Financial records | `orders`, `order_items`, `payments` | High — monetary amounts and Paystack references |
| Private uploads | `upload_root/` (`chat/`, `profiles/`, `events/`, `products/`, …) | High — private documents, images |
| Content integrity | `events`, `blog_*`, `marketplace_listings`, `announcements` | Medium |
| Operations | `/health/*`, `/metrics` | Medium — availability and monitoring |

## Trust boundaries

1. **Public internet ↔ API** — unauthenticated/rate-limited routes (catalogue reads, registration, contact, news).
2. **Authenticated member ↔ API** — Bearer access token; authority from current database facts.
3. **Administrator ↔ API** — the same Bearer surface, but higher permissions resolved fresh from the database.
4. **API ↔ MariaDB** — the source of truth for authorization state.
5. **API ↔ providers** — Paystack, SMTP, and (future) push. Outbound only, secrets server-side.
6. **Collector ↔ `/metrics`** — gated and token-authenticated.

## Threat actors

- Unauthenticated attacker on the public internet.
- Malicious or compromised member account.
- Compromised administrator token (stale/forged JWT).
- Automated bots and scrapers.
- Insider with repository access.

## Threats and mitigations

### 1. Account takeover (credential stuffing, brute force, token reuse)
- Mitigations: shared login throttling (10 attempts / 15 min per peer+identity); short-lived access tokens; rotating hashed refresh tokens with reuse detection; one-time hashed reset/verification codes; generic error responses. See `app/services/auth.py`, `app/core/rate_limit.py`.

### 2. Privilege escalation (caller-selected `user_id`, trusted JWT role claims)
- Mitigations: authorization reloads current `users.user_role`, `active`, and `is_coordinator` under row locks at the service boundary (`app/authorization/policy.py`); JWT role claims never grant authority; unsafe routes retired as HTTP 410 tombstones (`update_user_account`, `deactivate_staff`, `user_tokens`, dynamic role routes, `getAPIKey2`).

### 3. PII enumeration / over-exposure
- Mitigations: bounded public projections, field allowlists, server-side profile-visibility enforcement, fail-closed on malformed privacy JSON, no credential columns in directory/roster responses, `100`-row pagination caps.

### 4. Malicious or malformed uploads (polyglots, path traversal, executable content)
- Mitigations: content-signature/type checks, image re-encode with metadata strip, size/pixel bounds, generated filenames, private non-public storage, owner/participant-gated retrieval, rollback cleanup. No external malware scanner (accepted residual risk — see below).

### 5. Payment fraud and inventory corruption
- Mitigations: exact Paystack kobo comparison, webhook HMAC signature verification over the raw body, order row locking, conditional stock deduction (`UPDATE … WHERE quantity >= :qty`) with `InsufficientStockError`, idempotent finalization, and a webhook acknowledge-and-pend reconciliation path. See `app/repositories/store.py`, `tests/test_store_concurrency.py`.

### 6. Server-side request forgery (SSRF)
- Current status: outbound fetching is limited to a hardcoded feed allowlist (`app/services/news.py`). Wiring the live `news_feeds_setup` table will introduce admin-controlled URLs and must add explicit scheme/host/private-range validation first.

### 7. Secret leakage in logs and errors
- Mitigations: structured logging with top-level secret-field redaction, no secret in fixtures/logs, centralized error envelope without stack traces or SQL details. Remaining: nested/payment-payload redaction.

### 8. Metrics and operations exposure
- Mitigations: `/health/live` is process-only; `/health/ready` is database-aware; `/metrics` is disabled by default and requires a collector token in constant-time comparison; security headers and CORS are applied globally.

### 9. Abuse / denial of service
- Mitigations: per-route rate limiting across all routers, bounded pagination and search, 5 MB upload caps, bounded chat/event/form batch sizes.

### 10. Email relay abuse
- Mitigations: server-selected recipients only (`account_manager_recipients`), no caller-supplied recipient or arbitrary template, post-commit best-effort delivery.

## Residual risks (accepted, tracked in `HANDOFF.md`)

- No external malware scanner — content validation plus private delivery is the accepted control.
- Nested/payment-payload log redaction is incomplete.
- Durable audit storage requires a separate schema decision.
- Retention and deletion procedures require a policy decision.
- `create_notification` and push delivery are deliberately unimplemented until storage/provider decisions exist.
- Social-login provider-token validation remains open.
- No cookie/CSRF surface exists (Bearer-only), so those controls are N/A.

## Pre-cutover follow-ups

- Run an external dependency + container scan in CI (Goal 12).
- Re-verify the SSRF posture when `news_feeds_setup` is wired.
- Complete the retention/deletion policy and durable audit decision.
- Perform a provider-specific review of the Paystack webhook and SMTP configuration.
