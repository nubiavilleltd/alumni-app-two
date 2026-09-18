# Project Handoff

Last updated: 2026-09-18 (Africa/Lagos), gate-restoration session
Branch: `backend-dev`
HEAD: `7857fd52` (working tree contains additional uncommitted migration changes)

## Current Objective

Complete the retained PHP-to-FastAPI migration in verified, production-quality slices while preserving the legacy database during compatibility work. Use the re-reviewed Excel catalogue as the read-only reviewed baseline and keep continuation state in the Markdown breakdown and this handoff.

## Current State

The FastAPI foundation, registration plus nine authentication/recovery/verification routes, public city and privacy-aware welfare-zone catalogues, protected privacy-aware zone roster/self assignment, authorized transaction-safe zone/city management plus bounded bulk geography and alumni import, class-year-filtered public voucher discovery, owned pending-vouch listing/decision, credential-free profile read, allowlisted profile update with normalized avatar storage, protected profile-visibility read/update, privacy-aware member directory/downward account listing, member approval/rejection, protected account activation/deactivation plus bounded role changes, four-card alumni statistics, privacy-minimized birthday discovery, public/protected chapter lookup, authenticated setup-parameter lookup, authenticated notification feed/read state, public announcement/project/event feeds plus content/event administration, six event-registration-form routes, the initial V2 chat core family, and sixteen security/supersession tombstones are implemented. Public events now also derive active-form availability/count from the reviewed MySQL tables. The additive migration `9d9d1f2a7c31` retains immutable form-version and display snapshots, optional Firebase-source identifiers, and checkbox selection limits without rewriting legacy data. The V2 chat inbox is now a bounded, paged listing (`limit` 1-200 default 100, bounded `offset`, `has_more`, `thread_total`, mailbox-wide `unread_count`/`unread_thread_count`) with a fixed per-page query count, and additive reversible migration `c4d5e6f7a8b9` adds `idx_thread_participants_inbox` after disposable-schema measurement at 3,400 participant rows and 100,000 messages. The re-reviewed workbook contains 159 catalogue rows: 145 direct conversions, 10 consolidated replacements, 1 platform replacement, and 3 schema/caller-blocked `market` decisions.

**The working-tree verification gate is green again as of 2026-09-18.** On a freshly rebuilt sanitized disposable schema at head `d5e6f7a8b9c0` (68 tables imported, `alembic check` clean), the complete suite collected **481 tests with 0 failures at 90.22% branch coverage**, clearing the enforced 90% floor that had been red at 86.30%. The later 2026-09-18 Goal 6 slice added the bounded, idempotent staged-attachment reaper (`app/tasks/chat_attachment_reaper.py`, `scripts/reap_chat_attachments.py`) with six DB tests and additive reversible migration `d5e6f7a8b9c0` (`idx_msg_attachment_staged_purge`). `pip check`, `ruff format --check` (121 files), `ruff check`, strict `mypy` over `app migrations scripts tests` (121 files), Alembic drift check, Bandit, and `pip-audit` all pass. The deficit was closed with verified tests for already-implemented Goal 7/8/9 code; two real defects in that uncommitted code were fixed on the way (an unimported `ProductVariants` reference in `app/services/store.py` that would raise `NameError` on variant cart/checkout paths, and a missing `Any` import in `app/integrations/mail.py`). `frontend/node_modules` is now installed, so `npm ci`, `npm run build`, and a live Uvicorn HTTP smoke against the disposable schema are real evidence rather than clean skips. Full route parity, live providers, production database/media proof, deployment, browser *automation*, and cutover remain incomplete.

## Current Task

The active goal is Goal 6 (Chat V2); do not advance to another goal until its acceptance gate is passed. The repo-wide gate that was blocking every goal is now green (474 tests, 0 failures, 90.21% coverage, clean Ruff/mypy/Bandit/pip-audit), and the frontend build plus a live HTTP smoke have replaced two former clean skips. Goal 6 still needs registration of the now-built staged-attachment reaper on the deployment host (OS scheduler decided), and legacy reconciliation/cutover evidence. The scripted headless-browser V2 inbox journey now passes, and malware scanning is decided (no external provider; residual risk accepted). Goal 7 has database-tested implementation slices and now better-tested blog routes, but it is not goal-complete: the approved Firebase form/version/submission export, explicit event/user mapping, import rehearsal, and frontend cutover remain open. Firebase is not a recommended live replacement for the event-registration-form feature: FastAPI plus MariaDB is the canonical form/answer system, while Firebase is limited to a future approved, sanitized, read-only historical export and mapping rehearsal. Do not extend Firebase Functions or Firestore for new live form data. The privacy-policy and legacy-market blocks remain strictly contained. Report every focused test as a slice result, and call a goal complete only when the authoritative breakdown's goal acceptance gate is explicitly satisfied.

## Relevant Files

- `PYTHON_FASTAPI_MIGRATION_TASK_BREAKDOWN.md`
- `python-backend/docs/adr/0005-chat-canonical-model.md`
- `python-backend/app/api/chat.py`
- `python-backend/app/services/chat.py`
- `python-backend/app/repositories/chat.py`
- `python-backend/app/schemas/chat.py`
- `python-backend/app/models/generated.py`
- `python-backend/migrations/versions/c4d5e6f7a8b9_chat_inbox_pagination_index.py`
- `python-backend/migrations/versions/d5e6f7a8b9c0_chat_staged_attachment_purge_index.py`
- `python-backend/app/tasks/chat_attachment_reaper.py`
- `python-backend/scripts/reap_chat_attachments.py`
- `python-backend/docs/chat-attachment-reaper.md`
- `python-backend/tests/test_chat_reaper.py`
- `python-backend/scripts/seed_chat_journey_fixture.py`
- `frontend/e2e/messages-v2-inbox.mjs`
- `python-backend/scripts/chat_retrieval_probe.py`
- `python-backend/docs/schema-baseline.md`
- `frontend/src/features/messages/lib/backendMessagesTransport.ts`
- `python-backend/app/api/members.py`
- `python-backend/app/services/members.py`
- `python-backend/app/repositories/members.py`
- `python-backend/app/schemas/members.py`
- `python-backend/app/authorization/policy.py`
- `python-backend/app/integrations/mail.py`
- `python-backend/app/integrations/uploads.py`
- `python-backend/app/integrations/geography_import.py`
- `python-backend/app/integrations/alumni_import.py`
- `python-backend/app/api/auth.py`
- `python-backend/app/services/auth.py`
- `python-backend/app/repositories/auth.py`
- `python-backend/app/schemas/auth.py`
- `python-backend/app/main.py`
- `python-backend/app/api/marketplace.py`
- `python-backend/app/services/marketplace.py`
- `python-backend/app/repositories/marketplace.py`
- `python-backend/app/schemas/marketplace.py`
- `python-backend/app/api/projects.py`
- `python-backend/app/services/projects.py`
- `python-backend/app/repositories/projects.py`
- `python-backend/app/schemas/projects.py`
- `python-backend/app/api/leadership.py`
- `python-backend/app/services/leadership.py`
- `python-backend/app/repositories/leadership.py`
- `python-backend/app/schemas/leadership.py`
- `python-backend/app/api/vacancies.py`
- `python-backend/app/services/vacancies.py`
- `python-backend/app/repositories/vacancies.py`
- `python-backend/app/schemas/vacancies.py`
- `python-backend/app/api/events.py`
- `python-backend/app/services/events.py`
- `python-backend/app/repositories/events.py`
- `python-backend/app/schemas/events.py`
- `python-backend/app/api/retired.py`
- `python-backend/app/api/notifications.py`
- `python-backend/app/api/announcements.py`
- `python-backend/app/api/marketplace.py`
- `python-backend/app/services/notifications.py`
- `python-backend/app/services/announcements.py`
- `python-backend/app/services/marketplace.py`
- `python-backend/app/repositories/notifications.py`
- `python-backend/app/repositories/announcements.py`
- `python-backend/app/repositories/marketplace.py`
- `python-backend/app/schemas/notifications.py`
- `python-backend/app/schemas/announcements.py`
- `python-backend/app/schemas/marketplace.py`
- `python-backend/scripts/inventory_php_endpoints.py`
- `python-backend/tests/test_auth_integration.py`
- `python-backend/tests/test_store_edges.py`
- `python-backend/tests/test_paystack.py`
- `python-backend/tests/test_blog_edges.py`
- `python-backend/tests/test_uploads_edges.py`
- `python-backend/tests/test_store.py`
- `python-backend/tests/test_mail.py`
- `python-backend/app/services/store.py`
- `python-backend/app/api/product.py`
- `python-backend/app/repositories/store.py`
- `python-backend/app/integrations/paystack.py`
- `python-backend/app/integrations/mail.py`
- `python-backend/tests/test_geography_import.py`
- `python-backend/tests/test_alumni_import.py`
- `python-backend/tests/test_uploads.py`
- `python-backend/tests/test_endpoint_inventory.py`
- `python-backend/docs/adr/0004-authorization-policy.md`
- `frontend/src/features/authentication/api/adapters/register.adapter.ts`
- `frontend/src/features/authentication/api/adapters/voucher.adapter.ts`
- `frontend/src/features/authentication/services/auth.service.ts`
- `frontend/src/features/authentication/pages/RegisterDetailsPage.tsx`
- `frontend/src/features/welfare/api/adapters/welfare.adapter.ts`
- `frontend/src/features/welfare/pages/WelfareZonesPage.tsx`
- `frontend/src/features/welfare/types/welfare.type.ts`
- `frontend/src/features/announcements/services/announcement.service.ts`
- `frontend/src/features/announcements/api/adapters/announcement.adapter.ts`
- `frontend/src/features/announcements/types/announcement.types.ts`
- `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx`

## Recent Changes

- Goal 4 social login, Goal 9 notifications/push, Goal 10 audit+retention, and retirements (2026-09-18):
  - Retired `privacy_policy` ×3 and legacy `market` ×3 with 410 tombstones.
  - Built social login (`/socials/social_login|social_signup|link|unlink`) with server-side Google/Facebook token verification, auto-link, and minimal signup; `tests/test_social.py` (6).
  - `POST /api/create_notification` (content-admin only) + VAPID Web Push (`get_vapid_key`, `register_push_subscription`, `pywebpush` sender); `tests/test_notifications.py` (3) + `tests/test_push.py` (3).
  - Audit-log migration `e7f8a9b0c1d2` (secret-free `audit_log`) wired into account management; `docs/retention-policy.md`; `tests/test_audit.py` (1).
  - Full suite **552 passed, 0 failed, 90.27% coverage**; Ruff/`mypy`/Bandit/`alembic check` clean at head `e7f8a9b0c1d2`.
  - **Security note:** the PHP `Socials.php` controller hardcodes a real Facebook app secret. FastAPI loads it from `ALUMNI_FACEBOOK_APP_SECRET`; recommend rotating the committed value.

