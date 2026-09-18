"""Measure V2 chat inbox/message retrieval plans against a disposable database.

The probe mirrors the query shapes the chat repository executes, seeds a bounded
synthetic volume, prints `EXPLAIN` access plans plus wall-clock timings, and then
removes only the rows it created.  It refuses to run against anything that is not
a localhost database whose name contains "test", so it can never reach the PHP
production schema or a shared office database.
"""

from __future__ import annotations

import argparse
import json
import time
from collections.abc import Mapping, Sequence
from datetime import UTC, datetime, timedelta
from typing import Any, cast

from sqlalchemy import (
    CursorResult,
    Table,
    TextClause,
    bindparam,
    create_engine,
    delete,
    insert,
    select,
    text,
)
from sqlalchemy.engine import Connection, Dialect, make_url

from app.models.generated import (
    AlumniChapter,
    Messages,
    MessagesAttachments,
    MessageThreads,
    ThreadParticipants,
    Users,
)

CHAPTERS_TABLE: Table = cast(Table, AlumniChapter.__table__)
USERS_TABLE: Table = cast(Table, Users.__table__)
THREADS_TABLE: Table = cast(Table, MessageThreads.__table__)
PARTICIPANTS_TABLE: Table = cast(Table, ThreadParticipants.__table__)
MESSAGES_TABLE: Table = cast(Table, Messages.__table__)
ATTACHMENTS_TABLE: Table = cast(Table, MessagesAttachments.__table__)

PROBE_EMAIL_DOMAIN = "probe.invalid"
PROBE_CHAPTER_NAME = "Probe Synthetic Chapter"
PROBE_THREAD_TITLE_LIKE = "Probe thread %"
INBOX_PAGE = 100
THREAD_PAGE = 50
BATCH_SIZE = 5_000

INBOX_SQL = """
SELECT mt.id, mt.type, mt.title, mt.category, mt.last_message_id, mt.last_message_at,
       mt.last_message_preview, mt.last_message_sender_name, mt.last_message_sender_member_id,
       mt.attachment_enabled, mt.audio_enabled, mt.created_at, mt.updated_at, tp.is_pinned
FROM message_threads mt
JOIN thread_participants tp ON tp.thread_id = mt.id
WHERE tp.member_id = :member AND tp.left_at IS NULL
ORDER BY tp.is_pinned DESC, mt.last_message_at DESC, mt.id DESC
LIMIT :limit
"""

THREAD_TOTAL_SQL = """
SELECT COUNT(tp.id) FROM thread_participants tp
WHERE tp.member_id = :member AND tp.left_at IS NULL
"""

MESSAGE_PAGE_SQL = """
SELECT m.id, m.thread_id, m.sender_member_id, m.body, m.message_type,
       m.reply_to_message_id, m.client_generated_id, m.deleted_at, m.created_at,
       m.updated_at, u.fullname AS sender_name
FROM messages m
JOIN users u ON u.id = m.sender_member_id
WHERE m.thread_id = :thread
ORDER BY m.id DESC
LIMIT :limit
"""

UNREAD_COUNTS_SQL = """
SELECT m.thread_id, COUNT(m.id) FROM messages m
JOIN thread_participants tp
  ON tp.thread_id = m.thread_id
 AND tp.member_id = :member
 AND tp.left_at IS NULL
WHERE m.thread_id IN :thread_ids
  AND m.sender_member_id <> :member
  AND m.deleted_at IS NULL
  AND (tp.last_read_message_id IS NULL OR m.id > tp.last_read_message_id)
GROUP BY m.thread_id
"""

UNREAD_SUMMARY_SQL = """
SELECT COUNT(m.id), COUNT(DISTINCT m.thread_id) FROM messages m
JOIN thread_participants tp
  ON tp.thread_id = m.thread_id
 AND tp.member_id = :member
 AND tp.left_at IS NULL
WHERE m.sender_member_id <> :member
  AND m.deleted_at IS NULL
  AND (tp.last_read_message_id IS NULL OR m.id > tp.last_read_message_id)
"""

