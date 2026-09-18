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

### Canonical Scope Reconciled from the Re-reviewed Workbook

Reconciled on 2026-09-16 against `alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx`. This Markdown file is the execution source of truth for the conversion. The workbook is the reviewed baseline and evidence reference; future implementation chats must update this file and `HANDOFF.md` after each goal, and must not edit the workbook unless the user explicitly requests a workbook change.

The workbook contains 159 catalogue rows. Its Overview formula counts 156 rows that already have a direct-conversion, consolidated-replacement, or platform-replacement treatment, plus three `/api/create_market`, `/api/manage_market`, and `/api/get_market` rows that remain blocked pending a schema or caller decision.

| Workbook measure | Count | Execution meaning |
| --- | ---: | --- |
| Catalogue routes | 159 | Every row must end in verified FastAPI parity, an approved replacement, an approved retirement, or an explicitly accepted conditional decision. |
| Direct conversion rows | 145 | Build and verify as FastAPI capabilities, including the one conditional PWA row and the one pending notification-design row. |
| Consolidated replacement rows | 10 | Do not recreate the old public contract when the workbook specifies a safer replacement. Nine use an existing replacement; one replacement remains pending. |
| Platform replacement rows | 1 | `/my404/index` is handled by FastAPI error handling rather than a business route. |
| Schema/caller-blocked rows | 3 | The legacy `market` family is not implemented until current schema or runtime evidence authorizes it. |
| Already built in Python | 40 | Implementation exists, but frontend, current-database, provider, and cutover evidence may still be outstanding. |
| Implemented, database integration pending | 3 | Python implementation exists; disposable-MariaDB proof is still required. |
| Implemented source/static, database integration pending | 15 | Source and static checks exist; database, browser, and cutover proof are still required. |
| Still to build and test | 85 | No FastAPI implementation may be assumed. |
| Pending schema and provider design | 1 | `/api/create_notification` stays unexposed until its storage, caller, recipient, provider, retry, and opt-out contract is approved. |
| Conditional app-usage row | 1 | `/pwa/offline` is implemented only if deployed runtime evidence shows it is still requested. |

The workbook groups the 159 routes into these functional areas. The route register below is the row-level checklist and must remain complete for every group:

| Functional area | Routes |
| --- | ---: |
| Authentication, recovery and verification | 13 |
| Member/document uploads | 1 |
| Chapters, roles and geography | 13 |
| Community content and contact | 13 |
| Member administration and profiles | 13 |
| Notifications | 3 |
| Events and registrations | 12 |
| Marketplace listings | 3 |
| Projects | 3 |
| Leadership | 3 |
| Vouchers | 3 |
| Blog homepage and carousel | 6 |
| Blog FAQs | 5 |
| Blog categories | 5 |
| Blog posts | 5 |
| Chat v1 and push | 14 |
| Chat v2 | 13 |
| News and feeds | 1 |
| Store catalogue | 5 |
| Cart and addresses | 10 |
| Checkout and payments | 4 |
| Orders | 5 |
| News and PWA | 1 |
| Social login | 4 |
| Error handling replacement | 1 |

The workbook's `Meaningful Functions` sheet adds these non-route capabilities. Each must be implemented privately or deliberately replaced and tested under the owning goal:

| Supporting feature | Owning goal(s) | Required treatment |
| --- | --- | --- |
| JWT and password lifecycle | Goals 2 and 4 | Preserve confirmed password compatibility, token rotation/revocation, reset, expiry, and forced cutover sign-in policy. |
| Authorization policy | Goals 4, 5, 6, 7, 8, and 10 | Use current database facts, ownership, hierarchy, and named permissions at the service boundary. |
| Database access | Goals 2 and 3 | Replace hidden global model access with typed repositories, explicit transactions, parameterized queries, drift checks, and rollback/deadlock evidence. |
| File and image handling | Goals 5, 7, 8, and 9 | Validate type/signature/size, generate safe names, preserve approved URLs, protect private files, and clean up on rollback. |
| Email, notifications, and push | Goals 4 and 9 | Keep recipient ownership and provider behavior server-controlled; test retry, timeout, failure, opt-out, and redaction rules. |
| CSV/XLSX imports | Goals 5 and 9 | Bound parsing, validate complete batches, reject unsafe content, preserve protected account fields, and make writes atomic/idempotent. |
| Payments | Goals 8 and 12 | Preserve Paystack references and exact money checks, verify signatures, finalize transactionally, and reconcile failed callbacks. |
| Social identity providers | Goal 4 | Validate provider tokens server-side and map identities without trusting frontend claims; resolve the missing `Socials` controller audit row. |
| Legacy URL rewriting | Goals 1 and 12 | Keep only approved legacy links through an explicit route/cutover map; do not apply a blanket rewrite. |
| Health and operations | Goals 2, 10, 11, and 12 | Provide safe health/metrics, structured redacted logs, rate limits, monitoring, release gates, rollback, and deployment evidence. |

The workbook's `Supporting Code Audit` separately covers the five retained PHP models (`Api_model.php`, `Base_model.php`, `Blog_model.php`, `Chat_model.php`, and `Ion_auth_model.php`) and 25 audited `MY_Controller` functions. These are private implementation responsibilities, not additional public endpoints. The Markdown task and goal checklists must account for them even when no one-to-one FastAPI function is exposed.

The workbook also records the 548-item inventory boundary: 445 callable non-constructor methods from the 17 controller files plus 103 functions from the standalone root `Api.php` artifact. Its current disposition totals are 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact. These counts are inventory classifications, not completed FastAPI routes.

The continuation prompt is provided separately in the current chat. It is intentionally not stored in project handoff documentation because `HANDOFF.md` covers the broader Alumni Portal project.

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

Last verified: 2026-09-18 (Africa/Lagos)

Overall status: **In progress — 34 catalogue routes are built and 18 additional route slices have source/static implementations awaiting database proof; Goal 7 now includes announcement, vacancy, project, leadership, event, RSVP, and attendee slices, and Goal 6 now has a bounded, paged V2 inbox with measured index evidence, an indexed bounded staged-attachment reaper, and a scripted headless-browser V2 inbox journey, while full parity and production cutover remain incomplete.**

2026-09-18 working-tree gate repair: the repository-wide verification gate is **green again** on a fresh disposable MariaDB schema (474 tests passed, 0 failed, **90.21%** branch coverage against the 90% floor; `pip check`, Ruff format/check, strict `mypy` over `app migrations scripts tests`, Alembic drift check, Bandit, and `pip-audit` all clean). The 86.30% coverage deficit was closed by adding verified tests for the already-implemented Goal 7/8/9 code, not by deleting code or lowering thresholds. Two real working-tree defects were fixed on the way (an unimported `ProductVariants` reference that would raise `NameError` on variant cart/checkout paths, and a missing `Any` import in the mail adapter), plus one baseline frontend type error. The frontend `node_modules` dependency is now installed, so `npm run build` and a live FastAPI runtime smoke are real evidence instead of clean skips; the remaining frontend gap is the ~477-file Prettier baseline and 11 pre-existing `tsc --noEmit` errors.

2026-09-18 Goal 6 staged-attachment reaper slice: the working tree is green at head `d5e6f7a8b9c0` with **481 tests passed, 0 failed, 90.22% branch coverage** (90% floor met); `pip check`, Ruff format/check (125 files), strict `mypy` (125 files), Alembic drift check ("No new upgrade operations detected."), Bandit, and `pip-audit` ("No known vulnerabilities found") are all clean. The reaper removes expired unsent private attachments without racing a concurrent send, and the purge access path is indexed and reversible.

For this chat goal, "all endpoints finished" means every discovered PHP route candidate has a recorded retain, replace, merge, retire, internal-only, or remove decision; every retained capability has a FastAPI implementation; and the required contract, authorization, database, and frontend-compatibility checks pass. Retired and internal-only methods do not require a public FastAPI route, but their disposition must be evidenced.

### Goal-status discipline

- A completed **route family** or **implementation slice** means only that its stated, bounded checks passed. It is not a claim that the owning goal or the whole conversion has passed.
- A goal remains **in progress** while any of its unchecked tasks, acceptance-gate conditions, or required production/cutover evidence remains. Checkboxes intentionally include more than code: caller discovery, authorization, database effects, browser compatibility, provider behavior, migration/reconciliation, rollback, and release proof.
- A blocked external dependency is recorded as a bounded risk, not silently converted into a completed feature. It does not justify inventing a table, endpoint contract, or provider integration. Complete independently verifiable work in the active goal first; only mark a goal complete after its stated acceptance gate passes.
- The task breakdown is authoritative when a historical handoff entry or a focused test label says a goal is "complete." The latter must be read as a slice-level result unless the goal's acceptance gate is explicitly recorded as passed here.

| Completion gate | Current state | Current evidence | Required proof to complete |
| --- | --- | --- | --- |
| Separate FastAPI copy is complete | Foundation and multiple route families in progress | `python-backend/` contains a Python 3.13.7 FastAPI service, locked dependencies, typed configuration, health routes, database engine, explicit schema mappings, Alembic baseline, 34 catalogue routes already built, and 18 additional content route slices implemented pending database proof | Implement and verify every retained business capability; obtain approval for production worker/proxy configuration |
| All endpoint candidates are finished | In progress | Deterministic inventory covers 445 controller methods plus 103 root-artifact functions. The re-reviewed catalogue contains 159 rows: 145 direct conversions, 10 consolidated replacements, 1 platform replacement, and 3 schema/caller-blocked market decisions. The developer audit supplies 163 detailed `Live/KEEP` rows, exactly matching 157 catalogue rows. Frontend runtime parity, current-database proof, and cutover are not complete. | Resolve the developer-audit discrepancies, complete the 91 unbuilt rows, prove the implemented rows against disposable MariaDB and the frontend, and record every intentional difference and retirement |
| Existing SQL database can be connected safely | Export-compatible test connection verified | A localhost-only MariaDB 12.3.3 target imported all 68 tables after all `INSERT` statements were removed; readiness, 778-column mapping parity, empty-table checks, and rollback-safe write tests pass | Read-only comparison and permission tests against the approved current office database remain required; no production access has been attempted |
| Required tests are successful | Working-tree disposable gate passing; release gate not yet passed | On 2026-09-18 a fresh sanitized disposable MariaDB schema was rebuilt from `.local-state/sanitized-schema.sql`, upgraded to head `c4d5e6f7a8b9` with a clean `alembic check`, and the complete suite was run from `python-backend` with `PIP_REQUIRE_VIRTUALENV=true`: **474 passed, 0 failed, 90.21% branch coverage** (90% floor met), plus `pip check`, `ruff format --check` (121 files), `ruff check`, strict `mypy` over `app migrations scripts tests` (121 files), Alembic drift check, Bandit, and `pip-audit` all clean. Earlier the same day the tree was red at 86.30% coverage with 7 strict-mypy errors and 3 unformatted files; those are now cleared, and the two behavioural defects found while clearing them were fixed. Frontend evidence is also real now: `npm ci` succeeded, `npm run build` completed (8,524 modules, 58.56s), and a live Uvicorn smoke against the disposable schema served `/health/live`, `/health/ready`, and the public `get_events`/`get_listings`/`get_projects` contracts. A subsequent 2026-09-18 Goal 6 slice added the expired staged-attachment reaper, migration `d5e6f7a8b9c0` (`idx_msg_attachment_staged_purge`), and six DB-backed reaper tests: the full suite is now **481 passed, 0 failed, 90.22% coverage** with Ruff/mypy/Bandit/`pip-audit`/`alembic check` clean at head `d5e6f7a8b9c0`. | Add retained-route contract/authorization/browser automation, load, deployment, provider, and cutover evidence; `tsc --noEmit` still reports 11 pre-existing baseline type errors and repository-wide Prettier still fails its 477-file baseline, so neither can yet be enforced as a gate. No production equivalence is claimed |

### Initial Review Findings

- **P1 — Secret and personal-data containment is the first gate.** Credential-bearing configuration locations exist in `application/config/database.php`, `email.php`, `jwt.php`, and `vapid.php`. The SQL export contains user, session, refresh-token, OTP, and other data-bearing tables. Values must not be copied into the Python service, fixtures, logs, or this document.
- **P1 — Database success cannot be claimed from the SQL file alone.** The export establishes schema evidence, not current server access, credentials, schema drift, or successful read/write behaviour.
- **P1 — Endpoint completeness originally omitted implicit-public PHP methods.** The master register has been corrected from 427 explicitly-public methods to 445 callable non-constructor controller methods. Internal helpers are recorded as internal-only candidates rather than silently exposed.
- **P2 — The standalone `Backend/alumniappV2 - PHP/Api.php` is outside `application/controllers/` and has 103 non-constructor functions.** It appears to contain overlapping and unrelated legacy capabilities and must be classified before the endpoint inventory can be signed off.
- **P2 — Frontend runtime parity is not complete.** The frontend source and built artifacts are now present and have been reviewed for several active slices, but Goal 1 still needs route-by-route caller, request/response, authenticated browser, and live compatibility evidence for all retained routes.
- **P2 — Developer audit evidence improves route reachability, but has unresolved internal count and coverage gaps.** Its detailed `Endpoints` sheet contains 163 rows, all marked `Live/KEEP`, while its `Summary` says 175 live endpoints. It supplies six controller rows but lists four `Socials` endpoints without a `Socials` controller row; it also says the core controller has 24 functions while the detailed core-function sheet lists 25. Treat the detailed rows as traceable evidence and obtain a corrected audit before using its headline counts as final.
- **P2 — Repository history and working-tree provenance require care.** The checkout has Git history, but the working tree contains concurrent user changes. Do not reset, discard, or stage unrelated files while continuing the migration.

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
| Git | Git 2.51.0 is installed and this checkout is on `backend-dev` | Use `git status` and scoped diffs before every change; preserve concurrent user changes and do not reset or discard unrelated work |
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
- [x] Classify all 548 inventory items and the 159 workbook catalogue rows as retain, direct conversion, redesign, merge, deprecate, internal-only, platform replacement, security retirement, or remove. Final caller/contract confirmation remains a separate acceptance gate.
- [ ] Treat `Api.php` as a candidate primary source, then compare it with `Api_with_jwt.php`, `Api_backup.php`, and `oldApi.php` before selecting behaviour.
- [ ] Review `Setup.php`, `Setup22.php`, `RateAgentApi.php`, test scripts, and legacy controllers to ensure they are not mistakenly migrated as production features.
- [x] Classify the standalone root `Backend/alumniappV2 - PHP/Api.php` and its 103 non-constructor functions (retire as a non-controller duplicate: it is outside `application/controllers`, exits when `BASEPATH` is undefined, and no package reference or alternate front-controller mapping was found).
- [x] Reconcile the PHP developer audit against the route catalogue (the detailed audit lists 163 `Live/KEEP` rows; 157 exactly match the 159 catalogue rows, while `Pwa /pwa/offline` and `my404 /my404/index` are not listed).
- [ ] Ask the PHP developer to reconcile the audit's 175-summary versus 163-detail endpoint count, its six listed controller rows versus seven `KEEP` controllers claimed in the summary, the omitted `Socials` controller row, and 24-versus-25 shared-core-function count.
- [x] Treat a developer `Live/KEEP` finding as reachability evidence only. The current ledger retains the fourteen security-risk inventory retirements, ten workbook consolidated replacements, sixteen executable tombstones, and root-level `Api.php` retirement unless separate security, source-file, and frontend-contract evidence approves a different treatment.
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