- Goal 7 event guards + Goal 8 oversell fix + Goal 9 contact + Goal 10 security (2026-09-18):
  - `tests/test_events_edges.py` (20 tests) drove `app/services/events.py` from 79%→**89%**; event repo →84%, event api →92%.
  - Fixed a real stock-oversell defect in `app/repositories/store.py` (un-locked read-modify-write → conditional `UPDATE ... WHERE quantity >= :qty` + `InsufficientStockError`/HTTP 409/reconciliation). `tests/test_store_concurrency.py` (3 tests) proves one-paid/one-pending + stock 0 under serial and concurrent webhooks. Route-family inventory: all 24 PHP `Product` methods map 1:1 to 24 `/product/*` routes.
  - Implemented `POST /api/contact_us` (public, rate-limited, validated, persisted to the live `contact_us` table, manager-notified post-commit). `tests/test_contact.py` (4 tests).
  - Goal 10: CORS allowlist (`allow_credentials=False`) + `SecurityHeadersMiddleware` (nosniff/DENY/no-referrer/permissions-policy/CSP); cookie/CSRF documented as N/A (Bearer-only auth); gated `GET /metrics` endpoint + `MetricsMiddleware`; `docs/threat-model.md`. `tests/test_security.py` (6 tests) + `tests/test_metrics.py` (5 tests).
  - Goal 11: `tests/test_edge_data.py` (4 tests: Unicode/emoji round-trip, Nigerian-phone contract, null legacy fields); confirmed `scripts/verify.ps1` as the deployment-blocking release gate.
  - Goal 8: headless-browser store-catalogue journey (`npm run e2e:store`, `fetch_products` 200, product rendered).
  - Goal 10: recursive nested/PII/payment-payload log redaction (`app/core/logging.py`, 5 tests).
  - Goal 8 Paystack: added `POST /api/paystack/webhook` alias; created `frontend/.env.example` (names-only) and `frontend/.env.local` (gitignored, test public key); live-smoked the supplied `sk_test_*` key against the sandbox (initialize + verify + webhook HMAC all pass).
  - Full suite **527 passed, 0 failed, 90.82% coverage**; `pip check`, Ruff format/check (141 files), strict `mypy` (141 files), Bandit, `pip-audit`, `alembic check` all clean.

- Live-database dump drift analysis and scope decisions (2026-09-18):
  - The user placed `alumni_portal_v2.sql` (phpMyAdmin, server `10.5.26-MariaDB`, 69 tables, real rows) at the repo root. A schema-only copy was produced with the new `scripts/sanitize_sql_dump.py` and imported into a disposable DB; `scripts/schema_drift_report.py` compares it against the models. **Only one genuinely new live table** — `news_feeds_setup` (live RSS/Atom feed config, 33 rows) — was unmapped and is now added to `app/models/generated.py`. Everything else matches; the remaining deltas are our own additive migrations (event-form snapshots/source ids, chat-attachment ownership/expiry, `orders.status`+`processing`, four indexes), which `alembic upgrade head` applies cleanly.
  - `.local-state/sanitized-schema.sql` was regenerated from the live dump (schema-only). `test_schema_models.py` now asserts 70 model tables. Full suite green on the live baseline: **481 passed, 0 failed, 90.23% coverage**; `pip check`, Ruff format/check (128 files), strict `mypy` (128 files), Bandit, `pip-audit`, and `alembic check` clean.
  - Follow-up flagged: the `news/feeds` service still hardcodes 12 feeds and falls back to `setup_parameters`; it should read active feeds from `news_feeds_setup` (Goal 7).
  - User decisions recorded: Firebase tasks deferred; no legacy V1 chat data (nothing to reconcile); deployment/cutover deferred.
  - **Safety:** `alumni_portal_v2.sql` (real data) is untracked and not gitignored — it must not be committed.

- Scripted the Goal 6 headless-browser V2 inbox journey (2026-09-18 third session, user-approved Playwright):
  - Added `@playwright/test` + Chromium to `frontend/` dev dependencies and an `e2e:messages` script. `python-backend/scripts/seed_chat_journey_fixture.py` seeds a disposable DB (approved/verified/active member, peer, group thread, one message; refuses non-localhost/non-`test`). `frontend/e2e/messages-v2-inbox.mjs` starts Uvicorn + `vite preview` against the built `dist`, logs in through the real form, opens `/messages`, and asserts the seeded thread renders from a live `chat_api/v2_get_threads` 200.
  - Result: `npm run build` (8,524 modules, 41.29s, known chunk-size warning) then `npm run e2e:messages` → exit 0, `final_url=http://127.0.0.1:4173/messages`, `thread_statuses=[200]`, `Synthetic V2 Inbox Journey` visible with unread badge and peer preview, screenshot `tmp/e2e-messages-v2-inbox.png`. Servers torn down synchronously. The test proxies same-origin API calls to Uvicorn inside the browser context because production CORS is still absent (Goal 10), so no app code changed. `dist/`, `node_modules/`, and the screenshot are not committed.

- Built the Goal 6 scheduled staged-attachment reaper and its global purge access path (2026-09-18 third session):
  - `ChatService.reap_expired_staged_attachments(limit=1..500)` locks a bounded batch of unsent rows (`message_id IS NULL AND expires_at <= now`), deletes the authoritative rows inside one transaction, then removes the private files after commit; output is aggregate-only. `app/tasks/chat_attachment_reaper.py` is the deployable entry point and `scripts/reap_chat_attachments.py` is the operator wrapper (refuses non-localhost unless `--allow-non-localhost`; rejects an out-of-range limit).
  - Additive reversible migration `d5e6f7a8b9c0` adds `idx_msg_attachment_staged_purge (message_id, expires_at)`; the owner-scoped `idx_msg_attachment_staged_expiry` could not serve a global sweep. `scripts/chat_retrieval_probe.py` measured the sweep going from `type=ALL; Using filesort` to `type=range key=idx_msg_attachment_staged_purge`; `alembic downgrade -1` + `upgrade head` re-created it with `alembic check` clean.
  - `tests/test_chat_reaper.py` (six DB tests) proves expiry-only selection, linked/fresh preservation, batching/idempotency, the missing-storage guard, missing/malformed path handling, and a concurrent-link lock race, plus the task entry point. Fresh disposable `alumni_portal_reaper_test`: full suite **481 passed, 90.22% coverage**; `pip check`, Ruff format/check (125 files), strict `mypy` (125 files), Bandit, `pip-audit`, and `alembic check` all clean. No production data, credentials, provider, or workbook was touched.
  - Decisions (2026-09-18, user-approved): **no external malware scanner** for the compatibility phase (content-signature/re-encode/size limits + private authenticated delivery; residual client-side malware risk explicitly accepted), and an **OS scheduler** (cron/systemd timer/Windows Task Scheduler) invokes the CLI; see `python-backend/docs/chat-attachment-reaper.md`. Actual registration happens on the deployment host under Goal 12.
  - Still open: the headless-browser V2-inbox journey, live scheduler registration on the deployment host, and legacy V1 reconciliation/cutover.

- Restored the repository-wide verification gate and turned two frontend/goal-level "clean skips" into real evidence (2026-09-18 second session):
  - **Gate is green.** Fresh disposable schema `alumni_portal_gate_test` on `127.0.0.1:3313`, rebuilt from `.local-state/sanitized-schema.sql` and upgraded to head `c4d5e6f7a8b9`: `pytest --cov=app --cov-report=term-missing` → **474 passed, 0 failed, 90.21% coverage** (90% floor met). Also clean: `pip check`, `ruff format --check app migrations scripts tests` (121 files), `ruff check`, `mypy app migrations scripts tests` (121 files), `alembic check` ("No new upgrade operations detected."), `bandit -q -r app`, `pip-audit -r requirements-dev.lock` ("No known vulnerabilities found").
  - **Two real defects found and fixed in the previously uncommitted Goal 8/9 code.** `app/services/store.py` used `ProductVariants` at runtime without importing it (lines 586/635/870 in the pre-fix file) — the variant cart-add, cart-update, and checkout stock paths would have raised `NameError`; the new variant cart test failed before the fix and passes after. `app/integrations/mail.py` annotated `dict[str, Any]` without importing `Any` and had over-length order-status bodies. Alongside those: three unused imports removed, three nested-`if` lints collapsed, three redundant `int(round(...))` money casts simplified to `round(...)` (identical value; `round(Decimal)` already returns `int`), one `int()` cast narrowed so a `None` cannot reach it, and the three unformatted files reformatted. No behaviour change beyond the two defect fixes.
  - **Goal 8 test evidence added.** `tests/test_store_edges.py` (52 tests) covers product scalar/variant/image/spotlight validation, variant-image binding, staged-file rollback on a late persistence failure, foreign/malformed `delete_image_ids`, conflicting and stale spotlight selectors, the four-product pin limit plus unpin retry, cart variant/stock/cross-user guards, address validation and default switching, checkout rejection paths, Paystack init failure (HTTP 502 with no orphan pending order), `verify_payment` ownership/status/mismatch/idempotency (including that a replay does not decrement stock twice), webhook signature/acknowledge/failed/finalize-once/replay behaviour, and the whole order status state machine with rider-detail and mail-outage cases. `tests/test_paystack.py` (32 tests) takes `app/integrations/paystack.py` from 21% to **100%** using an in-process transport only (HMAC verification, request shape, timeouts, JSON/non-JSON/malformed/false-status envelopes, missing `access_code`/`data`, injected and implicit client branches). `tests/test_mail.py` additions take `app/integrations/mail.py` to **100%**. Resulting module coverage: `app/services/store.py` 87%, `app/repositories/store.py` 87%, `app/api/product.py` 91%.
  - **Goal 7 test evidence added.** `tests/test_blog_edges.py` (15 tests) drives blank-field, malformed-section, unknown-target, no-field, invalid-image, cover-ownership, cleanup, draft-visibility, reorder, and authorization branches across the 21 blog routes → `app/api/blog.py` 87%→96%, `app/services/blog.py` 68%→86%, `app/repositories/blog.py` 76%→81%. Two observed behaviours are recorded, not "fixed": reordering with unknown ids is an idempotent HTTP 200 no-op, and a bad image inside a multi-image upload deletes the images already staged for that request.
  - **Goal 9 upload evidence added.** `tests/test_uploads_edges.py` (40 tests, no DB/network) covers avatar and chat-attachment content validation (empty/oversized, unsupported format, every re-encode branch, office-document structure, UTF-8/binary rejection, signature-vs-extension mismatches, filename sanitization) and proves all eleven storage targets fail closed with a typed `UploadStorageError` when the destination cannot be created, that `delete()` cannot escape its own directory, and that generated names round-trip under the configured root.
  - **Frontend evidence is no longer a clean skip.** `npm ci` installed 300 packages (17 advisories: 2 low, 2 moderate, 12 high, 1 critical — unchanged baseline); `npm run build` completed in 58.56s (8,524 modules; `dist/index.html` 0.57 kB, CSS 211.73 kB, JS 1,746.43 kB) with only the known stale-Browserslist and >500 kB chunk warnings. A live Uvicorn instance against the disposable schema served `GET /health/live` → `{"status":"ok"}`, `GET /health/ready` → `{"status":"ready","database":"ready"}`, and `POST /api/get_events`, `POST /api/get_listings`, `POST /api/get_projects` → each HTTP 200 with the `events`/`listings`/`projects` keys the frontend adapters read. The server was stopped and the port verified closed.
  - **Only one frontend type error belonged to the migration.** `npx tsc --noEmit` reported 12 errors; the migration-owned one was `buildRecordedVoiceNoteUploadRequest` declaring an `async` function as returning `UploadMessageAttachmentRequest` instead of `Promise<UploadMessageAttachmentRequest>` in `frontend/src/features/messages/api/adapters/messages.adapter.ts` (present at HEAD too). Fixed type-only; the count is now 11 pre-existing baseline errors outside migration scope (`src/data/content.ts`, `AdminStorePage.tsx`, `social.adapter.ts` ×2, `mockAuth.ts` ×3, `ErrorBoundary.tsx` ×2, `PhoneNumberInput.tsx`, `renderIcon.tsx`), which were deliberately left untouched.
  - **Scope discipline:** the reviewed Excel workbook was not written to; `privacy_policy` and the legacy `market` routes stay blocked; no goal-level acceptance claim is made. Goal 6 still owes malware-scanner/reaper and cutover decisions, Goal 7 still owes the approved Firebase export/mapping plus frontend cutover, and Goals 8–12 still owe their own acceptance evidence.

