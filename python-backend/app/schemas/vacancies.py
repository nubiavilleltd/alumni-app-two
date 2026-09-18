"""Public and member-owned contracts for job vacancies."""

from __future__ import annotations

from datetime import date, datetime
from typing import Literal

from pydantic import BaseModel, ConfigDict, EmailStr, Field, field_validator

from app.schemas.auth import StatusResponse

JobType = Literal["full_time", "part_time", "contract", "internship", "freelance"]
WorkplaceType = Literal["remote", "hybrid", "on_site"]
ExpertiseLevel = Literal["entry_level", "mid_level", "senior_level", "executive"]
ApplicationType = Literal["email", "link"]
Currency = Literal["NGN", "USD", "GBP", "EUR"]


class VacancyFilters(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    user_id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)
    job_type: JobType | None = None
    workplace_type: WorkplaceType | None = None
    level_of_expertise: ExpertiseLevel | None = None
    limit: int = Field(default=100, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100_000)


class VacancyItem(BaseModel):
    id: int
    user_id: int
    chapter_id: int
    job_title: str
    company_name: str
    job_type: JobType
    workplace_type: WorkplaceType
    level_of_expertise: ExpertiseLevel
    application_type: ApplicationType
    location: str | None = None
    salary: str | None = None
    application_deadline: date | None = None
    keywords: str | None = None
    about_role: str | None = None
    responsibilities: str | None = None
    requirements: str | None = None
    # Existing PHP rows may predate validation, so public reads remain resilient.
    application_email: str | None = None
    application_link: str | None = None
    flyer: str | None = None
    created_at: datetime | None = None
    updated_at: datetime | None = None
    posted_by: str | None = None


class VacancyListResponse(StatusResponse):
    vacancies: list[VacancyItem]
    total: int
    limit: int
    offset: int


class VacancyDetailResponse(StatusResponse):
    vacancy: VacancyItem


class VacancyMutationResponse(StatusResponse):
    vacancy: VacancyItem | None = None


class VacancyCreateRequest(BaseModel):
    """Client input; ownership and chapter membership are always server-derived."""

    model_config = ConfigDict(extra="ignore")

    job_title: str = Field(min_length=1, max_length=255)
    company_name: str = Field(min_length=1, max_length=255)
    job_type: JobType = "full_time"
    workplace_type: WorkplaceType = "remote"
    level_of_expertise: ExpertiseLevel = "entry_level"
    location: str | None = Field(default=None, max_length=255)
    salary: str | None = Field(default=None, max_length=100)
    # The reviewed table has no currency column. Accept the active client field
    # without fabricating persistence; the frontend already defaults missing values to NGN.
    currency: Currency | None = None
    application_deadline: date | None = None
    keywords: str | None = Field(default=None, max_length=10_000)
    about_role: str | None = Field(default=None, max_length=100_000)
    responsibilities: str | None = Field(default=None, max_length=100_000)
    requirements: str | None = Field(default=None, max_length=100_000)
    application_type: ApplicationType = "email"
    application_email: EmailStr | None = None
    application_link: str | None = Field(default=None, max_length=500)
    chapter_id: int | None = Field(default=None, gt=0)

    @field_validator("job_title", "company_name")
    @classmethod
    def required_text_must_not_be_blank(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("Value must not be blank")
        return value

    @field_validator("application_link")
    @classmethod
    def application_link_must_not_be_blank(cls, value: str | None) -> str | None:
        return value.strip() or None if value is not None else None

    def valid_application_destination(self) -> bool:
        return (
            self.application_email is not None
            if self.application_type == "email"
            else self.application_link is not None
        )


class VacancyUpdateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    job_title: str | None = Field(default=None, min_length=1, max_length=255)
    company_name: str | None = Field(default=None, min_length=1, max_length=255)
    job_type: JobType | None = None
    workplace_type: WorkplaceType | None = None
    level_of_expertise: ExpertiseLevel | None = None
    location: str | None = Field(default=None, max_length=255)
    salary: str | None = Field(default=None, max_length=100)
    currency: Currency | None = None
    application_deadline: date | None = None
    keywords: str | None = Field(default=None, max_length=10_000)
    about_role: str | None = Field(default=None, max_length=100_000)
    responsibilities: str | None = Field(default=None, max_length=100_000)
    requirements: str | None = Field(default=None, max_length=100_000)
    application_type: ApplicationType | None = None
    application_email: EmailStr | None = None
    application_link: str | None = Field(default=None, max_length=500)
    chapter_id: int | None = Field(default=None, gt=0)
    remove_flyer: bool = False

    @field_validator("job_title", "company_name")
    @classmethod
    def optional_text_must_not_be_blank(cls, value: str | None) -> str | None:
        if value is None:
            return None
        value = value.strip()
        if not value:
            raise ValueError("Value must not be blank")
        return value


class VacancyDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    function_type: Literal["delete"]
    id: int = Field(gt=0)
