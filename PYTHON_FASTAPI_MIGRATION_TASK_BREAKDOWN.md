# PHP to FastAPI Migration Task Breakdown

## Purpose

This document is the execution plan for replacing the existing CodeIgniter/PHP backend with a Python FastAPI service while preserving the current MySQL-compatible database, frontend behaviour, stored data, file URLs, and external integrations.

The migration is a controlled reimplementation. It must not be performed as a line-by-line translation of the PHP code because the current application contains confirmed security weaknesses, duplicated controllers, inconsistent authorization, raw SQL, sensitive logging, and legacy behaviour that must not be reproduced.

## Source of Truth

- Current PHP application: `Backend/alumniappV2 - PHP/`
- Current database export: `Backend/alumni_portal_v2.sql`
- Current framework: CodeIgniter/PHP
- Target framework: FastAPI/Python
- Current database driver: `mysqli`, indicating a MySQL-compatible database
- Frontend API behaviour and the live database remain authoritative where they differ from comments or backup controllers.

## Definition of Done

The migration is complete only when:

- Every retained frontend endpoint is implemented by FastAPI or explicitly retired.
- Request fields, response payloads, status codes, pagination, file URLs, and business rules have verified compatibility.
- Existing users can authenticate without a bulk password reset.
- The FastAPI service reads and writes the existing database safely.
- Payment, chat, membership, approval, voucher, notification, and upload workflows pass integration tests.
- Confirmed PHP security weaknesses are removed rather than copied.
- Secrets and personal data are absent from source control and logs.
- Production cutover, monitoring, rollback, and database backup procedures are tested.
- The PHP application is disabled only after the rollback window has passed.

## Live Goal Status

Last verified: 2026-09-09 (Africa/Lagos)

Overall status: **In progress — the FastAPI foundation, schema connection, and first authentication slice are verified; full endpoint parity and production cutover remain incomplete.**

For this chat goal, "all endpoints finished" means every discovered PHP route candidate has a recorded retain, replace, merge, retire, internal-only, or remove decision; every retained capability has a FastAPI implementation; and the required contract, authorization, database, and frontend-compatibility checks pass. Retired and internal-only methods do not require a public FastAPI route, but their disposition must be evidenced.

| Completion gate | Current state | Current evidence | Required proof to complete |
| --- | --- | --- | --- |
| Separate FastAPI copy is complete | Foundation, auth, and first member slice in progress | `python-backend/` now contains a Python 3.13.7 FastAPI service, locked dependencies, typed configuration, health routes, database engine, explicit schema mappings, Alembic baseline, and ten SQL-backed, rate-limited authentication/member routes | Implement and verify every retained business capability; obtain approval for production worker/proxy configuration |
| All endpoint candidates are finished | In progress | Deterministic inventory covers 445 controller methods plus 103 root-artifact functions now classified as non-controller retirement candidates; ten primary auth/member routes have FastAPI implementations and sanitized-SQL tests, while eight unsafe or superseded primary routes have explicit HTTP 410 tombstones, but frontend parity is not yet proven | Confirm remaining controller dispositions and add retained-route implementation, OpenAPI contract, authorization tests, database tests, and frontend compatibility evidence |
| Existing SQL database can be connected safely | Export-compatible test connection verified | A localhost-only MariaDB 12.3.3 target imported all 68 tables after all `INSERT` statements were removed; readiness, 778-column mapping parity, empty-table checks, and rollback-safe write tests pass | Read-only comparison and permission tests against the approved current office database remain required; no production access has been attempted |
| Required tests are successful | Current-scope suite passing | 118 tests pass at 98.54% statement/branch coverage for the foundation, schema, inventory, authentication/recovery/verification, post-verification notification, access-code, authorization-policy, member-profile, rate-limit, and explicit security-retirement slices; Ruff, mypy, Alembic drift detection, Bandit, dependency audit, and `pip check` pass | Remaining endpoint, protected-route authorization, live-provider, concurrency, frontend, load, deployment, and cutover tests do not exist yet |

### Initial Review Findings

- **P1 — Secret and personal-data containment is the first gate.** Credential-bearing configuration locations exist in `application/config/database.php`, `email.php`, `jwt.php`, and `vapid.php`. The SQL export contains user, session, refresh-token, OTP, and other data-bearing tables. Values must not be copied into the Python service, fixtures, logs, or this document.
- **P1 — Database success cannot be claimed from the SQL file alone.** The export establishes schema evidence, not current server access, credentials, schema drift, or successful read/write behaviour.
- **P1 — Endpoint completeness originally omitted implicit-public PHP methods.** The master register has been corrected from 427 explicitly-public methods to 445 callable non-constructor controller methods. Internal helpers are recorded as internal-only candidates rather than silently exposed.
- **P2 — The standalone `Backend/alumniappV2 - PHP/Api.php` is outside `application/controllers/` and has 103 non-constructor functions.** It appears to contain overlapping and unrelated legacy capabilities and must be classified before the endpoint inventory can be signed off.
- **P2 — Frontend contract evidence is not present at the workspace root.** The current workspace contains the backend snapshot, SQL export, and this plan. Goal 1 still needs the actual frontend source, captured traffic, or an approved contract fixture set before retained-route parity can be verified.
- **P2 — Repository history is unavailable here.** This workspace has no `.git` directory, so history-based secret exposure, ownership, and change provenance cannot be verified from this copy.

## Mandatory Execution Rules

- Never run migration tests against the production database.
- Take a verified database backup before any schema or production write change.
- Create a sanitized database clone for development and automated testing.
- Do not copy secrets from the current PHP configuration into Python source files.
- Do not delete or rewrite production data without a separately reviewed data-migration plan.
- Preserve existing database tables and columns during the compatibility phase.
- Use parameterized queries or SQLAlchemy expressions. Do not recreate raw string-built SQL.
- Implement authorization at the service/use-case boundary, not only in route handlers.
- Treat PHP comments, backup controllers, and SQL dump data as evidence requiring verification, not automatically correct specifications.
- Record each intentional compatibility difference in a migration decision log.

## Pre-Goal Gate: Toolchain and Virtual Environment

No Python migration code may be written or executed until this gate passes. System tools may be installed at workstation level, but every Python package, command, test runner, formatter, migration tool, and application process must run from the project virtual environment.

### Current Workstation Snapshot

The following was checked from the workspace when this plan was updated:

| Tool | Current state | Required action before implementation |
| --- | --- | --- |
| Python | Python 3.13.7 is available through `python` and `py` | Confirm all selected dependencies support it; use Python 3.12 instead only if compatibility evidence requires it |
| Git | Git 2.51.0 is installed | The current workspace has no `.git` directory; do not initialize or connect a remote without project-owner approval |
| `uv` | Not installed | Optional; the baseline workflow below uses standard `venv` and `pip-tools` |
| Docker | Not installed | Install if disposable MySQL and Redis containers will be used for integration tests |
| MySQL client and `mysqldump` | Not installed or not on `PATH` | Install before schema introspection, backup, restore, or reconciliation work unless Docker provides the tools |
| `pytest` | A global executable is discoverable | Ignore the global installation and install/run `pytest` inside `.venv` |
| `ruff` | Not installed globally | Install inside `.venv` as a development dependency |

This snapshot proves only local command availability. It does not prove access to the office database, deployment host, Paystack, SMTP, push service, or production secrets.

### Required System Tools

- Python 3.13 or Python 3.12, selected and pinned for the project
- Git, once repository ownership and remote policy are confirmed
- MySQL-compatible command-line tools for read-only introspection, backup, restore, and reconciliation
- Docker Desktop or an approved equivalent for disposable MySQL and Redis test services
- PowerShell for the documented Windows commands
- An approved secrets provider for staging and production
- An API client such as Bruno, Postman, or generated `curl` commands for manual contract verification
- A reverse proxy or gateway capable of route-level PHP/FastAPI cutover

### Required Python Runtime Libraries

| Area | Required libraries | Purpose |
| --- | --- | --- |
| API runtime | `fastapi`, `uvicorn[standard]` | ASGI API framework and production server entry point |
| Configuration | `pydantic-settings` | Typed environment configuration and secret references |
| Database | `sqlalchemy`, `PyMySQL`, `alembic` | Existing MySQL access, transactions, models, and controlled future migrations |
| Request handling | `python-multipart`, `email-validator` | Upload/form parsing and validated email fields |
| Authentication | `PyJWT[crypto]`, `cryptography`, `bcrypt`, `argon2-cffi` | JWT handling, current bcrypt compatibility, and modern password hashing |
| HTTP integrations | `httpx`, `tenacity` | Paystack/provider requests with explicit timeout and retry policies |
| Templates and sanitization | `Jinja2`, `bleach` | Escaped email templates and bounded rich-text sanitization |
| Files and images | `Pillow`, `filetype`, `qrcode` | Image decoding, content-signature checks, and QR generation |
| Spreadsheet import/export | `openpyxl` | Replacement for current spreadsheet workflows |
| Web push | `pywebpush` | VAPID-based browser push notifications |
| Background work | `redis`, `arq` | Durable email, push, reconciliation, and scheduled jobs |
| Rate limiting | `limits` | Redis-backed endpoint throttling |
| Logging and metrics | `structlog`, `prometheus-client` | Structured redacted logs and operational metrics |

Use only one MySQL driver in the final runtime. `PyMySQL` is the baseline because it avoids native compilation on Windows. An asynchronous driver such as `asyncmy` may replace it only after the database-access architecture decision is recorded and tested.

### Required Python Development and Test Libraries

| Area | Required libraries | Purpose |
| --- | --- | --- |
| Testing | `pytest`, `pytest-cov`, `anyio`, `pytest-asyncio` | Unit, integration, coverage, and asynchronous tests |
| API/provider testing | `respx`, `schemathesis` | Mocked HTTP integrations and OpenAPI contract testing |
| Fixtures | `factory-boy`, `Faker`, `freezegun`, `hypothesis` | Relationship-complete data, time control, and property testing |
| Database integration | `testcontainers[mysql]` | Disposable MySQL integration tests when Docker is available |
| Quality | `ruff`, `mypy`, `pre-commit` | Formatting, linting, typing, and local quality gates |
| Security | `bandit`, `pip-audit` | Static security and dependency vulnerability checks |
| Dependency locking | `pip-tools` | Reproducible locked requirements derived from declared dependencies |
| Type support | `types-PyMySQL`, `types-redis` | Type information for selected integrations |

Conditional libraries must be documented before adoption. For example, `sentry-sdk[fastapi]` is required only if the office selects Sentry, and cloud-storage SDKs are required only if uploads move to that provider.

### Virtual Environment Bootstrap

Run these commands from the future `python-backend/` directory. Create `pyproject.toml` and the declared dependency groups before the editable-install command.

```powershell
py -3.13 --version
py -3.13 -m venv .venv
.\.venv\Scripts\Activate.ps1
$env:PIP_REQUIRE_VIRTUALENV = "true"
python -c "import sys; assert sys.prefix != sys.base_prefix, 'Virtual environment is not active'"
python -m pip install --upgrade pip pip-tools
python -m pip install -e ".[dev]"
python -m pip check
python -c "import fastapi, sqlalchemy, alembic, pydantic, pymysql; print('Core imports verified')"
python -m pytest --version
python -m ruff --version
python -m mypy --version
where.exe python
```

