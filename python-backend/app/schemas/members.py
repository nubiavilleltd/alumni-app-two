"""Bounded request and response contracts for member endpoints."""

from __future__ import annotations

from datetime import UTC, date, datetime
from typing import Literal

from pydantic import BaseModel, ConfigDict, EmailStr, Field, field_validator, model_validator


class AlumniStats(BaseModel):
    """Four non-identifying directory summary counts from the legacy contract."""

    total_alumni: int = Field(ge=0)
    total_years: int = Field(ge=0)
    total_chapters: int = Field(ge=0)
    total_departments: int = Field(ge=0)


class AlumniStatsResponse(BaseModel):
    """Bounded aggregate response for the member-directory header."""

    status: int = 200
    message: str = "Alumni stats retrieved successfully"
    stats: AlumniStats


class GetBirthdaysRequest(BaseModel):
    """Bounded birthday window filters shared by the GET and bodyless POST routes."""

    model_config = ConfigDict(extra="ignore")

    scope: Literal["today", "week", "month", "upcoming"] = "today"
    days: int = 30
    month: int | None = None
    limit: int = 50
    include_self: bool = True

    @field_validator("scope", mode="before")
    @classmethod
    def normalize_scope(cls, value: object) -> object:
        """Preserve the legacy blank default while accepting case-insensitive scopes."""
        if value is None:
            return "today"
        if isinstance(value, str):
            normalized = value.strip().casefold()
            return normalized or "today"
        return value

    @field_validator("days", mode="before")
    @classmethod
    def normalize_days_input(cls, value: object) -> object:
        """Treat a blank upcoming window as the documented 30-day default."""
        if value is None or (isinstance(value, str) and not value.strip()):
            return 30
        try:
            return int(str(value).strip())
        except (TypeError, ValueError):
            return 0

    @field_validator("days")
    @classmethod
    def bound_days(cls, value: int) -> int:
        """Match the legacy default-for-low and clamp-for-high window behavior."""
        if value < 1:
            return 30
        return min(value, 365)

    @field_validator("month", mode="before")
    @classmethod
    def normalize_month_input(cls, value: object) -> object:
        """Allow a blank month to resolve to the current Lagos month in the service."""
        if value is None or (isinstance(value, str) and not value.strip()):
            return None
        try:
            return int(str(value).strip())
        except (TypeError, ValueError):
            return 0

    @field_validator("limit", mode="before")
    @classmethod
    def normalize_limit_input(cls, value: object) -> object:
        """Treat a blank limit as the documented default."""
        if value is None or (isinstance(value, str) and not value.strip()):
            return 50
        try:
            return int(str(value).strip())
        except (TypeError, ValueError):
            return 0

    @field_validator("limit")
    @classmethod
    def bound_limit(cls, value: int) -> int:
        """Match the legacy default-for-low and clamp-for-high result behavior."""
        if value < 1:
            return 50
        return min(value, 200)

    @field_validator("include_self", mode="before")
    @classmethod
    def normalize_include_self(cls, value: object) -> object:
        """Preserve the legacy true default for omitted or blank values."""
        if value is None or (isinstance(value, str) and not value.strip()):
            return True
        if isinstance(value, bool):
            return value
        if isinstance(value, int):
            return value != 0
        return str(value).strip().casefold() in {"true", "1", "yes", "on"}

    @model_validator(mode="after")
    def validate_scoped_month(self) -> GetBirthdaysRequest:
        """Validate month only when the caller selected the month scope."""
        if self.scope == "month" and self.month is not None and not 1 <= self.month <= 12:
            raise ValueError("month must be between 1 and 12")
        return self


class BirthdayAnnouncement(BaseModel):
    """Privacy-minimized birthday card data used by the current frontend."""

    user_id: int = Field(gt=0)
    fullname: str = Field(min_length=1, max_length=100)
    name_in_school: str | None = Field(default=None, max_length=200)
    avatar: str | None = None
    class_label: str | None = Field(default=None, max_length=16)
    date: date
    days_until: int = Field(ge=0, le=366)
    is_today: bool
    is_self: bool
    message: str = Field(min_length=1, max_length=80)


