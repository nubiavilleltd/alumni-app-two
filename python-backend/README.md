# Alumni Portal FastAPI backend

This directory contains the isolated Python replacement for the legacy PHP backend. It preserves the existing MySQL-compatible schema during the compatibility phase and must never default to production credentials.

## Supported runtime

- Python 3.13.7, pinned in `.python-version`
- SQLAlchemy 2.x with PyMySQL in synchronous mode
- MySQL or MariaDB with `utf8mb4`

## Bootstrap

From this directory in PowerShell:

```powershell
py -3.13 -m venv .venv
.\.venv\Scripts\Activate.ps1
$env:PIP_REQUIRE_VIRTUALENV = "true"
python -m pip install --upgrade pip pip-tools
python -m pip install -r requirements-dev.lock
python -m pip install --no-deps -e .
python -m pip check
```

`requirements.lock` pins runtime dependencies. `requirements-dev.lock` pins the complete development and verification environment. Regenerate them from `pyproject.toml` with `python -m piptools compile`; review all changes before installation.

## Runtime configuration

Copy `.env.example` to an untracked `.env` and supply only environment-appropriate values. The template contains names, not credentials. `ALUMNI_DATABASE_URL` is deliberately unset by default; liveness remains available while readiness returns HTTP 503 until a database is explicitly configured.

Never point local tests, migration commands, or fixtures at production. The local integration suite expects an explicit `ALUMNI_TEST_DATABASE_URL` that names a disposable or sanitized database.

For a local disposable MariaDB database, create `alumni_portal_test`, import
`Backend/alumni_portal_v2.sql` with `--default-character-set=utf8mb4`, and clear
the imported rows before testing. The dump contains sample data, so importing
it without clearing the rows is not a valid integration-test target. Stamp the
empty schema with `python -m alembic stamp 7250164972b6`.

Set `ALUMNI_TEST_DATABASE_URL` for `scripts/verify.ps1`. The verification script
uses that URL for database-backed fixtures and temporarily uses it as
`ALUMNI_DATABASE_URL` only for the Alembic drift check; this keeps configuration
and health tests deterministic when a separate local API process is running.

The authentication routes require an RS256 private/public PEM pair (or an ES256
pair when `ALUMNI_JWT_ALGORITHM=ES256`). Supply the private key through
`ALUMNI_JWT_SIGNING_KEY` and the matching public key through
`ALUMNI_JWT_VERIFICATION_KEY`; never store either value in this repository. In a
local PowerShell session, PEM files can be loaded from an approved secure location
with `Get-Content -Raw`.

Authentication attempts use hashed, PII-free fixed-window keys. Development and
tests default to process-local counters. Production configuration is rejected unless
`ALUMNI_AUTHENTICATION_RATE_LIMIT_BACKEND=redis` and `ALUMNI_REDIS_URL` names an
approved shared Redis service; Redis failures fail closed with HTTP 503. The reverse
proxy must supply a trustworthy client address because each key includes the peer IP.
Limit and backend-failure events are emitted as structured logs without identities or
tokens.

Current limits per peer and bounded identity/token key:

| Route | Allowance | Window |
| --- | ---: | ---: |
| Registration request | 30 | 60 minutes |
| Registration identity | 5 | 60 minutes |
| Login | 10 | 15 minutes |
| Refresh token | 30 | 5 minutes |
| Logout | 60 | 5 minutes |
| Forgot password | 5 | 60 minutes |
| Reset password | 10 | 60 minutes |
| Change password | 5 | 60 minutes |
| Resend verification | 5 | 60 minutes |
| Verify email | 10 | 60 minutes |
| Verify member access code | 10 | 60 minutes |
| Voucher discovery | 120 | 5 minutes |
| Pending voucher requests | 120 | 5 minutes |
| Voucher decision | 60 | 5 minutes |
| City catalogue | 120 | 5 minutes |
| Welfare-zone catalogue | 120 | 5 minutes |

Implemented compatibility routes:

- `POST /api/register` accepts bounded JSON, URL-encoded form, or multipart form
  registration data plus an optional normalized avatar. It validates an enabled
  chapter and that the selected city belongs to it, owns role/coordinator/year
  assignments on the server, writes the member/group/category/private-profile/
  optional-vouch/verification graph atomically, and sends verification mail only
  after commit. Mail failure keeps the completed account and returns resend guidance.
- `GET|POST /api/get_vouchers` provides an optional class-year-filtered public
  registration picker containing only voucher ID, name, graduation year, and
  chapter ID. It ignores the legacy shared application key.
- `GET|POST /api/voucher_pending` returns only pending rows assigned to the
  current active voucher account.
