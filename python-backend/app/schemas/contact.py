"""Public contact-form contracts."""

from __future__ import annotations

from pydantic import AliasChoices, BaseModel, ConfigDict, EmailStr, Field, field_validator

from app.schemas.auth import StatusResponse


class ContactCreateRequest(BaseModel):
    """A bounded, validated public contact message."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    first_name: str = Field(
        min_length=1,
        max_length=100,
        validation_alias=AliasChoices("first_name", "firstName"),
    )
    last_name: str = Field(
        min_length=1,
        max_length=100,
        validation_alias=AliasChoices("last_name", "lastName"),
    )
    email: EmailStr = Field(max_length=255)
    message: str = Field(min_length=1, max_length=10_000)

    @field_validator("first_name", "last_name", "message", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class ContactResponse(StatusResponse):
    """A successful contact submission acknowledgement."""
