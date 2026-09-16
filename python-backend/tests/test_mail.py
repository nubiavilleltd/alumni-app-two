"""Transactional SMTP adapter tests without external network access."""

from __future__ import annotations

import smtplib
from email.message import EmailMessage
from types import TracebackType

import pytest

from app.core.config import Settings
from app.core.errors import MailDeliveryError
from app.integrations.mail import SmtpMailer

SMTP_CREDENTIAL = "synthetic-passphrase"


class FakeSmtp:
    """Minimal context-managed SMTP recorder."""

    def __init__(self) -> None:
        self.ehlo_calls = 0
        self.tls_started = False
        self.credentials: tuple[str, str] | None = None
        self.message: EmailMessage | None = None

    def __enter__(self) -> FakeSmtp:
        return self

    def __exit__(
        self,
        exc_type: type[BaseException] | None,
        exc: BaseException | None,
        traceback: TracebackType | None,
    ) -> None:
        return None

    def ehlo(self) -> None:
        self.ehlo_calls += 1

    def starttls(self, *, context: object) -> None:
        assert context is not None
        self.tls_started = True

    def login(self, username: str, password: str) -> None:
        self.credentials = (username, password)

    def send_message(self, message: EmailMessage) -> None:
        self.message = message


def test_smtp_mailer_sends_tls_authenticated_reset_message(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Configured SMTP delivery uses TLS, authentication, and the bounded template."""
    fake = FakeSmtp()
    monkeypatch.setattr(smtplib, "SMTP", lambda *_args, **_kwargs: fake)
    settings = Settings(
        frontend_base_url="https://frontend.example.test/",
        smtp_host="smtp.example.test",
        smtp_from_address="no-reply@example.com",
        smtp_username="synthetic-user",
        smtp_password=SMTP_CREDENTIAL,
    )
    SmtpMailer(settings).send_password_reset(
        "member@example.com",
        "Synthetic Member",
        "https://frontend.example.test/reset-password?code=opaque-synthetic-token",
    )
    assert fake.tls_started is True
    assert fake.ehlo_calls == 2
    assert fake.credentials == ("synthetic-user", SMTP_CREDENTIAL)
    assert fake.message is not None
    assert fake.message["To"] == "member@example.com"
    assert "opaque-synthetic-token" in fake.message.get_content()

    SmtpMailer(settings).send_verification_code("member@example.com", "Synthetic Member", "123456")
    assert fake.message is not None
    assert fake.message["Subject"] == "Verify your Alumni Portal email"
    assert "123456" in fake.message.get_content()

    SmtpMailer(settings).send_new_account_notification(
        "manager@example.com",
        "Synthetic Manager",
        "Synthetic Member",
        "member@example.com",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "New Alumni Portal account pending approval"
    assert "member@example.com" in fake.message.get_content()

    SmtpMailer(settings).send_voucher_request(
        "voucher@example.com",
        "Synthetic Voucher",
        "Synthetic Member",
        "member@example.com",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Alumni voucher review requested"
    assert "member@example.com" in fake.message.get_content()

    SmtpMailer(settings).send_voucher_approval_notification(
        "manager@example.com",
        "Synthetic Manager",
        "Synthetic Voucher",
        "Synthetic Member",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Voucher approved a new Alumni Portal account"
    assert "Synthetic Voucher" in fake.message.get_content()
    assert "Synthetic Member" in fake.message.get_content()

    SmtpMailer(settings).send_account_status(
        "member@example.com",
        "Synthetic Member",
        "reject",
        "Synthetic review reason",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Update on Your FGGC Alumni Account Application"
    assert "Synthetic review reason" in fake.message.get_content()

    SmtpMailer(settings).send_account_activity(
        "member@example.com",
        "Synthetic Member",
        "deactivate",
        "self",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Alumni Portal Account Has Been Deactivated"
    assert "deactivated by you" in fake.message.get_content()


def test_smtp_mailer_requires_configuration_and_wraps_transport_errors(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Missing settings and transport failures become domain errors."""
    with pytest.raises(MailDeliveryError, match="not configured"):
        SmtpMailer(Settings()).send_password_reset(
            "member@example.com", "", "https://frontend.example.test/reset"
        )

    def fail_smtp(*_args: object, **_kwargs: object) -> None:
        raise OSError("synthetic transport failure")

    monkeypatch.setattr(smtplib, "SMTP", fail_smtp)
    configured = Settings(
        frontend_base_url="https://frontend.example.test/",
        smtp_host="smtp.example.test",
        smtp_from_address="no-reply@example.com",
        smtp_use_tls=False,
    )
    with pytest.raises(MailDeliveryError, match="delivery failed"):
        SmtpMailer(configured).send_password_reset(
            "member@example.com", "", "https://frontend.example.test/reset"
        )
