"""Allowlisted event queries over the reviewed compatibility schema."""

from __future__ import annotations

from typing import Any, cast

from sqlalchemy import CursorResult, Table, delete, func, or_, select, update
from sqlalchemy.orm import Session

from app.models.generated import (
    AlumniChapter,
    EventAttendees,
    EventRegistrationAnswers,
    EventRegistrationFormQuestions,
    EventRegistrationForms,
    EventRegistrationFormVersions,
    Events,
    Users,
)

EVENTS_TABLE: Table = cast(Table, Events.__table__)
EVENT_ATTENDEES_TABLE: Table = cast(Table, EventAttendees.__table__)
EVENT_REGISTRATION_ANSWERS_TABLE: Table = cast(Table, EventRegistrationAnswers.__table__)
EVENT_REGISTRATION_FORMS_TABLE: Table = cast(Table, EventRegistrationForms.__table__)
EVENT_REGISTRATION_QUESTIONS_TABLE: Table = cast(Table, EventRegistrationFormQuestions.__table__)
EVENT_REGISTRATION_FORM_VERSIONS_TABLE: Table = cast(Table, EventRegistrationFormVersions.__table__)


class EventRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _columns() -> list[Any]:
        return [
            Events.id,
            Events.title,
            Events.description,
            Events.event_banner,
            Events.status,
            Events.location,
            Events.start_date,
            Events.end_date,
            Events.start_time,
            Events.end_time,
            Events.color,
            Events.chapter_id,
            Events.year,
            Events.visibility,
            Events.max_attendees,
            Events.created_at,
            Events.updated_at,
            Users.fullname.label("created_by_name"),
            AlumniChapter.chapter_name,
            select(func.count(EventAttendees.id))
            .where(EventAttendees.event_id == Events.id, EventAttendees.status == "going")
            .correlate(Events)
            .scalar_subquery()
            .label("attendee_count"),
            select(func.count(EventRegistrationForms.id))
            .where(
                EventRegistrationForms.event_id == Events.id,
                EventRegistrationForms.is_active == 1,
            )
            .correlate(Events)
            .scalar_subquery()
            .label("registration_form_count"),
        ]

    @staticmethod
    def _public_conditions(filters: dict[str, Any]) -> list[Any]:
        clauses: list[Any] = [
            Events.is_approved == 1,
            Events.visibility == "public",
            Events.status != "draft",
        ]
        if filters.get("chapter_id") is not None:
            clauses.append(
                or_(Events.chapter_id == filters["chapter_id"], Events.chapter_id.is_(None))
            )
        if filters.get("year") is not None:
            clauses.append(or_(Events.year == filters["year"], Events.year.is_(None)))
        if filters.get("status") is not None and filters["status"] != "draft":
            clauses.append(Events.status == filters["status"])
        return clauses

    def list_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(*self._columns())
                .select_from(Events)
                .outerjoin(Users, Users.id == Events.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Events.chapter_id)
                .where(*self._public_conditions(filters))
                .order_by(Events.start_date.asc(), Events.id.asc())
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
                select(func.count(Events.id)).where(*self._public_conditions(filters))
            )
            or 0
        )

    def public_event(self, event_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Events)
                .outerjoin(Users, Users.id == Events.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Events.chapter_id)
                .where(Events.id == event_id, *self._public_conditions({}))
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def event(self, event_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(Events)
                .outerjoin(Users, Users.id == Events.created_by)
                .outerjoin(AlumniChapter, AlumniChapter.id == Events.chapter_id)
                .where(Events.id == event_id)
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

    def lock_event(self, event_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(Events.id, Events.event_banner)
                .where(Events.id == event_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def lock_event_for_rsvp(self, event_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    Events.id,
                    Events.title,
                    Events.event_date,
                    Events.start_date,
                    Events.year,
                    Events.status,
                    Events.visibility,
                    Events.is_approved,
                    Events.max_attendees,
                )
                .where(Events.id == event_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def lock_attendee(self, event_id: int, user_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    EventAttendees.id,
                    EventAttendees.event_id,
                    EventAttendees.user_id,
                    EventAttendees.year,
                    EventAttendees.status,
                    EventAttendees.additional_info,
                    EventAttendees.registered_at,
                )
                .where(EventAttendees.event_id == event_id, EventAttendees.user_id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def count_going(self, event_id: int) -> int:
        return int(
            self._session.scalar(
                select(func.count(EventAttendees.id)).where(
                    EventAttendees.event_id == event_id, EventAttendees.status == "going"
                )
            )
            or 0
        )

    def create_attendee(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any],
            self._session.execute(EVENT_ATTENDEES_TABLE.insert().values(**values)),
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Event attendee insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def update_attendee(self, attendee_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(EventAttendees).where(EventAttendees.id == attendee_id).values(**values)
        )

    def attendee_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        clauses: list[Any] = [EventAttendees.event_id == filters["event_id"]]
        if filters.get("status") is not None:
            clauses.append(EventAttendees.status == filters["status"])
        if filters.get("year") is not None:
            clauses.append(EventAttendees.year == filters["year"])
        rows = (
            self._session.execute(
                select(
                    EventAttendees.id.label("attendee_id"),
                    EventAttendees.user_id,
                    EventAttendees.status,
                    EventAttendees.year,
                    EventAttendees.additional_info,
                    EventAttendees.registered_at,
                    Users.fullname,
                    Users.email,
                    Users.phone,
                    Users.avatar,
                )
                .select_from(EventAttendees)
                .join(Users, Users.id == EventAttendees.user_id)
                .where(*clauses)
                .order_by(EventAttendees.registered_at.asc(), EventAttendees.id.asc())
                .offset(int(filters["offset"]))
                .limit(int(filters["limit"]))
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def attendee_total(self, filters: dict[str, Any]) -> int:
        clauses: list[Any] = [EventAttendees.event_id == filters["event_id"]]
        if filters.get("status") is not None:
            clauses.append(EventAttendees.status == filters["status"])
        if filters.get("year") is not None:
            clauses.append(EventAttendees.year == filters["year"])
        return int(self._session.scalar(select(func.count(EventAttendees.id)).where(*clauses)) or 0)

    def attendee_summary(self, event_id: int) -> dict[str, int]:
        rows = self._session.execute(
            select(EventAttendees.status, func.count(EventAttendees.id).label("count"))
            .where(EventAttendees.event_id == event_id)
            .group_by(EventAttendees.status)
        ).all()
        counts = {
            str(status.value if hasattr(status, "value") else status): int(count)
            for status, count in rows
        }
        return {
            "going": counts.get("going", 0),
            "maybe": counts.get("maybe", 0),
            "not_going": counts.get("not_going", 0),
            "total": sum(counts.values()),
        }

    def form_rows(self, event_id: int, *, include_inactive: bool) -> list[dict[str, Any]]:
        clauses: list[Any] = [EventRegistrationForms.event_id == event_id]
        if not include_inactive:
            clauses.append(EventRegistrationForms.is_active == 1)
        rows = (
            self._session.execute(
                select(
                    EventRegistrationForms.id,
                    EventRegistrationForms.event_id,
                    EventRegistrationForms.name,
                    EventRegistrationForms.description,
                    EventRegistrationForms.sort_order,
                    EventRegistrationForms.version,
                    EventRegistrationForms.is_active,
                )
                .where(*clauses)
                .order_by(EventRegistrationForms.sort_order.asc(), EventRegistrationForms.id.asc())
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def question_rows(self, form_ids: list[int]) -> list[dict[str, Any]]:
        if not form_ids:
            return []
        rows = (
            self._session.execute(
                select(
                    EventRegistrationFormQuestions.id,
                    EventRegistrationFormQuestions.form_id,
                    EventRegistrationFormQuestions.label,
                    EventRegistrationFormQuestions.type,
                    EventRegistrationFormQuestions.required,
                    EventRegistrationFormQuestions.placeholder,
                    EventRegistrationFormQuestions.options_json,
                    EventRegistrationFormQuestions.max_selections,
                    EventRegistrationFormQuestions.sort_order,
                )
                .where(EventRegistrationFormQuestions.form_id.in_(form_ids))
                .order_by(
                    EventRegistrationFormQuestions.form_id.asc(),
                    EventRegistrationFormQuestions.sort_order.asc(),
                    EventRegistrationFormQuestions.id.asc(),
                )
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def lock_form(self, form_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    EventRegistrationForms.id,
                    EventRegistrationForms.event_id,
                    EventRegistrationForms.name,
                    EventRegistrationForms.description,
                    EventRegistrationForms.sort_order,
                    EventRegistrationForms.version,
                    EventRegistrationForms.is_active,
                )
                .where(EventRegistrationForms.id == form_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def lock_forms_for_event(self, event_id: int, *, active_only: bool) -> list[dict[str, Any]]:
        clauses: list[Any] = [EventRegistrationForms.event_id == event_id]
        if active_only:
            clauses.append(EventRegistrationForms.is_active == 1)
        rows = (
            self._session.execute(
                select(
                    EventRegistrationForms.id,
                    EventRegistrationForms.event_id,
                    EventRegistrationForms.name,
                    EventRegistrationForms.description,
                    EventRegistrationForms.sort_order,
                    EventRegistrationForms.version,
                    EventRegistrationForms.is_active,
                )
                .where(*clauses)
                .order_by(EventRegistrationForms.sort_order.asc(), EventRegistrationForms.id.asc())
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def create_form(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any],
            self._session.execute(EVENT_REGISTRATION_FORMS_TABLE.insert().values(**values)),
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Event registration form insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def update_form(self, form_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(EventRegistrationForms)
            .where(EventRegistrationForms.id == form_id)
            .values(**values)
        )

    def delete_form(self, form_id: int) -> None:
        self._session.execute(
            delete(EventRegistrationForms).where(EventRegistrationForms.id == form_id)
        )

    def create_question(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any],
            self._session.execute(EVENT_REGISTRATION_QUESTIONS_TABLE.insert().values(**values)),
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Event registration question insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def create_form_version(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any],
            self._session.execute(EVENT_REGISTRATION_FORM_VERSIONS_TABLE.insert().values(**values)),
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError(
                "Event registration form-version insert did not return a primary key"
            )
        return int(result.inserted_primary_key[0])

    def lock_question(self, form_id: int, question_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(EventRegistrationFormQuestions.id, EventRegistrationFormQuestions.form_id)
                .where(
                    EventRegistrationFormQuestions.id == question_id,
                    EventRegistrationFormQuestions.form_id == form_id,
                )
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def update_question(self, question_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(EventRegistrationFormQuestions)
            .where(EventRegistrationFormQuestions.id == question_id)
            .values(**values)
        )

    def delete_question(self, question_id: int) -> None:
        self._session.execute(
            delete(EventRegistrationFormQuestions).where(
                EventRegistrationFormQuestions.id == question_id
            )
        )

    def delete_questions_for_form(self, form_id: int) -> None:
        self._session.execute(
            delete(EventRegistrationFormQuestions).where(
                EventRegistrationFormQuestions.form_id == form_id
            )
        )

    def form_answer_count(self, form_id: int) -> int:
        return int(
            self._session.scalar(
                select(func.count(EventRegistrationAnswers.id)).where(
                    EventRegistrationAnswers.form_id == form_id
                )
            )
            or 0
        )

    def delete_answers_for_attendee(self, attendee_id: int) -> None:
        self._session.execute(
            delete(EventRegistrationAnswers).where(
                EventRegistrationAnswers.attendee_id == attendee_id
            )
        )

    def create_answer(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any],
            self._session.execute(EVENT_REGISTRATION_ANSWERS_TABLE.insert().values(**values)),
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Event registration answer insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def submission_rows(self, filters: dict[str, Any]) -> list[dict[str, Any]]:
        clauses: list[Any] = [EventAttendees.event_id == filters["event_id"]]
        if filters.get("rsvp_status") is not None:
            clauses.append(EventAttendees.status == filters["rsvp_status"])
        answers = (
            select(func.count(EventRegistrationAnswers.id))
            .where(EventRegistrationAnswers.attendee_id == EventAttendees.id)
            .correlate(EventAttendees)
            .scalar_subquery()
        )
        rows = (
            self._session.execute(
                select(
                    EventAttendees.id.label("attendee_id"),
                    EventAttendees.user_id,
                    EventAttendees.status.label("rsvp_status"),
                    EventAttendees.additional_info,
                    EventAttendees.registered_at,
                    Users.fullname,
                    Users.email,
                    Users.phone,
                    Users.avatar,
                    answers.label("answer_count"),
                )
                .select_from(EventAttendees)
                .join(Users, Users.id == EventAttendees.user_id)
                .where(*clauses)
                .order_by(EventAttendees.registered_at.desc(), EventAttendees.id.desc())
                .offset(int(filters["offset"]))
                .limit(int(filters["per_page"]))
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def submission_total(self, filters: dict[str, Any]) -> int:
        clauses: list[Any] = [EventAttendees.event_id == filters["event_id"]]
        if filters.get("rsvp_status") is not None:
            clauses.append(EventAttendees.status == filters["rsvp_status"])
        return int(self._session.scalar(select(func.count(EventAttendees.id)).where(*clauses)) or 0)

    def submission_detail(
        self, event_id: int, attendee_id: int | None, user_id: int | None
    ) -> dict[str, Any] | None:
        clauses: list[Any] = [EventAttendees.event_id == event_id]
        if attendee_id is not None:
            clauses.append(EventAttendees.id == attendee_id)
        if user_id is not None:
            clauses.append(EventAttendees.user_id == user_id)
        row = (
            self._session.execute(
                select(
                    EventAttendees.id.label("attendee_id"),
                    EventAttendees.event_id,
                    EventAttendees.user_id,
                    EventAttendees.status.label("rsvp_status"),
                    EventAttendees.additional_info,
                    EventAttendees.registered_at,
                    Users.fullname,
                    Users.email,
                    Users.phone,
                    Users.avatar,
                )
                .select_from(EventAttendees)
                .join(Users, Users.id == EventAttendees.user_id)
                .where(*clauses)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def answer_rows(self, attendee_id: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(
                    EventRegistrationAnswers.form_id,
                    EventRegistrationAnswers.form_version,
                    EventRegistrationAnswers.question_id,
                    EventRegistrationAnswers.question_label_snapshot,
                    EventRegistrationAnswers.question_type,
                    EventRegistrationAnswers.question_order,
                    EventRegistrationAnswers.required_snapshot,
                    EventRegistrationAnswers.form_name_snapshot,
                    EventRegistrationAnswers.placeholder_snapshot,
                    EventRegistrationAnswers.options_json_snapshot,
                    EventRegistrationAnswers.max_selections_snapshot,
                    EventRegistrationAnswers.answer_text,
                    EventRegistrationAnswers.answer_json,
                    EventRegistrationForms.name.label("form_name"),
                )
                .select_from(EventRegistrationAnswers)
                .outerjoin(
                    EventRegistrationForms,
                    EventRegistrationForms.id == EventRegistrationAnswers.form_id,
                )
                .where(EventRegistrationAnswers.attendee_id == attendee_id)
                .order_by(
                    EventRegistrationAnswers.form_id.asc(),
                    EventRegistrationAnswers.question_order.asc(),
                    EventRegistrationAnswers.id.asc(),
                )
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(EVENTS_TABLE.insert().values(**values))
        )
        if not result.inserted_primary_key or result.inserted_primary_key[0] is None:
            raise RuntimeError("Event insert did not return a primary key")
        return int(result.inserted_primary_key[0])

    def update(self, event_id: int, values: dict[str, Any]) -> None:
        self._session.execute(update(Events).where(Events.id == event_id).values(**values))

    def delete(self, event_id: int) -> None:
        self._session.execute(delete(Events).where(Events.id == event_id))
