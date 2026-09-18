"""index the global staged-attachment expiry sweep

Revision ID: d5e6f7a8b9c0
Revises: c4d5e6f7a8b9
Create Date: 2026-09-18
"""

from collections.abc import Sequence

from alembic import op

revision: str = "d5e6f7a8b9c0"
down_revision: str | Sequence[str] | None = "c4d5e6f7a8b9"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Add a purge-path index for the scheduled staged-attachment reaper.

    `scripts/chat_retrieval_probe.py` measures the reaper sweep
    (`message_id IS NULL AND expires_at <= :now`, oldest first).  The existing
    `idx_msg_attachment_staged_expiry` starts with `uploaded_by_member_id`, so it
    cannot serve a global sweep; this `(message_id, expires_at)` index seeks the
    unsent partition and returns rows in expiry order, and InnoDB appends the
    primary key so `ORDER BY expires_at, id` needs no filesort.
    """
    op.create_index(
        "idx_msg_attachment_staged_purge",
        "messages_attachments",
        ["message_id", "expires_at"],
    )


def downgrade() -> None:
    op.drop_index("idx_msg_attachment_staged_purge", table_name="messages_attachments")
