"""retain immutable event-registration form history and answer display snapshots

Revision ID: 9d9d1f2a7c31
Revises: 7250164972b6
Create Date: 2026-09-16
"""

from collections.abc import Sequence

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects import mysql

revision: str = "9d9d1f2a7c31"
down_revision: str | Sequence[str] | None = "7250164972b6"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Add only additive compatibility storage; no legacy rows are rewritten."""
    op.add_column(
        "event_registration_forms",
        sa.Column("source_form_id", sa.String(length=128), nullable=True),
    )
    op.create_index(
        "uq_erf_source_form_id",
        "event_registration_forms",
        ["source_form_id"],
        unique=True,
    )
    op.add_column(
        "event_registration_form_questions",
        sa.Column("source_question_id", sa.String(length=128), nullable=True),
    )
    op.add_column(
        "event_registration_form_questions",
        sa.Column("max_selections", mysql.SMALLINT(unsigned=True), nullable=True),
    )
    op.create_index(
        "uq_erfq_source_question_id",
        "event_registration_form_questions",
        ["source_question_id"],
        unique=True,
    )
    op.add_column(
        "event_registration_answers",
        sa.Column("form_name_snapshot", sa.String(length=255), nullable=True),
    )
    op.add_column(
        "event_registration_answers",
        sa.Column("placeholder_snapshot", sa.String(length=255), nullable=True),
    )
    op.add_column(
        "event_registration_answers",
        sa.Column(
            "options_json_snapshot",
            mysql.LONGTEXT(charset="utf8mb4", collation="utf8mb4_bin"),
            nullable=True,
        ),
    )
    op.add_column(
        "event_registration_answers",
        sa.Column("max_selections_snapshot", mysql.SMALLINT(unsigned=True), nullable=True),
    )
    op.create_table(
        "event_registration_form_versions",
        sa.Column("id", mysql.BIGINT(unsigned=True), primary_key=True, autoincrement=True),
        sa.Column("form_id", mysql.INTEGER(unsigned=True), nullable=False),
        sa.Column("version", mysql.SMALLINT(unsigned=True), nullable=False),
        sa.Column("name_snapshot", sa.String(length=255), nullable=False),
        sa.Column("description_snapshot", sa.Text(), nullable=True),
        sa.Column("sort_order_snapshot", mysql.SMALLINT(unsigned=True), nullable=False),
        sa.Column(
            "questions_json",
            mysql.LONGTEXT(charset="utf8mb4", collation="utf8mb4_bin"),
            nullable=False,
        ),
        sa.Column("source_version_id", sa.String(length=128), nullable=True),
        sa.Column("created_by", mysql.INTEGER(unsigned=True), nullable=False),
        sa.Column(
            "created_at", sa.DateTime(), nullable=False, server_default=sa.text("CURRENT_TIMESTAMP")
        ),
        sa.ForeignKeyConstraint(["form_id"], ["event_registration_forms.id"], ondelete="CASCADE"),
        sa.UniqueConstraint("form_id", "version", name="uq_erfv_form_version"),
    )
    op.create_index("idx_erfv_form_id", "event_registration_form_versions", ["form_id"])
    op.create_index(
        "uq_erfv_source_version_id",
        "event_registration_form_versions",
        ["source_version_id"],
        unique=True,
    )


def downgrade() -> None:
    """Remove only the additive migration objects in reverse dependency order."""
    op.drop_index("uq_erfv_source_version_id", table_name="event_registration_form_versions")
    op.drop_index("idx_erfv_form_id", table_name="event_registration_form_versions")
    op.drop_table("event_registration_form_versions")
    op.drop_column("event_registration_answers", "max_selections_snapshot")
    op.drop_column("event_registration_answers", "options_json_snapshot")
    op.drop_column("event_registration_answers", "placeholder_snapshot")
    op.drop_column("event_registration_answers", "form_name_snapshot")
    op.drop_index("uq_erfq_source_question_id", table_name="event_registration_form_questions")
    op.drop_column("event_registration_form_questions", "max_selections")
    op.drop_column("event_registration_form_questions", "source_question_id")
    op.drop_index("uq_erf_source_form_id", table_name="event_registration_forms")
    op.drop_column("event_registration_forms", "source_form_id")
