"""add processing to orders status enum

Revision ID: b2c3d4e5f6a7
Revises: 0e4c31d8f2a7
Create Date: 2026-09-18
"""

from collections.abc import Sequence

from alembic import op

revision: str = "b2c3d4e5f6a7"
down_revision: str | Sequence[str] | None = "0e4c31d8f2a7"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Add processing state to orders status enum."""
    op.execute(
        "ALTER TABLE orders MODIFY COLUMN status "
        "enum('pending','paid','processing','shipped','delivered','failed','cancelled') "
        "NOT NULL DEFAULT 'pending'"
    )


def downgrade() -> None:
    """Revert orders status enum back to original."""
    op.execute(
        "ALTER TABLE orders MODIFY COLUMN status "
        "enum('pending','paid','shipped','delivered','failed','cancelled') "
        "NOT NULL DEFAULT 'pending'"
    )
