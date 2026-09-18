"""Validate a sanitized Firebase event-survey export before an approved import.

This utility never connects to Firebase or a database, and it does not write any
records.  It accepts only a deliberately sanitized, normalized export plus explicit
source-to-target ID maps, so an eventual import cannot silently infer production IDs.
"""

from __future__ import annotations

import argparse
import json
from collections.abc import Mapping, Sequence
from dataclasses import dataclass
from pathlib import Path
from typing import Any

MAX_EXPORT_BYTES = 10 * 1024 * 1024
MAX_MAPPING_BYTES = 1024 * 1024
MAX_SURVEYS = 1_000
MAX_FORMS_PER_SURVEY = 100
MAX_REGISTRATIONS_PER_SURVEY = 10_000
MAX_QUESTIONS_PER_VERSION = 100
EXPORT_FORMAT = "alumni-event-survey-export"
EXPORT_VERSION = 1


class EventSurveyExportError(Exception):
    """A sanitized event-survey export or explicit map is invalid."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class EventSurveyExportSummary:
    """Non-sensitive counts proving that an export is internally importable."""

    surveys: int
    forms: int
    versions: int
    registrations: int
    answers: int

    def as_dict(self) -> dict[str, int]:
        """Return only aggregate, non-identifying validation results."""
        return {
            "surveys": self.surveys,
            "forms": self.forms,
            "versions": self.versions,
            "registrations": self.registrations,
            "answers": self.answers,
        }


def validate_export_files(export_path: Path, mapping_path: Path) -> EventSurveyExportSummary:
    """Validate a bounded sanitized export and explicit event/user target maps."""
    export = _load_json_object(export_path, MAX_EXPORT_BYTES, "event_survey_export")
    mapping = _load_json_object(mapping_path, MAX_MAPPING_BYTES, "event_survey_mapping")
    event_ids, user_ids = _validate_mapping(mapping)
    return _validate_export(export, event_ids, user_ids)


def _load_json_object(path: Path, maximum_bytes: int, code_prefix: str) -> dict[str, Any]:
    if not path.is_file():
        raise EventSurveyExportError(
            f"{code_prefix}_file_required", "A regular JSON file is required"
        )
    size = path.stat().st_size
    if size <= 0:
        raise EventSurveyExportError(f"{code_prefix}_empty", "The JSON file must not be empty")
    if size > maximum_bytes:
        raise EventSurveyExportError(
            f"{code_prefix}_too_large", f"The JSON file must not exceed {maximum_bytes} bytes"
        )
    try:
        parsed = json.loads(
            path.read_text(encoding="utf-8"),
            parse_constant=lambda value: (_ for _ in ()).throw(ValueError(value)),
        )
    except (OSError, UnicodeDecodeError, ValueError, json.JSONDecodeError) as exc:
        raise EventSurveyExportError(
            f"{code_prefix}_invalid_json", "The JSON file is not a valid UTF-8 JSON object"
        ) from exc
    if not isinstance(parsed, dict):
        raise EventSurveyExportError(
            f"{code_prefix}_invalid_json", "The JSON file must contain an object"
        )
    return parsed


def _validate_mapping(mapping: Mapping[str, Any]) -> tuple[dict[str, int], dict[str, int]]:
    if set(mapping) != {"event_ids", "user_ids"}:
        raise EventSurveyExportError(
            "event_survey_mapping_invalid", "Mappings must contain only event_ids and user_ids"
        )
    return (
        _validate_id_map(mapping["event_ids"], "event"),
        _validate_id_map(mapping["user_ids"], "user"),
    )


def _validate_id_map(value: Any, kind: str) -> dict[str, int]:
    if not isinstance(value, dict):
        raise EventSurveyExportError(
            "event_survey_mapping_invalid", f"{kind}_ids must be an object"
        )
    result: dict[str, int] = {}
    for source_id, target_id in value.items():
        source = _source_id(source_id, f"{kind} mapping key")
        if isinstance(target_id, bool) or not isinstance(target_id, int) or target_id <= 0:
            raise EventSurveyExportError(
                "event_survey_mapping_invalid", f"{kind} mapping values must be positive integers"
            )
        result[source] = target_id
    return result


def _validate_export(
    export: Mapping[str, Any], event_ids: Mapping[str, int], user_ids: Mapping[str, int]
) -> EventSurveyExportSummary:
    if set(export) != {"format", "version", "surveys"}:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Export must contain only format, version, and surveys"
        )
    if export["format"] != EXPORT_FORMAT or export["version"] != EXPORT_VERSION:
        raise EventSurveyExportError(
            "event_survey_export_unsupported", "Export format or version is unsupported"
        )
    surveys = export["surveys"]
    if not isinstance(surveys, list) or not surveys or len(surveys) > MAX_SURVEYS:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Export must contain a bounded non-empty surveys array"
        )

    total_forms = total_versions = total_registrations = total_answers = 0
    seen_events: set[str] = set()
    for survey in surveys:
        forms, versions, registrations, answers = _validate_survey(survey, event_ids, user_ids)
        event_id = _source_id(cast_mapping(survey, "survey")["eventId"], "survey eventId")
        if event_id in seen_events:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "An event may appear only once in the export"
            )
        seen_events.add(event_id)
        total_forms += forms
        total_versions += versions
        total_registrations += registrations
        total_answers += answers
    return EventSurveyExportSummary(
        surveys=len(surveys),
        forms=total_forms,
        versions=total_versions,
        registrations=total_registrations,
        answers=total_answers,
    )


def _validate_survey(
    raw_survey: Any, event_ids: Mapping[str, int], user_ids: Mapping[str, int]
) -> tuple[int, int, int, int]:
    survey = cast_mapping(raw_survey, "survey")
    if set(survey) != {"eventId", "forms", "registrations"}:
        raise EventSurveyExportError(
            "event_survey_export_invalid",
            "Each survey must contain eventId, forms, and registrations",
        )
    event_id = _source_id(survey["eventId"], "survey eventId")
    if event_id not in event_ids:
        raise EventSurveyExportError(
            "event_survey_event_unmapped", "Every source event must have an explicit target mapping"
        )
    forms = survey["forms"]
    registrations = survey["registrations"]
    if not isinstance(forms, list) or len(forms) > MAX_FORMS_PER_SURVEY:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Survey forms exceed the allowed bound"
        )
    if not isinstance(registrations, list) or len(registrations) > MAX_REGISTRATIONS_PER_SURVEY:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Survey registrations exceed the allowed bound"
        )
    version_questions: dict[tuple[str, str], set[str]] = {}
    form_ids: set[str] = set()
    versions = 0
    for raw_form in forms:
        form_id, form_versions = _validate_form(raw_form)
        if form_id in form_ids:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "Form IDs must be unique per event"
            )
        form_ids.add(form_id)
        versions += len(form_versions)
        version_questions.update(
            {
                (form_id, version_id): question_ids
                for version_id, question_ids in form_versions.items()
            }
        )
    answers = _validate_registrations(registrations, user_ids, version_questions)
    return len(forms), versions, len(registrations), answers


def _validate_form(raw_form: Any) -> tuple[str, dict[str, set[str]]]:
    form = cast_mapping(raw_form, "form")
    required = {
        "id",
        "name",
        "sortOrder",
        "isActive",
        "activeVersionId",
        "activeVersionNumber",
        "activeSnapshot",
        "versions",
    }
    if set(form) != required:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Form contains unsupported fields"
        )
    form_id = _source_id(form["id"], "form id")
    _bounded_text(form["name"], "form name", 255)
    _bounded_integer(form["sortOrder"], "form sortOrder", 0, 255)
    if not isinstance(form["isActive"], bool):
        raise EventSurveyExportError("event_survey_export_invalid", "form isActive must be boolean")
    active_version_id = _source_id(form["activeVersionId"], "activeVersionId")
    active_version_number = _bounded_integer(
        form["activeVersionNumber"], "activeVersionNumber", 1, 65_535
    )
    snapshot = cast_mapping(form["activeSnapshot"], "activeSnapshot")
    if set(snapshot) != {"name", "questions"}:
        raise EventSurveyExportError("event_survey_export_invalid", "activeSnapshot is invalid")
    _bounded_text(snapshot["name"], "activeSnapshot name", 255)
    versions = form["versions"]
    if not isinstance(versions, list) or not versions:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Form versions must be non-empty"
        )
    version_questions: dict[str, set[str]] = {}
    version_numbers: set[int] = set()
    active_questions: set[str] | None = None
    for raw_version in versions:
        version_id, version_number, question_ids = _validate_version(raw_version)
        if version_id in version_questions or version_number in version_numbers:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "Form versions must be unique"
            )
        version_questions[version_id] = question_ids
        version_numbers.add(version_number)
        if version_id == active_version_id:
            active_questions = question_ids
            if version_number != active_version_number:
                raise EventSurveyExportError(
                    "event_survey_export_invalid",
                    "Active version number does not match its version",
                )
    if (
        active_questions is None
        or _question_ids(snapshot["questions"], "activeSnapshot") != active_questions
    ):
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Active snapshot must exactly match the active version"
        )
    return form_id, version_questions


def _validate_version(raw_version: Any) -> tuple[str, int, set[str]]:
    version = cast_mapping(raw_version, "form version")
    if set(version) != {"id", "versionNumber", "name", "sortOrder", "questions", "status"}:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Form version contains unsupported fields"
        )
    version_id = _source_id(version["id"], "form version id")
    version_number = _bounded_integer(version["versionNumber"], "versionNumber", 1, 65_535)
    _bounded_text(version["name"], "version name", 255)
    _bounded_integer(version["sortOrder"], "version sortOrder", 0, 255)
    if version["status"] not in {"draft", "published", "archived"}:
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Form version status is invalid"
        )
    return version_id, version_number, _question_ids(version["questions"], "form version")


def _question_ids(raw_questions: Any, location: str) -> set[str]:
    if not isinstance(raw_questions, list) or len(raw_questions) > MAX_QUESTIONS_PER_VERSION:
        raise EventSurveyExportError(
            "event_survey_export_invalid", f"{location} questions exceed the bound"
        )
    question_ids: set[str] = set()
    for raw_question in raw_questions:
        question = cast_mapping(raw_question, "question")
        required = {
            "id",
            "label",
            "type",
            "required",
            "placeholder",
            "options",
            "maxSelections",
            "order",
        }
        if set(question) != required:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Question contains unsupported fields"
            )
        question_id = _source_id(question["id"], "question id")
        if question_id in question_ids:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "Question IDs must be unique"
            )
        question_ids.add(question_id)
        _bounded_text(question["label"], "question label", 500)
        if question["type"] not in {
            "short_answer",
            "long_answer",
            "multiple_choice",
            "checkbox",
            "dropdown",
        }:
            raise EventSurveyExportError("event_survey_export_invalid", "Question type is invalid")
        if not isinstance(question["required"], bool):
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Question required must be boolean"
            )
        _bounded_text(question["placeholder"], "question placeholder", 255, allow_empty=True)
        options = question["options"]
        if not isinstance(options, list) or not all(isinstance(option, str) for option in options):
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Question options must be strings"
            )
        if question["type"] in {"multiple_choice", "checkbox", "dropdown"} and len(options) < 2:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Choice questions need two options"
            )
        if question["type"] not in {"multiple_choice", "checkbox", "dropdown"} and options:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Text questions cannot have options"
            )
        maximum = question["maxSelections"]
        if question["type"] == "checkbox":
            if maximum is not None:
                _bounded_integer(maximum, "maxSelections", 1, len(options))
        elif maximum is not None:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Only checkbox questions can have maxSelections"
            )
        _bounded_integer(question["order"], "question order", 0, 65_535)
    return question_ids


def _validate_registrations(
    registrations: Sequence[Any],
    user_ids: Mapping[str, int],
    version_questions: Mapping[tuple[str, str], set[str]],
) -> int:
    seen_users: set[str] = set()
    answer_count = 0
    for raw_registration in registrations:
        registration = cast_mapping(raw_registration, "registration")
        required = {"userId", "rsvpStatus", "additionalInfo", "formVersions", "answers"}
        if set(registration) != required:
            raise EventSurveyExportError(
                "event_survey_export_unsanitized",
                "Registration contains unsupported or identifying fields",
            )
        user_id = _source_id(registration["userId"], "registration userId")
        if user_id in seen_users:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "Registration users must be unique per event"
            )
        seen_users.add(user_id)
        if user_id not in user_ids:
            raise EventSurveyExportError(
                "event_survey_user_unmapped",
                "Every source user must have an explicit target mapping",
            )
        if registration["rsvpStatus"] not in {"going", "maybe", "not_going"}:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Registration RSVP status is invalid"
            )
        _bounded_text(registration["additionalInfo"], "additionalInfo", 10_000, allow_empty=True)
        answer_count += _validate_registration_forms_and_answers(registration, version_questions)
    return answer_count


def _validate_registration_forms_and_answers(
    registration: Mapping[str, Any], version_questions: Mapping[tuple[str, str], set[str]]
) -> int:
    raw_versions = registration["formVersions"]
    raw_answers = registration["answers"]
    if not isinstance(raw_versions, list) or not isinstance(raw_answers, list):
        raise EventSurveyExportError(
            "event_survey_export_invalid", "Registration forms and answers must be arrays"
        )
    selected_versions: set[tuple[str, str]] = set()
    for raw_version in raw_versions:
        version = cast_mapping(raw_version, "registration form version")
        if set(version) != {"formId", "formVersionId", "formVersionNumber", "formName"}:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Registration form version is invalid"
            )
        key = (
            _source_id(version["formId"], "registration formId"),
            _source_id(version["formVersionId"], "registration formVersionId"),
        )
        if key in selected_versions or key not in version_questions:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Registration references an unknown form version"
            )
        selected_versions.add(key)
        _bounded_integer(version["formVersionNumber"], "registration formVersionNumber", 1, 65_535)
        _bounded_text(version["formName"], "registration formName", 255)
    seen_answers: set[tuple[str, str, str]] = set()
    for raw_answer in raw_answers:
        answer = cast_mapping(raw_answer, "registration answer")
        required = {
            "formId",
            "formVersionId",
            "questionId",
            "questionLabel",
            "questionType",
            "order",
            "required",
            "value",
        }
        if set(answer) != required:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Registration answer is invalid"
            )
        form_id = _source_id(answer["formId"], "answer formId")
        version_id = _source_id(answer["formVersionId"], "answer formVersionId")
        question_id = _source_id(answer["questionId"], "answer questionId")
        key = (form_id, version_id)
        answer_key = (form_id, version_id, question_id)
        if key not in selected_versions or question_id not in version_questions.get(key, set()):
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Answer references an unknown question"
            )
        if answer_key in seen_answers:
            raise EventSurveyExportError(
                "event_survey_export_duplicate", "Registration answers must be unique"
            )
        seen_answers.add(answer_key)
        _bounded_text(answer["questionLabel"], "answer questionLabel", 500)
        if answer["questionType"] not in {
            "short_answer",
            "long_answer",
            "multiple_choice",
            "checkbox",
            "dropdown",
        }:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Answer questionType is invalid"
            )
        if not isinstance(answer["required"], bool):
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Answer required must be boolean"
            )
        _bounded_integer(answer["order"], "answer order", 0, 65_535)
        _validate_answer_value(answer["value"])
    return len(raw_answers)


def _validate_answer_value(value: Any) -> None:
    if isinstance(value, str):
        _bounded_text(value, "answer value", 10_000, allow_empty=True)
        return
    if isinstance(value, list) and all(isinstance(item, str) for item in value):
        if len(value) > MAX_QUESTIONS_PER_VERSION:
            raise EventSurveyExportError(
                "event_survey_export_invalid", "Checkbox answer exceeds the bound"
            )
        for item in value:
            _bounded_text(item, "checkbox answer value", 255)
        return
    raise EventSurveyExportError(
        "event_survey_export_invalid", "Answer values must be strings or arrays"
    )


def cast_mapping(value: Any, location: str) -> Mapping[str, Any]:
    if not isinstance(value, dict) or not all(isinstance(key, str) for key in value):
        raise EventSurveyExportError("event_survey_export_invalid", f"{location} must be an object")
    return value


def _source_id(value: Any, location: str) -> str:
    if (
        not isinstance(value, str)
        or not value.strip()
        or len(value) > 128
        or any(ord(character) < 32 for character in value)
    ):
        raise EventSurveyExportError("event_survey_export_invalid", f"{location} must be a safe ID")
    return value.strip()


def _bounded_text(value: Any, location: str, maximum: int, *, allow_empty: bool = False) -> str:
    if (
        not isinstance(value, str)
        or len(value.strip()) > maximum
        or (not allow_empty and not value.strip())
        or any(ord(character) < 32 for character in value)
    ):
        raise EventSurveyExportError("event_survey_export_invalid", f"{location} is invalid")
    return value.strip()


def _bounded_integer(value: Any, location: str, minimum: int, maximum: int) -> int:
    if isinstance(value, bool) or not isinstance(value, int) or not minimum <= value <= maximum:
        raise EventSurveyExportError("event_survey_export_invalid", f"{location} is invalid")
    return value


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Parse file paths for the read-only export validation command."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--export", type=Path, required=True, help="sanitized normalized export JSON"
    )
    parser.add_argument(
        "--mapping", type=Path, required=True, help="explicit event/user mapping JSON"
    )
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    """Validate inputs and write only aggregate counts to standard output."""
    arguments = parse_args(argv)
    try:
        summary = validate_export_files(arguments.export, arguments.mapping)
    except EventSurveyExportError as exc:
        print(json.dumps({"status": "invalid", "code": exc.code}, sort_keys=True))
        return 2
    print(json.dumps({"status": "valid", **summary.as_dict()}, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