- `POST /api/vouch_action` approves or denies one owned pending vouch. It locks
  ownership and account state, requires a verified unapproved registrant, commits
  the vouch/account transition atomically, and sends bounded notifications only
  after commit.
- `POST /api/login` accepts JSON, URL-encoded form, or multipart form credentials.
- `POST /api/refresh_token` rotates an opaque refresh token and returns its replacement.
- `POST /api/logout` idempotently revokes a supplied refresh token.
- `POST /api/forgot_password` sends a generic recovery response and, when configured,
  a 30-minute one-time reset link.
- `POST /api/reset_password` consumes the reset token and revokes existing refresh sessions.
- `POST /api/change_user_password` changes only the authenticated member's password.
- `POST /api/resend_verify_email` replaces and delivers a finite email-verification code.
- `POST /api/verify_email` consumes a current code, marks the account email verified, and then best-effort notifies reviewed active administrators and an assigned pending voucher without rolling back committed verification if mail delivery fails.
- `POST /api/get_user_profile` returns an explicit credential-free self profile; selecting another member requires current database-backed account-management permission, never a JWT role claim alone.
- `POST /api/verify_user_access_code` verifies only the authenticated member's own
  legacy access code and returns no member identity or role details.
- `POST /api/manage_user_account` applies exactly one account-state or reviewed
  account-role transition. Role changes use current database facts, the fixed
  frontend category allowlist, escalation guards, and transactional refresh-session
  revocation.
- `GET|POST /api/get_alumni_stats` returns only the four reviewed directory-summary
  counts. It requires an active Bearer principal and never accepts or returns the
  legacy shared application token.
- `GET|POST /api/get_birthdays` preserves the active frontend's bodyless POST and
  the documented query-only GET contract. It uses the legacy Africa/Lagos date
  boundary and February 29 observance, requires a current active Bearer account,
  caps output at 200, and applies explicit birth-date opt-out privacy. Malformed
  visibility fails private; exact birth dates, ages, contact details, account
  fields, and other unnecessary profile data are never returned.
- `POST /api/import_alumni` accepts a bounded JSON roster or exactly one UTF-8
  CSV/XLSX file plus an enabled chapter. It requires current database-backed
  `MANAGE_ACCOUNTS` authority, validates every row and city before SQL, rejects
  formulas, duplicate identities, unknown fields, ambiguous memberships, and
  legacy binary XLS, then reconciles at most 500 members in one transaction.
  Existing account privileges, credentials, status, and visibility are preserved;
  new members receive server-owned alumni privileges and no known shared password.
  Responses contain row/status/user ID only and never disclose email, passwords,
  access codes, or generated compatibility codes.
- `GET|POST /api/get_chapters` lists enabled chapter metadata without a shared
  credential. Supplying `user_id` changes the operation to a protected self or
  downward account-manager assignment lookup based on current database facts.
- `GET|POST /api/get_cities` returns the public registration/profile city,
  chapter, and zone mapping in deterministic order without accepting the legacy
  shared key. Orphaned legacy zone references retain their source ID.
- `GET|POST /api/get_zones` returns public welfare-zone metadata and nested
  cities. Coordinator email is never public; coordinator identity is returned
  only for active, approved, verified accounts, while phone/avatar obey the
  stored global and per-field profile visibility settings.
- `GET|POST /api/get_users_by_zone` requires a current active Bearer account and
  resolves a bounded zone ID or exact name. The paginated roster includes only
  active, approved, verified members whose zone membership may be disclosed;
  global/city visibility can exclude a member, phone/avatar remain field-aware,
  and email, address, birth date, role, account state, and credentials are absent.
- `GET|POST /api/get_my_zone` resolves only the current active member's stored
  city through the oldest matching city row. Missing/orphan mappings retain the
  legacy HTTP 200 unavailable result, while resolved coordinator output uses the
  same eligibility/privacy contract and never exposes email.
- `POST /api/manage_zone` provides current-database-authorized create, update,
  and delete operations with normalized duplicate checks, chapter/coordinator
  validation, serialized catalogue locks, child-city chapter alignment, and
  reference-safe deletion.
- `POST /api/manage_city` provides the matching bounded city mutation contract.
  The selected zone governs chapter metadata, and rename/delete is blocked while
  member or profile records still use the legacy free-text city name.
- `POST /api/upload_zones_cities` accepts exactly one UTF-8 CSV or XLSX file
  containing only `zone` and `city` columns. It requires current database-backed
  `MANAGE_ZONES` authority, authenticates and throttles before parsing, caps input
  at 2 MiB and 2,000 rows, rejects formulas and unsafe archive expansion, and
  applies validated zone/city changes in one idempotent transaction. Legacy binary
  `.xls` files must be converted to CSV or XLSX before upload.
