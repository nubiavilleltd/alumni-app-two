"""Bounded contracts for the active V2 messaging frontend."""

from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import AliasChoices, BaseModel, ConfigDict, Field, field_validator

from app.schemas.auth import StatusResponse


class _ChatInput(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)


class ChatThreadRequest(_ChatInput):
    thread_id: int = Field(gt=0, validation_alias=AliasChoices("thread_id", "threadId"))
    limit: int = Field(default=50, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100_000)


class ChatThreadsRequest(_ChatInput):
    """Bounded inbox paging; legacy callers may send no body and keep the default page."""

    limit: int = Field(default=100, ge=1, le=200)
    offset: int = Field(default=0, ge=0, le=100_000)


class ChatSendRequest(ChatThreadRequest):
    body: str | None = Field(default=None, max_length=10_000)
    client_generated_id: str | None = Field(
        default=None,
        max_length=100,
        validation_alias=AliasChoices("client_generated_id", "clientGeneratedId"),
    )
    reply_to_message_id: int | None = Field(
        default=None,
        gt=0,
        validation_alias=AliasChoices("reply_to_message_id", "replyToMessageId"),
    )
    attachment_ids: list[int] = Field(default_factory=list, max_length=10)

    @field_validator("body", mode="before")
    @classmethod
    def trim_body(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class ChatDirectRequest(_ChatInput):
    recipient_id: int = Field(gt=0, validation_alias=AliasChoices("recipient_id", "recipientId"))
    body: str | None = Field(default=None, max_length=10_000)
    client_generated_id: str | None = Field(
        default=None,
        max_length=100,
        validation_alias=AliasChoices("client_generated_id", "clientGeneratedId"),
    )
    reply_to_message_id: int | None = Field(
        default=None,
        gt=0,
        validation_alias=AliasChoices("reply_to_message_id", "replyToMessageId"),
    )
    attachment_ids: list[int] = Field(default_factory=list, max_length=10)

    @field_validator("body", mode="before")
    @classmethod
    def trim_body(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class ChatGroupRequest(_ChatInput):
    title: str = Field(min_length=1, max_length=255)
    member_ids: list[int] = Field(
        min_length=1, max_length=100, validation_alias=AliasChoices("member_ids", "memberIds")
    )
    category: Literal["community", "mentorship", "events", "marketplace"] = "community"

    @field_validator("title", mode="before")
    @classmethod
    def trim_title(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class ChatDeleteRequest(_ChatInput):
    message_id: int = Field(gt=0, validation_alias=AliasChoices("message_id", "messageId"))


class ChatMarkReadRequest(_ChatInput):
    thread_id: int = Field(gt=0, validation_alias=AliasChoices("thread_id", "threadId"))
    message_id: int | None = Field(
        default=None,
        gt=0,
        validation_alias=AliasChoices("message_id", "messageId"),
    )


class ChatMarkDeliveredRequest(_ChatInput):
    thread_id: int = Field(gt=0, validation_alias=AliasChoices("thread_id", "threadId"))
    message_id: int = Field(gt=0, validation_alias=AliasChoices("message_id", "messageId"))


class ChatAddMemberRequest(_ChatInput):
    thread_id: int = Field(gt=0, validation_alias=AliasChoices("thread_id", "threadId"))
    member_id: int = Field(
        gt=0,
        validation_alias=AliasChoices("member_id", "memberId", "user_id", "userId"),
    )


class ChatLeaveGroupRequest(_ChatInput):
    thread_id: int = Field(gt=0, validation_alias=AliasChoices("thread_id", "threadId"))


class ChatPinThreadRequest(ChatLeaveGroupRequest):
    pin: bool = True


class ChatParticipant(BaseModel):
    member_id: int
    fullname: str | None = None
    avatar: str | None = None
    graduation_year: int | None = None
    city: str | None = None
    role: str
    joined_at: datetime | None = None
    last_read_message_id: int | None = None
    last_delivered_message_id: int | None = None


class ChatMessage(BaseModel):
    id: int
    thread_id: int
    sender_member_id: int
    body: str | None = None
    message_type: str
    created_at: datetime
    client_generated_id: str | None = None
    reply_to_message_id: int | None = None
    deleted_at: datetime | None = None
    sender_name: str | None = None
    attachments: list[dict[str, object]] = Field(default_factory=list)


class ChatAttachment(BaseModel):
    attachment_id: int
    thread_id: int
    kind: Literal["image", "audio", "file"]
    file_name: str
    mime_type: str
    size_in_bytes: int
    duration_seconds: int | None = None
    download_path: str


class ChatAttachmentResponse(StatusResponse):
    data: ChatAttachment
    server_time: datetime


class ChatThread(BaseModel):
    thread_id: int
    type: str
    title: str | None = None
    category: str
    attachment_enabled: bool
    audio_enabled: bool
    created_at: datetime
    updated_at: datetime | None = None
    last_message_id: int | None = None
    last_message_at: datetime | None = None
    last_message_preview: str | None = None
    last_message_sender_name: str | None = None
    last_message_sender_member_id: int | None = None
    unread_count: int = 0
    is_pinned: bool = False
    participants: list[ChatParticipant] = Field(default_factory=list)
    messages: list[ChatMessage] = Field(default_factory=list)


class ChatThreadsResponse(StatusResponse):
    threads: list[ChatThread] = Field(max_length=200)
    count: int = Field(ge=0, le=200)
    thread_total: int = Field(ge=0)
    unread_count: int = Field(ge=0)
    unread_thread_count: int = Field(ge=0)
    limit: int = Field(ge=1, le=200)
    offset: int = Field(ge=0, le=100_000)
    has_more: bool
    server_time: datetime


class ChatThreadResponse(StatusResponse):
    # The active frontend resolves a newly-created group from the top-level
    # identifier before it performs its follow-up detail request.
    thread_id: int
    thread: ChatThread
    server_time: datetime


class ChatMutationData(BaseModel):
    thread_id: int
    message: ChatMessage | None = None
    unread_count: int | None = None
    member_id: int | None = None
    is_pinned: bool | None = None
    last_delivered_message_id: int | None = None
    left_at: datetime | None = None


class ChatMutationResponse(StatusResponse):
    data: ChatMutationData
    server_time: datetime
