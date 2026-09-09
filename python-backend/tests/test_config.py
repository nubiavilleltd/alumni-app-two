"""Configuration guard tests."""

import pytest
from pydantic import ValidationError

from app.core.config import Settings, get_settings


def test_production_rejects_wildcard_trusted_hosts() -> None:
    """Production must never accept every Host header."""
    with pytest.raises(ValidationError, match="Wildcard trusted hosts"):
        Settings(environment="production", trusted_hosts=["*"])


def test_production_rejects_explicit_docs() -> None:
    """Interactive documentation must remain disabled in production."""
    with pytest.raises(ValidationError, match="documentation"):
        Settings(environment="production", docs_enabled=True)


def test_database_url_is_redacted() -> None:
    """Settings representations must not display database credentials."""
    settings = Settings(database_url="mysql+pymysql://member:secret@example.invalid/app")
    assert "member:secret@" not in repr(settings)
    assert settings.database_url_value() == "mysql+pymysql://member:secret@example.invalid/app"


def test_production_rejects_wildcard_cors() -> None:
    """Production must not permit every cross-origin caller."""
    with pytest.raises(ValidationError, match="Wildcard CORS"):
        Settings(environment="production", cors_origins=["*"])


def test_documentation_defaults_follow_environment() -> None:
    """Documentation is visible locally and hidden by default in production."""
    assert Settings(environment="development").expose_docs is True
    assert (
        Settings(
            environment="production",
            authentication_rate_limit_backend="redis",
            redis_url="redis://localhost:6379/0",
        ).expose_docs
        is False
    )
    assert Settings(environment="test", docs_enabled=False).expose_docs is False


def test_unconfigured_database_url_returns_none() -> None:
    """An absent database URL remains absent rather than receiving a hidden default."""
    assert Settings().database_url_value() is None


def test_cached_settings_factory_returns_one_instance() -> None:
    """Process settings are parsed once."""
    get_settings.cache_clear()


def test_production_requires_shared_authentication_rate_limits() -> None:
    """Process-local counters cannot protect a multi-worker production service."""
    with pytest.raises(ValidationError, match="requires Redis"):
        Settings(environment="production")
    with pytest.raises(ValidationError, match="requires Redis"):
        Settings(environment="production", authentication_rate_limit_backend="redis")
    assert get_settings() is get_settings()
    get_settings.cache_clear()