class BirthdayListResponse(BaseModel):
    """Bounded, privacy-aware birthday announcements for one calendar window."""

    status: int = 200
    message: str
    scope: Literal["today", "week", "month", "upcoming"]
    date: date
    total: int = Field(ge=0)
    returned: int = Field(ge=0, le=200)
    birthdays: list[BirthdayAnnouncement]


class GetChaptersRequest(BaseModel):
    """Optional user target for the dual public/protected chapter route."""

    model_config = ConfigDict(extra="ignore")

    user_id: int | None = Field(default=None, gt=0)


class ChapterSummary(BaseModel):
    """Public fields for one enabled alumni chapter."""

    id: int
    chapter_name: str
    location: str
    is_enabled: bool
    created_at: str


class ChapterListResponse(BaseModel):
    """Public enabled-chapter listing used before or after authentication."""

    status: int = 200
    message: str = "Chapters retrieved successfully"
    total: int = Field(ge=0)
    chapters: list[ChapterSummary]


class PublicCity(BaseModel):
    """Public city and zone metadata used by registration and profile forms."""

    city_id: int = Field(gt=0)
    city: str = Field(min_length=1, max_length=150)
    chapter_id: int = Field(gt=0)
    zone_id: int = Field(gt=0)
    zone: str | None = Field(default=None, max_length=100)


class CityListResponse(BaseModel):
    """Deterministic public city catalogue under the legacy response key."""

    status: int = 200
    message: str = "Cities retrieved successfully"
    data: list[PublicCity]


class ZoneCity(BaseModel):
    """Minimum city identity nested beneath one public welfare zone."""

    city_id: int = Field(gt=0)
    city: str = Field(min_length=1, max_length=150)


class PublicZoneCoordinator(BaseModel):
    """Privacy-aware coordinator fields needed for welfare contact and messaging."""

    user_id: int = Field(gt=0)
    name: str = Field(min_length=1, max_length=255)
    first_name: str | None = Field(default=None, max_length=255)
    last_name: str | None = Field(default=None, max_length=255)
    phone: str | None = Field(default=None, max_length=100)
    avatar: str | None = None


class PublicZone(BaseModel):
    """Public welfare zone with bounded cities and an eligible coordinator."""

    zone_id: int = Field(gt=0)
    zone: str = Field(min_length=1, max_length=100)
    chapter_id: int = Field(gt=0)
    coordinator: PublicZoneCoordinator | None
    cities: list[ZoneCity]


class ZoneListResponse(BaseModel):
    """Deterministic public welfare-zone catalogue under the legacy response key."""

    status: int = 200
    message: str = "Zones retrieved successfully"
    data: list[PublicZone]


class ManageZoneRequest(BaseModel):
    """Bounded create, update, or delete input for one welfare zone."""

    model_config = ConfigDict(extra="ignore")

    action: Literal["create", "update", "delete"]
    zone_id: int | None = Field(default=None, gt=0)
    zone: str | None = Field(default=None, max_length=100)
    coordinator_user_id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)

    @field_validator("action", mode="before")
    @classmethod
    def normalize_action(cls, value: object) -> object:
        """Accept the legacy case-insensitive action spellings."""
        return value.strip().casefold() if isinstance(value, str) else value

    @field_validator("zone", mode="before")
    @classmethod
    def normalize_zone(cls, value: object) -> object:
        """Store a compact non-blank zone name."""
        if not isinstance(value, str):
            return value
        normalized = " ".join(value.split())
        return normalized or None

    @field_validator("zone_id", "coordinator_user_id", "chapter_id", mode="before")
    @classmethod
    def normalize_optional_identifier(cls, value: object) -> object:
        """Treat an empty form field as an omitted or cleared optional identifier."""
        return None if isinstance(value, str) and not value.strip() else value

    @model_validator(mode="after")
    def require_action_fields(self) -> ManageZoneRequest:
        """Require only the identifiers and changes needed by the selected action."""
        supplied = self.model_fields_set
        if self.action == "create":
            if self.zone is None:
                raise ValueError("zone is required")
            return self
        if self.zone_id is None:
            raise ValueError("zone_id is required")
        if self.action == "update":
            mutable = {"zone", "coordinator_user_id", "chapter_id"}
            if not supplied.intersection(mutable):
                raise ValueError("at least one zone field is required")
            if "zone" in supplied and self.zone is None:
                raise ValueError("zone cannot be blank")
        return self


