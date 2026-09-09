"""Public request and response contracts for authentication endpoints."""

from __future__ import annotations

from datetime import date
from typing import Literal

from pydantic import BaseModel, ConfigDict, EmailStr, Field


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
