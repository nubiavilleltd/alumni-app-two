"""Pure contract tests for chat service branching that does not require database rows."""

from __future__ import annotations

import pytest
from sqlalchemy.exc import OperationalError

from app.schemas.chat import ChatAttachment
from app.services.chat import ChatError, ChatService


def test_message_type_preserves_text_single_kind_and_mixed_contracts() -> None:
    assert ChatService._message_type("Text", []) == "text"
    assert ChatService._message_type(None, [{"kind": "image"}]) == "image"
    assert ChatService._message_type(None, [{"kind": "image"}, {"kind": "file"}]) == "mixed"
    assert ChatService._message_type("Text", [{"kind": "audio"}]) == "mixed"


@pytest.mark.parametrize("attachment_ids", [[1, 1], [1, 2, 3, 4, 5, 6, 7]])
def test_send_validation_rejects_duplicate_or_excess_attachment_ids(
    attachment_ids: list[int],
) -> None:
    with pytest.raises(ChatError, match="at most six distinct"):
        ChatService._validate_send(None, attachment_ids)


def test_send_validation_requires_content() -> None:
    with pytest.raises(ChatError, match="body or attachment_ids"):
        ChatService._validate_send("", [])


def test_current_account_and_attachment_target_guards_fail_before_storage_or_database_work() -> (
    None
):
    service = object.__new__(ChatService)

    class _InactiveRepository:
        @staticmethod
        def lock_actor(_actor_id: int) -> dict[str, int]:
            return {"active": 0}

    service._repository = _InactiveRepository()  # type: ignore[assignment]
    with pytest.raises(ChatError, match="Authentication required"):
        service._active_actor(42)

    prepared = type("Prepared", (), {})()
    service._attachment_storage = None
    with pytest.raises(RuntimeError, match="not configured"):
        service.stage_attachment(42, prepared, thread_id=7, recipient_id=None)
    service._attachment_storage = object()  # type: ignore[assignment]
    with pytest.raises(ChatError, match="Exactly one"):
        service.stage_attachment(42, prepared, thread_id=None, recipient_id=None)


def test_attachment_response_redacts_storage_metadata_and_normalizes_defaults() -> None:
    attachment = ChatService._attachment(
        {
            "id": 42,
            "thread_id": 7,
            "kind": "file",
            "file_name": None,
            "mime_type": None,
            "size_in_bytes": None,
            "duration_seconds": 9,
            "storage_path": "chat/private-name.bin",
        }
    )
    assert attachment == ChatAttachment(
        attachment_id=42,
        thread_id=7,
        kind="file",
        file_name="attachment",
        mime_type="application/octet-stream",
        size_in_bytes=0,
        duration_seconds=9,
        download_path="/chat_api/v2_attachments/42",
    )
    assert "storage_path" not in attachment.model_dump()


class _SyntheticDatabaseError(Exception):
    pass


@pytest.mark.parametrize(("code", "expected"), [(1213, True), (9999, False)])
def test_transient_direct_conflict_classifier_is_bounded(code: int, expected: bool) -> None:
    error = OperationalError("synthetic", {}, _SyntheticDatabaseError(code))
    assert ChatService._is_retryable_direct_error(error) is expected
