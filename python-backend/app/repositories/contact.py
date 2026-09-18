"""Persistence for the public contact form."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, Table, func, select
from sqlalchemy.orm import Session

from app.models.generated import ContactUs, Users

CONTACT_TABLE: Table = cast(Table, ContactUs.__table__)


class ContactRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(CONTACT_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Contact insert did not return a primary key")
        return int(key[0])

    def account_manager_recipients(self) -> list[dict[str, Any]]:
        """Return active recipients with reviewed account-management roles."""
        rows = (
            self._session.execute(
                select(Users.email, Users.fullname)
                .where(
                    Users.active == 1,
                    func.lower(func.trim(Users.user_role)).in_(
                        ("admin", "administrator", "manager", "superadmin", "super admin")
                    ),
                )
                .order_by(Users.id)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]
