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


def test_smtp_mailer_sends_every_order_status_variant(monkeypatch: pytest.MonkeyPatch) -> None:
    """Each order-status branch renders the reviewed subject and body sections."""
    fake = FakeSmtp()
    monkeypatch.setattr(smtplib, "SMTP", lambda *_args, **_kwargs: fake)
    settings = Settings(
        smtp_host="smtp.example.test",
        smtp_from_address="no-reply@example.com",
    )
    mailer = SmtpMailer(settings)
    send = mailer.send_order_status_email

    send(
        "member@example.com",
        "Synthetic Member",
        "ORD-PICKUP",
        "pickup",
        "self_pickup",
        "Bring a valid ID",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Order Is Ready for Pickup"
    content = fake.message.get_content()
    assert "ORD-PICKUP" in content
    assert "Bring a valid ID" in content

    send(
        "member@example.com",
        "Synthetic Member",
        "ORD-RIDER",
        "out_for_delivery",
        "door_delivery",
        "",
        "Rider Musa - 0803 000 0000",
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Order Is Out for Delivery"
    assert "Rider Musa" in fake.message.get_content()

    # The shipped alias shares the out-for-delivery presentation.
    send("member@example.com", "Synthetic Member", "ORD-SHIPPED", "shipped", "door_delivery")
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Order Is Out for Delivery"

    send("member@example.com", "Synthetic Member", "ORD-DONE", "completed", "self_pickup")
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Order Has Been Completed"

    send("member@example.com", "Synthetic Member", "ORD-DELIVERED", "delivered", "self_pickup")
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Order Has Been Completed"

    # An unknown status keeps the legacy generic subject and echoes the status value.
    send(
        "member@example.com", "", "ORD-GENERIC", "processing", "self_pickup", first_name="Fallback"
    )
    assert fake.message is not None
    assert fake.message["Subject"] == "Order #ORD-GENERIC Status Update"
    generic = fake.message.get_content()
    assert "Hello Fallback" in generic
    assert "updated to processing." in generic

    # Rider details and notes can also arrive through the details mapping.
    send(
        "member@example.com",
        "Synthetic Member",
        "ORD-DETAILS",
        "shipped",
        "door_delivery",
        details={"rider_details": "Rider Zayd", "note": "Leave at the gate"},
    )
    assert fake.message is not None
    details_content = fake.message.get_content()
    assert "Rider Zayd" in details_content
    assert "Leave at the gate" in details_content


def test_smtp_mailer_sends_account_decision_variants(monkeypatch: pytest.MonkeyPatch) -> None:
    """Approval, activation, and anonymous display names keep their reviewed copy."""
    fake = FakeSmtp()
    monkeypatch.setattr(smtplib, "SMTP", lambda *_args, **_kwargs: fake)
    mailer = SmtpMailer(
        Settings(
            smtp_host="smtp.example.test",
            smtp_from_address="no-reply@example.com",
        )
    )

    mailer.send_account_status("member@example.com", "Synthetic Member", "approve")
    assert fake.message is not None
    assert fake.message["Subject"] == "Your FGGC Alumni Account Has Been Approved"
    assert "Congratulations, Synthetic Member!" in fake.message.get_content()

    # A blank display name falls back to the generic salutation, and an empty
    # reason must not render an empty "Reason:" section.
    mailer.send_account_status("member@example.com", "   ", "reject", None)
    assert fake.message is not None
    rejected = fake.message.get_content()
    assert "Dear member," in rejected
    assert "Reason:" not in rejected

    mailer.send_account_activity("member@example.com", "Synthetic Member", "activate", "self")
    assert fake.message is not None
    assert fake.message["Subject"] == "Your Alumni Portal Account Has Been Activated"
    activated = fake.message.get_content()
    assert "activated by you" in activated
    assert "You can now sign in and access the portal." in activated

    mailer.send_account_activity("member@example.com", "", "deactivate", "administrator")
    assert fake.message is not None
    deactivated = fake.message.get_content()
    assert "Hello member," in deactivated
    assert "deactivated by an account administrator" in deactivated
    assert "Contact an administrator if you want the account reactivated." in deactivated


def test_smtp_mailer_supports_a_plain_unauthenticated_relay(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A relay without TLS or credentials skips both optional handshake steps."""
    fake = FakeSmtp()
    monkeypatch.setattr(smtplib, "SMTP", lambda *_args, **_kwargs: fake)
    mailer = SmtpMailer(
        Settings(
            smtp_host="smtp.example.test",
            smtp_from_address="no-reply@example.com",
            smtp_use_tls=False,
        )
    )

    mailer.send_password_reset(
        "member@example.com",
        "Synthetic Member",
        "https://frontend.example.test/reset-password?code=opaque-synthetic-token",
    )
    assert fake.tls_started is False
    assert fake.credentials is None
    assert fake.ehlo_calls == 1
    assert fake.message is not None


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
