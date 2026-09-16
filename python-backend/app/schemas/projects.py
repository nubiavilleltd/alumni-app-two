"""Public and administrative contracts for reviewed community projects."""

from __future__ import annotations

from datetime import datetime
from decimal import Decimal
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.schemas.auth import StatusResponse

ProjectStatus = Literal["active", "completed", "paused", "draft", "ongoing"]


class ProjectFilters(BaseModel):
    """Bound public project queries and prevent unbounded listing reads."""

    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    status: ProjectStatus | None = None
    is_featured: bool | None = None
    limit: int = Field(default=10, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100_000)


class ProjectItem(BaseModel):
    id: int
    title: str
    description: str | None = None
    images: list[str] = Field(default_factory=list)
    amount_raised: Decimal
    target_amount: Decimal | None = None
    status: ProjectStatus
    location: str
    sort_order: int
    is_featured: bool
    chapter_id: int | None = None
    chapter_name: str | None = None
    year: str | None = None
    start_date: datetime | None = None
    end_date: datetime | None = None
    conducted_by: str | None = None
    created_at: datetime | None = None
    created_by_name: str | None = None


class ProjectListResponse(StatusResponse):
    projects: list[ProjectItem]
    total: int
    limit: int
    offset: int


class ProjectDetailResponse(StatusResponse):
    project: ProjectItem


class ProjectMutationResponse(StatusResponse):
    project: ProjectItem | None = None
    image_count: int = 0


class ProjectCreateRequest(BaseModel):
    """Client-controlled fields; identity and deletion flags are server owned."""

    model_config = ConfigDict(extra="ignore")

    title: str = Field(min_length=1, max_length=255)
    description: str = Field(default="", max_length=20_000)
    amount_raised: Decimal = Field(default=Decimal("0.00"), ge=0, max_digits=15, decimal_places=2)
    target_amount: Decimal | None = Field(default=None, ge=0, max_digits=15, decimal_places=2)
    status: ProjectStatus = "active"
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    sort_order: int = Field(default=0, ge=0, le=1_000_000)
    is_featured: bool = False
    start_date: datetime | None = None
    end_date: datetime | None = None
    conducted_by: str | None = Field(default=None, max_length=255)
    location: str = Field(default="", max_length=255)

    @field_validator("title")
    @classmethod
    def title_must_not_be_blank(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("Title must not be blank")
        return value


class ProjectUpdateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    title: str | None = Field(default=None, min_length=1, max_length=255)
    description: str | None = Field(default=None, max_length=20_000)
    amount_raised: Decimal | None = Field(default=None, ge=0, max_digits=15, decimal_places=2)
    target_amount: Decimal | None = Field(default=None, ge=0, max_digits=15, decimal_places=2)
    status: ProjectStatus | None = None
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    sort_order: int | None = Field(default=None, ge=0, le=1_000_000)
    is_featured: bool | None = None
    start_date: datetime | None = None
    end_date: datetime | None = None
    conducted_by: str | None = Field(default=None, max_length=255)
    location: str | None = Field(default=None, max_length=255)
    image_action: Literal["add", "replace"] = "add"
    remove_images: list[str] = Field(default_factory=list, max_length=6)


class ProjectDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["delete"]
    id: int = Field(gt=0)
