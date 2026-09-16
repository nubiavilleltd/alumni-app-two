"""Public request and response contracts for authentication endpoints."""

from __future__ import annotations

import re
from datetime import UTC, date, datetime
from typing import Literal, Self

from pydantic import BaseModel, ConfigDict, EmailStr, Field, field_validator, model_validator


class RegistrationRequest(BaseModel):
    """Bounded public member-registration fields accepted from JSON or form bodies."""

    model_config = ConfigDict(extra="ignore")

    email: EmailStr = Field(max_length=150)
    password: str = Field(min_length=8, max_length=4096)
    first_name: str = Field(min_length=2, max_length=50)
    last_name: str = Field(min_length=2, max_length=50)
    phone: str = Field(pattern=r"^0[789][0-9]{9}$")
    chapter_id: int = Field(gt=0)
    graduation_year: int = Field(ge=1966, le=datetime.now(UTC).year)
    city: str = Field(min_length=1, max_length=100)
    voucher_id: int | None = Field(default=None, gt=0)
    department: str = Field(default="", max_length=200)
    name_in_school: str = Field(default="", max_length=200)
    alternative_phone: str = Field(default="", max_length=20)
    birth_date: date | None = None
    house_color: str = Field(default="", max_length=50)
    residential_address: str = Field(default="", max_length=5000)
    area: str = Field(default="", max_length=100)
    state: str = Field(default="", max_length=255)
    employment_status: str = Field(default="", max_length=100)
    occupation: str = Field(default="", max_length=5000)
    industry_sector: str = Field(default="", max_length=5000)
    years_of_experience: str = Field(default="", max_length=50)
    is_volunteer: bool = False
    nick_name: str = Field(default="", max_length=255)

    @field_validator(
        "first_name",
        "last_name",
        "phone",
        "city",
        "department",
        "name_in_school",
        "alternative_phone",
        "house_color",
        "residential_address",
        "area",
        "state",
        "employment_status",
        "occupation",
        "industry_sector",
        "years_of_experience",
        "nick_name",
        mode="before",
    )
    @classmethod
    def trim_profile_text(cls, value: object) -> object:
        """Apply legacy trimming to identity/profile text without altering passwords."""
        return value.strip() if isinstance(value, str) else value

    @field_validator("birth_date", "voucher_id", mode="before")
    @classmethod
    def empty_optional_values_are_none(cls, value: object) -> object:
        """Treat empty HTML form controls as omitted optional values."""
        return None if isinstance(value, str) and not value.strip() else value

    @field_validator("alternative_phone")
    @classmethod
    def validate_optional_phone(cls, value: str) -> str:
        """Apply the current Nigerian-phone contract when a secondary number is supplied."""
        if value and re.fullmatch(r"0[789][0-9]{9}", value) is None:
            raise ValueError("alternative_phone must be a valid Nigerian phone number")
        return value

    @field_validator("password")
    @classmethod
    def require_strong_password(cls, value: str) -> str:
        """Match the current registration form's minimum password policy."""
        categories = (
            any(character.islower() for character in value),
            any(character.isupper() for character in value),
            any(character.isdigit() for character in value),
            any(not character.isalnum() for character in value),
        )
        if not all(categories):
            raise ValueError(
                "password must include lowercase, uppercase, numeric, and special characters"
            )
        return value

    @model_validator(mode="after")
    def validate_combined_name(self) -> Self:
        """Keep the derived legacy fullname within its database column."""
        if len(f"{self.first_name} {self.last_name}") > 100:
            raise ValueError("combined first_name and last_name must not exceed 100 characters")
        if self.birth_date is not None and self.birth_date > datetime.now(UTC).date():
            raise ValueError("birth_date cannot be in the future")
        return self


class RegistrationResponse(BaseModel):
    """Successful registration projection consumed by the current frontend flow."""

    status: Literal[200] = 200
    message: str
    expires_in_minutes: Literal[1440] = 1440
    user_id: int
    user_code: str
    email: str
    fullname: str
    first_name: str
    last_name: str
    user_role: Literal["alumni"] = "alumni"
    phone: str
    chapter_id: int
    year: str
    graduation_year: int
    department: str
    email_verified: Literal[0] = 0
    avatar: str | None = None
    name_in_school: str
    alternative_phone: str
    birth_date: date | None = None
    house_color: str
    is_coordinator: Literal[False] = False
    residential_address: str
    area: str
    city: str
    employment_status: str
    occupation: str
    industry_sector: str
    years_of_experience: str
    is_volunteer: bool
    nick_name: str
    state: str