STAGED_PURGE_SQL = """
SELECT id, thread_id, storage_path, expires_at
FROM messages_attachments
WHERE message_id IS NULL
  AND expires_at IS NOT NULL
  AND expires_at <= :now
ORDER BY expires_at ASC, id ASC
LIMIT :limit
"""

BASE_USER_COLUMNS: dict[str, Any] = {
    "ip_address": "127.0.0.1",
    "password": "no-credential-is-stored-by-this-probe",
    "has_password": 0,
    "onboarding_completion": 0,
    "nick_name": "probe",
    "state": "Probe",
    "country": "Probe",
    "created_on": 0,
    "active": 1,
    "user_role": "alumni",
    "userAccessCode": "",
    "profile_status": "private",
    "voucher": "",
    "resetKey": "",
}


class ProbeRefusedError(Exception):
    """The requested target is not an approved disposable database."""


def _inserted_id(result: CursorResult[Any]) -> int:
    """Return the generated primary key of a single-row probe insert."""
    keys = result.inserted_primary_key
    if keys is None:
        raise RuntimeError("probe insert did not return a primary key")
    return int(keys[0])


def _assert_disposable(database_url: str) -> None:
    url = make_url(database_url)
    host = (url.host or "").casefold()
    database = (url.database or "").casefold()
    if host not in {"127.0.0.1", "localhost", "::1"}:
        raise ProbeRefusedError(f"refusing non-localhost database host {host!r}")
    if "test" not in database:
        raise ProbeRefusedError(f"refusing database name {database!r} without 'test'")


def _purge(connection: Connection) -> None:
    """Remove only synthetic rows, including residue from an interrupted run."""
    probe_thread_ids = select(THREADS_TABLE.c.id).where(
        THREADS_TABLE.c.title.like(PROBE_THREAD_TITLE_LIKE)
    )
    connection.execute(
        delete(ATTACHMENTS_TABLE).where(ATTACHMENTS_TABLE.c.thread_id.in_(probe_thread_ids))
    )
    connection.execute(
        delete(MESSAGES_TABLE).where(MESSAGES_TABLE.c.thread_id.in_(probe_thread_ids))
    )
    connection.execute(
        delete(PARTICIPANTS_TABLE).where(PARTICIPANTS_TABLE.c.thread_id.in_(probe_thread_ids))
    )
    connection.execute(
        delete(THREADS_TABLE).where(THREADS_TABLE.c.title.like(PROBE_THREAD_TITLE_LIKE))
    )
    connection.execute(
        delete(USERS_TABLE).where(USERS_TABLE.c.email.like(f"%@{PROBE_EMAIL_DOMAIN}"))
    )
    connection.execute(
        delete(CHAPTERS_TABLE).where(CHAPTERS_TABLE.c.chapter_name == PROBE_CHAPTER_NAME)
    )


