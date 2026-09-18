"""Persistence for Web Push subscriptions."""

from __future__ import annotations

from datetime import datetime
from typing import Any, cast

from sqlalchemy import Table, select
from sqlalchemy.dialects.mysql import insert as mysql_insert
from sqlalchemy.orm import Session

from app.models.generated import UserPushSubscriptions

SUBSCRIPTIONS_TABLE = cast(Table, UserPushSubscriptions.__table__)


class PushRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    def upsert_subscription(
        self,
        user_id: int,
        endpoint: str,
        p256dh: str,
        auth: str,
        browser: str | None,
        now: datetime,
    ) -> None:
        """Insert or refresh the current user's subscription keyed by endpoint."""
        statement = (
            mysql_insert(SUBSCRIPTIONS_TABLE)
            .values(
                user_id=user_id,
                endpoint=endpoint,
                p256dh=p256dh,
                auth=auth,
                browser=browser,
                created_at=now,
                updated_at=now,
            )
            .on_duplicate_key_update(
                p256dh=p256dh,
                auth=auth,
                browser=browser,
                updated_at=now,
            )
        )
        self._session.execute(statement)

    def subscriptions(self, user_id: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(
                    UserPushSubscriptions.endpoint,
                    UserPushSubscriptions.p256dh,
                    UserPushSubscriptions.auth,
                ).where(UserPushSubscriptions.user_id == user_id)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]