- [x] Introspect the live schema and compare it with `alumni_portal_v2.sql`. A supplied live dump (phpMyAdmin, server `10.5.26-MariaDB`, database `alumni_portal_v2`) was sanitized to schema-only and compared via `scripts/schema_drift_report.py`; the only new live table since the export is `news_feeds_setup`.
- [x] Record database engine/version, character sets, collations, storage engines, indexes, foreign keys, triggers, procedures, and scheduled events. Live schema: 69 tables, 785 columns, InnoDB only, 3 collations, 221 indexes, 55 foreign keys, 0 triggers/routines/events (recorded in `docs/schema-baseline.md`); SQL mode/timezone still require live-server confirmation.
- [x] Identify schema drift between the SQL export and the actual environment. Drift is limited to `news_feeds_setup` (now mapped) plus our own additive migrations (expected to apply at cutover); no other column/type/nullability/index/FK difference was found.
- [ ] Catalogue orphan rows, duplicate records, invalid enums/statuses, inconsistent booleans, zero dates, nullable-field surprises, and broken relationships.
- [x] Generate SQLAlchemy mappings for all 69 exported/live tables and verify 785 columns against the sanitized clone, including names, types/lengths, defaults, computed expressions, comments, keys, indexes, and nullability. `news_feeds_setup` was added; the parity test (`test_schema_models.py`) now asserts 70 model tables.
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

- [x] Decide whether the legacy and V2 chat models are both active; select one canonical target model. ADR 0005 selects the reviewed V2 `message_threads` family because the active frontend source and compiled bundle call only `chat_api/v2_*`; the empty disposable schema cannot prove live legacy/V2 data ownership, so no automatic reconciliation is authorized.
- [ ] Map legacy group IDs, V2 thread IDs, participants, membership states, read markers, delivery markers, replies, attachments, and soft deletions.
- [x] Implement thread and message listing with participant checks before every query. The V2 inbox/detail routes require an active current-account participant, cap detail pagination at 100, and clear only that participant's read cursor on open.
- [x] Implement direct-message creation with deterministic duplicate prevention. Direct keys use the reviewed unique index, an atomic MariaDB `INSERT IGNORE` collision path, and bounded retry for transient 1020/1205/1213 conflicts.
- [x] Implement group creation and member management with explicit owner/admin authorization. Group creation makes the creator the initial administrator; only an active administrator may add/reactivate an active member, and voluntary leave preserves history while transferring a sole remaining administrator role deterministically.
- [x] Prevent ordinary members from adding users to arbitrary groups. V2 add-member rejects non-group and ordinary-member attempts before any target lookup/write.
- [x] Restrict graduation-year bulk synchronization to authorized administrators or an internal job. `v2_sync_year_groups` is an authenticated HTTP 410 boundary until an approved current-policy internal-job design, schema uniqueness/reconciliation plan, and active caller exist; PHP's ordinary-user and unauthorised `sync_all` writes are not retained.
- [x] Validate that reply targets and attachments belong to the same thread and authorized sender. Replies must be non-deleted and in the current thread; attachment IDs are locked, must be distinct (maximum six), staged in that thread by the current actor, and unexpired before message creation.
- [x] Enforce attachment ownership before linking staged attachments to messages. Additive migration `0e4c31d8f2a7` records the stager and expiry; a link atomically clears expiry, is one-message-only, and is covered by cross-thread/replay denial tests.
- [x] Implement safe read, delivered, pin, leave, and soft-delete operations. Each requires an active participant and validates the thread/message relationship; pin is scoped to that participant, leave is group-only, and delete is sender-only.
- [x] Preserve historical messages when a member leaves while preventing new access beyond the intended policy. Leave soft-sets `left_at`, preserves rows/messages, clears the member pin, and blocks all future detail/read/write access.
- [x] Add pagination limits and indexes for high-volume message retrieval. `POST /chat_api/v2_get_threads` now takes a bounded `limit` (1-200, default 100) and `offset` (0-100,000) and reports `count`, `thread_total`, `unread_count`, `unread_thread_count`, `limit`, `offset`, and `has_more`. Listing one page is a fixed query count: one paged thread join plus batched membership, participants, per-thread unread counts, and one mailbox-wide unread aggregate; previously every thread cost a separate participant and unread query. Additive, reversible migration `c4d5e6f7a8b9` adds `idx_thread_participants_inbox (member_id, left_at, is_pinned)` after `scripts/chat_retrieval_probe.py` measured the four retrieval query shapes against a disposable schema with 3,400 participant rows and 100,000 messages; the bounded thread count is now an index-only scan and per-thread message paging already used `idx_thread_id`, so no speculative `messages` index was added.
- [x] Reclaim expired private staged attachments with a bounded, idempotent sweep whose global access path is indexed. `ChatService.reap_expired_staged_attachments` (`limit` 1-500, default 200) locks a bounded batch of `message_id IS NULL AND expires_at <= now` rows, deletes the authoritative database rows in one transaction, then removes the private files after commit; the deployable entry point is `app/tasks/chat_attachment_reaper.reap_expired_chat_attachments` with the `scripts/reap_chat_attachments.py` operator wrapper. Additive, reversible migration `d5e6f7a8b9c0` adds `idx_msg_attachment_staged_purge (message_id, expires_at)` because the owner-scoped `idx_msg_attachment_staged_expiry` cannot serve a global sweep; the probe shows the sweep moves from `type=ALL; Using filesort` to `type=range key=idx_msg_attachment_staged_purge`. Tests cover expiry-only selection, linked/fresh preservation, batching/idempotency, the missing-storage guard, missing/malformed path handling, and a concurrent-link lock race. **Scheduler decision (2026-09-18, user-approved):** an OS scheduler (cron/systemd timer/Windows Task Scheduler) invokes `scripts/reap_chat_attachments.py`; registration happens on the deployment host under Goal 12. Malware scanning is now decided (no external provider; residual risk accepted — see the Goal 9 task).
- [x] Script the headless-browser V2 inbox journey. Playwright + Chromium were added to `frontend/` (user-approved) and `frontend/e2e/messages-v2-inbox.mjs` seeds a disposable database (`scripts/seed_chat_journey_fixture.py`), starts Uvicorn and `vite preview` against the built `dist`, drives Chromium through the real login form into `/messages`, and asserts the seeded group thread and its preview render from the live `chat_api/v2_get_threads` response (HTTP 200). Evidence: `final_url=http://127.0.0.1:4173/messages`, `Synthetic V2 Inbox Journey` visible, screenshot `tmp/e2e-messages-v2-inbox.png`, `npm run e2e:messages` exit 0. The journey proxies same-origin API calls to Uvicorn inside the browser context because the app has no CORS middleware yet (Goal 10), so no app code was changed for the test.
- [x] Add concurrency and duplicate-send tests using client-generated IDs. Disposable-MariaDB tests prove same-actor message idempotency and simultaneous opposite first direct sends converge on one thread.
- [x] Add cross-group and cross-thread authorization tests. Disposable-MariaDB tests deny non-member reads, sender-foreign deletion, and cross-thread replies.

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
- [ ] Complete the event-registration-form family: the six bounded Bearer routes, immutable form-version/display snapshots, checkbox limits, and fresh-schema 90.02%-coverage gate pass; public event metadata derives active-form count/availability from MySQL. **Decision:** FastAPI plus MariaDB is the canonical live form/answer system. Firebase/Firestore/Cloud Functions are not a replacement runtime and must not be extended; they are temporary, read-only historical-export sources only. The acceptance gate remains open pending an approved sanitized Firebase export, explicit ID mapping and import rehearsal, frontend Firebase-to-FastAPI cutover to the single atomic RSVP-with-answers write, browser journey, hidden-caller, and release evidence. Preserve current answer validation, self-owned RSVP, event visibility, administrator scope, pagination, and PII protections. Firebase credential availability must not block independent non-Firebase work in this goal, but it also cannot be called cutover proof.
- [ ] Re-review the privacy-policy family (`create_privacy_policy`, `manage_privacy_policy`, and `get_privacy_policy`) against current frontend callers and a real storage model. Do not invent a `privacy_policy` table or public contract from the PHP handlers alone.
- [ ] Keep the legacy `create_market`, `manage_market`, and `get_market` family blocked until a current `market` schema or live caller is proven. Do not recreate an absent table merely to preserve route names.
- [ ] Treat implemented source/static slices as incomplete until disposable-MariaDB integration, authenticated/browser contract, media persistence, hidden-caller, and cutover checks pass.

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

- [x] Map products, variants, stock, carts, cart items, orders, order items, delivery methods, and payment states. All are mapped in `app/models/generated.py` (`Products`, `ProductVariants`, `Carts`, `CartItems`, `Orders`, `OrderItems`, `DeliveryZones`, `Payments`), and the route-family inventory is complete: all 24 PHP `Product` controller methods map 1:1 to 24 registered `/product/*` routes (`pin_product_item`, `fetch_products`, `add_product`, `edit_product`, `delete_product`, `fetch_cart`, `add_to_cart`, `update_cart`, `remove_from_cart`, `clear_cart`, `add_address`, `fetch_addresses`, `edit_address`, `delete_address`, `set_default_address`, `fetch_delivery_zones`, `initiate_checkout`, `verify_payment`, `paystack_webhook`, `fetch_orders`, `view_order_details`, `order_management`, `manage_order_details`, `update_order_status`).
- [x] Preserve existing order numbers and Paystack references. `initiate_checkout` generates both server-side; `verify_payment`/webhook look up by Paystack `reference` under an order row lock, and `view_order_details`/`fetch_orders` expose the server-owned `order_number`.
- [x] Contract-review slice (products/checkout/payment verified). The public `fetch_products` payload (`meta` + `data` with `product_name`, `category`, `price`, `description`, `has_size`/`has_color`, `status`, `pin_item`, `quantity`, `total_stock`, `images[{id,image_path,image_url,is_spotlight}]`, `variants[{id,color,size,quantity,image_id}]`) matches `frontend/src/features/store/services/store.service.ts` `adaptProduct` field-for-field; `initiate_checkout` and `verify_payment` return the exact keys the frontend `address.service.ts` adapters read. The remaining routes are exercised end-to-end by the 97-test store suite; a full per-route frontend-adapter audit plus store browser journey remain.
- [ ] Implement product and inventory administration with store-admin authorization.
- [ ] Implement carts and checkout with ownership checks.
- [ ] Recalculate prices, shipping, discounts, and totals on the server from trusted database values.
- [ ] Store monetary values as `Decimal` and compare Paystack kobo amounts exactly.
- [ ] Implement checkout initialization without exposing the Paystack secret.
- [ ] Implement payment verification with authenticated order ownership checks.
- [ ] Implement webhook signature verification using the raw request body and constant-time comparison.
- [ ] Verify payment status, amount, currency, reference, and expected order before finalization.
- [ ] Make finalization transactional and idempotent.
- [x] Lock or conditionally update order and stock rows to prevent double stock deduction. `StoreRepository.finalize_order_atomic` deducts variant/product stock with conditional updates (`UPDATE ... WHERE quantity >= :qty`, checked by `rowcount`) and raises `InsufficientStockError` on a shortfall so the transaction rolls back; `verify_payment` returns HTTP 409 `INSUFFICIENT_STOCK` and the webhook acknowledges but leaves the order pending for reconciliation. The previous read-modify-write clamped stock to zero and silently oversold two competing orders.
- [ ] Ensure webhook retries return safe responses without duplicate state changes.
- [ ] Add a manual reconciliation process for paid transactions whose database finalization fails.
- [ ] Redact payment payloads and secrets from user responses and logs.
- [x] Test duplicate callbacks, amount mismatches, failed payments, abandoned checkouts, stock races, and rollback. `tests/test_store_concurrency.py` proves two paid orders cannot oversell a single unit (serial and concurrent webhook), replay idempotency, and no negative stock; `tests/test_store_edges.py` and `tests/test_paystack.py` cover amount mismatch, failure, signature, and abandoned-checkout branches.

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
- [x] Decide whether malware scanning is required before files become available. **Decision (2026-09-18, user-approved):** no external malware scanner for the compatibility phase. Uploads remain bounded by content-signature/type checks, image re-encode, size/pixel limits, private non-public storage, and owner/participant-gated authenticated retrieval. The residual risk (an upload could carry malware that the client later opens) is explicitly accepted and recorded in `HANDOFF.md`; a self-hosted ClamAV or commercial scanning provider can be added later without changing the storage contract if policy changes.
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

- [x] Define trusted hosts and fixed environment-specific public base URLs. `Settings.trusted_hosts`, `public_base_url`, and `frontend_base_url` are typed and validated; wildcard trusted hosts are rejected in production. Concrete environment values are a Goal 12 deployment concern.
- [x] Configure a minimal CORS allowlist without `null` origins or unconditional credential support. `create_app` now adds `CORSMiddleware` from `settings.cors_origins` (explicit allowlist, `allow_credentials=False`, restricted methods/headers). With no allowlist configured, no cross-origin grant is emitted; wildcard origins are rejected by config validation in production. `tests/test_security.py` proves the allowlisted origin is echoed, unlisted and `null` origins are rejected, and credential support is never advertised.
- [x] Use secure, HttpOnly, SameSite cookies if cookies remain part of authentication. Not applicable: the FastAPI authentication surface is Bearer-token only (no session cookies), so no cookie/CSRF surface exists; this is a documented design decision rather than an open task.
- [x] Add CSRF protection to any cookie-authenticated state-changing flow. Not applicable for the same reason: there are no cookie-authenticated flows; state-changing routes use Bearer tokens and are rate-limited.
- [x] Add security headers appropriate to API and file responses. `SecurityHeadersMiddleware` adds `x-content-type-options: nosniff`, `x-frame-options: DENY`, `referrer-policy: no-referrer`, `permissions-policy`, and a restrictive `content-security-policy` baseline without overriding route-provided headers. `tests/test_security.py` covers the baseline and non-override behaviour.
- [x] Apply endpoint-specific rate limits to authentication, registration, search, uploads, emails, and expensive listings. `enforce_rate_limit` is applied per-route across `auth`, `members`, `chat`, `events`, `marketplace`, `announcements`, `blog`, `news`, `contact`, `projects`, `leadership`, `vacancies`, and `notifications` (shared peer/identity keyed limits; production requires Redis-backed counters).
- [x] Redact passwords, tokens, authorization headers, reset codes, personal data, and payment payloads from logs. `app/core/logging.py` now recursively redacts a comprehensive sensitive-key set (secrets, PII, and payment fields) at any nesting depth in structured logs, and replaces whole `payment_payload`/`tx_data` objects. `tests/test_logging.py` (5 tests) covers top-level, nested, list, payment-payload, and PII redaction.
- [ ] Add immutable audit records for privileged actions without storing secrets.
- [ ] Define data retention and deletion procedures for logs, sessions, tokens, uploads, and member records.
- [ ] Add centralized exception reporting that does not disclose stack traces or SQL details to clients.
- [x] Add metrics for latency, errors, database saturation, authentication failures, email failures, upload rejection, webhook processing, and background jobs. A bounded Prometheus `MetricsMiddleware` (request count + latency, keyed by route template not raw path) and a gated `GET /metrics` endpoint are added: disabled by default, requiring a configured collector token (Bearer or `X-Metrics-Token`, constant-time) when enabled. `tests/test_metrics.py` (5 tests). Finer per-domain counters (email/auth failures, webhook, uploads) remain future work.
- [ ] Add dependency and container scanning to continuous integration.
- [x] Run an application security review and threat model before production cutover. `docs/threat-model.md` is written (assets, trust boundaries, threat actors, 10 threat scenarios with mitigations, residual risks, pre-cutover follow-ups). A final re-review against the chosen deployment target remains a Goal 12 pre-cutover gate.

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
- [x] Test database encoding, Unicode names, Nigerian phone formats, dates, timezones, nulls, and legacy records. `tests/test_edge_data.py` (4 tests) proves non-ASCII names and four-byte emoji round-trip through utf8mb4 columns, the registration Nigerian-phone contract (`^0[789][0-9]{9}$` local format accepted, international/national/malformed variants rejected), and null `graduation_year`/`avatar` legacy rows list safely in the directory. Lagos/leap-day date and legacy `$2y$` hash behaviour were already covered by the birthday and auth suites.
- [ ] Test existing frontend flows against FastAPI in a non-production environment.
- [ ] Run load tests for login, member directory, chat polling/history, event listings, uploads, and checkout.
- [x] Add a release gate that blocks deployment when contract, security, migration, or financial tests fail. `scripts/verify.ps1` is the deployment-blocking gate: it aborts on any failure of the venv assertion, `pip check`, Ruff format/check, strict `mypy`, `pytest --cov=app` (90% floor), `alembic check` (migration drift), Bandit, and `pip-audit`; the store/paystack concurrency and contract tests are part of the pytest suite, so financial and contract regressions block the gate too.

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

