"""Tests for the read-only, explicitly mapped Firebase survey export validator."""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from scripts.validate_event_survey_export import (
    EventSurveyExportError,
    EventSurveyExportSummary,
    validate_export_files,
)


def _question(question_id: str = "question-1") -> dict[str, object]:
    return {
        "id": question_id,
        "label": "Dietary note",
        "type": "short_answer",
        "required": False,
        "placeholder": "Optional",
        "options": [],
        "maxSelections": None,
        "order": 0,
    }


def _export() -> dict[str, object]:
    question = _question()
    return {
        "format": "alumni-event-survey-export",
        "version": 1,
        "surveys": [
            {
                "eventId": "firebase-event-1",
                "forms": [
                    {
                        "id": "form-1",
                        "name": "Registration questions",
                        "sortOrder": 0,
                        "isActive": True,
                        "activeVersionId": "v1",
                        "activeVersionNumber": 1,
                        "activeSnapshot": {
                            "name": "Registration questions",
                            "questions": [question],
                        },
                        "versions": [
                            {
                                "id": "v1",
                                "versionNumber": 1,
                                "name": "Registration questions",
                                "sortOrder": 0,
                                "questions": [question],
                                "status": "published",
                            }
                        ],
                    }
                ],
                "registrations": [
                    {
                        "userId": "firebase-user-1",
                        "rsvpStatus": "going",
                        "additionalInfo": "",
                        "formVersions": [
                            {
                                "formId": "form-1",
                                "formVersionId": "v1",
                                "formVersionNumber": 1,
                                "formName": "Registration questions",
                            }
                        ],
                        "answers": [
                            {
                                "formId": "form-1",
                                "formVersionId": "v1",
                                "questionId": "question-1",
                                "questionLabel": "Dietary note",
                                "questionType": "short_answer",
                                "order": 0,
                                "required": False,
                                "value": "Vegetarian",
                            }
                        ],
                    }
                ],
            }
        ],
    }


def _mapping() -> dict[str, object]:
    return {"event_ids": {"firebase-event-1": 101}, "user_ids": {"firebase-user-1": 202}}


def _write_inputs(
    tmp_path: Path, export: dict[str, object], mapping: dict[str, object]
) -> tuple[Path, Path]:
    export_path = tmp_path / "sanitized-export.json"
    mapping_path = tmp_path / "explicit-map.json"
    export_path.write_text(json.dumps(export), encoding="utf-8")
    mapping_path.write_text(json.dumps(mapping), encoding="utf-8")
    return export_path, mapping_path


def test_sanitized_export_requires_explicit_event_and_user_maps(tmp_path: Path) -> None:
    export_path, mapping_path = _write_inputs(tmp_path, _export(), _mapping())

    assert validate_export_files(export_path, mapping_path) == EventSurveyExportSummary(
        surveys=1,
        forms=1,
        versions=1,
        registrations=1,
        answers=1,
    )


@pytest.mark.parametrize(
    ("target", "expected_code"),
    [
        ("event", "event_survey_event_unmapped"),
        ("user", "event_survey_user_unmapped"),
    ],
)
def test_export_rejects_inferred_source_to_target_ids(
    tmp_path: Path, target: str, expected_code: str
) -> None:
    mapping = _mapping()
    mapping[f"{target}_ids"] = {}
    export_path, mapping_path = _write_inputs(tmp_path, _export(), mapping)

    with pytest.raises(EventSurveyExportError) as caught:
        validate_export_files(export_path, mapping_path)

    assert caught.value.code == expected_code


def test_export_rejects_registration_pii_not_in_the_sanitized_contract(tmp_path: Path) -> None:
    export = _export()
    registration = export["surveys"][0]["registrations"][0]  # type: ignore[index]
    registration["userEmail"] = "not-allowed@example.invalid"
    export_path, mapping_path = _write_inputs(tmp_path, export, _mapping())

    with pytest.raises(EventSurveyExportError) as caught:
        validate_export_files(export_path, mapping_path)

    assert caught.value.code == "event_survey_export_unsanitized"


def test_export_requires_answer_version_and_question_provenance(tmp_path: Path) -> None:
    export = _export()
    answer = export["surveys"][0]["registrations"][0]["answers"][0]  # type: ignore[index]
    answer["formVersionId"] = "missing-version"
    export_path, mapping_path = _write_inputs(tmp_path, export, _mapping())

    with pytest.raises(EventSurveyExportError) as caught:
        validate_export_files(export_path, mapping_path)

    assert caught.value.code == "event_survey_export_invalid"


def test_export_requires_active_snapshot_to_match_its_immutable_version(tmp_path: Path) -> None:
    export = _export()
    form = export["surveys"][0]["forms"][0]  # type: ignore[index]
    form["activeSnapshot"] = {
        "name": "Registration questions",
        "questions": [_question("other")],
    }
    export_path, mapping_path = _write_inputs(tmp_path, export, _mapping())

    with pytest.raises(EventSurveyExportError) as caught:
        validate_export_files(export_path, mapping_path)

    assert caught.value.code == "event_survey_export_invalid"
