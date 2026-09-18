"""Allowlisted job-vacancy queries over the reviewed compatibility schema."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, Table, delete, func, select, update
from sqlalchemy.orm import Session

from app.models.generated import AlumniChapter, JobVacancies, Users

VACANCIES_TABLE: Table = cast(Table, JobVacancies.__table__)


class VacancyRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _columns() -> list[Any]:
        return [
            JobVacancies.id,
            JobVacancies.user_id,
            JobVacancies.chapter_id,
            JobVacancies.job_title,
            JobVacancies.company_name,
            JobVacancies.job_type,
            JobVacancies.workplace_type,
            JobVacancies.level_of_expertise,
            JobVacancies.application_type,
            JobVacancies.location,
            JobVacancies.salary,
            JobVacancies.application_deadline,
            JobVacancies.keywords,
            JobVacancies.about_role,
            JobVacancies.responsibilities,
            JobVacancies.requirements,
            JobVacancies.application_email,
            JobVacancies.application_link,
            JobVacancies.flyer,
            JobVacancies.created_at,
            JobVacancies.updated_at,
            Users.fullname.label("posted_by"),
        ]

    @staticmethod
    def _conditions(filters: dict[str, Any]) -> list[Any]:
        return [
            getattr(JobVacancies, field) == filters[field]
            for field in (
                "id",
                "user_id",
                "chapter_id",
                "job_type",
                "workplace_type",
                "level_of_expertise",
            )
            if filters.get(field) is not None
        ]

    def list_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(*self._columns())
                .select_from(JobVacancies)
                .outerjoin(Users, Users.id == JobVacancies.user_id)
                .where(*self._conditions(filters))
                .order_by(JobVacancies.created_at.desc(), JobVacancies.id.desc())
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
                select(func.count(JobVacancies.id)).where(*self._conditions(filters))
            )
            or 0
        )

    def details(self, vacancy_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(JobVacancies)
                .outerjoin(Users, Users.id == JobVacancies.user_id)
                .where(JobVacancies.id == vacancy_id)
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

    def lock_vacancy(self, vacancy_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(JobVacancies.id, JobVacancies.user_id, JobVacancies.flyer)
                .where(JobVacancies.id == vacancy_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(VACANCIES_TABLE.insert().values(**values))
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Vacancy insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def update(self, vacancy_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(JobVacancies).where(JobVacancies.id == vacancy_id).values(**values)
        )

    def delete(self, vacancy_id: int) -> None:
        self._session.execute(delete(JobVacancies).where(JobVacancies.id == vacancy_id))