class ManageZoneResponse(BaseModel):
    """Legacy-shaped result without returning the mutated database row."""

    status: int = 200
    message: str
    zone_id: int | None = Field(default=None, gt=0)


class ManageCityRequest(BaseModel):
    """Bounded create, update, or delete input for one city mapping."""

    model_config = ConfigDict(extra="ignore")

    action: Literal["create", "update", "delete"]
    city_id: int | None = Field(default=None, gt=0)
    city: str | None = Field(default=None, max_length=150)
    zone_id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)

    @field_validator("action", mode="before")
    @classmethod
    def normalize_action(cls, value: object) -> object:
        """Accept the legacy case-insensitive action spellings."""
        return value.strip().casefold() if isinstance(value, str) else value

    @field_validator("city", mode="before")
    @classmethod
    def normalize_city(cls, value: object) -> object:
        """Store a compact non-blank city name."""
        if not isinstance(value, str):
            return value
        normalized = " ".join(value.split())
        return normalized or None

    @field_validator("city_id", "zone_id", "chapter_id", mode="before")
    @classmethod
    def normalize_optional_identifier(cls, value: object) -> object:
        """Treat empty form identifiers as missing values."""
        return None if isinstance(value, str) and not value.strip() else value

    @model_validator(mode="after")
    def require_action_fields(self) -> ManageCityRequest:
        """Require a complete create and at least one allowlisted update field."""
        supplied = self.model_fields_set
        if self.action == "create":
            if self.city is None or self.zone_id is None:
                raise ValueError("city and zone_id are required")
            return self
        if self.city_id is None:
            raise ValueError("city_id is required")
        if self.action == "update":
            mutable = {"city", "zone_id", "chapter_id"}
            if not supplied.intersection(mutable):
                raise ValueError("at least one city field is required")
            if "city" in supplied and self.city is None:
                raise ValueError("city cannot be blank")
        return self


class ManageCityResponse(BaseModel):
    """Legacy-shaped result without exposing unrelated catalogue state."""

    status: int = 200
    message: str
    city_id: int | None = Field(default=None, gt=0)


class UploadZonesCitiesSummary(BaseModel):
    """Bounded legacy-compatible counts for one completed geography import."""

    zones_added: int = Field(ge=0, le=2_000)
    cities_added: int = Field(ge=0, le=2_000)
    cities_updated: int = Field(ge=0, le=2_000)
    total_rows: int = Field(ge=1, le=2_000)


class UploadZonesCitiesResponse(BaseModel):
    """Successful all-or-nothing geography import response."""

    status: int = 200
    message: str = "Upload processed successfully"
    summary: UploadZonesCitiesSummary


class AlumniImportSummary(BaseModel):
    """Bounded counts for one completed all-or-nothing alumni import."""

    total: int = Field(ge=1, le=500)
    imported: int = Field(ge=0, le=500)
    updated: int = Field(ge=0, le=500)
    unchanged: int = Field(ge=0, le=500)
    coordinator_requests_ignored: int = Field(ge=0, le=500)


class AlumniImportResult(BaseModel):
    """Minimum row correlation returned to the authorized importer."""

    row: int = Field(ge=1)
    status: Literal["imported", "updated", "unchanged"]
    user_id: int = Field(gt=0)


class AlumniImportResponse(BaseModel):
    """Successful import response without credentials, access codes, or profile PII."""

    status: int = 200
    message: str
    summary: AlumniImportSummary
    results: list[AlumniImportResult] = Field(max_length=500)


class GetZoneMembersRequest(BaseModel):
    """Bounded zone selector and pagination for authenticated member discovery."""

    model_config = ConfigDict(extra="ignore")

    zone_id: int | None = Field(default=None, gt=0)
    zone: str | None = Field(default=None, max_length=100)
    page: int = Field(default=1, ge=1)
    limit: int = Field(default=50, ge=1, le=100)

    @field_validator("zone", mode="before")
    @classmethod
    def normalize_zone_name(cls, value: object) -> object:
        """Trim the optional name and collapse blank input to no selector."""
        if not isinstance(value, str):
            return value
        normalized = " ".join(value.split())
        return normalized or None

    @model_validator(mode="after")
    def require_zone_selector(self) -> GetZoneMembersRequest:
        """Require an ID or exact name while preserving ID precedence."""
        if self.zone_id is None and self.zone is None:
            raise ValueError("zone_id or zone is required")
        return self


