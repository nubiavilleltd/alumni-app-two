"""Durable, secret-free administrative audit recording."""

from __future__ import annotations

import json
from typing import Any, cast

from sqlalchemy import Table
from sqlalchemy.orm import Session

from app.models.generated import AuditLog

AUDIT_LOG_TABLE = cast(Table, AuditLog.__table__)


class AuditRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    def record(
        self,
        *,
        actor_user_id: int | None,
        action: str,
        target_type: str | None,
        target_id: int | None,
        details: dict[str, Any] | None = None,
    ) -> None:
        """Append one immutable, PII-free audit row within the current transaction."""
        self._session.execute(
            AUDIT_LOG_TABLE.insert().values(
                actor_user_id=actor_user_id,
                action=action,
                target_type=target_type,
                target_id=target_id,
                details=json.dumps(details, ensure_ascii=False) if details is not None else None,
            )
        )
