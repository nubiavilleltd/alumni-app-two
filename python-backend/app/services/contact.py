"""Public contact-form submission use case."""

from __future__ import annotations

from datetime import UTC, datetime

import structlog
from sqlalchemy.orm import Session

from app.core.errors import MailDeliveryError
from app.integrations.mail import Mailer
from app.repositories.contact import ContactRepository
from app.schemas.contact import ContactCreateRequest, ContactResponse

logger = structlog.get_logger(__name__)


class ContactService:
    def __init__(self, session: Session, mailer: Mailer | None = None) -> None:
        self._session = session
        self._repository = ContactRepository(session)
        self._mailer = mailer

    def submit(self, request: ContactCreateRequest) -> ContactResponse:
        """Persist a contact message and notify active managers after commit."""
        with self._session.begin():
            self._repository.create(
                {
                    "first_name": request.first_name,
                    "last_name": request.last_name,
                    "email": str(request.email),
                    "message": request.message,
                    "status": "new",
                    "created_at": datetime.now(UTC).replace(tzinfo=None),
                }
            )
            managers = self._repository.account_manager_recipients()
        self._notify_managers(managers, request)
        return ContactResponse(
            status=200,
            message="Your message has been sent successfully. We will get back to you soon.",
        )

    def _notify_managers(
        self, managers: list[dict[str, object]], request: ContactCreateRequest
    ) -> None:
        if self._mailer is None:
            return
        full_name = f"{request.first_name} {request.last_name}".strip()
        for manager in managers:
            try:
                self._mailer.send_contact_form(
                    str(manager["email"]),
                    str(manager.get("fullname") or "administrator"),
                    full_name,
                    str(request.email),
                    request.message,
                )
            except MailDeliveryError:
                logger.error("contact_manager_notification_failed")