The first `where.exe python` result must be `python-backend\.venv\Scripts\python.exe`. Add `.venv/` to `.gitignore`. After activation, use `python -m pip`, `python -m pytest`, and `python -m` entry points so global executables cannot be selected accidentally.

### Bootstrap Acceptance Gate

- [x] The chosen Python version is recorded in `pyproject.toml`, `.python-version`, and the runtime/deployment documentation.
- [x] `python-backend/.venv/` exists and is excluded from source control.
- [x] The virtual-environment assertion passes.
- [x] Core imports succeed from `.venv`.
- [x] `python -m pip check` reports no broken dependency requirements.
- [x] Test, lint, type-check, and security tools execute from `.venv`.
- [x] Runtime and development dependency lock files are generated and reviewed.
- [x] A schema-only MariaDB test target is reachable on localhost without production credentials; all 68 application tables contain zero rows.
- [x] Alembic has no URL fallback and requires an explicit additional gate for production migrations.

## Goal 0: Secure and Stabilize the Current Baseline

### Objective

Prevent known credential and data exposure from undermining either the PHP system or the new FastAPI service.

### Tasks

- [ ] Inventory every secret currently stored in PHP configuration, SQL exports, logs, deployment files, and frontend configuration.
- [ ] Rotate the database password, SMTP credential, JWT signing secret, encryption key, VAPID private key, API key, Paystack keys if exposed, and all other discovered credentials.
- [ ] Move runtime secrets into an approved environment-variable or secret-management system.
- [ ] Remove real secrets from tracked files without copying their values into this document or commit messages.
- [ ] Determine whether repository history exists elsewhere and arrange history cleanup if secrets were previously committed.
- [ ] Quarantine the raw SQL dump and committed logs from normal deployment packages.
- [ ] Produce a sanitized development database containing representative relationships but no real personal data, active tokens, sessions, or credentials.
- [ ] Disable or protect the unauthenticated API-key rotation endpoint in the current PHP deployment while migration work proceeds.
- [ ] Disable or protect the unauthenticated `RateAgentApi` email endpoints.
- [ ] Restrict the current unrestricted upload surface until its replacement is ready.
- [ ] Freeze non-essential PHP feature development or require all new work to be recorded in the migration inventory.

### Required Outputs

- Secret inventory with owners and rotation status
- Sanitized development database
- Current-system containment report
- Approved secret-loading convention for FastAPI

### Acceptance Gate

No active production credential, plaintext password, active session, refresh token, or real member record is present in the working repository or migration test fixtures.

## Goal 1: Inventory the Existing System and Freeze API Contracts

### Objective

Build an evidence-based specification of what the frontend and integrations actually use.

### Tasks

- [x] Inventory all callable public methods in every controller under `application/controllers/` (445 non-constructor methods verified on 2026-09-08; includes implicit-public PHP methods and internal-only candidates).
- [ ] Classify each endpoint as retain, redesign, merge, deprecate, internal-only, or remove.
- [ ] Treat `Api.php` as a candidate primary source, then compare it with `Api_with_jwt.php`, `Api_backup.php`, and `oldApi.php` before selecting behaviour.
- [ ] Review `Setup.php`, `Setup22.php`, `RateAgentApi.php`, test scripts, and legacy controllers to ensure they are not mistakenly migrated as production features.
- [x] Classify the standalone root `Backend/alumniappV2 - PHP/Api.php` and its 103 non-constructor functions (retire as a non-controller duplicate: it is outside `application/controllers`, exits when `BASEPATH` is undefined, and no package reference or alternate front-controller mapping was found).
- [ ] Map each retained endpoint to its frontend caller, HTTP method, path, authentication requirement, request fields, response schema, and status codes.
- [ ] Capture pagination, sorting, search, filtering, date formatting, null handling, boolean coercion, and error-message behaviour.
- [ ] Inventory scheduled tasks, webhooks, background work, email flows, push notifications, social login, and external APIs.
- [ ] Inventory uploaded-file directories and determine which URLs are stored in the database versus generated dynamically.
- [ ] Document current business states and transitions for users, approvals, vouchers, chats, events, orders, payments, and deliveries.
- [ ] Identify all tables used by each retained endpoint.
- [ ] Capture representative sanitized request/response fixtures from the PHP implementation.
- [ ] Create a migration decision log for unclear or conflicting behaviour.

### Required Outputs

- Endpoint inventory
- Frontend-to-endpoint dependency map
- External integration inventory
- Business-state transition catalogue
- Compatibility fixture set
- Retain/deprecate decision register

### Acceptance Gate

Every production frontend request and external callback has a named owner, an identified PHP implementation, known database dependencies, and a migration disposition.

## Goal 2: Establish the FastAPI Foundation

### Objective

Create a maintainable Python service with secure defaults and clear module boundaries.

### Tasks

- [x] Select and document Python 3.13.7 as the supported version.
- [x] Create the FastAPI project in the separate `python-backend/` directory without modifying the PHP runtime directory.
- [x] Define modules for API routes, schemas, database models, repositories, services, authorization, integrations, and background tasks.
- [x] Add typed environment configuration using Pydantic Settings.
- [x] Add SQLAlchemy 2.x with PyMySQL.
- [x] Select synchronous database access for the compatibility phase and record the decision in ADR 0001.
- [ ] Configure connection pooling, connection health checks, transaction boundaries, timeouts, and safe shutdown (pooling, health, rollback cleanup, timeouts, shutdown, and the authentication transaction boundaries are verified; remaining business-service boundaries are pending).
- [x] Add consistent JSON error handling and request validation.
- [ ] Add correlation IDs, structured logging, and secret/PII redaction (correlation IDs, JSON logging, and top-level secret-field redaction are implemented; nested payload and PII redaction remains pending).
- [x] Add `/health/live` and `/health/ready` with separate process and database behaviour.
- [x] Add dependency locking, formatting, linting, strict type checking, unit/integration testing, security scanning, and `scripts/verify.ps1`.
- [x] Add local environment instructions and a names-only `.env.example` without credentials.
- [ ] Add a production entry point using an approved ASGI server and worker configuration.

### Suggested Initial Layout

```text
python-backend/
  app/
    api/
    core/
    db/
    integrations/
    models/
    repositories/
    schemas/
    services/
    main.py
  migrations/
  scripts/
  tests/
  pyproject.toml
  alembic.ini
```

### Required Outputs

- Runnable FastAPI skeleton
- Dependency lock file
- Environment configuration template containing names only
- Automated quality-check commands
- Architecture decision records for database mode and deployment model

### Acceptance Gate

The service starts without production credentials, health checks distinguish readiness from liveness, and formatting, linting, type checking, and tests run in automation.

## Goal 3: Preserve and Model the Existing Database

### Objective

Use the existing database safely without an uncontrolled schema rewrite.

### Tasks

- [ ] Introspect the live schema read-only and compare it with `alumni_portal_v2.sql`.
- [ ] Record database engine/version, character sets, collations, SQL mode, timezone, storage engines, indexes, foreign keys, triggers, procedures, and scheduled events.
- [ ] Identify schema drift between the SQL export and the actual environment.
- [ ] Catalogue orphan rows, duplicate records, invalid enums/statuses, inconsistent booleans, zero dates, nullable-field surprises, and broken relationships.
- [x] Generate SQLAlchemy mappings for all 68 exported tables and verify 778 columns against the sanitized clone, including names, types/lengths, defaults, computed expressions, comments, keys, indexes, and nullability.
- [x] Map exported `DECIMAL` columns to Python `Decimal`, never binary floating point.
- [ ] Define explicit timezone behaviour for all datetime reads and writes.
- [ ] Map legacy integer/string booleans without silently changing stored values.
- [ ] Add repository methods for database access and prohibit SQL execution from route modules.
- [ ] Parameterize every unavoidable handwritten SQL statement.
- [x] Configure Alembic, stamp empty baseline revision `7250164972b6` on the sanitized clone, and verify `alembic check` reports no new upgrade operations.
- [ ] Test transaction rollback and deadlock/retry behaviour.
- [ ] Benchmark important member, chat, event, and order queries on production-like data volumes.
- [ ] Propose indexes separately and verify them before applying any production schema change.

### Required Outputs

- Verified schema report
- SQLAlchemy model set
- Schema-drift report
- Alembic baseline
- Data-quality issue register
- Query benchmark report

### Acceptance Gate

FastAPI can perform representative read and rollback-safe write tests against the sanitized clone, with field-by-field results matching the existing schema and no unintended schema mutation.

## Goal 4: Rebuild Authentication and Authorization

### Objective

Allow existing members to authenticate while replacing inconsistent and insecure access control.

### Tasks

- [x] Catalogue password formats used by existing accounts, including bcrypt `$2y$` records and any legacy hashes (the supplied export contains 82 `$2y$` cost-8 bcrypt rows and one unclassified 40-character hexadecimal row; the inventory contains no hash values).
- [x] Implement a compatibility password verifier only for confirmed existing formats (bcrypt is accepted; the unproven hexadecimal format fails closed and requires recovery).
- [x] Rehash legacy passwords into the approved modern format after successful login.
- [x] Map Ion Auth groups, `users.user_role`, role tables, coordinator flags, voucher ownership, and store-admin rules (ADR 0004 records which sources grant bounded permissions and which remain metadata).
- [x] Choose one canonical authorization model and document mappings from legacy fields (named permissions derived from freshly loaded database facts; JWT roles are never sufficient for privileged access).
- [x] Implement short-lived access tokens, securely generated refresh tokens, refresh-token hashing, rotation, revocation, and reuse detection for the migrated login/refresh/logout slice.
- [x] Decide whether current JWTs receive a short compatibility window or all users must sign in again after cutover (ADR 0003 requires sign-in again).
- [x] Remove any endpoint that returns or rotates application credentials for ordinary clients (`getAPIKey2` and reset-key disclosure now have tested GET/POST HTTP 410 tombstones; server credentials are never returned).
- [ ] Ensure registration always assigns a server-controlled default role and privileged flags.
- [x] Implement finite, one-time password-reset tokens with rate limiting and generic responses (30-minute hashed one-time tokens, generic account/delivery responses, refresh revocation, and shared production rate limiting are SQL-tested).
- [x] Implement login throttling with defensible limits and monitoring (10 attempts per 15-minute peer-and-identity key; production requires atomic shared Redis counters; exhausted/backend-failure events are structured and PII-free).
- [ ] Validate social-login tokens with their issuing provider and map identities without trusting frontend profile claims.
- [ ] Create reusable authorization policies for member, owner, group administrator, voucher, coordinator, content administrator, store administrator, and super administrator.
- [ ] Add negative authorization tests for every protected endpoint.

### Required Outputs

- Authentication compatibility matrix
- Role and permission matrix
- Token lifecycle specification
- Password migration strategy
- Authorization test suite

### Acceptance Gate

Existing test accounts using every confirmed password format can authenticate, privileged fields cannot be self-assigned, and cross-user/cross-role access tests fail closed.

## Goal 5: Migrate Member, Profile, Chapter, Approval, and Voucher Features

### Objective

Reimplement the core alumni membership lifecycle without leaking protected member data.

### Tasks

