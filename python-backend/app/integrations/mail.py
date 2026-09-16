"""Transactional email boundary and SMTP implementation."""

from __future__ import annotations

import smtplib
import ssl
from email.message import EmailMessage
from typing import Protocol

from app.core.config import Settings
from app.core.errors import MailDeliveryError


class Mailer(Protocol):
    """Delivery operations required by account workflows."""

    def send_password_reset(self, recipient: str, display_name: str, reset_url: str) -> None:
        """Send a one-time password-reset link."""

    def send_verification_code(self, recipient: str, display_name: str, code: str) -> None:
        """Send a finite account email-verification code."""

    def send_new_account_notification(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Tell an account manager that a verified member awaits approval."""

    def send_voucher_request(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Tell an assigned voucher that a verified member awaits review."""

    def send_voucher_approval_notification(
        self,
        recipient: str,
        recipient_name: str,
        voucher_name: str,
        member_name: str,
    ) -> None:
        """Tell an account manager that a voucher approved a member."""

    def send_account_status(
        self,
        recipient: str,
        display_name: str,
        action: str,
        reason: str | None = None,
    ) -> None:
        """Tell a member that an administrator approved or rejected the account."""

    def send_account_activity(
        self,
        recipient: str,
        display_name: str,
        action: str,
        actor_kind: str,
    ) -> None:
        """Tell a member that their active account state changed."""


class SmtpMailer:
    """Send bounded transactional mail over an explicitly configured SMTP server."""

    def __init__(self, settings: Settings) -> None:
        self._settings = settings

    def send_password_reset(self, recipient: str, display_name: str, reset_url: str) -> None:
        """Send a plain-text reset message without logging recipient or token data."""
        safe_name = display_name.strip() or "member"
        self._send(
            recipient,
            "Reset your Alumni Portal password",
            f"Hello {safe_name},\n\n"
            "Use the one-time link below to reset your password. "
            "It expires in 30 minutes.\n\n"
            f"{reset_url}\n\n"
            "If you did not request this, you can ignore this message.",
        )

    def send_verification_code(self, recipient: str, display_name: str, code: str) -> None:
        """Send a six-digit verification code without logging identity or code data."""
        safe_name = display_name.strip() or "member"
        self._send(
            recipient,
            "Verify your Alumni Portal email",
            f"Hello {safe_name},\n\n"
            f"Your one-time verification code is: {code}\n\n"
            "It expires in 24 hours. If you did not create this account, ignore this message.",
        )

    def send_new_account_notification(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Send a bounded pending-approval notification to an account manager."""
        self._send(
            recipient,
            "New Alumni Portal account pending approval",
            f"Hello {recipient_name.strip() or 'administrator'},\n\n"
            f"{member_name} ({member_email}) verified their email and awaits account approval.\n\n"
            "Sign in to the Alumni Portal to review the account.",
        )

    def send_voucher_request(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Send a bounded review request to the assigned voucher."""
        self._send(
            recipient,
            "Alumni voucher review requested",
            f"Hello {recipient_name.strip() or 'member'},\n\n"
            f"{member_name} ({member_email}) selected you as their voucher.\n\n"
            "Sign in to review the pending request.",
        )

    def send_voucher_approval_notification(
        self,
        recipient: str,
        recipient_name: str,
        voucher_name: str,
        member_name: str,
    ) -> None:
        """Send a bounded post-commit voucher-approval notice to an account manager."""
        self._send(
            recipient,
            "Voucher approved a new Alumni Portal account",
            f"Hello {recipient_name.strip() or 'administrator'},\n\n"
            f"{member_name} was approved by their selected voucher, {voucher_name}.\n\n"
            "No further action is required unless you want to review the account.",
        )

    def send_account_status(
        self,
        recipient: str,
        display_name: str,
        action: str,
        reason: str | None = None,
    ) -> None:
        """Send a bounded plain-text equivalent of the legacy status email."""
        safe_name = display_name.strip() or "member"
        if action == "approve":
            self._send(
                recipient,
                "Your FGGC Alumni Account Has Been Approved",
                f"Congratulations, {safe_name}!\n\n"
                "Your FGGC Alumni account has been reviewed and approved. "
                "You can now sign in and access the portal.",
            )
            return
        reason_text = f"\n\nReason: {reason}" if reason else ""
        self._send(
            recipient,
            "Update on Your FGGC Alumni Account Application",
            f"Dear {safe_name},\n\n"
            "After review, we are unable to approve your alumni account at this time."
            f"{reason_text}\n\n"
            "Please contact support if you believe this is an error.",
        )

    def send_account_activity(
        self,
        recipient: str,
        display_name: str,
        action: str,
        actor_kind: str,
    ) -> None:
        """Send the bounded plain-text equivalent of the legacy activity email."""
        safe_name = display_name.strip() or "member"
        if action == "activate":
            subject = "Your Alumni Portal Account Has Been Activated"
            outcome = "activated"
            next_step = "You can now sign in and access the portal."
        else:
            subject = "Your Alumni Portal Account Has Been Deactivated"
            outcome = "deactivated"
            next_step = "Contact an administrator if you want the account reactivated."
        actor_text = "you" if actor_kind == "self" else "an account administrator"
        self._send(
            recipient,
            subject,
            f"Hello {safe_name},\n\n"
            f"Your Alumni Portal account was {outcome} by {actor_text}.\n\n"
            f"{next_step}",
        )

    def _send(self, recipient: str, subject: str, body: str) -> None:
        """Deliver one pre-rendered plain-text transactional message."""
        host = self._settings.smtp_host
        sender = self._settings.smtp_from_address
        if not host or not sender:
            raise MailDeliveryError("SMTP delivery is not configured")
        message = EmailMessage()
        message["Subject"] = subject
        message["From"] = f"{self._settings.smtp_from_name} <{sender}>"
        message["To"] = recipient
        message.set_content(body)
        try:
            with smtplib.SMTP(host, self._settings.smtp_port, timeout=10) as client:
                client.ehlo()
                if self._settings.smtp_use_tls:
                    client.starttls(context=ssl.create_default_context())
                    client.ehlo()
                if self._settings.smtp_username and self._settings.smtp_password:
                    client.login(
                        self._settings.smtp_username,
                        self._settings.smtp_password.get_secret_value(),
                    )
                client.send_message(message)
        except (OSError, smtplib.SMTPException) as exc:
            raise MailDeliveryError("SMTP delivery failed") from exc
