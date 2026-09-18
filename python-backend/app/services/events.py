"""Public event presentation and current-policy event administration."""

from __future__ import annotations

import json
from datetime import UTC, datetime
from enum import Enum
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import EventStorage, PreparedAvatar, StoredAvatar
from app.repositories.events import EventRepository
from app.schemas.events import (
    EventAttendee,
    EventAttendeeEvent,
    EventAttendeeFilters,
    EventAttendeeListResponse,
    EventAttendeeSummary,
    EventCreateRequest,
    EventDeleteRequest,
    EventDetailResponse,
    EventFilters,
    EventItem,
    EventListResponse,
    EventMutationResponse,
    EventRegistrationAnswerInput,
    EventRegistrationAnswerView,
    EventRegistrationForm,
    EventRegistrationFormCreateRequest,
    EventRegistrationFormManageRequest,
    EventRegistrationFormMutationResponse,
    EventRegistrationFormQuestion,
    EventRegistrationFormsRequest,
    EventRegistrationFormsResponse,
    EventRegistrationRequest,
    EventRegistrationSubmissionDetail,
    EventRegistrationSubmissionDetailRequest,
    EventRegistrationSubmissionDetailResponse,
    EventRegistrationSubmissionFilters,
    EventRegistrationSubmissionFormView,
    EventRegistrationSubmissionListItem,
    EventRegistrationSubmissionResponse,
    EventRegistrationSubmissionsResponse,
    EventRegistrationWithFormsRequest,
    EventRsvp,
    EventRsvpManageRequest,
    EventRsvpResponse,
    EventUpdateRequest,
)


class EventError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code, self.message, self.http_status = code, message, http_status


