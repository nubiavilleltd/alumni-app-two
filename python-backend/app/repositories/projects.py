"""Allowlisted project queries over the reviewed compatibility schema."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, Table, func, or_, select, update
from sqlalchemy.orm import Session

from app.models.generated import AlumniChapter, Projects, Users

PROJECTS_TABLE: Table = cast(Table, Projects.__table__)


class ProjectRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _columns() -> list[Any]:
        return [
            Projects.id,
            Projects.title,
            Projects.description,
            Projects.images,
            Projects.amount_raised,
            Projects.target_amount,
            Projects.status,
            Projects.location,
            Projects.sort_order,
            Projects.is_featured,
            Projects.chapter_id,
            Projects.year,
            Projects.start_date,
            Projects.end_date,
            Projects.conducted_by,
            Projects.created_at,
            Users.fullname.label("created_by_name"),
            AlumniChapter.chapter_name,
        ]

    @staticmethod
    def _public_conditions(filters: dict[str, Any]) -> list[Any]:
        conditions: list[Any] = [
            Projects.is_deleted == 0,
            Projects.status.in_(("active", "completed", "paused", "ongoing")),
        ]
        if filters.get("chapter_id") is not None:
            conditions.append(
                or_(
                    Projects.chapter_id == filters["chapter_id"],
                    Projects.chapter_id.is_(None),
                )
            )
        if filters.get("year") is not None:
            conditions.append(or_(Projects.year == filters["year"], Projects.year.is_(None)))
        if filters.get("is_featured") is not None:
            conditions.append(Projects.is_featured == int(filters["is_featured"]))
        # Public callers cannot ask for drafts, even when they know the identifier.
        if filters.get("status") is not None and filters["status"] != "draft":
            conditions.append(Projects.status == filters["status"])
        return conditions

    def list_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(*self._columns())
                .select_from(Projects)
                .outerjoin(Users, Users.id == Projects.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Projects.chapter_id)
                .where(*self._public_conditions(filters))
                .order_by(
                    Projects.is_featured.desc(),
                    Projects.sort_order.asc(),
                    Projects.created_at.desc(),
                    Projects.id.desc(),
                )
                .offset(int(filters["offset"]))
                .limit(int(filters["limit"]))
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def count(self, filters: dict[str, Any]) -> int:
        return int(
            self._session.scalar(
                select(func.count(Projects.id)).where(*self._public_conditions(filters))
            )
            or 0
        )

    def public_project(self, project_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Projects)
                .outerjoin(Users, Users.id == Projects.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Projects.chapter_id)
                .where(Projects.id == project_id, *self._public_conditions({}))
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def project(self, project_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Projects)
                .outerjoin(Users, Users.id == Projects.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Projects.chapter_id)
                .where(Projects.id == project_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    Users.id, Users.active, Users.user_role, Users.is_coordinator, Users.chapter_id
                )
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def chapter_enabled(self, chapter_id: int) -> bool:
        return (
            self._session.scalar(
                select(AlumniChapter.id).where(
                    AlumniChapter.id == chapter_id, AlumniChapter.is_enabled == 1
                )
            )
            is not None
        )

    def lock_project(self, project_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(Projects.id, Projects.images, Projects.is_deleted)
                .where(Projects.id == project_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(PROJECTS_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Project insert did not return a primary key")
        return int(key[0])

    def update_project(self, project_id: int, values: dict[str, Any]) -> None:
        self._session.execute(update(Projects).where(Projects.id == project_id).values(**values))
