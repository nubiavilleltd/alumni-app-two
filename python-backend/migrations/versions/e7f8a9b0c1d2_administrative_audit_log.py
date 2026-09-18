"""add a durable, secret-free administrative audit log

Revision ID: e7f8a9b0c1d2
Revises: d5e6f7a8b9c0
Create Date: 2026-09-18
"""

from collections.abc import Sequence

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects import mysql

revision: str = "e7f8a9b0c1d2"
down_revision: str | Sequence[str] | None = "d5e6f7a8b9c0"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Create an append-only, secret-free audit log for privileged actions."""
    op.create_table(
        "audit_log",
        sa.Column(
            "id",
            mysql.INTEGER(display_width=10, unsigned=True),
            primary_key=True,
            autoincrement=True,
            nullable=False,
        ),
        sa.Column("actor_user_id", mysql.INTEGER(display_width=10, unsigned=True), nullable=True),
        sa.Column("action", sa.String(100), nullable=False),
        sa.Column("target_type", sa.String(100), nullable=True),
        sa.Column("target_id", mysql.INTEGER(display_width=10, unsigned=True), nullable=True),
        sa.Column("details", sa.Text(), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(),
            nullable=False,
            server_default=sa.text("current_timestamp()"),
        ),
    )
    op.create_index("idx_audit_log_actor", "audit_log", ["actor_user_id"])
    op.create_index("idx_audit_log_action", "audit_log", ["action"])


def downgrade() -> None:
    op.drop_index("idx_audit_log_action", table_name="audit_log")
    op.drop_index("idx_audit_log_actor", table_name="audit_log")
    op.drop_table("audit_log")