class MemberZone(BaseModel):
    """Bounded zone identity with a privacy-aware eligible coordinator."""

    zone_id: int = Field(gt=0)
    zone: str = Field(min_length=1, max_length=100)
    coordinator: PublicZoneCoordinator | None


class ZoneMemberUser(BaseModel):
    """Minimum privacy-filtered member identity useful inside a zone roster."""

    user_id: int = Field(gt=0)
    fullname: str | None = Field(default=None, max_length=100)
    first_name: str | None = Field(default=None, max_length=50)
    last_name: str | None = Field(default=None, max_length=50)
    graduation_year: int | None = None
    avatar: str | None = None
    phone: str | None = Field(default=None, max_length=100)
    city: str = Field(min_length=1, max_length=100)
    is_coordinator: bool


class ZoneMemberListResponse(BaseModel):
    """Paginated authenticated zone roster without broad account or profile records."""

    status: int = 200
    message: str = "Users retrieved successfully"
    zone: MemberZone
    count: int = Field(ge=0)
    total: int = Field(ge=0)
    page: int = Field(ge=1)
    limit: int = Field(ge=1, le=100)
    has_more: bool
    users: list[ZoneMemberUser]


class MyZoneResponse(BaseModel):
    """Current member's city and resolved privacy-aware zone assignment."""

    status: int = 200
    message: str
    city: str | None = Field(default=None, max_length=100)
    zone: MemberZone | Literal["Not Yet Available"]


class GetVouchersRequest(BaseModel):
    """Optional class-year filter for public voucher discovery."""

    model_config = ConfigDict(extra="ignore")

    graduation_year: int | None = Field(default=None, ge=1966, le=datetime.now(UTC).year)


class PublicVoucher(BaseModel):
    """Minimum public identity needed to select a registration voucher."""

    voucher_id: int = Field(gt=0)
    fullname: str = Field(min_length=1, max_length=100)
    graduation_year: int | None = None
    chapter_id: int = Field(gt=0)


class VoucherListResponse(BaseModel):
    """Bounded public voucher list without contact or authorization metadata."""

    status: int = 200
    message: str = "Vouchers retrieved successfully"
    total: int = Field(ge=0)
    vouchers: list[PublicVoucher]


class PendingVouch(BaseModel):
    """Registrant details visible only to the voucher assigned to the row."""

    vouch_id: int = Field(gt=0)
    user_id: int = Field(gt=0)
    fullname: str = Field(min_length=1, max_length=100)
    email: EmailStr
    graduation_year: int | None = None
    nick_name: str | None = Field(default=None, max_length=255)
    status: Literal["pending"] = "pending"
    created_at: str


class PendingVouchesResponse(BaseModel):
    """Owned pending voucher requests for the current active voucher."""

    status: int = 200
    message: str = "Pending vouches retrieved successfully"
    total: int = Field(ge=0)
    pending: list[PendingVouch]


class VouchActionRequest(BaseModel):
    """One bounded approve or deny decision for an owned pending vouch."""

    model_config = ConfigDict(extra="ignore")

    vouch_id: int = Field(gt=0)
    action: Literal["approve", "deny", "reject"]
    reason: str | None = Field(default=None, max_length=1000)

    @field_validator("action", mode="before")
    @classmethod
    def normalize_action(cls, value: object) -> object:
        """Accept the frontend's reject alias while storing the legacy denied state."""
        return value.strip().casefold() if isinstance(value, str) else value

    @field_validator("reason", mode="before")
    @classmethod
    def normalize_reason(cls, value: object) -> object:
        """Store blank optional reasons as null."""
        if value is None or not isinstance(value, str):
            return value
        normalized = value.strip()
        return normalized or None

    @property
    def decision(self) -> Literal["approve", "deny"]:
        """Return the canonical decision used by service and database code."""
        return "approve" if self.action == "approve" else "deny"


class VouchActionResponse(BaseModel):
    """Explicit result of one committed voucher decision."""

    status: int = 200
    message: str
    vouch_id: int = Field(gt=0)
    register_id: int = Field(gt=0)
    action: Literal["approve", "deny"]
    vouch_status: Literal["approved", "denied"]
    account_approved: bool
    account_active: bool


