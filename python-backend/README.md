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
| Login | 10 | 15 minutes |
| Refresh token | 30 | 5 minutes |
| Logout | 60 | 5 minutes |
| Forgot password | 5 | 60 minutes |
| Reset password | 10 | 60 minutes |
| Change password | 5 | 60 minutes |
| Resend verification | 5 | 60 minutes |
| Verify email | 10 | 60 minutes |
| Verify member access code | 10 | 60 minutes |

Implemented compatibility routes:

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

The older `GET|POST /api/verify_otp` and `GET|POST /api/resend_otp` workflows are
retired with HTTP 410 tombstones. They contained a broken identity comparison and
could bypass the current approval state machine; clients must use `/api/verify_email`
and `/api/resend_verify_email`.

The previously public `check_reset_password`, `getAPIKey2`, `trackUser`,
`sendUserOTP`, `change_user_password1`, and `test_qr` paths also return HTTP 410.
They exposed reset/application credentials, diagnostics, unbounded side effects,
unsafe password mutation, or a fixed demo value and therefore have no executable
FastAPI equivalent.

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