- Closed the last unchecked Goal 6 implementation task (bounded pagination limits and indexes for high-volume message retrieval) without activating a later goal:
  - `POST /chat_api/v2_get_threads` accepts an optional `{"limit": 1-200, "offset": 0-100000}` body and returns `count`, `thread_total`, `unread_count`, `unread_thread_count`, `limit`, `offset`, `has_more`, `threads`, and `server_time`. A body-less or `{}` call keeps the previous default behaviour, so the active frontend contract is preserved; an out-of-range limit returns HTTP 400 `chat_invalid_request` through the existing bounded-chat validation, and the shared `{"error": {"code", "message"}}` envelope is untouched.
  - One page is now a fixed query count: paged thread join, batched membership rows, batched participants, grouped per-thread unread counts, and one mailbox-wide unread aggregate, replacing two queries per thread. Membership is still re-verified per returned thread and fails closed with HTTP 403 `chat_membership_forbidden` if a page ever returns a thread without the actor's active membership. `unread_count`/`unread_thread_count` intentionally report the whole mailbox so paging cannot under-report the badge (an intentional, documented difference from PHP's unbounded per-thread loop).
  - New `python-backend/scripts/chat_retrieval_probe.py` seeds synthetic chat volume into a disposable localhost test database (it refuses any non-localhost host or database name without `test`), prints `EXPLAIN` plans plus best-of-three timings for the five retrieval query shapes, and deletes only its own marked rows. Evidence at 200 owned threads, 3,000 other-member threads (3,400 participant rows) and 100,000 messages: the pre-change plans already used `idx_member_id` (participants) and `idx_thread_id` (per-thread message pages); additive reversible migration `c4d5e6f7a8b9` adds `idx_thread_participants_inbox (member_id, left_at, is_pinned)`, which turns the bounded thread count into an index-only scan. A candidate `messages (thread_id, id)` index was measured and rejected because the optimizer kept choosing the existing `idx_thread_id` and timings stayed inside run-to-run noise.
  - Verification against a fresh disposable MariaDB schema on `127.0.0.1:3313` (`alumni_portal_goal6_perf_test`, rebuilt from `.local-state/sanitized-schema.sql`, upgraded to head `c4d5e6f7a8b9`, `alembic check` clean, `downgrade -1` + `upgrade head` round-trip proven): `pytest tests/test_auth_integration.py -k v2_chat -q` -> 6 passed; `pytest tests/test_schema_models.py tests/test_chat_service.py tests/test_auth_integration.py -k "schema or chat or legacy_tables" -q` -> 18 passed (including index parity against `information_schema`); Ruff and strict mypy pass for every changed module. The full suite collected **332 tests, 332 passed, 0 failed** but reported **86.30%** branch coverage, below the enforced 90% floor.
  - Clean skip: the frontend build/browser journey still cannot run because `frontend/node_modules` (and `vite`) is absent; only the source adapter change is proven. The coverage shortfall, 7 strict-`mypy` errors, and 3 `ruff format --check` failures inside `app/integrations/mail.py`, `app/repositories/store.py`, `app/services/store.py`, and `app/api/product.py` belong to concurrently edited Goal 8/9 work in this working tree and were deliberately not modified; they now block the full gate and any goal-level claim.

- Resolved the user-supplied Antigravity Store/Orders test failure without changing production API behavior. The service already returned the correct HTTP 400 when door delivery attempted `paid -> shipped` without rider details; the test incorrectly searched legacy top-level `message`/`detail.message` fields instead of the application-wide `error.message` envelope. `tests/test_store.py` now asserts `error.code == "http_error"` and the canonical message. Its test-only Paystack fixture now generates one disposable signing key per run and uses that exact key for the webhook HMAC check, avoiding a hard-coded test credential. Focused verification against disposable MariaDB `127.0.0.1:3314/alumni_portal_test`: `pytest tests/test_store.py -q` -> **10 passed** in 95.71s; Ruff check/format and `git diff --check` pass. No source API, schema, provider, production data, or Goal 8 acceptance state changed; upstream TestClient/AnyIO deprecation warnings remain.

- Completed and disposable-MariaDB-tested the Goal 7 News Feeds Aggregator (`/news/feeds`, Catalogue ID 129), completing all 54 routes of Goal 7:
  - Route: `GET` and `POST` `/news/feeds`, matching active frontend consumer `frontend/src/features/liveNews/services/livenews.service.ts` (`apiClient.post(API_ENDPOINTS.LIVENEWS.GET_LIVE_NEWS)`).
  - Security & Authentication: Enforces `X-API-Key` verification against the database `api_table` (`api_name = 'alumni_key'`). Supports both raw key comparison and bcrypt verification (`$2y$` translated to `$2b$` for python-bcrypt compatibility) in constant time. Missing or invalid keys return HTTP 401 `{"status": 401, "message": "Invalid API token"}` matching legacy CodeIgniter contracts.
  - Category, Alias, and Free Keyword Engine: Supports 7 canonical topics (`business`, `entertainment`, `sports`, `politics`, `technology`, `health`, `education`) with full alias resolution (`financies` → `business`, `football` → `sports`, etc.), fallback to `setup_parameters` (`setup_name = 'news_category'`), and free search keywords with stem-suffix matching for words >= 5 characters. Invalid request categories return HTTP 400 `{"status": 400, "message": "Unknown category", ...}`; invalid setup parameters are skipped and recorded in `ignored_categories`.
  - Feed Aggregation, Enrichment, and Caching: Pulls dedicated topic feeds and 12 Nigerian news feeds via `httpx`, deduplicates articles by normalized URL with category merging, enriches candidate articles by scraping full body text and `og:image`/`twitter:image` (dropping articles lacking text or image), and caches JSON responses on disk for 15 minutes (900 seconds) returning `X-Cache: HIT` or `X-Cache: MISS`.
  - Verification: Tested against isolated disposable MariaDB 12.3 daemon running on `127.0.0.1:3314` (`alumni_portal_test`). All 7 tests in `tests/test_news.py` passed 100%. The entire pytest suite passed **321 of 321 tests** in 112.78s with zero regressions. All static quality gates passed cleanly (`ruff check`, `ruff format --check`, `mypy --strict`, and `bandit -q -r app`).
  - Goal 7 Milestone: All components of Goal 7 (Events, Vacancies, Listings, Blog with 21 routes, and News Feeds) are completely built and verified on MariaDB.

- Completed and disposable-MariaDB-tested the Goal 7 Blog, Carousel, FAQs, Categories, and Posts slice (21 routes):
  - Implemented the complete 21-route Blog family (`Blog_api.php`): `blog_api/homepage`, `blog_api/update_homepage_text`, `blog_api/create_carousel_image`, `blog_api/update_carousel_image`, `blog_api/reorder_carousel`, `blog_api/delete_carousel_image`, `blog_api/faqs`, `blog_api/create_faq`, `blog_api/update_faq`, `blog_api/reorder_faqs`, `blog_api/delete_faq`, `blog_api/blog_categories`, `blog_api/create_blog_category`, `blog_api/update_blog_category`, `blog_api/delete_blog_category`, `blog_api/reorder_categories`, `blog_api/blog_posts`, `blog_api/blog_post_detail/{id_or_slug}`, `blog_api/create_blog_post`, `blog_api/update_blog_post`, and `blog_api/delete_blog_post`.
  - Architecture & Security: `Permission.MANAGE_CONTENT` authorization enforced at service boundary. `CarouselStorage` and `BlogGalleryStorage` validate image integrity, assign safe UUID-based filenames, and automatically clean up staged files on disk if the database transaction fails.
  - Business rules: Homepage text atomic upsert, carousel ordering and exclusive `show_greeting` toggle, greeting auto-reassignment upon hiding or deleting the active greeting image, soft deletion with timestamps for carousel images, FAQs, categories, and posts, category unique slug generation and soft-delete revival, blog post slug generation, reading time calculation (~200 wpm), and session identity map synchronization on update mutations.
  - Verification: Run against isolated disposable MariaDB 12.3 instance on `127.0.0.1:3314` (`alumni_portal_test`). All 11 tests in `tests/test_blog.py` passed 100%. The full test suite ran with all 314 tests passing in 102.51s with zero regressions. Static analysis: `ruff check`, `ruff format --check`, `mypy --strict`, and `bandit -q -r app` all passed with 0 issues.
