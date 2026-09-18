"""Bounded contracts for the current per-user notification schema."""

from __future__ import annotations

from datetime import datetime

from pydantic import AliasChoices, BaseModel, ConfigDict, Field, field_validator


class GetNotificationsRequest(BaseModel):
    """Self-scoped notification filters; legacy target and token extras are ignored."""

    model_config = ConfigDict(extra="ignore")

    page: int = Field(default=1, ge=1)
    limit: int = Field(default=20, ge=1, le=100)
    unread_only: bool = False


class CreateNotificationRequest(BaseModel):
    """An administrator-created in-app notification for one recipient."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    user_id: int = Field(gt=0, validation_alias=AliasChoices("user_id", "target_user_id", "userId"))
    type: str = Field(min_length=1, max_length=50)
    message: str = Field(min_length=1, max_length=10_000)
    link: str | None = Field(default=None, max_length=255)

    @field_validator("type", "message", "link", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class MarkNotificationReadRequest(BaseModel):
    """Mark one owned notification, or every owned unread notification when omitted."""

    model_config = ConfigDict(extra="ignore")

    notification_id: int | None = Field(default=None, gt=0)

    @field_validator("notification_id", mode="before")
    @classmethod
    def normalize_optional_id(cls, value: object) -> object:
        """Preserve the legacy empty-value meaning of mark all."""
        if value is None or (isinstance(value, str) and not value.strip()):
            return None
        return value


class NotificationItem(BaseModel):
    """Minimum notification projection from the reviewed current SQL table."""

    id: int = Field(gt=0)
    type: str = Field(min_length=1, max_length=50)
    message: str = Field(min_length=1)
    link: str | None = Field(default=None, max_length=255)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    is_read: bool
    created_at: datetime


class NotificationListResponse(BaseModel):
    """Paginated self-only notification feed."""

    status: int = 200
    message: str = "Notifications retrieved successfully"
    count: int = Field(ge=0, le=100)
    total: int = Field(ge=0)
    unread_count: int = Field(ge=0)
    page: int = Field(ge=1)
    limit: int = Field(ge=1, le=100)
    has_more: bool
    notifications: list[NotificationItem] = Field(max_length=100)


class NotificationReadResponse(BaseModel):
    """Idempotent read-state result without exposing another user's row."""

    status: int = 200
    message: str
    updated_count: int = Field(ge=0, le=1000)
