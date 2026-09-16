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
- PHP developer audit: `output/spreadsheet/alumniapp_code_audit.xlsx` (audited 2026-09-09; route reachability evidence, not final migration approval)
- Re-reviewed migration catalogue: `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx` (developer-audit reconciliation and route/function traceability)
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

Last verified: 2026-09-14 (Africa/Lagos)

Overall status: **In progress — the FastAPI foundation, schema connection, and first authentication slice are verified; full endpoint parity and production cutover remain incomplete.**

For this chat goal, "all endpoints finished" means every discovered PHP route candidate has a recorded retain, replace, merge, retire, internal-only, or remove decision; every retained capability has a FastAPI implementation; and the required contract, authorization, database, and frontend-compatibility checks pass. Retired and internal-only methods do not require a public FastAPI route, but their disposition must be evidenced.

| Completion gate | Current state | Current evidence | Required proof to complete |
| --- | --- | --- | --- |
| Separate FastAPI copy is complete | Foundation, auth, member, and notification-read slices in progress | `python-backend/` now contains a Python 3.13.7 FastAPI service, locked dependencies, typed configuration, health routes, database engine, explicit schema mappings, Alembic baseline, and thirty-four SQL-backed, rate-limited authentication/member/notification-read routes | Implement and verify every retained business capability; obtain approval for production worker/proxy configuration |
| All endpoint candidates are finished | In progress | Deterministic inventory covers 445 controller methods plus 103 root-artifact functions now classified as non-controller retirement candidates. The developer audit supplies 163 detailed endpoint rows marked `Live/KEEP`, exactly matching 157 of 159 conversion-catalogue rows; thirty-four primary auth/member/notification-read routes have FastAPI implementations and sanitized-SQL tests, while sixteen unsafe or superseded primary routes have explicit HTTP 410 tombstones. Frontend runtime parity is not yet proven. | Reconcile the developer audit's 175-summary versus 163-detail count, confirm the two unmatched catalogue rows and controller-list gaps, then add retained-route implementation, OpenAPI contract, authorization tests, database tests, and frontend compatibility evidence |
| Existing SQL database can be connected safely | Export-compatible test connection verified | A localhost-only MariaDB 12.3.3 target imported all 68 tables after all `INSERT` statements were removed; readiness, 778-column mapping parity, empty-table checks, and rollback-safe write tests pass | Read-only comparison and permission tests against the approved current office database remain required; no production access has been attempted |
| Required tests are successful | Current-scope suite passing | 242 tests pass at 93.13% statement/branch coverage for the foundation, schema, inventory, registration, public city/welfare-zone catalogues, protected privacy-aware zone roster/self assignment, authorized transaction-safe zone/city management, bounded transaction-safe geography and alumni imports, voucher discovery/ownership/decision, authentication/recovery/verification, post-verification notification, access-code, authorization-policy, member-profile, profile-visibility/update, validated avatar storage, privacy-aware directory, downward account listing, member-approval, account-state/role management, bounded alumni statistics, privacy-minimized birthday windows, public/protected chapter lookup, authenticated setup-parameter lookup, authenticated notification feed/read state, rate-limit, and expanded security-retirement slices; Ruff, strict mypy, Alembic drift detection, Bandit, dependency audit, and `pip check` pass | Remaining endpoint, protected-route authorization, live-provider, broader concurrency, frontend, load, deployment, and cutover tests do not exist yet |

### Initial Review Findings

- **P1 — Secret and personal-data containment is the first gate.** Credential-bearing configuration locations exist in `application/config/database.php`, `email.php`, `jwt.php`, and `vapid.php`. The SQL export contains user, session, refresh-token, OTP, and other data-bearing tables. Values must not be copied into the Python service, fixtures, logs, or this document.
- **P1 — Database success cannot be claimed from the SQL file alone.** The export establishes schema evidence, not current server access, credentials, schema drift, or successful read/write behaviour.
- **P1 — Endpoint completeness originally omitted implicit-public PHP methods.** The master register has been corrected from 427 explicitly-public methods to 445 callable non-constructor controller methods. Internal helpers are recorded as internal-only candidates rather than silently exposed.
- **P2 — The standalone `Backend/alumniappV2 - PHP/Api.php` is outside `application/controllers/` and has 103 non-constructor functions.** It appears to contain overlapping and unrelated legacy capabilities and must be classified before the endpoint inventory can be signed off.
- **P2 — Frontend contract evidence is not present at the workspace root.** The current workspace contains the backend snapshot, SQL export, and this plan. Goal 1 still needs the actual frontend source, captured traffic, or an approved contract fixture set before retained-route parity can be verified.
- **P2 — Developer audit evidence improves route reachability, but has unresolved internal count and coverage gaps.** Its detailed `Endpoints` sheet contains 163 rows, all marked `Live/KEEP`, while its `Summary` says 175 live endpoints. It supplies six controller rows but lists four `Socials` endpoints without a `Socials` controller row; it also says the core controller has 24 functions while the detailed core-function sheet lists 25. Treat the detailed rows as traceable evidence and obtain a corrected audit before using its headline counts as final.
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
- [x] Reconcile the PHP developer audit against the route catalogue (the detailed audit lists 163 `Live/KEEP` rows; 157 exactly match the 159 catalogue rows, while `Pwa /pwa/offline` and `my404 /my404/index` are not listed).
- [ ] Ask the PHP developer to reconcile the audit's 175-summary versus 163-detail endpoint count, its six listed controller rows versus seven `KEEP` controllers claimed in the summary, the omitted `Socials` controller row, and 24-versus-25 shared-core-function count.
- [ ] Treat a developer `Live/KEEP` finding as reachability evidence only. Retain the fourteen security-risk inventory retirements, ten workbook consolidated replacements, sixteen executable tombstones, and root-level `Api.php` retirement unless separate security, source-file, and frontend-contract evidence approves a different treatment.
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
- Developer-audit reconciliation and exception log

### Acceptance Gate

Every production frontend request and external callback has a named owner, an identified PHP implementation, known database dependencies, and a migration disposition. The developer-audit total and any controller/source-file discrepancies are resolved or explicitly accepted with evidence.

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

- [x] Implement registration with strict validation and server-controlled role defaults (public JSON/form/multipart registration is SQL-tested with atomic graph creation, rollback-safe avatar storage, and post-commit verification mail; production data, SMTP, and full browser proof remain separate gates).
- [x] Implement email verification with expiring, one-time codes (24-hour replacement/consumption and activation, exact-role administrator notification, pending-voucher notification, and committed-state behavior on provider failure are SQL-tested; live SMTP remains a separate gate, and unsafe legacy aliases are retired).
- [x] Implement login, logout, token refresh, password reset, and account recovery (all six routes are implemented and SQL-tested; live SMTP acceptance remains a Goal 9/provider gate).
- [x] Implement profile read/update operations with explicit field allowlists (`get_user_profile`, `update_profile`, `get_profile_visibility`, and `update_profile_visibility` are implemented and SQL-tested with dedicated schemas; live frontend and production comparison remain separate gates).
- [x] Preserve field-level visibility rules for member directory responses (`get_users_by_action` enforces global and per-field settings server-side; malformed JSON fails private and missing keys retain the PHP-compatible public default).
- [x] Implement protected birthday discovery with Lagos date boundaries, reviewed February 29 observance, bounded today/week/month/upcoming filters, explicit birth-date privacy, and a frontend-compatible minimum projection (`get_birthdays` supports documented GET plus the active bodyless POST caller; exact DOB, age, contact, account, and unrelated profile fields are omitted).
- [x] Implement bounded administrative alumni import with current-database account-management authority, strict JSON/CSV/XLSX allowlists, enabled chapter/city validation, duplicate and ambiguity rejection, all-or-nothing reconciliation, preserved existing account privileges/credentials/state/visibility, server-owned new-member privileges, and credential-free per-row outcomes (`import_alumni` is SQL- and contract-tested; hidden callers, approved production data, scale, proxy limits, and concurrency remain separate gates).
- [ ] Implement chapter and graduation-year membership rules (`get_chapters` provides a bounded enabled-chapter list plus current-database-authorized self/downward assignment lookup; public city/zone catalogues, protected zone roster/self-assignment, authorized single-row geography mutation, and bounded transaction-safe geography import now preserve the location mapping safely, while assignment UI and production parity remain).
- [x] Implement member approval, deactivation, and bounded role changes with administrator authorization (`POST /api/approve_user` and `POST /api/manage_user_account` are implemented with current database authorization, row locks, hierarchy/state guards, fixed role categories, bounded output, transactional refresh-token revocation, post-commit state notification, and rollback tests).
- [x] Retire the destructive legacy `GET|POST /api/deactivate_staff/{user_id}` contract with a tested HTTP 410 tombstone. It authenticated no actor, authorized no target, and deleted the user after deactivation; callers must use the state-only, authorized `/api/manage_user_account` operation.
- [x] Implement voucher discovery using only the minimum public fields required (GET/POST support an optional class year and expose only ID, name, year, and chapter).
- [x] Implement voucher pending and approve/deny workflows with ownership checks (current-role recheck, pending-only state machine, verified/unapproved guards, atomic approval, denial without account mutation, and post-commit mail are SQL-tested).
- [x] Ensure a voucher can act only on records assigned to that voucher (cross-owner decisions are concealed as not found and database-backed tests prove exact ownership filtering).
- [ ] Replace `users.*` responses with dedicated public, member, administrator, and self-profile schemas (`get_user_profile`, approval/account mutation, `get_users_by_action`, `get_users_by_zone`, and `get_birthdays` now use dedicated projections; remaining user-returning routes are pending).
- [ ] Add pagination and bounded filters to all user/member listing endpoints (`get_users_by_action` and `get_users_by_zone` are capped at 100 rows per page; remaining listing routes are pending).
- [ ] Add audit events for privileged profile, role, approval, and voucher changes (approval, voucher decision, and visibility mutation emit PII-free structured events after commit; a durable audit table requires a separately reviewed schema decision).
- [ ] Verify email, phone, address, date-of-birth, employment, token, and password fields are excluded unless explicitly authorized (`get_users_by_action` omits credential columns and applies profile visibility; `get_zones`, `get_users_by_zone`, and `get_my_zone` omit coordinator/member email and apply reviewed eligibility/global/field visibility; `get_birthdays` omits exact DOB, age, contact, account, and unrelated profile fields; remaining routes require the same review).

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

### PHP Developer Audit Re-review (2026-09-10)

The PHP developer audit in `output/spreadsheet/alumniapp_code_audit.xlsx` adds useful evidence about which primary PHP methods are currently reachable. Its detailed `Endpoints` sheet contains 163 rows, all marked `Live/KEEP`. Those rows exactly match 157 of the 159 routes in the FastAPI conversion catalogue. The two catalogue rows not listed are `Pwa /pwa/offline` and `my404 /my404/index`; neither should be considered developer-confirmed until separately reviewed.

This evidence narrows the inventory, but it does not replace Goal 1 contract evidence or the existing security decisions:

