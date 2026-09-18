"""Goal 6 scheduled staged-attachment reaper tests.

The reaper is an internal, bounded, idempotent operation rather than an HTTP route.
These tests drive it directly against the sanitized disposable schema with synthetic
rows: expiry selection, linked/fresh preservation, batching, idempotency, storage
failure counting, the missing-storage guard, and the lock race against a concurrent
message link.
"""

from __future__ import annotations

import threading
import time
from collections.abc import Iterator
from datetime import UTC, datetime, timedelta
from pathlib import Path
from typing import cast

import pytest
from sqlalchemy import Engine, Table, select
from sqlalchemy.orm import Session

from app.integrations.uploads import ChatAttachmentStorage
from app.models.generated import Messages, MessagesAttachments, MessageThreads, ThreadParticipants
from app.services.chat import ChatAttachmentReapResult, ChatError, ChatService
from app.tasks.chat_attachment_reaper import reap_expired_chat_attachments
from tests.test_auth_integration import AuthHarness
from tests.test_auth_integration import auth_harness as _shared_auth_harness

MESSAGES_TABLE: Table = cast(Table, Messages.__table__)
ATTACHMENTS_TABLE: Table = cast(Table, MessagesAttachments.__table__)
THREADS_TABLE: Table = cast(Table, MessageThreads.__table__)
PARTICIPANTS_TABLE: Table = cast(Table, ThreadParticipants.__table__)


@pytest.fixture(name="auth_harness")
def _auth_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[AuthHarness]:
    """Delegate to the shared auth harness generator without shadowing its name."""
    yield from _shared_auth_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


def _now() -> datetime:
    return datetime.now(UTC).replace(tzinfo=None)


def _make_thread(engine: Engine, owner_id: int, title: str) -> int:
    now = _now()
    with engine.begin() as connection:
        key = connection.execute(
            THREADS_TABLE.insert().values(
                type="group",
                title=title,
                created_by=owner_id,
                category="community",
                attachment_enabled=1,
                audio_enabled=0,
                created_at=now,
            )
        ).inserted_primary_key
        assert key is not None
        thread_id = int(key[0])
        connection.execute(
            PARTICIPANTS_TABLE.insert().values(
                thread_id=thread_id,
                member_id=owner_id,
                role="admin",
                joined_at=now,
                created_at=now,
            )
        )
    return thread_id


def _make_message(engine: Engine, thread_id: int, sender_id: int) -> int:
    with engine.begin() as connection:
        key = connection.execute(
            MESSAGES_TABLE.insert().values(
                thread_id=thread_id,
                sender_member_id=sender_id,
                body="synthetic",
                message_type="file",
                created_at=_now(),
            )
        ).inserted_primary_key
        assert key is not None
        return int(key[0])


def _stage_attachment(
    engine: Engine,
    upload_root: Path,
    *,
    thread_id: int,
    owner_id: int,
    name: str,
    expires_at: datetime | None,
    message_id: int | None = None,
    storage_path: str | None = None,
    write_file: bool = True,
) -> int:
    relative = f"chat/{name}"
    if write_file:
        target = upload_root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(b"synthetic-bytes")
    with engine.begin() as connection:
        key = connection.execute(
            ATTACHMENTS_TABLE.insert().values(
                thread_id=thread_id,
                message_id=message_id,
                uploaded_by_member_id=owner_id,
                kind="file",
                file_name=name,
                mime_type="application/octet-stream",
                size_in_bytes=15,
                storage_path=relative if storage_path is None else storage_path,
                created_at=_now(),
                expires_at=expires_at,
            )
        ).inserted_primary_key
        assert key is not None
        return int(key[0])