- Re-reviewed and verified the Goal 7 event-registration-form feature family (6 routes: `create_event_registration_form`, `manage_event_registration_form`, `get_event_registration_forms`, `register_event_with_forms`, `get_event_registration_submissions`, and `get_event_registration_submission_detail`). Form/answer relationships, immutable version snapshots (`9d9d1f2a7c31`), capacity locks, administrator-only PII protection, and bounded pagination are confirmed. SQL-free contract tests in `test_auth_api.py` and pre-import survey export validator tests in `test_event_survey_export.py` pass 100%.
- Re-reviewed the 3 privacy-policy routes (`create_privacy_policy`, `manage_privacy_policy`, `get_privacy_policy`): confirmed that no `privacy_policy` table exists in SQL dumps, `frontend/src/pages/legal/PrivacyPage.tsx` is static text, and no API callers exist. They remain strictly blocked; no table or contract is invented.
- Re-reviewed the 3 legacy market routes (`create_market`, `manage_market`, `get_market`): confirmed that no `market` table exists in SQL. Active marketplace features use `marketplace_listings` and `marketplace_social_media` via `/api/get_listings`, `/api/create_listing`, and `/api/manage_listing`. The legacy routes remain strictly blocked.
- Firebase decision (2026-09-18): the PHP service uses `firebase/php-jwt` only as a JWT library; its actual mobile push delivery is Expo and browser push is VAPID/Web Push. Firebase Functions/Firestore are currently used only by the frontend event-survey feature. FastAPI plus the reviewed MariaDB form/version/answer schema is the selected live replacement. Firebase is not to receive new form/answer writes and is retained only as a future approved, sanitized, read-only export source for historical migration. This reduces App Check, Function deployment, Firestore-rule, cross-system partial-write, and credential dependencies. Expo/VAPID provider design remains a separate Goal 9 decision.
- Disposable MariaDB gate status: no isolated test instance is running on dedicated test ports (3312/3313), and system MariaDB service on 3307 cannot be modified per safety rules. 112 database integration tests cleanly recorded as skipped (not passed). 191 offline unit, contract, upload, inventory, and schema tests passed 100%. Ruff check/format (98 files), strict mypy, and Bandit security scans passed with zero issues.
- Added authenticated, rate-limited HTTP 410 containment for all twelve legacy V1 `/chat_api/*` paths (`chat_legacy_unavailable`). Current source and compiled-frontend evidence call V2 only; the V1 tables remain untouched pending an approved sanitized reconciliation/cutover decision. The focused chat suite proves the explicit legacy boundary.
- Contained `POST /chat_api/v2_sync_year_groups` as authenticated HTTP 410 (`chat_year_sync_unavailable`) rather than copying PHP's ordinary-user/self and unprotected `sync_all` table writes. No active frontend caller exists; a future replacement needs approved internal-job authority plus class-group uniqueness/reconciliation evidence.
- Completed the private V2 attachment slice after re-reviewing PHP, active source/compiled caller evidence, reviewed tables, storage mounts, and tests. `POST /chat_api/v2_upload_attachment` uses bounded content-derived validation (raster re-encoding; PDF/Office/text/audio structure/signature checks), rejects SVG, stores generated files below unmounted `upload_root/chat`, and records stager plus 24-hour unsent expiry through additive migration `0e4c31d8f2a7`. `GET /chat_api/v2_attachments/{id}` is Bearer-protected: only the stager can retrieve a still-staged file; after linking, only active thread participants can retrieve it. Paths, no-store/nosniff download headers, and metadata redaction prevent a static/public URL from bypassing membership. Messages lock and accept only up to six distinct, same-thread, unexpired staged IDs owned by the sender; linking clears expiry and prevents reuse. The active frontend preserves an authenticated `download_path` and fetches it into a Blob URL; SVG was removed from its picker. Focused disposable MariaDB proof: `pytest tests/test_auth_integration.py -k v2_chat -q` -> 4 passed, 102 deselected; Ruff, strict mypy, and compileall pass. `npm run build` was attempted but skipped cleanly because `frontend/node_modules` is absent (`vite` is unavailable), so browser runtime/cutover is not claimed. Malware/provider selection, staged-file reaping, production persistence, browser runtime, legacy reconciliation, and retention/cutover remain gates.
- Completed and disposable-MariaDB-tested the second Goal 6 V2 group-state slice: `v2_mark_delivered`, `v2_add_member`, `v2_leave_group`, and `v2_pin_thread`. Active member/thread ownership is rechecked on every write. Group member addition/reactivation is administrator-only; PHP's arbitrary authenticated add is not retained. Leave is group-only, history-preserving, and transfers a sole administrator to the oldest remaining active participant so the group remains governable. The focused 3-test chat suite passes with Ruff and strict mypy. The active frontend source/bundle has no caller for these four routes, so no transport was changed.
- Added the initial Goal 6 V2 chat API/router/service/repository/schema family and mounted it in the FastAPI app. The two disposable-MariaDB tests cover inbox/detail membership, idempotency, group creation, reply isolation, sender-only delete, read state, and simultaneous opposite first direct sends. The concurrency test found a MariaDB deadlock in the initial lock-then-insert approach; the final deterministic direct-key `INSERT IGNORE` plus bounded transient retry passes. Exact focused command: `$env:ALUMNI_TEST_DATABASE_URL='mysql+pymysql://root@127.0.0.1:3313/alumni_portal_goal6_chat_test'; .\.venv\Scripts\pytest.exe tests/test_auth_integration.py -k v2_chat -q` -> 2 passed, 102 deselected. Ruff format/check and strict mypy pass. The database was newly initialized at localhost-only port 3313, imported from the sanitized schema, then migrated to head; no production data/credentials or workbook were used.
- Completed the Goal 7 registration-form family: create/manage/list forms,
  register with answers, list submissions, and submission detail. It uses current
  `MANAGE_EVENTS`, bounded form/question inputs, event visibility checks, locked
  self-owned RSVP capacity decisions, answer snapshots, and bounded administrator-only
  PII reads. PHP reusable keys, raw payload logging, caller-selected identity, and
  JWT-role trust are intentionally not retained.
- Extended the existing Goal 7 integration tests through the event-form mutation,
  vacancy flyer replacement/removal, listing image lifecycle, project replacement,
  and leadership photo/reorder flows. Removed production `assert` control-flow
  checks flagged by Bandit, using explicit event validation and type-only casts where
  Pydantic has already guaranteed the variant.
- Fresh-schema command sequence (repository root, then `python-backend`): drop/create
  only `alumni_portal_goal7_test`, `source .local-state/sanitized-schema.sql`, set
  `ALUMNI_DATABASE_URL` to the localhost disposable URL, `python -m alembic upgrade head`,
  then set `ALUMNI_TEST_DATABASE_URL` and run `.\\scripts\\verify.ps1`. Result:
  278 collected/completed, 90.02% coverage, `pip check`, Ruff, strict mypy, Alembic no-drift,
  Bandit, and `pip-audit` passed. No production data, credentials, or workbook
  changes were used.
- Added migration `9d9d1f2a7c31_event_form_history_snapshots.py`. It is additive and
  reversible: source form/question/version identifiers, checkbox `max_selections`,
  answer display snapshots, and immutable per-form version records. No Firebase,
  production data, credentials, or automatic ID mapping was accessed. A future import
  must receive an approved sanitized export and an explicit event/user ID mapping.
- Added `scripts/validate_event_survey_export.py`, a read-only pre-import gate derived
  from the active Firebase functions/types. It accepts only a bounded normalized,
  PII-free export and explicit event/user maps; it validates version/question/answer
  provenance and prints aggregate counts only. Six focused tests pass. It never opens
  Firebase or a database, so it is not an importer or cutover proof.
- Re-reviewed `RegisterEventModal.tsx`, `useEventSurvey.ts`, Firebase survey functions,
  FastAPI routes, and `EventService.manage_registration_form`. The live modal currently
  posts a standard RSVP before posting a Firebase survey, so it cannot be pointed at
  FastAPI without using `register_event_with_forms` as its sole write. Existing-form
  FastAPI `upsert` already replaces the full question set when `questions` is supplied,
  so the remaining compatibility gap is the atomic RSVP/answer frontend adapter, not a
  missing form-definition mutation. No transport flag is enabled.
- Added ADR 0005 after reviewing PHP legacy/V2 chat handlers, the reviewed schema, active
  frontend message transport, and compiled bundle. FastAPI will target the V2
  `message_threads` family only; legacy reconciliation remains blocked on an approved
  sanitized export. The active frontend calls only `chat_api/v2_*`. PHP's arbitrary
  member-add, unbound reply/attachment, and bulk-year-sync behaviors are rejected as
  security defects. No chat records were present in the disposable schema, so this is a
  target-model decision, not live-data migration proof.

- Added bounded public `POST /api/get_leadership` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_leader` and `POST /api/manage_leader`. Public output preserves the active featured/team/all contract but excludes legacy member PII; mutations validate references, enforce annual duplicate/featured rules, lock rows, bound reorder, soft-delete, and safely store/revoke generated photo overrides.
- Added focused integration coverage for forged/stale role claims, public shaping, soft deletion, and generated-photo rollback after late database failure. Static checks pass; the two new database tests skip without the disposable MariaDB URL. The ledger, handoff, and re-reviewed workbook now distinguish source/static completion from outstanding database/browser/cutover proof.

- Added bounded public `POST /api/get_vacancies` plus active-Bearer `POST /api/create_vacancy` and `POST /api/manage_vacancy`. Creation derives the current account's enabled chapter and owner ID; update/delete lock the target and allow only its owner or current-database `MANAGE_CONTENT`, closing PHP's caller-selected cross-user mutation gap. Public output is presentation-shaped and excludes user email/account data; the legacy reusable read key is intentionally not copied.
- Added validated/re-encoded generated vacancy-flyer storage with rollback/replacement/deletion cleanup, form/JSON/multipart handling, and matching email/link application-destination validation. Focused integration coverage proves chapter derivation, stale/forged-role denial, owner/admin write separation, public shaping, hard deletion, and late-failure cleanup. Static checks and route/mount smoke checks pass; focused database tests skip without the disposable MariaDB URL.

- Added bounded public `POST /api/get_events` plus active-Bearer `POST /api/create_event` and `POST /api/manage_event`. Public output is approved/public/non-draft presentation data only; writes use current-database `MANAGE_EVENTS`, lock actor and target rows, own creator/approval state server-side, validate enabled chapter references, and hard-delete the reviewed event row.
- Added validated/re-encoded generated event-banner storage with rollback/replacement/deletion cleanup and JSON/form/multipart handling. The focused suite covers forged/stale claims, public projection, draft concealment, deletion, and late-failure cleanup; the SQL-free API contract test, strict mypy, Ruff, and route/static-mount smoke checks pass. Database integration tests skip without the disposable MariaDB URL.

- Added bounded public `POST /api/get_projects` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_project` and `POST /api/manage_project`. The project feed only exposes non-deleted active/completed/paused/ongoing presentation data; creation derives ownership and chapter from current database facts, mutations lock rows and allowlist fields, and delete is soft.
- Added JSON/form/multipart project handling with at most six validated/re-encoded generated images, safe add/replace/removal behavior, reference validation, and transaction-safe cleanup after rollback/replacement/deletion. Focused integration coverage proves forged/stale role claims cannot grant content access, public/draft/deletion boundaries, server-owned fields, and late-failure cleanup. Static checks pass; focused database tests skip without the disposable MariaDB URL.
- Updated the ledger, README, handoff, and route catalogue. The three project records distinguish source/static completion from outstanding database/browser/cutover proof.

