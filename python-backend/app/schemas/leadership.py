"""Public and administrative contracts for reviewed leadership records."""

from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.schemas.auth import StatusResponse


class LeadershipFilters(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    user_id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    is_featured: bool | None = None
    is_active: bool = True
    limit: int = Field(default=50, ge=1, le=100)


class LeaderItem(BaseModel):
    id: int
    user_id: int
    position_title: str
    message: str | None = None
    photo: str | None = None
    fullname: str | None = None
    chapter_id: int | None = None
    chapter_name: str | None = None
    year: str | None = None
    sort_order: int
    is_featured: bool
    is_active: bool
    created_at: datetime | None = None
    updated_at: datetime | None = None


class LeadershipListResponse(StatusResponse):
    total: int
    featured: list[LeaderItem]
    team: list[LeaderItem]
    all: list[LeaderItem]


class LeadershipDetailResponse(StatusResponse):
    leader: LeaderItem


class LeadershipMutationResponse(StatusResponse):
    leader: LeaderItem | None = None
    updated: int = 0


class LeadershipCreateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    user_id: int = Field(gt=0)
    position_title: str = Field(min_length=1, max_length=150)
    message: str | None = Field(default=None, max_length=20_000)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    sort_order: int = Field(default=0, ge=0, le=1_000_000)
    is_featured: bool = False
    is_active: bool = True

    @field_validator("position_title")
    @classmethod
    def position_is_not_blank(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("Position title must not be blank")
        return value


class LeadershipUpdateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    user_id: int | None = Field(default=None, gt=0)
    position_title: str | None = Field(default=None, min_length=1, max_length=150)
    message: str | None = Field(default=None, max_length=20_000)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    sort_order: int | None = Field(default=None, ge=0, le=1_000_000)
    is_featured: bool | None = None
    is_active: bool | None = None
    remove_photo: bool = False


class LeadershipDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["delete"]
    id: int = Field(gt=0)


class LeadershipReorderItem(BaseModel):
    id: int = Field(gt=0)
    sort_order: int = Field(ge=0, le=1_000_000)


class LeadershipReorderRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["reorder"]
    order: list[LeadershipReorderItem] = Field(min_length=1, max_length=100)
