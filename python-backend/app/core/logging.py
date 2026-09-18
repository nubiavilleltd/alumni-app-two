"""Structured logging configured to redact sensitive application fields."""

import logging
from collections.abc import MutableMapping
from typing import Any

import structlog

# Credential and secret material.
SENSITIVE_KEYS = frozenset(
    {
        "authorization",
        "cookie",
        "database_url",
        "jwt_signing_key",
        "password",
        "paystack_secret_key",
        "refresh_token",
        "reset_code",
        "smtp_password",
        "token",
        "vapid_private_key",
        # Personal data.
        "email",
        "phone",
        "alternative_phone",
        "fullname",
        "first_name",
        "last_name",
        "birth_date",
        "residential_address",
        "address",
        "name_in_school",
        "user_code",
        "user_access_code",
        "useraccesscode",
        # Payment and verification material.
        "card",
        "card_number",
        "cvv",
        "cvc",
        "pan",
        "payment_payload",
        "paystack_payload",
        "tx_data",
        "authorization_code",
        "access_code",
        "otp",
        "verification_code",
        "signature",
        "secret",
        "api_key",
        "client_secret",
    }
)


def _redact(value: Any, key: object | None = None) -> Any:
    """Recursively redact sensitive keys while leaving structure intact."""
    if key is not None and str(key).casefold() in SENSITIVE_KEYS:
        return "[REDACTED]"
    if isinstance(value, MutableMapping):
        return {
            inner_key: _redact(inner_value, inner_key) for inner_key, inner_value in value.items()
        }
    if isinstance(value, (list, tuple)):
        return [_redact(item) for item in value]
    return value


def redact_sensitive_fields(
    _logger: Any,
    _method_name: str,
    event_dict: MutableMapping[str, Any],
) -> MutableMapping[str, Any]:
    """Replace sensitive top-level and nested structured-log values."""
    for key in tuple(event_dict):
        event_dict[key] = _redact(event_dict[key], key)
    return event_dict


def configure_logging() -> None:
    """Configure standard-library and structured JSON logging once."""
    logging.basicConfig(level=logging.INFO, format="%(message)s")
    structlog.configure(
        processors=[
            structlog.contextvars.merge_contextvars,
            redact_sensitive_fields,
            structlog.processors.add_log_level,
            structlog.processors.TimeStamper(fmt="iso", utc=True),
            structlog.processors.JSONRenderer(),
        ],
        wrapper_class=structlog.make_filtering_bound_logger(logging.INFO),
        logger_factory=structlog.stdlib.LoggerFactory(),
        cache_logger_on_first_use=True,
    )
