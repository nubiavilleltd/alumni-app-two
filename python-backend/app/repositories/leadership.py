"""Allowlisted leadership queries over the reviewed compatibility schema."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, Table, or_, select, update
from sqlalchemy.orm import Session

from app.models.generated import AlumniChapter, Leadership, Users

LEADERSHIP_TABLE: Table = cast(Table, Leadership.__table__)


class LeadershipRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _columns() -> list[Any]:
        return [
            Leadership.id,
            Leadership.user_id,
            Leadership.position_title,
            Leadership.message,
            Leadership.leadership_photo,
            Leadership.chapter_id,
            Leadership.year,
            Leadership.sort_order,
            Leadership.is_featured,
            Leadership.is_active,
            Leadership.created_at,
            Leadership.updated_at,
            Users.fullname,
            Users.avatar.label("user_avatar"),
            AlumniChapter.chapter_name,
        ]

    @staticmethod
    def _conditions(filters: dict[str, Any]) -> list[Any]:
        conditions: list[Any] = [
            Leadership.is_deleted == 0,
            Leadership.is_active == int(filters["is_active"]),
        ]
        if filters.get("chapter_id") is not None:
            conditions.append(
                or_(Leadership.chapter_id == filters["chapter_id"], Leadership.chapter_id.is_(None))
            )
        if filters.get("year") is not None:
            conditions.append(or_(Leadership.year == filters["year"], Leadership.year.is_(None)))
        if filters.get("is_featured") is not None:
            conditions.append(Leadership.is_featured == int(filters["is_featured"]))
        return conditions

    def list_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(*self._columns())
                .select_from(Leadership)
                .outerjoin(Users, Users.id == Leadership.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == Leadership.chapter_id)
                .where(*self._conditions(filters))
                .order_by(
                    Leadership.sort_order.asc(), Leadership.created_at.asc(), Leadership.id.asc()
                )
                .limit(int(filters["limit"]))
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def public_leader(self, leader_id: int | None, user_id: int | None) -> dict[str, Any] | None:
        condition = (
            Leadership.id == leader_id if leader_id is not None else Leadership.user_id == user_id
        )
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Leadership)
                .outerjoin(Users, Users.id == Leadership.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == Leadership.chapter_id)
                .where(condition, Leadership.is_deleted == 0, Leadership.is_active == 1)
                .order_by(
                    Leadership.sort_order.asc(), Leadership.created_at.asc(), Leadership.id.asc()
                )
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def leader(self, leader_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Leadership)
                .outerjoin(Users, Users.id == Leadership.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == Leadership.chapter_id)
                .where(Leadership.id == leader_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
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
        return dict(row) if row is not None else None

    def lock_leader(self, leader_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    Leadership.id,
                    Leadership.user_id,
                    Leadership.year,
                    Leadership.leadership_photo,
                    Leadership.is_deleted,
                )
                .where(Leadership.id == leader_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def user_exists(self, user_id: int) -> bool:
        return self._session.scalar(select(Users.id).where(Users.id == user_id)) is not None

    def chapter_enabled(self, chapter_id: int) -> bool:
        return (
            self._session.scalar(
                select(AlumniChapter.id).where(
                    AlumniChapter.id == chapter_id, AlumniChapter.is_enabled == 1
                )
            )
            is not None
        )

    def active_duplicate(
        self, user_id: int, year: str | None, *, except_id: int | None = None
    ) -> bool:
        clauses: list[Any] = [Leadership.user_id == user_id, Leadership.is_deleted == 0]
        clauses.append(Leadership.year == year if year is not None else Leadership.year.is_(None))
        if except_id is not None:
            clauses.append(Leadership.id != except_id)
        return self._session.scalar(select(Leadership.id).where(*clauses).limit(1)) is not None

    def clear_featured(self, year: str | None, *, except_id: int | None = None) -> None:
        clauses: list[Any] = [Leadership.is_featured == 1, Leadership.is_deleted == 0]
        clauses.append(Leadership.year == year if year is not None else Leadership.year.is_(None))
        if except_id is not None:
            clauses.append(Leadership.id != except_id)
        self._session.execute(update(Leadership).where(*clauses).values(is_featured=0))

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(LEADERSHIP_TABLE.insert().values(**values))
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Leadership insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def update(self, leader_id: int, values: dict[str, Any]) -> None:
        self._session.execute(update(Leadership).where(Leadership.id == leader_id).values(**values))
