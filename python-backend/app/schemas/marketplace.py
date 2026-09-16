"""Public contracts for reviewed marketplace listing reads."""

from __future__ import annotations

from datetime import date, datetime
from decimal import Decimal
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.schemas.auth import StatusResponse

MarketplaceCategory = Literal["jobs", "housing", "items", "services", "tutoring", "other"]
MarketplaceStatus = Literal["active", "sold", "expired", "pending"]
MarketplacePriceType = Literal["fixed", "negotiable", "free"]


class MarketplaceFilters(BaseModel):
    """Bound public listing filters; expired inventory is never a public listing filter."""

    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    user_id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    category: MarketplaceCategory | None = None
    search: str | None = Field(default=None, min_length=1, max_length=100)
    page: int = Field(default=1, ge=1, le=10_000)
    limit: int = Field(default=24, ge=1, le=100)


class MarketplaceSocial(BaseModel):
    instagram_handle: str | None = None
    instagram_url: str | None = None
    instagram_hashtag: str | None = None
    twitter_handle: str | None = None
    twitter_url: str | None = None
    linkedin_handle: str | None = None
    linkedin_url: str | None = None
    facebook_handle: str | None = None
    facebook_url: str | None = None
    tiktok_handle: str | None = None
    tiktok_url: str | None = None
    youtube_handle: str | None = None
    youtube_url: str | None = None


class MarketplaceListing(BaseModel):
    id: int
    user_id: int
    title: str
    business_name: str
    phone: str
    chapter_id: int | None = None
    chapter_name: str | None = None
    year: str | None = None
    description: str | None = None
    category: MarketplaceCategory
    price: Decimal | None = None
    price_type: MarketplacePriceType
    images: list[str] = Field(default_factory=list)
    contact_info: str | None = None
    whatsapp: str | None = None
    website: str | None = None
    location: str | None = None
    message_prompt: str | None = None
    status: MarketplaceStatus
    is_featured: bool = False
    expires_at: date | None = None
    created_at: datetime | None = None
    seller_name: str | None = None
    seller_avatar: str | None = None
    social_media: MarketplaceSocial | None = None
    is_expired: bool = False


class MarketplaceListResponse(StatusResponse):
    listings: list[MarketplaceListing]
    total: int
    page: int
    limit: int
    has_more: bool


class MarketplaceItemResponse(StatusResponse):
    listing: MarketplaceListing


class MarketplaceCreateRequest(BaseModel):
    """Seller-controlled fields; account ownership is always server-derived."""

    # The legacy client includes user_id, status, and is_featured.  They are
    # deliberately ignored: ownership and privileged flags are server-derived.
    model_config = ConfigDict(extra="ignore")

    title: str = Field(min_length=1, max_length=250)
    business_name: str = Field(min_length=1, max_length=255)
    phone: str = Field(min_length=1, max_length=255)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    description: str | None = Field(default=None, max_length=10_000)
    category: MarketplaceCategory = "other"
    price: Decimal = Field(default=Decimal("0.00"), ge=0, max_digits=10, decimal_places=2)
    price_type: MarketplacePriceType = "free"
    contact_info: str | None = Field(default=None, max_length=255)
    whatsapp: str | None = Field(default=None, max_length=50)
    website: str | None = Field(default=None, max_length=255)
    location: str | None = Field(default=None, max_length=200)
    message_prompt: str = Field(default="", max_length=10_000)
    expires_at: date | None = None

    @field_validator("title", "business_name", "phone")
    @classmethod
    def required_text_must_not_be_blank(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("Value must not be blank")
        return value


class MarketplaceUpdateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    title: str | None = Field(default=None, min_length=1, max_length=250)
    business_name: str | None = Field(default=None, min_length=1, max_length=255)
    phone: str | None = Field(default=None, min_length=1, max_length=255)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    description: str | None = Field(default=None, max_length=10_000)
    category: MarketplaceCategory | None = None
    price: Decimal | None = Field(default=None, ge=0, max_digits=10, decimal_places=2)
    price_type: MarketplacePriceType | None = None
    contact_info: str | None = Field(default=None, max_length=255)
    whatsapp: str | None = Field(default=None, max_length=50)
    website: str | None = Field(default=None, max_length=255)
    location: str | None = Field(default=None, max_length=200)
    message_prompt: str | None = Field(default=None, max_length=10_000)
    status: MarketplaceStatus | None = None
    expires_at: date | None = None
    image_action: Literal["add", "replace"] = "add"
    remove_images: list[str] = Field(default_factory=list, max_length=6)


class MarketplaceMutationResponse(StatusResponse):
    listing: MarketplaceListing | None = None
    image_count: int = 0


class MarketplaceDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    function_type: Literal["delete"]
    id: int = Field(gt=0)
