"""SQL access for the reviewed V2 message tables only."""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Any, cast

from sqlalchemy import CursorResult, Table, delete, func, select, update
from sqlalchemy.dialects.mysql import insert as mysql_insert
from sqlalchemy.orm import Session

from app.models.generated import (
    Messages,
    MessagesAttachments,
    MessageThreads,
    ThreadParticipants,
    Users,
)

THREADS_TABLE: Table = cast(Table, MessageThreads.__table__)
MESSAGES_TABLE: Table = cast(Table, Messages.__table__)
PARTICIPANTS_TABLE: Table = cast(Table, ThreadParticipants.__table__)
ATTACHMENTS_TABLE: Table = cast(Table, MessagesAttachments.__table__)


class ChatRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _mapping(result: Any) -> dict[str, Any]:
        """Flatten ORM entities so service code receives stable reviewed column keys."""
        payload = dict(result)
        for key, value in tuple(payload.items()):
            table = getattr(value, "__table__", None)
            if table is not None:
                payload.pop(key)
                payload.update({column.key: getattr(value, column.key) for column in table.columns})
        return payload

    @classmethod
    def _row(cls, result: Any) -> dict[str, Any] | None:
        return cls._mapping(result) if result is not None else None

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(Users.id, Users.active).where(Users.id == user_id).with_for_update().limit(1)
            )
            .mappings()
            .first()
        )

    def active_users(self, user_ids: set[int]) -> set[int]:
        if not user_ids:
            return set()
        return set(
            self._session.scalars(
                select(Users.id).where(Users.id.in_(user_ids), Users.active == 1)
            ).all()
        )

    def lock_participant(self, thread_id: int, member_id: int) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(ThreadParticipants)
                .where(
                    ThreadParticipants.thread_id == thread_id,
                    ThreadParticipants.member_id == member_id,
                )
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )

    def list_thread_rows(self, member_id: int, limit: int, offset: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(MessageThreads, ThreadParticipants.is_pinned)
                .join(ThreadParticipants, ThreadParticipants.thread_id == MessageThreads.id)
                .where(
                    ThreadParticipants.member_id == member_id, ThreadParticipants.left_at.is_(None)
                )
                .order_by(
                    ThreadParticipants.is_pinned.desc(),
                    MessageThreads.last_message_at.desc(),
                    MessageThreads.id.desc(),
                )
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in rows]

    def thread_total(self, member_id: int) -> int:
        """Count the member's active threads without transferring their rows."""
        total = self._session.scalar(
            select(func.count(ThreadParticipants.id)).where(
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
            )
        )
        return int(total or 0)

    def participant_rows_for_threads(
        self, thread_ids: list[int], member_id: int
    ) -> dict[int, dict[str, Any]]:
        """Load the actor's own membership rows for a bounded thread page in one query."""
        if not thread_ids:
            return {}
        rows = (
            self._session.execute(
                select(ThreadParticipants).where(
                    ThreadParticipants.thread_id.in_(thread_ids),
                    ThreadParticipants.member_id == member_id,
                    ThreadParticipants.left_at.is_(None),
                )
            )
            .mappings()
            .all()
        )
        memberships: dict[int, dict[str, Any]] = {}
        for row in rows:
            flattened = self._mapping(row)
            memberships[int(flattened["thread_id"])] = flattened
        return memberships

    def lock_thread(self, thread_id: int) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(MessageThreads)
                .where(MessageThreads.id == thread_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )

    def participants(self, thread_id: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(
                    ThreadParticipants.member_id,
                    ThreadParticipants.role,
                    ThreadParticipants.joined_at,
                    ThreadParticipants.last_read_message_id,
                    ThreadParticipants.last_delivered_message_id,
                    Users.fullname,
                    Users.avatar,
                    Users.graduation_year,
                    Users.city,
                )
                .join(Users, Users.id == ThreadParticipants.member_id)
                .where(
                    ThreadParticipants.thread_id == thread_id, ThreadParticipants.left_at.is_(None)
                )
                .order_by(ThreadParticipants.id.asc())
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in rows]

    def participants_for_threads(self, thread_ids: list[int]) -> dict[int, list[dict[str, Any]]]:
        """Group an inbox page's participants so listing stays a fixed query count."""
        if not thread_ids:
            return {}
        rows = (
            self._session.execute(
                select(
                    ThreadParticipants.thread_id,
                    ThreadParticipants.member_id,
                    ThreadParticipants.role,
                    ThreadParticipants.joined_at,
                    ThreadParticipants.last_read_message_id,
                    ThreadParticipants.last_delivered_message_id,
                    Users.fullname,
                    Users.avatar,
                    Users.graduation_year,
                    Users.city,
                )
                .join(Users, Users.id == ThreadParticipants.member_id)
                .where(
                    ThreadParticipants.thread_id.in_(thread_ids),
                    ThreadParticipants.left_at.is_(None),
                )
                .order_by(ThreadParticipants.thread_id.asc(), ThreadParticipants.id.asc())
            )
            .mappings()
            .all()
        )
        grouped: dict[int, list[dict[str, Any]]] = {}
        for row in rows:
            flattened = self._mapping(row)
            grouped.setdefault(int(flattened["thread_id"]), []).append(flattened)
        return grouped

    def messages(self, thread_id: int, limit: int, offset: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(Messages, Users.fullname.label("sender_name"))
                .join(Users, Users.id == Messages.sender_member_id)
                .where(Messages.thread_id == thread_id)
                .order_by(Messages.id.desc())
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in reversed(rows)]

    def attachments(self, message_ids: list[int]) -> dict[int, list[dict[str, object]]]:
        if not message_ids:
            return {}
        rows = (
            self._session.execute(
                select(MessagesAttachments).where(MessagesAttachments.message_id.in_(message_ids))
            )
            .mappings()
            .all()
        )
        grouped: dict[int, list[dict[str, object]]] = {}
        for row in rows:
            payload = self._mapping(row)
            message_id = payload.get("message_id")
            if message_id is not None:
                grouped.setdefault(int(message_id), []).append(payload)
        return grouped

    def lock_attachment(self, attachment_id: int) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(MessagesAttachments)
                .where(MessagesAttachments.id == attachment_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )

    def lock_attachments(self, attachment_ids: list[int]) -> list[dict[str, Any]]:
        if not attachment_ids:
            return []
        rows = (
            self._session.execute(
                select(MessagesAttachments)
                .where(MessagesAttachments.id.in_(attachment_ids))
                .order_by(MessagesAttachments.id.asc())
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in rows]

    def create_attachment(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(ATTACHMENTS_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Attachment insert did not return a primary key")
        return int(key[0])

    def link_attachments(self, attachment_ids: list[int], message_id: int) -> None:
        if attachment_ids:
            self._session.execute(
                update(MessagesAttachments)
                .where(MessagesAttachments.id.in_(attachment_ids))
                .values(message_id=message_id, expires_at=None)
            )

    def expired_staged_attachments(self, now: datetime, limit: int) -> list[dict[str, Any]]:
        """Lock a bounded batch of unsent uploads whose staging window has closed.

        `SELECT ... FOR UPDATE` makes the sweep race-safe: a concurrent send that
        holds the row lock commits first, so this re-reads the linked row and drops
        it from the batch, while a sweep that locks first forces the send to observe
        the deleted row.  The query is driven by `idx_msg_attachment_staged_purge`
        (`message_id`, `expires_at`); the owner-scoped staged-expiry index cannot
        serve a global sweep because its leading column is the uploader.
        """
        rows = (
            self._session.execute(
                select(MessagesAttachments)
                .where(
                    MessagesAttachments.message_id.is_(None),
                    MessagesAttachments.expires_at.is_not(None),
                    MessagesAttachments.expires_at <= now,
                )
                .order_by(MessagesAttachments.expires_at.asc(), MessagesAttachments.id.asc())
                .limit(limit)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in rows]

    def delete_attachments(self, attachment_ids: list[int]) -> None:
        if attachment_ids:
            self._session.execute(
                delete(MessagesAttachments).where(MessagesAttachments.id.in_(attachment_ids))
            )

    def unread_count(self, thread_id: int, member_id: int, cursor: int | None) -> int:
        predicates = [
            Messages.thread_id == thread_id,
            Messages.sender_member_id != member_id,
            Messages.deleted_at.is_(None),
        ]
        if cursor is not None:
            predicates.append(Messages.id > cursor)
        return int(self._session.scalar(select(func.count(Messages.id)).where(*predicates)) or 0)

    def unread_counts(self, thread_ids: list[int], member_id: int) -> dict[int, int]:
        """Count unread messages per listed thread in one grouped query."""
        if not thread_ids:
            return {}
        rows = self._session.execute(
            select(Messages.thread_id, func.count(Messages.id).label("unread_count"))
            .join(
                ThreadParticipants,
                (ThreadParticipants.thread_id == Messages.thread_id)
                & (ThreadParticipants.member_id == member_id)
                & (ThreadParticipants.left_at.is_(None)),
            )
            .where(
                Messages.thread_id.in_(thread_ids),
                Messages.sender_member_id != member_id,
                Messages.deleted_at.is_(None),
                (
                    ThreadParticipants.last_read_message_id.is_(None)
                    | (Messages.id > ThreadParticipants.last_read_message_id)
                ),
            )
            .group_by(Messages.thread_id)
        ).all()
        return {int(thread_id): int(count) for thread_id, count in rows}

    def unread_summary(self, member_id: int) -> tuple[int, int]:
        """Count unread messages and unread threads across all active threads in one query."""
        row = self._session.execute(
            select(
                func.count(Messages.id),
                func.count(func.distinct(Messages.thread_id)),
            )
            .select_from(Messages)
            .join(
                ThreadParticipants,
                (ThreadParticipants.thread_id == Messages.thread_id)
                & (ThreadParticipants.member_id == member_id)
                & (ThreadParticipants.left_at.is_(None)),
            )
            .where(
                Messages.sender_member_id != member_id,
                Messages.deleted_at.is_(None),
                (
                    ThreadParticipants.last_read_message_id.is_(None)
                    | (Messages.id > ThreadParticipants.last_read_message_id)
                ),
            )
        ).one()
        return int(row[0] or 0), int(row[1] or 0)

    def latest_message_id(self, thread_id: int) -> int | None:
        value = self._session.scalar(
            select(Messages.id)
            .where(Messages.thread_id == thread_id)
            .order_by(Messages.id.desc())
            .limit(1)
        )
        return int(value) if value is not None else None

    def mark_read(self, thread_id: int, member_id: int, message_id: int) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id,
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
                (
                    ThreadParticipants.last_read_message_id.is_(None)
                    | (ThreadParticipants.last_read_message_id < message_id)
                ),
            )
            .values(last_read_message_id=message_id, last_delivered_message_id=message_id)
        )

    def mark_delivered(self, thread_id: int, member_id: int, message_id: int) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id,
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
                (
                    ThreadParticipants.last_delivered_message_id.is_(None)
                    | (ThreadParticipants.last_delivered_message_id < message_id)
                ),
            )
            .values(last_delivered_message_id=message_id)
        )

    def active_administrators(self, thread_id: int) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(ThreadParticipants)
                .where(
                    ThreadParticipants.thread_id == thread_id,
                    ThreadParticipants.left_at.is_(None),
                    ThreadParticipants.role == "admin",
                )
                .order_by(ThreadParticipants.joined_at.asc(), ThreadParticipants.id.asc())
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return [self._mapping(row) for row in rows]

    def first_active_member_except(
        self, thread_id: int, excluded_member_id: int
    ) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(ThreadParticipants)
                .where(
                    ThreadParticipants.thread_id == thread_id,
                    ThreadParticipants.member_id != excluded_member_id,
                    ThreadParticipants.left_at.is_(None),
                )
                .order_by(ThreadParticipants.joined_at.asc(), ThreadParticipants.id.asc())
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )

    def reactivate_participant(self, thread_id: int, member_id: int, now: datetime) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id, ThreadParticipants.member_id == member_id
            )
            .values(
                left_at=None,
                role="member",
                joined_at=now,
                is_pinned=0,
                last_read_message_id=None,
                last_delivered_message_id=None,
            )
        )

    def promote_administrator(self, thread_id: int, member_id: int) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id,
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
            )
            .values(role="admin")
        )

    def leave_participant(self, thread_id: int, member_id: int, now: datetime) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id,
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
            )
            .values(left_at=now, is_pinned=0)
        )

    def set_pinned(self, thread_id: int, member_id: int, pinned: bool) -> None:
        self._session.execute(
            update(ThreadParticipants)
            .where(
                ThreadParticipants.thread_id == thread_id,
                ThreadParticipants.member_id == member_id,
                ThreadParticipants.left_at.is_(None),
            )
            .values(is_pinned=1 if pinned else 0)
        )

    def lock_message(self, message_id: int) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(Messages).where(Messages.id == message_id).with_for_update().limit(1)
            )
            .mappings()
            .first()
        )

    def create_or_get_direct_thread(self, values: dict[str, Any]) -> tuple[int, bool]:
        """Atomically create a direct thread or return the direct-key winner.

        ``INSERT IGNORE`` is deliberate: its affected-row count distinguishes
        a new row from a direct-key collision even with the MySQL
        ``CLIENT_FOUND_ROWS`` option enabled. On the collision path, the
        winner has committed before the current-key read obtains its lock.
        This avoids the gap-lock deadlock caused by a separate
        ``SELECT ... FOR UPDATE`` followed by ``INSERT``.
        """
        statement = mysql_insert(THREADS_TABLE).values(**values).prefix_with("IGNORE")
        result: CursorResult[Any] = cast(CursorResult[Any], self._session.execute(statement))
        if result.rowcount == 1 and result.lastrowid is not None:
            return int(result.lastrowid), True
        direct_key = str(values["direct_key"])
        row = self._row(
            self._session.execute(
                select(MessageThreads)
                .where(MessageThreads.type == "direct", MessageThreads.direct_key == direct_key)
                .limit(1)
            )
            .mappings()
            .first()
        )
        if row is None:
            raise RuntimeError("Direct-thread collision did not expose a winner")
        return int(row["id"]), False

    def create_thread(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(THREADS_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Thread insert did not return a primary key")
        return int(key[0])

    def add_participant(self, thread_id: int, member_id: int, role: str) -> None:
        self._session.execute(
            PARTICIPANTS_TABLE.insert().values(
                thread_id=thread_id,
                member_id=member_id,
                role=role,
                joined_at=datetime.now(UTC).replace(tzinfo=None),
                created_at=datetime.now(UTC).replace(tzinfo=None),
            )
        )

    def find_idempotent_message(
        self, thread_id: int, client_generated_id: str
    ) -> dict[str, Any] | None:
        return self._row(
            self._session.execute(
                select(Messages)
                .where(
                    Messages.thread_id == thread_id,
                    Messages.client_generated_id == client_generated_id,
                )
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )

    def create_message(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(MESSAGES_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Message insert did not return a primary key")
        return int(key[0])

    def update_thread_summary(
        self,
        thread_id: int,
        message_id: int,
        created_at: datetime,
        preview: str,
        sender_name: str,
        sender_id: int,
    ) -> None:
        self._session.execute(
            update(MessageThreads)
            .where(MessageThreads.id == thread_id)
            .values(
                last_message_id=message_id,
                last_message_at=created_at,
                last_message_preview=preview[:300],
                last_message_sender_name=sender_name[:150],
                last_message_sender_member_id=sender_id,
                updated_at=created_at,
            )
        )

    def soft_delete(self, message_id: int, now: datetime) -> None:
        self._session.execute(
            update(Messages)
            .where(Messages.id == message_id)
            .values(body="", deleted_at=now, updated_at=now)
        )
