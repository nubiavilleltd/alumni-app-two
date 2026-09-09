"""baseline existing alumni schema

Revision ID: 7250164972b6
Revises:
Create Date: 2026-09-08 22:32:11.766808

"""

from collections.abc import Sequence

# revision identifiers, used by Alembic.
revision: str = "7250164972b6"
down_revision: str | Sequence[str] | None = None
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Upgrade schema."""


def downgrade() -> None:
    """Downgrade schema."""