- Added public bounded `GET|POST /api/get_announcements` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_announcement` and `POST /api/manage_announcement`. The routes accept existing JSON/form/multipart frontend shapes, validate explicit fields and chapters, lock state before writes, rate limit, shape public output, and use generated metadata-stripped JPEG/PNG/GIF/WEBP images under a separate announcements directory. Failed database writes remove newly stored images; successful replacement/deletion removes only generated local announcement images.
- Added focused integration coverage for member denial, current-database role changes despite a stale token, public output shaping, create/update/delete, and image/database rollback. Static checks pass; the focused tests are currently skipped without the disposable MariaDB test URL.
- Updated the ledger, README, handoff, and route catalogue. The three records now distinguish implemented source/static evidence from outstanding database/browser/cutover proof.

- Re-reviewed both marketplace API families. The active frontend service calls only `/api/get_listings`, `/api/create_listing`, and `/api/manage_listing`; their current PHP implementation uses the reviewed `marketplace_listings` and `marketplace_social_media` tables, multi-image JSON, social metadata, listing expiry, and owner-or-administrator mutation. The older `market`-table handlers are source-disconnected from both the frontend and reviewed schema and must remain a disposition/retirement decision rather than an implementation shortcut.
- Added public bounded `POST /api/get_listings` over the actual marketplace tables. It applies active/unexpired constraints before selection, chapter/year global scope, fixed search/filter/page bounds, selected presentation fields, batched social data, and a single-listing view increment. It deliberately does not expose legacy user email or write scopes. Ruff and strict mypy pass; database-backed contract proof and all mutations remain next.
- Completed the active marketplace family: `POST /api/create_listing` and `POST /api/manage_listing` accept reviewed JSON/multipart shapes, derive actor ownership/status/featured state server-side, validate/re-encode generated marketplace images, atomically write listing/social rows, support bounded add/replace/removal cleanup, and use owner-or-fresh-current-database `MANAGE_STORE` authorization. Public reads retain legacy http(s) image records for compatibility but do not expose seller email. Focused integration tests cover spoofed ownership, stale JWT/current role facts, and storage rollback; they skip until disposable MariaDB is configured. The route catalogue records all three as source/static implemented with runtime proof pending.

- Added `GET|POST /api/get_notifications` and `POST /api/mark_notification_read` against the reviewed `notifications.user_id`/per-row `is_read` schema. Both derive scope from the active Bearer principal, recheck current database account state, enforce the legacy account-creation cutoff in application UTC, bound list/mark-all work, shape responses, and ignore caller-selected `user_id`.
- Single-row and mark-all changes lock eligible rows and run in one transaction. Focused integration tests prove cross-owner denial, cutoff filtering, stale-account denial, idempotency, bounded mark-all, and rollback after a late repository failure.
- Left `/api/create_notification` and push delivery unimplemented. PHP's ineffective role gate, caller-selected recipients, missing `category`/`title`/`target_user_id`/`push_tokens` storage, and unproven provider/retry/opt-out contract cannot be safely inferred from the reviewed schema.
- Updated the migration ledger, ADR 0004, README, and route catalogue. At that checkpoint the workbook marked two notification read routes built and creation pending; the current workbook baseline is recorded below and supersedes that historical checkpoint.

- Security-retired GET/POST `/api/deactivate_staff/{user_id}`. The PHP route accepts a caller-selected target without actor authentication or authorization and permanently deletes the user after deactivation; FastAPI now returns bounded HTTP 410 guidance to the existing state-only, hierarchy-checked `/api/manage_user_account` operation.
- Security-retired GET/POST `/api/user_tokens`. The PHP route trusts a reusable key and caller-selected user ID, logs and returns push-token material, and writes through a `push_tokens` table absent from the reviewed SQL snapshot. A replacement remains a Goal 9 design gate, not implemented functionality.
- Regenerated the 548-record inventory to 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact records. That historical workbook checkpoint had 148 direct conversions and 10 consolidated replacements; the current 159-row baseline is recorded below.

- Added POST `/api/import_alumni` with bearer-first authentication, current active-database `MANAGE_ACCOUNTS` and downward-target authorization, JSON or exact multipart input, 2 MiB/500-row bounds, strict CSV/XLSX/archive/formula/header/value/duplicate validation, enabled chapter/city/member-group checks, and one all-or-nothing transaction.
- Existing imports preserve roles, coordinator state, password/hash state, access/user codes, active/approved/verified/profile status, and profile visibility while reconciling allowlisted profile/chapter/year data. New imports receive fixed alumni/non-coordinator state, an unknown random Argon2id credential with `has_password=0`, empty access code, member group/category, and a private profile. Responses expose only row, status, and user ID.
- Retired the PHP route's reusable-key-only authority, caller-selected shared password, client coordinator grant, forced existing-account activation/approval/verification, source timestamp write, binary XLS, partial commits, and secret-bearing response fields. No current source or built-frontend caller was found.
- Added GET plus bodyless POST `/api/get_birthdays` with current active-account authorization, shared throttling, exact today/week/month/upcoming and Africa/Lagos/February 29 behavior, PHP-compatible missing-key/explicit-false birth-date privacy, malformed-JSON fail-closed handling, deliberate directory-visibility independence, field-aware avatars, deterministic 200-row output, a 10,000-candidate scan bound, and a dedicated active-frontend-compatible response that omits exact DOB, age, contact, account, and unrelated profile fields.
- Added multipart POST `/api/upload_zones_cities` with current active-account `MANAGE_ZONES` authorization, authentication/throttling before parsing, exact one-file and zone/city-header contracts, 2 MiB/2,000-row limits, bounded XLSX archive validation, formula/control-character/cell/duplicate rejection, deterministic catalogue locks, chapter-1 validation for new zones, zone/city chapter alignment, idempotent summaries, bounded errors, PII-free count logs, and all-or-nothing rollback. Legacy binary `.xls` requires conversion.
- Added JSON/form POST `/api/manage_zone` with current active-account `MANAGE_ZONES` authorization, zone-then-city catalogue locks, normalized duplicate checks, chapter and eligible same-chapter coordinator validation, explicit coordinator clearing, atomic child-city chapter alignment, reference-safe deletion, bounded output, PII-free audit metadata, and rollback proof.
- Added JSON/form POST `/api/manage_city` with the same authorization/lock order, global normalized duplicate checks, enforced zone/chapter alignment, automatic chapter alignment on moves, member/profile free-text reference guards for rename/delete, bounded output, and rollback proof.
- Added GET plus JSON/form POST `/api/get_users_by_zone` with active-Bearer/current-account checks, deterministic ID/exact-name resolution, ID precedence, a fixed legacy name-query bug, 100-row pagination, eligible-member filtering, global/city privacy exclusion, owner visibility, field-aware phone/avatar, and no broad PII/account/credential selection.
- Added GET/POST `/api/get_my_zone` as a current-active self-only lookup with spoofed-body immunity, trimmed case-insensitive city matching, deterministic oldest mapping, HTTP 200 unavailable compatibility, explicit null coordinator handling, shared coordinator eligibility/privacy, and no email.
- Added GET/POST `/api/get_cities` with deterministic city/chapter/source-zone output, preserved orphan zone IDs, peer throttling, no member data, and no reusable-key authority.
- Added GET/POST `/api/get_zones` with nested city identity, current coordinator eligibility gates, global/phone/avatar visibility enforcement, malformed-privacy fail-closed behavior, explicit null coordinators, and no coordinator email/role/department selection.
- Updated welfare coordinator mapping/UI to remove email and use member ID for self-message prevention; anonymous users still receive public zone/city structure and in-app messaging retains its existing sign-in gate.
- Added GET/POST `/api/get_vouchers` with optional class-year filtering, active-voucher-only deterministic selection, peer/year throttling, and a minimum public ID/name/year/chapter projection; the legacy shared key has no effect.
- Added GET/POST `/api/voucher_pending` with active Bearer/current-voucher checks, exact owner scoping, pending-only deterministic results, and a dedicated protected schema.
- Added JSON/form POST `/api/vouch_action` with concealed cross-owner IDs, pending replay protection, verified/unapproved registrant guards, deterministic locks, atomic approval or denial-without-account-mutation, bounded responses, PII-free audit fields, and post-commit notifications to the registrant plus exact reviewed managers on approval.
- Updated registration to request vouchers for the selected class year and label choices by name/class rather than email.
- Added current-database authorization, deterministic actor/target row locks, lower-role hierarchy, verified-email and state guards, bounded response fields, and rollback-safe approval/rejection transactions.
- Added private post-commit account-status email; provider failure cannot roll back the database decision.
- Added PII-free structured approval audit events. No durable audit table was added because the legacy schema has no suitable table and schema changes require separate review.
- Added self-deactivation and downward administrator activation/deactivation with current database facts, deterministic row locks, approved/verified reactivation guards, transactional refresh-token revocation, bounded output, rollback proof, PII-free audit events, and post-commit activity email.
- Added bounded role changes to `/api/manage_user_account` using locked current-database actor/target facts, reviewed aliases, self/hierarchy/state gates, atomic refresh-token revocation, bounded responses, rollback proof, and PII-free audit fields.
- Added self/downward-manager profile visibility read/update with current database facts, a fixed 15-field allowlist, deterministic locks, partial-merge/upsert behavior, malformed-JSON fail-closed handling, rollback proof, bounded output, and PII-free audit logging.
- Updated the migration ledger and the workbook's route, grouped, confirmation, and developer-audit views for both visibility routes.
- Replaced `/api/get_users_by_action` with separate bounded approved-directory and downward account-management modes. Directory output applies global/per-field privacy server-side and selects no credential columns; administrator output uses current database authorization and excludes self, peers, higher/unrecognized roles, and unverified accounts.
- Implemented `/api/update_profile` for JSON, flat form, nested multipart profile fields, and optional avatar uploads. User/profile fields are allowlisted, chapter references are validated, responses omit credentials, and cross-user updates require current downward account-management authority.
- Added 5 MB decoded avatar limits, JPEG/PNG/GIF verification, pixel bounds, metadata-stripping re-encoding, generated filenames, profile-only static exposure, attachment metadata, and database/file rollback cleanup. The workbook's four active views now mark the route built and retain production/runtime proof as pending.
- Security-retired `/api/update_user_account` after proving its caller-selected cross-account authorization flaw, raw request/token logging, seven reviewed-schema mismatches, and absence from current source/built frontend. Both legacy methods now return bounded HTTP 410 guidance to `/api/update_profile`; the regenerated inventory quarantines the two legacy-controller copies too.
- Security-retired standalone `/api/update_user_role` and `/api/manage_user_roles`; current source and built frontend use only the shared account route. Their arbitrary/free-form role contracts are not copied, chapter edits remain in the profile flow, and the legacy `roles` table remains non-authoritative metadata.
- Implemented GET and POST `/api/get_alumni_stats` with one bounded four-integer response, current active-account recheck, shared per-user rate limiting, exact reviewed PHP count semantics, and no legacy application-token authorization. No current source or built-frontend caller was found.
- Implemented GET and POST `/api/get_chapters` with a public enabled-chapter list when `user_id` is absent and an active-Bearer self/downward-manager assignment lookup when it is supplied. Output is bounded, unknown roles fail closed, duplicate assignments resolve deterministically to the oldest category row, and no assignment preserves HTTP 200 with `chapter: null`.
- Implemented POST `/api/get_setup_parameters` with bounded JSON/form input, active-Bearer current-state recheck, per-user rate limiting, explicit setup fields, compacted comma values that retain `0`, deterministic oldest-row selection, and real HTTP 400/404 results. The reusable legacy `X-API-Key` is ignored; no current route caller was found.
- Security-retired `/api/create_role`, `/api/manage_role`, and `/api/get_roles` with tested GET/POST HTTP 410 guidance to the fixed account-role contract. Their six backup/old-controller copies are containment targets, the three root copies remain artifact retirements, and the later current inventory totals are recorded above.
- Implemented public JSON/form/multipart registration with peer/identity throttling, bounded password/phone/date input, Argon2id, enabled chapter/city and current active voucher checks, deterministic member codes, and server-owned role/coordinator/year/account state.
- Registration now commits the user, Ion Auth member-group assignment, alumni category, private-by-default profile, optional pending vouch, finite verification code, and optional normalized avatar metadata atomically. A late database failure removes the new file; post-commit mail failure preserves the account and returns resend guidance.
- Removed the frontend's chapter `1` registration assumption; the selected city now supplies its live `chapterId`, and submission fails locally when no mapping is available.

## Decisions and Reasoning

- Scope decisions (2026-09-18, user): (1) Firebase-related migration work is deferred — FastAPI+MariaDB stays canonical and the frontend Firebase transport is not flipped; the read-only `validate_event_survey_export.py` gate stays. (2) There is no legacy V1 chat data, so legacy reconciliation is moot; the V1 `410` tombstones and ADR 0005's V2-only target stand with nothing to migrate. (3) Deployment/cutover (Goal 12) is deferred. These are deferrals, not completions.
- Live schema drift (2026-09-18): the supplied dump is the current `10.5.26-MariaDB` schema. `news_feeds_setup` is a real live table (RSS/Atom feed config, 33 rows) that the `news/feeds` service should eventually read instead of hardcoded feeds; it is now mapped but not yet wired into the service.
- Malware scanning (2026-09-18, user-approved): no external scanner for the compatibility phase. Upload safety relies on content-signature/type validation, image re-encode, size/pixel bounds, private non-public storage, and owner/participant-gated authenticated retrieval. The residual risk that a client opens a malicious upload is explicitly accepted; a ClamAV/commercial provider can be added later without changing the storage contract.
- Staged-attachment cleanup scheduling (2026-09-18, user-approved): an OS scheduler (cron/systemd timer/Windows Task Scheduler) invokes `scripts/reap_chat_attachments.py`; the operation and entry point are in-repo and tested, and deployment-host registration belongs to Goal 12 (`python-backend/docs/chat-attachment-reaper.md`). The sweep's global access path is indexed by `d5e6f7a8b9c0` because the owner-scoped staged-expiry index cannot serve `message_id IS NULL`.
- JWT role claims never grant approval authority; current `users.user_role` and active state are loaded under lock.
- Managers may decide reviewed member aliases; administrators may also decide managers; super administrators may decide lower reviewed roles. Self, peer/higher, and unknown-role targets fail closed.
- Approval requires verified email. Rejecting an already approved account is redirected conceptually to the pending deactivation/account-management slice.
- The frontend source directly calls `/api/approve_user`, but currently changes roles through `/api/manage_user_account`; do not assume the two PHP role routes are active frontend contracts.
- The authorization role set is fixed to alumni, manager, legacy admin/super admin, and the reviewed approval/content/event/finance/storekeeper administrator categories. Category roles receive only their mapped permissions; unknown roles fail closed.
- Legacy `admin` may assign any reviewed role to a lower target, preserving the active frontend's `admin` to `super admin` mapping. A super administrator may change another recognized role but never self; administrative promotion requires an active, approved, email-verified target.
- `users.user_role` is the authorization source. Ion Auth groups and free-form `roles` rows are separate legacy stores and do not grant FastAPI permissions.
- Aggregate alumni statistics preserve PHP's inclusion rules but require an active Bearer principal. They expose no member rows, identifiers, credentials, or free-form filters; legacy token values in query/body data have no effect.
- Birthday discovery preserves PHP's active-user eligibility, Lagos calendar boundary, February 29 observance, and missing-key-public/explicit-false-private rule, but requires a current active Bearer account. Malformed visibility fails private, global directory hiding remains independent, and private avatars are masked. The active frontend's five card fields are retained; exact DOB, age, contact, account, and unrelated profile fields are intentionally retired. Processing is read-only and bounded separately from the 200-row output limit.
- Chapter discovery is public only without a user target so registration can obtain enabled IDs without a shared application secret. A supplied `user_id` changes the contract to protected current-database self/downward-manager authorization; an invalid supplied Bearer token never falls back to public access.
- Setup-parameter lookup requires a current active Bearer account because its name is caller-selected and can address any future row, not a reviewed fixed public allowlist. The four generic names seen in the SQL snapshot do not justify exposing future configuration through a reusable application key.
- Dynamic `roles` definitions are not an authorization source. The legacy definition routes have no current caller or reviewed data, trust only a reusable application key, and conflict with both the table's mandatory `user_id` and the fixed account-role policy, so a `Live/KEEP` audit label does not justify rebuilding them.
- Legacy staff deactivation cannot be treated as parity work: deleting a caller-selected user after deactivation is incompatible with the reviewed account-state contract. Hidden callers must move to `manage_user_account`; the tombstone must remain.
- Push-token ownership must come from the current authenticated member, never a caller-selected ID or reusable application key. Do not add storage or provider behavior until hidden/mobile callers and the live data model are confirmed; token values must not be logged or returned.
- Notification ownership is current-principal-only. The body can retain legacy `user_id` compatibility noise but it cannot affect reads or mutations. SQL predicates include the authenticated recipient and the legacy account-creation boundary; the current per-row `is_read` schema replaces the absent PHP `notifications_read` table.
- Announcement reads are intentionally public after current frontend/homepage review, but are bounded and presentation-shaped. Announcement mutation authority is current database `MANAGE_CONTENT`, never a JWT role claim or reusable API key. Event-administrator frontend display affordances do not expand the canonical content permission until product policy explicitly approves that change.
- Event administration is a separate reviewed `MANAGE_EVENTS` permission, reloaded from current locked database facts. The public feed is restricted to approved, public, non-draft events; member-only/private and draft events need a separately approved protected-read contract. The legacy reusable key and caller-provided creator/approval controls are intentionally not copied.
- Event RSVP is self-service only: body `user_id` and year are compatibility noise and cannot select the account or override the parent event. FastAPI locks the parent event before duplicate/capacity decisions, preserves an existing note when a repeat registration omits it, and exposes attendee PII only to a freshly authorized `MANAGE_EVENTS` actor. The active frontend uses POST for attendee retrieval, so the legacy GET/key-only variant remains unretained.
- The three legacy privacy-policy routes are not a safe next copy: the current Privacy page is static, no frontend API caller was found, and the reviewed FastAPI model set has no `privacy_policy` table. Preserve the inventory evidence and require approved storage/traffic proof before designing a replacement.
- Registration is intentionally public but bounded. It never accepts legacy shared-key or Bearer authority, ignores privilege-bearing extras, requires the reviewed member group, and owns initial authorization/account state on the server. Mail delivery occurs after commit so retry guidance cannot create duplicate partial users.
- Voucher discovery is public only for the minimum registration picker projection. Pending and decision operations trust neither JWT role claims nor caller-selected owner IDs: they reload current active/voucher state and bind access to `vouches.voucher_id`. Approval changes both rows atomically; denial changes only the vouch. All notification work is post-commit.
- City and welfare-zone catalogues remain public because registration and the current welfare route need them before authentication. Public geography never trusts the embedded application key. Coordinator email is not part of the visibility model and is therefore never exposed; assigned coordinator phone/avatar follow eligibility plus the same stored visibility defaults and malformed-state fail-closed behavior as the member directory.
- Zone-member discovery is protected because the legacy response exposes membership and broad personal/account data behind only a reusable key. FastAPI resolves a zone ID or exact name but returns only active/approved/verified members; another member's global hiding or private city removes the row so zone membership itself does not bypass location privacy. Missing keys remain public for compatibility, malformed JSON fails private, the owner may see their own row, and pages are capped at 100.
- Self-zone lookup accepts no target identity and reloads current active account state. Duplicate case-insensitive city mappings select the oldest city row; missing/unknown/orphan mappings keep the legacy HTTP 200 unavailable shape. Coordinator output reuses the reviewed public-zone eligibility/privacy projection and never includes email.
- Geography mutation trusts only the current active database role through `MANAGE_ZONES`; JWT role claims and reusable application keys grant nothing. Zone and city writes share one deterministic lock order. Normalized names conflict globally; zones own their child-city chapter metadata; coordinators must be active, approved, verified, and in the same chapter; and legacy free-text member/profile city references block city rename/delete rather than being silently orphaned.
- Bulk geography import preserves those authorization, lock-order, duplicate, and chapter-alignment rules across the entire file. It prevalidates before mutation, limits parser and archive work, reports actual changes so reruns are idempotent, and rolls back every row on a late failure. Binary `.xls` and raw parser errors are intentionally not copied from PHP; new-zone chapter 1 remains a compatibility rule that must be proven against approved production data.
- Alumni import is an account-management operation, not reusable-key ingestion. Current database facts and downward hierarchy govern every existing target; complete roster/chapter/city/configuration validation precedes SQL. Existing account authority, credentials, state, and visibility remain authoritative. New members have no known password and must use the secure reset flow. The client coordinator flag is counted as ignored evidence but never grants privilege.
- `manage_user_account` preserves PHP's `profile_status` during activation/deactivation and changes only `active`; approval state and verified email gate reactivation. Deactivation revokes every active refresh token transactionally.
- Visibility defaults remain PHP-compatible public when a key is absent, but malformed stored JSON fails closed to private. Directory/list responses must apply these settings before returning member fields.
- `get_users_by_action` defaults to the approved directory rather than PHP's broad verified-user fallback. Unknown actions are rejected. Pending/all-user modes require current `MANAGE_ACCOUNTS` permission and use a separate minimum account projection limited to strictly lower reviewed roles.
- Profile editing never copies PHP's implicit `active=1`; an inactive target remains inactive. Avatar bytes are decoded and re-encoded before storage, and a newly stored file is deleted when its database transaction fails.
- A developer-audit `Live/KEEP` label is reachability evidence, not approval to copy an unsafe contract. `update_user_account` is treated as a security retirement because its supported profile purpose is superseded and its remaining fields have no reviewed schema meaning.

## Known Issues

- **Resolved on 2026-09-18:** the working tree now passes the full verification gate (474 tests, 0 failures, 90.21% coverage; Ruff format/check, strict mypy, `pip check`, `alembic check`, Bandit, and `pip-audit` all clean). The earlier 86.30% / 7-mypy-error / 3-unformatted-file state is superseded. Keep the 90% floor honest: it is currently met partly by new tests for already-implemented Goal 7/8/9 code, so any further dead code should be deleted rather than covered.
- The frontend cannot yet enforce a type gate: `npx tsc --noEmit` fails with 11 pre-existing baseline errors outside migration scope (`src/data/content.ts`, `AdminStorePage.tsx`, `social.adapter.ts` ×2, `mockAuth.ts` ×3, `ErrorBoundary.tsx` ×2, `PhoneNumberInput.tsx`, `renderIcon.tsx`). There is no `typecheck` script in `frontend/package.json`, and repository-wide Prettier still fails its ~477-file baseline, so `npm run build` (which does not type-check) remains the only green frontend gate.
- A scripted Playwright + Chromium journey now covers the Goal 6 V2 inbox: `frontend/e2e/messages-v2-inbox.mjs` logs in through the real UI and asserts the seeded thread renders from a live `chat_api/v2_get_threads` 200 (`npm run e2e:messages`, exit 0, screenshot `tmp/e2e-messages-v2-inbox.png`). Other authenticated browser journeys (registration, admin, store) remain unproven, and the missing production CORS middleware (Goal 10) is still masked by the test's in-browser API proxy.
- Largest remaining test gaps by uncovered branch/statement volume: `app/services/events.py` 79%, `app/services/members.py` 90% (large absolute miss), `app/integrations/alumni_import.py` 78%, `app/integrations/geography_import.py` 79%, `app/services/announcements.py` 80%, `app/services/marketplace.py` 83%, `app/repositories/marketplace.py` 64%, `app/services/news.py` 87%, `app/services/chat.py` 88%.
- Goal 6 still owes deployment-host registration of the reaper (OS scheduler chosen; runbook at `python-backend/docs/chat-attachment-reaper.md`) and provider delivery/cutover. The headless-browser V2-inbox journey now passes, the reaper is built/tested, malware scanning is decided (no external provider), and legacy V1 reconciliation is moot (no V1 data).
- `alumni_portal_v2.sql` (real production data) is untracked and not gitignored at the repo root; it must not be committed, and should be moved to an ignored location.
- The `news/feeds` service still hardcodes feeds; the live `news_feeds_setup` table (33 rows) is now mapped but not yet wired in (Goal 7 follow-up).
- Developer audit discrepancies remain unresolved: 175 summary versus 163 detail endpoints, missing `Socials` controller row, and 24-versus-25 core functions.
- Authenticated frontend role journeys, current production role distribution, live SMTP/Redis, approved current-database behavior, deployment, and cutover are unverified.
- Durable audit storage needs an approved schema/operations decision.
- Production media persistence and old-avatar retention/deletion policy remain unverified.
- Current production city/chapter/member-group/voucher data, live registration SMTP delivery, and the complete browser registration-to-verification journey remain unverified.
- The authenticated voucher pending/decision UI journey, current production voucher assignments, exact manager-role distribution, live voucher SMTP, and production concurrency remain unverified.
- Approved production city/zone relationships, coordinator eligibility/privacy values, and public registration/welfare browser journeys remain unverified.
- Hidden callers, approved production zone-member counts/privacy states, duplicate city mappings, and authenticated roster/self-zone browser behavior remain unverified.
- Hidden callers, approved production role/chapter/coordinator/city-reference data, representative concurrent administrator mutations, and the administrator UI journey for `/api/manage_zone` and `/api/manage_city` remain unverified.
- No current source or built-frontend caller for `/api/upload_zones_cities` was found. Approved production chapter-1 and duplicate geography data, edge/proxy body-size enforcement, representative concurrent imports, and the administrator upload UI remain unverified.
- No current source or built-frontend caller for `/api/get_setup_parameters` was found; hidden traffic and the approved production setup-name/value contract remain unverified.
- The current frontend source calls `/api/get_birthdays`, but its authenticated browser journey, hidden callers, approved production birth-date/visibility quality, candidate volume, and midnight/leap-day runtime behavior remain unverified outside the disposable SQL suite.
- No current source or built-frontend caller for `/api/import_alumni` was found. Hidden traffic, approved production chapter/city/membership/account data, representative 500-row scale, edge/proxy body limits, concurrency, and an administrator import/password-setup workflow remain unverified.
- Hidden callers for the three retired dynamic role-definition routes remain unverified; they must receive cutover guidance rather than reactivating the unsafe contracts.
- Hidden callers for `deactivate_staff` and `user_tokens` remain unverified. Staff callers must migrate to `manage_user_account`; push callers require a separately reviewed provider/schema and authenticated self-service contract before any replacement is built.
- No current source or built-frontend caller was found for the notification routes. Hidden/client behavior, the live notification data shape, and creator/recipient/provider/retry/opt-out requirements remain cutover gates. `/api/create_notification` is deliberately absent.
- Announcement integration tests require the disposable MariaDB URL; current runtime checks only prove type/lint and test collection. Hidden callers, public-feed frontend behavior, exact content-admin role product policy, production object/media storage/CDN policy, cross-process cleanup, and authenticated browser workflows remain cutover gates.
- Marketplace requires disposable-MariaDB/browser proof, a manager-versus-current-`MANAGE_STORE` product decision, exact legacy GET compatibility decision, production object-media policy, and hidden-caller confirmation. Projects, leadership, vacancies, event core, and RSVP/attendee routes require the same disposable-MariaDB/browser, object-media, hidden-caller, and cutover proof; their public/active frontend reads are intentionally POST. Registration-form routes remain unbuilt. Vacancy currency is currently UI-only because the reviewed `job_vacancies` table has no currency column. Do not reintroduce the absent legacy `market` table to satisfy the old route names.

## Important Constraints

- Run Python only through `python-backend/.venv` and set `PIP_REQUIRE_VIRTUALENV=true` for package execution.
- Never use production data or credentials for tests.
- Preserve the fourteen security-sensitive inventory retirements, ten workbook consolidated replacements, sixteen executable tombstones, root `Api.php` retirement, user workbook notes, and unrelated working-tree changes.

## Commands and Tests

From `python-backend/`, with `ALUMNI_TEST_DATABASE_URL` pointing only to the disposable schema:

```powershell
$env:PIP_REQUIRE_VIRTUALENV = "true"
.\scripts\verify.ps1
```

Gate-restoration run (2026-09-18, second session). Disposable schema rebuilt from the sanitized dump and upgraded to head, then the full suite: **474 passed, 0 failed, 90.21% coverage**, with `pip check`, Ruff format/check (121 files), strict `mypy` (121 files), Alembic drift check, Bandit, and `pip-audit` all clean.

```powershell
# repository root: rebuild the disposable schema (passwordless local MariaDB on 127.0.0.1:3313)
$mysql = ".local-tools\mariadb\MariaDB 12.3\bin\mariadb.exe"
& $mysql --host=127.0.0.1 --port=3313 --user=root -e "DROP DATABASE IF EXISTS alumni_portal_gate_test; CREATE DATABASE alumni_portal_gate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $mysql --host=127.0.0.1 --port=3313 --user=root --database=alumni_portal_gate_test -e "source C:/Users/nubiaville/Desktop/work folder/Alumni Portal/.local-state/sanitized-schema.sql"

