"""retain private chat attachment ownership and staged-upload expiry

Revision ID: 0e4c31d8f2a7
Revises: 9d9d1f2a7c31
Create Date: 2026-09-17
"""

from collections.abc import Sequence

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects import mysql

revision: str = "0e4c31d8f2a7"
down_revision: str | Sequence[str] | None = "9d9d1f2a7c31"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Add privacy metadata without rewriting legacy/public attachment rows."""
    op.add_column(
        "messages_attachments",
        sa.Column(
            "uploaded_by_member_id",
            mysql.INTEGER(unsigned=True),
            nullable=True,
            comment="Required for private staged-upload ownership",
        ),
    )
    op.add_column(
        "messages_attachments",
        sa.Column(
            "expires_at",
            sa.DateTime(),
            nullable=True,
            comment="Unsent private uploads expire and must be cleaned up",
        ),
    )
    op.create_index(
        "idx_msg_attachment_staged_expiry",
        "messages_attachments",
        ["uploaded_by_member_id", "message_id", "expires_at"],
    )


def downgrade() -> None:
    op.drop_index("idx_msg_attachment_staged_expiry", table_name="messages_attachments")
    op.drop_column("messages_attachments", "expires_at")
    op.drop_column("messages_attachments", "uploaded_by_member_id")
