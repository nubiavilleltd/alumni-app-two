"""Goal 7 event-form/RSVP guard and branch coverage.

The happy paths live in ``tests/test_auth_integration.py``; this module drives the
validation, authorization, not-found, capacity, and defensive service-boundary
branches of ``app/services/events.py``. The answer-validation and static-helper tests
are pure (no database); the remaining tests reuse the shared auth harness.
"""

from __future__ import annotations

import json
from collections.abc import Iterator
from datetime import date
from pathlib import Path
from typing import cast

import pytest
from sqlalchemy import Table, func, select

from app.models.generated import EventAttendees, Events
from app.schemas.events import (
    EventRegistrationAnswerGroup,
    EventRegistrationAnswerInput,
    EventRegistrationWithFormsRequest,
)
from app.services.events import EventError, EventService
from tests.test_auth_integration import AuthHarness, _access_token_for
from tests.test_auth_integration import auth_harness as _shared_auth_harness

EVENTS_TABLE: Table = cast(Table, Events.__table__)
ATTENDEES_TABLE: Table = cast(Table, EventAttendees.__table__)


def _event(
    harness: AuthHarness,
    admin_id: int,
    *,
    visibility: str = "public",
    is_approved: int = 1,
    status: str = "upcoming",
    max_attendees: int = 0,
) -> int:
    with harness.engine.begin() as connection:
        key = connection.execute(
            EVENTS_TABLE.insert().values(
                title="Synthetic Edge Event",
                event_banner="",
                status=status,
                created_by=admin_id,
                start_date=date(2027, 1, 12),
                visibility=visibility,
                is_approved=is_approved,
                max_attendees=max_attendees,
            )
        ).inserted_primary_key
        assert key is not None
        return int(key[0])


def _bearer(
    harness: AuthHarness, user_id: int, email: str, *, role: str = "alumni"
) -> dict[str, str]:
    return {"Authorization": f"Bearer {_access_token_for(harness, user_id, email, user_role=role)}"}


def _form(harness: AuthHarness, admin_headers: dict[str, str], event_id: int) -> tuple[int, int]:
    created = harness.client.post(
        "/api/create_event_registration_form",
        headers=admin_headers,
        json={
            "event_id": event_id,
            "name": "Edge form",
            "questions": [
                {
                    "label": "Meal",
                    "type": "dropdown",
                    "required": True,
                    "options": ["Rice", "Pasta"],
                },
                {"label": "Note", "type": "long_answer"},
            ],
        },
    )
    assert created.status_code == 200
    form = created.json()["form"]
    return int(form["id"]), int(form["questions"][0]["id"])


@pytest.fixture(name="auth_harness")
def _auth_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[AuthHarness]:
    yield from _shared_auth_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


class _FakeEventRepository:
    def __init__(self, forms: list[dict[str, object]], questions: list[dict[str, object]]) -> None:
        self._forms = forms
        self._questions = questions

    def lock_forms_for_event(self, _event_id: int, *, active_only: bool) -> list[dict[str, object]]:
        return self._forms

    def question_rows(self, form_ids: list[int]) -> list[dict[str, object]]:
        return [question for question in self._questions if question["form_id"] in form_ids]


def _answer_service(
    forms: list[dict[str, object]], questions: list[dict[str, object]]
) -> EventService:
    service = object.__new__(EventService)
    service._repository = _FakeEventRepository(forms, questions)  # type: ignore[assignment]
    return service


def _checkbox_question(
    *,
    form_id: int = 1,
    required: int = 0,
    options: list[str] | None = None,
    max_selections: int | None = None,
) -> dict[str, object]:
    return {
        "form_id": form_id,
        "id": 1,
        "label": "Pick",
        "type": "checkbox",
        "required": required,
        "placeholder": None,
        "options_json": json.dumps(options or ["a", "b"]),
        "max_selections": max_selections,
        "sort_order": 0,
    }


