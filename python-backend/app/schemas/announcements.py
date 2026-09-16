"""Explicit public and content-administration announcement contracts."""

from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator

from app.schemas.auth import StatusResponse

AnnouncementType = Literal["info", "warning", "success", "event"]


class AnnouncementFilters(BaseModel):
    """Bound public feed filters and pagination."""

    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    created_by: int | None = Field(default=None, gt=0)
    type: AnnouncementType | None = None
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    page: int = Field(default=1, ge=1, le=10_000)
    limit: int = Field(default=100, ge=1, le=100)


class AnnouncementCreateRequest(BaseModel):
    """Content-admin fields accepted for a new announcement."""

    model_config = ConfigDict(extra="forbid")

    title: str = Field(min_length=1, max_length=255)
    content: str = Field(min_length=1, max_length=10_000)
    type: AnnouncementType = "info"
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    starts_at: datetime | None = None
    ends_at: datetime | None = None

    @field_validator("title", "content")
    @classmethod
    def required_text(cls, value: str) -> str:
        normalized = value.strip()
        if not normalized:
            raise ValueError("Value must not be blank")
        return normalized

    @model_validator(mode="after")
    def dates_are_ordered(self) -> AnnouncementCreateRequest:
        if self.starts_at and self.ends_at and self.ends_at < self.starts_at:
            raise ValueError("ends_at must not be before starts_at")
        return self


class AnnouncementUpdateRequest(BaseModel):
    """Partial content-admin update with explicit clearing fields."""

    model_config = ConfigDict(extra="forbid")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    title: str | None = Field(default=None, min_length=1, max_length=255)
    content: str | None = Field(default=None, min_length=1, max_length=10_000)
    type: AnnouncementType | None = None
    chapter_id: int | None = Field(default=None, gt=0)
    clear_chapter_id: bool = False
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    clear_year: bool = False
    starts_at: datetime | None = None
    ends_at: datetime | None = None
    clear_starts_at: bool = False
    clear_ends_at: bool = False

    @field_validator("title", "content")
    @classmethod
    def optional_text(cls, value: str | None) -> str | None:
        if value is None:
            return None
        normalized = value.strip()
        if not normalized:
            raise ValueError("Value must not be blank")
        return normalized

    @model_validator(mode="after")
    def dates_are_ordered(self) -> AnnouncementUpdateRequest:
        if self.starts_at and self.ends_at and self.ends_at < self.starts_at:
            raise ValueError("ends_at must not be before starts_at")
        return self


class AnnouncementDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    function_type: Literal["delete"]
    id: int = Field(gt=0)


class AnnouncementItem(BaseModel):
    id: int
    title: str
    content: str
    images: str | None = None
    type: AnnouncementType
    created_by: int
    created_by_name: str | None = None
    chapter_id: int | None = None
    year: str | None = None
    starts_at: datetime | None = None
    ends_at: datetime | None = None
    created_at: datetime | None = None
    updated_at: datetime | None = None


class AnnouncementListResponse(StatusResponse):
    data: list[AnnouncementItem]
    total: int
    page: int
    limit: int
    has_more: bool


class AnnouncementMutationResponse(StatusResponse):
    data: AnnouncementItem | None = None
