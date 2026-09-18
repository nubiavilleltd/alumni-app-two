"""Database repository for news feeds authentication and configuration lookup."""

from __future__ import annotations

import secrets

import bcrypt
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models.generated import ApiTable, SetupParameters


class NewsRepository:
    """Encapsulates database access for api_table credentials and setup parameters."""

    def __init__(self, session: Session) -> None:
        self._session = session

    def verify_api_key(self, raw_key: str | None) -> bool:
        """Verify the supplied API key against the alumni_key row in api_table."""
        if not raw_key or not raw_key.strip():
            return False

        trimmed = raw_key.strip()
        stmt = select(ApiTable).where(ApiTable.api_name == "alumni_key").limit(1)
        row = self._session.execute(stmt).scalar_one_or_none()
        if row is None:
            return False

        # Match either exact api_key column or bcrypt hash in api_token column
        if row.api_key and secrets.compare_digest(trimmed, row.api_key):
            return True

        if row.api_token:
            try:
                norm_hash = row.api_token.replace("$2y$", "$2b$").encode("utf-8")
                if bcrypt.checkpw(trimmed.encode("utf-8"), norm_hash):
                    return True
            except (ValueError, TypeError):
                pass

        return False

    def get_setup_news_category(self) -> str:
        """Fetch the default news_category setup parameter, or empty string if unset."""
        stmt = (
            select(SetupParameters.setup_value)
            .where(SetupParameters.setup_name == "news_category")
            .limit(1)
        )
        val = self._session.execute(stmt).scalar_one_or_none()
        return val.strip() if val else ""
