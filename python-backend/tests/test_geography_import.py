"""Bounded parser tests for administrator geography catalogue uploads."""

from __future__ import annotations

from io import BytesIO

import pytest
from openpyxl import Workbook  # type: ignore[import-untyped]

from app.integrations.geography_import import (
    MAX_GEOGRAPHY_IMPORT_BYTES,
    GeographyImportFileError,
    GeographyImportRow,
    parse_geography_import,
)


def _xlsx_bytes(rows: list[list[object]]) -> bytes:
    workbook = Workbook()
    sheet = workbook.active
    for row in rows:
        sheet.append(row)
    output = BytesIO()
    workbook.save(output)
    workbook.close()
    return output.getvalue()


def test_geography_import_parser_accepts_normalized_csv_and_xlsx() -> None:
    """Both retained formats yield the same compact, formula-free row contract."""
    csv_rows = parse_geography_import(
        "locations.csv",
        b"\xef\xbb\xbf CITY , ZONE \r\n  First   City  , North   Zone \r\n",
        "text/csv; charset=utf-8",
    )
    xlsx_rows = parse_geography_import(
        "locations.xlsx",
        _xlsx_bytes([["zone", "city"], ["South Zone", "Second City"]]),
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    )

    assert csv_rows == (GeographyImportRow(source_row=2, zone="North Zone", city="First City"),)
    assert xlsx_rows == (GeographyImportRow(source_row=2, zone="South Zone", city="Second City"),)


@pytest.mark.parametrize(
    ("filename", "content", "content_type", "code", "http_status"),
    [
        (
            "locations.xls",
            b"legacy",
            "application/vnd.ms-excel",
            "geography_import_legacy_xls_unsupported",
            415,
        ),
        (
            "locations.txt",
            b"zone,city\nNorth,One",
            "text/plain",
            "geography_import_unsupported_type",
            415,
        ),
        (
            "locations.csv",
            b"zone,city\nNorth,One\nSouth,one",
            "text/csv",
            "geography_import_duplicate_city",
            400,
        ),
        (
            "locations.csv",
            b"zone,city,role\nNorth,One,admin",
            "text/csv",
            "geography_import_invalid_headers",
            400,
        ),
        (
            "locations.csv",
            b"zone,city\nNorth,\x00One",
            "text/csv",
            "geography_import_invalid_file",
            400,
        ),
        (
            "locations.xlsx",
            b"not-a-zip",
            "application/octet-stream",
            "geography_import_invalid_file",
            400,
        ),
        (
            "locations.csv",
            b"x" * (MAX_GEOGRAPHY_IMPORT_BYTES + 1),
            "text/csv",
            "geography_import_too_large",
            413,
        ),
    ],
    ids=[
        "legacy-xls",
        "unsupported-extension",
        "duplicate-city",
        "extra-header",
        "nul-byte",
        "invalid-xlsx",
        "oversized-file",
    ],
)
def test_geography_import_parser_rejects_unsafe_or_ambiguous_files(
    filename: str,
    content: bytes,
    content_type: str,
    code: str,
    http_status: int,
) -> None:
    """Unsupported, oversized, malformed, or duplicate input fails before SQL."""
    with pytest.raises(GeographyImportFileError) as error:
        parse_geography_import(filename, content, content_type)
    assert error.value.code == code
    assert error.value.http_status == http_status


def test_geography_import_parser_rejects_spreadsheet_formulas() -> None:
    """The server never evaluates or accepts formulas in imported catalogue fields."""
    content = _xlsx_bytes([["zone", "city"], ["North", '=HYPERLINK("https://invalid")']])
    with pytest.raises(GeographyImportFileError) as error:
        parse_geography_import("locations.xlsx", content)
    assert error.value.code == "geography_import_formula_not_allowed"
