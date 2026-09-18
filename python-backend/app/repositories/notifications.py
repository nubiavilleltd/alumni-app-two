"""Persistence operations for the current per-user notification schema."""

from __future__ import annotations

from datetime import datetime
from typing import Any, cast

from sqlalchemy import CursorResult, RowMapping, Table, func, select, update
from sqlalchemy.orm import Session

from app.models.generated import Notifications, Users

NOTIFICATIONS_TABLE = cast(Table, Notifications.__table__)


class NotificationRepository:
    """Keep notification ownership and state transitions in SQL predicates."""

    def __init__(self, session: Session) -> None:
        self._session = session

    def actor(self, user_id: int, *, lock: bool = False) -> dict[str, Any] | None:
        """Load the current account state and account-creation boundary."""
        statement = select(Users.id, Users.active, Users.created_on).where(Users.id == user_id)
        if lock:
            statement = statement.with_for_update()
        row = self._session.execute(statement.limit(1)).mappings().first()
        return dict(row) if row is not None else None

    def actor_authority(self, user_id: int) -> dict[str, Any] | None:
        """Load the facts required to derive the notification-creation permission."""
        row = (
            self._session.execute(
                select(
                    Users.id, Users.active, Users.user_role, Users.is_coordinator, Users.created_on
                )
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def active_user_exists(self, user_id: int) -> bool:
        """Confirm a notification target is an active account."""
        return (
            self._session.scalar(
                select(Users.id).where(Users.id == user_id, Users.active == 1).limit(1)
            )
            is not None
        )

    def create(self, values: dict[str, Any]) -> int:
        """Insert one per-user notification row and return its ID."""
        result = cast(
            CursorResult[Any],
            self._session.execute(
                NOTIFICATIONS_TABLE.insert().values(
                    user_id=values["user_id"],
                    type=values["type"],
                    message=values["message"],
                    link=values.get("link"),
                    chapter_id=values.get("chapter_id"),
                    year=values.get("year"),
                    is_read=0,
                )
            ),
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Notification insert did not return a primary key")
        return int(key[0])

    @staticmethod
    def _eligible_conditions(user_id: int, account_created_at: datetime) -> tuple[Any, ...]:
        """Prevent reused identifiers from exposing rows older than the account."""
        return (
            Notifications.user_id == user_id,
            Notifications.created_at >= account_created_at,
        )

    def list_for_user(
        self,
        user_id: int,
        account_created_at: datetime,
        *,
        offset: int,
        limit: int,
        unread_only: bool,
    ) -> list[dict[str, Any]]:
        """Return only owned rows in deterministic newest-first order."""
        conditions = list(self._eligible_conditions(user_id, account_created_at))
        if unread_only:
            conditions.append(Notifications.is_read == 0)
        rows = (
            self._session.execute(
                select(
                    Notifications.id,
                    Notifications.type,
                    Notifications.message,
                    Notifications.link,
                    Notifications.chapter_id,
                    Notifications.year,
                    Notifications.is_read,
                    Notifications.created_at,
                )
                .where(*conditions)
                .order_by(Notifications.id.desc())
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def count_for_user(
        self, user_id: int, account_created_at: datetime, *, unread_only: bool
    ) -> int:
        """Count the same self-scoped rows used by list and mark operations."""
        conditions = list(self._eligible_conditions(user_id, account_created_at))
        if unread_only:
            conditions.append(Notifications.is_read == 0)
        return int(
            self._session.scalar(select(func.count(Notifications.id)).where(*conditions)) or 0
        )

    def lock_owned_notification(
        self, user_id: int, account_created_at: datetime, notification_id: int
    ) -> dict[str, Any] | None:
        """Lock one eligible row without revealing whether another owner has that ID."""
        row: RowMapping | None = (
            self._session.execute(
                select(Notifications.id, Notifications.is_read)
                .where(
                    *self._eligible_conditions(user_id, account_created_at),
                    Notifications.id == notification_id,
                )
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def lock_unread_ids(
        self, user_id: int, account_created_at: datetime, *, limit: int
    ) -> list[int]:
        """Bound an all-unread mutation before applying it."""
        return [
            int(value)
            for value in self._session.scalars(
                select(Notifications.id)
                .where(
                    *self._eligible_conditions(user_id, account_created_at),
                    Notifications.is_read == 0,
                )
                .order_by(Notifications.id)
                .with_for_update()
                .limit(limit)
            )
        ]

    def mark_read(self, user_id: int, notification_ids: list[int]) -> int:
        """Update only still-unread rows owned by the current member."""
        if not notification_ids:
            return 0
        result = cast(
            CursorResult[Any],
            self._session.execute(
                update(NOTIFICATIONS_TABLE)
                .where(
                    Notifications.user_id == user_id,
                    Notifications.id.in_(notification_ids),
                    Notifications.is_read == 0,
                )
                .values(is_read=1)
            ),
        )
        return int(result.rowcount or 0)