# python-backend
$env:PIP_REQUIRE_VIRTUALENV = "true"
$env:ALUMNI_DATABASE_URL = "mysql+pymysql://root@127.0.0.1:3313/alumni_portal_gate_test"
python -m alembic upgrade head        # -> c4d5e6f7a8b9
python -m alembic check               # No new upgrade operations detected.
$env:ALUMNI_TEST_DATABASE_URL = $env:ALUMNI_DATABASE_URL
Remove-Item Env:ALUMNI_DATABASE_URL
python -m pytest --cov=app --cov-report=term-missing -q     # 474 passed, 90.21%
python -m ruff format --check app migrations scripts tests
python -m ruff check app migrations scripts tests
python -m mypy app migrations scripts tests
python -m bandit -q -r app
python -m pip_audit -r requirements-dev.lock
```

Focused slices added in that session (same disposable URL):

```powershell
python -m pytest tests/test_paystack.py -q     # 32 passed, app/integrations/paystack.py 100%
python -m pytest tests/test_store_edges.py -q  # 52 passed
python -m pytest tests/test_blog_edges.py -q   # 15 passed
python -m pytest tests/test_uploads_edges.py -q  # 40 passed (no database needed)
```

Goal 6 staged-attachment reaper slice (2026-09-18), from `python-backend` with `ALUMNI_TEST_DATABASE_URL` pointing only at `alumni_portal_reaper_test` on `127.0.0.1:3313` (fresh schema imported from `.local-state/sanitized-schema.sql`, `alembic upgrade head` → `d5e6f7a8b9c0`, `alembic check` clean):

```powershell
# access-path evidence: seed synthetic chat volume with half-expired staged uploads
python -m scripts.chat_retrieval_probe --database-url $env:ALUMNI_TEST_DATABASE_URL --threads 200 --messages-per-thread 50 --other-threads 300
# pre-index: staged_purge type=ALL ... Using filesort ; post-index: type=range key=idx_msg_attachment_staged_purge
python -m pytest tests/test_chat_reaper.py tests/test_chat_service.py tests/test_schema_models.py -q   # 18 passed
python -m scripts.reap_chat_attachments --database-url $env:ALUMNI_TEST_DATABASE_URL --limit 50       # aggregate JSON, exit 0
```

Goal 6 headless-browser V2 inbox journey (2026-09-18), from `frontend/` with the disposable MariaDB up:

```powershell
npm run build            # 8,524 modules, ~41s, known >500 kB chunk warning only
npm run e2e:messages     # seeds the disposable DB, starts Uvicorn + vite preview, drives Chromium
# -> {"status":"ok","final_url":"http://127.0.0.1:4173/messages","thread_statuses":[200], ...}
# screenshot: tmp/e2e-messages-v2-inbox.png
```

Frontend evidence (from `frontend/`, no production credentials or data):

```powershell
npm ci                 # 300 packages, 17 advisories (unchanged baseline)
npm run build          # 8,524 modules in 58.56s -> dist/ (stale-Browserslist + >500 kB chunk warnings only)
npx tsc --noEmit       # 11 pre-existing baseline errors; the one migration-owned error was fixed

