"""Web Push subscription and delivery contracts."""

from __future__ import annotations

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.schemas.auth import StatusResponse


class PushSubscriptionRequest(BaseModel):
    """A browser Push API subscription bound to the current account."""

    model_config = ConfigDict(extra="ignore")

    endpoint: str = Field(min_length=1, max_length=2048)
    p256dh: str = Field(min_length=1, max_length=255)
    auth: str = Field(min_length=1, max_length=255)
    browser: str | None = Field(default=None, max_length=50)

    @field_validator("endpoint", "p256dh", "auth", "browser", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class VapidKeyResponse(StatusResponse):
    """The application's VAPID public key for client subscription."""

    public_key: str