def _seed(
    connection: Connection, threads: int, messages_per_thread: int, other_threads: int
) -> dict[str, Any]:
    """Insert synthetic chat rows and return the identifiers needed for cleanup."""
    _purge(connection)
    chapter_id = _inserted_id(
        connection.execute(
            insert(CHAPTERS_TABLE).values(
                chapter_name=PROBE_CHAPTER_NAME, location="Probe", is_enabled=1
            )
        )
    )

    user_ids: list[int] = []
    for index in range(2):
        username = f"probe-user-{index}"
        user_ids.append(
            _inserted_id(
                connection.execute(
                    insert(USERS_TABLE).values(
                        **BASE_USER_COLUMNS,
                        chapter_id=chapter_id,
                        username=username,
                        email=f"{username}@{PROBE_EMAIL_DOMAIN}",
                        fullname=f"Probe User {index}",
                    )
                )
            )
        )
    member_id, other_id = user_ids

    thread_ids = [
        _inserted_id(
            connection.execute(
                insert(THREADS_TABLE).values(
                    type="group",
                    title=f"Probe thread {index}",
                    created_by=member_id,
                    category="community",
                )
            )
        )
        for index in range(threads)
    ]
    connection.execute(
        insert(PARTICIPANTS_TABLE),
        [
            {
                "thread_id": thread_id,
                "member_id": user_id,
                "role": "admin" if user_id == member_id else "member",
                "last_read_message_id": 1,
            }
            for thread_id in thread_ids
            for user_id in (member_id, other_id)
        ],
    )

    batch: list[dict[str, Any]] = []
    for thread_id in thread_ids:
        batch.extend(
            {
                "thread_id": thread_id,
                "sender_member_id": other_id,
                "body": "synthetic probe payload",
                "message_type": "text",
            }
            for _ in range(messages_per_thread)
        )
        if len(batch) >= BATCH_SIZE:
            connection.execute(insert(MESSAGES_TABLE), batch)
            batch = []
    if batch:
        connection.execute(insert(MESSAGES_TABLE), batch)

    # Other members' traffic keeps the probe member's share of thread_participants
    # realistic, so the optimizer sees a selective member filter instead of a
    # synthetic single-owner table.
    if other_threads:
        foreign_thread_ids = [
            _inserted_id(
                connection.execute(
                    insert(THREADS_TABLE).values(
                        type="group",
                        title=f"Probe thread other {index}",
                        created_by=other_id,
                        category="community",
                    )
                )
            )
            for index in range(other_threads)
        ]
        connection.execute(
            insert(PARTICIPANTS_TABLE),
            [
                {
                    "thread_id": thread_id,
                    "member_id": other_id,
                    "role": "admin",
                    "last_read_message_id": None,
                }
                for thread_id in foreign_thread_ids
            ],
        )
        connection.execute(
            insert(MESSAGES_TABLE),
            [
                {
                    "thread_id": thread_id,
                    "sender_member_id": other_id,
                    "body": "synthetic bystander payload",
                    "message_type": "text",
                }
                for thread_id in foreign_thread_ids
            ],
        )
        connection.execute(
            THREADS_TABLE.update()
            .where(THREADS_TABLE.c.id.in_(foreign_thread_ids))
            .values(last_message_at=text("NOW()"))
        )

    # Denormalize the newest message and activity timestamp the way the application
    # does, so the measured inbox ordering matches a live mailbox.
    newest = (
        select(MESSAGES_TABLE.c.id)
        .where(MESSAGES_TABLE.c.thread_id == THREADS_TABLE.c.id)
        .order_by(MESSAGES_TABLE.c.id.desc())
        .limit(1)
        .scalar_subquery()
    )
    connection.execute(
        THREADS_TABLE.update()
        .where(THREADS_TABLE.c.id.in_(thread_ids))
        .values(last_message_id=newest, last_message_at=text("NOW()"))
    )

    # Half of the staged uploads are already expired, so the purge sweep has both
    # matching and non-matching rows and the optimizer must use the expiry range.
    staged_now = datetime.now(UTC).replace(tzinfo=None)
    connection.execute(
        insert(ATTACHMENTS_TABLE),
        [
            {
                "thread_id": thread_id,
                "message_id": None,
                "uploaded_by_member_id": member_id,
                "kind": "file",
                "file_name": f"probe-stage-{index}.bin",
                "mime_type": "application/octet-stream",
                "size_in_bytes": 1,
                "storage_path": f"chat/probe-stage-{index}.bin",
                "expires_at": (
                    staged_now - timedelta(hours=1)
                    if index % 2 == 0
                    else staged_now + timedelta(hours=1)
                ),
            }
            for index, thread_id in enumerate(thread_ids)
        ],
    )
    return {"member_id": member_id, "other_id": other_id, "thread_ids": thread_ids}


def _literal_sql(statement: TextClause, dialect: Dialect, values: Mapping[str, Any]) -> str:
    """Render one probe statement with its probe-generated literal values."""
    bound = statement.bindparams(
        *(
            bindparam(key, value=value, expanding=isinstance(value, (list, tuple)))
            for key, value in values.items()
        )
    )
    return str(
        bound.compile(
            dialect=dialect,
            compile_kwargs={"literal_binds": True, "render_postcompile": True},
        )
    )