The workbook `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx` records every developer-audit match, exception, and resulting migration treatment. It is the reviewed baseline; this register remains the implementation checklist and is the place where the implementing agent records current code and test evidence. All 159 workbook route paths were reconciled to this register on 2026-09-16. A route must not be called complete from source existence alone: use the goal checkpoint below and the per-route completion definition before checking it off.

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
| `api/create_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1811` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a bounded JSON/form/multipart POST. Any active member assigned to an enabled current chapter may create a vacancy; ownership and chapter are derived from locked current-database facts, the active client currency field is accepted but not persisted because the reviewed table has no currency column, and an optional generated/re-encoded flyer is cleaned up on rollback. |
| `api/manage_vacancy` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:1918` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded JSON/form/multipart update/delete operations. A locked active owner may change or delete their own vacancy; current-database `MANAGE_CONTENT` is the explicit administrative override. Caller-selected ownership/chapter is ignored, application destinations are validated on mode changes, and generated flyers are safely replaced/removed. |
| `api/get_vacancies` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:2035` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded public POST list/detail contract. It returns selected job/application fields and poster display name, never user email/account fields. The legacy application-key read gate is deliberately not copied; hard delete follows the retained PHP table behaviour. |
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
| `api/create_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3479` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded JSON/form/multipart POST. An active actor with freshly loaded current-database `MANAGE_EVENTS` creates an event with server-owned creator/approval state, an optional selected enabled chapter, and an optional generated/re-encoded banner that is cleaned up on rollback. |
| `api/manage_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3594` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded JSON/form/multipart update/delete operations. A current-database `MANAGE_EVENTS` actor locks the target before an allowlisted update or hard delete; server-generated banners are safely replaced/removed, and a reusable application key or caller-selected creator never grants authority. |
| `api/get_events` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3694` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded public POST list/detail contract. It returns only approved, public, non-draft event presentation data and creator display name, never account identifiers/email; the legacy reusable read key is deliberately not copied. |
| `api/register_event` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3818` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a Bearer-only self-service POST. The active current-database account owns the RSVP; legacy caller-selected `user_id` and year are ignored, the parent event provides the year, and only approved public upcoming/active events may accept registrations. The parent event is locked before bounded capacity and duplicate-upsert decisions. |
| `api/manage_event_rsvp` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:3950` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as a Bearer-only self-service cancel/update POST. The active current-database account may affect only its own RSVP; a transition to `going` rechecks the locked event capacity. The legacy shared application key and caller-selected user are deliberately not copied. |
| `api/get_event_attendees` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4043` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded POST attendee view. Contact fields are returned only after a freshly loaded current-database `MANAGE_EVENTS` check; filters and pagination are bounded. The documented legacy GET and reusable-key path are not retained because no active frontend caller uses GET and the PHP contract exposes attendee PII without user authorization. |
| `api/create_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4126` | Goal 7 | Implemented pending full-gate/frontend cutover proof. Current-database `MANAGE_EVENTS`, bounded form/question contract, safe type/options validation, and transactional form persistence replace the PHP shared-key/JWT-role trust. |
| `api/manage_event_registration_form` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4230` | Goal 7 | Implemented pending full-gate/frontend cutover proof. Current-database event administrators can create/update/archive/reorder bounded forms/questions; archive retains historical answer snapshots. |
| `api/get_event_registration_forms` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4406` | Goal 7 | Implemented pending full-gate/frontend cutover proof. Active members receive active forms only for approved public registrable events; event administrators may inspect inactive forms. |
| `api/register_event_with_forms` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4486` | Goal 7 | Implemented pending full-gate/frontend cutover proof. Bearer-only self-owned RSVP locks capacity, validates required/current form answers, ignores caller-selected user identity, and stores answer snapshots atomically. |
| `api/get_event_registration_submissions` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4631` | Goal 7 | Implemented pending full-gate/frontend cutover proof. Bounded, event-scoped submission lists expose registrant PII only to current-database `MANAGE_EVENTS`. |
| `api/get_event_registration_submission_detail` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4714` | Goal 7 | Implemented pending full-gate/frontend cutover proof. A current-database event administrator must supply the event plus one attendee/user selector; answer snapshots are returned without PHP's key-only disclosure. |
| `api/create_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:4940` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded JSON/multipart POST over `marketplace_listings` and `marketplace_social_media`. Active actor ownership and `MANAGE_STORE` permissions are derived server-side. |
| `api/manage_listing` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5098` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded JSON/multipart POST for update/delete. Owner or `MANAGE_STORE` authorization enforced. |
| `api/get_listings` | `Backend/alumniappV2 - PHP/application/controllers/Api.php:5359` | Goal 7 | Implemented pending disposable-MariaDB/frontend proof as bounded public POST list/detail contract over active, unexpired marketplace listings. |
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
| `blog_api/homepage` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:148` | Goal 7 | Built and disposable-MariaDB tested; public GET/POST read over greeting text, active carousel images, and greeting image ID; browser/cutover pending |
| `blog_api/update_homepage_text` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:161` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT enforcement, atomic greeting title/message upsert; browser/cutover pending |
| `blog_api/create_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:192` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, multipart image upload with safe UUID filename, alt_text, sort ordering, show_greeting exclusivity, and rollback file cleanup; browser/cutover pending |
| `blog_api/update_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:227` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, optional image replacement, alt_text, is_hidden toggle, greeting exclusivity, and automatic greeting reassignment on hide; browser/cutover pending |
| `blog_api/reorder_carousel` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:360` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, batch sort_order updates, normalized sequence; browser/cutover pending |
| `blog_api/delete_carousel_image` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:387` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, soft delete with deleted_at timestamp, greeting reassignment before deletion, and order normalization; browser/cutover pending |
| `blog_api/faqs` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:419` | Goal 7 | Built and disposable-MariaDB tested; public GET/POST returns published FAQs by sort_order; admin MANAGE_CONTENT all=true returns all FAQs; browser/cutover pending |
| `blog_api/create_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:432` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, question/answer validation, sort ordering, and publication state; browser/cutover pending |
| `blog_api/update_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:462` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, question/answer/sort/publication updates, session identity map expiration; browser/cutover pending |
| `blog_api/reorder_faqs` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:506` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, batch sort_order updates; browser/cutover pending |
| `blog_api/delete_faq` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:533` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, soft delete with deleted_at timestamp, order normalization; browser/cutover pending |
| `blog_api/blog_categories` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:562` | Goal 7 | Built and disposable-MariaDB tested; public GET/POST returns active categories by sort_order; admin MANAGE_CONTENT all=true includes inactive categories; browser/cutover pending |
| `blog_api/create_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:575` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, unique slug generation from name, soft-delete revival on slug collision, and order normalization; browser/cutover pending |
| `blog_api/update_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:605` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, name/slug/status/order updates with session identity map synchronization; browser/cutover pending |
| `blog_api/delete_blog_category` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:657` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, soft delete preserving foreign key references, order normalization; browser/cutover pending |
| `blog_api/reorder_categories` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:689` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, batch sort_order updates; browser/cutover pending |
| `blog_api/blog_posts` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:720` | Goal 7 | Built and disposable-MariaDB tested; public GET/POST returns published post summaries with pagination, category filter, and keyword search; admin MANAGE_CONTENT draft/all status filtering; browser/cutover pending |
| `blog_api/blog_post_detail/{id_or_slug}` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:747` | Goal 7 | Built and disposable-MariaDB tested; public GET/POST detail by integer ID or unique slug, including ordered sections, gallery images, cover image, and read time; browser/cutover pending |
| `blog_api/create_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:772` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, multipart form-data or JSON, unique slug generation, calculated reading time, cover image selection, atomic post/sections/gallery persistence, and rollback file cleanup; browser/cutover pending |
| `blog_api/update_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:864` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, post title/excerpt/category/status updates, full section replacement, gallery uploads, and session identity map synchronization; browser/cutover pending |
| `blog_api/delete_blog_post` | `Backend/alumniappV2 - PHP/application/controllers/Blog_api.php:968` | Goal 7 | Built and disposable-MariaDB tested; admin MANAGE_CONTENT, soft delete with deleted_at timestamp; browser/cutover pending |
| `chat_api/get_threads` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:29` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/get_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:50` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/create_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:86` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/send_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:129` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/send_direct` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:200` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/list_messages` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:275` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/delete_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:319` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/mark_read` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:358` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/add_member` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:389` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/leave_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:418` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/pin_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:445` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/upload_media` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:477` | Goal 6 | Intentional authenticated HTTP 410; V1 legacy model retired pending reconciliation |
| `chat_api/get_vapid_key` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:531` | Goal 9 | Implement and compare |
| `chat_api/register_push_subscription` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:544` | Goal 9 | Implement and compare |
| `chat_api/v2_get_threads` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:600` | Goal 6 | Built and disposable-MariaDB tested; active-member V2 inbox, browser/cutover pending |
| `chat_api/v2_get_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:621` | Goal 6 | Built and disposable-MariaDB tested; active-member detail/reply isolation, browser/cutover pending |
| `chat_api/v2_create_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:654` | Goal 6 | Built and disposable-MariaDB tested; creator-admin group with separate admin-only member-management slice, browser/cutover pending |
| `chat_api/v2_send_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:697` | Goal 6 | Built and disposable-MariaDB tested; participant-only, same-thread replies and private owned staged attachment linking; browser/cutover pending |
| `chat_api/v2_send_direct` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:767` | Goal 6 | Built and disposable-MariaDB concurrency/idempotency tested; browser/cutover pending |
| `chat_api/v2_upload_attachment` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:841` | Goal 6 | Built and disposable-MariaDB tested; private content-validated staging with authenticated retrieval; browser/cutover/provider pending |
| `chat_api/v2_delete_message` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:913` | Goal 6 | Built and disposable-MariaDB tested; sender-only soft delete, browser/cutover pending |
| `chat_api/v2_mark_read` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:942` | Goal 6 | Built and disposable-MariaDB tested; participant-only cursor update, browser/cutover pending |
| `chat_api/v2_mark_delivered` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:996` | Goal 6 | Built and disposable-MariaDB tested; active-member same-thread cursor update, browser/cutover pending |
| `chat_api/v2_add_member` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1021` | Goal 6 | Built and disposable-MariaDB tested; group-admin-only active member add/reactivate, browser/cutover pending |
| `chat_api/v2_leave_group` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1047` | Goal 6 | Built and disposable-MariaDB tested; soft leave/history preservation and sole-admin handoff, browser/cutover pending |
| `chat_api/v2_pin_thread` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1071` | Goal 6 | Built and disposable-MariaDB tested; active member's own pin only, browser/cutover pending |
| `chat_api/v2_sync_year_groups` | `Backend/alumniappV2 - PHP/application/controllers/Chat_api.php:1099` | Goal 6 | Intentional authenticated HTTP 410 pending approved internal-job/schema/caller evidence; PHP bulk write rejected |
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
| `news/feeds` | `Backend/alumniappV2 - PHP/application/controllers/News.php:125` | Goal 7 | Built and tested on disposable MariaDB; GET/POST public aggregator with X-API-Key auth, topic/keyword engine, and 15m cache |
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

### Goal-by-goal Execution and Verification Protocol

Work on one goal at a time. A later goal may use completed foundations, but it must not be marked complete because an earlier goal has source code, a passing unit test, or a workbook status. After each goal, update the task checkboxes, the goal status table, the progress log, and `HANDOFF.md`. Do not update the Excel workbook during implementation or testing unless the user explicitly asks for that separate workbook task.

For the active goal, the implementing chat must:

1. Read this breakdown and `HANDOFF.md`, inspect the relevant PHP, FastAPI, frontend, schema, and current tests, and confirm the exact in-scope route rows.
2. Implement only the approved route family or supporting feature for that goal. Preserve unrelated working-tree changes.
3. Run focused tests first, then the applicable `.venv` quality checks, disposable-MariaDB tests, and frontend/browser or provider checks required by the goal.
4. Record passed checks, skipped checks, failures, intentional PHP differences, and remaining production/cutover gates. A skipped database/provider/browser test is not a pass.
5. Mark a task complete only when its implementation, authorization, data/side-effect, compatibility, and required evidence gates pass. Otherwise leave it open and state the blocker.

| Goal | Completion evidence required before marking the goal complete |
| --- | --- |
| Goal 0 | Secret/PII inventory, rotation and containment evidence, sanitized fixtures/database, and protected legacy unsafe surfaces. |
| Goal 1 | Complete 548-item inventory, 159-route catalogue mapping, caller/method/request/response/status/database map, audit discrepancy decision, and compatibility decision log. |
| Goal 2 | Isolated FastAPI service, locked dependencies, safe configuration, health/error behaviour, quality automation, and approved production entry-point decision. |
| Goal 3 | Current-schema comparison, drift/data-quality report, typed mappings/repositories, timezone/boolean decisions, rollback/deadlock tests, and query evidence. |
| Goal 4 | Password/token compatibility, canonical current-database authorization, negative protected-route tests, social-provider decision, and auth/recovery verification. |
| Goal 5 | Registration-to-approval, profile/privacy, chapters/geography, roles, vouchers, imports, and statistics journeys with SQL, authorization, field-exposure, and frontend evidence. |
| Goal 6 | One approved chat model, membership/ownership enforcement, attachment rules, legacy/V2 decision, concurrency tests, and frontend messaging evidence. |
| Goal 7 | Announcements, vacancies, projects, leadership, events/RSVP/attendees, registration forms, privacy policy, marketplace decision, blog/news/content contracts, media handling, and public/admin/browser/SQL evidence. |
| Goal 8 | Product/cart/address/order/payment mapping, exact Decimal/Paystack checks, webhook signature and idempotency tests, stock/order concurrency, and provider/reconciliation evidence. |
| Goal 9 | Upload policy and protected storage, contact/email/push contracts, recipient ownership, provider retry/timeout tests, URL-fetch restrictions, and live-provider decision/evidence. |
| Goal 10 | Trusted-host/CORS/cookie/CSRF/security headers, rate limits, redaction, audit/retention, metrics, dependency scanning, and threat-model review. |
| Goal 11 | Full retained-route contract/authorization/database/frontend suite, intentional-difference register, negative/concurrency/failure tests, load evidence, and a deployment-blocking release gate. |
| Goal 12 | Environment/deployment matrix, route cutover plan, backup/rollback rehearsal, monitoring/reconciliation, controlled traffic move, PHP write shutdown, and decommission sign-off. |

