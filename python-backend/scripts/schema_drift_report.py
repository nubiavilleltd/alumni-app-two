"""Compare a live schema-only database against the current SQLAlchemy models.

Reports tables and columns/indexes that differ in either direction without touching
row data. Used as Goal 3 drift evidence against a disposable schema-only import of a
supplied live dump.
"""

from __future__ import annotations

import argparse
import json
from collections.abc import Sequence

from sqlalchemy import create_engine, inspect
from sqlalchemy.engine import Engine

from app.models.generated import Base


def _columns(engine: Engine, table: str) -> dict[str, dict[str, object]]:
    inspector = inspect(engine)
    dialect = engine.dialect
    return {
        column["name"]: {
            "type": column["type"].compile(dialect=dialect).casefold(),
            "nullable": column["nullable"],
            "default": column.get("default"),
        }
        for column in inspector.get_columns(table)
    }


def _indexes(engine: Engine, table: str) -> set[tuple[str, tuple[str, ...], bool]]:
    inspector = inspect(engine)
    return {
        (
            str(index["name"]),
            tuple(str(c) for c in (index["column_names"] or [])),
            bool(index.get("unique")),
        )
        for index in inspector.get_indexes(table)
    }


def report(database_url: str) -> dict[str, object]:
    engine = create_engine(database_url, pool_pre_ping=True)
    try:
        inspector = inspect(engine)
        live_tables = set(inspector.get_table_names())
        model_tables = set(Base.metadata.tables)

        tables_only_live = sorted(live_tables - model_tables)
        tables_only_model = sorted(model_tables - live_tables)

        column_drift: dict[str, dict[str, object]] = {}
        index_drift: dict[str, dict[str, object]] = {}
        for table in sorted(live_tables & model_tables):
            live_columns = _columns(engine, table)
            model_table = Base.metadata.tables[table]
            model_columns = {
                column.name: {
                    "type": column.type.compile(dialect=engine.dialect).casefold(),
                    "nullable": column.nullable,
                }
                for column in model_table.columns
            }
            only_live = sorted(set(live_columns) - set(model_columns))
            only_model = sorted(set(model_columns) - set(live_columns))
            type_diff = sorted(
                name
                for name in set(live_columns) & set(model_columns)
                if live_columns[name]["type"] != model_columns[name]["type"]
            )
            nullable_diff = sorted(
                name
                for name in set(live_columns) & set(model_columns)
                if live_columns[name]["nullable"] != model_columns[name]["nullable"]
            )
            if only_live or only_model or type_diff or nullable_diff:
                column_drift[table] = {
                    "only_in_live": only_live,
                    "only_in_model": only_model,
                    "type_differs": type_diff,
                    "nullable_differs": nullable_diff,
                }

            live_indexes = _indexes(engine, table)
            model_indexes = {
                (str(index.name), tuple(c.name for c in index.columns), bool(index.unique))
                for index in model_table.indexes
            }
            only_live_idx = sorted(i[0] for i in live_indexes - model_indexes)
            only_model_idx = sorted(i[0] for i in model_indexes - live_indexes)
            if only_live_idx or only_model_idx:
                index_drift[table] = {
                    "only_in_live": only_live_idx,
                    "only_in_model": only_model_idx,
                }

        return {
            "live_tables": len(live_tables),
            "model_tables": len(model_tables),
            "tables_only_in_live": tables_only_live,
            "tables_only_in_model": tables_only_model,
            "column_drift": column_drift,
            "index_drift": index_drift,
        }
    finally:
        engine.dispose()


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--database-url", required=True, help="disposable localhost test URL")
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    arguments = parse_args(argv)
    print(json.dumps(report(arguments.database_url), indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