class LoginRequest(BaseModel):
    """Credentials accepted from JSON or form bodies."""

    model_config = ConfigDict(extra="ignore")

    identity: EmailStr
    password: str = Field(min_length=1, max_length=4096)


class RefreshRequest(BaseModel):
    """One-time refresh credential."""

    model_config = ConfigDict(extra="ignore")

    refresh_token: str = Field(min_length=32, max_length=1024)


class LogoutRequest(BaseModel):
    """Optional refresh credential for idempotent logout."""

    model_config = ConfigDict(extra="ignore")

    refresh_token: str | None = Field(default=None, min_length=32, max_length=1024)


class ForgotPasswordRequest(BaseModel):
    """Email identity used to initiate generic password recovery."""

    model_config = ConfigDict(extra="ignore")

    identity: EmailStr


class ResetPasswordRequest(BaseModel):
    """One-time reset token and replacement password confirmation."""

    model_config = ConfigDict(extra="ignore")

    code: str = Field(min_length=32, max_length=1024)
    new_password: str = Field(min_length=8, max_length=4096)
    new_password_confirm: str = Field(min_length=8, max_length=4096)


class ChangePasswordRequest(BaseModel):
    """Authenticated password-change fields."""

    model_config = ConfigDict(extra="ignore")

    old_password: str = Field(min_length=1, max_length=4096)
    new_password: str = Field(min_length=8, max_length=4096)
    confirm_password: str = Field(min_length=8, max_length=4096)


class VerifyEmailRequest(BaseModel):
    """User identity and finite six-digit email-verification code."""

    model_config = ConfigDict(extra="ignore")

    user_id: int = Field(gt=0)
    verify_code: str = Field(pattern=r"^[0-9]{6}$")


class ResendVerificationRequest(BaseModel):
    """User identity requesting replacement email verification."""

    model_config = ConfigDict(extra="ignore")

    user_id: int = Field(gt=0)


class VerifyAccessCodeRequest(BaseModel):
    """Access credential checked only against the authenticated member."""

    model_config = ConfigDict(extra="ignore")

    access_code: str = Field(min_length=1, max_length=100)


class ProfileResponse(BaseModel):
    """Legacy-compatible nested user profile projection."""

    linkedin: str | None = None
    twitter: str | None = None
    tiktok: str | None = None
    facebook: str | None = None
    instagram: str | None = None
    field_visibility: str | None = None
    website: str | None = None
    current_company: str | None = None
    current_position: str | None = None
    city: str | None = None
    country: str | None = None
    skills: str | None = None
    achievements: str | None = None
    year: str | None = None
    is_visible: bool = True
    system_role: str = ""


class LoginResponse(BaseModel):
    """Successful or onboarding-continuation login response."""

    status: int
    message: str
    access_token: str
    refresh_token: str
    token_type: Literal["Bearer"] = "Bearer"
    expires_in: int
    user_id: int
    email: str
    fullname: str | None = None
    user_code: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    phone: str | None = None
    user_role: str | None = None
    avatar: str | None = None
    active: bool | None = None
    email_verified: bool | None = None
    is_approved: bool | None = None
    chapter_id: int | None = None
    graduation_year: int | None = None
    department: str | None = None
    bio: str | None = None
    name_in_school: str | None = None
    alternative_phone: str | None = None
    birth_date: date | None = None
    house_color: str | None = None
    is_coordinator: bool = False
    residential_address: str | None = None
    area: str | None = None
    city: str | None = None
    employment_status: str | None = None
    occupation: str | None = None
    industry_sector: str | None = None
    years_of_experience: str | None = None
    is_volunteer: bool = False
    zone_id: int | None = None
    zone_name: str | None = None
    city_id: int | None = None
    profile: ProfileResponse | None = None


class RefreshResponse(BaseModel):
    """Rotated token pair returned by the refresh endpoint."""

    status: int = 200
    message: str = "Token refreshed successfully"
    access_token: str
    refresh_token: str
    token_type: Literal["Bearer"] = "Bearer"
    expires_in: int


class StatusResponse(BaseModel):
    """Legacy-shaped bounded status response."""

    status: int
    message: str
    code: str | None = None
    user_id: int | None = None


class BooleanStatusResponse(BaseModel):
    """Compatibility response for non-enumerating account recovery."""

    status: bool
    message: str