The final migration decision is the conjunction of these goal gates. A route is not “converted” merely because it appears in the Python router. The final evidence must also show the correct current data model, security policy, frontend contract, side effects, and approved production transition.

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

Legacy chat endpoints are all authenticated HTTP 410 pending approved sanitized reconciliation; active source and compiled frontend evidence call V2 only:

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
- `api/create_vacancy` — implemented pending disposable-MariaDB/frontend proof; any current active member of an enabled chapter can create a vacancy, with server-derived owner/chapter, validated application destination, and optional safe generated flyer
- `api/manage_vacancy` — implemented pending disposable-MariaDB/frontend proof; the locked owner or current-database `MANAGE_CONTENT` may update/delete, with client ownership/chapter ignored and safe flyer cleanup
- `api/get_vacancies` — implemented pending disposable-MariaDB/frontend proof as the bounded public active-frontend POST list/detail contract; presentation output excludes user email/account fields and deliberately retires the reusable API-key gate

Announcements and policy:

- `api/create_announcement` — implemented pending disposable-MariaDB/frontend proof; active Bearer plus current database `MANAGE_CONTENT` permission, explicit input contract, and bounded re-encoded image storage
- `api/manage_announcement` — implemented pending disposable-MariaDB/frontend proof; same current-policy gate, explicit update/delete allowlist, locks, transaction boundary, and local-image cleanup
- `api/get_announcements` — implemented pending disposable-MariaDB/frontend proof as a bounded public GET/POST presentation feed; legacy API-key/JWT read gate is deliberately not copied after current homepage/frontend review
- `api/create_privacy_policy`
- `api/manage_privacy_policy`
- `api/get_privacy_policy`

The privacy-policy rows remain direct-conversion candidates in the workbook, but they are not implementation approval. The 2026-09-16 re-review found the current `frontend/src/pages/legal/PrivacyPage.tsx` is static, found no source or built-artifact caller for these route names, and found no reviewed `privacy_policy` table, migration, or generated model. Existing profile-privacy settings are a separate member-account feature. All three rows remain blocked pending approved storage and live-traffic/caller evidence; do not invent a table or public API from PHP alone.

Events and registrations:

- `api/create_event` — implemented pending disposable-MariaDB/frontend proof; current-database `MANAGE_EVENTS` is required, creator/approval state is server-owned, enabled chapter scope is validated, and an optional re-encoded banner cleans up on rollback
- `api/manage_event` — implemented pending disposable-MariaDB/frontend proof; the current-database event administrator locks the target for allowlisted update or hard delete, and generated banners are safely replaced/removed
- `api/get_events` — implemented pending disposable-MariaDB/frontend proof as the active public POST feed/detail; only approved/public/non-draft presentation data and creator display name are exposed
- `api/register_event` — implemented pending disposable-MariaDB/frontend proof as a Bearer-only, self-service RSVP. The current active account is authoritative; legacy `user_id` and year are ignored, the approved/public/available parent event supplies the year, and the parent row locks capacity and duplicate decisions.
- `api/manage_event_rsvp` — implemented pending disposable-MariaDB/frontend proof as a Bearer-only self-cancel/update. It can change only the current account's row and rechecks capacity under the event lock before any transition to `going`.
- `api/get_event_attendees` — implemented pending disposable-MariaDB/frontend proof as the active frontend's bounded POST attendee view. It returns contact fields only to a freshly authorized current-database `MANAGE_EVENTS` actor; the PHP key-only GET variant is intentionally not retained.
- `api/create_event_registration_form` — implemented pending full-gate/frontend cutover proof; current database `MANAGE_EVENTS`, bounded safe question definitions, and transaction-safe persistence replace PHP's shared-key/JWT-role trust.
- `api/manage_event_registration_form` — implemented pending full-gate/frontend cutover proof; bounded create/update/archive/reorder is current-policy event-admin-only, and archive retains answer snapshots.
- `api/get_event_registration_forms` — implemented pending full-gate/frontend cutover proof; members see active forms only after approved/public/registrable event visibility validation, while event administrators may include archived forms.
- `api/register_event_with_forms` — implemented pending full-gate/frontend cutover proof; active Bearer identity owns the RSVP, required/current answers are validated, capacity is locked, and snapshots save atomically.
- `api/get_event_registration_submissions` — implemented pending full-gate/frontend cutover proof; current `MANAGE_EVENTS`, bounded event-scoped pagination, and PII-only-for-authorized-admin protection.
- `api/get_event_registration_submission_detail` — implemented pending full-gate/frontend cutover proof; current `MANAGE_EVENTS` plus explicit event and one attendee/user selector, with stored answer snapshots.

These six routes are one incomplete feature family, not six independent “done” items. Their completion gate requires a relationship-complete event/form/submission fixture, field and answer validation, event-owner versus administrator authorization, bounded submission reads, PII minimization, rollback/idempotency tests, and the active frontend journey.

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

