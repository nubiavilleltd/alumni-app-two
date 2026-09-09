"""Database-model parity tests against the explicit sanitized test database."""

import os
from collections.abc import Iterator
from decimal import Decimal

import pytest
from sqlalchemy import DECIMAL, Engine, create_engine, func, inspect, select

from app.models import Base


def _normalized_default(value: object | None) -> str | None:
    """Normalize reflected and generated SQL default expressions for comparison."""
    if value is None:
        return None
    argument = getattr(value, "arg", value)
    return " ".join(str(argument).casefold().split())


@pytest.fixture(scope="module")
def schema_engine() -> Iterator[Engine]:
    """Connect only to the explicitly supplied disposable database."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")
    engine = create_engine(database_url, pool_pre_ping=True)
    try:
        yield engine
    finally:
        engine.dispose()


@pytest.mark.integration
def test_all_legacy_tables_and_columns_are_mapped(schema_engine: Engine) -> None:
    """Every database table, column, primary key, and nullability flag matches the models."""
    inspector = inspect(schema_engine)
    database_tables = set(inspector.get_table_names()) - {"alembic_version"}
    model_tables = set(Base.metadata.tables)
    assert model_tables == database_tables
    assert len(model_tables) == 68

    for table_name in sorted(database_tables):
        model_table = Base.metadata.tables[table_name]
        database_columns = {column["name"]: column for column in inspector.get_columns(table_name)}
        database_primary_key = set(
            inspector.get_pk_constraint(table_name).get("constrained_columns") or []
        )
        assert set(model_table.columns.keys()) == set(database_columns), table_name
        for column in model_table.columns:
            database_column = database_columns[column.name]
            assert column.nullable is database_column["nullable"], f"{table_name}.{column.name}"
            assert column.primary_key is (column.name in database_primary_key), (
                f"{table_name}.{column.name}"
            )
            model_type = column.type.compile(dialect=schema_engine.dialect).casefold()
            database_type = (
                database_column["type"].compile(dialect=schema_engine.dialect).casefold()
            )
            assert model_type == database_type, f"{table_name}.{column.name}"
            database_computed = database_column.get("computed")
            if column.computed is None:
                assert database_computed is None, f"{table_name}.{column.name}"
                assert _normalized_default(column.server_default) == _normalized_default(
                    database_column.get("default")
                ), f"{table_name}.{column.name}"
            else:
                assert isinstance(database_computed, dict), f"{table_name}.{column.name}"
                assert _normalized_default(column.computed.sqltext) == _normalized_default(
                    database_computed.get("sqltext")
                ), f"{table_name}.{column.name}"
                assert column.computed.persisted is database_computed.get("persisted"), (
                    f"{table_name}.{column.name}"
                )
            assert column.comment == database_column.get("comment"), f"{table_name}.{column.name}"

        model_foreign_keys = {
            (
                tuple(element.parent.name for element in constraint.elements),
                constraint.referred_table.name,
                tuple(element.column.name for element in constraint.elements),
            )
            for constraint in model_table.foreign_key_constraints
        }
        database_foreign_keys = {
            (
                tuple(constraint["constrained_columns"]),
                constraint["referred_table"],
                tuple(constraint["referred_columns"]),
            )
            for constraint in inspector.get_foreign_keys(table_name)
        }
        assert model_foreign_keys == database_foreign_keys, table_name

        model_indexes = {
            (tuple(column.name for column in index.columns), bool(index.unique))
            for index in model_table.indexes
        }
        database_indexes = {
            (tuple(index["column_names"]), bool(index["unique"]))
            for index in inspector.get_indexes(table_name)
        }
        assert model_indexes == database_indexes, table_name


@pytest.mark.integration
def test_sanitized_database_contains_no_rows(schema_engine: Engine) -> None:
    """The generated integration target must not contain copied SQL-dump records."""
    with schema_engine.connect() as connection:
        for table in Base.metadata.sorted_tables:
            assert connection.scalar(select(func.count()).select_from(table)) == 0, table.name


def test_decimal_columns_use_decimal_values() -> None:
    """Money-compatible schema columns must never map to binary floating point."""
    decimal_columns = [
        column
        for table in Base.metadata.tables.values()
        for column in table.columns
        if isinstance(column.type, DECIMAL)
    ]
    assert len(decimal_columns) == 16
    assert all(column.type.python_type is Decimal for column in decimal_columns)


@pytest.mark.integration
def test_rollback_safe_write_preserves_empty_schema(schema_engine: Engine) -> None:
    """A representative write can be flushed and rolled back without residual data."""
    table = Base.metadata.tables["setup_parameters"]
    with schema_engine.connect() as connection:
        transaction = connection.begin()
        connection.execute(
            table.insert().values(setup_name="rollback_probe", setup_value="temporary")
        )
        transaction.rollback()
        remaining = connection.scalar(
            select(func.count()).select_from(table).where(table.c.setup_name == "rollback_probe")
        )
    assert remaining == 0