def _text_question(
    *,
    question_id: int,
    question_type: str = "short_answer",
    required: int = 0,
    options: list[str] | None = None,
    form_id: int = 1,
) -> dict[str, object]:
    return {
        "form_id": form_id,
        "id": question_id,
        "label": "Field",
        "type": question_type,
        "required": required,
        "placeholder": None,
        "options_json": json.dumps(options) if options else None,
        "max_selections": None,
        "sort_order": 0,
    }


_FORM = {"id": 1, "version": 2, "name": "Food"}


def _answers(service: EventService, event_id: int, answers: list[tuple[int, int, object]]) -> None:
    service._validated_answers(
        event_id,
        [
            EventRegistrationAnswerInput.model_construct(
                form_id=form_id,
                question_id=question_id,
                answer=answer,  # type: ignore[arg-type]
            )
            for form_id, question_id, answer in answers
        ],
    )


def test_options_rejects_invalid_json_and_non_string_lists() -> None:
    assert EventService._options("not json") is None
    assert EventService._options('["a", 1]') is None
    assert EventService._options('"a"') is None
    assert EventService._options("") is None
    assert EventService._options('["a", "b"]') == ["a", "b"]


def test_rsvp_event_requires_approved_public_upcoming_or_active() -> None:
    base = {"is_approved": 1, "visibility": "public", "status": "upcoming"}
    EventService._rsvp_event(base, mutation=True)
    for overrides in (
        {"is_approved": 0},
        {"visibility": "members"},
        {"status": "draft"},
        {"status": "completed"},
    ):
        with pytest.raises(EventError) as excinfo:
            EventService._rsvp_event({**base, **overrides}, mutation=True)
        assert excinfo.value.http_status == 404


def test_next_form_version_bounds() -> None:
    assert EventService._next_form_version({"version": 3}) == 4
    assert EventService._next_form_version({"version": None}) == 2
    with pytest.raises(EventError, match="version limit"):
        EventService._next_form_version({"version": 65_535})


def test_item_normalizes_banner_and_counts() -> None:
    item = EventService._item(
        {
            "id": 1,
            "title": "Synthetic",
            "event_banner": "../../etc/passwd",
            "attendee_count": "3",
            "registration_form_count": 1,
        }
    )
    assert item.event_banner is None
    assert item.attendee_count == 3
    assert item.has_registration_questions is True
    kept = EventService._item(
        {"id": 1, "title": "Synthetic", "event_banner": "uploads/events/a.png"}
    )
    assert kept.event_banner == "uploads/events/a.png"
    absolute = EventService._item(
        {"id": 1, "title": "Synthetic", "event_banner": "https://cdn.example/x.png"}
    )
    assert absolute.event_banner == "https://cdn.example/x.png"


def test_answer_validation_rejects_duplicate_and_unknown_questions() -> None:
    service = _answer_service([_FORM], [_checkbox_question()])
    with pytest.raises(EventError, match="Answers do not match"):
        _answers(service, 1, [(1, 1, ["a"]), (1, 1, ["b"])])
    with pytest.raises(EventError, match="Answers do not match"):
        _answers(service, 1, [(1, 999, ["a"])])


def test_checkbox_answers_must_be_unique_known_options() -> None:
    service = _answer_service([_FORM], [_checkbox_question(max_selections=2, options=["a", "b"])])
    for bad in (["a", "a"], ["c"], ["a", "b", "c"]):
        with pytest.raises(EventError, match="invalid option"):
            _answers(service, 1, [(1, 1, bad)])


def test_checkbox_answers_must_be_arrays_within_max_selections() -> None:
    service = _answer_service([_FORM], [_checkbox_question(max_selections=1)])
    with pytest.raises(EventError, match="must be an array"):
        _answers(service, 1, [(1, 1, "a")])
    with pytest.raises(EventError, match="must be an array"):
        _answers(service, 1, [(1, 1, ["a", 1])])
    with pytest.raises(EventError, match="too many options"):
        _answers(service, 1, [(1, 1, ["a", "b"])])