- The audit `Summary` says 175 live endpoints, which is 12 more than the traceable 163-row `Endpoints` sheet. Use the detailed rows rather than the headline total until the PHP developer reconciles the difference.
- The audit's `Controllers` sheet supplies six controller rows (`Api`, `Auth`, `Blog_api`, `Chat_api`, `News`, and `Product`) but its summary says seven `KEEP` controllers. `Socials` has four `Live/KEEP` endpoint rows without a matching controller row. `Auth` is reachable but marked `REVIEW` with no log evidence.
- The audit lists `Api`, `Blog_api`, `Chat_api`, `News`, and `Product` as active and `KEEP`; it identifies `MY_Controller`, five models, and its shared helpers as required supporting code. Port the required behavior through FastAPI dependencies/services, not by exposing the helpers as public routes.
- The audit listing a method as `Live/KEEP` does not reverse the nine security tombstones (`check_reset_password`, `getAPIKey2`, `trackUser`, `sendUserOTP`, `change_user_password1`, `test_qr`, `update_user_account`, `update_user_role`, and `manage_user_roles`) or the two consolidated aliases (`verify_otp` and `resend_otp`). These stay blocked, redesigned, or redirected unless separate security and frontend evidence approves a change.
- A name/URL match to the active `Api` controller does not make the standalone root `Backend/alumniappV2 - PHP/Api.php` active. Its 103 functions remain non-controller duplicate retirement candidates unless source-file or runtime evidence disproves that classification.

