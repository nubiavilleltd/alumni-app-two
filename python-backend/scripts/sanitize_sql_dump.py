"""Produce a schema-only, data-free copy of a phpMyAdmin/mysqldump SQL file.

The migration workflow must never import production rows, so this strips every
``INSERT INTO`` statement (single- or multi-line) while preserving ``CREATE TABLE``,
``ALTER TABLE``, comments, and session pragmas. It is used to turn a supplied live
dump into a disposable-schema baseline for drift and parity checks.
"""

from __future__ import annotations

import argparse
from collections.abc import Sequence


def sanitize(text: str) -> str:
    """Remove INSERT statements, leaving structure and comments intact."""
    output: list[str] = []
    skipping = False
    for line in text.splitlines():
        stripped = line.lstrip()
        if skipping:
            if stripped.endswith(";"):
                skipping = False
            continue
        if stripped.upper().startswith("INSERT INTO"):
            if not stripped.endswith(";"):
                skipping = True
            continue
        output.append(line)
    return "\n".join(output) + "\n"


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", required=True, help="path to the full SQL dump")
    parser.add_argument("--output", required=True, help="path to write the schema-only copy")
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    arguments = parse_args(argv)
    with open(arguments.input, encoding="utf-8") as handle:
        source = handle.read()
    cleaned = sanitize(source)
    with open(arguments.output, "w", encoding="utf-8") as handle:
        handle.write(cleaned)
    create_count = cleaned.count("CREATE TABLE")
    insert_count = cleaned.count("INSERT INTO")
    alter_count = cleaned.count("ALTER TABLE")
    print(
        f"sanitized {arguments.input} -> {arguments.output}: "
        f"CREATE TABLE={create_count} INSERT INTO={insert_count} ALTER TABLE={alter_count}"
    )
    return 0 if insert_count == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