def test_text_answers_must_be_strings_within_length() -> None:
    service = _answer_service([_FORM], [_text_question(question_id=2)])
    with pytest.raises(EventError, match="must be strings"):
        _answers(service, 1, [(1, 2, ["a"])])
    with pytest.raises(EventError, match="too long"):
        _answers(service, 1, [(1, 2, "x" * 10_001)])


def test_choice_answers_must_match_options_and_required_is_enforced() -> None:
    service = _answer_service(
        [_FORM],
        [
            _text_question(question_id=2, question_type="dropdown", required=1, options=["x", "y"]),
            _text_question(question_id=3, question_type="short_answer", required=1),
        ],
    )
    with pytest.raises(EventError, match="invalid option"):
        _answers(service, 1, [(1, 2, "z")])
    with pytest.raises(EventError, match="required answer is missing"):
        _answers(service, 1, [(1, 2, "x")])
    with pytest.raises(EventError, match="required answer is missing"):
        _answers(service, 1, [(1, 2, "x"), (1, 3, "")])


def test_valid_answers_produce_snapshots_and_skip_blank_optional_fields() -> None:
    service = _answer_service(
        [_FORM],
        [
            _checkbox_question(options=["a", "b"]),
            _text_question(question_id=2, question_type="dropdown", required=1, options=["x", "y"]),
            _text_question(question_id=3, question_type="short_answer", required=0),
        ],
    )
    stored = service._validated_answers(
        1,
        [
            EventRegistrationAnswerInput(form_id=1, question_id=1, answer=["a"]),
            EventRegistrationAnswerInput(form_id=1, question_id=2, answer="x"),
            EventRegistrationAnswerInput(form_id=1, question_id=3, answer=""),
        ],
    )
    assert len(stored) == 2
    checkbox = next(item for item in stored if item["question_id"] == 1)
    assert checkbox["form_version"] == 2
    assert checkbox["form_name_snapshot"] == "Food"
    assert json.loads(checkbox["answer_json"]) == ["a"]
    assert next(item for item in stored if item["question_id"] == 2)["answer_text"] == "x"


def test_optional_checkbox_left_blank_is_skipped() -> None:
    service = _answer_service([_FORM], [_checkbox_question(required=0)])
    stored = service._validated_answers(1, [])
    assert stored == []


def test_register_with_forms_rejects_malformed_grouped_answers_without_database() -> None:
    service = _answer_service([_FORM], [_checkbox_question()])
    group = EventRegistrationAnswerGroup.model_construct(
        form_id=1,
        answers=[
            EventRegistrationAnswerInput.model_construct(form_id=2, question_id=1, answer="x")
        ],
    )
    request = EventRegistrationWithFormsRequest.model_construct(
        event_id=1, rsvp_status="going", form_answers=[group]
    )
    with pytest.raises(EventError, match="Invalid grouped form answers"):
        service.register_with_forms(1, request)


@pytest.mark.integration
def test_rsvp_capacity_and_cancel_guards(auth_harness: AuthHarness) -> None:
    """Capacity rejects an over-booked going RSVP, and cancel is self-owned."""
    admin_id, _admin_email, _ = auth_harness.create_user(user_role="event admin")
    first_id, first_email, _ = auth_harness.create_user()
    second_id, second_email, _ = auth_harness.create_user()
    event_id = _event(auth_harness, admin_id, max_attendees=1)
    first_headers = _bearer(auth_harness, first_id, first_email)
    second_headers = _bearer(auth_harness, second_id, second_email)

    going = auth_harness.client.post(
        "/api/register_event", headers=first_headers, json={"event_id": event_id, "status": "going"}
    )
    assert going.status_code == 200
    overbooked = auth_harness.client.post(
        "/api/register_event",
        headers=second_headers,
        json={"event_id": event_id, "status": "going"},
    )
    assert overbooked.status_code == 409
    assert overbooked.json()["code"] == "event_full"

    cancelled = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=first_headers,
        json={"event_id": event_id, "function_type": "cancel"},
    )
    assert cancelled.status_code == 200
    assert cancelled.json()["rsvp"]["status"] == "not_going"

    missing = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=second_headers,
        json={"event_id": event_id, "function_type": "cancel"},
    )
    assert missing.status_code == 404
    assert missing.json()["code"] == "event_rsvp_not_found"