class UserChapter(BaseModel):
    """Bounded assignment projection for an authorized user lookup."""

    category_id: int
    user_id: int
    year: str
    location: str
    joined_at: str
    chapter_id: int | None = None
    chapter_name: str | None = None
    is_enabled: bool | None = None


class UserChapterResponse(BaseModel):
    """Legacy-compatible response for one authorized chapter assignment lookup."""

    status: int = 200
    message: str
    user_id: int | None = None
    chapter: UserChapter | None


class GetSetupParametersRequest(BaseModel):
    """Bounded legacy setup-name lookup input."""

    model_config = ConfigDict(extra="ignore")

    action_type: str = Field(min_length=1, max_length=100)

    @field_validator("action_type", mode="before")
    @classmethod
    def normalize_action_type(cls, value: object) -> object:
        """Trim the legacy action_type alias before database lookup."""
        return value.strip() if isinstance(value, str) else value


class SetupParameterData(BaseModel):
    """Explicit fields returned for one configuration record."""

    setup_id: int
    setup_name: str
    setup_value: str
    values: list[str]


class SetupParametersResponse(BaseModel):
    """Bounded response for one authenticated setup-parameter lookup."""

    status: int = 200
    message: str = "Setup parameters retrieved successfully"
    data: SetupParameterData


class GetUserProfileRequest(BaseModel):
    """Optional profile target; ordinary members remain restricted to self."""

    model_config = ConfigDict(extra="ignore")

    user_id: int | None = Field(default=None, gt=0)


class MemberApprovalRequest(BaseModel):
    """Legacy-compatible, bounded approval or rejection input."""

    model_config = ConfigDict(extra="ignore")

    user_id: int = Field(gt=0)
    action: Literal["approve", "reject"]
    reject_reason: str | None = Field(default=None, max_length=1000)

    @field_validator("action", mode="before")
    @classmethod
    def normalize_action(cls, value: object) -> object:
        """Accept the PHP route's case-insensitive action spelling."""
        return value.strip().casefold() if isinstance(value, str) else value

    @field_validator("reject_reason", mode="before")
    @classmethod
    def normalize_reject_reason(cls, value: object) -> object:
        """Trim an optional reason and collapse blank text to no reason."""
        if not isinstance(value, str):
            return value
        normalized = value.strip()
        return normalized or None

    @model_validator(mode="after")
    def discard_reason_for_approval(self) -> MemberApprovalRequest:
        """Never carry rejection text into an approval side effect."""
        if self.action == "approve":
            self.reject_reason = None
        return self


class MemberApprovalUser(BaseModel):
    """Credential-free administrator projection after an approval decision."""

    id: int
    fullname: str | None = None
    email: EmailStr
    user_role: str | None = None
    active: bool
    is_approved: bool
    profile_status: str


class MemberApprovalResponse(BaseModel):
    """Compatibility response for the administrator approval screen."""

    status: int = 200
    message: str
    user: MemberApprovalUser


AssignableUserRole = Literal[
    "alumni",
    "manager",
    "admin",
    "super admin",
    "approval admin",
    "content admin",
    "storekeeper admin",
    "event admin",
    "finance admin",
]


class ManageMemberAccountRequest(BaseModel):
    """Bounded input for one account-state or reviewed role transition."""

    model_config = ConfigDict(extra="ignore")

    user_id: int | None = Field(default=None, gt=0)
    action: Literal["activate", "deactivate"] | None = None
    user_role: AssignableUserRole | None = None

    @field_validator("action", mode="before")
    @classmethod
    def normalize_account_action(cls, value: object) -> object:
        """Accept the PHP route's case-insensitive action spelling."""
        return value.strip().casefold() if isinstance(value, str) else value

    @field_validator("user_role", mode="before")
    @classmethod
    def normalize_requested_role(cls, value: object) -> object:
        """Normalize reviewed aliases to stable account-role spellings."""
        if not isinstance(value, str):
            return value
        normalized = " ".join(value.replace("_", " ").replace("-", " ").split()).casefold()
        aliases = {
            "member": "alumni",
            "administrator": "admin",
            "superadmin": "super admin",
            "super administrator": "super admin",
            "approval administrator": "approval admin",
            "content administrator": "content admin",
            "storekeeper administrator": "storekeeper admin",
            "event administrator": "event admin",
            "finance administrator": "finance admin",
        }
        return aliases.get(normalized, normalized or None)

    @model_validator(mode="after")
    def require_account_operation(self) -> ManageMemberAccountRequest:
        """Require exactly one state transition or reviewed role transition."""
        if (self.action is None) == (self.user_role is None):
            raise ValueError("exactly one of action or user_role is required")
        return self


