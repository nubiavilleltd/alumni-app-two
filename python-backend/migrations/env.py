"""Alembic environment bound only to explicit Alumni Portal configuration."""

from logging.config import fileConfig

from alembic import context
from sqlalchemy import engine_from_config, pool

from app.core.config import Settings
from app.models import Base

config = context.config

if config.config_file_name is not None:
    fileConfig(config.config_file_name)

settings = Settings()
database_url = settings.database_url_value()
if not database_url:
    raise RuntimeError("ALUMNI_DATABASE_URL is required for every Alembic command")
if settings.environment == "production" and not settings.migration_allow_production:
    raise RuntimeError(
        "Production migrations require the explicit ALUMNI_MIGRATION_ALLOW_PRODUCTION=true gate"
    )

# ConfigParser treats percent characters as interpolation markers.
config.set_main_option("sqlalchemy.url", database_url.replace("%", "%%"))
target_metadata = Base.metadata


def include_object(
    _object: object,
    name: str | None,
    type_: str,
    _reflected: bool,
    _compare_to: object | None,
) -> bool:
    """Exclude Alembic's own version table from model/schema comparisons."""
    return not (type_ == "table" and name == "alembic_version")


def run_migrations_offline() -> None:
    """Render SQL without establishing a database connection."""
    context.configure(
        url=config.get_main_option("sqlalchemy.url"),
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
        compare_type=True,
        compare_server_default=True,
        include_object=include_object,
    )
    with context.begin_transaction():
        context.run_migrations()


def run_migrations_online() -> None:
    """Run migrations against the explicitly configured database."""
    connectable = engine_from_config(
        config.get_section(config.config_ini_section, {}),
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )
    with connectable.connect() as connection:
        context.configure(
            connection=connection,
            target_metadata=target_metadata,
            compare_type=True,
            compare_server_default=True,
            include_object=include_object,
        )
        with context.begin_transaction():
            context.run_migrations()


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