- [ ] Implement registration with strict validation and server-controlled role defaults.
- [x] Implement email verification with expiring, one-time codes (24-hour replacement/consumption and activation, exact-role administrator notification, pending-voucher notification, and committed-state behavior on provider failure are SQL-tested; live SMTP remains a separate gate, and unsafe legacy aliases are retired).
- [x] Implement login, logout, token refresh, password reset, and account recovery (all six routes are implemented and SQL-tested; live SMTP acceptance remains a Goal 9/provider gate).
- [ ] Implement profile read/update operations with explicit field allowlists (`get_user_profile` read is implemented and SQL-tested with a credential-free response; update remains pending).
- [ ] Preserve field-level visibility rules for member directory responses.
- [ ] Implement chapter and graduation-year membership rules.
- [ ] Implement member approval and deactivation with administrator authorization.
- [ ] Implement voucher discovery using only the minimum public fields required.
- [ ] Implement voucher pending and approve/deny workflows with ownership checks.
- [ ] Ensure a voucher can act only on records assigned to that voucher.
- [ ] Replace `users.*` responses with dedicated public, member, administrator, and self-profile schemas.
- [ ] Add pagination and bounded filters to all user/member listing endpoints.
- [ ] Add audit events for privileged profile, role, approval, and voucher changes.
- [ ] Verify email, phone, address, date-of-birth, employment, token, and password fields are excluded unless explicitly authorized.

### Required Outputs

- Member and profile API
- Approval and voucher API
- Field-exposure matrix
- Audit event definitions
- Contract and authorization tests

### Acceptance Gate

The frontend can complete the full registration-to-approval journey, while protected fields and other users' private profile data remain inaccessible.

## Goal 6: Migrate Chat and Messaging

### Objective

Preserve chat history and behaviour while enforcing membership and ownership consistently.

### Tasks

- [ ] Decide whether the legacy and V2 chat models are both active; select one canonical target model.
- [ ] Map legacy group IDs, V2 thread IDs, participants, membership states, read markers, delivery markers, replies, attachments, and soft deletions.
- [ ] Implement thread and message listing with participant checks before every query.
- [ ] Implement direct-message creation with deterministic duplicate prevention.
- [ ] Implement group creation and member management with explicit owner/admin authorization.
- [ ] Prevent ordinary members from adding users to arbitrary groups.
- [ ] Restrict graduation-year bulk synchronization to authorized administrators or an internal job.
- [ ] Validate that reply targets and attachments belong to the same thread and authorized sender.
- [ ] Enforce attachment ownership before linking staged attachments to messages.
- [ ] Implement safe read, delivered, pin, leave, and soft-delete operations.
- [ ] Preserve historical messages when a member leaves while preventing new access beyond the intended policy.
- [ ] Add pagination limits and indexes for high-volume message retrieval.
- [ ] Add concurrency and duplicate-send tests using client-generated IDs.
- [ ] Add cross-group and cross-thread authorization tests.

### Required Outputs

- Canonical chat data model decision
- Chat API
- Chat authorization policy
- Migration/reconciliation script if legacy and V2 data require consolidation
- Chat contract, concurrency, and security tests

### Acceptance Gate

No authenticated user can read, post to, alter, or attach files to a conversation without the required membership and role.

## Goal 7: Migrate Community and Content Features

### Objective

Move public and administrative content features while preserving publication and ownership rules.

### Tasks

- [ ] Inventory chapters, announcements, events, projects, vacancies, leadership, blog posts, comments, reactions, and other content entities actually used by the frontend.
- [ ] Implement public listing/detail schemas that expose only published and approved content.
- [ ] Implement authenticated member actions with ownership checks.
- [ ] Implement content-administrator create, update, publish, unpublish, and delete operations.
- [ ] Ensure draft and hidden content cannot be unlocked by possession of a shared application API key.
- [ ] Preserve existing slugs, identifiers, image URLs, timestamps, ordering, filtering, and pagination.
- [ ] Sanitize rich text according to an explicit allowed-content policy.
- [ ] Validate all outbound URLs and uploaded content references.
- [ ] Add audit events for publication, moderation, and deletion.
- [ ] Add contract tests for public versus administrative representations.

### Required Outputs

- Content-domain APIs
- Publication and moderation policy
- Public/admin response schemas
- Content migration compatibility tests

### Acceptance Gate

Published content remains available at compatible endpoints, while drafts, moderation actions, and private author data require the correct role.

## Goal 8: Migrate Store, Orders, and Paystack Payments

### Objective

Preserve financial and inventory correctness under retries, concurrent requests, and webhook delivery.

### Tasks

- [ ] Map products, variants, stock, carts, cart items, orders, order items, delivery methods, and payment states.
- [ ] Preserve existing order numbers and Paystack references.
- [ ] Implement product and inventory administration with store-admin authorization.
- [ ] Implement carts and checkout with ownership checks.
- [ ] Recalculate prices, shipping, discounts, and totals on the server from trusted database values.
- [ ] Store monetary values as `Decimal` and compare Paystack kobo amounts exactly.
- [ ] Implement checkout initialization without exposing the Paystack secret.
- [ ] Implement payment verification with authenticated order ownership checks.
- [ ] Implement webhook signature verification using the raw request body and constant-time comparison.
- [ ] Verify payment status, amount, currency, reference, and expected order before finalization.
- [ ] Make finalization transactional and idempotent.
- [ ] Lock or conditionally update order and stock rows to prevent double stock deduction.
- [ ] Ensure webhook retries return safe responses without duplicate state changes.
- [ ] Add a manual reconciliation process for paid transactions whose database finalization fails.
- [ ] Redact payment payloads and secrets from user responses and logs.
- [ ] Test duplicate callbacks, amount mismatches, failed payments, abandoned checkouts, stock races, and rollback.

### Required Outputs

- Store and order APIs
- Paystack integration
- Payment state machine
- Reconciliation procedure
- Financial integration and concurrency tests

### Acceptance Gate

A payment can finalize an order exactly once, only for the matching amount/reference/order, without exposing another user's order or deducting stock twice.

## Goal 9: Migrate Uploads, Email, Push, and External Integrations

### Objective

Replace unsafe file and messaging surfaces with bounded, authenticated integration services.

### Tasks

- [ ] Catalogue every accepted file category, maximum size, actual MIME type, required ownership, retention period, and consumer.
- [ ] Store private documents outside the public web root or behind an authenticated download route.
- [ ] Generate server-side filenames and ignore user-provided paths.
- [ ] Validate extension, MIME type, file signature, size, and image decoding where applicable.
- [ ] Prevent executable files, path traversal, polyglots, and unauthorized attachment linking.
- [ ] Decide whether malware scanning is required before files become available.
- [ ] Preserve existing file URLs through a compatibility route or controlled object-storage migration.
- [ ] Centralize email templates and escape all user-controlled substitutions.
- [ ] Permit only server-selected recipients and approved email workflows.
- [ ] Add rate limits and abuse monitoring to verification, password-reset, contact, and notification emails.
- [ ] Rebuild web-push subscriptions and sending without exposing the VAPID private key.
- [ ] Validate and restrict any server-side URL fetching to prevent server-side request forgery.
- [ ] Add retry, timeout, circuit-breaker, and failure-recording behaviour for external services.
- [ ] Remove Rate Agent-specific endpoints and templates unless their ownership and business requirement are explicitly confirmed.

### Required Outputs

- Upload policy and implementation
- Authenticated file-serving design
- Email and push services
- External integration timeout/retry policy
- Abuse and file-security tests

### Acceptance Gate

Users can access only their authorized files, uploaded content cannot execute on the server, and clients cannot use the backend as an arbitrary email or URL-fetching relay.

## Goal 10: Add Security, Privacy, and Operational Controls

### Objective

Make secure operation measurable and enforceable.

### Tasks

- [ ] Define trusted hosts and fixed environment-specific public base URLs.
- [ ] Configure a minimal CORS allowlist without `null` origins or unconditional credential support.
- [ ] Use secure, HttpOnly, SameSite cookies if cookies remain part of authentication.
- [ ] Add CSRF protection to any cookie-authenticated state-changing flow.
- [ ] Add security headers appropriate to API and file responses.
- [ ] Apply endpoint-specific rate limits to authentication, registration, search, uploads, emails, and expensive listings.
- [ ] Redact passwords, tokens, authorization headers, reset codes, personal data, and payment payloads from logs.
- [ ] Add immutable audit records for privileged actions without storing secrets.
- [ ] Define data retention and deletion procedures for logs, sessions, tokens, uploads, and member records.
- [ ] Add centralized exception reporting that does not disclose stack traces or SQL details to clients.
- [ ] Add metrics for latency, errors, database saturation, authentication failures, email failures, upload rejection, webhook processing, and background jobs.
- [ ] Add dependency and container scanning to continuous integration.
- [ ] Run an application security review and threat model before production cutover.

### Required Outputs

- Security configuration baseline
- Logging/redaction policy
- Rate-limit matrix
- Audit and retention policy
- Threat model and pre-production security review

### Acceptance Gate

Automated tests confirm that secrets and protected personal data do not appear in source, API responses, logs, or error messages, and all privileged operations produce audit evidence.

## Goal 11: Build Compatibility and Regression Testing

### Objective

Prove that the replacement preserves required behaviour while intentionally closing security gaps.

### Tasks

- [ ] Build unit tests for domain rules and authorization policies.
- [ ] Build API contract tests from the sanitized PHP request/response fixtures.
- [ ] Build database integration tests using disposable or isolated database instances.
- [ ] Seed relationship-complete fixtures for members, roles, chapters, vouchers, chats, events, uploads, products, carts, orders, and payments.
- [ ] Compare normalized PHP and FastAPI responses for retained endpoints.
- [ ] Document expected differences caused by security fixes or corrected defects.
- [ ] Test unauthorized, unauthenticated, malformed, oversized, duplicate, and concurrent requests.
- [ ] Test transaction rollback and failure recovery.
- [ ] Test database encoding, Unicode names, Nigerian phone formats, dates, timezones, nulls, and legacy records.
- [ ] Test existing frontend flows against FastAPI in a non-production environment.
- [ ] Run load tests for login, member directory, chat polling/history, event listings, uploads, and checkout.
- [ ] Add a release gate that blocks deployment when contract, security, migration, or financial tests fail.

### Required Outputs

- Unit, integration, contract, security, and load-test suites
- Compatibility difference register
- Frontend end-to-end results
- Release quality gate

### Acceptance Gate

All retained workflows pass against the sanitized database, all intentional differences are approved, and there are no unresolved critical or high-severity security failures.

## Goal 12: Deploy Incrementally and Cut Over Safely

### Objective

Move traffic to FastAPI without data loss, prolonged downtime, or an untested rollback.

### Tasks

- [ ] Define development, test, staging, and production environments with isolated credentials.
- [ ] Build repeatable deployment artifacts and database-independent startup checks.
- [ ] Configure TLS, trusted proxy headers, worker count, graceful shutdown, timeouts, and connection limits.
- [ ] Create a route-by-route migration map for reverse-proxy or gateway routing.
- [ ] Start with read-only, low-risk endpoints.
- [ ] Compare shadow-read results where privacy and infrastructure permit.
- [ ] Move bounded write workflows only after their rollback and idempotency tests pass.
- [ ] Avoid dual writes unless there is a reviewed reconciliation and idempotency design.
- [ ] Migrate authentication before routes that require the new authorization model.
- [ ] Migrate payment webhooks only during a controlled window with Paystack configuration verification.
- [ ] Back up the database and record restoration commands before each high-risk cutover.
- [ ] Define rollback triggers for authentication, elevated error rates, data mismatches, payment failures, and queue/integration failures.
- [ ] Monitor both services and reconcile writes throughout the agreed rollback window.
- [ ] Update frontend base URLs and environment configuration through reviewed deployment changes.
- [ ] Remove public access to migrated PHP routes.
- [ ] Retain the PHP service in a non-writing rollback state for the agreed period.
- [ ] Archive PHP code, sanitized migration evidence, and final endpoint mappings after sign-off.
- [ ] Decommission PHP credentials, jobs, webhooks, and writable deployment paths only after final reconciliation.

