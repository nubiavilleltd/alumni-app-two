"""Typed runtime configuration with secure production defaults."""

from functools import lru_cache
from pathlib import Path
from typing import Literal, Self

from pydantic import Field, HttpUrl, SecretStr, model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Application settings loaded from ``ALUMNI_`` environment variables."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        env_prefix="ALUMNI_",
        extra="ignore",
    )

    app_name: str = "Alumni Portal API"
    environment: Literal["development", "test", "staging", "production"] = "development"
    database_url: SecretStr | None = None
    migration_allow_production: bool = False
    public_base_url: HttpUrl | None = None
    frontend_base_url: HttpUrl | None = None
    trusted_hosts: list[str] = Field(default_factory=lambda: ["127.0.0.1", "localhost"])
    cors_origins: list[str] = Field(default_factory=list)
    docs_enabled: bool | None = None

    database_pool_size: int = Field(default=5, ge=1, le=50)
    database_max_overflow: int = Field(default=10, ge=0, le=100)
    database_pool_timeout_seconds: int = Field(default=10, ge=1, le=60)
    database_connect_timeout_seconds: int = Field(default=5, ge=1, le=30)

    jwt_signing_key: SecretStr | None = None
    jwt_verification_key: SecretStr | None = None
    jwt_algorithm: Literal["RS256", "ES256"] = "RS256"
    jwt_issuer: str = "alumni-portal-api"
    jwt_audience: str = "alumni-portal-clients"
    access_token_ttl_minutes: int = Field(default=15, ge=1, le=60)
    refresh_token_ttl_days: int = Field(default=30, ge=1, le=90)

    redis_url: SecretStr | None = None
    authentication_rate_limit_backend: Literal["memory", "redis"] = "memory"
    rate_limit_key_prefix: str = "alumni:rate-limit"
    paystack_secret_key: SecretStr | None = None
    paystack_public_key: str | None = None
    smtp_host: str | None = None
    smtp_port: int = Field(default=587, ge=1, le=65535)
    smtp_username: str | None = None
    smtp_password: SecretStr | None = None
    smtp_from_address: str | None = None
    smtp_from_name: str = "Alumni Portal"
    smtp_use_tls: bool = True
    vapid_public_key: str | None = None
    vapid_private_key: SecretStr | None = None
    upload_root: Path = Path("var/uploads")

    @model_validator(mode="after")
    def validate_environment_guards(self) -> Self:
        """Reject unsafe production-only combinations early."""
        if self.environment == "production":
            if self.docs_enabled is True:
                raise ValueError("Interactive API documentation cannot be enabled in production")
            if "*" in self.trusted_hosts:
                raise ValueError("Wildcard trusted hosts are forbidden in production")
            if "*" in self.cors_origins:
                raise ValueError("Wildcard CORS origins are forbidden in production")
            if self.authentication_rate_limit_backend != "redis" or self.redis_url is None:
                raise ValueError("Production authentication rate limiting requires Redis")
        return self

    @property
    def expose_docs(self) -> bool:
        """Return whether OpenAPI documentation routes should be exposed."""
        if self.docs_enabled is not None:
            return self.docs_enabled
        return self.environment in {"development", "test"}

    def database_url_value(self) -> str | None:
        """Return the database URL without exposing it through model serialization."""
        if self.database_url is None:
            return None
        return self.database_url.get_secret_value()

    def jwt_signing_key_value(self) -> str | None:
        """Return PEM signing material without including it in settings output."""
        return self.jwt_signing_key.get_secret_value() if self.jwt_signing_key else None

    def jwt_verification_key_value(self) -> str | None:
        """Return PEM verification material without including it in settings output."""
        return self.jwt_verification_key.get_secret_value() if self.jwt_verification_key else None


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    """Return the process-wide validated settings instance."""
    return Settings()
