"""Classify legacy user password hashes without emitting credential material."""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from pathlib import Path

BCRYPT_PATTERN = re.compile(r"^\$2([aby])\$(\d{2})\$")
HEX40_PATTERN = re.compile(r"^[0-9a-fA-F]{40}$")


def _insert_statement(sql: str, table: str) -> str:
    marker = f"INSERT INTO `{table}`"
    start = sql.find(marker)
    if start < 0:
        raise ValueError(f"No INSERT statement found for {table}")
    quoted = False
    escaped = False
    for index in range(start, len(sql)):
        character = sql[index]
        if escaped:
            escaped = False
        elif character == "\\" and quoted:
            escaped = True
        elif character == "'":
            quoted = not quoted
        elif character == ";" and not quoted:
            return sql[start : index + 1]
    raise ValueError(f"Unterminated INSERT statement for {table}")


def _parse_sql_values(values: str) -> list[list[str]]:
    rows: list[list[str]] = []
    row: list[str] = []
    field: list[str] = []
    depth = 0
    quoted = False
    escaped = False
    for character in values:
        if escaped:
            field.append(character)
            escaped = False
            continue
        if character == "\\" and quoted:
            escaped = True
            continue
        if character == "'":
            quoted = not quoted
            continue
        if not quoted and character == "(":
            if depth == 0:
                row = []
                field = []
            else:
                field.append(character)
            depth += 1
            continue
        if not quoted and character == ")":
            depth -= 1
            if depth == 0:
                row.append("".join(field).strip())
                rows.append(row)
                field = []
            else:
                field.append(character)
            continue
        if not quoted and character == "," and depth == 1:
            row.append("".join(field).strip())
            field = []
            continue
        if depth >= 1:
            field.append(character)
    if quoted or depth != 0:
        raise ValueError("Malformed SQL VALUES expression")
    return rows


def inventory_password_hashes(sql: str) -> dict[str, object]:
    """Return aggregate password-hash categories from the users INSERT only."""
    statement = _insert_statement(sql, "users")
    columns_match = re.search(r"INSERT INTO `users`\s*\((.*?)\)\s*VALUES", statement, re.DOTALL)
    if not columns_match:
        raise ValueError("Users INSERT column list is malformed")
    columns = re.findall(r"`([^`]+)`", columns_match.group(1))
    try:
        password_index = columns.index("password")
    except ValueError as exc:
        raise ValueError("Users INSERT has no password column") from exc
    values = statement[columns_match.end() :].rsplit(";", maxsplit=1)[0]
    rows = _parse_sql_values(values)
    categories: Counter[str] = Counter()
    bcrypt_costs: Counter[str] = Counter()
    for row in rows:
        if len(row) != len(columns):
            raise ValueError("Users INSERT row does not match its column list")
        encoded_hash = row[password_index]
        bcrypt_match = BCRYPT_PATTERN.match(encoded_hash)
        if bcrypt_match:
            categories["bcrypt"] += 1
            bcrypt_costs[bcrypt_match.group(2)] += 1
        elif encoded_hash.startswith("$argon2"):
            categories["argon2"] += 1
        elif HEX40_PATTERN.fullmatch(encoded_hash):
            categories["40-character-hex-legacy"] += 1
        elif not encoded_hash or encoded_hash.upper() == "NULL":
            categories["empty-or-null"] += 1
        else:
            categories["unclassified"] += 1
    return {
        "users_with_exported_hashes": len(rows),
        "categories": dict(sorted(categories.items())),
        "bcrypt_cost_factors": dict(sorted(bcrypt_costs.items())),
        "contains_hash_values": False,
    }


def main() -> int:
    """Write a value-free JSON inventory for security review."""
    parser = argparse.ArgumentParser()
    parser.add_argument("sql_path", type=Path)
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()
    report = inventory_password_hashes(args.sql_path.read_text(encoding="utf-8", errors="strict"))
    rendered = json.dumps(report, indent=2) + "\n"
    if args.output:
        args.output.write_text(rendered, encoding="utf-8")
    else:
        print(rendered, end="")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
