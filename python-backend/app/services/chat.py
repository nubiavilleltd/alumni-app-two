"""Current-account, participant-scoped V2 messaging use cases."""

from __future__ import annotations

import time
from dataclasses import dataclass
from datetime import UTC, datetime, timedelta
from pathlib import Path
from typing import Any

from sqlalchemy.exc import OperationalError
from sqlalchemy.orm import Session

from app.integrations.uploads import ChatAttachmentStorage, PreparedChatAttachment, StoredAvatar
from app.repositories.chat import ChatRepository
from app.schemas.chat import (
    ChatAddMemberRequest,
    ChatAttachment,
    ChatAttachmentResponse,
    ChatDeleteRequest,
    ChatDirectRequest,
    ChatGroupRequest,
    ChatLeaveGroupRequest,
    ChatMarkDeliveredRequest,
    ChatMarkReadRequest,
    ChatMessage,
    ChatMutationData,
    ChatMutationResponse,
    ChatPinThreadRequest,
    ChatSendRequest,
    ChatThread,
    ChatThreadRequest,
    ChatThreadResponse,
    ChatThreadsRequest,
    ChatThreadsResponse,
)


class ChatError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code, self.message, self.http_status = code, message, http_status


@dataclass(frozen=True, slots=True)
class ChatAttachmentReapResult:
    """Aggregate, non-identifying outcome of one bounded reaper batch."""

    candidates: int
    deleted_rows: int
    deleted_files: int
    file_failures: int
    limit: int
    server_time: datetime


