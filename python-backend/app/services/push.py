"""Web Push subscription and delivery use cases."""

from __future__ import annotations

from datetime import UTC, datetime

import structlog
from sqlalchemy.orm import Session

from app.core.config import Settings
from app.integrations.push import PushDeliveryError, send_web_push
from app.repositories.push import PushRepository
from app.schemas.push import PushSubscriptionRequest

logger = structlog.get_logger(__name__)


class PushService:
    def __init__(self, session: Session, settings: Settings) -> None:
        self._session = session
        self._settings = settings
        self._repository = PushRepository(session)

    def register_subscription(self, user_id: int, request: PushSubscriptionRequest) -> None:
        """Store or refresh the current account's push subscription."""
        with self._session.begin():
            self._repository.upsert_subscription(
                user_id,
                request.endpoint,
                request.p256dh,
                request.auth,
                request.browser,
                datetime.now(UTC).replace(tzinfo=None),
            )

    def notify(self, user_id: int, title: str, body: str) -> int:
        """Best-effort push to every registered subscription for a user."""
        private_key = self._settings.vapid_private_key_value()
        if not private_key:
            return 0
        sent = 0
        with self._session.begin():
            subscriptions = self._repository.subscriptions(user_id)
        for subscription in subscriptions:
            try:
                send_web_push(
                    subscription,
                    {"title": title, "body": body},
                    private_key=private_key,
                    subject=self._settings.vapid_subject,
                )
                sent += 1
            except PushDeliveryError:
                continue
        return sent

    def public_key(self) -> str | None:
        return self._settings.vapid_public_key
