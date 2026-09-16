"""Allowlisted announcement queries over the reviewed compatibility schema."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, RowMapping, Table, func, select, update
from sqlalchemy.orm import Session

from app.models.generated import AlumniChapter, Announcements, Users

ANNOUNCEMENTS_TABLE = cast(Table, Announcements.__table__)


class AnnouncementRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _as_dict(row: RowMapping | None) -> dict[str, Any] | None:
        return dict(row) if row is not None else None

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.user_role, Users.is_coordinator)
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def chapter_exists(self, chapter_id: int) -> bool:
        return (
            self._session.scalar(select(AlumniChapter.id).where(AlumniChapter.id == chapter_id))
            is not None
        )

    def announcement(self, announcement_id: int, *, lock: bool = False) -> dict[str, Any] | None:
        statement = select(
            Announcements.id,
            Announcements.title,
            Announcements.content,
            Announcements.imag,
            Announcements.type,
            Announcements.created_by,
            Announcements.chapter_id,
            Announcements.year,
            Announcements.starts_at,
            Announcements.ends_at,
            Announcements.created_at,
            Announcements.updated_at,
        ).where(Announcements.id == announcement_id)
        if lock:
            statement = statement.with_for_update()
        row = self._session.execute(statement.limit(1)).mappings().first()
        return self._as_dict(row)

    def create(self, values: dict[str, Any]) -> int:
        result = cast(
            CursorResult[Any], self._session.execute(ANNOUNCEMENTS_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Announcement insert did not return a primary key")
        return int(key[0])

    def update(self, announcement_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(ANNOUNCEMENTS_TABLE).where(Announcements.id == announcement_id).values(**values)
        )

    def delete(self, announcement_id: int) -> None:
        self._session.execute(
            ANNOUNCEMENTS_TABLE.delete().where(Announcements.id == announcement_id)
        )

    def list_rows(
        self, filters: dict[str, Any], *, offset: int, limit: int
    ) -> list[dict[str, Any]]:
        conditions = self._conditions(filters)
        rows = (
            self._session.execute(
                select(
                    Announcements.id,
                    Announcements.title,
                    Announcements.content,
                    Announcements.imag,
                    Announcements.type,
                    Announcements.created_by,
                    Announcements.chapter_id,
                    Announcements.year,
                    Announcements.starts_at,
                    Announcements.ends_at,
                    Announcements.created_at,
                    Announcements.updated_at,
                    func.concat_ws(" ", Users.first_name, Users.last_name).label("created_by_name"),
                )
                .select_from(Announcements)
                .outerjoin(Users, Users.id == Announcements.created_by)
                .where(*conditions)
                .order_by(Announcements.created_at.desc(), Announcements.id.desc())
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def count(self, filters: dict[str, Any]) -> int:
        return int(
            self._session.scalar(
                select(func.count(Announcements.id)).where(*self._conditions(filters))
            )
            or 0
        )

    @staticmethod
    def _conditions(filters: dict[str, Any]) -> list[Any]:
        conditions: list[Any] = []
        for field in ("id", "created_by", "type", "chapter_id", "year"):
            value = filters.get(field)
            if value is not None:
                conditions.append(getattr(Announcements, field) == value)
        return conditions