# python-backend: live smoke against the disposable schema, then stop and confirm the port is closed
$env:ALUMNI_ENVIRONMENT = "development"
$env:ALUMNI_DATABASE_URL = "mysql+pymysql://root@127.0.0.1:3313/alumni_portal_gate_test"
$env:ALUMNI_PUBLIC_BASE_URL = "http://127.0.0.1:8099/"
$env:ALUMNI_UPLOAD_ROOT = "..\tmp\smoke-uploads"
python -m uvicorn app.main:app --host 127.0.0.1 --port 8099 --log-level warning
# GET /health/live -> {"status":"ok"}; GET /health/ready -> {"status":"ready","database":"ready"}
# POST /api/get_events, /api/get_listings, /api/get_projects -> HTTP 200 with events/listings/projects keys
```

Latest full result: an isolated passwordless local MariaDB instance under `tmp/goal7-mariadb`, bound only to `127.0.0.1:3312`, was rebuilt from the sanitized 68-table schema into `alumni_portal_goal7_test`; it contains no production credentials or data. The disposable schema was upgraded through `9d9d1f2a7c31` before verification, adding the event-form compatibility objects. The 278-test full suite completed at 90.02% coverage, clearing the 90% threshold; `pip check`, Ruff format/check, strict mypy, Alembic with no new operations, Bandit, and `pip-audit` were re-run. Public event reads now include MySQL-derived active-form count/availability for the existing adapter. The Firebase survey app remains the live data owner: its string IDs, immutable version documents, max-selection settings, and historical submissions require an approved sanitized export and explicit event/user mapping before FastAPI transport can replace Firebase. Do not flip the frontend transport or delete Firebase until that import is rehearsed and approved. Earlier failures (OpenAPI inline-schema assertion, reserved `.test` email, orphan form cleanup, coverage, baseline stamp, and Bandit B101 assertions) are fixed. Importing the schema must run from the repository root because `.local-state/sanitized-schema.sql` lives there. The workbook remains read-only. Repository-wide Prettier remains a 477-file baseline failure; npm previously reported 17 dependency advisories (2 low, 2 moderate, 12 high, 1 critical), and Vite retains stale-Browserslist and large-bundle warnings. No lockfile-changing automatic fix was attempted.

Goal 6 bounded-inbox checkpoint (2026-09-18), run from `python-backend` with `PIP_REQUIRE_VIRTUALENV=true` and `ALUMNI_DATABASE_URL`/`ALUMNI_TEST_DATABASE_URL` pointing only at the disposable `alumni_portal_goal6_perf_test` schema on `127.0.0.1:3313`:

```powershell
# 1) rebuild a fresh disposable schema (repository root) and upgrade it
& ".local-tools\mariadb\MariaDB 12.3\bin\mariadb.exe" --host=127.0.0.1 --port=3313 --user=root `
  -e "DROP DATABASE IF EXISTS alumni_portal_goal6_perf_test; CREATE DATABASE alumni_portal_goal6_perf_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& ".local-tools\mariadb\MariaDB 12.3\bin\mariadb.exe" --host=127.0.0.1 --port=3313 --user=root `
  --database=alumni_portal_goal6_perf_test -e "source C:/Users/nubiaville/Desktop/work folder/Alumni Portal/.local-state/sanitized-schema.sql"