def _explain(connection: Connection, statement: TextClause, values: Mapping[str, Any]) -> list[str]:
    """Ask MariaDB for the access plan of a literal probe statement."""
    rendered = _literal_sql(statement, connection.dialect, values)
    plan = text(f"EXPLAIN {rendered}")
    return [
        " ".join(
            (
                f"table={row.table}",
                f"type={row.type}",
                f"key={row.key or '-'}",
                f"possible={row.possible_keys or '-'}",
                f"rows={row.rows}",
                f"extra={row.Extra or '-'}",
            )
        )
        for row in connection.execute(plan).all()
    ]


def _timed(
    connection: Connection, statement: TextClause, values: Mapping[str, Any], runs: int = 3
) -> float:
    best = float("inf")
    for _ in range(runs):
        start = time.perf_counter()
        connection.execute(statement, dict(values)).all()
        best = min(best, (time.perf_counter() - start) * 1000)
    return round(best, 3)


def probe(
    database_url: str, threads: int, messages_per_thread: int, other_threads: int
) -> dict[str, Any]:
    """Seed, measure, clean up, and report against one disposable database."""
    _assert_disposable(database_url)
    engine = create_engine(database_url, pool_pre_ping=True)
    seeded: dict[str, Any] = {}
    try:
        with engine.begin() as connection:
            seeded = _seed(connection, threads, messages_per_thread, other_threads)
        member = int(seeded["member_id"])
        page_ids = [int(value) for value in seeded["thread_ids"][:INBOX_PAGE]]
        probes: dict[str, tuple[TextClause, dict[str, Any]]] = {
            "inbox_page": (text(INBOX_SQL), {"member": member, "limit": INBOX_PAGE + 1}),
            "thread_total": (text(THREAD_TOTAL_SQL), {"member": member}),
            "message_page": (
                text(MESSAGE_PAGE_SQL),
                {"thread": int(seeded["thread_ids"][0]), "limit": THREAD_PAGE},
            ),
            "unread_counts": (text(UNREAD_COUNTS_SQL), {"member": member, "thread_ids": page_ids}),
            "unread_summary": (text(UNREAD_SUMMARY_SQL), {"member": member}),
            "staged_purge": (
                text(STAGED_PURGE_SQL),
                {"now": datetime.now(UTC).replace(tzinfo=None), "limit": 100},
            ),
        }
        report: dict[str, Any] = {
            "database": make_url(database_url).database,
            "threads": threads,
            "messages_per_thread": messages_per_thread,
            "other_threads": other_threads,
            "total_messages": threads * messages_per_thread,
            "queries": {},
        }
        with engine.connect() as connection:
            for name, (statement, values) in probes.items():
                report["queries"][name] = {
                    "explain": _explain(connection, statement, values),
                    "best_ms": _timed(connection, statement, values),
                }
        return report
    finally:
        if seeded:
            with engine.begin() as connection:
                _purge(connection)
        engine.dispose()


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Parse the disposable target and synthetic volume."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--database-url", required=True, help="disposable localhost test URL")
    parser.add_argument("--threads", type=int, default=200)
    parser.add_argument("--messages-per-thread", type=int, default=500)
    parser.add_argument(
        "--other-threads",
        type=int,
        default=3_000,
        help="threads owned by another member, so the member filter stays selective",
    )
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    """Run the probe and print only aggregate, non-identifying output."""
    arguments = parse_args(argv)
    if arguments.threads < 1 or arguments.messages_per_thread < 1 or arguments.other_threads < 0:
        print(json.dumps({"status": "refused", "reason": "volume must be positive"}))
        return 2
    try:
        report = probe(
            arguments.database_url,
            arguments.threads,
            arguments.messages_per_thread,
            arguments.other_threads,
        )
    except ProbeRefusedError as exc:
        print(json.dumps({"status": "refused", "reason": str(exc)}, sort_keys=True))
        return 2
    print(json.dumps({"status": "ok", **report}, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
