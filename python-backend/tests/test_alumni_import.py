"""Security and validation tests for bounded alumni roster parsing."""

from __future__ import annotations

import json
from collections.abc import Sequence
from datetime import UTC, datetime, timedelta
from io import BytesIO

import pytest
from openpyxl import Workbook  # type: ignore[import-untyped]

from app.integrations.alumni_import import (
    MAX_ALUMNI_IMPORT_BYTES,
    AlumniImportFileError,
    parse_alumni_import_file,
    parse_alumni_import_json,
)


def _record(**changes: object) -> dict[str, object]:
    record: dict[str, object] = {
        "email": "alumni@example.com",
        "last_name": "Member",
        "first_name": "Synthetic Ada",
        "graduation_year": datetime.now(UTC).year,
        "city": "Synthetic City",
    }
    record.update(changes)
    return record


def _xlsx_bytes(rows: Sequence[Sequence[object]]) -> bytes:
    workbook = Workbook()
    sheet = workbook.active
    for row in rows:
        sheet.append(row)
    output = BytesIO()
    workbook.save(output)
    workbook.close()
    return output.getvalue()


def _error_code(callable_: object, *args: object) -> str:
    with pytest.raises(AlumniImportFileError) as caught:
        callable_(*args)  # type: ignore[operator]
    return caught.value.code


def test_json_accepts_canonical_fields_and_never_treats_coordinator_as_a_grant() -> None:
    payload = {
        "chapter_id": 7,
        "records": [
            _record(
                email="ALUMNI@EXAMPLE.COM",
                is_coordinator=True,
                is_volunteer="yes",
                birth_date="2000-02-29",
            )
        ],
    }

    chapter_id, rows = parse_alumni_import_json(json.dumps(payload).encode())

    assert chapter_id == 7
    assert len(rows) == 1
    assert rows[0].email == "alumni@example.com"
    assert rows[0].first_name == "Synthetic"
    assert rows[0].coordinator_requested is True
    assert rows[0].is_volunteer is True


def test_csv_and_xlsx_accept_legacy_headers_without_evaluating_formulas() -> None:
    headers = [
        "Email Address",
        "Surname",
        "Other Names",
        "Year of Graduation from FGGC Owerri (e.g. 1994)",
        "City (please select the closest to your location)",
    ]
    csv_content = (
        ",".join(headers)
        + f"\nalumni@example.com,Member,Synthetic Ada,{datetime.now(UTC).year},Synthetic City\n"
    ).encode()
    csv_rows = parse_alumni_import_file("roster.csv", csv_content, "text/csv")
    xlsx_rows = parse_alumni_import_file(
        "roster.xlsx",
        _xlsx_bytes([headers, list(_record().values())]),
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    )

    assert csv_rows[0].fullname == "Synthetic Ada Member"
    assert xlsx_rows[0].city == "Synthetic City"

    formula = _xlsx_bytes([headers, ['=LOWER("A@EXAMPLE.COM")', "Member", "Ada", 2000, "City"]])
    assert (
        _error_code(parse_alumni_import_file, "roster.xlsx", formula, "application/octet-stream")
        == "alumni_import_formula_not_allowed"
    )


@pytest.mark.parametrize(
    ("payload_change", "expected_code"),
    [
        ({"default_password": "SharedPassword1!"}, "alumni_import_invalid_request"),
        ({"role": "admin"}, "alumni_import_invalid_request"),
    ],
)
def test_json_rejects_top_level_credential_or_privilege_fields(
    payload_change: dict[str, object], expected_code: str
) -> None:
    payload: dict[str, object] = {"chapter_id": 1, "records": [_record()]}
    payload.update(payload_change)
    assert _error_code(parse_alumni_import_json, json.dumps(payload).encode()) == expected_code


def test_import_rejects_duplicate_emails_and_unknown_or_duplicate_columns() -> None:
    duplicate = {"chapter_id": 1, "records": [_record(), _record(email="ALUMNI@example.com")]}
    assert (
        _error_code(parse_alumni_import_json, json.dumps(duplicate).encode())
        == "alumni_import_duplicate_email"
    )

    unknown_header = (
        b"email,last_name,first_name,graduation_year,city,role\na@b.com,A,B,2000,City,admin\n"
    )
    duplicate_header = (
        b"email,email,last_name,first_name,graduation_year,city\na@b.com,a@b.com,A,B,2000,City\n"
    )
    for content in (unknown_header, duplicate_header):
        assert (
            _error_code(parse_alumni_import_file, "roster.csv", content, "text/csv")
            == "alumni_import_invalid_headers"
        )


@pytest.mark.parametrize(
    "changes",
    [
        {"birth_date": (datetime.now(UTC).date() + timedelta(days=1)).isoformat()},
        {"graduation_year": datetime.now(UTC).year + 1},
        {"is_volunteer": "perhaps"},
        {"city": "Invalid\u0007City"},
    ],
)
def test_json_rejects_invalid_dates_years_booleans_and_control_characters(
    changes: dict[str, object],
) -> None:
    payload = {"chapter_id": 1, "records": [_record(**changes)]}
    assert (
        _error_code(parse_alumni_import_json, json.dumps(payload).encode())
        == "alumni_import_invalid_row"
    )


def test_import_rejects_legacy_xls_wrong_mime_and_oversized_payloads() -> None:
    assert (
        _error_code(
            parse_alumni_import_file,
            "roster.xls",
            b"legacy",
            "application/vnd.ms-excel",
        )
        == "alumni_import_legacy_xls_unsupported"
    )
    assert (
        _error_code(parse_alumni_import_file, "roster.csv", b"x", "application/pdf")
        == "alumni_import_unsupported_type"
    )
    assert (
        _error_code(parse_alumni_import_json, b"x" * (MAX_ALUMNI_IMPORT_BYTES + 1))
        == "alumni_import_too_large"
    )
