"""Authorized member profile use cases."""

from __future__ import annotations

import calendar
import json
import secrets
from datetime import UTC, date, datetime, timedelta
from typing import Any, Literal
from urllib.parse import urljoin
from zoneinfo import ZoneInfo

import structlog
from sqlalchemy.exc import IntegrityError, SQLAlchemyError
from sqlalchemy.orm import Session

from app.authorization.policy import (
    AuthorizationFacts,
    Permission,
    can_act_on_vouch,
    can_change_account_role,
    can_manage_account_target,
    has_permission,
    manageable_account_role_aliases,
    recognized_role,
    role_storage_value,
)
from app.core.config import Settings
from app.core.errors import MailDeliveryError
from app.core.security import PasswordService
from app.integrations.alumni_import import AlumniImportRow
from app.integrations.geography_import import GeographyImportRow
from app.integrations.mail import Mailer
from app.integrations.uploads import AvatarStorage, PreparedAvatar
from app.repositories.members import MemberRepository
from app.schemas.members import (
    PROFILE_VISIBILITY_FIELDS,
    AdministrativeMemberListResponse,
    AdministrativeMemberUser,
    AlumniImportResponse,
    AlumniImportResult,
    AlumniImportSummary,
    AlumniStats,
    AlumniStatsResponse,
    BirthdayAnnouncement,
    BirthdayListResponse,
    ChapterListResponse,
    ChapterSummary,
    CityListResponse,
    GetBirthdaysRequest,
    GetMembersRequest,
    GetVouchersRequest,
    GetZoneMembersRequest,
    ManageCityRequest,
    ManageCityResponse,
    ManagedMemberAccount,
    ManageMemberAccountResponse,
    ManageZoneRequest,
    ManageZoneResponse,
    MemberApprovalResponse,
    MemberApprovalUser,
    MemberDirectoryProfile,
    MemberDirectoryResponse,
    MemberDirectoryUser,
    MemberProfileDetails,
    MemberZone,
    MyZoneResponse,
    PendingVouch,
    PendingVouchesResponse,
    ProfileFieldVisibility,
    ProfileUpdateUser,
    ProfileVisibilityResponse,
    PublicCity,
    PublicVoucher,
    PublicZone,
    PublicZoneCoordinator,
    SetupParameterData,
    SetupParametersResponse,
    UpdateProfileRequest,
    UpdateProfileResponse,
    UploadZonesCitiesResponse,
    UploadZonesCitiesSummary,
    UserChapter,
    UserChapterResponse,
    UserProfileResponse,
    VouchActionRequest,
    VouchActionResponse,
    VoucherListResponse,
    ZoneCity,
    ZoneListResponse,
    ZoneMemberListResponse,
    ZoneMemberUser,
)

logger = structlog.get_logger(__name__)

BIRTHDAY_TIME_ZONE = ZoneInfo("Africa/Lagos")
MAX_BIRTHDAY_CANDIDATES = 10_000
ALUMNI_MEMBER_GROUP_ID = 2
_NO_PASSWORD = 0
_NO_VERIFICATION_TOKEN: str | None = None