class ManagedMemberAccount(BaseModel):
    """Credential-free account projection after activation or deactivation."""

    id: int
    fullname: str | None = None
    email: EmailStr
    phone: str | None = None
    user_role: str | None = None
    active: bool
    profile_status: str


class ManageMemberAccountResponse(BaseModel):
    """Compatibility response for account activation and deactivation."""

    status: int = 200
    message: str
    user: ManagedMemberAccount


PROFILE_VISIBILITY_FIELDS = (
    "avatar",
    "phone",
    "alternative_phone",
    "birth_date",
    "residential_address",
    "area",
    "city",
    "employment_status",
    "occupation",
    "industry_sector",
    "years_of_experience",
    "is_volunteer",
    "socials",
    "facebook",
    "tiktok",
)


class GetProfileVisibilityRequest(BaseModel):
    """Optional visibility target; ordinary members remain restricted to self."""

    model_config = ConfigDict(extra="ignore")

    user_id: int | None = Field(default=None, gt=0)


class UpdateProfileVisibilityRequest(GetProfileVisibilityRequest):
    """Allowlisted whole-profile and per-field visibility changes."""

    is_visible: bool | None = None
    avatar_visible: bool | None = None
    phone_visible: bool | None = None
    alternative_phone_visible: bool | None = None
    birth_date_visible: bool | None = None
    residential_address_visible: bool | None = None
    area_visible: bool | None = None
    city_visible: bool | None = None
    employment_status_visible: bool | None = None
    occupation_visible: bool | None = None
    industry_sector_visible: bool | None = None
    years_of_experience_visible: bool | None = None
    is_volunteer_visible: bool | None = None
    socials_visible: bool | None = None
    facebook_visible: bool | None = None
    tiktok_visible: bool | None = None

    @model_validator(mode="after")
    def require_visibility_change(self) -> UpdateProfileVisibilityRequest:
        """Reject requests that contain no allowlisted visibility update."""
        if self.is_visible is not None:
            return self
        if any(
            getattr(self, f"{field}_visible") is not None for field in PROFILE_VISIBILITY_FIELDS
        ):
            return self
        raise ValueError("at least one visibility field is required")

    def field_changes(self) -> dict[str, bool]:
        """Return only explicitly supplied per-field visibility values."""
        return {
            field: value
            for field in PROFILE_VISIBILITY_FIELDS
            if (value := getattr(self, f"{field}_visible")) is not None
        }


class ProfileFieldVisibility(BaseModel):
    """Complete public/private visibility map returned to authorized users."""

    avatar: Literal["public", "private"]
    phone: Literal["public", "private"]
    alternative_phone: Literal["public", "private"]
    birth_date: Literal["public", "private"]
    residential_address: Literal["public", "private"]
    area: Literal["public", "private"]
    city: Literal["public", "private"]
    employment_status: Literal["public", "private"]
    occupation: Literal["public", "private"]
    industry_sector: Literal["public", "private"]
    years_of_experience: Literal["public", "private"]
    is_volunteer: Literal["public", "private"]
    socials: Literal["public", "private"]
    facebook: Literal["public", "private"]
    tiktok: Literal["public", "private"]


class ProfileVisibilityResponse(BaseModel):
    """Legacy-compatible visibility state with a bounded field map."""

    status: int = 200
    message: str
    user_id: int
    is_visible: bool
    field_visibility: ProfileFieldVisibility