- `POST /api/get_setup_parameters` returns one explicit legacy configuration row
  to a current active Bearer principal. The reusable `X-API-Key` is ignored,
  names are bounded, duplicate rows resolve deterministically, and JSON/form
  request bodies are supported.
- `GET|POST /api/get_notifications` returns a bounded current-member feed from the
  reviewed `notifications` table. It derives user scope from an active Bearer
  principal, enforces the legacy account-creation boundary in UTC, ignores
  caller-supplied user IDs, and returns no recipient identifier.
- `POST /api/mark_notification_read` idempotently marks one owned notification or
  a bounded set of the current member's unread notifications. It locks current
  rows, ignores caller-supplied user IDs, and stores read state in the reviewed
  per-row `is_read` column.
- `GET|POST /api/get_announcements` is a bounded public presentation feed with
  reviewed filters and no caller-supplied identity. `POST /api/create_announcement`
  and `POST /api/manage_announcement` require an active Bearer principal plus
  current database `MANAGE_CONTENT` permission; they use explicit field
  allowlists, row locks, transactions, and validated generated announcement-image
  storage. These three routes still need disposable-MariaDB and browser proof.
- `POST /api/get_listings` is a bounded public active/unexpired marketplace feed
  that excludes seller email. Authenticated `POST /api/create_listing` and
  `POST /api/manage_listing` derive ownership and privileged state from current
  database facts, permit owner-or-`MANAGE_STORE` mutation, and support bounded
  validated/re-encoded marketplace images plus social metadata in transactional
  writes. These routes still need disposable-MariaDB, browser, and exact legacy
  GET/policy proof.
- `POST /api/get_projects` is the active frontend's bounded public project
  feed/detail lookup. It returns only reviewed non-deleted
  active/completed/paused/ongoing presentation data, not draft, owner, or member
  fields. Authenticated `POST /api/create_project` and `POST /api/manage_project`
  require an active Bearer principal with freshly loaded database
  `MANAGE_CONTENT` permission, derive ownership/default chapter server-side, lock
  project state before mutation, use explicit fields and soft deletion, and safely
  validate/re-encode at most six generated project images with transaction cleanup.
  These routes still need disposable-MariaDB and browser proof.

The older `GET|POST /api/verify_otp` and `GET|POST /api/resend_otp` workflows are
retired with HTTP 410 tombstones. They contained a broken identity comparison and
could bypass the current approval state machine; clients must use `/api/verify_email`
and `/api/resend_verify_email`.

The previously public `check_reset_password`, `getAPIKey2`, `trackUser`,
`sendUserOTP`, `update_user_account`, `deactivate_staff/{user_id}`,
`update_user_role`, `manage_user_roles`, `create_role`, `manage_role`, `get_roles`,
`user_tokens`, `change_user_password1`, and `test_qr` paths also return HTTP 410.
They exposed reset/application credentials, diagnostics, unbounded side effects,
destructive or arbitrary cross-account changes, caller-selected push-token
ownership, free-form role/password mutation, broad role metadata, or a fixed demo
value and therefore have no direct executable FastAPI equivalent.
Clients of `update_user_account` must use the allowlisted `/api/update_profile`
contract instead. Clients of any standalone role mutation/definition route must
use the reviewed role branch of `/api/manage_user_account`; dynamic `roles` rows
are not an authorization source. Clients of `deactivate_staff/{user_id}` must use
the authorized state-only `/api/manage_user_account` operation. A push-token
replacement is intentionally pending: if real hidden/mobile callers still require
it, the new contract must be authenticated self-service, bind ownership to the
current member, support revocation, and never log or return token values.

`POST /api/create_notification` and push delivery are intentionally not exposed yet.
The PHP route has no effective administrator gate and references recipients, fields,
and push-token storage absent from the reviewed schema. Confirm the creator/recipient
policy, storage migration, provider, retry and opt-out rules, and real callers before
adding a replacement.

Clients must replace their stored refresh token after every successful refresh.

## Run and verify

```powershell
$env:ALUMNI_ENVIRONMENT = "development"
$env:ALUMNI_DATABASE_URL = "mysql+pymysql://<user>:<password>@<host>:<port>/<sanitized_database>?charset=utf8mb4"
python -m uvicorn app.main:app --host 127.0.0.1 --port 8000

python -m ruff format --check app migrations scripts tests
python -m ruff check app migrations scripts tests
python -m mypy app migrations scripts tests
python -m pytest --cov=app --cov-report=term-missing
python -m alembic check
python -m bandit -q -r app
python -m pip_audit -r requirements-dev.lock
```

The local smoke test proves only the ASGI process and the explicitly configured sanitized database. It does not prove production credentials, current live-schema parity, proxy configuration, provider access, or frontend compatibility.