class ChatService:
    def __init__(
        self, session: Session, attachment_storage: ChatAttachmentStorage | None = None
    ) -> None:
        self._session, self._repository = session, ChatRepository(session)
        self._attachment_storage = attachment_storage

    @staticmethod
    def _now() -> datetime:
        return datetime.now(UTC).replace(tzinfo=None)

    def _active_actor(self, actor_id: int) -> None:
        actor = self._repository.lock_actor(actor_id)
        if actor is None or not bool(actor.get("active")):
            raise ChatError("chat_actor_unavailable", "Authentication required", 401)

    def _participant(self, thread_id: int, actor_id: int) -> dict[str, Any]:
        participant = self._repository.lock_participant(thread_id, actor_id)
        if participant is None or participant.get("left_at") is not None:
            raise ChatError(
                "chat_membership_forbidden", "You are not a participant of this thread", 403
            )
        return participant

    def _group_administrator(self, thread: dict[str, Any], actor_id: int) -> None:
        if thread.get("type") != "group":
            raise ChatError("chat_group_required", "This operation requires a group thread", 422)
        participant = self._participant(int(thread["id"]), actor_id)
        if participant.get("role") != "admin":
            raise ChatError("chat_group_admin_required", "A group administrator is required", 403)

    def _message(
        self, row: dict[str, Any], attachments: dict[int, list[dict[str, object]]]
    ) -> ChatMessage:
        safe_attachments = [
            self._attachment(dict(attachment)).model_dump(mode="json", exclude_none=True)
            for attachment in attachments.get(int(row["id"]), [])
        ]
        return ChatMessage(
            **{key: value for key, value in row.items() if key in ChatMessage.model_fields},
            attachments=safe_attachments,
        )

    @staticmethod
    def _attachment(row: dict[str, Any]) -> ChatAttachment:
        attachment_id = int(row["id"])
        kind = getattr(row["kind"], "value", row["kind"])
        return ChatAttachment(
            attachment_id=attachment_id,
            thread_id=int(row["thread_id"]),
            kind=str(kind),
            file_name=str(row.get("file_name") or "attachment"),
            mime_type=str(row.get("mime_type") or "application/octet-stream"),
            size_in_bytes=int(row.get("size_in_bytes") or 0),
            duration_seconds=(
                int(row["duration_seconds"]) if row.get("duration_seconds") is not None else None
            ),
            download_path=f"/chat_api/v2_attachments/{attachment_id}",
        )

    def _thread(
        self,
        row: dict[str, Any],
        actor_id: int,
        include_messages: tuple[int, int] | None = None,
        *,
        participant: dict[str, Any] | None = None,
        participants: list[dict[str, Any]] | None = None,
        unread_count: int | None = None,
    ) -> ChatThread:
        thread_id = int(row["id"] if "id" in row else row["thread_id"])
        member_row = (
            participant if participant is not None else self._participant(thread_id, actor_id)
        )
        if participants is None:
            participants = self._repository.participants(thread_id)
        payload = {key: value for key, value in row.items() if key in ChatThread.model_fields}
        payload["thread_id"] = thread_id
        payload["attachment_enabled"] = bool(row.get("attachment_enabled"))
        payload["audio_enabled"] = bool(row.get("audio_enabled"))
        payload["is_pinned"] = bool(row.get("is_pinned") or member_row.get("is_pinned"))
        payload["unread_count"] = (
            unread_count
            if unread_count is not None
            else self._repository.unread_count(
                thread_id, actor_id, member_row.get("last_read_message_id")
            )
        )
        payload["participants"] = participants
        if include_messages is not None:
            limit, offset = include_messages
            rows = self._repository.messages(thread_id, limit, offset)
            attachments = self._repository.attachments([int(item["id"]) for item in rows])
            payload["messages"] = [self._message(item, attachments) for item in rows]
        return ChatThread(**payload)

    def list_threads(
        self, actor_id: int, request: ChatThreadsRequest | None = None
    ) -> ChatThreadsResponse:
        """Page the actor's inbox with a fixed query count per page."""
        page = request if request is not None else ChatThreadsRequest()
        with self._session.begin():
            self._active_actor(actor_id)
            rows = self._repository.list_thread_rows(actor_id, page.limit + 1, page.offset)
            has_more = len(rows) > page.limit
            rows = rows[: page.limit]
            thread_ids = [int(row["id"]) for row in rows]
            memberships = self._repository.participant_rows_for_threads(thread_ids, actor_id)
            if len(memberships) != len(thread_ids):
                raise ChatError(
                    "chat_membership_forbidden", "You are not a participant of this thread", 403
                )
            participants = self._repository.participants_for_threads(thread_ids)
            unread_counts = self._repository.unread_counts(thread_ids, actor_id)
            threads = [
                self._thread(
                    row,
                    actor_id,
                    participant=memberships[int(row["id"])],
                    participants=participants.get(int(row["id"]), []),
                    unread_count=unread_counts.get(int(row["id"]), 0),
                )
                for row in rows
            ]
            thread_total = self._repository.thread_total(actor_id)
            unread_total, unread_thread_count = self._repository.unread_summary(actor_id)
        return ChatThreadsResponse(
            status=200,
            message="OK",
            threads=threads,
            count=len(threads),
            thread_total=thread_total,
            unread_count=unread_total,
            unread_thread_count=unread_thread_count,
            limit=page.limit,
            offset=page.offset,
            has_more=has_more,
            server_time=self._now(),
        )

    def get_thread(self, actor_id: int, request: ChatThreadRequest) -> ChatThreadResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            row = self._repository.lock_thread(request.thread_id)
            if row is None:
                raise ChatError("chat_thread_not_found", "Thread not found", 404)
            thread = self._thread(row, actor_id, (request.limit, request.offset))
            latest = self._repository.latest_message_id(request.thread_id)
            if latest is not None:
                self._repository.mark_read(request.thread_id, actor_id, latest)
                thread.unread_count = 0
        return ChatThreadResponse(
            status=200,
            message="OK",
            thread_id=thread.thread_id,
            thread=thread,
            server_time=self._now(),
        )

    @staticmethod
    def _validate_send(body: str | None, attachment_ids: list[int]) -> None:
        if not (body or attachment_ids):
            raise ChatError("chat_message_empty", "body or attachment_ids is required", 422)
        if len(attachment_ids) > 6 or len(set(attachment_ids)) != len(attachment_ids):
            raise ChatError(
                "chat_attachments_invalid",
                "Attachments must contain at most six distinct items",
                422,
            )

    def _staged_attachments(
        self, thread_id: int, actor_id: int, attachment_ids: list[int]
    ) -> list[dict[str, Any]]:
        attachments = self._repository.lock_attachments(attachment_ids)
        if len(attachments) != len(attachment_ids):
            raise ChatError("chat_attachment_not_found", "Attachment not found", 404)
        now = self._now()
        for attachment in attachments:
            if (
                int(attachment["thread_id"]) != thread_id
                or int(attachment.get("uploaded_by_member_id") or 0) != actor_id
                or attachment.get("message_id") is not None
                or attachment.get("expires_at") is None
                or attachment["expires_at"] <= now
            ):
                raise ChatError(
                    "chat_attachment_forbidden", "Attachment is unavailable for this message", 403
                )
        return attachments

    @staticmethod
    def _message_type(body: str | None, attachments: list[dict[str, Any]]) -> str:
        if body and attachments:
            return "mixed"
        if not attachments:
            return "text"
        kinds = {
            str(getattr(attachment["kind"], "value", attachment["kind"]))
            for attachment in attachments
        }
        return kinds.pop() if len(kinds) == 1 else "mixed"

    def _send(
        self,
        actor_id: int,
        thread_id: int,
        body: str | None,
        client_id: str | None,
        reply_id: int | None,
        attachment_ids: list[int],
    ) -> ChatMutationResponse:
        self._validate_send(body, attachment_ids)
        self._participant(thread_id, actor_id)
        if reply_id is not None:
            reply = self._repository.lock_message(reply_id)
            if (
                reply is None
                or int(reply["thread_id"]) != thread_id
                or reply.get("deleted_at") is not None
            ):
                raise ChatError("chat_reply_invalid", "Reply target is unavailable", 422)
        if client_id:
            existing = self._repository.find_idempotent_message(thread_id, client_id)
            if existing is not None:
                if int(existing["sender_member_id"]) != actor_id:
                    raise ChatError(
                        "chat_idempotency_conflict", "Message identifier is already in use", 409
                    )
                linked = self._repository.attachments([int(existing["id"])]).get(
                    int(existing["id"]), []
                )
                return ChatMutationResponse(
                    status=200,
                    message="Message sent",
                    data=ChatMutationData(
                        thread_id=thread_id,
                        message=self._message(existing, {int(existing["id"]): linked}),
                    ),
                    server_time=self._now(),
                )
        staged = self._staged_attachments(thread_id, actor_id, attachment_ids)
        now = self._now()
        message_id = self._repository.create_message(
            {
                "thread_id": thread_id,
                "sender_member_id": actor_id,
                "body": body or None,
                "message_type": self._message_type(body, staged),
                "reply_to_message_id": reply_id,
                "client_generated_id": client_id,
                "created_at": now,
            }
        )
        self._repository.link_attachments(attachment_ids, message_id)
        actor = self._repository.participants(thread_id)
        sender_name = next(
            (
                str(item.get("fullname") or "Member")
                for item in actor
                if int(item["member_id"]) == actor_id
            ),
            "Member",
        )
        self._repository.update_thread_summary(
            thread_id, message_id, now, body or "[attachment]", sender_name, actor_id
        )
        created = self._repository.lock_message(message_id)
        if created is None:
            raise RuntimeError("Message disappeared after insert")
        self._repository.mark_read(thread_id, actor_id, message_id)
        return ChatMutationResponse(
            status=200,
            message="Message sent",
            data=ChatMutationData(
                thread_id=thread_id,
                message=self._message(
                    created,
                    {message_id: [dict(item) for item in staged]},
                ),
            ),
            server_time=self._now(),
        )

    def send_message(self, actor_id: int, request: ChatSendRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            return self._send(
                actor_id,
                request.thread_id,
                request.body,
                request.client_generated_id,
                request.reply_to_message_id,
                request.attachment_ids,
            )

    @staticmethod
    def _is_retryable_direct_error(error: OperationalError) -> bool:
        args = getattr(error.orig, "args", ())
        return bool(args and args[0] in {1020, 1205, 1213})

    def send_direct(self, actor_id: int, request: ChatDirectRequest) -> ChatMutationResponse:
        """Create or use a deterministic direct thread, retrying transient MariaDB conflicts."""
        for attempt in range(3):
            try:
                with self._session.begin():
                    self._active_actor(actor_id)
                    if request.recipient_id == actor_id:
                        raise ChatError(
                            "chat_direct_self", "Cannot send a direct message to yourself", 422
                        )
                    key = "__".join(str(item) for item in sorted((actor_id, request.recipient_id)))
                    thread_id, created = self._repository.create_or_get_direct_thread(
                        {
                            "type": "direct",
                            "created_by": actor_id,
                            "direct_key": key,
                            "category": "community",
                            "attachment_enabled": 1,
                            "audio_enabled": 0,
                            "created_at": self._now(),
                        }
                    )
                    # Run the active-recipient check after the atomic direct-key
                    # write. A pre-write non-locking read would establish an
                    # InnoDB repeatable-read snapshot that cannot see the
                    # concurrent direct-thread winner.
                    if request.recipient_id not in self._repository.active_users(
                        {request.recipient_id}
                    ):
                        raise ChatError("chat_recipient_not_found", "Recipient not found", 404)
                    if created:
                        self._repository.add_participant(thread_id, actor_id, "admin")
                        self._repository.add_participant(thread_id, request.recipient_id, "member")
                    return self._send(
                        actor_id,
                        thread_id,
                        request.body,
                        request.client_generated_id,
                        request.reply_to_message_id,
                        request.attachment_ids,
                    )
            except OperationalError as error:
                if not self._is_retryable_direct_error(error) or attempt == 2:
                    raise
                time.sleep(0.025 * (attempt + 1))
        raise RuntimeError("Direct-message retry loop ended unexpectedly")

    def stage_attachment(
        self,
        actor_id: int,
        prepared: PreparedChatAttachment,
        *,
        thread_id: int | None,
        recipient_id: int | None,
    ) -> ChatAttachmentResponse:
        """Persist an owned, expiring upload beneath private storage only."""
        if self._attachment_storage is None:
            raise RuntimeError("Chat attachment storage was not configured")
        if (thread_id is None) == (recipient_id is None):
            raise ChatError(
                "chat_attachment_target_invalid",
                "Exactly one thread_id or recipient_id is required",
                400,
            )
        stored: StoredAvatar | None = None
        row: dict[str, Any] | None = None
        try:
            with self._session.begin():
                self._active_actor(actor_id)
                if recipient_id is not None:
                    if recipient_id == actor_id:
                        raise ChatError("chat_direct_self", "Cannot message yourself", 422)
                    if recipient_id not in self._repository.active_users({recipient_id}):
                        raise ChatError("chat_recipient_not_found", "Recipient not found", 404)
                    direct_key = "__".join(str(item) for item in sorted((actor_id, recipient_id)))
                    resolved_thread_id, created = self._repository.create_or_get_direct_thread(
                        {
                            "type": "direct",
                            "created_by": actor_id,
                            "direct_key": direct_key,
                            "category": "community",
                            "attachment_enabled": 1,
                            "audio_enabled": 0,
                            "created_at": self._now(),
                        }
                    )
                    if created:
                        self._repository.add_participant(resolved_thread_id, actor_id, "admin")
                        self._repository.add_participant(resolved_thread_id, recipient_id, "member")
                    thread_id = resolved_thread_id
                if thread_id is None:
                    raise RuntimeError("Attachment target resolution did not return a thread")
                thread = self._repository.lock_thread(thread_id)
                if thread is None:
                    raise ChatError("chat_thread_not_found", "Thread not found", 404)
                self._participant(thread_id, actor_id)
                if not bool(thread.get("attachment_enabled")):
                    raise ChatError("chat_attachments_disabled", "Attachments are disabled", 422)
                if prepared.kind == "audio" and not bool(thread.get("audio_enabled")):
                    raise ChatError("chat_audio_disabled", "Audio attachments are disabled", 422)
                stored = self._attachment_storage.save(actor_id, prepared)
                attachment_id = self._repository.create_attachment(
                    {
                        "thread_id": thread_id,
                        "message_id": None,
                        "uploaded_by_member_id": actor_id,
                        "kind": prepared.kind,
                        "file_name": stored.original_filename,
                        "mime_type": prepared.mime_type,
                        "size_in_bytes": len(prepared.content),
                        "storage_path": stored.relative_path,
                        "public_url": None,
                        "created_at": self._now(),
                        "expires_at": self._now() + timedelta(hours=24),
                    }
                )
                row = self._repository.lock_attachment(attachment_id)
                if row is None:
                    raise RuntimeError("Attachment disappeared after insert")
        except Exception:
            if stored is not None:
                self._attachment_storage.delete(stored)
            raise
        if row is None:
            raise RuntimeError("Attachment insert did not return a row")
        return ChatAttachmentResponse(
            status=200,
            message="Attachment staged",
            data=self._attachment(row),
            server_time=self._now(),
        )

    def retrieve_attachment(self, actor_id: int, attachment_id: int) -> dict[str, Any]:
        """Authorize private attachment retrieval before the API reads bytes."""
        with self._session.begin():
            self._active_actor(actor_id)
            attachment = self._repository.lock_attachment(attachment_id)
            if attachment is None or not attachment.get("storage_path"):
                raise ChatError("chat_attachment_not_found", "Attachment not found", 404)
            if attachment.get("message_id") is None:
                if (
                    int(attachment.get("uploaded_by_member_id") or 0) != actor_id
                    or attachment.get("expires_at") is None
                    or attachment["expires_at"] <= self._now()
                ):
                    raise ChatError("chat_attachment_forbidden", "Attachment not found", 404)
            else:
                self._participant(int(attachment["thread_id"]), actor_id)
        return attachment

    def reap_expired_staged_attachments(self, *, limit: int = 200) -> ChatAttachmentReapResult:
        """Delete expired private staged uploads in a bounded, idempotent batch.

        Rows are removed inside one transaction under a row lock so a concurrent
        send cannot link an attachment the sweep is deleting; the private files are
        removed only after the row transaction commits.  The database row is
        authoritative, so a crash between commit and file removal can leave an
        unreferenced file for a storage audit to collect, but it can never leave a
        retrievable staged attachment.  Re-running is a no-op once the batch is
        empty.  Output is aggregate only and contains no storage paths or uploader
        identifiers.
        """
        if not 1 <= limit <= 500:
            raise ChatError("chat_reaper_limit_invalid", "limit must be between 1 and 500", 422)
        now = self._now()
        with self._session.begin():
            rows = self._repository.expired_staged_attachments(now, limit)
            attachment_ids = [int(row["id"]) for row in rows]
            if attachment_ids and self._attachment_storage is None:
                raise RuntimeError("Chat attachment storage was not configured")
            if attachment_ids:
                self._repository.delete_attachments(attachment_ids)
        deleted_files, file_failures = self._delete_reaped_files(rows)
        return ChatAttachmentReapResult(
            candidates=len(rows),
            deleted_rows=len(attachment_ids),
            deleted_files=deleted_files,
            file_failures=file_failures,
            limit=limit,
            server_time=now,
        )

    def _delete_reaped_files(self, rows: list[dict[str, Any]]) -> tuple[int, int]:
        """Remove the private files for reaped rows and count real deletions.

        A missing file is already clean, while a malformed stored path is a data
        problem worth counting rather than silently skipping.
        """
        if self._attachment_storage is None:
            return 0, 0
        deleted = failures = 0
        for row in rows:
            storage_path = row.get("storage_path")
            if not storage_path:
                continue
            try:
                target = self._attachment_storage.path(str(storage_path))
                existed = target.exists()
                self._attachment_storage.delete(
                    StoredAvatar(
                        relative_path=str(storage_path),
                        filename=Path(str(storage_path)).name,
                        original_filename="",
                    )
                )
                if existed and not target.exists():
                    deleted += 1
            except Exception:
                failures += 1
        return deleted, failures

    def create_group(self, actor_id: int, request: ChatGroupRequest) -> ChatThreadResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            members = set(request.member_ids) | {actor_id}
            if self._repository.active_users(members) != members:
                raise ChatError("chat_group_member_invalid", "A group member is unavailable", 422)
            now = self._now()
            thread_id = self._repository.create_thread(
                {
                    "type": "group",
                    "title": request.title,
                    "created_by": actor_id,
                    "category": request.category,
                    "attachment_enabled": 1,
                    "audio_enabled": 0,
                    "created_at": now,
                }
            )
            for member_id in sorted(members):
                self._repository.add_participant(
                    thread_id, member_id, "admin" if member_id == actor_id else "member"
                )
            row = self._repository.lock_thread(thread_id)
            if row is None:
                raise RuntimeError("Thread disappeared after insert")
            thread = self._thread(row, actor_id, (50, 0))
        return ChatThreadResponse(
            status=200,
            message="Group created",
            thread_id=thread.thread_id,
            thread=thread,
            server_time=self._now(),
        )

    def mark_delivered(
        self, actor_id: int, request: ChatMarkDeliveredRequest
    ) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            thread = self._repository.lock_thread(request.thread_id)
            if thread is None:
                raise ChatError("chat_thread_not_found", "Thread not found", 404)
            self._participant(request.thread_id, actor_id)
            message = self._repository.lock_message(request.message_id)
            if message is None or int(message["thread_id"]) != request.thread_id:
                raise ChatError("chat_message_not_found", "Message not found", 404)
            self._repository.mark_delivered(request.thread_id, actor_id, request.message_id)
        return ChatMutationResponse(
            status=200,
            message="Marked as delivered",
            data=ChatMutationData(
                thread_id=request.thread_id, last_delivered_message_id=request.message_id
            ),
            server_time=self._now(),
        )

    def add_member(self, actor_id: int, request: ChatAddMemberRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            thread = self._repository.lock_thread(request.thread_id)
            if thread is None:
                raise ChatError("chat_thread_not_found", "Thread not found", 404)
            self._group_administrator(thread, actor_id)
            if request.member_id not in self._repository.active_users({request.member_id}):
                raise ChatError("chat_member_not_found", "Member not found", 404)
            existing = self._repository.lock_participant(request.thread_id, request.member_id)
            if existing is not None and existing.get("left_at") is None:
                raise ChatError("chat_member_exists", "Member is already active", 409)
            if existing is None:
                self._repository.add_participant(request.thread_id, request.member_id, "member")
            else:
                self._repository.reactivate_participant(
                    request.thread_id, request.member_id, self._now()
                )
        return ChatMutationResponse(
            status=200,
            message="Member added",
            data=ChatMutationData(thread_id=request.thread_id, member_id=request.member_id),
            server_time=self._now(),
        )

    def leave_group(self, actor_id: int, request: ChatLeaveGroupRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            thread = self._repository.lock_thread(request.thread_id)
            if thread is None:
                raise ChatError("chat_thread_not_found", "Thread not found", 404)
            if thread.get("type") != "group":
                raise ChatError("chat_group_required", "Only group threads can be left", 422)
            participant = self._participant(request.thread_id, actor_id)
            if participant.get("role") == "admin":
                administrators = self._repository.active_administrators(request.thread_id)
                if len(administrators) == 1:
                    successor = self._repository.first_active_member_except(
                        request.thread_id, actor_id
                    )
                    if successor is not None:
                        self._repository.promote_administrator(
                            request.thread_id, int(successor["member_id"])
                        )
            left_at = self._now()
            self._repository.leave_participant(request.thread_id, actor_id, left_at)
        return ChatMutationResponse(
            status=200,
            message="Left group",
            data=ChatMutationData(thread_id=request.thread_id, member_id=actor_id, left_at=left_at),
            server_time=self._now(),
        )

    def pin_thread(self, actor_id: int, request: ChatPinThreadRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            thread = self._repository.lock_thread(request.thread_id)
            if thread is None:
                raise ChatError("chat_thread_not_found", "Thread not found", 404)
            self._participant(request.thread_id, actor_id)
            self._repository.set_pinned(request.thread_id, actor_id, request.pin)
        return ChatMutationResponse(
            status=200,
            message="Thread pinned" if request.pin else "Thread unpinned",
            data=ChatMutationData(thread_id=request.thread_id, is_pinned=request.pin),
            server_time=self._now(),
        )

    def delete_message(self, actor_id: int, request: ChatDeleteRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            message = self._repository.lock_message(request.message_id)
            if message is None:
                raise ChatError("chat_message_not_found", "Message not found", 404)
            thread_id = int(message["thread_id"])
            self._participant(thread_id, actor_id)
            if int(message["sender_member_id"]) != actor_id:
                raise ChatError("chat_message_forbidden", "Message not found", 404)
            now = self._now()
            self._repository.soft_delete(request.message_id, now)
        return ChatMutationResponse(
            status=200,
            message="Message removed",
            data=ChatMutationData(thread_id=thread_id),
            server_time=self._now(),
        )

    def mark_read(self, actor_id: int, request: ChatMarkReadRequest) -> ChatMutationResponse:
        with self._session.begin():
            self._active_actor(actor_id)
            participant = self._participant(request.thread_id, actor_id)
            message_id = request.message_id or self._repository.latest_message_id(request.thread_id)
            if message_id is not None:
                message = self._repository.lock_message(message_id)
                if message is None or int(message["thread_id"]) != request.thread_id:
                    raise ChatError("chat_message_invalid", "Message is not in this thread", 422)
                self._repository.mark_read(request.thread_id, actor_id, message_id)
            unread = self._repository.unread_count(
                request.thread_id, actor_id, message_id or participant.get("last_read_message_id")
            )
        return ChatMutationResponse(
            status=200,
            message="Marked as read",
            data=ChatMutationData(thread_id=request.thread_id, unread_count=unread),
            server_time=self._now(),
        )