### Required Outputs

- Deployment configuration
- Route cutover matrix
- Backup and rollback runbook
- Monitoring dashboard and alerts
- Production reconciliation report
- PHP decommission checklist

### Acceptance Gate

FastAPI serves all retained production traffic within agreed reliability thresholds, database reconciliation is clean, rollback has been tested, and no PHP process can continue writing after decommissioning.

## Endpoint Completion and Functional Parity by Goal

### Master Endpoint Register

This is the complete callable non-constructor method register extracted from the 17 current files under `application/controllers/`. It includes explicit-public methods, implicit-public PHP methods, internal helper candidates, and legacy/duplicate candidates so the implementing agent can reconcile the entire controller surface. HTTP methods and final FastAPI paths must be confirmed against frontend calls in Goal 1. The separate root-level `Backend/alumniappV2 - PHP/Api.php` is outside the configured controller directory, is guarded against direct execution, and has no package reference or alternate front-controller mapping; its 103 functions are therefore retirement candidates rather than migration endpoints.

| Legacy endpoint | Source | Migration goal | Disposition |
| --- | --- | --- | --- |
| `api/login` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:46` | Goal 4 | Implemented and SQL-tested; frontend comparison pending |
| `api/get_user_profile` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:312` | Goal 5 | Implemented with an explicit credential-free self/administrator schema, current-database account-manager authorization, active-actor recheck, rate limiting, and SQL tests; frontend comparison pending |
| `api/refresh_token` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:438` | Goal 4 | Implemented with rotation/reuse detection; frontend comparison pending |
| `api/logout` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:538` | Goal 4 | Implemented and SQL-tested; frontend comparison pending |
| `api/forgot_password` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:576` | Goal 4 | Implemented with generic response and fake-mail provider tests; live SMTP acceptance pending |
| `api/reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:627` | Goal 4 | Implemented with finite one-time hashed token and SQL tests; frontend comparison pending |
| `api/check_reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:710` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone; unsafe reset-key disclosure is replaced by one-time reset consumption |
| `api/getAPIKey2` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:766` | Goal 0 / Goal 1 | Retired with a GET/POST HTTP 410 tombstone; application credentials are server-only |
| `api/trackUser` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:789` | Goal 0 / Goal 1 | Retired with a GET/POST HTTP 410 tombstone; unauthenticated tracking/debug behavior is not copied |
| `api/uploadfiles/{field}/{user_id}/{type}` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:875` | Goal 9 | Implement and compare |
| `api/get_chapters` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:954` | Goal 5 | Implement and compare |
| `api/register` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1043` | Goal 5 | Implement and compare |
| `api/create_market` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1286` | Goal 7 | Implement and compare |
| `api/manage_market` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1351` | Goal 7 | Implement and compare |
| `api/get_market` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1414` | Goal 7 | Implement and compare |
| `api/create_announcement` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1465` | Goal 7 | Implement and compare |
| `api/manage_announcement` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1557` | Goal 7 | Implement and compare |
| `api/get_announcements` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1648` | Goal 7 | Implement and compare |
| `api/contact_us` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1704` | Goal 9 | Implement and compare |
| `api/create_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1811` | Goal 7 | Implement and compare |
| `api/manage_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1918` | Goal 7 | Implement and compare |
| `api/get_vacancies` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2035` | Goal 7 | Implement and compare |
| `api/create_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2080` | Goal 5 | Implement and compare |
| `api/manage_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2141` | Goal 5 | Implement and compare |
| `api/get_roles` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2205` | Goal 5 | Implement and compare |
| `api/get_setup_parameters` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2250` | Goal 5 | Implement and compare |
| `api/create_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2304` | Goal 7 | Implement and compare |
| `api/manage_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2349` | Goal 7 | Implement and compare |
| `api/get_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2396` | Goal 7 | Implement and compare |
| `api/manage_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2434` | Goal 5 | Implement and compare |
| `api/get_users_by_action` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2539` | Goal 5 | Implement and compare |
| `api/update_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2688` | Goal 5 | Implement and compare |
| `api/sendUserOTP` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2792` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone; delivery is private behind `resend_verify_email` |
| `api/verify_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2861` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone pointing to `api/verify_email`; the legacy identity comparison is broken and the route can bypass the approval state machine |
| `api/verify_user_access_code` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2911` | Goal 4 | Implemented as a rate-limited self-only Bearer route using current database state and constant-time comparison; caller `user_id` and identity/role disclosure removed; frontend comparison pending |
| `api/resend_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2952` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone pointing to `api/resend_verify_email`; duplicate mail/storage behavior is consolidated into the finite verification flow |
| `api/create_notification` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2989` | Goal 9 | Implement and compare |
| `api/get_notifications` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3044` | Goal 9 | Implement and compare |
| `api/mark_notification_read` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3080` | Goal 9 | Implement and compare |
| `api/change_user_password1` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3151` | Goal 0 / Goal 1 | Retired with a GET/POST HTTP 410 tombstone; use the self-only Bearer `change_user_password` route |
| `api/change_user_password` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3207` | Goal 4 | Implemented as self-only Bearer route with SQL tests; frontend comparison pending |
| `api/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3313` | Goal 5 | Implement and compare |
| `api/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3394` | Goal 9 | Implement and compare |
| `api/test_qr` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3446` | Goal 0 / Goal 1 | Retired with a GET/POST HTTP 410 tombstone; fixed demo data is not a production API |
| `api/create_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3479` | Goal 7 | Implement and compare |
| `api/manage_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3594` | Goal 7 | Implement and compare |
| `api/get_events` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3694` | Goal 7 | Implement and compare |
| `api/register_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3818` | Goal 7 | Implement and compare |
| `api/manage_event_rsvp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3950` | Goal 7 | Implement and compare |
| `api/get_event_attendees` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4043` | Goal 7 | Implement and compare |
| `api/create_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4126` | Goal 7 | Implement and compare |
| `api/manage_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4230` | Goal 7 | Implement and compare |
| `api/get_event_registration_forms` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4406` | Goal 7 | Implement and compare |
| `api/register_event_with_forms` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4486` | Goal 7 | Implement and compare |
| `api/get_event_registration_submissions` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4631` | Goal 7 | Implement and compare |
| `api/get_event_registration_submission_detail` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4714` | Goal 7 | Implement and compare |
| `api/create_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4940` | Goal 7 | Implement and compare |
| `api/manage_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5098` | Goal 7 | Implement and compare |
| `api/get_listings` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5359` | Goal 7 | Implement and compare |
| `api/verify_email` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5515` | Goal 4 | Implemented with 24-hour one-time code consumption, exact-role administrator and pending-voucher notifications, and SQL tests proving provider failure cannot roll back committed verification; live SMTP and frontend comparison pending |
| `api/resend_verify_email` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5616` | Goal 4 | Implemented with replacement-code and delivery-failure cleanup tests; live SMTP acceptance pending |
| `api/approve_user` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5682` | Goal 5 | Implement and compare |
| `api/update_user_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5773` | Goal 5 | Implement and compare |
| `api/manage_user_roles` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5858` | Goal 5 | Implement and compare |
| `api/update_profile` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5992` | Goal 5 | Implement and compare |
| `api/update_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6181` | Goal 5 | Implement and compare |
| `api/get_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6304` | Goal 5 | Implement and compare |
| `api/get_alumni_stats` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6382` | Goal 5 | Implement and compare |
| `api/import_alumni` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6436` | Goal 5 | Implement and compare |
| `api/create_project` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6885` | Goal 7 | Implement and compare |
| `api/manage_project` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7029` | Goal 7 | Implement and compare |
| `api/get_projects` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7214` | Goal 7 | Implement and compare |
| `api/create_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7433` | Goal 7 | Implement and compare |
| `api/manage_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7588` | Goal 7 | Implement and compare |
| `api/get_leadership` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7799` | Goal 7 | Implement and compare |
| `api/get_vouchers` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7991` | Goal 5 | Implement and compare |
| `api/voucher_pending` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8023` | Goal 5 | Implement and compare |
| `api/vouch_action` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8071` | Goal 5 | Implement and compare |
| `api/get_zones` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8177` | Goal 5 | Implement and compare |
| `api/get_cities` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8232` | Goal 5 | Implement and compare |
| `api/get_users_by_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8259` | Goal 5 | Implement and compare |
| `api/get_my_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8366` | Goal 5 | Implement and compare |
| `api/manage_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8444` | Goal 5 | Implement and compare |
| `api/manage_city` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8531` | Goal 5 | Implement and compare |
| `api/upload_zones_cities` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8622` | Goal 5 | Implement and compare |
| `api/get_birthdays` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8779` | Goal 5 | Implement and compare |
| `blog_api/homepage` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:148` | Goal 7 | Implement and compare |
| `blog_api/update_homepage_text` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:161` | Goal 7 | Implement and compare |
| `blog_api/create_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:192` | Goal 7 | Implement and compare |
| `blog_api/update_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:227` | Goal 7 | Implement and compare |
| `blog_api/reorder_carousel` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:360` | Goal 7 | Implement and compare |
| `blog_api/delete_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:387` | Goal 7 | Implement and compare |
| `blog_api/faqs` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:419` | Goal 7 | Implement and compare |
| `blog_api/create_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:432` | Goal 7 | Implement and compare |
| `blog_api/update_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:462` | Goal 7 | Implement and compare |
| `blog_api/reorder_faqs` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:506` | Goal 7 | Implement and compare |
| `blog_api/delete_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:533` | Goal 7 | Implement and compare |
| `blog_api/blog_categories` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:562` | Goal 7 | Implement and compare |
| `blog_api/create_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:575` | Goal 7 | Implement and compare |
| `blog_api/update_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:605` | Goal 7 | Implement and compare |
| `blog_api/delete_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:657` | Goal 7 | Implement and compare |
| `blog_api/reorder_categories` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:689` | Goal 7 | Implement and compare |
| `blog_api/blog_posts` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:720` | Goal 7 | Implement and compare |
| `blog_api/blog_post_detail/{id_or_slug}` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:747` | Goal 7 | Implement and compare |
| `blog_api/create_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:772` | Goal 7 | Implement and compare |
| `blog_api/update_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:864` | Goal 7 | Implement and compare |
| `blog_api/delete_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:968` | Goal 7 | Implement and compare |
| `chat_api/get_threads` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:29` | Goal 6 | Implement and compare |
| `chat_api/get_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:50` | Goal 6 | Implement and compare |
| `chat_api/create_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:86` | Goal 6 | Implement and compare |
| `chat_api/send_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:129` | Goal 6 | Implement and compare |
| `chat_api/send_direct` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:200` | Goal 6 | Implement and compare |
| `chat_api/list_messages` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:275` | Goal 6 | Implement and compare |
| `chat_api/delete_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:319` | Goal 6 | Implement and compare |
| `chat_api/mark_read` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:358` | Goal 6 | Implement and compare |
| `chat_api/add_member` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:389` | Goal 6 | Implement and compare |
| `chat_api/leave_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:418` | Goal 6 | Implement and compare |
| `chat_api/pin_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:445` | Goal 6 | Implement and compare |
| `chat_api/upload_media` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:477` | Goal 6 | Implement and compare |
| `chat_api/get_vapid_key` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:531` | Goal 9 | Implement and compare |
| `chat_api/register_push_subscription` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:544` | Goal 9 | Implement and compare |
| `chat_api/v2_get_threads` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:600` | Goal 6 | Implement and compare |
| `chat_api/v2_get_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:621` | Goal 6 | Implement and compare |
| `chat_api/v2_create_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:654` | Goal 6 | Implement and compare |
| `chat_api/v2_send_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:697` | Goal 6 | Implement and compare |
| `chat_api/v2_send_direct` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:767` | Goal 6 | Implement and compare |
| `chat_api/v2_upload_attachment` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:841` | Goal 6 | Implement and compare |
| `chat_api/v2_delete_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:913` | Goal 6 | Implement and compare |
| `chat_api/v2_mark_read` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:942` | Goal 6 | Implement and compare |
| `chat_api/v2_mark_delivered` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:996` | Goal 6 | Implement and compare |
| `chat_api/v2_add_member` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1021` | Goal 6 | Implement and compare |
| `chat_api/v2_leave_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1047` | Goal 6 | Implement and compare |
| `chat_api/v2_pin_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1071` | Goal 6 | Implement and compare |
| `chat_api/v2_sync_year_groups` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1099` | Goal 6 | Implement and compare |
| `product/pin_product_item` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:22` | Goal 8 | Implement and compare |
| `product/fetch_products` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:133` | Goal 8 | Implement and compare |
| `product/add_product` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:227` | Goal 8 | Implement and compare |
| `product/edit_product` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:483` | Goal 8 | Implement and compare |
| `product/delete_product` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:904` | Goal 8 | Implement and compare |
| `product/fetch_cart` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1063` | Goal 8 | Implement and compare |
| `product/add_to_cart` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1080` | Goal 8 | Implement and compare |
| `product/update_cart` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1224` | Goal 8 | Implement and compare |
| `product/remove_from_cart` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1305` | Goal 8 | Implement and compare |
| `product/clear_cart` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1357` | Goal 8 | Implement and compare |
| `product/add_address` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1423` | Goal 8 | Implement and compare |
| `product/fetch_addresses` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1502` | Goal 8 | Implement and compare |
| `product/edit_address` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1529` | Goal 8 | Implement and compare |
| `product/delete_address` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1595` | Goal 8 | Implement and compare |
| `product/set_default_address` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1628` | Goal 8 | Implement and compare |
| `product/fetch_delivery_zones` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1673` | Goal 8 | Implement and compare |
| `product/initiate_checkout` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:1722` | Goal 8 | Implement and compare |
| `product/verify_payment` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2013` | Goal 8 | Implement and compare |
| `product/paystack_webhook` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2102` | Goal 8 | Implement and compare |
| `product/fetch_orders` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2159` | Goal 8 | Implement and compare |
| `product/view_order_details` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2235` | Goal 8 | Implement and compare |
| `product/order_management` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2369` | Goal 8 | Implement and compare |
| `product/manage_order_details` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2460` | Goal 8 | Implement and compare |
| `product/update_order_status` | `Backend/alumniappV2 - PHP/application/controllers/Product.php:2593` | Goal 8 | Implement and compare |
| `socials/social_login` | `Backend/alumniappV2 - PHP/application/controllers/Socials.php:35` | Goal 4 | Implement and compare |
| `socials/social_signup` | `Backend/alumniappV2 - PHP/application/controllers/Socials.php:247` | Goal 4 | Implement and compare |
| `socials/link` | `Backend/alumniappV2 - PHP/application/controllers/Socials.php:380` | Goal 4 | Implement and compare |
| `socials/unlink` | `Backend/alumniappV2 - PHP/application/controllers/Socials.php:449` | Goal 4 | Implement and compare |
| `news/feeds` | `Backend/alumniappV2 - PHP/application/controllers/News.php:125` | Goal 7 | Implement and compare |
| `pwa/offline` | `Backend/alumniappV2 - PHP/application/controllers/Pwa.php:6` | Goal 9 | Conditional; retain only if frontend uses it |
| `rateagentapi/index` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:13` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `rateagentapi/send_contact_email` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:18` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `rateagentapi/send_meeting_email` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:104` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `rateagentapi/send_password_reset_email` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:206` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `rateagentapi/send_verification_email` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:265` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `rateagentapi/send_general_contact_email` | `Backend/alumniappV2 - PHP/application/controllers/RateAgentApi.php:324` | Goal 0 / Goal 1 | Contain now; retire unless separately approved |
| `auth/reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:250` | Goal 1 | Inventory only; retire unless active usage is proven |
| `auth/index` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:18` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/login` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:48` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/logout` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:98` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/change_password` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:111` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/forgot_password` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:179` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/activate` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:341` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/deactivate` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:367` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/create_user` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:413` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/edit_user` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:523` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/create_group` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:674` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/edit_group` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:721` | Goal 1 | Implicit-public method; inventory only and confirm routing |
| `auth/_get_csrf_nonce` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:785` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `auth/_valid_csrf_nonce` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:796` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `auth/_render_page` | `Backend/alumniappV2 - PHP/application/controllers/Auth.php:809` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `home/alert` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:15` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/index` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:19` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/previouslink` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:23` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/viewLoginRequest` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:39` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/login` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:66` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/forgot_password` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:104` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/logout` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:135` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/trust` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:142` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:149` | Goal 1 | Inventory only; retire unless active usage is proven |
| `home/_get_csrf_nonce` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:240` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `home/_valid_csrf_nonce` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:251` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `home/_render_page` | `Backend/alumniappV2 - PHP/application/controllers/Home.php:264` | Goal 1 | Implicit-public helper candidate; classify internal-only and verify it is not routable |
| `setup/index` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:20` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/dashboard` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:24` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_parameters` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:31` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_items_table` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:48` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_free_good_item` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:70` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_dept` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:92` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_users` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:114` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_workflow` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:215` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/add_workflow` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:250` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_request_approver_details` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:299` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_workflow_details` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:387` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/import` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:450` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_pos_batch` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:965` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/add_pos_item` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:985` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_pos_details` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1072` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/importBatch` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1100` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/importSingleFile` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1198` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/ajax_action` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1622` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/ajax_reset` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1662` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/ajax_list` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1672` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/ajax_edit` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1736` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/ajax_delete` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1742` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_sales_product_price` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1779` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_product_detail` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1784` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_page` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1811` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/add_customer` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1823` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_customer` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1876` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/view_customer_details` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:1890` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/run_detail_log` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:2128` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/add_pos_image` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:2248` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/do_upload_images` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:2253` | Goal 1 | Implicit-public upload method; inventory only and confirm routing |
| `setup/ajax_reset_approvers` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:2390` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup/getdept` | `Backend/alumniappV2 - PHP/application/controllers/Setup.php:2406` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/index` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:16` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_parameters` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:21` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_locations` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:39` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_products` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:65` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_customers` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:102` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_distributor_rewards` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:158` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_rewards` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:201` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_key_acct_rewards` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:274` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_wam_rewards` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:314` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_users` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:376` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_rules` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:437` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/add_rule` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:451` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_workflow` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:522` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/setup_credit` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:538` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/add_workflow` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:561` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_workflow_details` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:611` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/import` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:672` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/ajax_action` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1405` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/ajax_list` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1451` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/ajax_edit` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1533` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/ajax_delete` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1539` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/view_page` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1551` | Goal 1 | Inventory only; retire unless active usage is proven |
| `setup22/ajax_reset` | `Backend/alumniappV2 - PHP/application/controllers/Setup22.php:1562` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/login` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:165` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/refresh_token` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:336` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/logout` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:458` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/get_visitors` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:520` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/get_users` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:621` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_with_jwt/trackUser` | `Backend/alumniappV2 - PHP/application/controllers/Api_with_jwt.php:814` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/login` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:88` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_user_profile` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:313` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/refresh_token` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:501` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/logout` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:601` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/forgot_password` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:665` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:733` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/check_reset_password` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:816` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/getAPIKey` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1019` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/getAPIKey2` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1027` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/trackUser` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1050` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/uploadfiles/{field}/{user_id}/{type}` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1162` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_chapters` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1379` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/vehicle_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1805` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/estate_dues_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1906` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/bill_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:1998` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/expense_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2081` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/incident_report_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2257` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/register_visitor` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2388` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/service_request_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2487` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/service_request_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2577` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_emergency_contact` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2664` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_vehicle_register` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2717` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_estate_dues` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2853` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_bills` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:2978` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_expenses` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3070` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_incident_reports` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3165` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_visitors` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3260` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_service_requests` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3408` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/vehicle_register_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3517` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/estate_due_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3609` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/bills_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3786` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/expenses_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3883` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_incident_report` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:3973` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_visitors` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4128` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_emergency_contact` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4315` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_emergency_contacts` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4385` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_helpcentre_faq` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4443` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_helpcentre_faq` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4494` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_helpcentre_faq` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4564` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_staff` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4689` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_staff` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4831` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_staff` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:4886` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_shift` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5000` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_shifts` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5075` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_shift` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5147` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_market` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5235` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_market` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5300` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_market` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5362` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_announcement` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5413` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_announcement` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5488` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_announcements` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5584` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_role` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5632` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_role` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5692` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_roles` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5755` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5799` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5843` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5889` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:5927` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_users_by_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6145` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/update_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6370` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/sendUserOTP` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6560` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/verify_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6628` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/verify_user_access_code` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6680` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/resend_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6722` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_notification` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6760` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_notifications` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6848` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/mark_notification_read` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:6885` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/change_user_password1` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7000` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/change_user_password` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7056` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7169` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7248` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/generateQRCodeBase64` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7346` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/test_qr` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7363` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_event` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7460` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_event` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7571` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_events` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7670` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/register_event` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7795` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_event_rsvp` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7934` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_event_attendees` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8028` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8106` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8280` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_listings` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8520` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/verify_email` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8634` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/resend_verify_email` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8735` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/approve_user` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:8981` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/update_user_role` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:9080` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/update_profile` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:9295` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/update_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:9478` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_alumni_stats` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:9730` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/import_alumni` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:9784` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_project` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:10231` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_project` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:10374` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_projects` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:10566` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/create_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:10788` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/manage_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:10950` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_leadership` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:11168` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/get_vouchers` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:11362` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/voucher_pending` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:11394` | Goal 1 | Inventory only; retire unless active usage is proven |
| `api_backup/vouch_action` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:11449` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/login` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:88` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_user_profile` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:354` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/refresh_token` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:565` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/logout` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:665` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/forgot_password` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:729` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/reset_password` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:797` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/check_reset_password` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:880` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/getAPIKey` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1085` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/getAPIKey2` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1093` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/trackUser` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1116` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/uploadfiles/{field}/{user_id}/{type}` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1228` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_chapters` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1445` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_market` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1784` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_market` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1849` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_market` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1911` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_announcement` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:1962` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_announcement` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2060` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_announcements` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2156` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/contact_us` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2212` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2319` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2430` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_vacancies` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2551` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_role` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2596` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_role` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2656` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_roles` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2719` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_setup_parameters` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2763` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2816` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2860` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2906` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_user_account` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:2944` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_users_by_action` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3162` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/update_user_account` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3411` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/sendUserOTP` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3605` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/verify_otp` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3673` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/verify_user_access_code` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3725` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/resend_otp` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3767` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_notification` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3805` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_notifications` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3893` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/mark_notification_read` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:3930` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/change_user_password1` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4045` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/change_user_password` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4101` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4214` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4293` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/generateQRCodeBase64` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4391` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/test_qr` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4408` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_event` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4506` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_event` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4621` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_events` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4722` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/register_event` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4847` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_event_rsvp` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4986` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_event_attendees` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5080` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5164` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5275` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_event_registration_forms` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5459` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/register_event_with_forms` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5539` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_event_registration_submissions` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5691` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_event_registration_submission_detail` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5782` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_listing` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:5892` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_listing` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6068` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_listings` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6308` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/verify_email` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6422` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/resend_verify_email` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6523` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/approve_user` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6771` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/update_user_role` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6870` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_user_roles` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:6963` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/update_profile` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:7224` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/update_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:7420` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_alumni_stats` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:7682` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/import_alumni` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:7736` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_project` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:8185` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_project` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:8336` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_projects` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:8528` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/create_leader` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:8750` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_leader` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:8912` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_leadership` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9130` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_vouchers` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9324` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/voucher_pending` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9356` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/vouch_action` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9411` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_zones` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9630` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_cities` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9685` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_users_by_zone` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9712` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/get_my_zone` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9824` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_zone` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:9908` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/manage_city` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:10001` | Goal 1 | Inventory only; retire unless active usage is proven |
| `oldapi/upload_zones_cities` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:10098` | Goal 1 | Inventory only; retire unless active usage is proven |
| `news2/feeds` | `Backend/alumniappV2 - PHP/application/controllers/news2.php:40` | Goal 1 | Inventory only; retire unless active usage is proven |
| `my404/index` | `Backend/alumniappV2 - PHP/application/controllers/my404.php:9` | Goal 1 | Inventory only; retire unless active usage is proven |

The register intentionally includes duplicate and server-rendered controller methods as Goal 1 decision items. They must not be silently implemented merely because they exist in the PHP directory.

### How to Use This Checklist

Endpoint names below use the current CodeIgniter `controller/method` convention because the PHP controllers are the available source evidence. Goal 1 must confirm the actual HTTP method, path, frontend caller, and whether each endpoint is active. FastAPI may use cleaner REST paths, but every renamed route must have an explicit legacy-to-target mapping.

An endpoint is complete only when:

- [ ] Its retain, replace, merge, or retire decision is recorded.
- [ ] Its FastAPI route and OpenAPI schema are documented.
- [ ] Request validation, response shape, status codes, and error cases are tested.
- [ ] Authentication, role, ownership, and field-exposure tests pass.
- [ ] Database reads, writes, transactions, and side effects are verified against the sanitized MySQL clone.
- [ ] Existing frontend functionality passes or an approved compatibility adapter exists.
- [ ] Any intentional difference from PHP is recorded and approved.

### Goal 0 Endpoint Checkpoint: Containment Only

No FastAPI business endpoint is delivered in Goal 0. The following current PHP surfaces must be disabled, restricted, or isolated while migration proceeds:

- `api/getAPIKey2` — retired with an explicit tombstone
- `api/trackUser` — retired with an explicit tombstone
- Direct external access to `api/uploadfiles/{field}/{user_id}/{type}`
- `rateagentapi/send_contact_email`
- `rateagentapi/send_meeting_email`
- `rateagentapi/send_password_reset_email`
- `rateagentapi/send_verification_email`
- `rateagentapi/send_general_contact_email`

Parity checkpoint: containment is confirmed through HTTP tests showing the routes are unavailable to unauthorized callers. These unsafe behaviours must not receive compatibility implementations.

### Goal 1 Endpoint Checkpoint: Inventory and Decisions

No production FastAPI endpoint is required in Goal 1. The endpoint inventory must cover every public method in these controllers:

- Primary API candidates: `Api`, `Blog_api`, `Chat_api`, `Product`, `Socials`, `News`, and `Pwa`
- Server-rendered or administrative candidates: `Auth`, `Home`, and `Setup`
- Legacy/conflicting candidates: `Api_with_jwt`, `Api_backup`, `oldApi`, `Setup22`, and `news2`
- Unrelated or unsafe candidate: `RateAgentApi`

The following endpoints require explicit retire/retain decisions before implementation because they appear diagnostic, duplicated, generic, or unrelated:

- `api/getAPIKey2`
- `api/trackUser`
- `api/test_qr`
- `api/change_user_password1`
- `api/sendUserOTP/{email}/{fullname}` as an externally callable method
- `rateagentapi/index`
- All `rateagentapi/*` email methods
- `news2/feeds`
- All routes exposed only by `Api_backup`, `oldApi`, `Api_with_jwt`, or `Setup22`
- Generic `setup/ajax_action/*`, `setup/ajax_reset/*`, `setup/ajax_list/*`, `setup/ajax_edit/*`, and `setup/ajax_delete/*` routes

Parity checkpoint: the final inventory accounts for every extracted public controller method, and no legacy route is silently omitted or copied.

### Goal 2 Endpoint Checkpoint: Platform Endpoints

New FastAPI operational endpoints:

- `GET /health/live`
- `GET /health/ready`
- `GET /openapi.json`, restricted according to the deployment policy
- Interactive API documentation routes, enabled only in approved environments

Parity checkpoint: liveness works without database access; readiness fails safely when required dependencies are unavailable.

### Goal 3 Endpoint Checkpoint: Database Compatibility

No public business endpoint is added. Create internal test probes or integration tests for representative database operations covering:

- Users, roles, profiles, chapters, zones, cities, vouchers, and approvals
- Chat threads, participants, messages, attachments, and read/delivery state
- Events, registrations, forms, listings, projects, leadership, announcements, vacancies, and blog content
- Products, variants, carts, addresses, delivery zones, orders, order items, and payment references
- Notifications, push subscriptions, refresh-token records, uploads, and audit records

Parity checkpoint: representative reads match PHP-normalized fixtures and rollback-safe writes preserve the current schema.

### Goal 4 Endpoint Checkpoint: Authentication and Authorization

Current endpoint capabilities to implement, merge, or replace:

- `api/login`
- `api/refresh_token`
- `api/logout`
- `api/forgot_password`
- `api/reset_password`
- `api/check_reset_password`
- `api/verify_otp` — retired with an explicit tombstone; use `api/verify_email`
- `api/resend_otp` — retired with an explicit tombstone; use `api/resend_verify_email`
- `api/verify_email`
- `api/resend_verify_email`
- `api/verify_user_access_code`
- `api/change_user_password`
- `socials/social_login`
- `socials/social_signup`
- `socials/link`
- `socials/unlink`

Do not reproduce `api/getAPIKey2`. Convert `api/sendUserOTP/{email}/{fullname}` into a private service operation called only by authorized workflows. Retire `api/change_user_password1` after confirming it has no unique caller.

Parity checkpoint: existing users can authenticate, refresh, log out, recover access, verify email, and change passwords; negative role and ownership tests fail closed.

### Goal 5 Endpoint Checkpoint: Members, Profiles, Chapters, Roles, and Vouchers

Registration and member profile:

- `api/register`
- `api/get_user_profile`
- `api/update_profile`
- `api/update_profile_visibility`
- `api/get_profile_visibility`
- `api/get_alumni_stats`
- `api/import_alumni`
- `api/get_birthdays`

Member administration and roles:

- `api/create_role`
- `api/manage_role`
- `api/get_roles`
- `api/manage_user_account`
- `api/get_users_by_action`
- `api/update_user_account`
- `api/deactivate_staff/{user_id}`
- `api/approve_user`
- `api/update_user_role`
- `api/manage_user_roles`
- `api/get_setup_parameters`

Chapters, zones, and cities:

- `api/get_chapters`
- `api/get_zones`
- `api/get_cities`
- `api/get_users_by_zone`
- `api/get_my_zone`
- `api/manage_zone`
- `api/manage_city`
- `api/upload_zones_cities`

Voucher workflow:

- `api/get_vouchers`
- `api/voucher_pending`
- `api/vouch_action`

Parity checkpoint: registration through approval works end to end; directory and administrative responses use explicit field allowlists; voucher and zone operations enforce role and record ownership.

### Goal 6 Endpoint Checkpoint: Chat and Messaging

Legacy chat endpoints, to retain temporarily or explicitly retire after V2 parity:

- `chat_api/get_threads`
- `chat_api/get_thread`
- `chat_api/create_group`
- `chat_api/send_message`
- `chat_api/send_direct`
- `chat_api/list_messages`
- `chat_api/delete_message`
- `chat_api/mark_read`
- `chat_api/add_member`
- `chat_api/leave_group`
- `chat_api/pin_thread`
- `chat_api/upload_media`

V2 chat endpoints:

- `chat_api/v2_get_threads`
- `chat_api/v2_get_thread`
- `chat_api/v2_create_group`
- `chat_api/v2_send_message`
- `chat_api/v2_send_direct`
- `chat_api/v2_upload_attachment`
- `chat_api/v2_delete_message`
- `chat_api/v2_mark_read`
- `chat_api/v2_mark_delivered`
- `chat_api/v2_add_member`
- `chat_api/v2_leave_group`
- `chat_api/v2_pin_thread`
- `chat_api/v2_sync_year_groups`

Push-specific chat endpoints are completed under Goal 9:

- `chat_api/get_vapid_key`
- `chat_api/register_push_subscription`

Parity checkpoint: the frontend can create/list/open conversations, send direct and group messages, upload attachments, manage read/delivery state, and leave/pin groups without cross-thread access. The legacy route retirement decision is verified before cutover.

### Goal 7 Endpoint Checkpoint: Community and Content

Marketplace and vacancies:

- `api/create_market`
- `api/manage_market`
- `api/get_market`
- `api/create_vacancy`
- `api/manage_vacancy`
- `api/get_vacancies`

Announcements and policy:

- `api/create_announcement`
- `api/manage_announcement`
- `api/get_announcements`
- `api/create_privacy_policy`
- `api/manage_privacy_policy`
- `api/get_privacy_policy`

Events and registrations:

- `api/create_event`
- `api/manage_event`
- `api/get_events`
- `api/register_event`
- `api/manage_event_rsvp`
- `api/get_event_attendees`
- `api/create_event_registration_form`
- `api/manage_event_registration_form`
- `api/get_event_registration_forms`
- `api/register_event_with_forms`
- `api/get_event_registration_submissions`
- `api/get_event_registration_submission_detail`

Listings, projects, and leadership:

- `api/create_listing`
- `api/manage_listing`
- `api/get_listings`
- `api/create_project`
- `api/manage_project`
- `api/get_projects`
- `api/create_leader`
- `api/manage_leader`
- `api/get_leadership`

Homepage, carousel, FAQ, categories, and blog:

- `blog_api/homepage`
- `blog_api/update_homepage_text`
- `blog_api/create_carousel_image`
- `blog_api/update_carousel_image`
- `blog_api/reorder_carousel`
- `blog_api/delete_carousel_image`
- `blog_api/faqs`
- `blog_api/create_faq`
- `blog_api/update_faq`
- `blog_api/reorder_faqs`
- `blog_api/delete_faq`
- `blog_api/blog_categories`
- `blog_api/create_blog_category`
- `blog_api/update_blog_category`
- `blog_api/delete_blog_category`
- `blog_api/reorder_categories`
- `blog_api/blog_posts`
- `blog_api/blog_post_detail/{id_or_slug}`
- `blog_api/create_blog_post`
- `blog_api/update_blog_post`
- `blog_api/delete_blog_post`

News:

- `news/feeds`

Parity checkpoint: all public/admin representations, publication states, registration forms, ordering, filters, and file references match approved contracts; draft and moderation operations require explicit roles.

### Goal 8 Endpoint Checkpoint: Store, Orders, and Payments

Products and inventory:

- `product/pin_product_item`
- `product/fetch_products`
- `product/add_product`
- `product/edit_product`
- `product/delete_product`

Cart:

- `product/fetch_cart`
- `product/add_to_cart`
- `product/update_cart`
- `product/remove_from_cart`
- `product/clear_cart`

Addresses and delivery:

- `product/add_address`
- `product/fetch_addresses`
- `product/edit_address`
- `product/delete_address`
- `product/set_default_address`
- `product/fetch_delivery_zones`

Checkout, payments, and orders:

- `product/initiate_checkout`
- `product/verify_payment`
- `product/paystack_webhook`
- `product/fetch_orders`
- `product/view_order_details`
- `product/order_management`
- `product/manage_order_details`
- `product/update_order_status`

Parity checkpoint: catalogue/cart/address behaviour matches the frontend, and payment tests prove ownership, exact amount/reference validation, idempotency, transactional stock updates, webhook verification, and reconciliation.

### Goal 9 Endpoint Checkpoint: Uploads, Contact, Notifications, and Push

Contact and notifications:

- `api/contact_us`
- `api/create_notification`
- `api/get_notifications`
- `api/mark_notification_read`
- `api/user_tokens`, replaced by a private or self-service push-token operation with an explicit field schema

Uploads and generated assets:

- Replace direct `api/uploadfiles/{field}/{user_id}/{type}` access with authenticated, domain-specific upload endpoints.
- Preserve required avatar, announcement, event, market, vacancy, listing, project, leadership, blog, chat, document, and product upload capabilities.
- Replace `api/test_qr` with an internal QR service if QR generation is confirmed as required.

Push:

- `chat_api/get_vapid_key`
- `chat_api/register_push_subscription`

PWA/server-rendered fallback:

- `pwa/offline`, only if the deployed frontend still requests a backend-rendered offline page

Explicit non-parity targets unless Goal 1 proves an approved business requirement:

- `rateagentapi/index`
- `rateagentapi/send_contact_email`
- `rateagentapi/send_meeting_email`
- `rateagentapi/send_password_reset_email`
- `rateagentapi/send_verification_email`
- `rateagentapi/send_general_contact_email`

Parity checkpoint: every retained workflow can upload or send notifications only to authorized targets; file, recipient, rate-limit, retry, and redaction tests pass.

### Goal 10 Endpoint Checkpoint: Security and Operations

Operational endpoints to complete or integrate:

- `GET /metrics`, restricted to the monitoring network or authenticated collector
- `GET /health/live`
- `GET /health/ready`

All previously completed endpoints must receive the Goal 10 security baseline: trusted-host validation, CORS policy, rate limiting, redaction, audit events, safe errors, retention controls, and security headers.

Parity checkpoint: automated security tests cover every retained route, and operational endpoints reveal no secrets, personal data, stack traces, or database details.

### Goal 11 Endpoint Checkpoint: Whole-System Regression

No new business endpoint is required. Run the full contract suite for all retained endpoints from Goals 4 through 10 and verify the following complete journeys:

- Registration, verification, approval, login, refresh, recovery, and profile management
- Member directory, chapters, zones, roles, vouchers, and imports
- Chat, attachments, read/delivery state, group membership, and push notifications
- Content publication, events, registration forms, listings, projects, leadership, vacancies, market, FAQs, and blog
- Products, carts, addresses, checkout, Paystack callbacks, orders, inventory, and administrator fulfilment
- Contact, email, uploads, QR generation if retained, and operational monitoring

Parity checkpoint: every retained legacy-to-FastAPI mapping has passing contract, authorization, database, and frontend evidence; all retired routes have a tested replacement or approved removal.

### Goal 12 Endpoint Checkpoint: Production Cutover

Move endpoints by the approved route-cutover matrix in this order:

1. Health and read-only public content endpoints
2. Authenticated read-only member endpoints
3. Authentication and member write endpoints
4. Content and event write endpoints
5. Chat and notification endpoints
6. Store, checkout, order, and Paystack endpoints
7. Administrative imports, bulk operations, and internal jobs

Parity checkpoint: production monitoring and reconciliation confirm every retained endpoint is served by FastAPI, deprecated PHP routes are inaccessible, and the rollback procedure has passed a controlled test.

## Recommended Goal Order

1. Goal 0: Secure and stabilize the baseline.
2. Goal 1: Inventory endpoints and business contracts.
3. Goals 2 and 3: Build the foundation and database compatibility layer.
4. Goal 4: Implement authentication and authorization.
5. Goal 5: Migrate the member lifecycle.
6. Goals 6, 7, and 9: Migrate chat, content, files, and messaging in independently testable slices.
7. Goal 8: Migrate store and payments after transaction infrastructure is proven.
8. Goals 10 and 11: Apply security controls continuously and complete regression evidence.
9. Goal 12: Perform incremental production cutover and decommission PHP.

## Agent Progress Record

The implementing agent should update this section after each working session.

| Goal | Status | Evidence produced | Remaining blocker | Last verified |
| --- | --- | --- | --- | --- |
| Goal 0 | In progress (audit only) | Credential-bearing configuration locations identified without copying values; SQL export confirmed to contain data-bearing identity/session/token tables | Rotation, containment, sanitized clone, ownership decisions, and deployment-side controls require authorized environment access | 2026-09-08 |
| Goal 1 | In progress | Deterministic JSON inventory covers 445 controller methods and 103 retired root-artifact functions with source hashes, routeability, inferred request mode, inputs, table/model calls, auth signals, statuses, value-free controller execution references, two consolidated-route decisions, and six explicit security retirements | Confirm remaining controller decisions against frontend/traffic evidence, map exact contracts and side effects, and capture sanitized fixtures | 2026-09-09 |
| Goal 2 | In progress | Isolated Python 3.13.7 service, pinned runtime/dev locks, typed settings, SQLAlchemy/PyMySQL engine, health routes, error envelope, correlation IDs, structured logging, ADRs, automated verification, successful Uvicorn smoke test, and committed authentication transaction boundaries | Nested PII redaction, remaining service transaction boundaries, approved production worker/proxy configuration, and all other business modules remain | 2026-09-08 |
| Goal 3 | In progress | Local MariaDB 12.3.3 schema-only clone; 68 table/778 column mappings; model/schema parity tests; rollback-safe write; empty Alembic baseline `7250164972b6`; schema report | Approved current-database read-only access, schema-drift/data-quality report, timezone/boolean decisions, repositories, deadlock tests, and benchmarks remain | 2026-09-08 |
| Goal 4 | In progress | Nine SQL-backed auth/recovery/verification routes; self-only access-code verification; canonical database-fact permission policy in ADR 0004; exact-role administrator and pending-voucher post-verification notifications; eight unsafe/superseded routes retired with tested GET/POST HTTP 410 tombstones; bcrypt-to-Argon2id migration; asymmetric access JWTs; rotating hashed refresh tokens; finite reset/email codes; shared Redis throttling; SMTP adapter/fakes; ADR 0003 | Social login, group/owner policy application across remaining endpoints, role-management hierarchy, live Redis/SMTP acceptance, trusted-proxy validation, and frontend compatibility remain | 2026-09-09 |
| Goal 5 | In progress | Authentication, finite email verification, post-verification administrator/voucher notification, and credential-free self/administrator profile read are implemented; profile selection uses current database permissions and account-state gates are enforced | Registration, approval, profile update/visibility, directory, chapters, zones, roles, vouchers, imports, and frontend journeys remain | 2026-09-09 |
| Goal 6 | Not started | None | None recorded | Not verified |
| Goal 7 | Not started | None | None recorded | Not verified |
| Goal 8 | Not started | None | None recorded | Not verified |
| Goal 9 | Not started | None | None recorded | Not verified |
| Goal 10 | In progress | Production docs/wildcard guards, explicit database configuration, secret settings types, top-level log redaction, shared Redis authentication throttling, PII-free limit events, eight executable security/supersession tombstones, Bandit pass, and dependency audit pass | Remaining host/CORS/cookie/CSRF, non-auth route limits, audit/retention/metrics/threat-model, live Redis, and trusted-proxy work | 2026-09-09 |
| Goal 11 | In progress | 118 foundation, inventory, schema, authentication, recovery, verification/notification, access-code, authorization-policy, member-profile, fake-SMTP, rate-limit, and retired-route tests pass at 98.54% coverage; reusable root-safe `scripts/verify.ps1` includes Alembic drift detection | Remaining endpoint contracts, protected-route policy tests, complete fixtures, live providers, frontend, load, and release tests remain | 2026-09-09 |
| Goal 12 | Not started | None | None recorded | Not verified |

### Progress Log

#### 2026-09-09 — Credential-free member profile read

- Implemented rate-limited `POST /api/get_user_profile` in dedicated member API, repository, service, and schema layers. The SQL query allowlists required fields and never loads password, reset, verification, access-code, device-token, or session credentials into the response projection.
- Preserved the legacy self/default target and profile, role-label, city, and zone response shape, including the previously omitted TikTok field. Avatar paths use the configured public base URL.
- Replaced authorization by JWT role claim with freshly loaded account facts: members are self-only, exact reviewed account-manager roles can select another user, and deleted/inactive actors fail closed. Tests prove a forged `superadmin` token claim cannot elevate a database member and an older member claim cannot suppress a current database manager permission.
- Verification: 118 tests pass at 98.54% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Post-verification administrator and voucher notifications

- Completed the retained `verify_email` side effects after comparing the PHP workflow: a newly verified member now notifies active users in the reviewed exact manager/administrator role set and the active voucher assigned by a pending `vouches` row.
- Notifications run only after the verification transaction commits and only on the first successful consumption. Provider failure is isolated per recipient, emits a PII-free structured event, does not restore the OTP, and cannot roll back the verified account state.
- Extended the bounded SMTP adapter and no-network fake with account/voucher message contracts. No live SMTP credential or external message was used, so provider acceptance remains unverified.
- Verification: 113 tests pass at 98.51% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Canonical authorization policy and member access-code verification

- Added ADR 0004 and a typed permission policy that normalizes only reviewed `users.user_role` aliases, rejects substring-based admin elevation, keeps arbitrary `roles.role_name` values as non-authoritative metadata, derives coordinator scope from `is_coordinator`, and derives voucher authority from row ownership.
- Separated manager, administrator, super-administrator, finance-administrator, and storekeeper-administrator capabilities. Privileged decisions must reload database facts; JWT role claims alone cannot authorize them.
- Implemented rate-limited `POST /api/verify_user_access_code` as a self-only Bearer workflow. Body-supplied `user_id` is ignored, the active account is rechecked, comparison is constant-time, and responses expose neither member identity nor role.
- Verification: 111 tests pass at 98.68% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Explicit security-route retirements

- Centralized GET/POST HTTP 410 tombstones for `check_reset_password`, `getAPIKey2`, `trackUser`, `sendUserOTP`, `change_user_password1`, and `test_qr`, alongside the two superseded OTP routes. Each is deprecated in OpenAPI with a unique operation ID and executes no legacy side effect.
- Confirmed replacement guidance for finite reset consumption, self-only password changes, and finite email verification; application/reset credentials, unauthenticated tracking, unbounded direct mail, unsafe password mutation, and fixed QR demo values are not copied.
- Regenerated the 548-record inventory. Current dispositions are 156 candidate-retain, 201 candidate-retire, 19 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 6 retired-security-risk, and 103 retire-noncontroller-artifact.
- Verification: 96 tests pass at 98.64% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities. A Bandit false positive on the route name was resolved without a broad suppression.

#### 2026-09-09 — Legacy OTP route consolidation

- Reviewed the primary PHP `verify_otp` and `resend_otp` implementations against the newer email-verification workflow. The older verification path casts an email to an integer during lookup, requires but does not validate its body token, and changes `profile_status` outside the newer approval flow.
- Explicitly retired both duplicate endpoints for their prior GET and POST access patterns using deprecated HTTP 410 tombstones that direct clients to `verify_email` and `resend_verify_email`; no unsafe activation logic was copied.
- Regenerated the 548-record endpoint inventory. Current dispositions are 157 candidate-retain, 203 candidate-retire, 22 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, and 103 retire-noncontroller-artifact.
- Verification: 79 tests pass at 98.64% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Shared authentication rate limiting

- Added fixed-window throttling to all eight migrated authentication/recovery/verification routes, keyed by route, peer address, and a SHA-256 digest of bounded identity or token material; Redis keys and structured events contain no raw identities or tokens.
- Development and tests may use a thread-safe local limiter. Production settings now fail validation unless shared Redis is selected and configured; Redis increments and expiries execute atomically, and backend failure fails closed with a sanitized HTTP 503 response.
- Documented exact route limits, structured exhaustion/backend events, and the outstanding trusted reverse-proxy boundary in README and ADR 0003. Live Redis availability and proxy forwarding remain deployment acceptance gates.
- Corrected `scripts/verify.ps1` to run from its own project root regardless of the caller's working directory.
- Verification: 77 tests pass at 98.63% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Email verification core

- Implemented `POST /api/resend_verify_email` and `POST /api/verify_email` with OpenAPI schemas and the existing `register_user_otp`/`users` tables.
- Replacement generation deactivates prior codes in the same transaction, mirrors the new six-digit code for legacy compatibility, and removes the new active code/mirror if delivery fails.
- Verification locks both user and OTP state, rejects invalid codes, consumes expired codes after 24 hours, consumes successful codes once, and atomically marks the email verified and account active.
- Confirmed boundary: legacy post-verification admin/voucher email side effects, the older `verify_otp`/`resend_otp` aliases, distributed attempt limits, frontend comparison, and live SMTP delivery remain pending.
- Verification: 69 tests pass at 98.73% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-09 — Password recovery and authenticated password change

- Implemented `POST /api/forgot_password`, `POST /api/reset_password`, and `POST /api/change_user_password` with published OpenAPI request/response schemas.
- Replaced the legacy non-expiring/reset-key-disclosure behavior with a 30-minute opaque reset token, SHA-256-only database storage, one-time row locking/consumption, Argon2id password replacement, and revocation of all refresh sessions.
- Added a generic recovery response for unknown accounts, successful delivery, and delivery failure; a failed SMTP delivery clears the just-issued database token. Response timing and distributed throttling remain open security work.
- Added reusable Bearer-token validation and made password changes self-only: body-supplied target user IDs are ignored, current password and active state are rechecked, and successful changes revoke refresh sessions.
- Added a bounded TLS SMTP adapter plus no-network provider tests. No real SMTP credential or external message was used, so live delivery acceptance remains unverified.
- Verification: 63 tests pass at 98.84% coverage against the sanitized MariaDB; Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-08 — Root artifact classification and runtime evidence

- Classified the 103 functions in root-level `Api.php` as retirement candidates: the configured front controller loads `application/`, standard routing resolves `application/controllers`, direct access to the root file is stopped by its `BASEPATH` guard, and no alternate mapping or include was found.
- Added value-free checked-in log evidence to the endpoint inventory. Controller source references observed are `Home.php` 1,637, `Api.php` 714, `Blog_api.php` 23, `Chat_api.php` 5, `Product.php` 5, `Setup.php` 2, and `Socials.php` 1. These prove controller execution, not individual endpoint traffic or successful requests.
- Updated the deterministic inventory disposition totals to 159 candidate-retain, 203 candidate-retire, 22 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, and 103 retire-noncontroller-artifact records.

#### 2026-09-08 — Authentication compatibility slice

- Implemented SQL-backed `POST /api/login`, `POST /api/refresh_token`, and `POST /api/logout` with JSON/form login compatibility and legacy response/status handling where safe.
- Added generic credential failures that do not enumerate accounts, bcrypt `$2y$` verification, transactional Argon2id rehashing, RS256/ES256 JWT support with required issuer/audience/type/lifetime claims, opaque refresh tokens, SHA-256 token storage, rotation, five-session cap, revocation, and replay containment.
- Added ADR 0003 with intentional security differences: no shared application-key authorization, 15-minute access tokens, forced sign-in at cutover, and mandatory client persistence of each rotated refresh token.
- Added a value-free password-format inventory: 83 exported user hashes comprise 82 bcrypt `$2y$` cost-8 hashes and one unclassified 40-character hexadecimal value; no hashes are emitted by the report.
- Verification: 51 tests pass at 99.04% coverage against the sanitized MariaDB; synthetic auth rows are removed after each test. Ruff, strict mypy, Alembic drift detection, `pip check`, Bandit, and `pip-audit` pass with no known vulnerabilities.

#### 2026-09-08 — Deterministic endpoint discovery inventory

- Added `scripts/inventory_php_endpoints.py` and generated `docs/endpoint-inventory.json` without copying PHP method bodies, credentials, or database records.
- Locked the previously manual counts with tests: 17 controller files, 445 callable non-constructor controller methods, 439 potentially routable controller candidates after CodeIgniter's leading-underscore guard, 103 root-artifact methods, and 548 total inventory records.
- Initial provisional static dispositions were 159 candidate-retain, 203 candidate-retire, 22 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, and 103 unresolved-root-artifact methods; the later root-artifact classification above resolves those 103 as retirement candidates.
- Each record includes source/line, declared visibility, CodeIgniter routeability, inferred request mode, discovered input/upload fields, direct table references, model calls, auth signals, numeric response statuses, and a method-body SHA-256 for drift detection.
- Evidence boundary: the dispositions and request modes remain provisional because the actual frontend source, captured production traffic, and approved contract fixtures are not present in this workspace.
- Verification: the expanded gate passed Ruff, mypy, 28 pytest tests at 99.67% coverage, `pip check`, Bandit, and `pip-audit` with no known vulnerabilities.

#### 2026-09-08 — FastAPI foundation and sanitized schema compatibility

- Created `python-backend/` with Python 3.13.7 pinned in `pyproject.toml` and `.python-version`; created an isolated `.venv` and verified it is first on `PATH` after activation.
- Resolved and documented an `arq`/Redis-client incompatibility by constraining the runtime to Redis client 5.x; generated `requirements.lock` and `requirements-dev.lock` and installed only through `.venv`.
- Provisioned MariaDB 12.3.3 as a workspace-local, ignored tool and bound the disposable server to `127.0.0.1:3307`; no system database service was installed.
- Generated a schema-only copy of `Backend/alumni_portal_v2.sql` by removing every `INSERT` statement before import. Verified all 68 application tables are present and empty; no supplied user/session/token/OTP rows were loaded.
- Added typed environment settings with no database URL default, synchronous SQLAlchemy/PyMySQL pooling, bounded connection/read/write timeouts, rollback cleanup, graceful disposal, normalized JSON errors, correlation IDs, structured logging, and separate liveness/readiness routes.
- Added 68 explicit SQLAlchemy table mappings containing 778 columns. Integration tests compare names, types/lengths, nullability, primary keys, server defaults, computed expressions, comments, foreign keys, and indexes to the sanitized MariaDB schema.
- Added and stamped intentionally empty Alembic baseline `7250164972b6`; `alembic check` reported `No new upgrade operations detected.` Alembic refuses a missing database URL and requires an additional explicit gate for production.
- Recorded database-access and deployment ADRs plus `docs/schema-baseline.md`. The report proves supplied-export compatibility only; current office/production schema parity remains unverified.
- Verification at that checkpoint: Ruff formatting/check passed; mypy passed for `app`, `migrations`, and `tests`; 24 pytest tests passed at 99.67% coverage; `pip check`, Bandit, and `pip-audit` passed with no known dependency vulnerabilities.
- Runtime smoke test: Uvicorn served `/health/live`, `/health/ready`, and `/openapi.json`; readiness returned `ready` after a real query to the sanitized clone; graceful shutdown completed.
- Known warnings: FastAPI/Starlette's test client emits two upstream deprecation warnings about the transition from `httpx`/AnyIO aliases. They do not fail the current suite but must be revisited on dependency upgrades.
- PHP source and the original SQL export were not modified. The generated local database state and MariaDB binaries are excluded by the workspace `.gitignore`.
- Next safe task: generate the endpoint contract/dependency catalogue from the PHP controllers and route configuration, then implement the authentication foundation and first retained read-only endpoints against repositories.

#### 2026-09-08 — Initial plan review and goal activation

- Created the chat goal for a complete FastAPI copy, completed retained endpoints, verified SQL connectivity, and successful tests.
- Verified the workspace currently contains only the backend snapshot, SQL export, and this migration plan at its root; no Python backend exists yet.
- Recounted controller methods using PHP visibility rules. The previous register covered 427 explicitly-public methods but omitted 18 implicit-public methods. Added those methods, producing a 445-method controller register.
- Identified the separate root `Backend/alumniappV2 - PHP/Api.php` as an unresolved artifact with 103 non-constructor functions; no claim is made that it is deployed or belongs in the migration.
- Inspected the SQL export structurally without exposing row values: 68 InnoDB tables, 69 insert statements across 62 tables, including identity, session, token, OTP, and user data tables; no dump-defined triggers or procedures were found.
- Rechecked the workstation baseline: Python 3.13.7 and Git 2.51.0 are available; `uv`, Docker, `mysql`, and `mysqldump` are not available on `PATH`; this workspace is not a Git repository.
- Tests run: documentation/source reconciliation commands only. No Python, database, frontend, external-provider, deployment, or production tests have run.
- Data/schema changes: none. PHP source and SQL export were not modified.
- Next safe task: complete Goal 0's non-secret inventory and Goal 1's endpoint disposition/contract evidence, then satisfy the virtual-environment and sanitized-database bootstrap gate before writing migration code.

## Handoff Requirements for Every Goal

Before marking a goal complete, the implementing agent must record:

- Files created or changed
- Commands and tests run
- Database/environment used for validation
- Passed and failed checks
- Security implications
- Compatibility differences from PHP
- Data or schema changes
- Deployment proof, if any
- Remaining risks and the next safe task

Static checks, mocked tests, and local success must not be presented as proof of production database behaviour, external provider behaviour, frontend compatibility, or deployment success.
