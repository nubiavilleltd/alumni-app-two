"""Bounded request and response contracts for member endpoints."""

from __future__ import annotations

from datetime import date

from pydantic import BaseModel, ConfigDict, EmailStr, Field


class GetUserProfileRequest(BaseModel):
    """Optional profile target; ordinary members remain restricted to self."""

    model_config = ConfigDict(extra="ignore")

    user_id: int | None = Field(default=None, gt=0)


class MemberProfileDetails(BaseModel):
    """Allowlisted social and professional profile fields."""

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


class UserProfileResponse(BaseModel):
    """Explicit self/administrator profile projection without credential fields."""

    status: int = 200
    message: str = "Profile retrieved successfully"
    user_id: int
    user_code: str | None = None
    email: EmailStr
    fullname: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    phone: str | None = None
    user_role: str | None = None
    avatar: str | None = None
    active: bool
    email_verified: bool
    is_approved: bool
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
    nick_name: str | None = None
    state: str | None = None
    zone_id: int | None = None
    zone_name: str | None = None
    city_id: int | None = None
    profile: MemberProfileDetails
