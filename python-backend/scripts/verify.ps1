$ErrorActionPreference = "Stop"
$PSNativeCommandUseErrorActionPreference = $true

$projectRoot = Split-Path -Parent $PSScriptRoot
$python = Join-Path $projectRoot ".venv\Scripts\python.exe"

if (-not (Test-Path -LiteralPath $python)) {
    throw "The project virtual environment does not exist. Run the README bootstrap commands first."
}

if ([string]::IsNullOrWhiteSpace($env:ALUMNI_TEST_DATABASE_URL)) {
    throw "ALUMNI_TEST_DATABASE_URL must name a disposable or sanitized test database."
}

Push-Location -LiteralPath $projectRoot
try {
    & $python -c "import sys; assert sys.prefix != sys.base_prefix"
    & $python -m pip check
    & $python -m ruff format --check app migrations scripts tests
    & $python -m ruff check app migrations scripts tests
    & $python -m mypy app migrations scripts tests
    $previousDatabaseUrl = $env:ALUMNI_DATABASE_URL
    try {
        # Integration fixtures use ALUMNI_TEST_DATABASE_URL directly. Keep the
        # runtime URL absent during pytest so configuration/health tests remain
        # deterministic even when the caller also runs the API locally.
        Remove-Item Env:ALUMNI_DATABASE_URL -ErrorAction SilentlyContinue
        & $python -m pytest --cov=app --cov-report=term-missing
    }
    finally {
        if ($null -eq $previousDatabaseUrl) {
            Remove-Item Env:ALUMNI_DATABASE_URL -ErrorAction SilentlyContinue
        }
        else {
            $env:ALUMNI_DATABASE_URL = $previousDatabaseUrl
        }
    }
    $previousDatabaseUrl = $env:ALUMNI_DATABASE_URL
    try {
        $env:ALUMNI_DATABASE_URL = $env:ALUMNI_TEST_DATABASE_URL
        & $python -m alembic check
    }
    finally {
        if ($null -eq $previousDatabaseUrl) {
            Remove-Item Env:ALUMNI_DATABASE_URL -ErrorAction SilentlyContinue
        }
        else {
            $env:ALUMNI_DATABASE_URL = $previousDatabaseUrl
        }
    }
    & $python -m bandit -q -r app
    & $python -m pip_audit -r requirements-dev.lock
}
finally {
    Pop-Location
}
