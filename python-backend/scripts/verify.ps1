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
    & $python -m pytest --cov=app --cov-report=term-missing
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