class GetMembersRequest(BaseModel):
    """Bounded directory or account-management listing filters."""

    model_config = ConfigDict(extra="ignore")

    action_type: Literal["approved", "pending_approval", "all_users"] = "approved"
    user_id: int | None = Field(default=None, gt=0)
    search: str | None = Field(default=None, max_length=100)
    year: int | None = Field(default=None, ge=1900, le=2100)
    page: int = Field(default=1, ge=1)
    limit: int = Field(default=50, ge=1, le=100)

    @field_validator("action_type", mode="before")
    @classmethod
    def normalize_action_type(cls, value: object) -> object:
        """Accept active PHP/frontend spellings without an unsafe fallback."""
        if value is None or not isinstance(value, str):
            return value
        normalized = " ".join(value.replace("_", " ").split()).casefold()
        return {
            "approved": "approved",
            "pending approval": "pending_approval",
            "all users": "all_users",
        }.get(normalized, value)

    @field_validator("search", mode="before")
    @classmethod
    def normalize_search(cls, value: object) -> object:
        """Trim a bounded search term and collapse blank text to no filter."""
        if not isinstance(value, str):
            return value
        normalized = " ".join(value.split())
        return normalized or None


class ProfileUpdateDetails(BaseModel):
    """Allowlisted extended-profile fields accepted from JSON or multipart input."""

    model_config = ConfigDict(extra="ignore")

    linkedin: str | None = Field(default=None, max_length=255)
    twitter: str | None = Field(default=None, max_length=255)
    facebook: str | None = Field(default=None, max_length=255)
    website: str | None = Field(default=None, max_length=255)
    instagram: str | None = Field(default=None, max_length=255)
    tiktok: str | None = Field(default=None, max_length=255)
    current_company: str | None = Field(default=None, max_length=200)
    current_position: str | None = Field(default=None, max_length=200)
    city: str | None = Field(default=None, max_length=100)
    country: str | None = Field(default=None, max_length=100)
    skills: str | None = Field(default=None, max_length=5000)
    achievements: str | None = Field(default=None, max_length=5000)

    @field_validator("*", mode="before")
    @classmethod
    def trim_profile_text(cls, value: object) -> object:
        """Match PHP trimming while retaining an intentional empty-string clear."""
        return value.strip() if isinstance(value, str) else value

    def changes(self) -> dict[str, str | None]:
        """Return only fields explicitly supplied by the caller."""
        return {
            field: (
                ""
                if field in {"instagram", "tiktok"} and getattr(self, field) is None
                else getattr(self, field)
            )
            for field in self.model_fields_set
        }


class UpdateProfileRequest(ProfileUpdateDetails):
    """Bounded partial profile update; omitted fields remain untouched."""

    user_id: int | None = Field(default=None, gt=0)
    first_name: str | None = Field(default=None, max_length=50)
    last_name: str | None = Field(default=None, max_length=50)
    phone: str | None = Field(default=None, max_length=20)
    bio: str | None = Field(default=None, max_length=5000)
    graduation_year: int | None = Field(default=None, ge=1900, le=2100)
    department: str | None = Field(default=None, max_length=200)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^(?:\d{4})?$")
    birth_date: date | None = None
    name_in_school: str | None = Field(default=None, max_length=200)
    alternative_phone: str | None = Field(default=None, max_length=20)
    house_color: str | None = Field(default=None, max_length=50)
    residential_address: str | None = Field(default=None, max_length=5000)
    area: str | None = Field(default=None, max_length=100)
    city: str | None = Field(default=None, max_length=100)
    employment_status: str | None = Field(default=None, max_length=100)
    occupation: str | None = Field(default=None, max_length=5000)
    industry_sector: str | None = Field(default=None, max_length=5000)
    years_of_experience: str | None = Field(default=None, max_length=50)
    nick_name: str | None = Field(default=None, max_length=255)
    state: str | None = Field(default=None, max_length=255)
    is_coordinator: bool | None = None
    is_volunteer: bool | None = None
    profile: ProfileUpdateDetails | None = None

    @field_validator("graduation_year", "chapter_id", "birth_date", mode="before")
    @classmethod
    def empty_scalar_as_null(cls, value: object) -> object:
        """Allow browser forms to clear nullable date/year values with an empty string."""
        return None if isinstance(value, str) and not value.strip() else value

    @model_validator(mode="after")
    def require_non_null_chapter(self) -> UpdateProfileRequest:
        """The legacy chapter foreign key is non-null and cannot be cleared."""
        if "chapter_id" in self.model_fields_set and self.chapter_id is None:
            raise ValueError("chapter_id cannot be empty")
        return self

    def user_changes(self) -> dict[str, object]:
        """Return only explicitly supplied user-table fields."""
        fields = {
            "first_name",
            "last_name",
            "phone",
            "bio",
            "graduation_year",
            "department",
            "chapter_id",
            "year",
            "birth_date",
            "name_in_school",
            "alternative_phone",
            "house_color",
            "residential_address",
            "area",
            "city",
            "employment_status",
            "occupation",
            "industry_sector",
            "years_of_experience",
            "nick_name",
            "state",
            "is_coordinator",
            "is_volunteer",
        }
        return {field: getattr(self, field) for field in fields & self.model_fields_set}

    def profile_changes(self) -> dict[str, str | None]:
        """Merge flat legacy and nested frontend profile fields; nested values win."""
        fields = set(ProfileUpdateDetails.model_fields)
        changes = {field: getattr(self, field) for field in fields & self.model_fields_set}
        if self.profile is not None:
            changes.update(self.profile.changes())
        return changes


