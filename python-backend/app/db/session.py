"""Lazy SQLAlchemy engine creation and safe readiness checks."""

from collections.abc import Generator
from dataclasses import dataclass

from sqlalchemy import Engine, create_engine, text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session, sessionmaker

from app.core.config import Settings


@dataclass(frozen=True, slots=True)
class DatabaseStatus:
    """Sanitized database readiness result."""

    ready: bool
    reason: str


class Database:
    """Own the process-wide SQLAlchemy engine and session factory."""

    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._engine: Engine | None = None
        self._session_factory: sessionmaker[Session] | None = None

    @property
    def configured(self) -> bool:
        """Return whether a database URL is available."""
        return self._settings.database_url is not None

    def engine(self) -> Engine:
        """Create the synchronous MySQL engine on first use."""
        if self._engine is not None:
            return self._engine

        database_url = self._settings.database_url_value()
        if not database_url:
            raise RuntimeError("Database is not configured")

        connect_args: dict[str, object] = {}
        if database_url.startswith("mysql+pymysql://"):
            connect_args = {
                "charset": "utf8mb4",
                "connect_timeout": self._settings.database_connect_timeout_seconds,
                "read_timeout": 10,
                "write_timeout": 10,
            }

        self._engine = create_engine(
            database_url,
            connect_args=connect_args,
            pool_pre_ping=True,
            pool_recycle=1800,
            pool_size=self._settings.database_pool_size,
            max_overflow=self._settings.database_max_overflow,
            pool_timeout=self._settings.database_pool_timeout_seconds,
        )
        self._session_factory = sessionmaker(
            bind=self._engine,
            autoflush=False,
            expire_on_commit=False,
        )
        return self._engine

    def sessions(self) -> Generator[Session]:
        """Yield a session and guarantee rollback on failures."""
        self.engine()
        if self._session_factory is None:
            raise RuntimeError("Database session factory was not initialized")
        session = self._session_factory()
        try:
            yield session
        except Exception:
            session.rollback()
            raise
        finally:
            session.close()

    def check(self) -> DatabaseStatus:
        """Execute a bounded connectivity probe without leaking connection details."""
        if not self.configured:
            return DatabaseStatus(ready=False, reason="not_configured")
        try:
            with self.engine().connect() as connection:
                connection.execute(text("SELECT 1"))
        except (SQLAlchemyError, OSError, RuntimeError):
            return DatabaseStatus(ready=False, reason="unavailable")
        return DatabaseStatus(ready=True, reason="ready")

    def dispose(self) -> None:
        """Close all pooled connections during application shutdown."""
        if self._engine is not None:
            self._engine.dispose()