# 2) in python-backend
$env:ALUMNI_DATABASE_URL = "mysql+pymysql://root@127.0.0.1:3313/alumni_portal_goal6_perf_test"
python -m alembic upgrade head   # head c4d5e6f7a8b9
python -m alembic check          # No new upgrade operations detected.
python -m scripts.chat_retrieval_probe --database-url $env:ALUMNI_DATABASE_URL --threads 200 --messages-per-thread 500 --other-threads 3000
$env:ALUMNI_TEST_DATABASE_URL = $env:ALUMNI_DATABASE_URL
Remove-Item Env:ALUMNI_DATABASE_URL
python -m pytest tests/test_auth_integration.py -k v2_chat -q                              # 6 passed
python -m pytest tests/test_schema_models.py tests/test_chat_service.py tests/test_auth_integration.py -k "schema or chat or legacy_tables" -q   # 18 passed
python -m pytest --cov=app --cov-report=term-missing -q                                    # 332 passed, 86.30% coverage (floor not met)
```

The probe's key plans: before `c4d5e6f7a8b9`, `inbox_page`/`unread_counts`/`unread_summary` used `idx_member_id` with a temporary table/filesort and `message_page` used the existing `idx_thread_id`; after it, the bounded thread count is `key=idx_thread_participants_inbox ... Using index` (index-only) and the other shapes keep their access paths. `alembic downgrade -1` followed by `alembic upgrade head` re-created the index, proving the migration is reversible. Frontend `npm run build`/browser proof remains a clean skip: `frontend/node_modules` and `vite` are absent. Repository-wide `ruff format --check app migrations scripts tests` currently fails on three concurrently edited Goal 8/9 files (`app/integrations/mail.py`, `app/repositories/store.py`, `app/services/store.py`); `ruff format --check` and `ruff check` pass for every file this Goal 6 slice touched.

## Next Actions

1. Keep Goal 6 active until its acceptance gate passes. Its only remaining in-repo items are deployment-host scheduler registration and provider delivery; the browser journey, reaper, and legacy disposition are done.
2. Goal 7 remaining independent work: `app/repositories/marketplace.py` (64%) and `app/services/announcements.py` (80%) coverage; `events.py` is now at 89%. `news/feeds` wiring and Firebase are deferred by the user.
3. Goal 8: concurrency proof, 24/24 route inventory, products/checkout/verify contract review, the store-catalogue browser journey, the Paystack sandbox live-smoke, and the `/api/paystack/webhook` alias are done. Remaining: authenticated cart/checkout browser flows against the sandbox, and swapping the test Paystack keys for live keys at cutover. Backend secret key goes in `python-backend/.env` (`ALUMNI_PAYSTACK_SECRET_KEY`); public key already in `frontend/.env.local` (`VITE_PAYSTACK_PUBLIC_KEY`).
4. Goal 9: `contact_us` is implemented; uploads/email are done. `create_notification` (needs storage/caller/recipient/provider/retry/opt-out design) and push delivery (needs a provider choice) remain blocked.
5. Goal 10: CORS allowlist, security headers, rate-limit matrix, metrics endpoint, threat model, and nested/PII/payment-payload log redaction are done; cookie/CSRF is N/A (Bearer-only auth). Remaining Goal 10: a durable audit table (schema decision), retention/deletion procedures (policy decision), and CI dependency/container scanning (Goal 12).
6. Goal 11: edge-data tests and the release gate (`scripts/verify.ps1`) are done. Remaining: a normalized PHP-vs-FastAPI response-comparison harness and load tests (both deferred, not needed for in-repo completion), plus any retained-route contract coverage gaps.
5. Keep privacy-policy and legacy `market` rows blocked pending current storage and live-caller/traffic proof; do not manufacture a table or contract.
6. Do not adopt `tsc --noEmit` or Prettier as gates until their documented baselines (11 type errors, ~477 Prettier files) are cleared or explicitly waived by the user.
7. Data-quality catalogue (orphan/duplicate rows, invalid enums, zero dates) can now be produced from the supplied live dump, but only as aggregate/non-PII output; no production rows may enter fixtures or logs.

## Re-reviewed Workbook Baseline

Use `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx` as a read-only reference for the reviewed route scope. The Markdown breakdown is the execution source of truth for continuation.

| Scope item | Current count/status |
| --- | --- |
| Catalogue routes | 159 |
| Direct conversions | 145 |
| Consolidated replacements | 10 |
| Platform replacement | 1 (`/my404/index`) |
| Schema/caller-blocked decisions | 3 (`/api/create_market`, `/api/manage_market`, `/api/get_market`) |
| Already built in Python | 40 |
| Implemented but database integration pending | 18 total across the two implemented-pending workbook statuses |
| Still to build and test | 85 |
| Pending schema/provider design | 1 (`/api/create_notification`) |
| Full inventory boundary | 548 items: 445 controller methods plus 103 root `Api.php` artifact functions |

Do not treat source/static implementation, focused tests, skipped MariaDB tests, or workbook status as full migration completion. Every goal needs implementation, authorization, database/side-effect, compatibility, and applicable browser/provider/cutover evidence.