def _attachment_row(engine: Engine, attachment_id: int) -> dict[str, object] | None:
    with engine.connect() as connection:
        row = (
            connection.execute(
                select(MessagesAttachments).where(MessagesAttachments.id == attachment_id)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None


def _purge_threads(engine: Engine, thread_ids: list[int]) -> None:
    if not thread_ids:
        return
    with engine.begin() as connection:
        connection.execute(
            ATTACHMENTS_TABLE.delete().where(MessagesAttachments.thread_id.in_(thread_ids))
        )
        connection.execute(MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids)))
        connection.execute(
            PARTICIPANTS_TABLE.delete().where(ThreadParticipants.thread_id.in_(thread_ids))
        )
        connection.execute(THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids)))


def _reap(harness: AuthHarness, *, limit: int = 200) -> ChatAttachmentReapResult:
    with Session(harness.engine) as session:
        storage = ChatAttachmentStorage(harness.settings.upload_root)
        return ChatService(session, storage).reap_expired_staged_attachments(limit=limit)


@pytest.mark.integration
def test_scheduled_task_entry_point_reaps_and_is_idempotent(auth_harness: AuthHarness) -> None:
    """The deployed scheduler entry point wires settings, storage, and disposal."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Task")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap task")
    try:
        attachment_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="task.bin",
            expires_at=_now() - timedelta(minutes=1),
        )
        first = reap_expired_chat_attachments(auth_harness.settings, limit=10)
        second = reap_expired_chat_attachments(auth_harness.settings, limit=10)
        assert (first.candidates, first.deleted_rows, first.deleted_files) == (1, 1, 1)
        assert _attachment_row(auth_harness.engine, attachment_id) is None
        assert (second.candidates, second.deleted_rows) == (0, 0)
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_deletes_only_expired_unsent_attachments(auth_harness: AuthHarness) -> None:
    """Expired staged rows and files go; fresh staged and linked rows stay."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Owner")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap selection")
    message_id = _make_message(auth_harness.engine, thread_id, owner_id)
    try:
        expired_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="expired.bin",
            expires_at=_now() - timedelta(minutes=5),
        )
        fresh_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="fresh.bin",
            expires_at=_now() + timedelta(hours=1),
        )
        linked_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="linked.bin",
            expires_at=None,
            message_id=message_id,
        )

        report = _reap(auth_harness)

        assert report.candidates == 1
        assert report.deleted_rows == 1
        assert report.deleted_files == 1
        assert report.file_failures == 0
        assert _attachment_row(auth_harness.engine, expired_id) is None
        assert not (auth_harness.settings.upload_root / "chat" / "expired.bin").exists()
        assert _attachment_row(auth_harness.engine, fresh_id) is not None
        assert (auth_harness.settings.upload_root / "chat" / "fresh.bin").exists()
        assert _attachment_row(auth_harness.engine, linked_id) is not None
        assert (auth_harness.settings.upload_root / "chat" / "linked.bin").exists()
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_is_bounded_and_idempotent(auth_harness: AuthHarness) -> None:
    """A batch limit caps each pass and a drained reaper is a no-op."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Batch")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap batching")
    try:
        ids = [
            _stage_attachment(
                auth_harness.engine,
                auth_harness.settings.upload_root,
                thread_id=thread_id,
                owner_id=owner_id,
                name=f"batch-{index}.bin",
                expires_at=_now() - timedelta(minutes=10 - index),
            )
            for index in range(3)
        ]

        first = _reap(auth_harness, limit=2)
        second = _reap(auth_harness, limit=2)
        third = _reap(auth_harness, limit=2)

        assert (first.candidates, first.deleted_rows) == (2, 2)
        assert (second.candidates, second.deleted_rows) == (1, 1)
        assert (third.candidates, third.deleted_rows) == (0, 0)
        assert all(
            _attachment_row(auth_harness.engine, attachment_id) is None for attachment_id in ids
        )
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_rejects_out_of_range_limit_without_touching_rows(
    auth_harness: AuthHarness,
) -> None:
    """The batch bound is enforced before any row is selected or deleted."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Bounds")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap bounds")
    try:
        attachment_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="bounded.bin",
            expires_at=_now() - timedelta(minutes=1),
        )
        for bad_limit in (0, 501):
            with pytest.raises(ChatError) as excinfo:
                _reap(auth_harness, limit=bad_limit)
            assert excinfo.value.http_status == 422
            assert excinfo.value.code == "chat_reaper_limit_invalid"
        assert _attachment_row(auth_harness.engine, attachment_id) is not None
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_requires_configured_storage_before_deleting_rows(
    auth_harness: AuthHarness,
) -> None:
    """Without storage the operation refuses rather than orphaning private files."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Storage")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap storage")
    try:
        attachment_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="storage-guard.bin",
            expires_at=_now() - timedelta(minutes=1),
        )
        with (
            Session(auth_harness.engine) as session,
            pytest.raises(RuntimeError, match="storage was not configured"),
        ):
            ChatService(session, None).reap_expired_staged_attachments()
        assert _attachment_row(auth_harness.engine, attachment_id) is not None
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_tolerates_missing_files_and_flags_malformed_paths(
    auth_harness: AuthHarness,
) -> None:
    """An already-missing file is clean; an unvalidated stored path is counted."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Files")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap file faults")
    try:
        missing_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="missing.bin",
            expires_at=_now() - timedelta(minutes=2),
            write_file=False,
        )
        malformed_id = _stage_attachment(
            auth_harness.engine,
            auth_harness.settings.upload_root,
            thread_id=thread_id,
            owner_id=owner_id,
            name="malformed.bin",
            expires_at=_now() - timedelta(minutes=1),
            storage_path="../../etc/passwd",
        )

        report = _reap(auth_harness)

        assert report.deleted_rows == 2
        assert report.deleted_files == 0
        assert report.file_failures == 1
        assert _attachment_row(auth_harness.engine, missing_id) is None
        assert _attachment_row(auth_harness.engine, malformed_id) is None
    finally:
        _purge_threads(auth_harness.engine, [thread_id])