class MemberDirectoryProfile(BaseModel):
    """Visibility-filtered social and professional directory fields."""

    linkedin: str | None = None
    twitter: str | None = None
    tiktok: str | None = None
    facebook: str | None = None
    website: str | None = None
    instagram: str | None = None
    current_company: str | None = None
    current_position: str | None = None
    city: str | None = None
    country: str | None = None
    year: str | None = None
    is_visible: bool
    field_visibility: ProfileFieldVisibility


class MemberDirectoryUser(BaseModel):
    """Credential-free member projection with server-enforced field privacy."""

    id: int
    fullname: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    graduation_year: int | None = None
    name_in_school: str | None = None
    nick_name: str | None = None
    house_color: str | None = None
    avatar: str | None = None
    bio: str | None = None
    phone: str | None = None
    alternative_phone: str | None = None
    birth_date: date | None = None
    residential_address: str | None = None
    area: str | None = None
    city: str | None = None
    state: str | None = None
    employment_status: str | None = None
    occupation: str | None = None
    industry_sector: str | None = None
    years_of_experience: str | None = None
    is_coordinator: bool
    is_volunteer: bool | None = None
    user_role: str | None = None
    active: bool
    email_verified: bool
    is_approved: bool
    zone_id: int | None = None
    zone_name: str | None = None
    city_id: int | None = None
    profile: MemberDirectoryProfile


class AdministrativeMemberUser(BaseModel):
    """Minimum account-management projection; no credential/token fields."""

    id: int
    fullname: str | None = None
    email: str
    phone: str | None = None
    graduation_year: int | None = None
    user_role: str | None = None
    active: bool
    profile_status: str
    is_approved: bool
    email_verified: bool


class MemberDirectoryResponse(BaseModel):
    """Paginated response for approved member discovery."""

    status: int = 200
    message: str = "Users retrieved successfully"
    count: int
    total: int
    page: int
    limit: int
    has_more: bool
    users: list[MemberDirectoryUser]


class AdministrativeMemberListResponse(BaseModel):
    """Paginated response for downward account administration."""

    status: int = 200
    message: str = "Users retrieved successfully"
    count: int
    total: int
    page: int
    limit: int
    has_more: bool
    users: list[AdministrativeMemberUser]


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


class ProfileUpdateUser(BaseModel):
    """Credential-free fresh user state returned after a profile update."""

    id: int
    user_code: str | None = None
    email: str
    fullname: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    phone: str | None = None
    user_role: str | None = None
    avatar: str | None = None
    active: bool
    email_verified: bool
    is_approved: bool
    chapter_id: int
    graduation_year: int | None = None
    department: str | None = None
    bio: str | None = None
    name_in_school: str | None = None
    alternative_phone: str | None = None
    birth_date: date | None = None
    house_color: str | None = None
    is_coordinator: bool
    residential_address: str | None = None
    area: str | None = None
    city: str | None = None
    employment_status: str | None = None
    occupation: str | None = None
    industry_sector: str | None = None
    years_of_experience: str | None = None
    is_volunteer: bool
    nick_name: str
    state: str
    year: str | None = None
    onboarding_completion: bool


class UpdateProfileResponse(BaseModel):
    """Legacy-compatible fresh profile envelope without secret account columns."""

    status: int = 200
    message: str = "Profile updated successfully"
    user: ProfileUpdateUser
    profile: MemberProfileDetails
    zone_id: int | None = None
    zone_name: str | None = None
    city_id: int | None = None