@pytest.mark.integration
def test_registration_and_forms_require_an_approved_public_event(auth_harness: AuthHarness) -> None:
    """Non-registrable events reject both RSVP and form reads for ordinary members."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    member_id, member_email, _ = auth_harness.create_user()
    member_headers = _bearer(auth_harness, member_id, member_email)
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    event_id = _event(auth_harness, admin_id, is_approved=0)
    _form(auth_harness, admin_headers, event_id)

    denied_rsvp = auth_harness.client.post(
        "/api/register_event",
        headers=member_headers,
        json={"event_id": event_id, "status": "going"},
    )
    assert denied_rsvp.status_code == 404
    denied_forms = auth_harness.client.post(
        "/api/get_event_registration_forms", headers=member_headers, json={"eventId": event_id}
    )
    assert denied_forms.status_code == 404


@pytest.mark.integration
def test_form_management_error_branches(auth_harness: AuthHarness) -> None:
    """Reorder, empty-update, and unknown-question branches fail with the right codes."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    event_id = _event(auth_harness, admin_id)
    form_id, question_id = _form(auth_harness, admin_headers, event_id)

    unknown_reorder = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "reorder_forms",
            "event_id": event_id,
            "forms": [{"formId": 999_999, "sortOrder": 0}],
        },
    )
    assert unknown_reorder.status_code == 400

    duplicate_reorder = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "reorder_forms",
            "event_id": event_id,
            "forms": [
                {"formId": form_id, "sortOrder": 0},
                {"formId": form_id, "sortOrder": 1},
            ],
        },
    )
    assert duplicate_reorder.status_code == 400

    empty_update = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "update_form", "form_id": form_id},
    )
    assert empty_update.status_code == 422
    assert empty_update.json()["code"] == "event_form_update_empty"

    wrong_event = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "update_form", "form_id": form_id, "event_id": 999_999, "name": "X"},
    )
    assert wrong_event.status_code == 400

    unknown_question = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "update_question",
            "form_id": form_id,
            "question_id": 999_999,
            "question": {"label": "Z", "type": "long_answer"},
        },
    )
    assert unknown_question.status_code == 404

    missing_question_delete = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "delete_question", "form_id": form_id, "question_id": 999_999},
    )
    assert missing_question_delete.status_code == 404

    valid_delete = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "delete_question", "form_id": form_id, "question_id": question_id},
    )
    assert valid_delete.status_code == 200


@pytest.mark.integration
def test_submission_detail_and_attendee_not_found_paths(auth_harness: AuthHarness) -> None:
    """Missing registrations and unknown events return 404 rather than leaking data."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    event_id = _event(auth_harness, admin_id)

    missing_detail = auth_harness.client.post(
        "/api/get_event_registration_submission_detail",
        headers=admin_headers,
        json={"event_id": event_id, "attendee_id": 999_999},
    )
    assert missing_detail.status_code == 404

    unknown_event_attendees = auth_harness.client.post(
        "/api/get_event_attendees", headers=admin_headers, json={"event_id": 999_999}
    )
    assert unknown_event_attendees.status_code == 404

    unknown_event_rsvp = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=admin_headers,
        json={"event_id": 999_999, "function_type": "cancel"},
    )
    assert unknown_event_rsvp.status_code == 404


@pytest.mark.integration
def test_inactive_actors_are_rejected_on_reads_and_writes(auth_harness: AuthHarness) -> None:
    """Deactivated accounts fail closed for both member RSVPs and manager writes."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin", active=0)
    member_id, member_email, _ = auth_harness.create_user(active=0)
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    member_headers = _bearer(auth_harness, member_id, member_email)

    member_rsvp = auth_harness.client.post(
        "/api/register_event", headers=member_headers, json={"event_id": 1, "status": "going"}
    )
    assert member_rsvp.status_code == 401
    assert member_rsvp.json()["code"] == "event_actor_unavailable"

    manager_write = auth_harness.client.post(
        "/api/create_event",
        headers=admin_headers,
        json={"title": "Inactive", "start_date": "2027-01-10"},
    )
    assert manager_write.status_code == 401
    assert manager_write.json()["code"] == "event_actor_unavailable"