class EventService:
    def __init__(self, session: Session, storage: EventStorage | None = None) -> None:
        self._session, self._repository, self._storage = session, EventRepository(session), storage

    @staticmethod
    def _value(value: object, default: str | None = None) -> str | None:
        raw = value.value if isinstance(value, Enum) else value
        return str(raw) if raw is not None else default

    @classmethod
    def _item(cls, row: dict[str, Any]) -> EventItem:
        payload = {key: value for key, value in row.items() if key in EventItem.model_fields}
        payload["status"] = cls._value(row.get("status"), "upcoming")
        payload["visibility"] = cls._value(row.get("visibility"))
        banner = row.get("event_banner")
        if banner:
            parsed = urlparse(str(banner))
            payload["event_banner"] = (
                str(banner)
                if str(banner).startswith("uploads/") or parsed.scheme in {"http", "https"}
                else None
            )
        payload["attendee_count"] = int(row.get("attendee_count") or 0)
        payload["registration_form_count"] = int(row.get("registration_form_count") or 0)
        payload["has_registration_questions"] = payload["registration_form_count"] > 0
        return EventItem(**payload)

    @staticmethod
    def _stored(value: str | None) -> StoredAvatar | None:
        prefix = "uploads/events/"
        if not value or not value.startswith(prefix):
            return None
        filename = Path(value).name
        return StoredAvatar(value, filename, "") if filename == value.removeprefix(prefix) else None

    def _delete_stored(self, value: str | None) -> None:
        stored = self._stored(value)
        if stored is not None and self._storage is not None:
            self._storage.delete(stored)

    @staticmethod
    def _actor(actor: dict[str, Any] | None, actor_id: int) -> dict[str, Any]:
        if actor is None or not bool(actor.get("active")):
            raise EventError("event_actor_unavailable", "Authentication required", 401)
        facts = AuthorizationFacts(
            actor_id, actor.get("user_role"), is_coordinator=bool(actor.get("is_coordinator"))
        )
        if not has_permission(facts, Permission.MANAGE_EVENTS):
            raise EventError("event_forbidden", "You cannot manage events", 403)
        return actor

    @staticmethod
    def _active_actor(actor: dict[str, Any] | None) -> dict[str, Any]:
        if actor is None or not bool(actor.get("active")):
            raise EventError("event_actor_unavailable", "Authentication required", 401)
        return actor

    @staticmethod
    def _rsvp_value(row: dict[str, Any]) -> EventRsvp:
        return EventRsvp(
            id=int(row["id"]),
            event_id=int(row["event_id"]),
            user_id=int(row["user_id"]),
            year=row.get("year"),
            status=str(EventService._value(row.get("status"), "going")),
            additional_info=row.get("additional_info"),
            registered_at=row.get("registered_at"),
        )

    @staticmethod
    def _rsvp_event(event: dict[str, Any], *, mutation: bool) -> None:
        status = EventService._value(event.get("status"))
        visibility = EventService._value(event.get("visibility"))
        if (
            event.get("is_approved") != 1
            or visibility != "public"
            or status not in {"upcoming", "active"}
        ):
            message = "Event registration is unavailable" if mutation else "Event not found"
            raise EventError("event_registration_unavailable", message, 404)

    def _capacity_available(self, event: dict[str, Any], current_status: str | None) -> None:
        maximum = int(event.get("max_attendees") or 0)
        if maximum <= 0 or current_status == "going":
            return
        if self._repository.count_going(int(event["id"])) >= maximum:
            raise EventError("event_full", "Event is fully booked", 409)

    def _chapter(self, chapter_id: int | None, actor: dict[str, Any]) -> int | None:
        resolved = chapter_id if chapter_id is not None else actor.get("chapter_id")
        if resolved is not None and not self._repository.chapter_enabled(int(resolved)):
            raise EventError("event_chapter_invalid", "Chapter not found or not enabled", 400)
        return int(resolved) if resolved is not None else None

    def _response(self, event_id: int) -> EventItem:
        row = self._repository.event(event_id)
        if row is None:
            raise RuntimeError("Event mutation did not return its event")
        return self._item(row)

    @staticmethod
    def _options(value: object) -> list[str] | None:
        if not value:
            return None
        try:
            parsed = json.loads(str(value))
        except (TypeError, ValueError):
            return None
        if not isinstance(parsed, list) or not all(isinstance(item, str) for item in parsed):
            return None
        return parsed

    @classmethod
    def _form_view(
        cls, row: dict[str, Any], questions: list[dict[str, Any]]
    ) -> EventRegistrationForm:
        return EventRegistrationForm(
            id=int(row["id"]),
            event_id=int(row["event_id"]),
            name=str(row["name"]),
            description=row.get("description"),
            sort_order=int(row.get("sort_order") or 0),
            version=int(row.get("version") or 1),
            is_active=bool(row.get("is_active")),
            questions=[
                EventRegistrationFormQuestion(
                    id=int(question["id"]),
                    label=str(question["label"]),
                    type=str(cls._value(question.get("type"), "short_answer")),
                    required=bool(question.get("required")),
                    placeholder=question.get("placeholder"),
                    options=cls._options(question.get("options_json")),
                    max_selections=(
                        int(question["max_selections"])
                        if question.get("max_selections") is not None
                        else None
                    ),
                    sort_order=int(question.get("sort_order") or 0),
                )
                for question in questions
            ],
        )

    def _form_views(self, event_id: int, *, include_inactive: bool) -> list[EventRegistrationForm]:
        forms = self._repository.form_rows(event_id, include_inactive=include_inactive)
        questions_by_form: dict[int, list[dict[str, Any]]] = {int(row["id"]): [] for row in forms}
        for question in self._repository.question_rows(list(questions_by_form)):
            questions_by_form[int(question["form_id"])].append(question)
        return [self._form_view(row, questions_by_form[int(row["id"])]) for row in forms]

    @staticmethod
    def _question_values(form_id: int, question: Any, sort_order: int) -> dict[str, Any]:
        choice_types = {"multiple_choice", "checkbox", "dropdown"}
        return {
            "form_id": form_id,
            "label": question.label,
            "type": question.type,
            "required": int(question.required),
            "placeholder": question.placeholder or None,
            "options_json": json.dumps(question.options) if question.type in choice_types else None,
            "max_selections": question.max_selections if question.type == "checkbox" else None,
            "sort_order": sort_order,
            "created_at": datetime.now(UTC).replace(tzinfo=None),
        }

    @staticmethod
    def _next_form_version(form: dict[str, Any]) -> int:
        version = int(form.get("version") or 1)
        if version >= 65_535:
            raise EventError("event_form_version_exhausted", "Form version limit reached", 409)
        return version + 1

    def _record_form_version(self, form_id: int, actor_id: int) -> None:
        """Persist an immutable definition snapshot before later mutations can replace it."""
        form = self._repository.lock_form(form_id)
        if form is None:
            raise RuntimeError("Event registration form disappeared before version capture")
        questions = self._repository.question_rows([form_id])
        question_snapshots = [
            {
                "id": int(question["id"]),
                "label": str(question["label"]),
                "type": str(self._value(question.get("type"), "short_answer")),
                "required": bool(question.get("required")),
                "placeholder": question.get("placeholder"),
                "options": self._options(question.get("options_json")) or [],
                "max_selections": (
                    int(question["max_selections"])
                    if question.get("max_selections") is not None
                    else None
                ),
                "sort_order": int(question.get("sort_order") or 0),
            }
            for question in questions
        ]
        self._repository.create_form_version(
            {
                "form_id": form_id,
                "version": int(form["version"]),
                "name_snapshot": str(form["name"]),
                "description_snapshot": form.get("description"),
                "sort_order_snapshot": int(form.get("sort_order") or 0),
                "questions_json": json.dumps(question_snapshots, ensure_ascii=False),
                "created_by": actor_id,
                "created_at": datetime.now(UTC).replace(tzinfo=None),
            }
        )

    def _require_event_manager(self, actor_id: int, event_id: int) -> dict[str, Any]:
        self._actor(self._repository.lock_actor(actor_id), actor_id)
        event = self._repository.lock_event_for_rsvp(event_id)
        if event is None:
            raise EventError("event_not_found", "Event not found", 404)
        return event

    def get_registration_forms(
        self, actor_id: int, request: EventRegistrationFormsRequest
    ) -> EventRegistrationFormsResponse:
        """Expose only active forms to registrants and archived forms to event administrators."""
        with self._session.begin():
            actor = self._active_actor(self._repository.lock_actor(actor_id))
            facts = AuthorizationFacts(
                actor_id, actor.get("user_role"), is_coordinator=bool(actor.get("is_coordinator"))
            )
            is_manager = has_permission(facts, Permission.MANAGE_EVENTS)
            event = self._repository.lock_event_for_rsvp(request.event_id)
            if event is None:
                raise EventError("event_not_found", "Event not found", 404)
            if not is_manager:
                self._rsvp_event(event, mutation=False)
            forms = self._form_views(
                request.event_id, include_inactive=request.include_inactive and is_manager
            )
        return EventRegistrationFormsResponse(
            status=200,
            message="Event registration forms retrieved successfully",
            event_id=request.event_id,
            forms=forms,
        )

    def create_registration_form(
        self, actor_id: int, request: EventRegistrationFormCreateRequest
    ) -> EventRegistrationFormMutationResponse:
        with self._session.begin():
            self._require_event_manager(actor_id, request.event_id)
            if (
                len(self._repository.lock_forms_for_event(request.event_id, active_only=False))
                >= 20
            ):
                raise EventError("event_form_limit", "An event can have at most 20 forms", 422)
            form_id = self._repository.create_form(
                {
                    "event_id": request.event_id,
                    "name": request.name,
                    "description": request.description or None,
                    "sort_order": request.sort_order,
                    "version": 1,
                    "is_active": 1,
                    "created_by": actor_id,
                    "created_at": datetime.now(UTC).replace(tzinfo=None),
                }
            )
            for index, question in enumerate(request.questions):
                self._repository.create_question(
                    self._question_values(form_id, question, question.sort_order or index)
                )
            self._record_form_version(form_id, actor_id)
            form = self._form_views(request.event_id, include_inactive=True)
            created = next(item for item in form if item.id == form_id)
        return EventRegistrationFormMutationResponse(
            status=200, message="Event registration form created successfully", form=created
        )

    def manage_registration_form(
        self, actor_id: int, request: EventRegistrationFormManageRequest
    ) -> EventRegistrationFormMutationResponse:
        """Apply a bounded, current-policy form mutation under event and form row locks."""
        with self._session.begin():
            if request.action == "reorder_forms":
                if request.event_id is None or request.forms is None:
                    raise EventError("event_form_invalid_request", "Invalid form reorder", 400)
                self._require_event_manager(actor_id, request.event_id)
                ordered_forms = self._repository.lock_forms_for_event(
                    request.event_id, active_only=False
                )
                known_ids = {int(form["id"]) for form in ordered_forms}
                requested: list[tuple[int, int]] = []
                for item in request.forms:
                    form_id, sort_order = item.form_id, item.sort_order
                    if form_id not in known_ids:
                        raise EventError("event_form_invalid_request", "Invalid form reorder", 400)
                    if sort_order < 0 or sort_order > 255:
                        raise EventError("event_form_invalid_request", "Invalid form order", 400)
                    requested.append((form_id, sort_order))
                if len({form_id for form_id, _ in requested}) != len(requested):
                    raise EventError(
                        "event_form_invalid_request", "Duplicate form reorder entry", 400
                    )
                for form_id, sort_order in requested:
                    self._repository.update_form(form_id, {"sort_order": sort_order})
                return EventRegistrationFormMutationResponse(
                    status=200, message="Event registration forms reordered"
                )

            if request.action == "upsert" and request.form_id is None:
                if request.event_id is None or request.name is None or not request.questions:
                    raise EventError("event_form_invalid_request", "Invalid form upsert", 400)
                self._require_event_manager(actor_id, request.event_id)
                if (
                    len(self._repository.lock_forms_for_event(request.event_id, active_only=False))
                    >= 20
                ):
                    raise EventError("event_form_limit", "An event can have at most 20 forms", 422)
                form_id = self._repository.create_form(
                    {
                        "event_id": request.event_id,
                        "name": request.name,
                        "description": request.description or None,
                        "sort_order": request.sort_order or 0,
                        "version": 1,
                        "is_active": 1,
                        "created_by": actor_id,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                for index, question in enumerate(request.questions):
                    self._repository.create_question(
                        self._question_values(form_id, question, question.sort_order or index)
                    )
                self._record_form_version(form_id, actor_id)
                form_views = self._form_views(request.event_id, include_inactive=True)
                created = next(item for item in form_views if item.id == form_id)
                return EventRegistrationFormMutationResponse(
                    status=200, message="Event registration form created successfully", form=created
                )

            if request.form_id is None:
                raise EventError("event_form_invalid_request", "A form identifier is required", 400)
            form = self._repository.lock_form(request.form_id)
            if form is None:
                raise EventError("event_form_not_found", "Event registration form not found", 404)
            self._require_event_manager(actor_id, int(form["event_id"]))
            if request.event_id is not None and request.event_id != int(form["event_id"]):
                raise EventError(
                    "event_form_invalid_request", "Form does not belong to the event", 400
                )

            if request.action in {"delete_form", "archive"}:
                self._repository.update_form(request.form_id, {"is_active": 0})
                updated = self._form_views(int(form["event_id"]), include_inactive=True)
                target = next(item for item in updated if item.id == request.form_id)
                return EventRegistrationFormMutationResponse(
                    status=200, message="Event registration form archived", form=target
                )

            if request.action in {"update_form", "upsert"}:
                changes: dict[str, Any] = {}
                for field in ("name", "description", "sort_order", "is_active"):
                    if field in request.model_fields_set:
                        changes[field] = getattr(request, field)
                if request.questions is not None:
                    changes["version"] = self._next_form_version(form)
                    self._repository.delete_questions_for_form(request.form_id)
                    for index, question in enumerate(request.questions):
                        self._repository.create_question(
                            self._question_values(
                                request.form_id, question, question.sort_order or index
                            )
                        )
                if not changes:
                    raise EventError("event_form_update_empty", "No form fields provided", 422)
                if "version" not in changes:
                    changes["version"] = self._next_form_version(form)
                self._repository.update_form(request.form_id, changes)

            elif request.action == "add_question":
                if request.question is None:
                    raise EventError("event_form_invalid_request", "A question is required", 400)
                current_questions = self._repository.question_rows([request.form_id])
                self._repository.create_question(
                    self._question_values(
                        request.form_id,
                        request.question,
                        request.question.sort_order
                        if request.question.sort_order is not None
                        else len(current_questions),
                    )
                )
                self._repository.update_form(
                    request.form_id, {"version": self._next_form_version(form)}
                )

            elif request.action == "update_question":
                if request.question is None or request.question_id is None:
                    raise EventError(
                        "event_form_invalid_request", "A question identifier is required", 400
                    )
                if self._repository.lock_question(request.form_id, request.question_id) is None:
                    raise EventError("event_form_question_not_found", "Question not found", 404)
                values = self._question_values(
                    request.form_id,
                    request.question,
                    request.question.sort_order if request.question.sort_order is not None else 0,
                )
                values.pop("form_id")
                values.pop("created_at")
                self._repository.update_question(request.question_id, values)
                self._repository.update_form(
                    request.form_id, {"version": self._next_form_version(form)}
                )

            elif request.action == "delete_question":
                if request.question_id is None:
                    raise EventError(
                        "event_form_invalid_request", "A question identifier is required", 400
                    )
                if self._repository.lock_question(request.form_id, request.question_id) is None:
                    raise EventError("event_form_question_not_found", "Question not found", 404)
                self._repository.delete_question(request.question_id)
                self._repository.update_form(
                    request.form_id, {"version": self._next_form_version(form)}
                )

            elif request.action == "reorder_questions":
                if request.order is None:
                    raise EventError(
                        "event_form_invalid_request", "A question order is required", 400
                    )
                current = self._repository.question_rows([request.form_id])
                current_ids = {int(question["id"]) for question in current}
                if set(request.order) != current_ids or len(request.order) != len(current_ids):
                    raise EventError(
                        "event_form_invalid_request",
                        "Order must list every form question once",
                        400,
                    )
                for position, question_id in enumerate(request.order):
                    self._repository.lock_question(request.form_id, question_id)
                    self._repository.update_question(question_id, {"sort_order": position})
                self._repository.update_form(
                    request.form_id, {"version": self._next_form_version(form)}
                )

            else:
                raise EventError("event_form_invalid_request", "Invalid form action", 400)

            self._record_form_version(request.form_id, actor_id)
            updated = self._form_views(int(form["event_id"]), include_inactive=True)
            target = next(item for item in updated if item.id == request.form_id)
        return EventRegistrationFormMutationResponse(
            status=200, message="Event registration form updated", form=target
        )

    def _validated_answers(
        self, event_id: int, answers: list[EventRegistrationAnswerInput]
    ) -> list[dict[str, Any]]:
        forms = self._repository.lock_forms_for_event(event_id, active_only=True)
        questions = self._repository.question_rows([int(form["id"]) for form in forms])
        form_map = {int(form["id"]): form for form in forms}
        question_map = {
            (int(question["form_id"]), int(question["id"])): question for question in questions
        }
        submitted: dict[tuple[int, int], str | list[str] | None] = {}
        for answer in answers:
            key = (answer.form_id, answer.question_id)
            if key in submitted or key not in question_map:
                raise EventError(
                    "event_form_answers_invalid", "Answers do not match active event forms", 400
                )
            submitted[key] = answer.answer
        stored: list[dict[str, Any]] = []
        choice_types = {"multiple_choice", "checkbox", "dropdown"}
        for key, question in question_map.items():
            raw_value = submitted.get(key)
            question_type = str(self._value(question.get("type"), "short_answer"))
            required = bool(question.get("required"))
            options = self._options(question.get("options_json")) or []
            if question_type == "checkbox":
                if raw_value is None:
                    value: list[str] = []
                elif not isinstance(raw_value, list) or not all(
                    isinstance(item, str) for item in raw_value
                ):
                    raise EventError(
                        "event_form_answers_invalid", "Checkbox answers must be an array", 400
                    )
                else:
                    value = [item.strip() for item in raw_value if item.strip()]
                if len(value) != len(set(value)) or any(item not in options for item in value):
                    raise EventError(
                        "event_form_answers_invalid",
                        "Checkbox answer contains an invalid option",
                        400,
                    )
                maximum = question.get("max_selections")
                if maximum is not None and len(value) > int(maximum):
                    raise EventError(
                        "event_form_answers_invalid",
                        "Checkbox answer has too many options",
                        400,
                    )
                meaningful = bool(value)
                answer_text, answer_json = None, json.dumps(value) if meaningful else None
            else:
                if raw_value is None:
                    text = ""
                elif not isinstance(raw_value, str):
                    raise EventError(
                        "event_form_answers_invalid", "Text answers must be strings", 400
                    )
                else:
                    text = raw_value.strip()
                if len(text) > 10_000:
                    raise EventError("event_form_answers_invalid", "Answer is too long", 400)
                if question_type in choice_types and text and text not in options:
                    raise EventError(
                        "event_form_answers_invalid", "Answer contains an invalid option", 400
                    )
                meaningful = bool(text)
                answer_text, answer_json = text or None, None
            if required and not meaningful:
                raise EventError("event_form_answers_invalid", "A required answer is missing", 400)
            if meaningful:
                form = form_map[key[0]]
                stored.append(
                    {
                        "form_id": key[0],
                        "form_version": int(form["version"]),
                        "form_name_snapshot": str(form["name"]),
                        "question_id": key[1],
                        "question_label_snapshot": str(question["label"]),
                        "question_type": question_type,
                        "question_order": int(question.get("sort_order") or 0),
                        "required_snapshot": int(required),
                        "placeholder_snapshot": question.get("placeholder"),
                        "options_json_snapshot": question.get("options_json"),
                        "max_selections_snapshot": question.get("max_selections"),
                        "answer_text": answer_text,
                        "answer_json": answer_json,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
        return stored

    def register_with_forms(
        self, actor_id: int, request: EventRegistrationWithFormsRequest
    ) -> EventRegistrationSubmissionResponse:
        """Atomically save a member-owned RSVP and validated current-form answer snapshots."""
        try:
            submitted_answers = request.normalized_answers()
        except ValueError as exc:
            raise EventError(
                "event_form_answers_invalid", "Invalid grouped form answers", 400
            ) from exc
        with self._session.begin():
            self._active_actor(self._repository.lock_actor(actor_id))
            event = self._repository.lock_event_for_rsvp(request.event_id)
            if event is None:
                raise EventError("event_not_found", "Event not found", 404)
            self._rsvp_event(event, mutation=True)
            existing = self._repository.lock_attendee(request.event_id, actor_id)
            existing_status = self._value(existing.get("status")) if existing is not None else None
            if request.rsvp_status == "going":
                self._capacity_available(event, existing_status)
            answers = (
                self._validated_answers(request.event_id, submitted_answers)
                if submitted_answers is not None
                else []
            )
            now = datetime.now(UTC).replace(tzinfo=None)
            values: dict[str, Any] = {"year": event.get("year"), "status": request.rsvp_status}
            if existing is None:
                attendee_id = self._repository.create_attendee(
                    {
                        "event_id": request.event_id,
                        "user_id": actor_id,
                        "registered_at": now,
                        "additional_info": request.additional_info,
                        **values,
                    }
                )
                row = {
                    "id": attendee_id,
                    "event_id": request.event_id,
                    "user_id": actor_id,
                    "registered_at": now,
                    "additional_info": request.additional_info,
                    **values,
                }
            else:
                attendee_id = int(existing["id"])
                if "additional_info" in request.model_fields_set:
                    values["additional_info"] = request.additional_info
                self._repository.update_attendee(attendee_id, values)
                row = {**existing, **values}
            if submitted_answers is not None:
                self._repository.delete_answers_for_attendee(attendee_id)
                for answer in answers:
                    self._repository.create_answer({"attendee_id": attendee_id, **answer})
        return EventRegistrationSubmissionResponse(
            status=200,
            message="Event registration submitted successfully",
            rsvp=self._rsvp_value(row),
            answers_saved=len(answers),
        )

    def registration_submissions(
        self, actor_id: int, request: EventRegistrationSubmissionFilters
    ) -> EventRegistrationSubmissionsResponse:
        with self._session.begin():
            self._require_event_manager(actor_id, request.event_id)
            filters = request.model_dump()
            filters["offset"] = (request.page - 1) * request.per_page
            rows, total = (
                self._repository.submission_rows(filters),
                self._repository.submission_total(filters),
            )
        return EventRegistrationSubmissionsResponse(
            status=200,
            message="Event registrations retrieved successfully",
            event_id=request.event_id,
            total=total,
            page=request.page,
            per_page=request.per_page,
            registrations=[
                EventRegistrationSubmissionListItem(
                    attendee_id=int(row["attendee_id"]),
                    user_id=int(row["user_id"]),
                    fullname=row.get("fullname"),
                    email=row.get("email"),
                    phone=row.get("phone"),
                    avatar=row.get("avatar"),
                    rsvp_status=str(self._value(row.get("rsvp_status"), "going")),
                    additional_info=row.get("additional_info"),
                    registered_at=row.get("registered_at"),
                    has_form_answers=bool(row.get("answer_count")),
                    answer_count=int(row.get("answer_count") or 0),
                )
                for row in rows
            ],
        )

    def registration_submission_detail(
        self, actor_id: int, request: EventRegistrationSubmissionDetailRequest
    ) -> EventRegistrationSubmissionDetailResponse:
        with self._session.begin():
            self._require_event_manager(actor_id, request.event_id)
            registration = self._repository.submission_detail(
                request.event_id, request.attendee_id, request.user_id
            )
            if registration is None:
                raise EventError("event_registration_not_found", "Registration not found", 404)
            forms: dict[tuple[int, int], EventRegistrationSubmissionFormView] = {}
            for answer in self._repository.answer_rows(int(registration["attendee_id"])):
                key = (int(answer["form_id"]), int(answer["form_version"]))
                if key not in forms:
                    forms[key] = EventRegistrationSubmissionFormView(
                        form_id=key[0],
                        form_name=str(answer.get("form_name") or "Archived form"),
                        form_version=key[1],
                        answers=[],
                    )
                value: str | list[str] | None = answer.get("answer_text")
                if answer.get("answer_json") is not None:
                    parsed = self._options(answer.get("answer_json"))
                    value = parsed if parsed is not None else None
                forms[key].answers.append(
                    EventRegistrationAnswerView(
                        question_id=int(answer["question_id"]),
                        label=str(answer["question_label_snapshot"]),
                        type=str(answer["question_type"]),
                        required=bool(answer["required_snapshot"]),
                        sort_order=int(answer["question_order"]),
                        placeholder=answer.get("placeholder_snapshot"),
                        options=self._options(answer.get("options_json_snapshot")),
                        max_selections=(
                            int(answer["max_selections_snapshot"])
                            if answer.get("max_selections_snapshot") is not None
                            else None
                        ),
                        answer=value,
                    )
                )
            detail = EventRegistrationSubmissionDetail(
                attendee_id=int(registration["attendee_id"]),
                event_id=int(registration["event_id"]),
                user_id=int(registration["user_id"]),
                fullname=registration.get("fullname"),
                email=registration.get("email"),
                phone=registration.get("phone"),
                avatar=registration.get("avatar"),
                rsvp_status=str(self._value(registration.get("rsvp_status"), "going")),
                additional_info=registration.get("additional_info"),
                registered_at=registration.get("registered_at"),
                forms=list(forms.values()),
            )
        return EventRegistrationSubmissionDetailResponse(
            status=200, message="Event registration retrieved successfully", registration=detail
        )

    def get(self, request: EventFilters) -> EventListResponse | EventDetailResponse:
        with self._session.begin():
            if request.id is not None:
                row = self._repository.public_event(request.id)
                if row is None:
                    raise EventError("event_not_found", "Event not found", 404)
                return EventDetailResponse(
                    status=200, message="Event retrieved successfully", event=self._item(row)
                )
            filters = request.model_dump(exclude={"id"})
            rows, total = self._repository.list_rows(filters), self._repository.count(filters)
        return EventListResponse(
            status=200,
            message="Events retrieved successfully",
            events=[self._item(row) for row in rows],
            total=total,
            limit=request.limit,
            offset=request.offset,
        )

    def create(
        self, actor_id: int, request: EventCreateRequest, upload: PreparedAvatar | None
    ) -> EventMutationResponse:
        stored: StoredAvatar | None = None
        try:
            with self._session.begin():
                actor = self._actor(self._repository.lock_actor(actor_id), actor_id)
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Event storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                event_id = self._repository.create(
                    {
                        **request.model_dump(),
                        "chapter_id": self._chapter(request.chapter_id, actor),
                        "created_by": actor_id,
                        "is_approved": 1,
                        "event_banner": stored.relative_path if stored else "",
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                event = self._response(event_id)
        except Exception:
            if stored is not None:
                self._delete_stored(stored.relative_path)
            raise
        return EventMutationResponse(status=200, message="Event created successfully", event=event)

    def update(
        self, actor_id: int, request: EventUpdateRequest, upload: PreparedAvatar | None
    ) -> EventMutationResponse:
        stored: StoredAvatar | None = None
        stale: str | None = None
        try:
            with self._session.begin():
                self._actor(self._repository.lock_actor(actor_id), actor_id)
                current = self._repository.lock_event(request.id)
                if current is None:
                    raise EventError("event_not_found", "Event not found", 404)
                changes = request.model_dump(
                    exclude={"function_type", "id", "remove_banner"}, exclude_unset=True
                )
                if changes.get("chapter_id") is not None:
                    self._chapter(int(changes["chapter_id"]), {})
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Event storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                    changes["event_banner"], stale = (
                        stored.relative_path,
                        current.get("event_banner"),
                    )
                elif request.remove_banner:
                    changes["event_banner"], stale = "", current.get("event_banner")
                if not changes:
                    raise EventError("event_update_empty", "No fields provided to update", 422)
                changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                self._repository.update(request.id, changes)
                event = self._response(request.id)
        except Exception:
            if stored is not None:
                self._delete_stored(stored.relative_path)
            raise
        self._delete_stored(stale)
        return EventMutationResponse(status=200, message="Event updated successfully", event=event)

    def delete(self, actor_id: int, request: EventDeleteRequest) -> EventMutationResponse:
        stale: str | None = None
        with self._session.begin():
            self._actor(self._repository.lock_actor(actor_id), actor_id)
            current = self._repository.lock_event(request.id)
            if current is None:
                raise EventError("event_not_found", "Event not found", 404)
            stale = current.get("event_banner")
            self._repository.delete(request.id)
        self._delete_stored(stale)
        return EventMutationResponse(status=200, message="Event deleted successfully")

    def register(self, actor_id: int, request: EventRegistrationRequest) -> EventRsvpResponse:
        """Create or update the authenticated member's own RSVP under an event lock."""
        with self._session.begin():
            self._active_actor(self._repository.lock_actor(actor_id))
            event = self._repository.lock_event_for_rsvp(request.event_id)
            if event is None:
                raise EventError("event_not_found", "Event not found", 404)
            self._rsvp_event(event, mutation=True)
            existing = self._repository.lock_attendee(request.event_id, actor_id)
            existing_status = self._value(existing.get("status")) if existing is not None else None
            if request.status == "going":
                self._capacity_available(event, existing_status)
            now = datetime.now(UTC).replace(tzinfo=None)
            values = {
                "year": event.get("year"),
                "status": request.status,
            }
            if existing is None:
                attendee_id = self._repository.create_attendee(
                    {
                        "event_id": request.event_id,
                        "user_id": actor_id,
                        "registered_at": now,
                        "additional_info": request.additional_info,
                        **values,
                    }
                )
                row = {
                    "id": attendee_id,
                    "event_id": request.event_id,
                    "user_id": actor_id,
                    "registered_at": now,
                    "additional_info": request.additional_info,
                    **values,
                }
                message = "Successfully registered for event"
            else:
                if "additional_info" in request.model_fields_set:
                    values["additional_info"] = request.additional_info
                self._repository.update_attendee(int(existing["id"]), values)
                row = {**existing, **values}
                message = f'RSVP updated to "{request.status}"'
        return EventRsvpResponse(status=200, message=message, rsvp=self._rsvp_value(row))

    def manage_rsvp(self, actor_id: int, request: EventRsvpManageRequest) -> EventRsvpResponse:
        """Cancel or change only the authenticated member's existing RSVP."""
        with self._session.begin():
            self._active_actor(self._repository.lock_actor(actor_id))
            event = self._repository.lock_event_for_rsvp(request.event_id)
            if event is None:
                raise EventError("event_not_found", "Event not found", 404)
            existing = self._repository.lock_attendee(request.event_id, actor_id)
            if existing is None:
                raise EventError("event_rsvp_not_found", "RSVP not found", 404)
            if request.function_type == "cancel":
                values: dict[str, Any] = {"status": "not_going"}
                message = "RSVP cancelled successfully"
            else:
                self._rsvp_event(event, mutation=True)
                if request.status is None:
                    raise EventError(
                        "event_rsvp_invalid_request", "An RSVP status is required", 400
                    )
                if request.status == "going":
                    self._capacity_available(event, self._value(existing.get("status")))
                values = {"status": request.status}
                if "additional_info" in request.model_fields_set:
                    values["additional_info"] = request.additional_info
                message = f'RSVP updated to "{request.status}"'
            self._repository.update_attendee(int(existing["id"]), values)
            row = {**existing, **values}
        return EventRsvpResponse(status=200, message=message, rsvp=self._rsvp_value(row))

    def attendees(self, actor_id: int, request: EventAttendeeFilters) -> EventAttendeeListResponse:
        """Return attendee contact details only to a current event administrator."""
        with self._session.begin():
            self._actor(self._repository.lock_actor(actor_id), actor_id)
            event = self._repository.lock_event_for_rsvp(request.event_id)
            if event is None:
                raise EventError("event_not_found", "Event not found", 404)
            filters = request.model_dump()
            rows = self._repository.attendee_rows(filters)
            total = self._repository.attendee_total(filters)
            summary = self._repository.attendee_summary(request.event_id)
        return EventAttendeeListResponse(
            status=200,
            message="Attendees retrieved successfully",
            event=EventAttendeeEvent(
                id=int(event["id"]),
                title=str(event["title"]),
                start_date=event.get("start_date"),
                event_date=event.get("event_date"),
                year=event.get("year"),
            ),
            summary=EventAttendeeSummary(**summary),
            attendees=[
                EventAttendee(
                    attendee_id=int(row["attendee_id"]),
                    user_id=int(row["user_id"]),
                    fullname=row.get("fullname"),
                    email=row.get("email"),
                    phone=row.get("phone"),
                    avatar=row.get("avatar"),
                    status=str(self._value(row.get("status"), "going")),
                    year=row.get("year"),
                    additional_info=row.get("additional_info"),
                    registered_at=row.get("registered_at"),
                )
                for row in rows
            ],
            total=total,
            limit=request.limit,
            offset=request.offset,
        )
