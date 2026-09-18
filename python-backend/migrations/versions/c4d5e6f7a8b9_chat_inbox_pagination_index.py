"""index the bounded V2 chat inbox and unread aggregates

Revision ID: c4d5e6f7a8b9
Revises: b2c3d4e5f6a7
Create Date: 2026-09-18
"""

from collections.abc import Sequence

from alembic import op

revision: str = "c4d5e6f7a8b9"
down_revision: str | Sequence[str] | None = "b2c3d4e5f6a7"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Add a covering member-first index for the paged inbox and unread counts.

    `scripts/chat_retrieval_probe.py` measured the four V2 retrieval query shapes
    against a disposable schema holding 3,400 participant rows and 100,000
    messages.  Per-thread message paging already used `idx_thread_id`, but the
    member-scoped inbox aggregates read `thread_participants` by row and sorted a
    temporary table.  This index serves that member filter, the `left_at IS NULL`
    activity predicate, and the pinned-first ordering, and makes the bounded
    thread count an index-only scan.
    """
    op.create_index(
        "idx_thread_participants_inbox",
        "thread_participants",
        ["member_id", "left_at", "is_pinned"],
    )


def downgrade() -> None:
    op.drop_index("idx_thread_participants_inbox", table_name="thread_participants")
