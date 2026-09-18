"""Social identity provider contracts."""

from __future__ import annotations

from typing import Literal

from pydantic import AliasChoices, BaseModel, ConfigDict, Field, model_validator

from app.schemas.auth import StatusResponse


class SocialLoginRequest(BaseModel):
    """A provider and its server-verifiable credential."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    provider: Literal["google", "facebook"]
    id_token: str | None = Field(default=None, validation_alias=AliasChoices("id_token", "idToken"))
    access_token: str | None = Field(
        default=None, validation_alias=AliasChoices("access_token", "accessToken")
    )

    @model_validator(mode="after")
    def require_provider_credential(self) -> SocialLoginRequest:
        if self.provider == "google" and not self.id_token:
            raise ValueError("id_token is required for google")
        if self.provider == "facebook" and not self.access_token:
            raise ValueError("access_token is required for facebook")
        return self


class SocialSignupResponse(StatusResponse):
    """A newly created provider-linked account awaiting onboarding."""

    user_id: int
    email: str
    fullname: str | None = None


class SocialUnlinkRequest(BaseModel):
    """Remove a provider link from the authenticated account."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    provider: Literal["google", "facebook"]