@pytest.mark.integration
def test_public_event_list_and_rsvp_upsert(auth_harness: AuthHarness) -> None:
    """The public list shape works, and a repeat RSVP updates rather than duplicating."""
    admin_id, _admin_email, _ = auth_harness.create_user(user_role="event admin")
    member_id, member_email, _ = auth_harness.create_user()
    member_headers = _bearer(auth_harness, member_id, member_email)
    event_id = _event(auth_harness, admin_id)

    listing = auth_harness.client.post("/api/get_events", json={})
    assert listing.status_code == 200
    assert isinstance(listing.json()["events"], list)
    assert listing.json()["total"] >= 1

    first = auth_harness.client.post(
        "/api/register_event",
        headers=member_headers,
        json={"event_id": event_id, "status": "going"},
    )
    assert first.status_code == 200
    second = auth_harness.client.post(
        "/api/register_event",
        headers=member_headers,
        json={"event_id": event_id, "status": "maybe"},
    )
    assert second.status_code == 200
    assert second.json()["rsvp"]["status"] == "maybe"
    with auth_harness.engine.connect() as connection:
        total = connection.scalar(
            select(func.count()).where(
                ATTENDEES_TABLE.c.event_id == event_id, ATTENDEES_TABLE.c.user_id == member_id
            )
        )
        assert total == 1


@pytest.mark.integration
def test_chapter_validation_and_question_reorder_mismatch(auth_harness: AuthHarness) -> None:
    """Invalid chapters and incomplete question reorders are rejected."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    event_id = _event(auth_harness, admin_id)
    form_id, question_id = _form(auth_harness, admin_headers, event_id)

    bad_chapter = auth_harness.client.post(
        "/api/create_event",
        headers=admin_headers,
        json={"title": "X", "start_date": "2027-01-10", "chapter_id": 999_999},
    )
    assert bad_chapter.status_code == 400
    assert bad_chapter.json()["code"] == "event_chapter_invalid"

    partial_reorder = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "reorder_questions", "form_id": form_id, "order": [question_id]},
    )
    assert partial_reorder.status_code == 400


@pytest.mark.integration
def test_update_form_with_questions_and_manage_rsvp_capacity(auth_harness: AuthHarness) -> None:
    """Replacing a form's questions bumps its version, and manage-RSVP respects capacity."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    first_id, first_email, _ = auth_harness.create_user()
    second_id, second_email, _ = auth_harness.create_user()
    admin_headers = _bearer(auth_harness, admin_id, admin_email, role="event admin")
    first_headers = _bearer(auth_harness, first_id, first_email)
    second_headers = _bearer(auth_harness, second_id, second_email)
    event_id = _event(auth_harness, admin_id, max_attendees=1)
    form_id, _ = _form(auth_harness, admin_headers, event_id)

    replaced = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "update_form",
            "form_id": form_id,
            "questions": [{"label": "Diet", "type": "short_answer"}],
        },
    )
    assert replaced.status_code == 200
    assert replaced.json()["form"]["version"] == 2

    assert (
        auth_harness.client.post(
            "/api/register_event",
            headers=first_headers,
            json={"event_id": event_id, "status": "going"},
        ).status_code
        == 200
    )
    assert (
        auth_harness.client.post(
            "/api/register_event",
            headers=second_headers,
            json={"event_id": event_id, "status": "maybe"},
        ).status_code
        == 200
    )
    overbooked = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=second_headers,
        json={"event_id": event_id, "function_type": "update", "status": "going"},
    )
    assert overbooked.status_code == 409
    assert overbooked.json()["code"] == "event_full"