class MemberProfileError(Exception):
    """A member profile request failed an account or authorization rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class BirthdayLookupError(Exception):
    """A birthday lookup failed a current-account or resource-bound rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MemberApprovalError(Exception):
    """A member approval request failed a state or authorization rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MemberAccountError(Exception):
    """An account activation or deactivation failed a state or authorization rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class ProfileVisibilityError(Exception):
    """A profile-visibility request failed validation or authorization."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MemberDirectoryError(Exception):
    """A member-directory request failed authorization or account-state checks."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class ZoneMembershipError(Exception):
    """A zone roster or self-zone lookup failed a current-state rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class GeographyManagementError(Exception):
    """A privileged zone or city mutation failed a validated business rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class AlumniStatsError(Exception):
    """An alumni-statistics request failed the current account-state check."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class ChapterLookupError(Exception):
    """A protected user-chapter lookup failed authorization or account checks."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class SetupParametersError(Exception):
    """A setup-parameter lookup failed an account-state or lookup rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class VoucherError(Exception):
    """Voucher discovery or an owned decision failed a bounded business rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class ProfileUpdateError(Exception):
    """A profile update failed validation, authorization, or target checks."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class AlumniImportError(Exception):
    """A roster import failed authorization, integrity, or configuration checks."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MemberService:
    """Build bounded member projections after current-state authorization."""

    def __init__(
        self,
        session: Session,
        settings: Settings,
        mailer: Mailer | None = None,
    ) -> None:
        self._session = session
        self._settings = settings
        self._mailer = mailer
        self._repository = MemberRepository(session)
        self._passwords = PasswordService()

    def get_alumni_stats(self, actor_user_id: int) -> AlumniStatsResponse:
        """Return non-identifying directory aggregates to a current active account."""
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise AlumniStatsError(
                    "alumni_stats_actor_unavailable",
                    "Authentication required",
                    401,
                )
            return AlumniStatsResponse(
                stats=AlumniStats.model_validate(self._repository.alumni_stats())
            )

    def get_birthdays(
        self,
        actor_user_id: int,
        request: GetBirthdaysRequest,
        *,
        today: date | None = None,
    ) -> BirthdayListResponse:
        """Return privacy-aware birthday cards using the legacy Lagos calendar rules."""
        reference_date = today or datetime.now(BIRTHDAY_TIME_ZONE).date()
        months = self._birthday_candidate_months(request, reference_date)
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise BirthdayLookupError(
                    "birthdays_actor_unavailable",
                    "Authentication required",
                    401,
                )
            rows = self._repository.birthday_candidates(
                months,
                limit=MAX_BIRTHDAY_CANDIDATES + 1,
            )
            if len(rows) > MAX_BIRTHDAY_CANDIDATES:
                raise BirthdayLookupError(
                    "birthdays_candidate_limit_exceeded",
                    "Birthday catalogue is temporarily too large to process",
                    503,
                )

            birthdays: list[BirthdayAnnouncement] = []
            window = 6 if request.scope == "week" else request.days - 1
            requested_month = request.month or reference_date.month
            for row in rows:
                user_id = int(row["id"])
                visibility = self._decode_visibility_map(row.get("field_visibility"))
                if not visibility.get("birth_date", True):
                    continue
                if not request.include_self and user_id == actor_user_id:
                    continue

                dob_value = row.get("birth_date")
                if isinstance(dob_value, datetime):
                    dob = dob_value.date()
                elif isinstance(dob_value, date):
                    dob = dob_value
                else:
                    continue
                occurrence = self._next_birthday(dob, reference_date)
                days_until = (occurrence - reference_date).days
                if request.scope == "today" and days_until != 0:
                    continue
                if request.scope in {"week", "upcoming"} and days_until > window:
                    continue
                if request.scope == "month" and dob.month != requested_month:
                    continue

                fullname = self._birthday_fullname(row)
                class_label = self._birthday_class_label(row)
                is_today = days_until == 0
                birthdays.append(
                    BirthdayAnnouncement(
                        user_id=user_id,
                        fullname=fullname,
                        name_in_school=row.get("name_in_school"),
                        avatar=(
                            self._absolute_avatar(row.get("avatar"))
                            if user_id == actor_user_id or visibility.get("avatar", True)
                            else None
                        ),
                        class_label=class_label,
                        date=occurrence,
                        days_until=days_until,
                        is_today=is_today,
                        is_self=user_id == actor_user_id,
                        message=(
                            "It's their birthday today!"
                            if is_today
                            else f"Birthday in {days_until} day{'' if days_until == 1 else 's'}"
                        ),
                    )
                )

        birthdays.sort(key=lambda item: (item.days_until, item.fullname.casefold(), item.user_id))
        total = len(birthdays)
        returned_birthdays = birthdays[: request.limit]
        return BirthdayListResponse(
            message=(
                "Birthdays retrieved successfully"
                if total
                else "No birthdays found for this period"
            ),
            scope=request.scope,
            date=reference_date,
            total=total,
            returned=len(returned_birthdays),
            birthdays=returned_birthdays,
        )

    def get_chapters(
        self,
        actor_user_id: int | None,
        requested_user_id: int | None,
    ) -> ChapterListResponse | UserChapterResponse:
        """List public chapters or return one authorized user's assignment."""
        with self._session.begin():
            if requested_user_id is None:
                rows = self._repository.enabled_chapters()
                chapters = [
                    ChapterSummary(
                        id=int(row["id"]),
                        chapter_name=str(row["chapter_name"]),
                        location=str(row["location"]),
                        is_enabled=bool(row["is_enabled"]),
                        created_at=str(row["created_at"]),
                    )
                    for row in rows
                ]
                return ChapterListResponse(total=len(chapters), chapters=chapters)

            if actor_user_id is None:
                raise ChapterLookupError(
                    "chapter_authentication_required",
                    "Authentication required",
                    401,
                )
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise ChapterLookupError(
                    "chapter_actor_unavailable",
                    "Authentication required",
                    401,
                )
            target = (
                actor
                if actor_user_id == requested_user_id
                else self._repository.authorization_account(requested_user_id)
            )
            if target is None:
                raise ChapterLookupError("chapter_user_not_found", "User not found", 404)
            if actor_user_id != requested_user_id:
                facts = AuthorizationFacts(
                    user_id=actor_user_id,
                    user_role=actor.get("user_role"),
                    is_coordinator=bool(actor.get("is_coordinator")),
                )
                if not can_manage_account_target(facts, target.get("user_role")):
                    raise ChapterLookupError(
                        "chapter_forbidden",
                        "You cannot view this user's chapter assignment",
                        403,
                    )

            row = self._repository.user_chapter(requested_user_id)
            if row is None:
                return UserChapterResponse(
                    message="User is not assigned to any chapter",
                    chapter=None,
                )
            return UserChapterResponse(
                message="User chapter retrieved successfully",
                user_id=requested_user_id,
                chapter=UserChapter(
                    category_id=int(row["category_id"]),
                    user_id=int(row["user_id"]),
                    year=str(row["year"]),
                    location=str(row["location"]),
                    joined_at=str(row["joined_at"]),
                    chapter_id=(int(row["chapter_id"]) if row.get("chapter_id") else None),
                    chapter_name=row.get("chapter_name"),
                    is_enabled=(
                        bool(row["is_enabled"]) if row.get("is_enabled") is not None else None
                    ),
                ),
            )

    def get_cities(self) -> CityListResponse:
        """Return the public city/zone catalogue required before authentication."""
        with self._session.begin():
            cities = [
                PublicCity(
                    city_id=int(row["city_id"]),
                    city=str(row["city"]),
                    chapter_id=int(row["chapter_id"]),
                    zone_id=int(row["zone_id"]),
                    zone=row.get("zone"),
                )
                for row in self._repository.public_cities()
            ]
        return CityListResponse(data=cities)

    def get_zones(self) -> ZoneListResponse:
        """Return public welfare zones with privacy-aware eligible coordinators."""
        with self._session.begin():
            city_rows = self._repository.public_zone_cities()
            cities_by_zone: dict[int, list[ZoneCity]] = {}
            for row in city_rows:
                cities_by_zone.setdefault(int(row["zone_id"]), []).append(
                    ZoneCity(city_id=int(row["city_id"]), city=str(row["city"]))
                )
            zones = [
                PublicZone(
                    zone_id=int(row["zone_id"]),
                    zone=str(row["zone"]),
                    chapter_id=int(row["chapter_id"]),
                    coordinator=self._public_zone_coordinator(row),
                    cities=cities_by_zone.get(int(row["zone_id"]), []),
                )
                for row in self._repository.public_zones()
            ]
        return ZoneListResponse(data=zones)

    def manage_zone(
        self,
        actor_user_id: int,
        request: ManageZoneRequest,
    ) -> ManageZoneResponse:
        """Create, update, or delete one zone within a serialized validated transaction."""
        changed_fields: tuple[str, ...] = ()
        affected_zone_id: int | None = request.zone_id
        with self._session.begin():
            self._authorize_geography_mutation(actor_user_id)
            zones, cities = self._repository.lock_geography_catalogue()

            if request.action == "create":
                if request.zone is None:
                    raise RuntimeError("Validated zone name is missing")
                self._ensure_unique_geography_name(
                    request.zone,
                    zones,
                    name_field="zone",
                    id_field="zone_id",
                    conflict_code="zone_already_exists",
                    conflict_message="Zone already exists",
                )
                chapter_id = request.chapter_id or 1
                self._require_geography_chapter(chapter_id)
                self._validate_geography_coordinator(request.coordinator_user_id, chapter_id)
                affected_zone_id = self._repository.insert_zone(
                    zone=request.zone,
                    coordinator_user_id=request.coordinator_user_id,
                    chapter_id=chapter_id,
                    created_at=datetime.now(UTC).replace(tzinfo=None),
                )
                response = ManageZoneResponse(
                    message="Zone created successfully",
                    zone_id=affected_zone_id,
                )
                changed_fields = ("chapter_id", "coordinator_user_id", "zone")
            else:
                zone_id = request.zone_id
                if zone_id is None:
                    raise RuntimeError("Validated zone identifier is missing")
                target = self._geography_row(zones, "zone_id", zone_id)
                if target is None:
                    raise GeographyManagementError("zone_not_found", "Zone not found", 404)

                if request.action == "delete":
                    if any(int(city["zone_id"]) == zone_id for city in cities):
                        raise GeographyManagementError(
                            "zone_has_cities",
                            "Delete or move the zone's cities before deleting the zone",
                            409,
                        )
                    self._repository.delete_zone(zone_id)
                    response = ManageZoneResponse(message="Zone deleted successfully")
                    changed_fields = ("deleted",)
                else:
                    supplied = request.model_fields_set
                    changes: dict[str, object] = {}
                    if "zone" in supplied:
                        if request.zone is None:
                            raise RuntimeError("Validated zone name is missing")
                        self._ensure_unique_geography_name(
                            request.zone,
                            zones,
                            name_field="zone",
                            id_field="zone_id",
                            exclude_id=zone_id,
                            conflict_code="zone_already_exists",
                            conflict_message="Zone already exists",
                        )
                        if request.zone != str(target["zone"]):
                            changes["zone"] = request.zone

                    final_chapter_id = int(target["chapter_id"])
                    if "chapter_id" in supplied:
                        if request.chapter_id is None:
                            raise RuntimeError("Validated chapter identifier is missing")
                        self._require_geography_chapter(request.chapter_id)
                        final_chapter_id = request.chapter_id
                        if final_chapter_id != int(target["chapter_id"]):
                            changes["chapter_id"] = final_chapter_id

                    final_coordinator_id = target.get("coordinator_user_id")
                    if "coordinator_user_id" in supplied:
                        final_coordinator_id = request.coordinator_user_id
                        if final_coordinator_id != target.get("coordinator_user_id"):
                            changes["coordinator_user_id"] = final_coordinator_id
                    self._validate_geography_coordinator(final_coordinator_id, final_chapter_id)

                    if not changes:
                        raise GeographyManagementError(
                            "zone_unchanged",
                            "Zone already has the requested values",
                            409,
                        )
                    self._repository.update_zone(
                        zone_id,
                        changes=changes,
                        updated_at=datetime.now(UTC).replace(tzinfo=None),
                    )
                    if "chapter_id" in changes:
                        self._repository.update_zone_city_chapters(
                            zone_id,
                            chapter_id=final_chapter_id,
                        )
                    response = ManageZoneResponse(message="Zone updated successfully")
                    changed_fields = tuple(sorted(changes))

        logger.info(
            "geography_zone_mutated",
            actor_user_id=actor_user_id,
            zone_id=affected_zone_id,
            action=request.action,
            changed_fields=changed_fields,
        )
        return response

    def manage_city(
        self,
        actor_user_id: int,
        request: ManageCityRequest,
    ) -> ManageCityResponse:
        """Create, update, or delete one city without orphaning member locations."""
        changed_fields: tuple[str, ...] = ()
        affected_city_id: int | None = request.city_id
        with self._session.begin():
            self._authorize_geography_mutation(actor_user_id)
            zones, cities = self._repository.lock_geography_catalogue()

            if request.action == "create":
                if request.city is None or request.zone_id is None:
                    raise RuntimeError("Validated city fields are missing")
                self._ensure_unique_geography_name(
                    request.city,
                    cities,
                    name_field="city",
                    id_field="city_id",
                    conflict_code="city_already_exists",
                    conflict_message="City already exists",
                )
                zone = self._geography_row(zones, "zone_id", request.zone_id)
                if zone is None:
                    raise GeographyManagementError("zone_not_found", "Zone not found", 404)
                zone_chapter_id = int(zone["chapter_id"])
                if request.chapter_id is not None and request.chapter_id != zone_chapter_id:
                    raise GeographyManagementError(
                        "city_chapter_mismatch",
                        "City chapter must match its zone chapter",
                        409,
                    )
                affected_city_id = self._repository.insert_city(
                    city=request.city,
                    zone_id=request.zone_id,
                    chapter_id=zone_chapter_id,
                    created_at=datetime.now(UTC).replace(tzinfo=None),
                )
                response = ManageCityResponse(
                    message="City created successfully",
                    city_id=affected_city_id,
                )
                changed_fields = ("chapter_id", "city", "zone_id")
            else:
                city_id = request.city_id
                if city_id is None:
                    raise RuntimeError("Validated city identifier is missing")
                target = self._geography_row(cities, "city_id", city_id)
                if target is None:
                    raise GeographyManagementError("city_not_found", "City not found", 404)

                if request.action == "delete":
                    if self._repository.city_name_is_referenced(str(target["city"])):
                        raise GeographyManagementError(
                            "city_in_use",
                            "City is assigned to one or more member profiles",
                            409,
                        )
                    self._repository.delete_city(city_id)
                    response = ManageCityResponse(message="City deleted successfully")
                    changed_fields = ("deleted",)
                else:
                    supplied = request.model_fields_set
                    changes: dict[str, object] = {}
                    final_city = str(target["city"])
                    if "city" in supplied:
                        if request.city is None:
                            raise RuntimeError("Validated city name is missing")
                        self._ensure_unique_geography_name(
                            request.city,
                            cities,
                            name_field="city",
                            id_field="city_id",
                            exclude_id=city_id,
                            conflict_code="city_already_exists",
                            conflict_message="City already exists",
                        )
                        final_city = request.city
                        if self._geography_name_key(final_city) != self._geography_name_key(
                            str(target["city"])
                        ) and self._repository.city_name_is_referenced(str(target["city"])):
                            raise GeographyManagementError(
                                "city_in_use",
                                "City is assigned to one or more member profiles",
                                409,
                            )
                        if final_city != str(target["city"]):
                            changes["city"] = final_city

                    final_zone_id = (
                        request.zone_id if "zone_id" in supplied else int(target["zone_id"])
                    )
                    if final_zone_id is None:
                        raise RuntimeError("Validated zone identifier is missing")
                    zone = self._geography_row(zones, "zone_id", final_zone_id)
                    if zone is None:
                        raise GeographyManagementError("zone_not_found", "Zone not found", 404)
                    final_chapter_id = int(zone["chapter_id"])
                    if (
                        "chapter_id" in supplied
                        and request.chapter_id is not None
                        and request.chapter_id != final_chapter_id
                    ):
                        raise GeographyManagementError(
                            "city_chapter_mismatch",
                            "City chapter must match its zone chapter",
                            409,
                        )
                    if final_zone_id != int(target["zone_id"]):
                        changes["zone_id"] = final_zone_id
                    if final_chapter_id != int(target["chapter_id"]):
                        changes["chapter_id"] = final_chapter_id
                    if not changes:
                        raise GeographyManagementError(
                            "city_unchanged",
                            "City already has the requested values",
                            409,
                        )
                    self._repository.update_city(city_id, changes=changes)
                    response = ManageCityResponse(message="City updated successfully")
                    changed_fields = tuple(sorted(changes))

        logger.info(
            "geography_city_mutated",
            actor_user_id=actor_user_id,
            city_id=affected_city_id,
            action=request.action,
            changed_fields=changed_fields,
        )
        return response

    def import_alumni(
        self,
        actor_user_id: int,
        chapter_id: int,
        rows: tuple[AlumniImportRow, ...],
        peer_ip: str,
    ) -> AlumniImportResponse:
        """Reconcile a validated roster atomically without importing account privileges."""
        imported = 0
        updated = 0
        unchanged = 0
        results: list[AlumniImportResult] = []
        coordinator_requests_ignored = sum(row.coordinator_requested for row in rows)
        try:
            with self._session.begin():
                actor = self._repository.lock_alumni_import_actor(actor_user_id)
                if actor is None or not bool(actor.get("active")):
                    raise AlumniImportError(
                        "alumni_import_actor_unavailable",
                        "Authentication required",
                        401,
                    )
                facts = AuthorizationFacts(
                    user_id=actor_user_id,
                    user_role=actor.get("user_role"),
                    is_coordinator=bool(actor.get("is_coordinator")),
                )
                if not has_permission(facts, Permission.MANAGE_ACCOUNTS):
                    raise AlumniImportError(
                        "alumni_import_forbidden",
                        "Account-management permission required",
                        403,
                    )
                if not rows:
                    raise AlumniImportError(
                        "alumni_import_no_rows",
                        "The import must contain at least one record",
                        400,
                    )
                if not self._repository.alumni_import_chapter_exists(chapter_id):
                    raise AlumniImportError(
                        "alumni_import_chapter_unavailable",
                        "Selected chapter is unavailable",
                        400,
                    )
                if not self._repository.alumni_import_member_group_exists(ALUMNI_MEMBER_GROUP_ID):
                    raise AlumniImportError(
                        "alumni_import_configuration_unavailable",
                        "Alumni import is temporarily unavailable",
                        503,
                    )

                imported_city_keys = {row.city.casefold() for row in rows}
                known_city_keys = self._repository.alumni_import_cities(
                    chapter_id,
                    {row.city for row in rows},
                )
                missing_city_rows = [
                    row.source_row for row in rows if row.city.casefold() not in known_city_keys
                ]
                if imported_city_keys != known_city_keys or missing_city_rows:
                    first_row = min(missing_city_rows) if missing_city_rows else rows[0].source_row
                    raise AlumniImportError(
                        "alumni_import_city_unavailable",
                        f"Row {first_row} has a city outside the selected chapter",
                        400,
                    )

                accounts = self._repository.lock_alumni_import_accounts({row.email for row in rows})
                accounts_by_email = {
                    str(account["email"]).strip().casefold(): account for account in accounts
                }
                existing_ids = {int(account["id"]) for account in accounts}
                categories = self._repository.lock_alumni_import_categories(existing_ids)
                profiles = self._repository.lock_alumni_import_profiles(existing_ids)
                for account in accounts:
                    target_id = int(account["id"])
                    if target_id == actor_user_id or not can_manage_account_target(
                        facts, account.get("user_role")
                    ):
                        raise AlumniImportError(
                            "alumni_import_target_forbidden",
                            "The import includes an account this administrator cannot manage",
                            403,
                        )
                    if len(categories.get(target_id, [])) > 1:
                        raise AlumniImportError(
                            "alumni_import_membership_ambiguous",
                            "An existing imported account has multiple chapter memberships",
                            409,
                        )

                now = datetime.now(UTC).replace(tzinfo=None)
                private_visibility = json.dumps(
                    dict.fromkeys(PROFILE_VISIBILITY_FIELDS, False),
                    separators=(",", ":"),
                    sort_keys=True,
                )
                for row in rows:
                    existing = accounts_by_email.get(row.email)
                    year = str(row.graduation_year)
                    if existing is None:
                        user_id = self._repository.insert_alumni_import_user(
                            self._new_import_user_values(row, chapter_id, peer_ip, now)
                        )
                        self._repository.ensure_alumni_import_group(user_id, ALUMNI_MEMBER_GROUP_ID)
                        self._repository.insert_alumni_import_category(
                            user_id=user_id,
                            chapter_id=chapter_id,
                            year=year,
                            location=row.city,
                            created_at=now,
                        )
                        self._repository.insert_alumni_import_profile(
                            user_id=user_id,
                            chapter_id=chapter_id,
                            year=year,
                            city=row.city,
                            field_visibility=private_visibility,
                            created_at=now,
                        )
                        imported += 1
                        results.append(
                            AlumniImportResult(
                                row=row.source_row,
                                status="imported",
                                user_id=user_id,
                            )
                        )
                        continue

                    user_id = int(existing["id"])
                    changed = False
                    expected_user = self._existing_import_user_values(row, chapter_id)
                    user_changes = {
                        field: value
                        for field, value in expected_user.items()
                        if existing.get(field) != value
                    }
                    if user_changes:
                        self._repository.update_alumni_import_user(
                            user_id,
                            changes=user_changes,
                            updated_at=now,
                        )
                        changed = True
                    if self._repository.ensure_alumni_import_group(user_id, ALUMNI_MEMBER_GROUP_ID):
                        changed = True

                    member_categories = categories.get(user_id, [])
                    if not member_categories:
                        self._repository.insert_alumni_import_category(
                            user_id=user_id,
                            chapter_id=chapter_id,
                            year=year,
                            location=row.city,
                            created_at=now,
                        )
                        changed = True
                    else:
                        category = member_categories[0]
                        expected_category = {
                            "chapter_id": chapter_id,
                            "year": year,
                            "location": row.city,
                        }
                        if any(
                            category.get(field) != value
                            for field, value in expected_category.items()
                        ):
                            self._repository.update_alumni_import_category(
                                int(category["id"]),
                                chapter_id=chapter_id,
                                year=year,
                                location=row.city,
                            )
                            changed = True

                    profile = profiles.get(user_id)
                    if profile is None:
                        self._repository.insert_alumni_import_profile(
                            user_id=user_id,
                            chapter_id=chapter_id,
                            year=year,
                            city=row.city,
                            field_visibility=private_visibility,
                            created_at=now,
                        )
                        changed = True
                    else:
                        expected_profile = {
                            "chapter_id": chapter_id,
                            "year": year,
                            "city": row.city,
                        }
                        if any(
                            profile.get(field) != value for field, value in expected_profile.items()
                        ):
                            self._repository.update_alumni_import_profile(
                                int(profile["id"]),
                                chapter_id=chapter_id,
                                year=year,
                                city=row.city,
                                updated_at=now,
                            )
                            changed = True

                    outcome: Literal["updated", "unchanged"] = "updated" if changed else "unchanged"
                    updated += int(changed)
                    unchanged += int(not changed)
                    results.append(
                        AlumniImportResult(row=row.source_row, status=outcome, user_id=user_id)
                    )
        except AlumniImportError:
            raise
        except IntegrityError as exc:
            raise AlumniImportError(
                "alumni_import_conflict",
                "The roster conflicts with current account data; no rows were imported",
                409,
            ) from exc
        except SQLAlchemyError as exc:
            raise AlumniImportError(
                "alumni_import_unavailable",
                "Alumni import is temporarily unavailable",
                503,
            ) from exc

        summary = AlumniImportSummary(
            total=len(rows),
            imported=imported,
            updated=updated,
            unchanged=unchanged,
            coordinator_requests_ignored=coordinator_requests_ignored,
        )
        logger.info(
            "alumni_roster_imported",
            actor_user_id=actor_user_id,
            chapter_id=chapter_id,
            total=summary.total,
            imported=summary.imported,
            updated=summary.updated,
            unchanged=summary.unchanged,
            coordinator_requests_ignored=summary.coordinator_requests_ignored,
        )
        return AlumniImportResponse(
            message=(
                f"Import complete: {imported} imported, {updated} updated, {unchanged} unchanged"
            ),
            summary=summary,
            results=results,
        )

    def _new_import_user_values(
        self,
        row: AlumniImportRow,
        chapter_id: int,
        peer_ip: str,
        now: datetime,
    ) -> dict[str, object]:
        """Build one complete user row with server-owned account and privilege fields."""
        values = self._existing_import_user_values(row, chapter_id)
        values.update(
            {
                "ip_address": peer_ip,
                "username": row.email,
                "email": row.email,
                "password": self._passwords.hash(secrets.token_urlsafe(48)),
                "has_password": _NO_PASSWORD,
                "onboarding_completion": 1,
                "nick_name": "",
                "state": "",
                "country": "Nigeria",
                "created_on": int(now.replace(tzinfo=UTC).timestamp()),
                "userAccessCode": "",
                "profile_status": "active",
                "voucher": "",
                "resetKey": "",
                "user_code": self._generate_import_user_code(row.graduation_year, row.email),
                "user_role": "alumni",
                "department": "",
                "email_verified": 1,
                "verify_token": _NO_VERIFICATION_TOKEN,
                "is_approved": 1,
                "active": 1,
                "is_coordinator": 0,
                "created_at": now,
                "updated_at": now,
            }
        )
        return values

    @staticmethod
    def _existing_import_user_values(row: AlumniImportRow, chapter_id: int) -> dict[str, object]:
        """Return profile-only fields an import may reconcile on an existing account."""
        return {
            "chapter_id": chapter_id,
            "first_name": row.first_name,
            "last_name": row.last_name,
            "fullname": row.fullname,
            "name_in_school": row.name_in_school,
            "phone": row.phone,
            "alternative_phone": row.alternative_phone,
            "birth_date": row.birth_date,
            "house_color": row.house_color,
            "residential_address": row.residential_address,
            "area": row.area,
            "city": row.city,
            "employment_status": row.employment_status,
            "occupation": row.occupation,
            "industry_sector": row.industry_sector,
            "years_of_experience": row.years_of_experience,
            "is_volunteer": row.is_volunteer,
            "graduation_year": row.graduation_year,
            "year": str(row.graduation_year),
        }

    def _generate_import_user_code(self, graduation_year: int, email: str) -> str:
        """Generate the legacy-shaped compatibility code with deterministic retries."""
        for counter in range(1001):
            seed = email if counter == 0 else f"{email}|{counter}"
            legacy_hash = 0
            for character in seed.encode():
                legacy_hash = ((legacy_hash << 5) - legacy_hash + character) & 0xFFFFFFFF
            suffix = f"{legacy_hash:x}".rjust(6, "0")[:6]
            candidate = f"MBR-{graduation_year}-{suffix}"
            if not self._repository.alumni_import_user_code_exists(candidate):
                return candidate
        raise AlumniImportError(
            "alumni_import_code_unavailable",
            "Alumni import is temporarily unavailable",
            503,
        )

    def upload_zones_cities(
        self,
        actor_user_id: int,
        rows: tuple[GeographyImportRow, ...],
    ) -> UploadZonesCitiesResponse:
        """Reconcile one validated geography file in a single serialized transaction."""
        zones_added = 0
        cities_added = 0
        cities_updated = 0
        with self._session.begin():
            self._authorize_geography_mutation(actor_user_id)
            zones, cities = self._repository.lock_geography_catalogue()
            if not rows:
                raise GeographyManagementError(
                    "geography_import_no_rows",
                    "The file contains no geography rows",
                    400,
                )

            imported_cities: set[str] = set()
            imported_zones: dict[str, str] = {}
            for row in rows:
                zone_key = self._geography_name_key(row.zone)
                city_key = self._geography_name_key(row.city)
                if city_key in imported_cities:
                    raise GeographyManagementError(
                        "geography_import_duplicate_city",
                        "The file contains a duplicate city",
                        400,
                    )
                imported_cities.add(city_key)
                imported_zones.setdefault(zone_key, row.zone)

            zones_by_name: dict[str, list[dict[str, Any]]] = {}
            for zone in zones:
                zones_by_name.setdefault(self._geography_name_key(str(zone["zone"])), []).append(
                    zone
                )
            cities_by_name: dict[str, list[dict[str, Any]]] = {}
            for city in cities:
                cities_by_name.setdefault(self._geography_name_key(str(city["city"])), []).append(
                    city
                )

            zone_targets: dict[str, dict[str, Any] | None] = {}
            new_zone_required = False
            for zone_key in imported_zones:
                candidates = zones_by_name.get(zone_key, [])
                if len(candidates) > 1:
                    raise GeographyManagementError(
                        "geography_import_ambiguous_zone",
                        "An imported zone matches duplicate existing catalogue rows",
                        409,
                    )
                target = candidates[0] if candidates else None
                zone_targets[zone_key] = target
                if target is None:
                    new_zone_required = True
                else:
                    self._require_geography_chapter(int(target["chapter_id"]))

            city_targets: dict[str, dict[str, Any] | None] = {}
            for row in rows:
                city_key = self._geography_name_key(row.city)
                candidates = cities_by_name.get(city_key, [])
                if len(candidates) > 1:
                    raise GeographyManagementError(
                        "geography_import_ambiguous_city",
                        "An imported city matches duplicate existing catalogue rows",
                        409,
                    )
                city_targets[city_key] = candidates[0] if candidates else None

            if new_zone_required:
                self._require_geography_chapter(1)

            created_at = datetime.now(UTC).replace(tzinfo=None)
            for zone_key, zone_name in imported_zones.items():
                if zone_targets[zone_key] is not None:
                    continue
                zone_id = self._repository.insert_zone(
                    zone=zone_name,
                    coordinator_user_id=None,
                    chapter_id=1,
                    created_at=created_at,
                )
                zone_targets[zone_key] = {
                    "zone_id": zone_id,
                    "zone": zone_name,
                    "coordinator_user_id": None,
                    "chapter_id": 1,
                }
                zones_added += 1

            for row in rows:
                zone_target = zone_targets[self._geography_name_key(row.zone)]
                if zone_target is None:
                    raise RuntimeError("Validated import zone is unavailable")
                zone_id = int(zone_target["zone_id"])
                chapter_id = int(zone_target["chapter_id"])
                city_key = self._geography_name_key(row.city)
                city_target = city_targets[city_key]
                if city_target is None:
                    self._repository.insert_city(
                        city=row.city,
                        zone_id=zone_id,
                        chapter_id=chapter_id,
                        created_at=created_at,
                    )
                    cities_added += 1
                    continue
                changes: dict[str, object] = {}
                if int(city_target["zone_id"]) != zone_id:
                    changes["zone_id"] = zone_id
                if int(city_target["chapter_id"]) != chapter_id:
                    changes["chapter_id"] = chapter_id
                if changes:
                    self._repository.update_city(int(city_target["city_id"]), changes=changes)
                    cities_updated += 1

        summary = UploadZonesCitiesSummary(
            zones_added=zones_added,
            cities_added=cities_added,
            cities_updated=cities_updated,
            total_rows=len(rows),
        )
        logger.info(
            "geography_catalogue_imported",
            actor_user_id=actor_user_id,
            zones_added=summary.zones_added,
            cities_added=summary.cities_added,
            cities_updated=summary.cities_updated,
            total_rows=summary.total_rows,
        )
        return UploadZonesCitiesResponse(summary=summary)

    def _authorize_geography_mutation(self, actor_user_id: int) -> None:
        """Require an active current account with the reviewed zone-management permission."""
        actor = self._repository.lock_geography_actor(actor_user_id)
        if actor is None or not bool(actor.get("active")):
            raise GeographyManagementError(
                "geography_actor_unavailable",
                "Authentication required",
                401,
            )
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, Permission.MANAGE_ZONES):
            raise GeographyManagementError(
                "geography_forbidden",
                "Administrator access required",
                403,
            )

    def _require_geography_chapter(self, chapter_id: int) -> None:
        """Reject orphan chapter references before any catalogue write."""
        if not self._repository.geography_chapter_exists(chapter_id):
            raise GeographyManagementError("chapter_not_found", "Chapter not found", 404)

    def _validate_geography_coordinator(
        self,
        coordinator_user_id: int | None,
        chapter_id: int,
    ) -> None:
        """Require an eligible same-chapter member when a coordinator is assigned."""
        if coordinator_user_id is None:
            return
        coordinator_id = int(coordinator_user_id)
        account = self._repository.geography_coordinator_account(coordinator_id)
        if account is None:
            raise GeographyManagementError(
                "coordinator_not_found",
                "Coordinator user not found",
                404,
            )
        if (
            not bool(account.get("active"))
            or not bool(account.get("email_verified"))
            or not bool(account.get("is_approved"))
        ):
            raise GeographyManagementError(
                "coordinator_ineligible",
                "Coordinator must be an active approved account with verified email",
                409,
            )
        if int(account["chapter_id"]) != chapter_id:
            raise GeographyManagementError(
                "coordinator_chapter_mismatch",
                "Coordinator chapter must match the zone chapter",
                409,
            )

    @staticmethod
    def _geography_name_key(value: str) -> str:
        """Normalize catalogue names for duplicate and no-op comparisons."""
        return " ".join(value.split()).casefold()

    @classmethod
    def _ensure_unique_geography_name(
        cls,
        value: str,
        rows: list[dict[str, Any]],
        *,
        name_field: str,
        id_field: str,
        conflict_code: str,
        conflict_message: str,
        exclude_id: int | None = None,
    ) -> None:
        """Reject case- and whitespace-insensitive duplicates in a locked catalogue."""
        key = cls._geography_name_key(value)
        if any(
            cls._geography_name_key(str(row[name_field])) == key
            and (exclude_id is None or int(row[id_field]) != exclude_id)
            for row in rows
        ):
            raise GeographyManagementError(conflict_code, conflict_message, 409)

    @staticmethod
    def _geography_row(
        rows: list[dict[str, Any]],
        id_field: str,
        requested_id: int | None,
    ) -> dict[str, Any] | None:
        """Find one locked catalogue row by its validated positive identifier."""
        if requested_id is None:
            return None
        return next((row for row in rows if int(row[id_field]) == requested_id), None)

    def get_zone_members(
        self,
        actor_user_id: int,
        request: GetZoneMembersRequest,
    ) -> ZoneMemberListResponse:
        """Return one authenticated, privacy-filtered and paginated zone roster."""
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise ZoneMembershipError(
                    "zone_members_actor_unavailable",
                    "Authentication required",
                    401,
                )
            zone_row = self._repository.zone_with_coordinator(
                zone_id=request.zone_id,
                zone_name=request.zone,
            )
            if zone_row is None:
                raise ZoneMembershipError("zone_not_found", "Zone not found", 404)

            visible_members: list[ZoneMemberUser] = []
            for row in self._repository.zone_member_candidates(int(zone_row["zone_id"])):
                is_owner = int(row["user_id"]) == actor_user_id
                if (
                    not is_owner
                    and row.get("is_visible") is not None
                    and not bool(row.get("is_visible"))
                ):
                    continue
                visibility = self._decode_visibility_map(row.get("field_visibility"))
                if not is_owner and not visibility.get("city", True):
                    continue
                visible_members.append(
                    ZoneMemberUser(
                        user_id=int(row["user_id"]),
                        fullname=row.get("fullname"),
                        first_name=row.get("first_name"),
                        last_name=row.get("last_name"),
                        graduation_year=row.get("graduation_year"),
                        avatar=(
                            self._absolute_avatar(row.get("avatar"))
                            if is_owner or visibility.get("avatar", True)
                            else None
                        ),
                        phone=(
                            row.get("phone") if is_owner or visibility.get("phone", True) else None
                        ),
                        city=str(row["city"]).strip(),
                        is_coordinator=bool(row.get("is_coordinator")),
                    )
                )

            offset = (request.page - 1) * request.limit
            page_members = visible_members[offset : offset + request.limit]
            zone = MemberZone(
                zone_id=int(zone_row["zone_id"]),
                zone=str(zone_row["zone"]),
                coordinator=self._public_zone_coordinator(zone_row),
            )
        return ZoneMemberListResponse(
            zone=zone,
            count=len(page_members),
            total=len(visible_members),
            page=request.page,
            limit=request.limit,
            has_more=offset + len(page_members) < len(visible_members),
            users=page_members,
        )

    def get_my_zone(self, actor_user_id: int) -> MyZoneResponse:
        """Resolve the active principal's own city to one deterministic zone."""
        with self._session.begin():
            actor = self._repository.self_zone_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise ZoneMembershipError(
                    "my_zone_actor_unavailable",
                    "Authentication required",
                    401,
                )
            stored_city = actor.get("city")
            city = str(stored_city).strip() if stored_city is not None else None
            if not city:
                return MyZoneResponse(
                    message="Zone not yet available",
                    city=None,
                    zone="Not Yet Available",
                )
            zone_id = self._repository.city_zone_id(city)
            zone_row = (
                self._repository.zone_with_coordinator(zone_id=zone_id)
                if zone_id is not None
                else None
            )
            if zone_row is None:
                return MyZoneResponse(
                    message="Zone not yet available",
                    city=city,
                    zone="Not Yet Available",
                )
            zone = MemberZone(
                zone_id=int(zone_row["zone_id"]),
                zone=str(zone_row["zone"]),
                coordinator=self._public_zone_coordinator(zone_row),
            )
        return MyZoneResponse(
            message="Zone retrieved successfully",
            city=city,
            zone=zone,
        )

    def get_vouchers(self, request: GetVouchersRequest) -> VoucherListResponse:
        """List only the public fields needed by registration, optionally by class year."""
        with self._session.begin():
            vouchers = [
                PublicVoucher(
                    voucher_id=int(row["voucher_id"]),
                    fullname=str(row.get("fullname") or "Alumni member"),
                    graduation_year=row.get("graduation_year"),
                    chapter_id=int(row["chapter_id"]),
                )
                for row in self._repository.public_vouchers(request.graduation_year)
            ]
        return VoucherListResponse(total=len(vouchers), vouchers=vouchers)

    def get_pending_vouches(self, actor_user_id: int) -> PendingVouchesResponse:
        """Return only pending rows owned by a current active voucher."""
        with self._session.begin():
            actor = self._repository.voucher_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise VoucherError(
                    "voucher_actor_unavailable",
                    "Authentication required",
                    401,
                )
            if str(actor.get("voucher") or "").strip().casefold() != "yes":
                raise VoucherError(
                    "voucher_role_required",
                    "Access denied. Voucher role required.",
                    403,
                )
            pending = [
                PendingVouch(
                    vouch_id=int(row["vouch_id"]),
                    user_id=int(row["user_id"]),
                    fullname=str(row.get("fullname") or "Alumni member"),
                    email=str(row["email"]),
                    graduation_year=row.get("graduation_year"),
                    nick_name=row.get("nick_name"),
                    status="pending",
                    created_at=str(row["created_at"]),
                )
                for row in self._repository.pending_vouches(actor_user_id)
            ]
        return PendingVouchesResponse(total=len(pending), pending=pending)

    def decide_vouch(
        self,
        actor_user_id: int,
        request: VouchActionRequest,
    ) -> VouchActionResponse:
        """Commit one owned pending voucher decision and its account effect atomically."""
        managers: list[dict[str, Any]] = []
        previous_status: str
        now = datetime.now(UTC).replace(tzinfo=None)
        with self._session.begin():
            vouch = self._repository.lock_vouch(request.vouch_id)
            if vouch is None or not can_act_on_vouch(
                actor_user_id=actor_user_id,
                assigned_voucher_user_id=int(vouch.get("voucher_id") or 0),
            ):
                raise VoucherError(
                    "vouch_not_found",
                    "Vouch record not found",
                    404,
                )
            actor, registrant = self._repository.lock_vouch_accounts(
                actor_user_id,
                int(vouch["register_id"]),
            )
            if actor is None or not bool(actor.get("active")):
                raise VoucherError(
                    "voucher_actor_unavailable",
                    "Authentication required",
                    401,
                )
            if str(actor.get("voucher") or "").strip().casefold() != "yes":
                raise VoucherError(
                    "voucher_role_required",
                    "Access denied. Voucher role required.",
                    403,
                )
            if registrant is None:
                raise VoucherError(
                    "vouch_registrant_not_found",
                    "Registrant not found",
                    404,
                )
            status_value = getattr(vouch.get("status"), "value", vouch.get("status"))
            previous_status = str(status_value)
            if previous_status != "pending":
                raise VoucherError(
                    "vouch_already_actioned",
                    "This vouch has already been actioned",
                    409,
                )
            if not bool(registrant.get("email_verified")):
                raise VoucherError(
                    "vouch_email_unverified",
                    "Registrant email must be verified before a voucher decision",
                    409,
                )
            if bool(registrant.get("is_approved")):
                raise VoucherError(
                    "vouch_account_already_approved",
                    "Registrant account is already approved",
                    409,
                )

            decision = request.decision
            vouch_status: Literal["approved", "denied"] = (
                "approved" if decision == "approve" else "denied"
            )
            self._repository.update_vouch_decision(
                request.vouch_id,
                status=vouch_status,
                reason=request.reason,
                updated_at=now,
            )
            if decision == "approve":
                self._repository.update_approval_state(
                    int(registrant["id"]),
                    approved=True,
                    active=True,
                    profile_status="active",
                    updated_at=now,
                )
                managers = self._repository.account_manager_recipients()

            response = VouchActionResponse(
                message=(
                    "Account approved by voucher"
                    if decision == "approve"
                    else "Vouch denied. Admin can still approve the account."
                ),
                vouch_id=request.vouch_id,
                register_id=int(registrant["id"]),
                action=decision,
                vouch_status=vouch_status,
                account_approved=decision == "approve",
                account_active=(True if decision == "approve" else bool(registrant.get("active"))),
            )

        logger.info(
            "voucher_decision_changed",
            actor_user_id=actor_user_id,
            registrant_user_id=response.register_id,
            vouch_id=response.vouch_id,
            action=response.action,
            previous_status=previous_status,
        )
        self._send_vouch_notifications(actor, registrant, managers, request)
        return response

    def get_setup_parameters(
        self,
        actor_user_id: int,
        action_type: str,
    ) -> SetupParametersResponse:
        """Return one bounded configuration row to a current active account."""
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise SetupParametersError(
                    "setup_parameters_actor_unavailable",
                    "Authentication required",
                    401,
                )
            row = self._repository.setup_parameter(action_type)
            if row is None:
                raise SetupParametersError(
                    "setup_parameters_not_found",
                    f"No setup parameters found for action_type: {action_type}",
                    404,
                )
            raw_value = str(row.get("setup_value") or "")
            values = [value for item in raw_value.split(",") if (value := item.strip())]
            return SetupParametersResponse(
                data=SetupParameterData(
                    setup_id=int(row["setup_id"]),
                    setup_name=str(row["setup_name"]),
                    setup_value=raw_value,
                    values=values,
                )
            )

    def get_profile_visibility(
        self,
        actor_user_id: int,
        requested_target_user_id: int | None,
    ) -> ProfileVisibilityResponse:
        """Return visibility settings to self or an authorized account manager."""
        target_user_id = requested_target_user_id or actor_user_id
        with self._session.begin():
            self._authorize_visibility_target(actor_user_id, target_user_id, lock_accounts=False)
            stored = self._repository.profile_visibility(target_user_id)
            return self._visibility_response(
                target_user_id,
                stored,
                "Profile visibility retrieved successfully",
            )

    def update_profile_visibility(
        self,
        actor_user_id: int,
        requested_target_user_id: int | None,
        is_visible: bool | None,
        field_changes: dict[str, bool],
    ) -> ProfileVisibilityResponse:
        """Merge allowlisted visibility settings in one rollback-safe transaction."""
        target_user_id = requested_target_user_id or actor_user_id
        previous_global: bool
        changed_fields = tuple(sorted(field_changes))
        with self._session.begin():
            self._authorize_visibility_target(actor_user_id, target_user_id, lock_accounts=True)
            stored = self._repository.lock_profile_visibility(target_user_id)
            previous_global = bool(stored.get("is_visible", True)) if stored else True
            current_fields = self._decode_visibility_map(
                stored.get("field_visibility") if stored else None
            )
            current_fields.update(field_changes)
            resolved_global = previous_global if is_visible is None else is_visible
            now = datetime.now(UTC).replace(tzinfo=None)
            self._repository.upsert_profile_visibility(
                target_user_id,
                existing_profile_id=int(stored["id"]) if stored else None,
                is_visible=resolved_global,
                field_visibility=json.dumps(current_fields, separators=(",", ":"), sort_keys=True),
                updated_at=now,
            )
            updated = self._repository.profile_visibility(target_user_id)
            if updated is None:
                raise RuntimeError("Visibility update did not return the target profile")
            response = self._visibility_response(
                target_user_id,
                updated,
                "Visibility settings updated successfully",
            )

        logger.info(
            "member_profile_visibility_changed",
            actor_user_id=actor_user_id,
            target_user_id=target_user_id,
            changed_fields=changed_fields,
            global_visibility_changed=is_visible is not None,
            previous_is_visible=previous_global,
        )
        return response

    def get_members(
        self,
        actor_user_id: int,
        request: GetMembersRequest,
    ) -> MemberDirectoryResponse | AdministrativeMemberListResponse:
        """Return a bounded privacy-aware directory or downward admin listing."""
        offset = (request.page - 1) * request.limit
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise MemberDirectoryError(
                    "members_actor_unavailable",
                    "Authentication required",
                    401,
                )

            if request.action_type == "approved":
                rows, total = self._repository.directory_members(
                    actor_user_id=actor_user_id,
                    user_id=request.user_id,
                    search=request.search,
                    year=request.year,
                    offset=offset,
                    limit=request.limit,
                )
                directory_users = [self._directory_member(row, actor_user_id) for row in rows]
                return MemberDirectoryResponse(
                    count=len(directory_users),
                    total=total,
                    page=request.page,
                    limit=request.limit,
                    has_more=offset + len(directory_users) < total,
                    users=directory_users,
                )

            facts = AuthorizationFacts(
                user_id=actor_user_id,
                user_role=actor.get("user_role"),
                is_coordinator=bool(actor.get("is_coordinator")),
            )
            manageable_roles = manageable_account_role_aliases(facts)
            if not manageable_roles:
                raise MemberDirectoryError(
                    "members_admin_forbidden",
                    "You cannot view the account-management list",
                    403,
                )
            rows, total = self._repository.administrative_members(
                actor_user_id=actor_user_id,
                action_type=request.action_type,
                manageable_role_aliases=manageable_roles,
                user_id=request.user_id,
                search=request.search,
                year=request.year,
                offset=offset,
                limit=request.limit,
            )
            administrative_users = [
                AdministrativeMemberUser(
                    id=int(row["id"]),
                    fullname=row.get("fullname"),
                    email=str(row["email"]),
                    phone=row.get("phone"),
                    graduation_year=row.get("graduation_year"),
                    user_role=row.get("user_role"),
                    active=bool(row.get("active")),
                    profile_status=str(row["profile_status"]),
                    is_approved=bool(row.get("is_approved")),
                    email_verified=bool(row.get("email_verified")),
                )
                for row in rows
            ]
            return AdministrativeMemberListResponse(
                count=len(administrative_users),
                total=total,
                page=request.page,
                limit=request.limit,
                has_more=offset + len(administrative_users) < total,
                users=administrative_users,
            )

    def update_profile(
        self,
        actor_user_id: int,
        request: UpdateProfileRequest,
        avatar: PreparedAvatar | None = None,
        storage: AvatarStorage | None = None,
    ) -> UpdateProfileResponse:
        """Apply one allowlisted profile update with database/file rollback cleanup."""
        user_changes = request.user_changes()
        profile_changes = request.profile_changes()
        if not user_changes and not profile_changes and avatar is None:
            raise ProfileUpdateError(
                "profile_update_empty",
                "No fields provided to update",
                400,
            )

        target_user_id = request.user_id or actor_user_id
        stored_avatar = None
        try:
            with self._session.begin():
                actor, target = self._repository.lock_managed_accounts(
                    actor_user_id,
                    target_user_id,
                )
                if actor is None or not bool(actor.get("active")):
                    raise ProfileUpdateError(
                        "profile_update_actor_unavailable",
                        "Authentication required",
                        401,
                    )
                if target is None:
                    raise ProfileUpdateError(
                        "profile_update_target_not_found",
                        "User not found",
                        404,
                    )
                if target_user_id != actor_user_id:
                    facts = AuthorizationFacts(
                        user_id=actor_user_id,
                        user_role=actor.get("user_role"),
                        is_coordinator=bool(actor.get("is_coordinator")),
                    )
                    if not has_permission(facts, Permission.MANAGE_ACCOUNTS) or not (
                        can_manage_account_target(facts, target.get("user_role"))
                    ):
                        raise ProfileUpdateError(
                            "profile_update_forbidden",
                            "You can only update your own profile",
                            403,
                        )

                current_user = self._repository.profile_user(target_user_id)
                if current_user is None:
                    raise ProfileUpdateError(
                        "profile_update_target_not_found",
                        "User not found",
                        404,
                    )
                normalized_user_changes = self._normalize_profile_user_changes(
                    user_changes,
                    current_user,
                )
                chapter_id = request.chapter_id if "chapter_id" in user_changes else None
                if chapter_id is not None and not self._repository.chapter_exists(chapter_id):
                    raise ProfileUpdateError(
                        "profile_update_chapter_invalid",
                        "Chapter not found",
                        400,
                    )
                now = datetime.now(UTC).replace(tzinfo=None)
                if avatar is not None:
                    if storage is None:
                        raise RuntimeError("Avatar storage is not configured")
                    stored_avatar = storage.save(target_user_id, avatar)
                    normalized_user_changes["avatar"] = stored_avatar.relative_path

                if normalized_user_changes:
                    self._repository.update_profile_user(
                        target_user_id,
                        changes=normalized_user_changes,
                        updated_at=now,
                    )
                if profile_changes:
                    existing_profile = self._repository.lock_profile_visibility(target_user_id)
                    self._repository.upsert_profile_fields(
                        target_user_id,
                        existing_profile_id=(
                            int(existing_profile["id"]) if existing_profile else None
                        ),
                        changes=profile_changes,
                        updated_at=now,
                    )
                if stored_avatar is not None:
                    self._repository.insert_avatar_attachment(
                        target_user_id,
                        original_filename=stored_avatar.original_filename,
                        relative_path=stored_avatar.relative_path,
                        created_at=now,
                    )

                updated_user = self._repository.profile_user(target_user_id)
                if updated_user is None:
                    raise RuntimeError("Profile update did not return the target user")
                updated_profile = self._repository.profile_details(target_user_id) or {}
                roles = self._repository.roles_for_user(target_user_id)
                zone = self._repository.zone_for_city(updated_user.get("city"))
                response = self._profile_update_response(
                    updated_user,
                    updated_profile,
                    roles,
                    zone,
                )
        except Exception:
            if stored_avatar is not None and storage is not None:
                storage.delete(stored_avatar)
            raise

        logger.info(
            "member_profile_updated",
            actor_user_id=actor_user_id,
            target_user_id=target_user_id,
            user_fields=tuple(sorted(user_changes)),
            profile_fields=tuple(sorted(profile_changes)),
            avatar_changed=avatar is not None,
        )
        return response

    def manage_member_account(
        self,
        actor_user_id: int,
        requested_target_user_id: int | None,
        action: str | None,
        requested_role: str | None,
    ) -> ManageMemberAccountResponse:
        """Change account state or role using locked, current database facts."""
        target_user_id = requested_target_user_id or actor_user_id
        previous_active: bool
        previous_role: str | None = None
        role_changed = False
        with self._session.begin():
            actor, target = self._repository.lock_managed_accounts(
                actor_user_id,
                target_user_id,
            )
            if actor is None or not bool(actor.get("active")):
                raise MemberAccountError(
                    "account_actor_unavailable",
                    "Authentication required",
                    401,
                )
            if action is not None and requested_role is not None:
                raise MemberAccountError(
                    "account_invalid_request",
                    "Exactly one account operation is required",
                    400,
                )
            if target is None:
                raise MemberAccountError("account_target_not_found", "User not found", 404)

            self_action = actor_user_id == target_user_id
            facts = AuthorizationFacts(
                user_id=actor_user_id,
                user_role=actor.get("user_role"),
                is_coordinator=bool(actor.get("is_coordinator")),
            )
            if requested_role is not None:
                if self_action:
                    raise MemberAccountError(
                        "account_role_self_forbidden",
                        "You cannot change your own role",
                        403,
                    )
                normalized_role = role_storage_value(requested_role)
                if normalized_role is None:
                    raise MemberAccountError(
                        "account_role_invalid",
                        "The requested role is not supported",
                        400,
                    )
                if not can_change_account_role(
                    facts,
                    target.get("user_role"),
                    normalized_role,
                ):
                    raise MemberAccountError(
                        "account_role_forbidden",
                        "You cannot apply that role transition",
                        403,
                    )
                if recognized_role(target.get("user_role")) is recognized_role(normalized_role):
                    raise MemberAccountError(
                        "account_role_unchanged",
                        "User already has the requested role",
                        409,
                    )
                requested_canonical = recognized_role(normalized_role)
                if (
                    requested_canonical is not None
                    and requested_canonical.value != "member"
                    and (
                        not bool(target.get("active"))
                        or not bool(target.get("email_verified"))
                        or not bool(target.get("is_approved"))
                    )
                ):
                    raise MemberAccountError(
                        "account_role_state_invalid",
                        "Only active, approved accounts with verified email can receive "
                        "an administrative role",
                        409,
                    )
                previous_role = target.get("user_role")
                self._repository.update_user_role(
                    target_user_id,
                    user_role=normalized_role,
                    updated_at=datetime.now(UTC).replace(tzinfo=None),
                )
                self._repository.revoke_refresh_tokens(target_user_id)
                role_changed = True
            elif action is None:
                raise MemberAccountError(
                    "account_invalid_request",
                    "action (activate|deactivate) or user_role is required",
                    400,
                )
            elif self_action:
                if action != "deactivate":
                    raise MemberAccountError(
                        "account_self_activate_forbidden",
                        "You cannot activate your own account",
                        403,
                    )
            elif not can_manage_account_target(facts, target.get("user_role")):
                raise MemberAccountError(
                    "account_role_forbidden",
                    "You cannot manage an account with an equal, higher, or unrecognized role",
                    403,
                )

            if not role_changed:
                previous_active = bool(target.get("active"))
                activating = action == "activate"
                if activating and (
                    not bool(target.get("email_verified")) or not bool(target.get("is_approved"))
                ):
                    raise MemberAccountError(
                        "account_activation_state_invalid",
                        "Only approved accounts with verified email can be activated",
                        409,
                    )
                if previous_active == activating:
                    raise MemberAccountError(
                        "account_state_unchanged",
                        f"User account is already {'active' if activating else 'deactivated'}",
                        409,
                    )

                self._repository.update_active_state(
                    target_user_id,
                    active=activating,
                    updated_at=datetime.now(UTC).replace(tzinfo=None),
                )
                if not activating:
                    self._repository.revoke_refresh_tokens(target_user_id)
            updated = self._repository.managed_account(target_user_id)
            if updated is None:
                raise RuntimeError("Account update did not return the target user")
            response = ManageMemberAccountResponse(
                message=(
                    f"User role updated to {requested_role}"
                    if role_changed
                    else (
                        "User account activated"
                        if action == "activate"
                        else "User account deactivated"
                    )
                ),
                user=ManagedMemberAccount(
                    id=int(updated["id"]),
                    fullname=updated.get("fullname"),
                    email=str(updated["email"]),
                    phone=updated.get("phone"),
                    user_role=updated.get("user_role"),
                    active=bool(updated.get("active")),
                    profile_status=str(updated["profile_status"]),
                ),
            )

        if role_changed:
            logger.info(
                "member_account_role_changed",
                actor_user_id=actor_user_id,
                target_user_id=target_user_id,
                previous_role=previous_role,
                requested_role=requested_role,
            )
        else:
            if action is None:
                raise RuntimeError("Validated account action is missing")
            actor_kind = "self" if actor_user_id == target_user_id else "administrator"
            logger.info(
                "member_account_state_changed",
                actor_user_id=actor_user_id,
                target_user_id=target_user_id,
                action=action,
                actor_kind=actor_kind,
                previous_active=previous_active,
            )
            self._send_account_activity_notification(response.user, action, actor_kind)
        return response

    def decide_member_approval(
        self,
        actor_user_id: int,
        target_user_id: int,
        action: str,
        reject_reason: str | None,
    ) -> MemberApprovalResponse:
        """Approve or reject a verified member after locking current account facts."""
        previous_state: dict[str, Any]
        with self._session.begin():
            actor, target = self._repository.lock_approval_accounts(
                actor_user_id,
                target_user_id,
            )
            if actor is None or not bool(actor.get("active")):
                raise MemberApprovalError(
                    "approval_actor_unavailable",
                    "Authentication required",
                    401,
                )
            facts = AuthorizationFacts(
                user_id=actor_user_id,
                user_role=actor.get("user_role"),
                is_coordinator=bool(actor.get("is_coordinator")),
            )
            if not has_permission(facts, Permission.MANAGE_ACCOUNTS):
                raise MemberApprovalError(
                    "approval_forbidden",
                    "Access denied. Admin role required.",
                    403,
                )
            if target is None:
                raise MemberApprovalError("approval_target_not_found", "User not found", 404)
            if actor_user_id == target_user_id:
                raise MemberApprovalError(
                    "approval_self_forbidden",
                    "You cannot approve or reject your own account",
                    403,
                )
            if not can_manage_account_target(facts, target.get("user_role")):
                raise MemberApprovalError(
                    "approval_role_forbidden",
                    "You cannot manage an account with an equal, higher, or unrecognized role",
                    403,
                )
            if not bool(target.get("email_verified")):
                raise MemberApprovalError(
                    "approval_email_unverified",
                    "User email must be verified before an approval decision",
                    409,
                )

            current_status = str(target.get("profile_status") or "").casefold()
            currently_approved = bool(target.get("is_approved"))
            currently_active = bool(target.get("active"))
            if action == "approve" and currently_approved and currently_active:
                raise MemberApprovalError(
                    "approval_already_approved",
                    "User account is already approved",
                    409,
                )
            if action == "reject" and currently_approved:
                raise MemberApprovalError(
                    "approval_reject_active_forbidden",
                    "Approved accounts must be deactivated through account management",
                    409,
                )
            if action == "reject" and not currently_active and current_status == "rejected":
                raise MemberApprovalError(
                    "approval_already_rejected",
                    "User account is already rejected",
                    409,
                )

            previous_state = {
                "active": currently_active,
                "is_approved": currently_approved,
                "profile_status": current_status,
            }
            approved = action == "approve"
            self._repository.update_approval_state(
                target_user_id,
                approved=approved,
                active=approved,
                profile_status="active" if approved else "rejected",
                updated_at=datetime.now(UTC).replace(tzinfo=None),
            )
            updated = self._repository.approval_user(target_user_id)
            if updated is None:
                raise RuntimeError("Approval update did not return the target user")
            response = MemberApprovalResponse(
                message=(
                    "User account approved successfully" if approved else "User account rejected"
                ),
                user=MemberApprovalUser(
                    id=int(updated["id"]),
                    fullname=updated.get("fullname"),
                    email=str(updated["email"]),
                    user_role=updated.get("user_role"),
                    active=bool(updated.get("active")),
                    is_approved=bool(updated.get("is_approved")),
                    profile_status=str(updated["profile_status"]),
                ),
            )

        logger.info(
            "member_approval_changed",
            actor_user_id=actor_user_id,
            target_user_id=target_user_id,
            action=action,
            previous_active=previous_state["active"],
            previous_is_approved=previous_state["is_approved"],
            previous_profile_status=previous_state["profile_status"],
        )
        self._send_approval_notification(response.user, action, reject_reason)
        return response

    def get_user_profile(
        self,
        actor_user_id: int,
        requested_user_id: int | None,
    ) -> UserProfileResponse:
        """Return self, or another profile only to a current account manager."""
        with self._session.begin():
            actor = self._repository.authorization_account(actor_user_id)
            if actor is None or not bool(actor.get("active")):
                raise MemberProfileError(
                    "profile_actor_unavailable",
                    "Authentication required",
                    401,
                )

            target_user_id = requested_user_id or actor_user_id
            if target_user_id != actor_user_id:
                actor_roles = self._repository.roles_for_user(actor_user_id)
                facts = AuthorizationFacts(
                    user_id=actor_user_id,
                    user_role=actor.get("user_role"),
                    system_roles=tuple(actor_roles),
                    is_coordinator=bool(actor.get("is_coordinator")),
                )
                if not has_permission(facts, Permission.MANAGE_ACCOUNTS):
                    raise MemberProfileError(
                        "profile_forbidden",
                        "You can only view your own profile",
                        403,
                    )

            user = self._repository.profile_user(target_user_id)
            if user is None:
                raise MemberProfileError("profile_not_found", "User not found", 404)
            profile = self._repository.profile_details(target_user_id) or {}
            roles = self._repository.roles_for_user(target_user_id)
            zone = self._repository.zone_for_city(user.get("city"))
            return self._response(user, profile, roles, zone)

    def _response(
        self,
        user: dict[str, Any],
        profile: dict[str, Any],
        roles: list[str],
        zone: dict[str, Any] | None,
    ) -> UserProfileResponse:
        return UserProfileResponse(
            user_id=int(user["id"]),
            user_code=user.get("user_code"),
            email=str(user["email"]),
            fullname=user.get("fullname"),
            first_name=user.get("first_name"),
            last_name=user.get("last_name"),
            phone=user.get("phone"),
            user_role=user.get("user_role"),
            avatar=self._absolute_avatar(user.get("avatar")),
            active=bool(user.get("active")),
            email_verified=bool(user.get("email_verified")),
            is_approved=bool(user.get("is_approved")),
            chapter_id=user.get("chapter_id"),
            graduation_year=user.get("graduation_year"),
            department=user.get("department"),
            bio=user.get("bio"),
            name_in_school=user.get("name_in_school"),
            alternative_phone=user.get("alternative_phone"),
            birth_date=user.get("birth_date"),
            house_color=user.get("house_color"),
            is_coordinator=bool(user.get("is_coordinator")),
            residential_address=user.get("residential_address"),
            area=user.get("area"),
            city=user.get("city"),
            employment_status=user.get("employment_status"),
            occupation=user.get("occupation"),
            industry_sector=user.get("industry_sector"),
            years_of_experience=user.get("years_of_experience"),
            is_volunteer=bool(user.get("is_volunteer")),
            nick_name=user.get("nick_name"),
            state=user.get("state"),
            zone_id=zone.get("zone_id") if zone else None,
            zone_name=zone.get("zone_name") if zone else None,
            city_id=zone.get("city_id") if zone else None,
            profile=MemberProfileDetails(
                linkedin=profile.get("linkedin"),
                twitter=profile.get("twitter"),
                tiktok=profile.get("tiktok"),
                facebook=profile.get("facebook"),
                instagram=profile.get("instagram"),
                field_visibility=profile.get("field_visibility"),
                website=profile.get("website"),
                current_company=profile.get("current_company"),
                current_position=profile.get("current_position"),
                city=profile.get("city"),
                country=profile.get("country"),
                skills=profile.get("skills"),
                achievements=profile.get("achievements"),
                year=profile.get("year"),
                is_visible=bool(profile.get("is_visible", True)),
                system_role=";".join(roles),
            ),
        )

    @staticmethod
    def _normalize_profile_user_changes(
        changes: dict[str, object],
        current_user: dict[str, Any],
    ) -> dict[str, object]:
        """Normalize database booleans and rebuild fullname only from supplied name parts."""
        normalized = dict(changes)
        for field in ("is_coordinator", "is_volunteer"):
            if field in normalized:
                normalized[field] = int(bool(normalized[field]))
        for field in ("nick_name", "state"):
            if field in normalized and normalized[field] is None:
                normalized[field] = ""
        if "first_name" in normalized or "last_name" in normalized:
            first_name = normalized.get("first_name", current_user.get("first_name"))
            last_name = normalized.get("last_name", current_user.get("last_name"))
            normalized["fullname"] = f"{first_name or ''} {last_name or ''}".strip()
        return normalized

    def _profile_update_response(
        self,
        user: dict[str, Any],
        profile: dict[str, Any],
        roles: list[str],
        zone: dict[str, Any] | None,
    ) -> UpdateProfileResponse:
        """Build the active frontend's fresh, credential-free update envelope."""
        return UpdateProfileResponse(
            user=ProfileUpdateUser(
                id=int(user["id"]),
                user_code=user.get("user_code"),
                email=str(user["email"]),
                fullname=user.get("fullname"),
                first_name=user.get("first_name"),
                last_name=user.get("last_name"),
                phone=user.get("phone"),
                user_role=user.get("user_role"),
                avatar=self._absolute_avatar(user.get("avatar")),
                active=bool(user.get("active")),
                email_verified=bool(user.get("email_verified")),
                is_approved=bool(user.get("is_approved")),
                chapter_id=int(user["chapter_id"]),
                graduation_year=user.get("graduation_year"),
                department=user.get("department"),
                bio=user.get("bio"),
                name_in_school=user.get("name_in_school"),
                alternative_phone=user.get("alternative_phone"),
                birth_date=user.get("birth_date"),
                house_color=user.get("house_color"),
                is_coordinator=bool(user.get("is_coordinator")),
                residential_address=user.get("residential_address"),
                area=user.get("area"),
                city=user.get("city"),
                employment_status=user.get("employment_status"),
                occupation=user.get("occupation"),
                industry_sector=user.get("industry_sector"),
                years_of_experience=user.get("years_of_experience"),
                is_volunteer=bool(user.get("is_volunteer")),
                nick_name=str(user.get("nick_name") or ""),
                state=str(user.get("state") or ""),
                year=user.get("year"),
                onboarding_completion=bool(user.get("onboarding_completion")),
            ),
            profile=MemberProfileDetails(
                linkedin=profile.get("linkedin"),
                twitter=profile.get("twitter"),
                tiktok=profile.get("tiktok"),
                facebook=profile.get("facebook"),
                instagram=profile.get("instagram"),
                field_visibility=profile.get("field_visibility"),
                website=profile.get("website"),
                current_company=profile.get("current_company"),
                current_position=profile.get("current_position"),
                city=profile.get("city"),
                country=profile.get("country"),
                skills=profile.get("skills"),
                achievements=profile.get("achievements"),
                year=profile.get("year"),
                is_visible=(
                    True if profile.get("is_visible") is None else bool(profile.get("is_visible"))
                ),
                system_role=";".join(roles),
            ),
            zone_id=zone.get("zone_id") if zone else None,
            zone_name=zone.get("zone_name") if zone else None,
            city_id=zone.get("city_id") if zone else None,
        )

    def _directory_member(
        self,
        row: dict[str, Any],
        actor_user_id: int,
    ) -> MemberDirectoryUser:
        """Apply stored visibility before constructing one public member projection."""
        is_owner = int(row["id"]) == actor_user_id
        decoded = self._decode_visibility_map(row.get("field_visibility"))

        def allowed(field: str) -> bool:
            return is_owner or decoded.get(field, True)

        visibility = ProfileFieldVisibility.model_validate(
            {
                field: "public" if decoded.get(field, True) else "private"
                for field in PROFILE_VISIBILITY_FIELDS
            }
        )
        socials_allowed = allowed("socials")
        city_allowed = allowed("city")
        employment_allowed = allowed("employment_status")
        profile_is_visible = True if row.get("is_visible") is None else bool(row.get("is_visible"))

        return MemberDirectoryUser(
            id=int(row["id"]),
            fullname=row.get("fullname"),
            first_name=row.get("first_name"),
            last_name=row.get("last_name"),
            graduation_year=row.get("graduation_year"),
            name_in_school=row.get("name_in_school"),
            nick_name=row.get("nick_name"),
            house_color=row.get("house_color"),
            avatar=self._absolute_avatar(row.get("avatar")) if allowed("avatar") else None,
            bio=row.get("bio"),
            phone=row.get("phone") if allowed("phone") else None,
            alternative_phone=(
                row.get("alternative_phone") if allowed("alternative_phone") else None
            ),
            birth_date=row.get("birth_date") if allowed("birth_date") else None,
            residential_address=(
                row.get("residential_address") if allowed("residential_address") else None
            ),
            area=row.get("area") if allowed("area") else None,
            city=row.get("city") if city_allowed else None,
            state=row.get("state") if city_allowed else None,
            employment_status=(row.get("employment_status") if employment_allowed else None),
            occupation=row.get("occupation") if allowed("occupation") else None,
            industry_sector=(row.get("industry_sector") if allowed("industry_sector") else None),
            years_of_experience=(
                row.get("years_of_experience") if allowed("years_of_experience") else None
            ),
            is_coordinator=bool(row.get("is_coordinator")),
            is_volunteer=(bool(row.get("is_volunteer")) if allowed("is_volunteer") else None),
            user_role=row.get("user_role"),
            active=bool(row.get("active")),
            email_verified=bool(row.get("email_verified")),
            is_approved=bool(row.get("is_approved")),
            zone_id=row.get("zone_id") if city_allowed else None,
            zone_name=row.get("zone_name") if city_allowed else None,
            city_id=row.get("city_id") if city_allowed else None,
            profile=MemberDirectoryProfile(
                linkedin=row.get("linkedin") if socials_allowed else None,
                twitter=row.get("twitter") if socials_allowed else None,
                tiktok=(row.get("tiktok") if socials_allowed and allowed("tiktok") else None),
                facebook=(row.get("facebook") if socials_allowed and allowed("facebook") else None),
                website=row.get("website") if socials_allowed else None,
                instagram=row.get("instagram") if socials_allowed else None,
                current_company=(row.get("current_company") if employment_allowed else None),
                current_position=(row.get("current_position") if employment_allowed else None),
                city=row.get("profile_city") if city_allowed else None,
                country=row.get("profile_country") if city_allowed else None,
                year=row.get("profile_year"),
                is_visible=profile_is_visible,
                field_visibility=visibility,
            ),
        )

    def _public_zone_coordinator(
        self,
        row: dict[str, Any],
    ) -> PublicZoneCoordinator | None:
        """Project only eligible coordinator identity and visibility-approved contact data."""
        if (
            row.get("user_id") is None
            or not bool(row.get("active"))
            or not bool(row.get("email_verified"))
            or not bool(row.get("is_approved"))
        ):
            return None
        if row.get("is_visible") is not None and not bool(row.get("is_visible")):
            return None
        visibility = self._decode_visibility_map(row.get("field_visibility"))
        first_name = str(row.get("first_name") or "").strip()
        last_name = str(row.get("last_name") or "").strip()
        name = str(row.get("fullname") or "").strip() or " ".join(
            part for part in (first_name, last_name) if part
        )
        return PublicZoneCoordinator(
            user_id=int(row["user_id"]),
            name=name or "Welfare coordinator",
            first_name=first_name or None,
            last_name=last_name or None,
            phone=(
                str(row["phone"]) if visibility.get("phone", True) and row.get("phone") else None
            ),
            avatar=(
                self._absolute_avatar(row.get("avatar")) if visibility.get("avatar", True) else None
            ),
        )

    def _send_approval_notification(
        self,
        user: MemberApprovalUser,
        action: str,
        reject_reason: str | None,
    ) -> None:
        """Attempt the PHP account-status side effect only after the state commits."""
        if self._mailer is None:
            return
        try:
            self._mailer.send_account_status(
                str(user.email),
                user.fullname or "member",
                action,
                reject_reason,
            )
        except MailDeliveryError:
            logger.error(
                "member_approval_notification_failed",
                target_user_id=user.id,
                action=action,
            )

    def _send_vouch_notifications(
        self,
        actor: dict[str, Any],
        registrant: dict[str, Any],
        managers: list[dict[str, Any]],
        request: VouchActionRequest,
    ) -> None:
        """Attempt bounded voucher-result mail only after the transaction commits."""
        if self._mailer is None:
            return
        decision = request.decision
        try:
            self._mailer.send_account_status(
                str(registrant["email"]),
                str(registrant.get("fullname") or "member"),
                "approve" if decision == "approve" else "reject",
                request.reason,
            )
        except MailDeliveryError:
            logger.error(
                "voucher_registrant_notification_failed",
                vouch_id=request.vouch_id,
                action=decision,
            )
        if decision != "approve":
            return
        for manager in managers:
            try:
                self._mailer.send_voucher_approval_notification(
                    str(manager["email"]),
                    str(manager.get("fullname") or "administrator"),
                    str(actor.get("fullname") or "voucher"),
                    str(registrant.get("fullname") or "member"),
                )
            except MailDeliveryError:
                logger.error(
                    "voucher_manager_notification_failed",
                    vouch_id=request.vouch_id,
                    manager_user_id=int(manager["id"]),
                )

    def _authorize_visibility_target(
        self,
        actor_user_id: int,
        target_user_id: int,
        *,
        lock_accounts: bool,
    ) -> None:
        """Authorize self or a downward account-manager visibility operation."""
        if lock_accounts:
            actor, target = self._repository.lock_managed_accounts(actor_user_id, target_user_id)
        else:
            actor = self._repository.authorization_account(actor_user_id)
            target = (
                actor
                if actor_user_id == target_user_id
                else self._repository.authorization_account(target_user_id)
            )
        if actor is None or not bool(actor.get("active")):
            raise ProfileVisibilityError(
                "visibility_actor_unavailable",
                "Authentication required",
                401,
            )
        if actor_user_id == target_user_id:
            return
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, Permission.MANAGE_ACCOUNTS):
            raise ProfileVisibilityError(
                "visibility_forbidden",
                "You cannot manage visibility for this account",
                403,
            )
        if target is None:
            raise ProfileVisibilityError("visibility_target_not_found", "User not found", 404)
        if not can_manage_account_target(facts, target.get("user_role")):
            raise ProfileVisibilityError(
                "visibility_forbidden",
                "You cannot manage visibility for this account",
                403,
            )

    @staticmethod
    def _decode_visibility_map(raw: Any) -> dict[str, bool]:
        """Normalize legacy JSON; malformed state fails closed instead of becoming public."""
        if raw in (None, ""):
            return {}
        try:
            decoded = json.loads(str(raw))
        except (TypeError, ValueError):
            return dict.fromkeys(PROFILE_VISIBILITY_FIELDS, False)
        if not isinstance(decoded, dict):
            return dict.fromkeys(PROFILE_VISIBILITY_FIELDS, False)
        return {
            field: MemberService._visibility_value(decoded[field])
            for field in PROFILE_VISIBILITY_FIELDS
            if field in decoded
        }

    @staticmethod
    def _birthday_candidate_months(
        request: GetBirthdaysRequest,
        today: date,
    ) -> frozenset[int]:
        """Return only months intersecting the selected birthday window."""
        if request.scope == "month":
            return frozenset({request.month or today.month})
        if request.scope == "today":
            return frozenset({today.month})
        window = 6 if request.scope == "week" else request.days - 1
        return frozenset((today + timedelta(days=offset)).month for offset in range(window + 1))

    @staticmethod
    def _next_birthday(dob: date, today: date) -> date:
        """Resolve the next observance, shifting 29 February to 28 February as needed."""

        def occurrence(year: int) -> date:
            day = 28 if dob.month == 2 and dob.day == 29 and not calendar.isleap(year) else dob.day
            return date(year, dob.month, day)

        next_date = occurrence(today.year)
        return next_date if next_date >= today else occurrence(today.year + 1)

    @staticmethod
    def _birthday_fullname(row: dict[str, Any]) -> str:
        """Use the stored display name, then the legacy first/last-name fallback."""
        fullname = str(row.get("fullname") or "").strip()
        if fullname:
            return fullname
        fallback = " ".join(
            str(value).strip()
            for value in (row.get("first_name"), row.get("last_name"))
            if value is not None and str(value).strip()
        )
        return (fallback or "Alumni member")[:100]

    @staticmethod
    def _birthday_class_label(row: dict[str, Any]) -> str | None:
        """Preserve the frontend's compact Class 'YY display contract."""
        raw = row.get("year") or row.get("graduation_year")
        value = str(raw).strip() if raw is not None else ""
        return f"Class '{value[-2:]}" if value else None

    @staticmethod
    def _visibility_value(value: Any) -> bool:
        """Accept reviewed boolean/public legacy values; unknown values fail private."""
        if isinstance(value, bool):
            return value
        if isinstance(value, int):
            return value != 0
        if isinstance(value, str):
            return value.strip().casefold() in {"1", "true", "yes", "on", "public"}
        return False

    @classmethod
    def _visibility_response(
        cls,
        user_id: int,
        stored: dict[str, Any] | None,
        message: str,
    ) -> ProfileVisibilityResponse:
        """Build a complete legacy-compatible public/private visibility map."""
        raw = stored.get("field_visibility") if stored else None
        decoded = cls._decode_visibility_map(raw)
        values = {
            field: "public" if decoded.get(field, True) else "private"
            for field in PROFILE_VISIBILITY_FIELDS
        }
        return ProfileVisibilityResponse(
            message=message,
            user_id=user_id,
            is_visible=bool(stored.get("is_visible", True)) if stored else True,
            field_visibility=ProfileFieldVisibility.model_validate(values),
        )

    def _send_account_activity_notification(
        self,
        user: ManagedMemberAccount,
        action: str,
        actor_kind: str,
    ) -> None:
        """Attempt the legacy activity email only after the state commits."""
        if self._mailer is None:
            return
        try:
            self._mailer.send_account_activity(
                str(user.email),
                user.fullname or "member",
                action,
                actor_kind,
            )
        except MailDeliveryError:
            logger.error(
                "member_account_activity_notification_failed",
                target_user_id=user.id,
                action=action,
            )

    def _absolute_avatar(self, avatar: Any) -> str | None:
        if not avatar:
            return None
        base_url = str(self._settings.public_base_url) if self._settings.public_base_url else None
        return urljoin(base_url, str(avatar)) if base_url else str(avatar)