The workbook `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx` records every developer-audit match, exception, and resulting migration treatment. It is the working reference for retirement and confirmation review; this register remains the implementation checklist.

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
| `api/get_chapters` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:954` | Goal 5 | Implemented for both documented GET and POST access patterns. With no `user_id`, it returns a rate-limited explicit list of enabled chapter ID/name/location/state/creation fields without requiring the legacy shared application token. A `user_id` switches to a Bearer-protected self/downward-manager assignment lookup using current database facts, fail-closed role hierarchy, deterministic oldest-category selection, and bounded chapter/year/location output. Sanitized-SQL tests cover public filtering/order, GET/POST parity, self/manager/forged/stale authorization, unassigned/not-found outcomes, and PII exclusion. Registration now derives `chapter_id` from the selected city's live catalogue row; authenticated assignment runtime and production comparison remain pending. |
| `api/register` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1043` | Goal 5 | Implemented as a public peer- and identity-rate-limited POST accepting bounded JSON/form/multipart input. It ignores client privilege fields, fixes the account to `alumni`/non-coordinator/current registration year, validates the enabled chapter/city relationship and any current active voucher, hashes with Argon2id, and atomically writes the user, member-group assignment, alumni category, private profile, optional pending vouch, finite verification code, and optional normalized avatar metadata with file cleanup on rollback. Mail is post-commit; failure returns resend guidance without deleting committed state. The frontend chapter hard-code is removed in favour of the selected city's live `chapterId`. Sanitized-SQL and contract tests pass; production data, live SMTP, and complete frontend runtime parity remain pending. |
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
| `api/create_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2080` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance to `/api/manage_user_account`. PHP accepts a reusable application key without user authorization, permits arbitrary globally unique role names, and omits the reviewed `roles.user_id` required column. The SQL snapshot has no role rows and no current source or built-frontend caller was found; dynamic rows remain non-authoritative metadata. |
| `api/manage_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2141` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance to the fixed account-role contract. PHP lets a reusable application-key holder rename or delete any caller-selected free-form role row, lacks target hierarchy/existence proof, and conflicts with the reviewed `users.user_role` authorization source. No current source or built-frontend caller was found. |
| `api/get_roles` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2205` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance. PHP can return complete free-form role rows including `user_id` to a reusable application-key holder and emits a malformed no-data status. The active frontend role picker uses a fixed reviewed enum through `/api/manage_user_account`; the reviewed SQL snapshot has no role rows and no current route caller was found. |
| `api/get_setup_parameters` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2250` | Goal 5 | Implemented as authenticated POST JSON/form lookup with a bounded 100-character `action_type`, active Bearer account-state recheck, shared per-user rate limiting, explicit setup ID/name/raw-value/trimmed-values output, deterministic oldest-row selection for duplicate legacy data, real HTTP 400/404 outcomes, and no legacy `X-API-Key` authorization. Sanitized-SQL tests cover case-insensitive lookup under the reviewed collation, JSON/form parity, duplicate selection, empty-list-item compaction while retaining `0`, unknown names, and stale-token denial. The SQL snapshot has four generic setup names but the route can query any future row; no current source or built-frontend caller was found, so hidden usage and approved production-data comparison remain pending. |
| `api/create_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2304` | Goal 7 | Implement and compare |
| `api/manage_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2349` | Goal 7 | Implement and compare |
| `api/get_privacy_policy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2396` | Goal 7 | Implement and compare |
| `api/manage_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2434` | Goal 5 | Activation/deactivation and bounded role changes are implemented and sanitized-SQL tested against active frontend callers. Authorization reloads and locks current database actor/target facts; role input is restricted to the reviewed alumni/manager/administrator/category-admin set, administrative promotion requires active/approved/verified state, and successful state or role changes revoke refresh sessions transactionally. Responses are bounded and audit events exclude PII; authenticated frontend runtime, live SMTP for state notifications, and production comparison remain pending. |
| `api/get_users_by_action` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2539` | Goal 5 | Implemented and sanitized-SQL tested with separate bounded directory and account-administration projections, approved/active/verified filtering, fixed pagination, server-side profile-visibility redaction, globally hidden-profile exclusion, current-database permission checks, and downward-only reviewed role scope. Unknown actions fail closed; authenticated frontend and production comparison remain pending. |
| `api/update_user_account` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2688` | Goal 5 | Security-retired with tested GET/POST HTTP 410 replacement guidance to `/api/update_profile`. The PHP route checked only bearer validity before accepting any caller-selected `user_id`, logged the raw request/token, attempted ambiguous writes to seven fields absent from the reviewed `users` schema, and has no current source or built-frontend caller. Its valid name/phone/avatar purpose is covered by the allowlisted profile route; hidden production callers and any proof-document workflow still require confirmation. |
| `api/sendUserOTP` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2792` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone; delivery is private behind `resend_verify_email` |
| `api/verify_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2861` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone pointing to `api/verify_email`; the legacy identity comparison is broken and the route can bypass the approval state machine |
| `api/verify_user_access_code` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2911` | Goal 4 | Implemented as a rate-limited self-only Bearer route using current database state and constant-time comparison; caller `user_id` and identity/role disclosure removed; frontend comparison pending |
| `api/resend_otp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2952` | Goal 4 | Retired with a GET/POST HTTP 410 tombstone pointing to `api/resend_verify_email`; duplicate mail/storage behavior is consolidated into the finite verification flow |
| `api/create_notification` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2989` | Goal 9 | Pending source/schema/provider design. PHP has no effective administrator gate, accepts caller-selected recipients, writes `category`/`title`/`target_user_id`, and pushes through `push_tokens`; those fields/table are absent from the reviewed current schema. No FastAPI route is exposed until hidden/mobile callers, authorised creators, recipient rules, storage migration, provider, retries, opt-out, and production contract are confirmed. |
| `api/get_notifications` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3044` | Goal 9 | Implemented as bounded GET and legacy-shaped POST self-service routes. Active Bearer identity, not caller `user_id`, scopes current-schema `notifications.user_id` rows; the account-creation boundary, pagination, response shaping, current-account recheck, rate limit, and cross-owner denial are tested. PHP's absent `notifications_read`/`target_user_id` model is not copied; frontend/live-data comparison remains pending. |
| `api/mark_notification_read` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3080` | Goal 9 | Implemented as authenticated POST single-owned or bounded mark-all update. The current account and target row are locked, caller `user_id` is ignored, only current-schema per-row `is_read` state changes in one transaction, and rollback/cross-owner/stale-account/idempotency tests pass. PHP's absent `notifications_read` storage is not copied; frontend/live-data comparison remains pending. |
| `api/change_user_password1` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3151` | Goal 0 / Goal 1 | Retired with a GET/POST HTTP 410 tombstone; use the self-only Bearer `change_user_password` route |
| `api/change_user_password` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3207` | Goal 4 | Implemented as self-only Bearer route with SQL tests; frontend comparison pending |
| `api/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3313` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance to `/api/manage_user_account`. The PHP route accepts any path user ID without authenticating or authorizing the caller, deactivates and emails the target, then permanently deletes the user row. The safe replacement changes only account state and revokes refresh sessions transactionally. No current source or built-frontend caller was found; hidden callers must be confirmed before cutover. |
| `api/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3394` | Goal 9 | Security-retired with tested GET/POST HTTP 410 responses. PHP trusts a reusable key plus caller-selected `user_id`, logs the complete push-token payload, returns the stored token, and calls a `push_tokens` table absent from the reviewed SQL snapshot. No current source or built-frontend caller was found. An authenticated self-service registration/revocation contract remains pending until hidden/mobile callers and the live push provider/data model are confirmed. |
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
| `api/approve_user` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5682` | Goal 5 | Implemented and sanitized-SQL tested with active frontend source caller, current database authorization, lower-role hierarchy, verified-email/state guards, row locks, bounded response, rollback proof, and post-commit account-status mail; authenticated frontend runtime, live SMTP, durable audit storage, and production comparison pending |
| `api/update_user_role` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5773` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance to `/api/manage_user_account`. No current source or built-frontend caller was found; the PHP route accepts arbitrary role text and mixes role mutation with chapter assignment. The shared route now provides the reviewed bounded role contract, while chapter changes remain in the profile flow. |
| `api/manage_user_roles` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5858` | Goal 5 | Security-retired with tested GET/POST HTTP 410 guidance to the reviewed shared account route. No current source or built-frontend caller was found; its free-form per-user `roles` rows conflict with the reviewed globally unique `role_name` schema and are not an authorization source. |
| `api/update_profile` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5992` | Goal 5 | Implemented and sanitized-SQL tested with JSON/flat/nested multipart parsing, self/downward current-database authorization, partial allowlisted user/profile upsert, safe fullname rebuilding, chapter validation, inactive-target preservation, bounded credential-free response, PII-free audit fields, and 5 MB decoded-image validation/re-encoding. Avatar file plus attachment metadata rollback together on failure; authenticated frontend and production storage/database comparison remain pending. |
| `api/update_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6181` | Goal 5 | Implemented and sanitized-SQL tested with self/downward-manager authorization from current database facts, fixed field allowlist, locked merge/upsert, malformed-JSON fail-closed behavior, rollback proof, bounded response, and PII-free audit event; authenticated frontend and production comparison pending |
| `api/get_profile_visibility` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6304` | Goal 5 | Implemented and sanitized-SQL tested with self/downward-manager authorization from current database facts, complete public/private map, bounded response, and malformed-JSON fail-closed behavior; authenticated frontend and production comparison pending |
| `api/get_alumni_stats` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6382` | Goal 5 | Implemented for both documented GET and POST access patterns with active Bearer authentication, current-database actor-state recheck, shared per-user rate limiting, and an explicit four-integer response. Sanitized-SQL tests preserve the PHP count definitions for active approved exact-role alumni, distinct non-null category years, enabled chapters, and distinct non-empty departments on approved users. The legacy shared API token is ignored; no current source or built-frontend caller was found, so authenticated runtime and production comparison remain pending. |
| `api/import_alumni` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6436` | Goal 5 | Implemented as a Bearer-protected JSON or multipart POST requiring current active-database `MANAGE_ACCOUNTS` authority and strict downward-target checks; the reusable application key grants nothing. JSON accepts only `chapter_id` and at most 500 allowlisted records; multipart accepts exactly one enabled chapter plus one UTF-8 CSV/XLSX file under 2 MiB. MIME/extension, XLSX archive expansion, formulas, unknown/duplicate headers or identities, control characters, dates/years/booleans, selected-chapter city membership, member-group configuration, peer/higher targets, and ambiguous existing memberships are validated before mutation. One transaction preserves existing roles, coordinator state, credentials, access/user codes, activation/approval/verification state, and profile visibility while reconciling profile/chapter/year fields. New rows receive the fixed alumni role, no coordinator grant, a random unknown Argon2id credential with `has_password=0`, empty access code, member group/category, and private profile. The legacy client default password, raw access/user-code disclosure, client coordinator grant, source timestamp write, and forced existing-account activation/approval are intentionally retired. Responses expose only source row, imported/updated/unchanged status, and user ID. SQL tests prove current-role authorization, stale/forged-claim denial, exact preservation, secure new-account state, idempotency, ambiguity rejection, and late-failure rollback. No current source or built-frontend caller was found; hidden usage, approved production roster/city/membership data, proxy/body limits, scale, concurrency, and administrator workflow remain pending. |
| `api/create_project` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:6885` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a bounded JSON/form/multipart POST over the reviewed `Projects` table. An active, freshly loaded current-database `MANAGE_CONTENT` actor creates a project in their current chapter; caller-selected ownership/deletion fields are ignored, references and amounts are validated, and at most six generated/re-encoded images are transactionally stored with rollback cleanup. |
| `api/manage_project` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7029` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a bounded JSON/form/multipart update/delete contract. It requires active, freshly loaded current-database `MANAGE_CONTENT`, locks the project, allowlists fields/references, supports safe add/replace/removal of at most six generated images, and soft-deletes rather than copying PHP's unaudited behavior. |
| `api/get_projects` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7214` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded public POST feed/detail lookup. It returns only active/completed/paused/ongoing non-deleted presentation fields; draft and deleted projects, ownership, and member identifiers are not exposed. The legacy application-key read gate is deliberately not copied after current caller review. |
| `api/create_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7433` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a bounded JSON/form/multipart POST. An active, freshly loaded current-database `MANAGE_CONTENT` actor creates a reviewed leadership row, validates the member/chapter/year uniqueness, derives `created_by`, and transactionally stores an optional generated/re-encoded photo with rollback cleanup. |
| `api/manage_leader` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7588` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded update/delete/reorder operations. Current-database `MANAGE_CONTENT`, deterministic locks, explicit fields, per-year featured-row clearing, duplicate safeguards, generated-photo replacement cleanup, soft deletion, and a 100-record reorder limit replace PHP's unbounded trusted writes. |
| `api/get_leadership` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7799` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded public POST list/detail contract. It preserves reviewed chapter/year global scope and featured/team/all response groups, but does not expose PHP's member email, phone, department, or bio. The legacy application-key read gate is deliberately not copied. |
| `api/get_vouchers` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:7991` | Goal 5 | Implemented for GET and the active frontend POST pattern with optional bounded graduation-year filtering, enabled/current voucher selection, deterministic ordering, peer/year rate limiting, and a four-field public projection (ID, name, year, chapter). Contact/role/avatar/department data and the legacy shared key are excluded. The registration UI now queries the selected year and labels by class instead of email. Sanitized-SQL and OpenAPI tests pass; production voucher data and browser runtime remain pending. |
| `api/voucher_pending` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8023` | Goal 5 | Implemented for GET and active frontend POST compatibility with Bearer authentication, current active-voucher database recheck, per-user rate limiting, exact assigned-owner filtering, pending-only deterministic results, and a dedicated protected projection. Tests cover method parity, non-voucher/stale-account denial, status exclusion, and cross-owner exclusion; authenticated frontend and production comparison remain pending. |
| `api/vouch_action` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8071` | Goal 5 | Implemented as JSON/form POST with current active-voucher recheck, concealed cross-owner records, pending-only replay protection, verified-email/unapproved guards, deterministic row locks, canonical approve/deny state, atomic vouch/account approval, denial without account mutation, bounded output, PII-free audit logging, and post-commit registrant plus exact-role manager mail. SQL tests prove authorization, guards, rollback on late database failure, and committed state during mail failure; live SMTP, durable audit storage, production concurrency, and authenticated frontend runtime remain pending. |
| `api/get_zones` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8177` | Goal 5 | Implemented for GET and the active welfare frontend POST pattern as a public peer-rate-limited zone/city catalogue. It preserves zone/chapter and nested city IDs/names, but returns a coordinator only when the current account is active, approved, verified, and not globally hidden; phone/avatar follow stored visibility and malformed JSON fails private. Email, role, department, and unrelated account/profile fields are never selected. The frontend now uses member ID rather than email for self-message prevention. SQL/OpenAPI tests pass; approved production geography/privacy and browser runtime remain pending. |
| `api/get_cities` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8232` | Goal 5 | Implemented for GET and the active registration/profile frontend POST pattern as a public peer-rate-limited city/chapter/zone catalogue without the reusable application key. Output is an explicit five-field projection ordered by source zone ID, city, and city ID; orphaned zone references retain the city row's actual zone ID and omit only the missing zone name. SQL/OpenAPI tests cover method parity, ordering, bounded fields, orphan behavior, and PII exclusion; production data and browser comparison remain pending. |
| `api/get_users_by_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8259` | Goal 5 | Implemented for GET plus bounded JSON/form POST compatibility with active Bearer authentication, current account-state recheck, deterministic ID/exact-name resolution, ID precedence, shared per-user rate limiting, and 100-row maximum pagination. The legacy zone-name bug is fixed by querying the resolved ID. Candidate members must be active, approved, and verified; globally hidden or private-city members are excluded for other viewers so the zone itself does not leak location. Owner access and missing visibility preserve reviewed behavior, malformed JSON fails private, phone/avatar remain field-aware, and the dedicated roster omits email, user code, address, birth date, employment, role, account state, and credentials. SQL/OpenAPI tests pass; no current source or built-frontend caller was found, and production geography/privacy/browser proof remains pending. |
| `api/get_my_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8366` | Goal 5 | Implemented for GET and no-body POST as an active-Bearer self-only lookup with current account-state recheck and shared per-user rate limiting. Caller-supplied body data is ignored; trimmed case-insensitive city lookup chooses the oldest row for deterministic duplicate handling, and missing city/unknown city/orphan zone preserve HTTP 200 `Not Yet Available`. Resolved coordinator identity requires a current active/approved/verified/non-hidden account, phone/avatar obey visibility with malformed JSON failing private, explicit unavailable coordinators remain null, and email is never selected. SQL/OpenAPI tests pass; no current source or built-frontend caller was found, and production mapping/browser proof remains pending. |
| `api/manage_zone` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8444` | Goal 5 | Implemented as bounded JSON/form POST create, update, and delete with a current active-account `MANAGE_ZONES` check based only on database facts, per-user throttling, serialized catalogue locks, normalized duplicate detection, valid chapters, active/approved/verified same-chapter coordinators, explicit coordinator clearing, atomic child-city chapter alignment, and deletion protection while cities reference the zone. Responses and audit fields are bounded and PII-free; sanitized-SQL tests prove admin/super-admin access, stale/forged-claim denial, validation/conflict behavior, and rollback on a late write failure. No current source or built-frontend caller was found; production role/data, concurrency, and administrator UI proof remain pending. |
| `api/manage_city` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8531` | Goal 5 | Implemented as bounded JSON/form POST create, update, and delete with the same current-database `MANAGE_ZONES` authorization and catalogue lock order. Names are normalized and globally duplicate-checked, zones must exist, city chapter metadata must follow the selected zone, moves align the chapter automatically, and rename/delete is blocked while `users.city` or `user_profiles.city` still references the old name. Sanitized-SQL tests cover authorization, duplicate/chapter/reference conflicts, bounded outcomes, and rollback on a late update failure. No current source or built-frontend caller was found; production data, concurrency, and administrator UI proof remain pending. |
| `api/upload_zones_cities` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8622` | Goal 5 | Implemented as a current-database-authorized multipart POST accepting exactly one UTF-8 CSV or XLSX file with exactly `zone` and `city` columns, at most 2 MiB and 2,000 data rows. Authentication and per-user throttling precede parsing; the reusable key grants nothing. MIME/extension, archive entry/count/expansion, formulas, control characters, cell lengths, duplicate input cities, ambiguous existing normalized rows, and chapter references are validated before writes. New zones preserve the reviewed legacy chapter-1 default only when chapter 1 exists; every inserted or moved city aligns to its zone's chapter. One deterministic catalogue lock order and transaction make the import idempotent and all-or-nothing, with bounded errors and PII-free count logging. Binary `.xls` and raw parser exception disclosure are intentionally retired. Sanitized-SQL tests prove current-role authorization, stale/forged-claim denial, CSV/XLSX contracts, exact summaries, idempotent reruns, duplicate ambiguity failure, and rollback after a late insert. No current source or built-frontend caller was found; approved production chapter-1/duplicate data, edge body limits, concurrency, and administrator UI proof remain pending. |
| `api/get_birthdays` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:8779` | Goal 5 | Implemented as query-only GET plus bodyless POST compatibility for the active frontend caller. Both require a current active Bearer account, share per-user throttling, use the reviewed Africa/Lagos day boundary and February 29-to-28 non-leap observance, support today/week/month/upcoming windows, clamp legacy days/limit inputs, and cap output at 200 plus internal candidate processing at 10,000. Only active users are candidates, preserving PHP behavior regardless of approval/email verification; explicit `birth_date=false` opts out, missing keys remain public, malformed visibility fails private, and global profile hiding deliberately does not suppress birthdays. Avatar visibility is newly enforced. The response keeps the five active frontend card fields plus safe schedule metadata while retiring user code, exact DOB, age, names/components not used by the caller, department, house, city, email, and phone. Sanitized-SQL contract/privacy/window/bound tests pass; authenticated browser runtime, hidden callers, approved production privacy data, and scale remain pending. |
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
| `api_backup/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7169` | Goal 1 | Contain or remove; backup copy of the unauthenticated destructive primary route |
| `api_backup/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/Api_backup.php:7248` | Goal 1 | Contain or remove; backup copy of the caller-selected push-token route |
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
| `oldapi/deactivate_staff/{user_id}` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4214` | Goal 1 | Contain or remove; old-controller copy of the unauthenticated destructive primary route |
| `oldapi/user_tokens` | `Backend/alumniappV2 - PHP/application/controllers/oldApi.php:4293` | Goal 1 | Contain or remove; old-controller copy of the caller-selected push-token route |
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

- `api/register` — implemented and sanitized-SQL tested with bounded JSON/form/multipart input, peer/identity throttling, server-controlled role/coordinator/year, enabled chapter/city and current-voucher validation, Argon2id, atomic user/group/category/private-profile/vouch/OTP/avatar writes, rollback file cleanup, and post-commit mail/resend semantics; the frontend now derives chapter from the selected city
- `api/get_user_profile`
- `api/update_profile` — implemented and sanitized-SQL tested with allowlisted JSON/multipart partial updates, current-database self/downward authorization, profile upsert, normalized avatar storage, attachment metadata and database/file rollback cleanup
- `api/update_profile_visibility` — implemented and sanitized-SQL tested with locked allowlisted merge/upsert, rollback proof, and current-database self/downward-manager authorization
- `api/get_profile_visibility` — implemented and sanitized-SQL tested with a complete bounded public/private map and malformed-JSON fail-closed handling
- `api/get_alumni_stats` — GET and POST implemented and sanitized-SQL tested with current active-account recheck, exact four-count PHP semantics, shared rate limiting, no member records, and no legacy application-token authorization
- `api/import_alumni` — JSON and multipart CSV/XLSX implemented and sanitized-SQL tested with current database account-management authorization, strict prevalidation, enabled chapter/city enforcement, preserved existing credential/privilege/state/visibility fields, server-owned new-member account state, idempotent row outcomes, no secret disclosure, and whole-batch rollback
- `api/get_birthdays` — GET and bodyless POST implemented and sanitized-SQL tested with current active-account recheck, Lagos today/week/month/upcoming windows, February 29 observance, missing-key-public/explicit-false-private rules, malformed-JSON fail-closed handling, global-directory-visibility independence, avatar privacy, deterministic 200-row output and 10,000-candidate processing bounds, and a minimum active-frontend-compatible projection that omits exact DOB, age, contact, account, and unrelated profile fields

Member administration and roles:

- `api/create_role` — security-retired with tested GET/POST HTTP 410 guidance; arbitrary definition creation omits mandatory `roles.user_id` and is not an authorization contract
- `api/manage_role` — security-retired with tested GET/POST HTTP 410 guidance; reusable-key arbitrary row update/deletion is not copied
- `api/get_roles` — security-retired with tested GET/POST HTTP 410 guidance; broad free-form role rows are replaced by the fixed `/api/manage_user_account` role enum
- `api/manage_user_account` — activation/deactivation and bounded role changes implemented and sanitized-SQL tested with current-database authorization, reviewed category aliases, state/hierarchy gates, atomic refresh-session revocation, bounded responses, and rollback proof
- `api/get_users_by_action` — implemented and sanitized-SQL tested with credential-free visibility-aware directory output plus downward-only administrator account output and bounded pagination
- `api/update_user_account` — security-retired with tested GET/POST HTTP 410 guidance to `/api/update_profile`; do not copy its arbitrary cross-account writes, raw token/body logging, or nonexistent-column contract
- `api/deactivate_staff/{user_id}` — security-retired with tested GET/POST HTTP 410 guidance to the authorized state-only `/api/manage_user_account`; the legacy unauthenticated delete-after-deactivate behavior is not copied
- `api/approve_user` — implemented and sanitized-SQL tested; frontend source contract is mapped, while authenticated runtime and live SMTP proof remain pending
- `api/update_user_role` — security-retired with tested GET/POST HTTP 410 guidance to `api/manage_user_account`; its arbitrary-role/chapter-mutation contract is not copied
- `api/manage_user_roles` — security-retired with tested GET/POST HTTP 410 guidance; free-form `roles` rows remain non-authoritative metadata
- `api/get_setup_parameters` — implemented and sanitized-SQL tested as a current-active-Bearer JSON/form lookup with bounded input/output, deterministic oldest-row selection, trimmed comma-list values, real 400/404 statuses, per-user rate limiting, and no reusable application-key authorization

Chapters, zones, and cities:

- `api/get_chapters` — GET and POST implemented and sanitized-SQL tested with a public enabled-chapter list when `user_id` is absent plus active-Bearer self/downward-manager assignment lookup when it is supplied; output is bounded, unknown roles fail closed, duplicate assignments resolve to the oldest category row, and no assignment remains HTTP 200 with `chapter: null`
- `api/get_zones` — GET/POST implemented and sanitized-SQL tested with public nested zone/city metadata, eligible current coordinator projection, global/phone/avatar visibility enforcement, malformed-privacy fail-closed behavior, no email, and frontend member-ID self-checking
- `api/get_cities` — GET/POST implemented and sanitized-SQL tested with deterministic five-field city/chapter/zone output, retained orphan source zone IDs, no reusable key, and no member data
- `api/get_users_by_zone` — GET plus JSON/form POST implemented and sanitized-SQL tested with active-Bearer authorization, deterministic ID/name resolution, fixed legacy name lookup, 100-row pagination, eligibility/global/city visibility exclusion, field-aware phone/avatar, owner visibility, and a dedicated PII/account-minimized roster
- `api/get_my_zone` — GET/POST implemented and sanitized-SQL tested as a current-active self-only lookup with deterministic oldest city mapping, spoofed-body immunity, HTTP 200 unavailable compatibility, privacy-aware eligible coordinator, explicit null, and no email
- `api/manage_zone` — implemented and sanitized-SQL tested as bounded JSON/form create/update/delete with current-database `MANAGE_ZONES` authorization, serialized catalogue locks, normalized duplicate checks, chapter/coordinator validation, explicit coordinator clearing, child-city chapter alignment, reference-safe deletion, rollback proof, and PII-free mutation logging
- `api/manage_city` — implemented and sanitized-SQL tested with the same authorization/lock order, global normalized duplicate checks, enforced zone/chapter alignment, automatic chapter alignment on moves, member/profile free-text reference guards for rename/delete, bounded responses, and rollback proof
- `api/upload_zones_cities` — implemented and sanitized-SQL tested as an exactly-one-file CSV/XLSX import with current-database `MANAGE_ZONES` authorization, authentication/throttling before parsing, 2 MiB/2,000-row and archive/formula/cell bounds, exact headers, normalized duplicate and ambiguity checks, validated chapter-1 fallback, zone/city chapter alignment, deterministic catalogue locks, idempotent counts, bounded errors, and all-or-nothing rollback; binary `.xls` requires conversion

Voucher workflow:

- `api/get_vouchers` — GET/POST implemented and sanitized-SQL tested with optional class-year filtering, active-voucher-only deterministic output, public four-field projection, no contact/role metadata, and frontend server-side year filtering
- `api/voucher_pending` — GET/POST implemented and sanitized-SQL tested with current active-voucher recheck, exact owner scoping, pending-only output, and non-voucher/stale-account denial
- `api/vouch_action` — POST implemented and sanitized-SQL tested with exact ownership, row locks, verified/unapproved/pending guards, canonical approve/deny transitions, atomic account approval, rollback proof, and post-commit bounded notifications

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

- `api/get_listings` — implemented pending disposable-MariaDB/frontend proof as a bounded public POST read over reviewed active/unexpired listings and social metadata; it preserves global chapter/year scope and legacy http(s) image references, but never exposes seller email
- `api/create_listing` — implemented pending disposable-MariaDB/frontend proof; accepts reviewed JSON/multipart shapes, derives ownership and privileged fields server-side, validates/re-encodes up to six images, creates social metadata, and cleans stored files if the database transaction fails
- `api/manage_listing` — implemented pending disposable-MariaDB/frontend proof; owner or freshly loaded current-database `MANAGE_STORE` policy can update/delete, safely add/replace/remove images, and upsert reviewed social metadata
- `api/create_market`, `api/manage_market`, `api/get_market` — legacy `market`-table family; not implemented because that table is absent from the reviewed schema and no current frontend caller was found. Retire/compatibility decision requires separate traffic or schema evidence.
- `api/create_vacancy`
- `api/manage_vacancy`
- `api/get_vacancies`

Announcements and policy:

- `api/create_announcement` — implemented pending disposable-MariaDB/frontend proof; active Bearer plus current database `MANAGE_CONTENT` permission, explicit input contract, and bounded re-encoded image storage
- `api/manage_announcement` — implemented pending disposable-MariaDB/frontend proof; same current-policy gate, explicit update/delete allowlist, locks, transaction boundary, and local-image cleanup
- `api/get_announcements` — implemented pending disposable-MariaDB/frontend proof as a bounded public GET/POST presentation feed; legacy API-key/JWT read gate is deliberately not copied after current homepage/frontend review
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
- `api/create_project` — implemented pending disposable-MariaDB/frontend proof; current-database `MANAGE_CONTENT` actors create bounded JSON/form/multipart projects in their current chapter, with server-owned ownership/deletion state and transaction-safe generated-image cleanup
- `api/manage_project` — implemented pending disposable-MariaDB/frontend proof; same current-policy gate, row locks, explicit field allowlist, safe bounded image add/replace/removal, and soft deletion
- `api/get_projects` — implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded public POST feed/detail contract; only non-deleted active/completed/paused/ongoing presentation data is exposed, never draft/owner/member fields
- `api/create_leader` — implemented pending disposable-MariaDB/frontend proof with current-database `MANAGE_CONTENT`, explicit member/chapter/year validation, generated optional photo storage, and rollback cleanup
- `api/manage_leader` — implemented pending disposable-MariaDB/frontend proof with locked update/delete/reorder operations, soft deletion, bounded reorder, featured uniqueness, and safe photo replacement/removal
- `api/get_leadership` — implemented pending disposable-MariaDB/frontend proof as the public active-frontend POST feed/detail; it retains featured/team/all grouping and filters while redacting PHP's member PII

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
- `api/user_tokens` — security-retired; a private or authenticated self-service replacement with an explicit field schema remains conditional on confirmed callers and provider/storage requirements

The legacy `api/user_tokens` path is already blocked by a tested GET/POST HTTP 410
tombstone. A replacement must bind token ownership to the current authenticated member,
avoid logging or returning token values, support revocation, and be added only after
hidden/mobile callers plus the live provider and storage model are confirmed.

`api/get_notifications` and `api/mark_notification_read` are now implemented only for
the reviewed current `notifications` schema. They require an active Bearer principal,
derive ownership from that identity, enforce the account-creation boundary, and never
query PHP's absent `notifications_read` or `target_user_id` storage. Notification
creation and push delivery remain deliberately unimplemented because the PHP request,
recipient and provider model does not map safely to the reviewed schema.

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
| Goal 1 | In progress | Deterministic JSON inventory covers 445 controller methods and 103 retired root-artifact functions with source hashes, routeability, inferred request mode, inputs, table/model calls, auth signals, statuses, value-free controller execution references, two consolidated-route decisions, and fourteen explicit security retirements. Current dispositions are 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact. The PHP developer audit contributes 163 detailed `Live/KEEP` endpoint rows, 157 exact matches to the 159-route conversion catalogue, and supporting-code evidence for five models plus `MY_Controller`. | Reconcile the audit's 175-summary versus 163-detail count, missing controller metadata for `Socials`, and 24-versus-25 core-helper count; then confirm frontend callers, exact contracts, side effects, and source-file evidence for any changed disposition. | 2026-09-13 |
| Goal 2 | In progress | Isolated Python 3.13.7 service, pinned runtime/dev locks, typed settings, SQLAlchemy/PyMySQL engine, health routes, error envelope, correlation IDs, structured logging, ADRs, automated verification, successful Uvicorn smoke test, and committed authentication transaction boundaries | Nested PII redaction, remaining service transaction boundaries, approved production worker/proxy configuration, and all other business modules remain | 2026-09-08 |
| Goal 3 | In progress | Local MariaDB 12.3.3 schema-only clone; 68 table/778 column mappings; model/schema parity tests; rollback-safe write; empty Alembic baseline `7250164972b6`; schema report | Approved current-database read-only access, schema-drift/data-quality report, timezone/boolean decisions, repositories, deadlock tests, and benchmarks remain | 2026-09-08 |
| Goal 4 | In progress | Nine SQL-backed auth/recovery/verification routes; self-only access-code verification; canonical database-fact permission policy in ADR 0004; lower-role account-management hierarchy applied to approval, account state, and bounded role changes; transactional refresh-token revocation on deactivation and role changes; exact-role administrator and pending-voucher post-verification notifications; sixteen unsafe/superseded routes retired with tested GET/POST HTTP 410 tombstones; bcrypt-to-Argon2id migration; asymmetric JWTs; rotating hashed refresh tokens; finite reset/email codes; shared Redis throttling; SMTP adapter/fakes; ADR 0003 | Social login, group/owner policy application across remaining endpoints, live Redis/SMTP acceptance, trusted-proxy validation, and frontend compatibility remain | 2026-09-13 |
| Goal 5 | In progress | Registration, public city/privacy-aware welfare-zone catalogues, protected privacy-aware zone roster/self assignment, authorized transaction-safe zone/city management, bounded geography and alumni imports, class-year-filtered public voucher discovery, owned pending-vouch listing and transactional approve/deny, authentication, finite email verification, post-verification administrator/voucher notification, credential-free self/administrator profile read, allowlisted profile update with normalized avatar storage, protected profile-visibility read/write, privacy-aware approved directory/downward account listing, member approval/rejection, account activation/deactivation, bounded role changes, four-card alumni statistics, privacy-minimized birthday windows, public/protected chapter list/assignment lookup, and authenticated setup-parameter lookup are implemented. Registration and alumni import own new-account privileges server-side; imports preserve existing account authorization, credential, state, and visibility fields, validate complete bounded rosters before mutation, disclose no secrets, and commit all rows atomically. Unsafe superseded account/role mutation and definition contracts are explicitly retired. Privileged changes use current database facts, deterministic row locks, reviewed role categories, state/hierarchy gates, dedicated response fields, rollback proof, and PII-free structured audit logging; geography writes additionally serialize catalogue access, validate chapter/coordinator/reference integrity, keep city/zone chapter metadata aligned, and make bounded CSV/XLSX imports idempotent and all-or-nothing. Birthday discovery is read-only, requires current account state, preserves reviewed Lagos/leap-day semantics, and returns only the active card fields plus safe schedule metadata under explicit privacy and resource bounds. | Remaining chapter-assignment UI proof, alumni-import hidden-caller/production-data/scale/proxy-limit/concurrency/admin-workflow proof, birthday authenticated-browser/production-data/scale proof, geography-import edge/body-limit and production chapter-1/duplicate-data/concurrency proof, durable audit storage, production media persistence, live SMTP, and authenticated frontend journeys remain | 2026-09-13 |
| Goal 6 | Not started | None | None recorded | Not verified |
| Goal 7 | In progress | Announcement publishing, all three active frontend marketplace routes, and all three active frontend project routes are implemented against reviewed schema. Projects use a public bounded POST presentation feed/detail lookup plus current-database `MANAGE_CONTENT` JSON/form/multipart mutation, server-owned ownership/deletion state, row locks, explicit fields, generated bounded images, rollback/replacement/deletion cleanup, soft deletion, and draft/owner/member public redaction. Ruff, strict mypy, route-registration smoke checks, authorization-policy tests, and focused test discovery pass. | A disposable-MariaDB run is unavailable in the current checkout, so new marketplace/project database-backed tests are skipped. Exact legacy GET compatibility, marketplace manager-versus-`MANAGE_STORE` policy, full contract/browser, production object-media, hidden-caller, and cutover proof remain required. Vacancies, policy, events, and other Goal 7 routes remain unbuilt. | 2026-09-16 |
| Goal 8 | Not started | None | None recorded | Not verified |
| Goal 9 | In progress | Unsafe legacy `user_tokens` is blocked by tested GET/POST HTTP 410 responses. `GET|POST /api/get_notifications` and `POST /api/mark_notification_read` are implemented against the reviewed current schema with active-Bearer self-only ownership, bounded responses/mutations, current-account checks, database locks, account-creation boundary, cross-owner denial, rollback, and idempotency tests. | `create_notification` and push delivery remain pending: hidden/mobile callers, authorised creators, recipient rules, storage migration, provider, retry, opt-out/revocation, live-data, and production/frontend proof are required. | 2026-09-14 |
| Goal 10 | In progress | Production docs/wildcard guards, explicit database configuration, secret settings types, top-level log redaction, shared Redis authentication throttling, PII-free limit and privileged-change audit events, normalized size/type/pixel-bounded avatar re-encoding with generated filenames and rollback cleanup, sixteen executable security/supersession tombstones, Bandit pass, and dependency audit pass | Remaining host/CORS/cookie/CSRF, durable audit/retention/metrics/threat-model, production media persistence, live Redis, and trusted-proxy work | 2026-09-13 |
| Goal 11 | In progress | 242 foundation, inventory, schema, registration, public city/welfare-zone catalogue, protected zone roster/self-assignment, authorized transaction-safe zone/city management and bounded bulk geography/alumni import, voucher-discovery/ownership/decision, authentication, recovery, verification/notification, access-code, authorization-policy, member-profile/update, avatar-storage, profile-visibility, privacy-aware directory/downward account-list, member-approval, account-state/role, alumni-statistics, birthday-window/privacy/resource-bound, public/protected chapter-lookup, setup-parameter, authenticated notification-read, fake-SMTP, rate-limit, and retired-route tests pass at 93.13% coverage; reusable root-safe `scripts/verify.ps1` includes Alembic drift detection | Remaining endpoint contracts, protected-route policy tests, complete fixtures, live providers, authenticated frontend, load, and release tests remain | 2026-09-14 |
| Goal 12 | Not started | None | None recorded | Not verified |

### Progress Log

#### 2026-09-16 — Goal 7 project slice

- Re-reviewed the active frontend project service/adapter, PHP `create_project`, `manage_project`, and `get_projects` handlers, the generated `Projects`/chapter/user models, and the existing current-database authorization policy. The active client calls only the three retained POST routes; its announcement marker remains part of the description payload, so no undocumented duplicate column was introduced.
- Added public bounded `POST /api/get_projects`, authenticated `POST /api/create_project`, and authenticated `POST /api/manage_project`. Public reads expose only non-deleted active/completed/paused/ongoing presentation fields. Writes require an active, freshly loaded current-database `MANAGE_CONTENT` actor; creation derives ownership and default chapter from current database facts, update/delete locks the project, fields are allowlisted, and deletion is soft.
- JSON/form/multipart mutation supports at most six validated/re-encoded generated project images, explicit add/replace/removal semantics, and transaction-safe cleanup after late failure/replacement/deletion. Focused integration coverage proves forged/stale role claims cannot grant content access, public shaping/draft/deletion boundaries hold, client ownership/deletion inputs are ignored, and generated images roll back after synthetic database failure.
- Verification boundary: Ruff format/check, strict mypy, authorization-policy tests, and route/mount smoke checks pass. The project database integration tests are collected but skipped because `ALUMNI_TEST_DATABASE_URL` is not configured. No production database, provider, or credential was touched.

#### 2026-09-14 — Goal 7 marketplace listing slice

- Re-reviewed the active frontend service/adapter and PHP `create_listing`, `manage_listing`, and `get_listings` handlers against the generated listing/social models. The older `market`-table family remains unimplemented because the table is absent and no current caller was found.
- Added public bounded `POST /api/get_listings`, authenticated `POST /api/create_listing`, and authenticated `POST /api/manage_listing`. Public output is active/unexpired and excludes seller email. Creation derives ownership/status/featured state server-side; mutation locks current state and permits the owner or freshly loaded `MANAGE_STORE` permission. JSON and multipart input support bounded validated/re-encoded images, generated marketplace paths, social upsert, image add/replace/removal, and transaction-safe cleanup.
- Added focused integration coverage for spoofed ownership, stale JWT role denial/current database permission, public shaping, and image cleanup after a synthetic late database failure. Updated the ledger, handoff, and re-reviewed workbook; the catalogue distinguishes source/static completion from unavailable disposable-MariaDB proof.
- Verification boundary: Ruff format/check, strict mypy, authorization-policy tests, and route/mount smoke checks pass. The two marketplace database integration tests are collected but skipped because `ALUMNI_TEST_DATABASE_URL` is not configured. No production database, provider, or credential was touched.

#### 2026-09-14 — Goal 7 announcement publishing slice

- Re-reviewed the active PHP announcement handlers, active frontend service/adapter, current SQLAlchemy model and schema, existing authorization policy, and route catalogue. The PHP writes accept any JWT account and persist raw uploaded files; the FastAPI routes instead enforce active, freshly locked current-database `MANAGE_CONTENT` permission and use allowlisted request/response contracts.
- Added public bounded `GET|POST /api/get_announcements`, plus authenticated `POST /api/create_announcement` and `POST /api/manage_announcement`. Feed output contains presentation fields only; writes validate chapter references, lock actor/target rows, use one transaction, rate limit, bound request/page work, and support safe generated/re-encoded JPEG/PNG/GIF/WEBP image storage with rollback cleanup. The existing frontend's `image`/`images` aliases and POST feed request remain supported.
- Updated the re-reviewed workbook's three announcement records. The legacy PHP/API-key/JWT read gate is deliberately not copied because the current homepage/frontend consumes the feed publicly; write authority remains server-derived from current database facts.
- Verification boundary: `.venv` prefix guard, Ruff format/check, and strict mypy pass. Two focused database integration tests are collected but skipped because no disposable MariaDB URL is configured in this resumed checkout. No production database, provider, or credential was touched.

#### 2026-09-14 — Authenticated notification read-state slice

- Re-reviewed `create_notification`, `get_notifications`, and `mark_notification_read` against the active PHP controller, backup and old copies, current frontend source/build artifacts, generated model, reviewed SQL schema, inventory, authorization policy, and workbook. No current source or built-frontend caller was found. The actual schema has per-recipient `notifications.user_id` and `is_read`; PHP instead references absent `target_user_id`, `notifications_read`, `category`, `title`, and `push_tokens` storage.
- Added bounded `GET|POST /api/get_notifications` and `POST /api/mark_notification_read`. They derive the recipient exclusively from the active Bearer principal, recheck current account state, preserve the account-creation cutoff in application UTC, shape responses, cap pages and mark-all work, lock rows before mutation, and log no message content or credential material. Caller-supplied `user_id` is ignored.
- Left `create_notification` and push delivery unexposed. The missing schema and unproven caller/provider/recipient/retry/opt-out contract make a compatibility copy unsafe. The route catalogue records two built notification-read routes and one pending design route.
- Verification: full disposable-MariaDB gate passed with 242 tests at 93.13% coverage, Ruff format/check, strict mypy across 64 source files, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities. Focused tests prove cross-owner denial, account-cutoff filtering, stale-account denial, idempotent read state, bounded mark-all, and transaction rollback. The database process was stopped and port 3307 cleared afterward.

#### 2026-09-13 — Destructive staff and unsafe push-token routes retired

- Re-reviewed `deactivate_staff` and `user_tokens` against the active PHP controller/model, backup and old-controller copies, current frontend source and build artifacts, the reviewed SQL snapshot, authorization policy, inventory, and workbook. No current source or built-frontend caller was found for either route.
- Added tested GET/POST HTTP 410 tombstones. The destructive staff path cannot bypass the authorized state-only `/api/manage_user_account` replacement or reactivate delete-after-deactivate behavior. The push path cannot bind a token to a caller-selected account, log/return token material, or silently depend on the absent reviewed `push_tokens` table.
- Regenerated the 548-record inventory. Current dispositions are 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact. The re-reviewed workbook retains 159 catalogue routes, now with 148 direct conversions and 10 consolidated replacements; member administration has no route still marked to build, but hidden-caller and production cutover proof remain.
- Workbook verification passed exact changed-cell inspection, seven targeted renders, artifact-tool formula/error inspection, `openpyxl` read-only reload, OpenXML SDK validation with zero errors, and read-only Excel COM full recalculation/open with all nine sheets and 159 route rows intact.
- Full verification passed against the disposable MariaDB schema: 239 tests at 93.04% coverage, Ruff format/check, strict mypy across 60 source files, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities. The database process was stopped and port 3307 was clear afterward.

#### 2026-09-13 — Bounded, privilege-safe alumni roster import

- Re-reviewed `import_alumni` against the active PHP controller, reusable-key helper, generated schema, current frontend source and build, tests, and all four workbook views. No current source or built-frontend caller was found, and the workbook's prior `role_check` claim was incorrect: PHP authorizes only a reusable application key.
- Added JSON and multipart CSV/XLSX ingestion with bearer-first parsing, a 2 MiB/500-row limit, archive/formula/header/field/value/duplicate checks, enabled chapter and exact chapter-city validation, current database account-management/downward-target authorization, and one deterministic all-or-nothing transaction. Existing account credentials, privileges, access/user codes, state, and visibility remain authoritative. New members receive fixed alumni/non-coordinator privileges, no known password, member-group/category rows, and a private profile; responses expose no email or secret-bearing values.
- Intentionally retired PHP's caller-selected shared password, client coordinator grant, forced activation/approval/verification of existing accounts, source timestamp write, unbounded partial writes, binary XLS, and access/user-code disclosure. New imported members must use the secure password reset flow before password login.
- Verification: the complete `.venv` gate passed with 235 tests at 93.03% coverage against the disposable MariaDB schema, Ruff format/check, strict mypy across 60 source files, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities. Focused SQL tests prove current-role authorization despite stale/forged claims, exact existing-account preservation, secure new-account state, row outcomes, idempotency, ambiguous-membership rejection, and rollback after a late profile insert failure. Hidden callers, approved production data compatibility, scale, proxy limits, representative concurrency, and administrator workflow remain cutover gates.

#### 2026-09-12 — Privacy-minimized birthday discovery

- Re-reviewed `get_birthdays` against the active PHP controller, its Africa/Lagos configuration, current frontend endpoint/service/adapter/types/page rendering, generated schema, and the shared visibility contract. The current frontend actively makes a bodyless POST and consumes `user_id`, `fullname`, `name_in_school`, `avatar`, and `class_label`; PHP documents GET with query filters but returned exact DOB, age, contact-adjacent and unrelated profile fields that the caller does not use.
- Implemented query-only GET plus bodyless POST compatibility with current active-Bearer revalidation and one per-user rate-limit bucket. Today/week/month/upcoming scopes preserve the legacy defaults/clamps and February 29 observance. Active members remain eligible regardless of approval/email verification as in PHP; missing `birth_date` visibility remains public, explicit false opts out, malformed JSON now fails private, global directory hiding remains deliberately independent, and avatar visibility is newly enforced.
- The dedicated response retains the five active card fields plus occurrence date, days-until, today/self flags, and a bounded message. It omits exact DOB, age, birth year, email, phone, user code, department, house, city, nickname, component names, and raw visibility. Results sort by next occurrence/name/ID, output is capped at 200, and candidate scanning fails safely above 10,000 rows. The route is read-only, so no transaction/rollback behavior exists to test.
- Verification: the complete `.venv` gate passed with 221 tests at 94.50% coverage, Ruff format/check, strict mypy across 58 source files, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. SQL-backed tests prove GET/POST parity, current-account denial, all scopes, leap-day shifting, sorting/limits, missing/explicit/malformed privacy, global visibility independence, unverified/unapproved legacy eligibility, avatar masking, minimum response keys, and candidate-bound failure. Authenticated browser runtime, hidden callers, approved production privacy/data quality, and production scale remain cutover gates.

#### 2026-09-12 — Bounded transaction-safe geography import

- Re-reviewed `upload_zones_cities` against active and old PHP controllers, current source and built-frontend callers, generated schema, current authorization policy, and the single-row geography contracts. No current caller was found. PHP trusted only a reusable key, accepted CSV/XLS/XLSX with unbounded parsing, silently skipped malformed rows, leaked parser exceptions, hard-coded new zones to chapter 1, updated existing city zones without chapter alignment, and could commit partial results because it had no transaction or duplicate-ambiguity handling.
- Implemented exactly-one-file multipart POST for UTF-8 CSV and XLSX with an active Bearer/current-account `MANAGE_ZONES` check and per-user throttling before parsing. The route ignores the legacy key, limits files to 2 MiB and 2,000 data rows, requires exactly `zone` and `city` headers, bounds multipart reads plus XLSX archive entries/count/uncompressed size/compression ratio, rejects formulas/control characters/oversized cells/duplicate input cities, and returns only bounded errors. Legacy binary `.xls` requires conversion instead of adding an unsafe parser dependency.
- Prevalidation fails closed on ambiguous normalized catalogue rows and invalid chapter references. New zones retain the reviewed chapter-1 default only when chapter 1 exists; inserted and moved cities inherit the selected zone chapter. The import takes the same actor/catalogue lock order as single-row mutations, writes in one transaction, reports honest inserted/updated counts, is idempotent on repeat, and logs only bounded counts after commit. SQL-backed tests prove stale/forged-role denial, current-role acceptance, CSV/XLSX contracts, exact summaries, repeat idempotency, ambiguity failure before writes, and rollback after a late city insert.
- Verification: the complete `.venv` gate passed with 217 tests at 94.52% coverage, Ruff format/check, strict mypy across 58 source files, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. The disposable server was stopped after verification. Production chapter-1/duplicate data, edge request-size enforcement, representative concurrent imports, hidden traffic, and the administrator UI remain cutover gates.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view. The saved workbook passed exact changed-cell assertions, a 30-built-route recount, four-view visual review, artifact-tool structure/formula/error inspection, `openpyxl` load checks, OpenXML SDK validation with zero errors, and read-only Excel COM full recalculation/open with nine sheets, four formulas, no formula errors, 159 routes, 150 direct conversions, and 8 consolidated replacements intact.

#### 2026-09-12 — Authorized zone and city catalogue mutation

- Re-reviewed `manage_zone` and `manage_city` against the active/old PHP controllers, current frontend/source search, generated schema, sanitized SQL, and the current authorization policy. No current source or built-frontend caller was found. PHP checked only the role string `admin`, accepted JSON/form mutations, had no transaction or reference protection, defaulted chapters to `1`, and relied on a schema with no foreign keys for city-to-zone, zone coordinator, or free-text `users.city`/`user_profiles.city` references.
- Implemented bounded JSON/form POST create/update/delete routes requiring a current active database account with `MANAGE_ZONES`; stale/forged JWT role claims and the legacy shared key grant nothing. Both routes take the same zone-then-city catalogue locks, normalize whitespace/case for deterministic duplicate conflicts, validate chapters, reject no-op updates, and emit only bounded operation metadata after commit.
- Zone mutation validates an active, approved, verified same-chapter coordinator, supports explicit coordinator clearing, aligns every child city's chapter atomically when a zone moves, and blocks deletion while cities reference the zone. City mutation requires a real zone and matching chapter, aligns chapter metadata automatically on moves, and blocks rename/delete while member or profile free-text city values still reference the old name. Four SQL-backed integration tests cover current-role authorization, JSON/form contracts, duplicate/chapter/coordinator/reference conflicts, move/delete outcomes, and rollback on late repository failures.
- Verification: the complete `.venv` gate passed with 204 tests at 95.30% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. Production data, hidden callers, representative concurrent mutations, and an administrator UI journey remain cutover gates.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for both mutation routes. The saved workbook passed exact changed-cell checks, a 29-built-route recount, visual review, artifact-tool formula/error inspection, OpenXML SDK validation with zero errors, and read-only Excel COM full recalculation/open with nine sheets, four formulas, no formula errors, 159 routes, 150 direct conversions, and 8 consolidated replacements intact.

#### 2026-09-12 — Protected zone roster and self-zone assignment

- Re-reviewed `get_users_by_zone` and `get_my_zone` against the active/old PHP controllers, current frontend/source search, generated schema, sanitized SQL, and the established directory/coordinator visibility rules. No current source or built-frontend caller was found. PHP trusted only the reusable application key for a broad zone roster containing email, address, birth date, employment, role, and account state; its zone-name branch resolved a zone but then queried members using the still-zero input ID. The self route was Bearer-protected but exposed coordinator email and used nondeterministic duplicate city matching.
- Implemented protected GET/POST zone-member discovery with bounded ID/exact-name input, ID precedence, current active-account recheck, per-user throttling, deterministic resolution, and 100-row maximum pagination. Results use a dedicated minimal roster: only active/approved/verified members are candidates; global hiding or private city removes another member so zone membership is not inferred; the owner retains self visibility; phone/avatar follow stored visibility; malformed JSON fails private; and broad PII, role/account metadata, user codes, and credentials are never selected.
- Implemented protected GET/POST self-zone lookup with caller-body spoof resistance, trimmed case-insensitive city matching, deterministic oldest-row selection, and HTTP 200 `Not Yet Available` compatibility for missing/unknown/orphan mappings. Both routes reuse the eligible privacy-aware coordinator projection with no email and explicit null when unavailable. Sanitized-SQL/OpenAPI tests cover shared-key denial, current-state authorization, fixed name lookup, ID precedence, pagination, eligibility/privacy exclusions, owner/default/malformed visibility, duplicate mappings, no-city/orphan outcomes, and PII exclusion.
- Verification: the complete `.venv` gate passed with 200 tests at 96.08% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. No frontend files changed in this slice because no current caller exists; production data, hidden-client, and browser proof remain cutover gates.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for both zone-membership routes. The saved workbook passed exact 47-cell/structure/formula assertions, OpenXML SDK validation with zero errors after restoring the source's valid font ordering, and read-only Excel COM full recalculation/open with nine sheets, four formulas, no formula errors, 159 routes, 150 direct conversions, 8 consolidated replacements, 27 built routes, and 12 security retirements intact.

#### 2026-09-12 — Public city and privacy-aware welfare-zone catalogues

- Re-reviewed `get_cities` and `get_zones` against the active/old PHP controllers, current registration/profile/welfare callers and adapters, route accessibility, generated schema, source SQL, and the established profile-visibility policy. Both active callers use POST even though comments describe GET; registration needs cities before authentication and the welfare page is publicly routed. PHP trusted a reusable client key and exposed coordinator email/phone/avatar without account eligibility or profile-visibility checks.
- Implemented public peer-rate-limited GET/POST routes with no reusable-key authority. Cities return only ID/name/chapter/source-zone ID and optional joined zone name in deterministic order, retaining orphan source IDs. Zones return nested city ID/name rows and only eligible active/approved/verified/non-hidden coordinators; phone/avatar obey stored visibility, malformed JSON fails private, missing keys retain the reviewed default, and email/role/department are never selected.
- Updated the welfare frontend contract to remove coordinator email and compare current/coordinator member IDs for self-message prevention. Contract and sanitized-SQL tests cover both methods, exact field schemas, ordering, nested cities, orphan joins, explicit null coordinators, eligibility gates, visibility defaults/overrides, malformed-privacy fail-closed behavior, and PII exclusion.
- Verification: the complete `.venv` gate passed with 197 tests at 96.10% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. Targeted Prettier and the frontend production build passed; Vite retains its existing stale-Browserslist and large-bundle warnings.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for both geography routes. The saved workbook passed exact 45-cell/structure/formula assertions, OpenXML SDK validation with zero errors after restoring the source's valid font ordering, and read-only Excel COM full recalculation/open with nine sheets, four formulas, no formula errors, 159 routes, 150 direct conversions, 8 consolidated replacements, 25 built routes, and 12 security retirements intact.

#### 2026-09-12 — Owned voucher discovery and decision workflow

- Re-reviewed `get_vouchers`, `voucher_pending`, and `vouch_action` against the active PHP controller, current frontend callers, generated schema, authorization policy, and mail flow. PHP exposed all voucher contact/role/profile fields through a reusable application key, returned broad pending data after a commented-out role guard, and performed approval plus mail without one transaction or verified-email/replay protection.
- Implemented public GET/POST voucher discovery with optional class-year filtering and only ID/name/year/chapter output. Pending GET/POST and decision POST require a Bearer principal plus current active-voucher state; rows are exact-owner and pending-only, cross-owner IDs are concealed, and approval/denial use deterministic locks, verified/unapproved guards, explicit responses, PII-free logging, and one database transaction. Mail is best-effort after commit and approval manager recipients use exact reviewed active roles.
- Updated registration to query vouchers for the selected graduation year and display name/class labels instead of public email addresses. Database-backed tests cover public redaction/order/filtering, method parity, stale/non-voucher/cross-owner denial, approve/deny/replay/eligibility states, late-SQL rollback, and committed state during mail failure.
- Verification: the complete `.venv` gate passed with 194 tests at 95.89% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. Targeted Prettier and the frontend production build passed; Vite retains its existing stale-Browserslist and large-bundle warnings.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for all three voucher routes. The saved workbook passed exact changed-cell/structure/formula assertions, OpenXML SDK validation with zero errors after restoring the source's valid 14-font ordering, and read-only Excel COM full recalculation/open with nine sheets, 159 routes, 150 direct conversions, 8 consolidated replacements, 23 built routes, and 12 security retirements intact.

#### 2026-09-11 — Transactional public member registration

- Re-reviewed `register` against the active PHP controller, sanitized schema, current frontend caller/adapter, city catalogue, mail/upload integrations, and authorization policy. The PHP route accepted client privilege fields, a reusable application key, weakly bounded input, and raw request/response logging; the frontend also hard-coded chapter `1` despite city rows carrying chapter IDs.
- Implemented a public peer- and identity-rate-limited JSON/form/multipart route with bounded validation, Argon2id hashing, server-owned `alumni`/non-coordinator/current-year state, enabled chapter/city and current active voucher checks, deterministic legacy-compatible member codes, and one transaction for user, member group, category, private profile, optional pending vouch, finite verification code, and avatar metadata. Late database failure removes the normalized file. Verification mail runs after commit; provider failure preserves the account and returns resend guidance.
- Updated the frontend adapter/service/page to derive `chapter_id` from the selected live city row and reject submission if it is absent. Client-supplied role/coordinator/year fields have no FastAPI contract effect.
- Verification: the complete `.venv` gate passed with 187 tests at 95.97% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema. Targeted Prettier and the frontend production build also passed; repository-wide Prettier remains a 477-file baseline failure, and Vite retains its existing stale-Browserslist and large-bundle warnings.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view while preserving all formulas, sheet structure, and counts. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors, and a separate read-only Excel COM full recalculation/open with all nine sheets intact, 150 direct conversions, 8 consolidated replacements, 159 catalogue routes, and 12 explicit security retirements.

#### 2026-09-11 — Dynamic role-definition routes security-retired

- Re-reviewed `create_role`, `manage_role`, and `get_roles` together against the active PHP controller, all legacy copies, current source and built frontend, developer audit, reviewed authorization policy, generated model, and SQL snapshot. No current caller or role row was found; `roles.user_id` is mandatory, `role_name` is globally unique, and `users.user_role` is the only FastAPI authorization source.
- Security-retired all three routes with tested GET/POST HTTP 410 guidance to the fixed `/api/manage_user_account` role contract. The reusable-key arbitrary create/update/delete and broad row-list behavior is not copied; `create_role` also omits a required database column, `manage_role` lacks target/existence guards, and `get_roles` may expose user metadata plus a malformed no-data status.
- Regenerated the 548-record inventory: 150 candidate-retain, 190 candidate-retire, 30 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 12 retired-security-risk, and 103 retire-noncontroller-artifact. The six backup/old-controller copies are containment targets and three standalone root copies remain artifact retirements.
- Verification: the complete `.venv` gate passed with 180 tests at 96.63% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema.
- Updated the route catalogue, grouped view, retirement summary/detail, confirmation queue, and developer-audit view. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 existing font-order records, and a normal read-only Excel COM full recalculation/open with all nine sheets intact, 150 direct conversions, 8 consolidated replacements, 159 catalogue routes, and 12 explicit security retirements.

#### 2026-09-11 — Authenticated bounded setup-parameter lookup

- Re-reviewed `get_setup_parameters` against the active PHP controller, old-controller copy, current source and built frontend, the developer audit, the sanitized model, and the value-bearing SQL snapshot without exposing values. The table currently has four generic names, but the route accepts any present or future `setup_name`; no current route caller was found.
- Implemented POST JSON/form lookup with a required trimmed 100-character `action_type`, active Bearer principal plus current database state recheck, shared per-user rate limiting, explicit setup ID/name/raw-value/trimmed-values output, deterministic oldest-row selection, and real HTTP 400/404 results. The legacy reusable `X-API-Key` is ignored rather than copied.
- Sanitized-SQL coverage proves case-insensitive lookup under the reviewed collation, JSON/form parity, duplicate-row determinism, bounded fields, empty comma-item compaction while retaining the valid string `0`, unknown-name handling, and stale-token denial after actor deactivation.
- Verification: the complete `.venv` gate passed with 174 tests at 96.63% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view while preserving the old-controller retirement candidate. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 existing font-order records, and a normal read-only Excel COM full recalculation/open with all nine sheets intact, 153 direct conversions, 5 consolidated replacements, and 159 catalogue routes.

#### 2026-09-11 — Public chapter list and protected assignment lookup

- Re-reviewed `get_chapters` against the active PHP controller, legacy copies, current source and built frontend, the developer audit, and the sanitized schema. At that checkpoint PHP documented GET or POST, no current route caller was found, registration hard-coded chapter `1`, and a separate frontend module exposed a static Lagos-only chapter list; the later registration slice removed the hard-code.
- Implemented GET and POST with one split contract: omitting `user_id` provides rate-limited registration-safe enabled chapter metadata without the legacy application token; supplying `user_id` requires an active Bearer principal and permits only self or a strictly lower account-management target using current database facts. Unknown/forged roles fail closed.
- Sanitized-SQL coverage proves enabled-only alphabetical public output, GET/POST parity, bounded fields, deterministic oldest-category assignment selection, self/downward authorization, stale-actor denial, not-found handling, no PII, and the PHP-compatible HTTP 200 `chapter: null` unassigned result.
- Verification: the complete `.venv` gate passed with 172 tests at 96.59% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema.
- Updated the route catalogue, grouped view, confirmation queue, developer-audit view, and linked duplicate trace while preserving backup/old-controller retirement candidates. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 existing font-order records, and a normal read-only Excel COM full recalculation/open with all nine sheets intact, 153 direct conversions, 5 consolidated replacements, and 159 catalogue routes.

#### 2026-09-11 — Bounded alumni directory statistics

- Re-reviewed `get_alumni_stats` against the active PHP controller, all legacy copies, current source and built frontend, the developer audit, and the sanitized schema. PHP documents GET or POST and returns only four aggregate counts; no current frontend caller was found, while developer `Live/KEEP` remains reachability evidence.
- Implemented both GET and POST with one active-principal rate-limit bucket and a current-database actor-state recheck. The legacy shared application token is neither accepted nor returned. The response is an explicit four-integer schema with no member rows or identifiers.
- Sanitized-SQL coverage proves the exact PHP definitions: active approved `alumni` users, distinct non-null `alumni_category.year` values, enabled chapters, and distinct non-empty departments among approved users. It also proves GET/POST parity, exclusion cases, no PII in output, ignored legacy body credentials, and stale-token denial after deactivation.
- Verification: the complete `.venv` gate passed with 169 tests at 96.54% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema.
- Updated the route catalogue, grouped view, confirmation queue, developer-audit view, and linked duplicate trace. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 existing font-order records, and a normal read-only Excel COM full recalculation/open with all nine sheets intact, 153 direct conversions, 5 consolidated replacements, and 159 catalogue routes.

#### 2026-09-11 — Bounded role changes and standalone role-route retirements

- Reconciled the active frontend role-management contract with `users.user_role`, the separate legacy Ion Auth groups, and the globally unique free-form `roles.role_name` table. The frontend changes roles only through `/api/manage_user_account`; it maps legacy `admin` to `super admin` and uses the fixed approval, content, event, finance, and storekeeper administrator categories.
- Implemented exactly-one state-or-role mutation on the shared route. Role authorization is decided from locked current-database actor/target facts, unknown values fail closed, self changes are forbidden, administrative promotion requires active/approved/verified state, and every successful role change revokes refresh sessions in the same transaction. Responses are bounded, audit fields exclude PII, and role-only changes do not send account-state mail.
- Security-retired `/api/update_user_role` and `/api/manage_user_roles` with tested GET/POST HTTP 410 guidance. Their arbitrary/free-form role behavior is not copied; chapter edits remain in `/api/update_profile`, and free-form `roles` rows remain non-authoritative metadata. The regenerated 548-record inventory now has 153 candidate-retain, 196 candidate-retire, 24 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 9 retired-security-risk, and 103 retire-noncontroller-artifact records.
- Verification: the complete `.venv` gate passed with 167 tests at 96.50% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the disposable MariaDB schema.
- Updated the route catalogue, grouped view, retirement summary/detail, confirmation queue, and developer-audit view. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 existing font-order records, and a normal read-only Excel COM full recalculation/open with all nine sheets intact, 153 direct conversions, 5 consolidated replacements, and 159 catalogue routes.

#### 2026-09-11 — Unsafe legacy account-update route retired

- Re-reviewed `update_user_account` against the active PHP controller, current frontend source and built output, Git history, the sanitized schema, the developer audit, and the implemented profile contract. The developer audit's `Live/KEEP` label proves the method exists but does not establish safe behavior or a current caller.
- The PHP route accepts any bearer-authenticated caller's chosen `user_id`, records the raw body and token in error logs, and passes arbitrary legacy field names directly to `users`. Seven of its eleven text field names do not exist in the reviewed table; the valid name, phone, address, and avatar purpose is handled by the bounded `/api/update_profile` contract.
- Added deprecated GET/POST HTTP 410 tombstones pointing clients to `/api/update_profile`; no legacy write, upload, or secret-bearing log path executes. Regenerated the 548-record inventory: 155 candidate-retain, 199 candidate-retire, 21 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 7 retired-security-risk, and 103 retire-noncontroller-artifact.
- Verification: the complete `.venv` gate passed with 154 tests at 96.54% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the empty disposable MariaDB schema.
- Updated the route catalogue, grouped view, retirement summary/detail, confirmation queue, and developer-audit view. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 font-order records, and a normal read-only Excel COM recalculation/open with all nine sheets intact and 155 direct conversions plus 3 consolidated replacements. Hidden production callers and any intended proof-document workflow remain explicit confirmation items before cutover.

#### 2026-09-10 — Allowlisted profile update and normalized avatar storage

- Implemented rate-limited `POST /api/update_profile` for the active frontend's JSON, flat multipart, and nested `profile[field]` contracts. Dedicated schemas accept only reviewed user/profile fields, preserve omitted values, support intentional clears, rebuild full names safely, validate chapter references, and return a bounded credential-free profile projection.
- Self-service is the default. Cross-user updates require freshly loaded account-management permission and a strictly lower reviewed target role; forged or stale JWT claims, inactive actors, peers, higher roles, and unrecognized roles fail closed. Unlike the PHP route, profile editing never reactivates an inactive target.
- Avatar uploads are capped at 5 MB, decoded and format/pixel validated, re-encoded as JPEG/PNG/GIF to strip metadata, assigned generated server filenames, and exposed only through the normalized profile-image mount. Database updates and attachment metadata share one transaction; injected failures prove rollback plus removal of a newly stored file.
- Verification: the complete `.venv` gate passed with 152 tests at 96.54% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the empty disposable MariaDB schema.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for the profile route. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic repair of 14 font-order records, and a normal read-only Excel COM open with all nine sheets intact. Authenticated frontend behavior, approved production database comparison, and durable production media storage remain separate cutover gates.

#### 2026-09-10 — Privacy-aware member directory and account list

- Implemented rate-limited `POST /api/get_users_by_action` with a bounded request contract for reviewed action names, optional exact member/year/search filters, and page/limit controls capped at 100. Unknown actions now return HTTP 400 instead of falling through to a broad verified-user query.
- Approved-directory mode requires an active current database account; returns only approved, active, verified members; excludes another member's globally hidden profile; and applies the 15-field visibility map before serialization. Malformed/non-object JSON hides every controlled field, missing keys retain the PHP-compatible public default, and owners can still see their own hidden/private values.
- Directory output never selects or returns email, passwords, selectors, reset/verification/device tokens, IP addresses, or other credential state. It retains the frontend's `users` envelope and explicit profile shape while omitting private values entirely.
- Pending/all-user modes require current account-management permission and return only verified accounts with reviewed roles strictly below the actor. JWT role claims cannot expand access; peers, higher roles, unrecognized roles, the actor, and unverified accounts are excluded. The administrator projection contains only the fields consumed by the active account-management adapter.
- Verification: the complete `.venv` gate passed with 142 tests at 97.16% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, and Bandit against the empty disposable MariaDB schema. A retry of `pip-audit` with a workspace-local cache completed with no known vulnerabilities after the first external PyPI response was malformed.
- Updated the route catalogue, grouped view, confirmation queue, and developer-audit view for the listing route. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic font-order repair, and a normal read-only Excel COM open with all nine sheets intact.

#### 2026-09-10 — Protected profile visibility read and update

- Implemented rate-limited `POST /api/get_profile_visibility` and `POST /api/update_profile_visibility` using the active frontend payload names and the PHP route's 15 controllable fields. Dedicated request/response schemas ignore the legacy body token, reject unknown-only updates, and always return a complete public/private visibility map.
- Self-service is the default. Cross-user reads and writes require freshly loaded account-management permission and a strictly lower reviewed target role; forged JWT claims, inactive actors, peers, higher roles, and unrecognized roles fail closed.
- Updates lock current actor/target facts and the target profile, merge only allowlisted fields, and safely create a missing legacy profile row with required empty social values. Tests prove existing fields survive partial updates and injected post-write failures roll back the entire change.
- Malformed or non-object stored visibility JSON resolves all controllable fields to private rather than exposing member data. Missing keys retain the PHP-compatible public default. Applying this state to a bounded `/api/get_users_by_action` directory projection remains the next prerequisite-backed slice.
- Verification: the complete `.venv` gate passed with 136 tests at 97.34% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the empty disposable MariaDB schema.
- Updated both visibility routes across the route catalogue, grouped view, confirmation queue, and developer-audit view. The saved workbook passed openpyxl reload assertions, OpenXML SDK validation with zero errors after deterministic font-order repair, and a normal read-only Excel COM open with all nine sheets intact.

#### 2026-09-10 — Protected account activation and deactivation

- Implemented the activation/deactivation subset of rate-limited `POST /api/manage_user_account` after confirming active frontend callers for administrator activation/deactivation and self-deactivation. The dedicated response preserves the seven-field PHP/frontend account projection and excludes credentials.
- Current locked database facts—not JWT role claims—govern cross-user actions. Any active member may deactivate only self; managers and administrators may act only down the reviewed hierarchy; self-activation, peer/higher/unrecognized targets, missing users, duplicate state, and reactivation before approval or email verification fail closed.
- Deactivation revokes all active refresh tokens in the same transaction. Tests prove state and revocation roll back after an injected repository failure; structured audit and activity email run after commit, so provider failure does not revert a valid account change.
- The route returns `account_role_change_unavailable` for `user_role` payloads. The frontend currently offers six free-form categories while PHP also has separate user-role and system-role routes; mutation remains pending an approved canonical mapping and escalation-safe grant/revoke rules.
- Verification: the complete `.venv` gate passed with 131 tests at 97.69% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the empty disposable MariaDB schema.

#### 2026-09-10 — Protected member approval and rejection

- Implemented rate-limited `POST /api/approve_user` in the member route/repository/service/schema layers after confirming the active frontend calls it with `user_id`, `action`, and optional `reject_reason`. The response preserves the frontend/PHP message contract and seven allowlisted user fields without loading or returning credentials.
- Replaced PHP authorization by JWT role text with freshly locked database facts. Managers may decide member accounts, administrators may also decide manager accounts, and super administrators may decide lower reviewed roles; self-action, equal/higher/unrecognized target roles, unverified email, duplicate decisions, and rejecting already approved accounts fail closed. The role and state restrictions are intentional security differences recorded in ADR 0004.
- Actor and target rows lock in deterministic identifier order. Tests prove the write rolls back after an injected post-update failure; account-status email runs only after commit, and provider failure emits a PII-free event without reverting the approval decision. A durable audit table remains pending because no suitable legacy table exists and compatibility work does not add an unreviewed schema.
- Source-level frontend mapping is confirmed in `frontend/src/features/admin/api/adminDashboardApi.ts`; authenticated browser behavior, live SMTP/Redis, production database compatibility, deployment, and cutover remain unverified.
- Verification: the complete `.venv` gate passed with 125 tests at 98.11% coverage, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities against the empty disposable MariaDB schema.

#### 2026-09-10 — PHP developer audit reconciliation

- Reviewed `output/spreadsheet/alumniapp_code_audit.xlsx` against the migration inventory and updated `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx`. The detailed audit has 163 endpoint rows marked `Live/KEEP`; 157 exactly match the 159 conversion-catalogue routes. `Pwa /pwa/offline` and `my404 /my404/index` remain outside the developer's endpoint list and require separate confirmation.
- Recorded the audit's unresolved evidence gaps instead of silently changing scope: its Summary says 175 live endpoints, its Controllers sheet lists six rows while its Summary claims seven `KEEP` controllers, `Socials` endpoint rows lack a controller record, and the Core sheet says 24 functions while Core functions lists 25.
- Kept the existing security and consolidation decisions. The six security-sensitive methods remain HTTP 410 tombstones or redesign candidates, while `verify_otp` and `resend_otp` remain consolidated into the newer email-verification flow. A shared name/URL with active `Api` code does not reclassify the standalone root `Api.php` artifact as an active controller.
- Added developer-audit evidence, re-review conclusions, next checks, controller/function traceability, and novice-friendly explanations to the route catalogue, retirement, confirmation, and supporting-code workbook sheets. OpenXML validation and an Excel open check passed; this documentation update adds no new endpoint implementation or frontend-parity proof.

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
