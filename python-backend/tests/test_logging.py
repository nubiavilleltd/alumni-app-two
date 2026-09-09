"""Structured logging safety tests."""

from app.core.logging import configure_logging, redact_sensitive_fields


def test_sensitive_log_fields_are_redacted_case_insensitively() -> None:
    """Known credential fields are replaced before rendering."""
    event = {"Password": "plaintext", "event": "login", "user_id": 7}
    result = redact_sensitive_fields(None, "info", event)
    assert result == {"Password": "[REDACTED]", "event": "login", "user_id": 7}


def test_logging_configuration_is_repeatable() -> None:
    """Application factories may safely request logging configuration more than once."""
    configure_logging()
    configure_logging()