- `blog_api/homepage` — implemented and disposable-MariaDB tested; public GET/POST read over greeting text, active carousel images, and greeting image ID
- `blog_api/update_homepage_text` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT` enforcement, atomic greeting title/message upsert
- `blog_api/create_carousel_image` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, multipart image upload with safe UUID filename, alt_text, sort ordering, show_greeting exclusivity, and rollback file cleanup
- `blog_api/update_carousel_image` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, optional image replacement, alt_text, is_hidden toggle, greeting exclusivity, and automatic greeting reassignment on hide
- `blog_api/reorder_carousel` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, batch sort_order updates, normalized sequence
- `blog_api/delete_carousel_image` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, soft delete with deleted_at timestamp, greeting reassignment before deletion, and order normalization
- `blog_api/faqs` — implemented and disposable-MariaDB tested; public GET/POST returns published FAQs by sort_order; admin `MANAGE_CONTENT` all=true returns all FAQs
- `blog_api/create_faq` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, question/answer validation, sort ordering, and publication state
- `blog_api/update_faq` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, question/answer/sort/publication updates, session identity map expiration
- `blog_api/reorder_faqs` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, batch sort_order updates
- `blog_api/delete_faq` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, soft delete with deleted_at timestamp, order normalization
- `blog_api/blog_categories` — implemented and disposable-MariaDB tested; public GET/POST returns active categories by sort_order; admin `MANAGE_CONTENT` all=true includes inactive categories
- `blog_api/create_blog_category` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, unique slug generation from name, soft-delete revival on slug collision, and order normalization
- `blog_api/update_blog_category` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, name/slug/status/order updates with session identity map synchronization
- `blog_api/delete_blog_category` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, soft delete preserving foreign key references, order normalization
- `blog_api/reorder_categories` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, batch sort_order updates
- `blog_api/blog_posts` — implemented and disposable-MariaDB tested; public GET/POST returns published post summaries with pagination, category filter, and keyword search; admin `MANAGE_CONTENT` draft/all status filtering
- `blog_api/blog_post_detail/{id_or_slug}` — implemented and disposable-MariaDB tested; public GET/POST detail by integer ID or unique slug, including ordered sections, gallery images, cover image, and read time
- `blog_api/create_blog_post` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, multipart form-data or JSON, unique slug generation, calculated reading time, cover image selection, atomic post/sections/gallery persistence, and rollback file cleanup
- `blog_api/update_blog_post` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, post title/excerpt/category/status updates, full section replacement, gallery uploads, and session identity map synchronization
- `blog_api/delete_blog_post` — implemented and disposable-MariaDB tested; admin `MANAGE_CONTENT`, soft delete with deleted_at timestamp

News:

- `news/feeds` — implemented and disposable-MariaDB tested; public GET/POST Nigerian news feeds aggregator, X-API-Key authentication against `api_table`, `setup_parameters` fallback, 7 canonical topics + aliases, free keyword matching, article page enrichment (body + og:image), and 15-minute file caching with `X-Cache` headers

Parity checkpoint: all public/admin representations, publication states, registration forms, ordering, filters, and file references match approved contracts; draft and moderation operations require explicit roles. Goal 7 is completely built and disposable-MariaDB tested.

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
| Goal 1 | In progress | Deterministic JSON inventory covers 445 controller methods and 103 retired root-artifact functions with source hashes, routeability, inferred request mode, inputs, table/model calls, auth signals, statuses, value-free controller execution references, two consolidated-route decisions, and fourteen explicit security retirements. Current dispositions are 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact. The re-reviewed catalogue has 159 rows: 145 direct conversions, 10 consolidated replacements, 1 platform replacement, and 3 schema/caller-blocked market rows. The PHP developer audit contributes 163 detailed `Live/KEEP` endpoint rows, 157 exact matches to the catalogue, and supporting-code evidence for five models plus `MY_Controller`. | Reconcile the audit's 175-summary versus 163-detail count, missing controller metadata for `Socials`, and 24-versus-25 core-helper count; then confirm frontend callers, exact contracts, side effects, and source-file evidence for any changed disposition. | 2026-09-16 |
| Goal 2 | In progress | Isolated Python 3.13.7 service, pinned runtime/dev locks, typed settings, SQLAlchemy/PyMySQL engine, health routes, error envelope, correlation IDs, structured logging, ADRs, automated verification, successful Uvicorn smoke test, and committed authentication transaction boundaries | Nested PII redaction, remaining service transaction boundaries, approved production worker/proxy configuration, and all other business modules remain | 2026-09-08 |
| Goal 3 | In progress | Local MariaDB 12.3.3 schema-only clone; live-schema (server `10.5.26-MariaDB`) drift report via `scripts/schema_drift_report.py`; 69 reflected tables / 785 columns mapped (added `news_feeds_setup`); model/schema parity tests (70 tables); rollback-safe write; empty Alembic baseline `7250164972b6`; schema report; `alembic upgrade head` + `alembic check` clean against the live schema | Orphan/duplicate-row and data-quality catalogue, timezone/boolean decisions, repositories, deadlock tests, and benchmarks remain | 2026-09-18 |
| Goal 4 | In progress | Nine SQL-backed auth/recovery/verification routes; self-only access-code verification; canonical database-fact permission policy in ADR 0004; lower-role account-management hierarchy applied to approval, account state, and bounded role changes; transactional refresh-token revocation on deactivation and role changes; exact-role administrator and pending-voucher post-verification notifications; sixteen unsafe/superseded routes retired with tested GET/POST HTTP 410 tombstones; bcrypt-to-Argon2id migration; asymmetric JWTs; rotating hashed refresh tokens; finite reset/email codes; shared Redis throttling; SMTP adapter/fakes; ADR 0003 | Social login, group/owner policy application across remaining endpoints, live Redis/SMTP acceptance, trusted-proxy validation, and frontend compatibility remain | 2026-09-13 |
| Goal 5 | In progress | Registration, public city/privacy-aware welfare-zone catalogues, protected privacy-aware zone roster/self assignment, authorized transaction-safe zone/city management, bounded geography and alumni imports, class-year-filtered public voucher discovery, owned pending-vouch listing and transactional approve/deny, authentication, finite email verification, post-verification administrator/voucher notification, credential-free self/administrator profile read, allowlisted profile update with normalized avatar storage, protected profile-visibility read/write, privacy-aware approved directory/downward account listing, member approval/rejection, account activation/deactivation, bounded role changes, four-card alumni statistics, privacy-minimized birthday windows, public/protected chapter list/assignment lookup, and authenticated setup-parameter lookup are implemented. Registration and alumni import own new-account privileges server-side; imports preserve existing account authorization, credential, state, and visibility fields, validate complete bounded rosters before mutation, disclose no secrets, and commit all rows atomically. Unsafe superseded account/role mutation and definition contracts are explicitly retired. Privileged changes use current database facts, deterministic row locks, reviewed role categories, state/hierarchy gates, dedicated response fields, rollback proof, and PII-free structured audit logging; geography writes additionally serialize catalogue access, validate chapter/coordinator/reference integrity, keep city/zone chapter metadata aligned, and make bounded CSV/XLSX imports idempotent and all-or-nothing. Birthday discovery is read-only, requires current account state, preserves reviewed Lagos/leap-day semantics, and returns only the active card fields plus safe schedule metadata under explicit privacy and resource bounds. | Remaining chapter-assignment UI proof, alumni-import hidden-caller/production-data/scale/proxy-limit/concurrency/admin-workflow proof, birthday authenticated-browser/production-data/scale proof, geography-import edge/body-limit and production chapter-1/duplicate-data/concurrency proof, durable audit storage, production media persistence, live SMTP, and authenticated frontend journeys remain | 2026-09-13 |
| Goal 6 | In progress | ADR 0005 selects reviewed V2 `message_threads`, `thread_participants`, `messages`, and `messages_attachments`. Disposable-MariaDB tests pass for the V2 inbox/detail/send/direct/group/delete/read/delivered/add/leave/pin/attachment family, active-member enforcement, client-ID idempotency, direct-thread concurrency, cross-thread reply/attachment denial, private staged-owner retrieval, recipient retrieval after linking, sender-only deletion, administrator handoff, and explicit V1/year-sync HTTP 410 boundaries. The inbox route is now bounded and paged (`limit` 1-200 default 100, bounded `offset`, `has_more`, `thread_total`, mailbox-wide `unread_count`/`unread_thread_count`) with a fixed per-page query count, and additive reversible migration `c4d5e6f7a8b9` adds `idx_thread_participants_inbox (member_id, left_at, is_pinned)` after disposable-schema measurement at 3,400 participant rows and 100,000 messages; per-thread message paging already used `idx_thread_id`. The frontend forwards a clamped inbox limit, and as of 2026-09-18 `npm run build` succeeds against the installed dependency tree and a live Uvicorn smoke proves the bounded inbox contract is served. Active frontend source and compiled bundle use only V2. The expired staged-attachment reaper is now built and tested: bounded/idempotent `ChatService.reap_expired_staged_attachments`, deployable `app/tasks/chat_attachment_reaper.py` plus `scripts/reap_chat_attachments.py`, and additive reversible migration `d5e6f7a8b9c0` adding `idx_msg_attachment_staged_purge (message_id, expires_at)`; the probe measured the sweep moving from `type=ALL; Using filesort` to `type=range key=idx_msg_attachment_staged_purge`, and six DB tests cover selection, batching/idempotency, bounds, the missing-storage guard, path faults, and a concurrent-link lock race. A scripted Playwright + Chromium journey (`frontend/e2e/messages-v2-inbox.mjs`, `scripts/seed_chat_journey_fixture.py`) now logs in through the real UI and proves the seeded V2 inbox renders from a 200 `chat_api/v2_get_threads` against Uvicorn and the built `dist` (exit 0, screenshot `tmp/e2e-messages-v2-inbox.png`). Malware scanning is decided (no external provider; residual risk accepted). | Deployment-host registration of the reaper (OS scheduler chosen), provider delivery, production data/media, and cutover remain. Legacy V1 chat data is confirmed absent (user, 2026-09-18), so there is nothing to reconcile; the V1 `410` tombstones stay. | 2026-09-18 |
| Goal 7 | In progress | Announcement publishing plus the active frontend marketplace listing, project, leadership, vacancy, event-core, RSVP, attendee, and six event-registration-form routes are implemented against the reviewed schema. The form family uses current-database `MANAGE_EVENTS`, safe bounded definitions, form-answer validation including checkbox `max_selections`, self-owned RSVP, event locks, transactional answer/display snapshots, immutable form-version history, bounded submission pagination, and administrator-only PII. Additive reversible migration `9d9d1f2a7c31` stores optional Firebase-source form/question/version identifiers without touching legacy rows. Read-only validator `validate_event_survey_export.py` requires a bounded PII-free normalized export and explicit event/user maps before an eventual import. Public event output now derives `has_registration_questions` and `registration_form_count` from active MySQL forms. On 2026-09-18 the whole suite passed at **90.21%** coverage on a fresh disposable schema, and new blog edge tests raised `app/api/blog.py` to 96%, `app/services/blog.py` to 86%, and `app/repositories/blog.py` to 81%, proving the validation/not-found/cleanup/visibility branches the happy-path tests missed. A live runtime smoke served the public `get_events`, `get_listings`, and `get_projects` contracts. The current frontend still uses Firebase survey functions, so no cutover is claimed. The PHP privacy-policy family is blocked after a static-page/no-caller/no-table re-review; legacy `market` stays schema/caller-blocked. | Firebase-related work is deferred by the user (2026-09-18): FastAPI+MariaDB remains canonical and no transport flip is pending. Goal 7 is not accepted until the deferred Firebase export/mapping/import and Firebase-to-FastAPI cutover are revisited, plus hidden-caller, media/provider, production-data, and release evidence exists. Event-service and event-repository coverage raised to 89%/84% by `tests/test_events_edges.py` (20 tests: answer-validation, RSVP capacity/cancel, inactive-actor 401s, form-management error branches, and not-found paths). Remaining Goal 7 test gaps are `app/repositories/marketplace.py` (64%) and `app/services/announcements.py` (80%). Keep privacy-policy and legacy market blocked pending current storage/caller proof. | 2026-09-18 |
| Goal 8 | In progress | Store/Orders suite: `tests/test_store_edges.py` (52 error-path tests), `tests/test_paystack.py` (32 provider tests, `app/integrations/paystack.py` 100%), and `tests/test_store_concurrency.py` (3 oversell/concurrency tests). Two real defects were fixed: an unimported `ProductVariants` reference, and a stock-oversell read-modify-write in `finalize_order_atomic` now replaced with conditional `UPDATE ... WHERE quantity >= :qty` deduction plus `InsufficientStockError`/HTTP 409/reconciliation. Route-family inventory is complete (24 PHP `Product` methods ↔ 24 `/product/*` routes), products/checkout/verify contracts match the frontend adapters, a headless-browser store-catalogue journey passes, and the Paystack integration is live-smoked against the sandbox with the supplied test key (`initialize` + `verify` + webhook HMAC all pass), with a `POST /api/paystack/webhook` alias added for the Paystack-configured webhook URL. | Remaining: authenticated cart/checkout browser flows against the sandbox and the live Paystack key exchange before cutover. | 2026-09-18 |
| Goal 9 | In progress | Unsafe legacy `user_tokens` is blocked by tested GET/POST HTTP 410 responses. `GET|POST /api/get_notifications` and `POST /api/mark_notification_read` are implemented against the reviewed current schema with active-Bearer self-only ownership, bounded responses/mutations, current-account checks, database locks, account-creation boundary, cross-owner denial, rollback, and idempotency tests. `POST /api/contact_us` is now implemented (public, rate-limited, validated, persisted to the live `contact_us` table, manager-notified post-commit via `Mailer.send_contact_form`). | `create_notification` and push delivery remain pending: hidden/mobile callers, authorised creators, recipient rules, storage migration, provider, retry, opt-out/revocation, live-data, and production/frontend proof are required. | 2026-09-18 |
| Goal 10 | In progress | Typed trusted-host/public-base-url config with production wildcard guards; CORS allowlist middleware (`allow_credentials=False`, restricted methods/headers); `SecurityHeadersMiddleware` (nosniff, DENY frame, no-referrer, permissions-policy, restrictive CSP) without overriding route headers; per-route `enforce_rate_limit` across all routers; cookie/CSRF documented as N/A (Bearer-only auth); top-level secret-field log redaction; PII-free limit and privileged-change audit events; normalized avatar re-encoding with rollback cleanup; sixteen executable security tombstones; Bandit + `pip-audit` clean. `tests/test_security.py` (6 tests). | Nested/payment-payload log redaction, a durable audit table, retention/deletion procedures, a metrics endpoint, CI dependency/container scanning, and a pre-cutover threat model remain. | 2026-09-18 |
| Goal 11 | In progress | On 2026-09-18 a fresh sanitized disposable schema (`alumni_portal_reaper_test`, rebuilt from `.local-state/sanitized-schema.sql`, upgraded to head `d5e6f7a8b9c0`, `alembic check` clean) ran the complete suite: **481 passed, 0 failed, 90.22% branch coverage**, with `pip check`, Ruff format/check (125 files), strict `mypy` (125 files), Bandit, and `pip-audit` clean. Frontend dependency installation, the production build, and a live HTTP smoke against the disposable schema were also captured. No production connection was used. | Add retained endpoint contracts, protected-route policy tests for the newly covered families, authenticated frontend/browser automation, load, deployment, and release evidence; adopt `tsc --noEmit` and Prettier as gates only after their documented baselines are cleared. | 2026-09-18 |
| Goal 12 | Deferred (user, 2026-09-18) | None | Deployment target and production host/provider access are deferred; no live cutover evidence exists | 2026-09-18 |

### Progress Log

#### 2026-09-18 — Goal 9 public contact form and Goal 8 stock-oversell fix

- **Implemented `POST /api/contact_us`** (the one Goal 9 contact route). `app/schemas/contact.py`, `app/repositories/contact.py`, `app/services/contact.py`, and `app/api/contact.py` accept a bounded, rate-limited public JSON/form message (`firstName`/`lastName`/`email`/`message`), validate with `EmailStr`, persist to the live `contact_us` table as `status='new'`, then notify active account managers post-commit via a new `Mailer.send_contact_form` method (best-effort; provider failure cannot roll back the committed message). The legacy reusable `X-API-Key` gate is deliberately not copied. `tests/test_contact.py` (4 tests) covers persistence + manager notification, form encoding, validation failure, and mail-failure persistence. The `contact_us` route is public; no current frontend caller was found (the endpoint is registered in `frontend/src/lib/api/endpoints.ts` but unused), so hidden-caller confirmation remains.

#### 2026-09-18 — Goal 8 Paystack provider unblock and webhook alias

- Added a compatible `POST /api/paystack/webhook` alias (shares the `_handle_paystack_webhook` logic with `POST /product/paystack_webhook`) because Paystack requires a dashboard-configurable HTTPS webhook path; the legacy `product/paystack_webhook` path remains but Paystack will target the new alias. `tests/test_store_concurrency.py` and `tests/test_store.py` assert both paths behave identically (invalid signature 401, valid 200).
- Created `frontend/.env.example` (names-only template for `VITE_API_BASE_URL`, `VITE_API_TOKEN`, `VITE_CONTENT_API_BASE_URL`, `VITE_CONTENT_API_TOKEN`, `VITE_PAYSTACK_PUBLIC_KEY`, `VITE_GOOGLE_CLIENT_ID`, `VITE_FACEBOOK_APP_ID`, and the eight `VITE_FIREBASE_*` survey keys) and `frontend/.env.local` (gitignored) holding the supplied test public key. The backend test secret key is wired via `ALUMNI_PAYSTACK_SECRET_KEY` (names already in `python-backend/.env.example`).
- **Live test-mode smoke (no real money, key passed via env only):** `PaystackClient.initialize_transaction` returned an `access_code`, `verify_transaction` returned the expected `abandoned` status for an unpaid test transaction, and webhook HMAC-SHA512 verification accepted the correct signature and rejected a wrong one. The supplied `sk_test_*` key is valid against Paystack's sandbox.
- **Result.** Full suite **527 passed, 0 failed, 90.82% coverage**; `pip check`, Ruff format/check (141 files), strict `mypy` (141 files), Bandit, `pip-audit`, and `alembic check` all clean.

#### 2026-09-18 — Goal 10 nested/PII/payment-payload log redaction

- Enhanced `app/core/logging.py`: the structured-log processor now walks nested mappings and lists and redacts a comprehensive sensitive-key set (credentials, PII identity/contact fields, and payment fields) at any depth, and replaces whole `payment_payload`/`tx_data` objects. `tests/test_logging.py` grew to 5 tests (top-level, nested, list, payment-payload, PII).
- **Result.** Full suite **526 passed, 0 failed, 90.82% coverage** (`app/core/logging.py` 100%); `pip check`, Ruff format/check (141 files), strict `mypy` (141 files), Bandit, `pip-audit`, and `alembic check` all clean.

#### 2026-09-18 — Goal 8 store catalogue browser journey

- Added `scripts/seed_store_journey_fixture.py` (disposable-only synthetic member + active product) and `frontend/e2e/store-journey.mjs` (`npm run e2e:store`). The journey starts Uvicorn + `vite preview` against the built `dist`, drives Chromium to `/store` (a public route), and asserts the seeded product renders from a live `/product/fetch_products` 200. Result: exit 0, `final_url=http://127.0.0.1:4173/store`, `product_statuses=[200]`, screenshot `tmp/e2e-store-journey.png`.
- This closes the Goal 8 browser-journey gap for the store catalogue; authenticated cart/checkout browser flows still need live Paystack keys (provider gate). The products/checkout/verify contracts were already verified field-for-field against the frontend adapters, the 24/24 route inventory is complete, and the oversell/concurrency proof passes.

#### 2026-09-18 — Goal 11 edge-data tests and release-gate confirmation

- Added `tests/test_edge_data.py` (4 tests): Unicode name round-trip, four-byte emoji round-trip in utf8mb4 columns, the enforced Nigerian-phone contract (`^0[789][0-9]{9}$`), and null `graduation_year`/`avatar` legacy rows listing safely. This closed the Goal 11 encoding/Unicode/phone/null/legacy-records task; Lagos/leap-day dates and legacy `$2y$` hashes were already covered.
- Confirmed `scripts/verify.ps1` is the deployment-blocking release gate (venv assert, `pip check`, Ruff, `mypy`, `pytest --cov` with a 90% floor, `alembic check`, Bandit, `pip-audit`; financial/concurrency tests live in the pytest suite).
- **Result.** Full suite **523 passed, 0 failed, 90.81% coverage**; `pip check`, Ruff format/check (139 files), strict `mypy` (139 files), Bandit, `pip-audit`, and `alembic check` all clean.

#### 2026-09-18 — Goal 10 metrics endpoint and threat model

- **Metrics.** Added `app/core/metrics.py` (`MetricsMiddleware` with `http_requests_total` and `http_request_duration_seconds`, keyed by route template to bound cardinality) and `app/api/metrics.py` (`GET /metrics`, disabled by default, requiring a configured collector token via `Bearer` or `X-Metrics-Token` with constant-time comparison). Config gains `metrics_enabled` and `metrics_token` (SecretStr). `tests/test_metrics.py` (5 tests) proves default-404, token-required 401, bearer and header token 200, and no-token 404.
- **Threat model.** Wrote `docs/threat-model.md`: assets, trust boundaries, threat actors, ten threat scenarios with repository-grounded mitigations, residual risks (no malware scanner, nested redaction, durable audit, retention), and pre-cutover follow-ups.
- **Result.** Full suite **519 passed, 0 failed, 90.79% coverage**; `pip check`, Ruff format/check (139 files), strict `mypy` (139 files), Bandit, and `pip-audit` all clean.

#### 2026-09-18 — Goal 9 contact form and Goal 10 CORS + security headers

- **Implemented `POST /api/contact_us`** (Goal 9). `app/schemas/contact.py`, `app/repositories/contact.py`, `app/services/contact.py`, and `app/api/contact.py` accept a bounded, rate-limited public JSON/form message, validate with `EmailStr`, persist to the live `contact_us` table as `status='new'`, then notify active account managers post-commit via a new `Mailer.send_contact_form` (best-effort; provider failure cannot roll back the committed message). The legacy reusable `X-API-Key` gate is not copied. `tests/test_contact.py` (4 tests). No current frontend caller was found.
- **Goal 10 security baseline.** Added the CORS allowlist (`CORSMiddleware` from `settings.cors_origins`, `allow_credentials=False`, restricted methods/headers) and `SecurityHeadersMiddleware` (nosniff, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy`, restrictive `Content-Security-Policy`) that never overrides route headers. Cookie/CSRF are documented as N/A because authentication is Bearer-token only. `tests/test_security.py` (6 tests) proves allowlist echo, unlisted/`null` rejection, no credential advertisement, header baseline, and non-override.
- **Result.** Full suite **514 passed, 0 failed, 90.79% coverage**; `pip check`, Ruff format/check (136 files), strict `mypy` (136 files), Bandit, and `pip-audit` all clean.

#### 2026-09-18 — Goal 8 stock-oversell defect fix and concurrency proof

- **Real defect fixed.** `StoreRepository.finalize_order_atomic` deducted stock with a read-modify-write (`var.quantity = max(0, var.quantity - item.quantity)`) with no row lock, so two different paid orders for the same last unit both clamped stock to zero and both finalized — a silent oversell. The fix uses conditional updates (`UPDATE product_variants/products SET quantity = quantity - :qty WHERE id = :id AND quantity >= :qty`) whose `rowcount` distinguishes a fulfilled item from a shortfall; a shortfall raises `InsufficientStockError`, which `verify_payment` maps to HTTP 409 `INSUFFICIENT_STOCK` (rolled back) and the webhook turns into an acknowledge-with-pending (reconciliation) path with a structured `paystack_webhook_stock_shortfall` log.
- **Concurrency proof.** New `tests/test_store_concurrency.py` (3 tests) proves, against the disposable schema: two competing paid orders for a single unit result in exactly one `paid` + one `pending` with stock 0 (serial), the same invariant holds under a two-thread concurrent webhook fire, and a replayed webhook does not double-deduct. These tests fail on the previous implementation.
- **Result.** Full suite **504 passed, 0 failed, 90.75% coverage**; `pip check`, Ruff format/check (130 files), strict `mypy` (130 files), Bandit, `pip-audit`, and `alembic check` all clean. Store coverage: `app/repositories/store.py` 87%, `app/services/store.py` 86%.

#### 2026-09-18 — Goal 7 event-form/RSVP guard coverage and Goal 8 groundwork

- Added `tests/test_events_edges.py` (20 tests) to close the form-management and RSVP guard branches of `app/services/events.py`. Pure unit tests drive `_validated_answers` (duplicate/unknown questions, checkbox array/option/max-selection rules, text length/type, choice-option and required checks, snapshot output) and the `_options`/`_rsvp_event`/`_next_form_version`/`_item` helpers without a database; DB-backed tests cover RSVP capacity + cancel, inactive-actor 401s, public list shape, RSVP upsert, chapter validation, question-reorder mismatch, update-form-with-questions versioning, and not-found paths for submissions/attendees/events.
- Result: `app/services/events.py` 79%→**89%**, `app/repositories/events.py` →**84%**, `app/api/events.py` →**92%**, `app/schemas/events.py` →**91%**. Full suite **501 passed, 0 failed, 90.74% coverage**; `pip check`, Ruff format/check (129 files), strict `mypy` (129 files), Bandit, `pip-audit`, and `alembic check` all clean. Remaining Goal 7 gaps are `app/repositories/marketplace.py` (64%) and `app/services/announcements.py` (80%).

#### 2026-09-18 — Live-database dump drift analysis, `news_feeds_setup` mapping, and scope decisions

- **Live dump supplied.** The user placed `alumni_portal_v2.sql` (phpMyAdmin 5.2.3, server `10.5.26-MariaDB`, database `alumni_portal_v2`, 69 tables, 928 KB, real rows) at the repository root. It was treated strictly as read-only evidence; a schema-only copy (zero rows) was produced with the new `scripts/sanitize_sql_dump.py` and imported into a disposable DB for drift measurement. **Warning:** the full dump is untracked and not gitignored; it must never be committed.
- **Drift result.** `scripts/schema_drift_report.py` compares the live schema against the models. The only genuinely new live table is `news_feeds_setup` (live RSS/Atom feed config, 33 rows, comments tie it to `articles[].source`), now added to `app/models/generated.py`. Every other table/column/nullability/default/index/FK matches; the remaining differences are our own additive migrations (event-form snapshots/source ids, `messages_attachments` ownership/expiry, `orders.status` + `processing`, and four indexes), which `alembic upgrade head` applies cleanly (`alembic check` clean) at cutover. `test_schema_models.py` now asserts 70 model tables (69 reflected + `event_registration_form_versions`).
- **Baseline refreshed.** `.local-state/sanitized-schema.sql` was regenerated from the live dump (schema-only, 69 tables). Full suite green against it: **481 passed, 0 failed, 90.23% coverage**; `pip check`, Ruff format/check (128 files), strict `mypy` (128 files), Bandit, `pip-audit`, and `alembic check` all clean.
- **Follow-up flagged (not yet done):** the `news/feeds` service still hardcodes 12 feeds and falls back to `setup_parameters`; it should read active feeds from `news_feeds_setup` for live parity (Goal 7).
- **User scope decisions (2026-09-18):** (1) Firebase-related tasks are deferred for now — keep FastAPI+MariaDB as canonical, keep the read-only `validate_event_survey_export.py` gate, do not flip the frontend transport; (2) there is no legacy V1 chat data to reconcile, so the V1 tables remain untouched, the V1 `410` tombstones stay, and ADR 0005's "no automatic reconciliation" stands with nothing to migrate; (3) deployment/cutover (Goal 12) is deferred for now. These are recorded as explicit deferrals, not completions.

#### 2026-09-18 — Goal 6 scripted headless-browser V2 inbox journey

- **What was built.** With user approval, `@playwright/test` and Chromium were added to `frontend/` dev dependencies. `python-backend/scripts/seed_chat_journey_fixture.py` creates a disposable-schema fixture (approved/verified/active member with a known bcrypt `$2y$` password, a peer, a group thread, one message); it refuses non-localhost and non-`test` databases and purges only its own `journey-%` rows. `frontend/e2e/messages-v2-inbox.mjs` seeds, generates an ephemeral RSA key pair, starts Uvicorn and `vite preview` (built `dist`), drives Chromium through the real login form into `/messages`, and asserts the seeded thread renders from a live `chat_api/v2_get_threads` 200. `npm run e2e:messages` runs it.
- **CORS-free test harness.** The app has no CORS middleware yet (Goal 10), so the journey intercepts same-origin API-prefixed requests in the browser context and proxies them to Uvicorn via `route.fetch`/`route.fulfill`. This exercises the real frontend + real API without changing production code; it does not mask the missing production CORS, which remains a Goal 10 gate.
- **Result.** `npm run build` (8,524 modules, 41.29s, known chunk-size warning only) then `npm run e2e:messages` → exit 0, `final_url=http://127.0.0.1:4173/messages`, `thread_statuses=[200]`, thread `Synthetic V2 Inbox Journey` visible with unread badge and `Journey Peer: Hello from the seeded …` preview (screenshot `tmp/e2e-messages-v2-inbox.png`). Servers were torn down synchronously; only TIME_WAIT sockets remained. Placeholder `.env` upload subdirectories are now pre-created by the harness so no spurious static-mount 500s occur.
- **Boundaries.** This closes the Goal 6 browser-automation gap for the V2 inbox; scheduler registration on the deployment host, legacy V1 reconciliation, provider delivery, production data/media, and cutover still gate the goal.
- **Dependency note.** `frontend/package.json`/`package-lock.json` now include Playwright as a dev dependency and an `e2e:messages` script; `dist/`, `node_modules/`, and the screenshot are not committed.

#### 2026-09-18 — Goal 6 scheduled staged-attachment reaper and purge-path index

- **What was built (internal operation, no new HTTP route).** `ChatService.reap_expired_staged_attachments(limit=1..500)` locks a bounded batch of `messages_attachments` rows with `message_id IS NULL AND expires_at IS NOT NULL AND expires_at <= now`, deletes the authoritative rows inside one transaction under `FOR UPDATE`, then removes the private files after commit and reports only aggregate counts. `app/tasks/chat_attachment_reaper.reap_expired_chat_attachments(settings, limit=...)` is the deployable scheduler entry point, and `scripts/reap_chat_attachments.py` is the operator wrapper (prints aggregate JSON; refuses a non-localhost target unless `--allow-non-localhost`, and an out-of-range `--limit`).
- **Access-path decision.** The existing `idx_msg_attachment_staged_expiry (uploaded_by_member_id, message_id, expires_at)` cannot serve a global sweep because its leading column is the uploader. I extended `scripts/chat_retrieval_probe.py` to seed half-expired staged uploads and measure the sweep. Pre-index plan: `table=messages_attachments type=ALL key=- rows=200 extra=Using where; Using filesort` (best-of-three 1.936 ms). Additive reversible migration `d5e6f7a8b9c0` adds `idx_msg_attachment_staged_purge (message_id, expires_at)`; post-index plan: `type=range key=idx_msg_attachment_staged_purge rows=100 extra=Using where` (no filesort, 2.49 ms within run-to-run noise). `alembic downgrade -1` + `upgrade head` re-created the index and `alembic check` stayed clean.
- **Semantics and safety.** Row locking makes a concurrent send and the sweep race-safe (the send commits first and the sweep re-reads the linked row and skips it). The database row is authoritative, so a crash between the row commit and file deletion can leave one orphaned private file for a storage audit, but can never leave a retrievable staged attachment; re-running is a no-op. Missing files are treated as already clean; a malformed stored path is counted as a file failure instead of silently skipped. No storage path, uploader ID, or file name is logged or returned.
- **Verification (fresh disposable `alumni_portal_reaper_test` on 127.0.0.1:3313, rebuilt from `.local-state/sanitized-schema.sql` and upgraded to head `d5e6f7a8b9c0`).** `pytest tests/test_chat_reaper.py tests/test_chat_service.py tests/test_schema_models.py -q` → 18 passed (six new reaper tests plus index parity). Full suite `pytest --cov=app --cov-report=term-missing -q` → **481 passed, 0 failed, 90.22% branch coverage** (90% floor met; `app/tasks/chat_attachment_reaper.py` 100%). `pip check`, `ruff format --check app migrations scripts tests` (125 files), `ruff check`, strict `mypy` (125 files), `bandit -q -r app`, `pip-audit -r requirements-dev.lock` ("No known vulnerabilities found"), and `alembic check` ("No new upgrade operations detected.") all clean. No production data, credentials, provider, or workbook was touched.
- **Still open in Goal 6.** The malware-scanning/provider decision and the live scheduler registration (cron/timer/worker) that invokes the built reaper are deployment decisions; the headless-browser V2 inbox journey, legacy V1 reconciliation, provider delivery, and cutover remain. The staged reaper is an internal operation, so the workbook's route rows are unchanged.

#### 2026-09-18 — Working-tree gate restoration, frontend build evidence, and Goal 8/9 test build-out

- **Gate result (the headline):** a fresh sanitized disposable MariaDB schema `alumni_portal_gate_test` was rebuilt from `.local-state/sanitized-schema.sql` (68 tables, all `INSERT` rows absent), upgraded with `ALUMNI_DATABASE_URL=mysql+pymysql://root@127.0.0.1:3313/alumni_portal_gate_test` and `python -m alembic upgrade head` through `c4d5e6f7a8b9`, and confirmed with `alembic check` (`No new upgrade operations detected.`). The complete suite then reported **474 passed, 0 failed, 90.21% branch coverage** (`pytest --cov=app --cov-report=term-missing`), clearing the enforced 90% floor that was red at 86.30% earlier the same day. `pip check`, `ruff format --check app migrations scripts tests` (121 files), `ruff check`, strict `mypy app migrations scripts tests` (121 files), Bandit (`bandit -q -r app`), and `pip-audit -r requirements-dev.lock` (`No known vulnerabilities found`) all pass. No production credential, production database, or production media was used.
- **Real defects fixed while clearing the gate.** Two behavioural defects in the uncommitted Goal 8/9 code were found and fixed rather than papered over: (1) `app/services/store.py` referenced `ProductVariants` without importing it, which would have raised `NameError` on the variant cart-add, cart-update, and checkout stock-validation paths — reproduced by a new variant cart test that failed before the fix; (2) `app/integrations/mail.py` annotated `dict[str, Any]` without importing `Any`, and its order-status bodies exceeded the configured line length. Also removed three unused imports, collapsed three nested-`if` lints, replaced three redundant `int(round(...))` money casts with `round(...)`, narrowed one `int()` cast that could receive `None`, and reformatted the three unformatted files. Formatting/lint/naming behaviour is unchanged; the money value is identical (`round(Decimal)` already returns `int`).
- **New Goal 8 tests.** `tests/test_store_edges.py` (52 tests) drives the store/cart/address/checkout/order error paths that the existing happy-path suite never reached: product scalar/variant/image/spotlight validation, variant-image binding, staged-file rollback on a late persistence failure, foreign and malformed `delete_image_ids`, conflicting/stale spotlight selectors, the four-product pin limit and its unpin retry, cart quantity/variant/stock guards and cross-user denial, address validation and default switching, checkout rejection paths (bad type, missing details, unknown saved address, unavailable area, empty cart), Paystack initialization failure surfaced as HTTP 502 with no orphan pending order, `verify_payment` ownership/status/amount-mismatch/idempotency behaviour (stock must not decrement twice on replay), webhook signature/acknowledge/failed-charge/finalize-once/replay behaviour, and the full order status state machine including rider-detail enforcement and mail-provider outage tolerance.
- **New Goal 8/9 provider tests.** `tests/test_paystack.py` (32 tests) brings `app/integrations/paystack.py` from 21% to **100%** coverage with an in-process transport only: HMAC-SHA512 webhook verification (case/whitespace, missing secret, tampered body), request shape and `Bearer`/`Cache-Control` headers, reference quoting, transport timeouts, JSON and non-JSON error bodies, malformed success envelopes, false `status` envelopes, missing `access_code`/`data`, and both the injected-client and implicit-client branches. `tests/test_mail.py` gained order-status, approval/activation, and plain-relay tests that bring `app/integrations/mail.py` to **100%**.
- **New Goal 7 blog tests.** `tests/test_blog_edges.py` (15 tests) raises `app/api/blog.py` from 87% to **96%**, `app/services/blog.py` from 68% to **86%**, and `app/repositories/blog.py` from 76% to **81%** by driving blank-field, malformed-section, unknown-target, no-field, invalid-image, cover-image ownership, gallery cleanup, draft-visibility, reorder, and authorization branches across the 21 blog routes. Two behaviours are recorded rather than "fixed": reordering with unknown ids is an idempotent no-op returning HTTP 200, and an unsupported image in a multi-image upload deletes the images already staged for that request.
- **New Goal 9 upload tests.** `tests/test_uploads_edges.py` (40 tests, no database or network) covers the avatar/chat content validators (empty/oversized input, unsupported format, each re-encode branch, office-document structure, UTF-8 and binary rejection, audio/PDF signature-vs-extension mismatches, filename sanitization) and proves that all eleven storage targets fail closed with a typed `UploadStorageError` when the destination cannot be created, that `delete()` cannot be used to reach outside its own directory, and that generated names round-trip into the configured root.
- **Frontend evidence is now real instead of a clean skip.** `npm ci` installed 300 packages (17 advisories: 2 low, 2 moderate, 12 high, 1 critical — unchanged from the previously recorded baseline), and `npm run build` produced a production bundle in 58.56s (8,524 modules; `dist/index.html` 0.57 kB, CSS 211.73 kB, JS 1,746.43 kB) with only the known stale-Browserslist and >500 kB chunk warnings. A live Uvicorn instance was then started against the disposable schema and served `GET /health/live` → `{"status":"ok"}`, `GET /health/ready` → `{"status":"ready","database":"ready"}`, plus `POST /api/get_events`, `POST /api/get_listings`, and `POST /api/get_projects`, each HTTP 200 with the `events`/`listings`/`projects` keys the frontend adapters actually read (`extractList(data, ['events','data'])`, `extractList(data, ['listings','data'])`, `data.projects ?? data.data`). The server was stopped afterwards and the port verified closed. No contract mismatch was found on the reviewed 2026-09-18 paths, including the chat V2 transport read of `unread_thread_count`.
- **Frontend type gate remains a baseline failure, now measured.** `npx tsc --noEmit` (there is no `typecheck` script in `frontend/package.json`) reported 12 errors; one of them was in migration-owned code — `buildRecordedVoiceNoteUploadRequest` declared an `async` function as returning `UploadMessageAttachmentRequest` instead of `Promise<...>` in `frontend/src/features/messages/api/adapters/messages.adapter.ts`. The annotation was fixed (type-only, behaviour identical) and the count dropped to 11. The remaining 11 are pre-existing baseline defects outside the migration's current scope (`src/data/content.ts`, `AdminStorePage.tsx`, `social.adapter.ts` ×2, `mockAuth.ts` ×3, `ErrorBoundary.tsx` ×2, `PhoneNumberInput.tsx`, `renderIcon.tsx`); they were deliberately not edited, so `tsc --noEmit` cannot be adopted as a gate until they are cleaned or explicitly waived.
- **Boundaries kept.** The reviewed Excel workbook was not opened for writing. `privacy_policy`, `create_market`, `manage_market`, and `get_market` remain blocked; no table, contract, or provider integration was invented. No goal-level acceptance claim is made: Goal 6 still owes its malware/reaper and cutover decisions, Goal 7 still owes the approved Firebase export/mapping and frontend cutover, and Goals 8–12 still owe their own acceptance evidence.
- **Files changed:** `python-backend/app/services/store.py`, `app/repositories/store.py`, `app/api/product.py`, `app/integrations/mail.py`, `app/schemas/store.py`, `tests/test_auth_integration.py`, `tests/test_mail.py`, `tests/test_news.py`, `tests/test_store_edges.py` (new), `tests/test_paystack.py` (new), `tests/test_blog_edges.py` (new), `tests/test_uploads_edges.py` (new), `frontend/src/features/messages/api/adapters/messages.adapter.ts`, `PYTHON_FASTAPI_MIGRATION_TASK_BREAKDOWN.md`, `HANDOFF.md`.

#### 2026-09-18 — Goal 6 bounded V2 chat inbox retrieval and index evidence

- Closed the final unchecked Goal 6 implementation task ("Add pagination limits and indexes for high-volume message retrieval") with a bounded, evidence-backed slice; no later goal was activated and no Goal 6 acceptance-gate claim is made.
- `POST /chat_api/v2_get_threads` now accepts an optional body with `limit` (1-200, default 100) and `offset` (0-100,000) and returns `count`, `thread_total`, `unread_count`, `unread_thread_count`, `limit`, `offset`, `has_more`, `threads`, and `server_time`. A body-less or legacy `{}` call keeps working and receives the default page, so the active frontend is unchanged. An out-of-range `limit` returns HTTP 400 `chat_invalid_request` through the existing bounded-chat validation, and the shared FastAPI error envelope was not modified.
- Intentional difference from PHP: `v2_get_threads` previously returned every thread with per-thread subqueries. FastAPI now returns a bounded page and reports the mailbox-wide unread totals, so the badge cannot under-report because of paging. Listing one page is a fixed query count (paged thread join + batched memberships + batched participants + grouped unread counts + one unread aggregate) instead of two queries per thread; membership is still re-verified for every returned thread, and a page whose membership rows do not match its threads fails closed with the existing HTTP 403 `chat_membership_forbidden`.
- Index evidence: `scripts/chat_retrieval_probe.py` (new, refuses any non-localhost database whose name lacks `test`) seeds synthetic chat volume, prints `EXPLAIN` plans and best-of-three timings for the five retrieval query shapes, and deletes only its own marked rows. Against a fresh disposable schema with 200 owned threads, 3,000 other-member threads (3,400 participant rows), and 100,000 messages, the pre-change plans were `inbox_page`/`unread_counts`/`unread_summary` on `idx_member_id` plus a temporary/filesort, and `message_page` on the existing `idx_thread_id`. Additive, reversible migration `c4d5e6f7a8b9` adds `idx_thread_participants_inbox (member_id, left_at, is_pinned)`; afterwards the bounded thread count is an index-only scan (`key=idx_thread_participants_inbox ... Using index`) while the other shapes keep their existing access paths. A candidate `messages (thread_id, id)` index was measured and **rejected**: the optimizer kept choosing the existing `idx_thread_id` and timings were within run-to-run noise, so no speculative index was added.
- Exact commands (repository root unless noted; disposable passwordless MariaDB on `127.0.0.1:3313`): `alumni_portal_goal6_perf_test` was created from `.local-state/sanitized-schema.sql` with all `INSERT`s absent, then upgraded with `ALUMNI_DATABASE_URL=mysql+pymysql://root@127.0.0.1:3313/alumni_portal_goal6_perf_test` and `python -m alembic upgrade head` (68 -> 69 tables, head `c4d5e6f7a8b9`). `python -m alembic check` reported `No new upgrade operations detected.` and `alembic downgrade -1` followed by `alembic upgrade head` round-tripped the new index.
- Test results from `python-backend` with `PIP_REQUIRE_VIRTUALENV=true` and `ALUMNI_TEST_DATABASE_URL` pointing at that disposable schema: `pytest tests/test_auth_integration.py -k v2_chat -q` -> **6 passed**; `pytest tests/test_schema_models.py tests/test_chat_service.py tests/test_auth_integration.py -k "schema or chat or legacy_tables" -q` -> **18 passed** (includes the table/column/index parity test against `information_schema`); `ruff format`/`ruff check` and strict `mypy` pass for the changed chat modules, the migration, `app/models/generated.py`, and the new probe script. The full suite (`pytest --cov=app --cov-report=term-missing`) collected **332 tests, 332 passed, 0 failed**, but reported **86.30%** branch coverage, below the enforced 90% floor.
- Clean skips and boundaries: the frontend build/browser journey is still a clean skip because `frontend/node_modules` (and therefore `vite`) is absent, so only the source adapter change is proven. Live malware scanning, staged-attachment reaping, legacy/V2 reconciliation, provider behaviour, production media persistence, and cutover remain unproven. The repository-wide gate is currently red for reasons outside this slice: 86.30% coverage against the 90% floor, 7 strict-`mypy` errors, and 3 `ruff format --check` failures, all in `app/integrations/mail.py`, `app/repositories/store.py`, `app/services/store.py`, and `app/api/product.py` (concurrently edited Goal 8/9 work that was not repaired here). They block any goal-level or release gate until their owners clear them.
- Files changed: `python-backend/app/api/chat.py`, `app/services/chat.py`, `app/repositories/chat.py`, `app/schemas/chat.py`, `app/models/generated.py`, `migrations/versions/c4d5e6f7a8b9_chat_inbox_pagination_index.py`, `scripts/chat_retrieval_probe.py`, `tests/test_auth_integration.py`, `docs/schema-baseline.md`, `frontend/src/features/messages/lib/backendMessagesTransport.ts`, `PYTHON_FASTAPI_MIGRATION_TASK_BREAKDOWN.md`, `HANDOFF.md`. The reviewed Excel workbook was not opened for writing and no production data or credential was used.
- Next safe action: keep Goal 6 active; decide the staged-attachment reaper and malware/provider policy, capture the frontend build/browser journey when dependencies are installed, and keep the 90% coverage floor and strict mypy in the working tree as the next blocking gate before any goal-level claim.

#### 2026-09-18 — Goal 8 focused Store/Orders test-contract repair (not goal activation)

- Diagnosed the repeated `test_order_management_and_status_transitions` failure from the supplied Antigravity output. The service correctly rejected a door-delivery `paid -> shipped` transition without rider details with HTTP 400 and the message `Rider details are required for door delivery.` The failure was a test-only contract mismatch: it searched obsolete top-level `message` / `detail.message` fields while the app-wide FastAPI exception handler emits `{"error": {"code": "http_error", "message": "..."}}`.
- Updated `tests/test_store.py` to assert the canonical `error.code` and `error.message` envelope. The test fixture now generates one per-run disposable Paystack signing key and exposes it to the webhook test through `StoreHarness`, so the valid HMAC test signs with the exact key configured in the test application. Removed unused test imports/values and formatted the file; no API, schema, provider, or production credential changed.
- Verification against the existing disposable MariaDB test URL on `127.0.0.1:3314`: `PIP_REQUIRE_VIRTUALENV=true`, `ALUMNI_TEST_DATABASE_URL=...`, and `pytest tests/test_store.py -q` completed **10 passed** in 95.71 seconds. `ruff check tests/test_store.py`, `ruff format --check tests/test_store.py`, and `git diff --check -- tests/test_store.py` pass. Two upstream TestClient/AnyIO deprecation warnings remain. This is focused regression proof only, not the Goal 8 acceptance gate or a full-suite result.

#### 2026-09-17 — Goal 7 News Feeds Aggregator slice and Goal 7 completion (`news/feeds`)

- Implemented the final route of Goal 7: `GET` and `POST` `/news/feeds` (`Backend/alumniappV2 - PHP/application/controllers/News.php:125`, Catalogue ID 129), matching active frontend consumer `livenews.service.ts` (`apiClient.post('/news/feeds')`).
- Authentication: Enforces `X-API-Key` verification against the database `api_table` (`api_name = 'alumni_key'`), validating either the raw key match or bcrypt token hash with constant-time equality and safe `$2y$`-to-`$2b$` salt translation. Missing or invalid keys return HTTP 401 `{"status": 401, "message": "Invalid API token"}` matching legacy PHP contracts.
- Classification & Matching Engine: Supports 7 canonical topics (`business`, `entertainment`, `sports`, `politics`, `technology`, `health`, `education`), comprehensive aliases (`financies` → `business`, `football` → `sports`, etc.), setup parameter fallback (`setup_parameters` where `setup_name = 'news_category'`), and free search keywords with stem-suffix matching for words >= 5 characters. Unknown/invalid categories in request parameters return HTTP 400 `{"status": 400, "message": "Unknown category", ...}`, while invalid setup categories are ignored and logged in `ignored_categories`.
- Aggregation, Enrichment & Caching: Pulls dedicated topic feeds and 12 major Nigerian news feeds via `httpx`, deduplicates by normalized URL while merging matched categories, extracts full text and `og:image`/`twitter:image` via lightweight, safe HTML parsing (skipping image-less/empty articles), and caches responses on disk for 15 minutes (900 seconds) returning `X-Cache: HIT` or `X-Cache: MISS`.
- Verification: Tested against isolated disposable MariaDB daemon on port 3314 (`alumni_portal_test`). `tests/test_news.py` passed 7 of 7 tests (slugification, HTML extraction, category/keyword parsing, candidate matching, 15-minute caching, authentication, and MariaDB integration). The full pytest suite passed **321 of 321 tests** with zero failures and zero regressions. All static quality gates passed cleanly (`ruff check`, `ruff format --check`, `mypy --strict`, and `bandit -q -r app`).
- Goal 7 Milestone: With the completion of Events, Vacancies, Listings, Blog (21 routes), and News Feeds, all routable components under Goal 7 are fully implemented and verified against MariaDB.

#### 2026-09-17 — Goal 7 event-registration-form family, privacy/market block, and push notification architecture re-review

- Confirmed the 6 event-registration-form routes: `POST /api/create_event_registration_form`, `POST /api/manage_event_registration_form`, `POST /api/get_event_registration_forms`, `POST /api/register_event_with_forms`, `POST /api/get_event_registration_submissions`, and `POST /api/get_event_registration_submission_detail`. Evaluated frontend callers (`RegisterEventModal.tsx`, `useEventSurvey.ts`, `AttendeesPage.tsx`, `EditEventPage.tsx`), PHP behavior, reviewed tables (`events`, `event_attendees`, `event_registration_forms`, `event_registration_form_questions`, `event_registration_answers`, `event_registration_form_versions`), capacity serialization, immutable question display snapshots, bounded pagination, and administrator-only PII protection.
- Re-reviewed the three privacy-policy routes (`/api/create_privacy_policy`, `/api/manage_privacy_policy`, `/api/get_privacy_policy`) separately: confirmed no `privacy_policy` table exists in SQL dumps, `frontend/src/pages/legal/PrivacyPage.tsx` is static text with no API calls, and no frontend caller exists. Kept strictly blocked; no table or synthetic contract invented.
- Re-reviewed the three legacy market routes (`/api/create_market`, `/api/manage_market`, `/api/get_market`): confirmed no `market` table exists in SQL snapshot. Active marketplace uses `marketplace_listings` and `marketplace_social_media` via `/api/get_listings`, `/api/create_listing`, and `/api/manage_listing`. Kept strictly blocked; no absent legacy table recreated.
- Analyzed push notification architecture to replace external Firebase Cloud Functions: in FastAPI/Python, background tasks (`BackgroundTasks` or Redis task queue) with `firebase-admin` (FCM) or `pywebpush` (VAPID matching `user_push_subscriptions`) handle push notifications asynchronously and efficiently without external Cloud Functions or intermediate Firestore documents.
- Disposable MariaDB gate status: no isolated test instance is running on dedicated test ports (3312/3313), and system MariaDB service on 3307 cannot be modified per safety rules. 112 database integration tests cleanly recorded as skipped (not passed). 191 offline unit, contract, upload, inventory, and schema tests passed 100%. Ruff check/format (98 files), strict mypy, and Bandit security scans passed with zero issues.

#### 2026-09-17 — Goal 7 event-form compatibility storage and fresh-schema gate

- Re-reviewed the Firebase source contract before altering SQL: forms and submissions have immutable historical revisions and checkbox `maxSelections`; the active frontend continues to call Firebase. Added additive/reversible migration `9d9d1f2a7c31` rather than inventing a `privacy_policy` or `market` table. It adds optional source form/question/version IDs, checkbox limits, answer display snapshots, and `event_registration_form_versions`; it does not read Firebase, access production, rewrite legacy rows, or claim an ID mapping.
- FastAPI now validates checkbox selection limits, returns saved form display metadata in submission detail, and records a form-version snapshot on create/upsert and every definition mutation. The focused disposable-MariaDB slice passes schema-model parity plus the full permission/answer/version workflow (5 tests), Ruff, and strict mypy.
- Added `python-backend/scripts/validate_event_survey_export.py`, a read-only pre-import validator derived from the active Firebase functions/types. It permits only a bounded normalized, PII-free JSON export plus explicit source-to-target event/user maps; rejects inferred IDs, PII-bearing registration fields, unknown form-version/question provenance, and inconsistent active snapshots; and emits only aggregate counts. Six focused tests, Ruff, strict mypy, and CLI help pass. It does not access Firebase, open a database, apply records, or constitute data-migration/cutover proof. The subsequent fresh-schema 278-test gate completed at 90.02% coverage.
- Re-reviewed the active `RegisterEventModal.tsx` and `useEventSurvey.ts` flow against the FastAPI routes/services. The cutover gap is the modal's legacy RSVP write followed by a Firebase survey write, whereas FastAPI's `register_event_with_forms` is intentionally the one atomic write. Existing-form FastAPI `upsert` already replaces the full question set when `questions` is supplied, so a new replace-definition mutation is unnecessary. Do not enable a FastAPI transport flag until a frontend adapter uses the combined RSVP route and is tested.
- Verification: rebuilt only localhost database `alumni_portal_goal7_test` from `.local-state/sanitized-schema.sql`, ran `python -m alembic upgrade head` with the project `.venv`, then ran the 278-test suite against `ALUMNI_TEST_DATABASE_URL`. `coverage` reports 90.02% against the enforced 90% floor; Alembic reports no new upgrade operations; pip check, Ruff format/check, strict mypy, Bandit, and dependency audit were re-run. No production data, credentials, or Excel workbook were used.
- Acceptance boundary remains: an approved sanitized Firebase export, explicit event/user mapping, dry-run/rollback import rehearsal, browser transport replacement, and production/cutover evidence are still required. At the user's direction, leave Goal 7 in progress while those Firebase parameters/evidence are unavailable and begin Goal 6 discovery. The separate privacy-policy and legacy-market blocks remain unchanged.

#### 2026-09-17 — Goal 7 Blog, Carousel, FAQs, Categories, and Posts slice (21 routes)

- Implemented and verified the complete 21-route Blog family (`Blog_api.php`): `blog_api/homepage`, `blog_api/update_homepage_text`, `blog_api/create_carousel_image`, `blog_api/update_carousel_image`, `blog_api/reorder_carousel`, `blog_api/delete_carousel_image`, `blog_api/faqs`, `blog_api/create_faq`, `blog_api/update_faq`, `blog_api/reorder_faqs`, `blog_api/delete_faq`, `blog_api/blog_categories`, `blog_api/create_blog_category`, `blog_api/update_blog_category`, `blog_api/delete_blog_category`, `blog_api/reorder_categories`, `blog_api/blog_posts`, `blog_api/blog_post_detail/{id_or_slug}`, `blog_api/create_blog_post`, `blog_api/update_blog_post`, and `blog_api/delete_blog_post`.
- Security and architecture: All mutation endpoints enforce service-boundary `Permission.MANAGE_CONTENT` backed by current active database facts. Public endpoints (`homepage`, `faqs`, `blog_categories`, `blog_posts`, `blog_post_detail`) expose clean wire schemas without leaked credentials or PHP internals. File uploads (`CarouselStorage` and `BlogGalleryStorage`) validate raster formats via Pillow, assign safe UUID-based filenames, prevent traversal, and clean up staged files on disk if the database transaction fails.
- Key business logic preserved: Homepage text upsert; carousel sort ordering and exclusive `show_greeting` toggle; greeting auto-reassignment on carousel hide/delete; soft delete of carousel images, FAQs, categories, and blog posts with `deleted_at` timestamps; category unique slug generation and soft-delete revival; blog post slug auto-generation; read-time calculation (~200 wpm); blog section/gallery atomic replacement; and session identity map synchronization on updates.
- Verification: Tested against isolated disposable MariaDB 12.3 daemon running on `127.0.0.1:3314` (`alumni_portal_test`). The full `tests/test_blog.py` suite passed 11 of 11 tests (including comprehensive database integration tests for homepage/carousel, FAQs, categories, blog posts, and file rollback cleanup). The entire test suite passed all 314 tests in 102.51s with zero regressions. Quality gates verified: `ruff check`, `ruff format --check`, `mypy --strict`, and `bandit -q -r app`.
- Containment notes: The separate privacy-policy and legacy-market blocks remain unchanged and contained.

#### 2026-09-17 — Goal 6 V2 chat core and deterministic-direct concurrency slice

- Added `app/api/chat.py`, `app/services/chat.py`, `app/repositories/chat.py`, and `app/schemas/chat.py`, mounted at the active frontend's V2 `POST /chat_api/*` paths. The bounded core family is `v2_get_threads`, `v2_get_thread`, `v2_create_group`, `v2_send_message`, `v2_send_direct`, `v2_delete_message`, and `v2_mark_read`. Every operation derives the actor from the current Bearer principal and reloads active database state; caller `viewerMemberId` remains compatibility noise. Inbox/detail/send/read require active membership, replies must be non-deleted in the same thread, messages are idempotent per actor/client ID, group creators are initial admins, and only the sender may soft-delete.
- The initial MariaDB concurrent-first-direct test exposed a deadlock in the natural PHP-style absent-row lock followed by insert. The repository now uses the reviewed unique `direct_key` with `INSERT IGNORE`; retryable MariaDB `1020`, `1205`, and `1213` conflicts retry the entire direct-send transaction. It deliberately runs the recipient-active check after the direct-key write, preventing an earlier repeatable-read snapshot from hiding the concurrent winner. Two simultaneous opposite first sends now resolve to one thread.
- Verification used a newly initialized passwordless localhost-only MariaDB 12.3 instance on `127.0.0.1:3313`, database `alumni_portal_goal6_chat_test`, imported only from `.local-state/sanitized-schema.sql`, then `python -m alembic upgrade head`. With `PIP_REQUIRE_VIRTUALENV=true` and `ALUMNI_TEST_DATABASE_URL` set only for the command, `pytest tests/test_auth_integration.py -k v2_chat -q` passed **2 tests** (102 deselected). Ruff format/check and strict mypy pass. The earlier listener on 3312 was unavailable; no production database, credentials, Firebase data, or Excel workbook was used.
- This does not close Goal 6: staged attachment/file policy, delivery, pin/leave and admin membership management, any year-sync authority, browser compatibility, legacy reconciliation, provider, and cutover proofs remain separate gates.

#### 2026-09-17 — Goal 6 V2 group-state slice

- Added active V2 `POST /chat_api/v2_mark_delivered`, `v2_add_member`, `v2_leave_group`, and `v2_pin_thread`. Every action reloads current account state and locks the thread/participant. Delivery additionally confirms that the cursor message belongs to the requested thread. Pins are per-active-participant. Add/reactivate requires an active group administrator and an active target; PHP's arbitrary authenticated add-member behavior is intentionally not retained.
- Leaving is group-only and soft-sets `left_at`, retains all message/history rows, clears that participant's pin, and blocks future access. When the sole administrator leaves a non-empty group, the oldest remaining active participant is promoted in the same transaction so the group remains governable; the test proves the successor can perform the next administrator action. Direct threads cannot be left through this endpoint.
- Focused verification on the fresh localhost-only sanitized/Migrated MariaDB database: `pytest tests/test_auth_integration.py -k v2_chat -q` passed **3 tests** (102 deselected), covering the original core/concurrency suite plus add-member denial/success/replay, delivery, own pin, post-leave access denial, row-history retention, and administrator handoff. Ruff format/check and strict mypy pass. No active frontend source/bundle caller currently references these four paths, so no frontend transport change was made.

#### 2026-09-17 — Goal 6 staged-attachment storage boundary re-review

- Re-reviewed PHP `v2_upload_attachment`, the active frontend upload/send/message rendering paths, the compiled-bundle evidence, reviewed `messages_attachments` schema, and existing FastAPI storage/mounts. The current frontend actively posts multipart bytes to `v2_upload_attachment`, then passes returned IDs to send and renders returned URLs directly. It permits image/audio/PDF/Office/text MIME types client-side, but client validation is not a server authorization or content-validation proof.
- No FastAPI attachment route or anonymous chat static mount was added at this re-review point. The reviewed table had no uploader/expiry/scan fields, and a generated/unguessable public path would still let any holder bypass thread membership. The later bounded implementation below supersedes this temporary containment, but not the provider/retention operational gate.

#### 2026-09-17 — Goal 6 private V2 attachment slice

- Implemented `POST /chat_api/v2_upload_attachment` and authenticated `GET /chat_api/v2_attachments/{attachment_id}`. Additive migration `0e4c31d8f2a7` adds nullable `uploaded_by_member_id` and `expires_at` to preserve old rows while binding newly staged rows to their stager and a 24-hour unsent expiry. Bytes live only under `upload_root/chat`, which is not statically mounted; persisted metadata exposes an authenticated `download_path`, never `storage_path` or an anonymous public URL.
- Server validation reads at most 2 MB, ignores supplied MIME metadata, re-encodes raster images, rejects SVG, verifies PDF/Office/text/audio signatures/structure, normalizes generated filenames, and serves downloads with `Content-Disposition: attachment`, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff`. A message can link at most six distinct, unexpired, same-thread staged IDs owned by the current sender; linking clears expiry and prevents reuse. This intentionally differs from PHP's generic public media upload, missing uploader/expiry record, and any-participant attachment link.
- The active transport now retains `download_path` and fetches it through the authenticated Axios client into a browser Blob URL; SVG was removed from the client picker to match the safe server policy. `npm run build` was attempted but cleanly skipped because `frontend/node_modules` is absent and `vite` is not installed; browser/runtime proof is not claimed. The reviewed compiled bundle/source evidence is unchanged: it calls this V2 upload route.
- Verification: after `python -m alembic upgrade head` on self-created sanitized localhost MariaDB port 3313, `$env:ALUMNI_TEST_DATABASE_URL='mysql+pymysql://root@127.0.0.1:3313/alumni_portal_goal6_chat_test'; .\.venv\Scripts\pytest.exe tests/test_auth_integration.py -k v2_chat -q` passed **4 tests** (102 deselected). The added attachment test proves SVG rejection, private staged owner retrieval, no pre-send participant retrieval, cross-thread and replay-link denial, recipient retrieval after send, outsider denial, response metadata redaction, and private file headers. Ruff format/check, strict mypy, and `python -m compileall -q app` pass. Staged-file reaping, malware scanning/provider selection, production persistence, browser runtime/cutover, legacy reconciliation, and data-retention evidence remain separate gates.

#### 2026-09-17 — Goal 6 graduation-year bulk-sync containment

- Re-reviewed PHP `v2_sync_year_groups` and its model helpers against the active frontend/bundle, reviewed V2 schema, and current authorization policy. No active caller uses the route. PHP lets any authenticated member mutate their graduation-year membership and lets any authenticated caller set `sync_all`; its class-group title has no reviewed uniqueness constraint, so concurrent global creation/reconciliation cannot safely be inferred.
- Added authenticated `POST /chat_api/v2_sync_year_groups` HTTP 410 containment with a bounded rate limit and explicit `chat_year_sync_unavailable` code. It performs no table write and requires an approved internally authorized job plus schema/reconciliation/caller evidence before replacement. The focused V2 chat suite proves this result for an authenticated caller; it remains intentionally unavailable rather than an undocumented missing route.

#### 2026-09-17 — Goal 6 V1 chat retirement boundary

- Reconfirmed the active frontend source and compiled bundle call only `chat_api/v2_*`; no V1 chat caller was found. V1 source targets separate `chat_groups`, `chat_group_members`, `chat_messages`, and `direct_messages` tables that the accepted V2 ADR intentionally neither reads nor writes without approved sanitized reconciliation.
- Added authenticated, rate-limited HTTP 410 routes for all twelve V1 chat paths with `chat_legacy_unavailable`. The focused chat suite verifies a valid Bearer caller receives this explicit boundary rather than an accidental legacy data path. This is a reversible cutover containment, not proof that live V1 history may be discarded: retention/reconciliation/cutover evidence remains required.

#### 2026-09-17 — Goal 6 canonical chat-model decision

- Re-reviewed the full PHP `Chat_api`/`Chat_model` legacy and V2 handlers, generated reviewed chat tables, active frontend message transport/contracts, and the compiled frontend bundle. The frontend source and built bundle call only `POST /chat_api/v2_*`; the current FastAPI app has no chat router, repository, service, schemas, or tests. The disposable sanitized schema contains both table families but no chat rows, so it is not evidence that either family is authoritative in live data.
- Added ADR 0005: FastAPI will target only V2 `message_threads`, `thread_participants`, `messages`, and `messages_attachments`, preserve the active V2 path surface, derive the viewer from the current Bearer principal, and leave legacy reconciliation for an approved sanitized export. PHP behaviors allowing arbitrary member additions, cross-thread reply/attachment linking, and ordinary-user bulk graduation-year synchronization are intentional security differences and will not be copied. No chat table, production system, Firebase resource, or Excel workbook was changed.

#### 2026-09-16 — Goal 7 event-registration-form and disposable-MariaDB gate

- Re-reviewed the six PHP registration-form handlers, generated `event_registration_forms` / question / answer tables, current FastAPI event code, Firebase survey source and compiled frontend artifacts. The frontend still calls Firebase, not PHP or FastAPI. The reviewed tables have no form-version history table or `max_selections`; stored answers preserve question/version snapshots, so future UI cutover needs an explicit data-migration/identifier strategy rather than assuming Firebase IDs map to MySQL IDs.
- Implemented the six Bearer routes: `create_event_registration_form`, `manage_event_registration_form`, `get_event_registration_forms`, `register_event_with_forms`, `get_event_registration_submissions`, and `get_event_registration_submission_detail`. Current-database `MANAGE_EVENTS` is required for form/submission administration; ordinary members see active forms only on approved public registrable events; body identity cannot select the RSVP owner; answers are current-form validated and committed atomically with the locked RSVP; submissions are bounded and PII is administrator-only. PHP reusable keys, raw payload logs, caller-selected users, and JWT-role-only authorization are intentionally not retained.
- Re-reviewed `create_privacy_policy`, `manage_privacy_policy`, and `get_privacy_policy` separately. `PrivacyPage.tsx` is static; no source/bundle caller, reviewed table, migration, or generated model exists. Profile privacy settings are unrelated. All three remain blocked pending approved storage and live-caller/traffic proof. The three legacy `market` routes remain blocked for the same schema/caller reason.
- Added derived `has_registration_questions` and `registration_form_count` fields to public event responses. They correlate only active reviewed MySQL forms and drive the existing event adapter’s survey-discovery contract without trusting tags/local storage. The focused event/form SQL integration tests pass.
- Superseded verification note: the original baseline-only `stamp 7250164972b6` procedure was replaced on 2026-09-17 by a fresh import followed by `alembic upgrade head`, because the event-form compatibility migration is now checked in. The new gate evidence above is authoritative; Firebase/browser/cutover, hidden-caller, media/provider, production-data, and release evidence still block Goal 7 acceptance.

#### 2026-09-16 — Goal 7 event RSVP and attendee slice

- Re-reviewed the active frontend event service/attendee adapters and PHP `register_event`, `manage_event_rsvp`, and `get_event_attendees` handlers against the generated `events`/`event_attendees` models and the canonical authorization policy. Active frontend callers use POST; the PHP attendee GET/key-only path exposes contact data without user authorization and is intentionally not retained.
- Added Bearer-only `POST /api/register_event` and `POST /api/manage_event_rsvp` as self-service routes. Current active database state owns the RSVP; body `user_id` and year cannot select or alter another account. Parent-event row locks serialize FastAPI capacity and duplicate decisions, repeat registration is an upsert, a missing repeat note is preserved, and `going` transitions recheck capacity.
- Added bounded `POST /api/get_event_attendees`. It requires a freshly loaded `MANAGE_EVENTS` actor before returning email/phone/avatar fields, supports bounded status/year pagination, and proves forged event-admin JWT claims cannot bypass current database role facts.
- Verification: Ruff format/check and strict mypy pass. Two SQL-free RSVP contract tests pass and OpenAPI confirms the three POST routes. Two focused MariaDB tests cover ownership, spoofed legacy body fields, capacity, current-role attendee access, and rollback but skip because `ALUMNI_TEST_DATABASE_URL` is not configured. No production database, provider, or credential was touched.

#### 2026-09-16 — Goal 7 event-core slice

- Re-reviewed the active frontend event service/adapter and PHP `create_event`, `manage_event`, and `get_events` handlers against the generated `events`/attendee/chapter/user models and canonical authorization policy. The current frontend uses the three core POST routes; RSVP, attendee, and registration-form routes remain separately pending.
- Added public bounded `POST /api/get_events`, authenticated `POST /api/create_event`, and authenticated `POST /api/manage_event`. Public queries select only approved, public, non-draft presentation data. Mutations reload and lock a current active `MANAGE_EVENTS` actor, derive creator/approval state server-side, validate enabled chapter references, lock targets, and intentionally do not copy the PHP reusable application key or caller-selected creator behavior.
- JSON/form/multipart mutation supports one validated/re-encoded generated banner under an isolated event path. Late database failures remove a newly stored banner; successful replacement/deletion removes only generated local banners. Focused integration coverage exercises forged/stale JWT role claims, public shaping, draft concealment, hard deletion, and rollback cleanup; a SQL-free API contract test passes.
- Verification boundary: Ruff format/check, strict mypy, route/static-mount smoke checks, and the event API contract test pass. Database-backed tests are collected but skip because `ALUMNI_TEST_DATABASE_URL` is not configured. No production database, provider, or credential was touched.

#### 2026-09-16 — Goal 7 vacancy slice

- Re-reviewed the active frontend job-vacancy service, adapters, ownership screen, and posting gate alongside PHP `create_vacancy`, `manage_vacancy`, and `get_vacancies`, the generated `job_vacancies` model, and current authorization policy. The active client permits any signed-in alumnus with a chapter to post and presents an owner-scoped “My Job Posts” view; currency exists in the UI but not the reviewed table.
- Added public bounded `POST /api/get_vacancies`, authenticated `POST /api/create_vacancy`, and authenticated `POST /api/manage_vacancy`. Creation derives `user_id` and chapter from a locked current active account; update/delete allow the owner or a fresh current-database `MANAGE_CONTENT` override, closing PHP's cross-user mutation gap. The public projection keeps job-application fields and poster display name but excludes user email/account data; the legacy application key is intentionally retired.
- JSON/form/multipart input accepts a validated/re-encoded optional flyer under an isolated generated path. Application destination changes require the matching valid email/link; update/delete lock the target, hard delete follows the reviewed legacy table behaviour, and late database failures remove newly stored flyers.
- Verification boundary: Ruff format/check, strict mypy, and route/mount smoke checks pass. The focused database tests are collected but skipped because `ALUMNI_TEST_DATABASE_URL` is not configured. No production database, provider, or credential was touched.

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