@pytest.mark.integration
def test_reaper_skips_an_attachment_linked_by_a_concurrent_send(
    auth_harness: AuthHarness,
) -> None:
    """Row locks keep a send and the sweep from deleting a just-linked upload."""
    owner_id, _email, _ = auth_harness.create_user(fullname="Synthetic Reaper Race")
    thread_id = _make_thread(auth_harness.engine, owner_id, "Synthetic reap race")
    message_id = _make_message(auth_harness.engine, thread_id, owner_id)
    attachment_id = _stage_attachment(
        auth_harness.engine,
        auth_harness.settings.upload_root,
        thread_id=thread_id,
        owner_id=owner_id,
        name="raced.bin",
        expires_at=_now() - timedelta(minutes=1),
    )
    locked = threading.Event()
    release = threading.Event()

    def _hold_and_link() -> None:
        with Session(auth_harness.engine) as session, session.begin():
            session.execute(
                ATTACHMENTS_TABLE.select()
                .where(MessagesAttachments.id == attachment_id)
                .with_for_update()
            )
            locked.set()
            release.wait(timeout=10)
            session.execute(
                ATTACHMENTS_TABLE.update()
                .where(MessagesAttachments.id == attachment_id)
                .values(message_id=message_id, expires_at=None)
            )

    linker = threading.Thread(target=_hold_and_link)
    linker.start()
    try:
        assert locked.wait(timeout=10)
        # Let the locked send commit; the sweep must then re-read the linked row and skip it.
        release.set()
        time.sleep(0.1)
        report = _reap(auth_harness)
        linker.join(timeout=10)
        assert not linker.is_alive()
        assert report.candidates == 0
        row = _attachment_row(auth_harness.engine, attachment_id)
        assert row is not None
        assert row["message_id"] == message_id
    finally:
        release.set()
        linker.join(timeout=10)
        _purge_threads(auth_harness.engine, [thread_id])
