"""Structured logging safety tests."""

from app.core.logging import configure_logging, redact_sensitive_fields


def test_sensitive_log_fields_are_redacted_case_insensitively() -> None:
    """Known credential fields are replaced before rendering."""
    event = {"Password": "plaintext", "event": "login", "user_id": 7}
    result = redact_sensitive_fields(None, "info", event)
    assert result == {"Password": "[REDACTED]", "event": "login", "user_id": 7}


def test_nested_sensitive_fields_are_redacted_recursively() -> None:
    """Nested mappings and lists are walked so secrets cannot hide in payloads."""
    event = {
        "event": "checkout",
        "details": {"customer": {"email": "a@example.com", "phone": "0801"}, "token": "t"},
        "items": [{"card_number": "4111"}, {"name": "safe"}],
    }
    result = redact_sensitive_fields(None, "info", event)
    assert result["details"]["customer"]["email"] == "[REDACTED]"
    assert result["details"]["customer"]["phone"] == "[REDACTED]"
    assert result["details"]["token"] == "[REDACTED]"
    assert result["items"] == [{"card_number": "[REDACTED]"}, {"name": "safe"}]


def test_payment_payload_is_redacted_whole() -> None:
    """A raw Paystack payload is replaced wholesale, not just its fields."""
    event = {
        "event": "webhook",
        "payment_payload": {
            "reference": "ORD-1",
            "authorization": {"card_type": "visa", "bin": "408408"},
            "customer": {"email": "a@example.com"},
        },
    }
    result = redact_sensitive_fields(None, "info", event)
    assert result["payment_payload"] == "[REDACTED]"


def test_pii_fields_are_redacted_at_the_top_level() -> None:
    """Personal-data keys are redacted even when logged directly."""
    event = {"fullname": "Ada", "email": "a@example.com", "phone": "0801", "event": "x"}
    result = redact_sensitive_fields(None, "info", event)
    assert result["fullname"] == "[REDACTED]"
    assert result["email"] == "[REDACTED]"
    assert result["phone"] == "[REDACTED]"


def test_logging_configuration_is_repeatable() -> None:
    """Application factories may safely request logging configuration more than once."""
    configure_logging()
    configure_logging()
