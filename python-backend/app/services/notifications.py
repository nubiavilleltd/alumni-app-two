"""Authorization-aware notification feed and read-state use cases."""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Any

import structlog
from sqlalchemy.orm import Session

from app.repositories.notifications import NotificationRepository
from app.schemas.notifications import (
    GetNotificationsRequest,
    NotificationItem,
    NotificationListResponse,
    NotificationReadResponse,
)

logger = structlog.get_logger(__name__)
_MAX_MARK_ALL = 1000


class NotificationError(Exception):
    """A notification request failed authentication, ownership, or resource bounds."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class NotificationService:
    """Serve only current-account-owned rows from the reviewed SQL schema."""

    def __init__(self, session: Session) -> None:
        self._session = session
        self._repository = NotificationRepository(session)

    @staticmethod
    def _active_actor(actor: dict[str, Any] | None) -> dict[str, Any]:
        if actor is None or not bool(actor.get("active")):
            raise NotificationError(
                "notification_actor_unavailable", "Authentication required", 401
            )
        return actor

    @staticmethod
    def _account_created_at(actor: dict[str, Any]) -> datetime:
        """Convert the legacy epoch field independently of the database time zone."""
        return datetime.fromtimestamp(int(actor.get("created_on") or 0), UTC).replace(tzinfo=None)

    def list_notifications(
        self, actor_user_id: int, request: GetNotificationsRequest
    ) -> NotificationListResponse:
        """List a bounded page for the authenticated member only."""
        with self._session.begin():
            actor = self._active_actor(self._repository.actor(actor_user_id))
            account_created_at = self._account_created_at(actor)
            offset = (request.page - 1) * request.limit
            rows = self._repository.list_for_user(
                actor_user_id,
                account_created_at,
                offset=offset,
                limit=request.limit,
                unread_only=request.unread_only,
            )
            total = self._repository.count_for_user(
                actor_user_id, account_created_at, unread_only=request.unread_only
            )
            unread_count = self._repository.count_for_user(
                actor_user_id, account_created_at, unread_only=True
            )
        notifications = [
            NotificationItem(
                id=int(row["id"]),
                type=str(row["type"]),
                message=str(row["message"]),
                link=str(row["link"]) if row.get("link") else None,
                chapter_id=int(row["chapter_id"]) if row.get("chapter_id") else None,
                year=str(row["year"]) if row.get("year") else None,
                is_read=bool(row.get("is_read")),
                created_at=row["created_at"],
            )
            for row in rows
        ]
        return NotificationListResponse(
            count=len(notifications),
            total=total,
            unread_count=unread_count,
            page=request.page,
            limit=request.limit,
            has_more=offset + len(notifications) < total,
            notifications=notifications,
        )

    def mark_read(
        self, actor_user_id: int, notification_id: int | None
    ) -> NotificationReadResponse:
        """Idempotently mark one owned row or a bounded owned unread set."""
        with self._session.begin():
            actor = self._active_actor(self._repository.actor(actor_user_id, lock=True))
            account_created_at = self._account_created_at(actor)
            if notification_id is not None:
                row = self._repository.lock_owned_notification(
                    actor_user_id, account_created_at, notification_id
                )
                if row is None:
                    raise NotificationError("notification_not_found", "Notification not found", 404)
                notification_ids = [notification_id]
                message = "Notification marked as read"
            else:
                unread_count = self._repository.count_for_user(
                    actor_user_id, account_created_at, unread_only=True
                )
                if unread_count > _MAX_MARK_ALL:
                    raise NotificationError(
                        "notification_mark_all_too_large",
                        "Too many unread notifications; mark them in smaller batches",
                        409,
                    )
                notification_ids = self._repository.lock_unread_ids(
                    actor_user_id, account_created_at, limit=_MAX_MARK_ALL
                )
                message = "All unread notifications marked as read"
            updated_count = self._repository.mark_read(actor_user_id, notification_ids)
        logger.info(
            "notification_read_state_changed",
            actor_user_id=actor_user_id,
            notification_id=notification_id,
            updated_count=updated_count,
        )
        return NotificationReadResponse(message=message, updated_count=updated_count)
