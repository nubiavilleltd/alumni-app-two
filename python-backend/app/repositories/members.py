"""Allowlisted persistence queries for member profile use cases."""

from __future__ import annotations

from datetime import datetime
from typing import Any, cast

from sqlalchemy import CursorResult, RowMapping, Table, extract, func, or_, select
from sqlalchemy.orm import Session

from app.models.generated import (
    AlumniCategory,
    AlumniChapter,
    Attachments,
    Cities,
    Groups,
    JwtRefreshTokens,
    Roles,
    SetupParameters,
    UserProfiles,
    Users,
    UsersGroups,
    Vouches,
    Zones,
)

USERS_TABLE = cast(Table, Users.__table__)
REFRESH_TOKENS_TABLE = cast(Table, JwtRefreshTokens.__table__)
USER_PROFILES_TABLE = cast(Table, UserProfiles.__table__)
ATTACHMENTS_TABLE = cast(Table, Attachments.__table__)
VOUCHES_TABLE = cast(Table, Vouches.__table__)
CITIES_TABLE = cast(Table, Cities.__table__)
ZONES_TABLE = cast(Table, Zones.__table__)
USERS_GROUPS_TABLE = cast(Table, UsersGroups.__table__)
ALUMNI_CATEGORY_TABLE = cast(Table, AlumniCategory.__table__)


class MemberRepository:
    """Read only the account and profile fields required by member endpoints."""

    def __init__(self, session: Session) -> None:
        self._session = session

    def authorization_account(self, user_id: int) -> dict[str, Any] | None:
        """Fetch current account facts used for authorization."""
        row = (
            self._session.execute(
                select(
                    Users.id,
                    Users.active,
                    Users.user_role,
                    Users.is_coordinator,
                )
                .where(Users.id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_geography_actor(self, user_id: int) -> dict[str, Any] | None:
        """Lock current authorization facts for a privileged geography mutation."""
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.user_role, Users.is_coordinator)
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_geography_catalogue(
        self,
    ) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
        """Serialize the small zone/city catalogues so duplicate checks and writes are atomic."""
        zones = self._session.execute(
            select(
                Zones.zone_id,
                Zones.zone,
                Zones.coordinator_user_id,
                Zones.chapter_id,
            )
            .order_by(Zones.zone_id)
            .with_for_update()
        ).mappings()
        cities = self._session.execute(
            select(Cities.city_id, Cities.city, Cities.zone_id, Cities.chapter_id)
            .order_by(Cities.city_id)
            .with_for_update()
        ).mappings()
        return [dict(row) for row in zones], [dict(row) for row in cities]

    def geography_chapter_exists(self, chapter_id: int) -> bool:
        """Validate a chapter reference despite the legacy table's missing foreign key."""
        return (
            self._session.scalar(
                select(AlumniChapter.id).where(AlumniChapter.id == chapter_id).limit(1)
            )
            is not None
        )

    def geography_coordinator_account(self, user_id: int) -> dict[str, Any] | None:
        """Lock only the account facts needed to validate a zone coordinator."""
        row = (
            self._session.execute(
                select(
                    Users.id,
                    Users.chapter_id,
                    Users.active,
                    Users.email_verified,
                    Users.is_approved,
                )
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def city_name_is_referenced(self, city: str) -> bool:
        """Protect member/profile location mappings before a city rename or deletion."""
        city_key = city.strip().casefold()
        user_reference = self._session.scalar(
            select(Users.id)
            .where(func.lower(func.trim(Users.city)) == city_key)
            .with_for_update()
            .limit(1)
        )
        if user_reference is not None:
            return True
        profile_reference = self._session.scalar(
            select(UserProfiles.id)
            .where(func.lower(func.trim(UserProfiles.city)) == city_key)
            .with_for_update()
            .limit(1)
        )
        return profile_reference is not None

    def insert_zone(
        self,
        *,
        zone: str,
        coordinator_user_id: int | None,
        chapter_id: int,
        created_at: datetime,
    ) -> int:
        """Insert one validated zone and return its generated identifier."""
        result = cast(
            CursorResult[Any],
            self._session.execute(
                ZONES_TABLE.insert().values(
                    zone=zone,
                    coordinator_user_id=coordinator_user_id,
                    chapter_id=chapter_id,
                    created_at=created_at,
                )
            ),
        )
        inserted_key = result.inserted_primary_key
        if inserted_key is None:
            raise RuntimeError("Zone insert did not return an identifier")
        return int(inserted_key[0])

    def update_zone(
        self,
        zone_id: int,
        *,
        changes: dict[str, object],
        updated_at: datetime,
    ) -> None:
        """Apply an allowlisted partial zone update."""
        self._session.execute(
            ZONES_TABLE.update()
            .where(Zones.zone_id == zone_id)
            .values(**changes, updated_at=updated_at)
        )

    def update_zone_city_chapters(self, zone_id: int, *, chapter_id: int) -> None:
        """Keep city chapter metadata aligned when a zone moves chapters."""
        self._session.execute(
            CITIES_TABLE.update().where(Cities.zone_id == zone_id).values(chapter_id=chapter_id)
        )

    def delete_zone(self, zone_id: int) -> None:
        """Delete one already-validated unreferenced zone."""
        self._session.execute(ZONES_TABLE.delete().where(Zones.zone_id == zone_id))

    def insert_city(
        self,
        *,
        city: str,
        zone_id: int,
        chapter_id: int,
        created_at: datetime,
    ) -> int:
        """Insert one validated city mapping and return its generated identifier."""
        result = cast(
            CursorResult[Any],
            self._session.execute(
                CITIES_TABLE.insert().values(
                    city=city,
                    zone_id=zone_id,
                    chapter_id=chapter_id,
                    created_at=created_at,
                )
            ),
        )
        inserted_key = result.inserted_primary_key
        if inserted_key is None:
            raise RuntimeError("City insert did not return an identifier")
        return int(inserted_key[0])

    def update_city(self, city_id: int, *, changes: dict[str, object]) -> None:
        """Apply an allowlisted partial city update."""
        self._session.execute(
            CITIES_TABLE.update().where(Cities.city_id == city_id).values(**changes)
        )

    def delete_city(self, city_id: int) -> None:
        """Delete one already-validated unreferenced city."""
        self._session.execute(CITIES_TABLE.delete().where(Cities.city_id == city_id))

    def lock_alumni_import_actor(self, user_id: int) -> dict[str, Any] | None:
        """Lock current account facts before a privileged roster mutation."""
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.user_role, Users.is_coordinator)
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def alumni_import_chapter_exists(self, chapter_id: int) -> bool:
        """Require the import target to be an enabled chapter."""
        return (
            self._session.scalar(
                select(AlumniChapter.id)
                .where(AlumniChapter.id == chapter_id, AlumniChapter.is_enabled == 1)
                .limit(1)
            )
            is not None
        )

    def alumni_import_cities(self, chapter_id: int, cities: set[str]) -> set[str]:
        """Return normalized imported city names present in the selected chapter."""
        if not cities:
            return set()
        normalized = {city.strip().casefold() for city in cities}
        rows = self._session.scalars(
            select(Cities.city).where(
                Cities.chapter_id == chapter_id,
                func.lower(func.trim(Cities.city)).in_(normalized),
            )
        )
        return {str(city).strip().casefold() for city in rows}

    def alumni_import_member_group_exists(self, group_id: int) -> bool:
        """Validate the reviewed Ion Auth member group before any roster write."""
        return (
            self._session.scalar(select(Groups.id).where(Groups.id == group_id).limit(1))
            is not None
        )

    def lock_alumni_import_accounts(self, emails: set[str]) -> list[dict[str, Any]]:
        """Lock only existing account and profile fields the roster may inspect or update."""
        if not emails:
            return []
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.email,
                    Users.chapter_id,
                    Users.first_name,
                    Users.last_name,
                    Users.fullname,
                    Users.name_in_school,
                    Users.phone,
                    Users.alternative_phone,
                    Users.birth_date,
                    Users.house_color,
                    Users.residential_address,
                    Users.area,
                    Users.city,
                    Users.employment_status,
                    Users.occupation,
                    Users.industry_sector,
                    Users.years_of_experience,
                    Users.is_volunteer,
                    Users.graduation_year,
                    Users.year,
                    Users.user_role,
                    Users.is_coordinator,
                    Users.active,
                )
                .where(func.lower(Users.email).in_(emails))
                .order_by(Users.id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def lock_alumni_import_categories(self, user_ids: set[int]) -> dict[int, list[dict[str, Any]]]:
        """Lock each existing member's category rows and retain ambiguity evidence."""
        categories: dict[int, list[dict[str, Any]]] = {user_id: [] for user_id in user_ids}
        if not user_ids:
            return categories
        rows = (
            self._session.execute(
                select(
                    AlumniCategory.id,
                    AlumniCategory.user_id,
                    AlumniCategory.chapter_id,
                    AlumniCategory.year,
                    AlumniCategory.location,
                )
                .where(AlumniCategory.user_id.in_(user_ids))
                .order_by(AlumniCategory.user_id, AlumniCategory.id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        for row in rows:
            categories[int(row["user_id"])].append(dict(row))
        return categories

    def lock_alumni_import_profiles(self, user_ids: set[int]) -> dict[int, dict[str, Any]]:
        """Lock the unique profile projection for every existing imported account."""
        if not user_ids:
            return {}
        rows = (
            self._session.execute(
                select(
                    UserProfiles.id,
                    UserProfiles.user_id,
                    UserProfiles.chapter_id,
                    UserProfiles.year,
                    UserProfiles.city,
                )
                .where(UserProfiles.user_id.in_(user_ids))
                .order_by(UserProfiles.user_id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        return {int(row["user_id"]): dict(row) for row in rows}

    def alumni_import_user_code_exists(self, user_code: str) -> bool:
        """Check the compatibility identifier before the unique account insert."""
        return (
            self._session.scalar(select(Users.id).where(Users.user_code == user_code).limit(1))
            is not None
        )

    def insert_alumni_import_user(self, values: dict[str, Any]) -> int:
        """Insert one server-controlled imported member account."""
        result = cast(
            CursorResult[Any], self._session.execute(USERS_TABLE.insert().values(**values))
        )
        inserted_key = result.inserted_primary_key
        if inserted_key is None:
            raise RuntimeError("Alumni import did not return a user identity")
        return int(inserted_key[0])

    def update_alumni_import_user(
        self, user_id: int, *, changes: dict[str, object], updated_at: datetime
    ) -> None:
        """Apply only allowlisted profile and chapter changes to an existing account."""
        self._session.execute(
            USERS_TABLE.update().where(Users.id == user_id).values(**changes, updated_at=updated_at)
        )

    def ensure_alumni_import_group(self, user_id: int, group_id: int) -> bool:
        """Add a missing member-group row and report whether the import changed it."""
        existing = self._session.scalar(
            select(UsersGroups.id)
            .where(UsersGroups.user_id == user_id, UsersGroups.group_id == group_id)
            .with_for_update()
            .limit(1)
        )
        if existing is not None:
            return False
        self._session.execute(
            USERS_GROUPS_TABLE.insert().values(user_id=user_id, group_id=group_id)
        )
        return True

    def insert_alumni_import_category(
        self,
        *,
        user_id: int,
        chapter_id: int,
        year: str,
        location: str,
        created_at: datetime,
    ) -> None:
        """Create the imported member's single reviewed chapter membership."""
        self._session.execute(
            ALUMNI_CATEGORY_TABLE.insert().values(
                user_id=user_id,
                chapter_id=chapter_id,
                year=year,
                location=location,
                created_at=created_at,
            )
        )

    def update_alumni_import_category(
        self,
        category_id: int,
        *,
        chapter_id: int,
        year: str,
        location: str,
    ) -> None:
        """Align one unambiguous existing membership with the imported roster row."""
        self._session.execute(
            ALUMNI_CATEGORY_TABLE.update()
            .where(AlumniCategory.id == category_id)
            .values(chapter_id=chapter_id, year=year, location=location)
        )

    def insert_alumni_import_profile(
        self,
        *,
        user_id: int,
        chapter_id: int,
        year: str,
        city: str,
        field_visibility: str,
        created_at: datetime,
    ) -> None:
        """Seed one private-by-default profile for an imported member."""
        self._session.execute(
            USER_PROFILES_TABLE.insert().values(
                user_id=user_id,
                chapter_id=chapter_id,
                year=year,
                city=city,
                instagram="",
                tiktok="",
                is_visible=0,
                field_visibility=field_visibility,
                created_at=created_at,
                updated_at=created_at,
            )
        )

    def update_alumni_import_profile(
        self,
        profile_id: int,
        *,
        chapter_id: int,
        year: str,
        city: str,
        updated_at: datetime,
    ) -> None:
        """Align non-privilege profile location fields for an existing member."""
        self._session.execute(
            USER_PROFILES_TABLE.update()
            .where(UserProfiles.id == profile_id)
            .values(chapter_id=chapter_id, year=year, city=city, updated_at=updated_at)
        )

    def alumni_stats(self) -> dict[str, int]:
        """Return the four aggregate counts defined by the reviewed PHP route."""
        total_alumni = self._session.scalar(
            select(func.count(Users.id)).where(
                Users.is_approved == 1,
                Users.active == 1,
                Users.user_role == "alumni",
            )
        )
        total_years = self._session.scalar(
            select(func.count(func.distinct(AlumniCategory.year))).where(
                AlumniCategory.year.is_not(None)
            )
        )
        total_chapters = self._session.scalar(
            select(func.count(AlumniChapter.id)).where(AlumniChapter.is_enabled == 1)
        )
        total_departments = self._session.scalar(
            select(func.count(func.distinct(Users.department))).where(
                Users.department.is_not(None),
                Users.department != "",
                Users.is_approved == 1,
            )
        )
        return {
            "total_alumni": int(total_alumni or 0),
            "total_years": int(total_years or 0),
            "total_chapters": int(total_chapters or 0),
            "total_departments": int(total_departments or 0),
        }

    def birthday_candidates(
        self,
        months: frozenset[int],
        *,
        limit: int,
    ) -> list[dict[str, Any]]:
        """Return a capped, credential-free birthday projection for relevant months."""
        conditions: list[Any] = [
            Users.active == 1,
            Users.birth_date.is_not(None),
        ]
        if len(months) < 12:
            conditions.append(extract("month", Users.birth_date).in_(sorted(months)))

        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.first_name,
                    Users.last_name,
                    Users.name_in_school,
                    Users.avatar,
                    Users.birth_date,
                    Users.graduation_year,
                    Users.year,
                    UserProfiles.field_visibility,
                )
                .select_from(
                    Users.__table__.outerjoin(
                        UserProfiles.__table__, UserProfiles.user_id == Users.id
                    )
                )
                .where(*conditions)
                .order_by(Users.id)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def enabled_chapters(self) -> list[dict[str, Any]]:
        """Return only explicit public fields for enabled chapters."""
        rows = self._session.execute(
            select(
                AlumniChapter.id,
                AlumniChapter.chapter_name,
                AlumniChapter.location,
                AlumniChapter.is_enabled,
                AlumniChapter.created_at,
            )
            .where(AlumniChapter.is_enabled == 1)
            .order_by(AlumniChapter.chapter_name, AlumniChapter.id)
        ).mappings()
        return [dict(row) for row in rows]

    def public_cities(self) -> list[dict[str, Any]]:
        """Return city catalogue fields without exposing member records."""
        rows = self._session.execute(
            select(
                Cities.city_id,
                Cities.city,
                Cities.chapter_id,
                Cities.zone_id,
                Zones.zone,
            )
            .select_from(
                Cities.__table__.outerjoin(Zones.__table__, Zones.zone_id == Cities.zone_id)
            )
            .order_by(Cities.zone_id, Cities.city, Cities.city_id)
        ).mappings()
        return [dict(row) for row in rows]

    def public_zones(self) -> list[dict[str, Any]]:
        """Return zones plus only the coordinator fields needed for public projection."""
        rows = self._session.execute(
            select(
                Zones.zone_id,
                Zones.zone,
                Zones.chapter_id,
                Users.id.label("user_id"),
                Users.fullname,
                Users.first_name,
                Users.last_name,
                Users.phone,
                Users.avatar,
                Users.active,
                Users.email_verified,
                Users.is_approved,
                UserProfiles.is_visible,
                UserProfiles.field_visibility,
            )
            .select_from(
                Zones.__table__.outerjoin(
                    Users.__table__, Users.id == Zones.coordinator_user_id
                ).outerjoin(UserProfiles.__table__, UserProfiles.user_id == Users.id)
            )
            .order_by(Zones.zone_id)
        ).mappings()
        return [dict(row) for row in rows]

    def public_zone_cities(self) -> list[dict[str, Any]]:
        """Return every city once for deterministic in-memory zone grouping."""
        rows = self._session.execute(
            select(Cities.zone_id, Cities.city_id, Cities.city).order_by(
                Cities.zone_id,
                Cities.city,
                Cities.city_id,
            )
        ).mappings()
        return [dict(row) for row in rows]

    def zone_with_coordinator(
        self,
        *,
        zone_id: int | None = None,
        zone_name: str | None = None,
    ) -> dict[str, Any] | None:
        """Resolve one zone and only the fields needed for its safe coordinator projection."""
        query = (
            select(
                Zones.zone_id,
                Zones.zone,
                Users.id.label("user_id"),
                Users.fullname,
                Users.first_name,
                Users.last_name,
                Users.phone,
                Users.avatar,
                Users.active,
                Users.email_verified,
                Users.is_approved,
                UserProfiles.is_visible,
                UserProfiles.field_visibility,
            )
            .select_from(
                Zones.__table__.outerjoin(
                    Users.__table__, Users.id == Zones.coordinator_user_id
                ).outerjoin(UserProfiles.__table__, UserProfiles.user_id == Users.id)
            )
            .order_by(Zones.zone_id)
            .limit(1)
        )
        if zone_id is not None:
            query = query.where(Zones.zone_id == zone_id)
        elif zone_name is not None:
            query = query.where(func.lower(func.trim(Zones.zone)) == zone_name.casefold())
        else:
            return None
        row = self._session.execute(query).mappings().first()
        return self._as_dict(row)

    def zone_member_candidates(self, zone_id: int) -> list[dict[str, Any]]:
        """Read eligible account candidates without selecting credentials or unrelated PII."""
        normalized_city = func.lower(func.trim(Cities.city))
        zone_cities = (
            select(normalized_city.label("city_key"))
            .where(Cities.zone_id == zone_id)
            .group_by(normalized_city)
            .subquery()
        )
        source = Users.__table__.join(
            zone_cities,
            func.lower(func.trim(Users.city)) == zone_cities.c.city_key,
        ).outerjoin(UserProfiles.__table__, UserProfiles.user_id == Users.id)
        rows = self._session.execute(
            select(
                Users.id.label("user_id"),
                Users.fullname,
                Users.first_name,
                Users.last_name,
                Users.graduation_year,
                Users.avatar,
                Users.phone,
                Users.city,
                Users.is_coordinator,
                UserProfiles.is_visible,
                UserProfiles.field_visibility,
            )
            .select_from(source)
            .where(
                Users.active == 1,
                Users.email_verified == 1,
                Users.is_approved == 1,
            )
            .order_by(Users.fullname, Users.id)
        ).mappings()
        return [dict(row) for row in rows]

    def self_zone_account(self, user_id: int) -> dict[str, Any] | None:
        """Return current account state and city for the self-scoped zone lookup."""
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.city).where(Users.id == user_id).limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def city_zone_id(self, city: str) -> int | None:
        """Resolve a valid zone through the oldest case-insensitive city mapping."""
        value = self._session.scalar(
            select(Cities.zone_id)
            .join(Zones, Zones.zone_id == Cities.zone_id)
            .where(func.lower(func.trim(Cities.city)) == city.casefold())
            .order_by(Cities.city_id)
            .limit(1)
        )
        return int(value) if value is not None else None

    def public_vouchers(self, graduation_year: int | None) -> list[dict[str, Any]]:
        """Return only fields required by the public registration picker."""
        query = select(
            Users.id.label("voucher_id"),
            Users.fullname,
            Users.graduation_year,
            Users.chapter_id,
        ).where(Users.voucher == "yes", Users.active == 1)
        if graduation_year is not None:
            query = query.where(Users.graduation_year == graduation_year)
        rows = self._session.execute(query.order_by(Users.fullname, Users.id)).mappings()
        return [dict(row) for row in rows]

    def voucher_account(self, user_id: int) -> dict[str, Any] | None:
        """Return the current fields that grant voucher-list ownership."""
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.voucher).where(Users.id == user_id).limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def pending_vouches(self, voucher_user_id: int) -> list[dict[str, Any]]:
        """Return pending registrants assigned to exactly one voucher owner."""
        rows = self._session.execute(
            select(
                Vouches.id.label("vouch_id"),
                Vouches.status,
                Vouches.created_at,
                Users.id.label("user_id"),
                Users.fullname,
                Users.email,
                Users.graduation_year,
                Users.nick_name,
            )
            .select_from(Vouches.__table__.join(Users.__table__, Users.id == Vouches.register_id))
            .where(Vouches.voucher_id == voucher_user_id, Vouches.status == "pending")
            .order_by(Vouches.created_at, Vouches.id)
        ).mappings()
        return [dict(row) for row in rows]

    def lock_vouch(self, vouch_id: int) -> dict[str, Any] | None:
        """Lock one vouch row before checking ownership and transition state."""
        row = (
            self._session.execute(
                select(
                    Vouches.id,
                    Vouches.register_id,
                    Vouches.voucher_id,
                    Vouches.status,
                    Vouches.reason,
                )
                .where(Vouches.id == vouch_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_vouch_accounts(
        self,
        actor_user_id: int,
        registrant_user_id: int,
    ) -> tuple[dict[str, Any] | None, dict[str, Any] | None]:
        """Lock voucher and registrant accounts in deterministic identifier order."""
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.email,
                    Users.active,
                    Users.voucher,
                    Users.email_verified,
                    Users.is_approved,
                    Users.profile_status,
                )
                .where(Users.id.in_((actor_user_id, registrant_user_id)))
                .order_by(Users.id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        accounts = {int(row["id"]): dict(row) for row in rows}
        return accounts.get(actor_user_id), accounts.get(registrant_user_id)

    def update_vouch_decision(
        self,
        vouch_id: int,
        *,
        status: str,
        reason: str | None,
        updated_at: datetime,
    ) -> None:
        """Apply only the reviewed status, reason, and timestamp fields."""
        self._session.execute(
            VOUCHES_TABLE.update()
            .where(Vouches.id == vouch_id)
            .values(status=status, reason=reason, updated_at=updated_at)
        )

    def account_manager_recipients(self) -> list[dict[str, Any]]:
        """Return active exact-role recipients for voucher-approval notice."""
        rows = (
            self._session.execute(
                select(Users.id, Users.email, Users.fullname)
                .where(
                    Users.active == 1,
                    func.lower(func.trim(Users.user_role)).in_(
                        (
                            "admin",
                            "administrator",
                            "manager",
                            "superadmin",
                            "super admin",
                            "super administrator",
                        )
                    ),
                )
                .order_by(Users.id)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def user_chapter(self, user_id: int) -> dict[str, Any] | None:
        """Return the oldest deterministic category assignment for one user."""
        row = (
            self._session.execute(
                select(
                    AlumniCategory.id.label("category_id"),
                    AlumniCategory.user_id,
                    AlumniCategory.year,
                    AlumniCategory.location,
                    AlumniCategory.created_at.label("joined_at"),
                    AlumniChapter.id.label("chapter_id"),
                    AlumniChapter.chapter_name,
                    AlumniChapter.is_enabled,
                )
                .select_from(
                    AlumniCategory.__table__.outerjoin(
                        AlumniChapter.__table__,
                        AlumniChapter.id == AlumniCategory.chapter_id,
                    )
                )
                .where(AlumniCategory.user_id == user_id)
                .order_by(AlumniCategory.id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def setup_parameter(self, setup_name: str) -> dict[str, Any] | None:
        """Return the oldest deterministic row for one legacy setup name."""
        row = (
            self._session.execute(
                select(
                    SetupParameters.setup_id,
                    SetupParameters.setup_name,
                    SetupParameters.setup_value,
                )
                .where(SetupParameters.setup_name == setup_name)
                .order_by(SetupParameters.setup_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_approval_accounts(
        self,
        actor_user_id: int,
        target_user_id: int,
    ) -> tuple[dict[str, Any] | None, dict[str, Any] | None]:
        """Lock actor and target in deterministic order for one approval transition."""
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.active,
                    Users.email_verified,
                    Users.is_approved,
                    Users.profile_status,
                    Users.user_role,
                    Users.is_coordinator,
                )
                .where(Users.id.in_((actor_user_id, target_user_id)))
                .order_by(Users.id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        accounts = {int(row["id"]): dict(row) for row in rows}
        return accounts.get(actor_user_id), accounts.get(target_user_id)

    def update_approval_state(
        self,
        user_id: int,
        *,
        approved: bool,
        active: bool,
        profile_status: str,
        updated_at: datetime,
    ) -> None:
        """Apply one allowlisted account-approval state transition."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(
                is_approved=int(approved),
                active=int(active),
                profile_status=profile_status,
                updated_at=updated_at,
            )
        )

    def lock_managed_accounts(
        self,
        actor_user_id: int,
        target_user_id: int,
    ) -> tuple[dict[str, Any] | None, dict[str, Any] | None]:
        """Lock current actor and target facts in deterministic identifier order."""
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.active,
                    Users.email_verified,
                    Users.is_approved,
                    Users.user_role,
                    Users.is_coordinator,
                )
                .where(Users.id.in_((actor_user_id, target_user_id)))
                .order_by(Users.id)
                .with_for_update()
            )
            .mappings()
            .all()
        )
        accounts = {int(row["id"]): dict(row) for row in rows}
        return accounts.get(actor_user_id), accounts.get(target_user_id)

    def update_active_state(self, user_id: int, *, active: bool, updated_at: datetime) -> None:
        """Apply one allowlisted account-active transition."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(active=int(active), updated_at=updated_at)
        )

    def update_user_role(self, user_id: int, *, user_role: str, updated_at: datetime) -> None:
        """Apply one allowlisted account-role transition."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(user_role=user_role, updated_at=updated_at)
        )

    def revoke_refresh_tokens(self, user_id: int) -> None:
        """Revoke every active refresh session when an account is deactivated."""
        self._session.execute(
            REFRESH_TOKENS_TABLE.update()
            .where(JwtRefreshTokens.user_id == user_id, JwtRefreshTokens.revoked == 0)
            .values(revoked=1)
        )

    def managed_account(self, user_id: int) -> dict[str, Any] | None:
        """Return only fields in the account-management response contract."""
        row = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.email,
                    Users.phone,
                    Users.user_role,
                    Users.active,
                    Users.profile_status,
                )
                .where(Users.id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_profile_visibility(self, user_id: int) -> dict[str, Any] | None:
        """Lock an optional profile row before merging visibility settings."""
        row = (
            self._session.execute(
                select(
                    UserProfiles.id,
                    UserProfiles.user_id,
                    UserProfiles.is_visible,
                    UserProfiles.field_visibility,
                )
                .where(UserProfiles.user_id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def profile_visibility(self, user_id: int) -> dict[str, Any] | None:
        """Read the bounded visibility state for one profile."""
        row = (
            self._session.execute(
                select(UserProfiles.is_visible, UserProfiles.field_visibility)
                .where(UserProfiles.user_id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def directory_members(
        self,
        *,
        actor_user_id: int,
        user_id: int | None,
        search: str | None,
        year: int | None,
        offset: int,
        limit: int,
    ) -> tuple[list[dict[str, Any]], int]:
        """Return a bounded approved-member projection without credential columns."""
        conditions: list[Any] = [
            Users.email_verified == 1,
            Users.active == 1,
            Users.is_approved == 1,
            or_(
                Users.id == actor_user_id,
                UserProfiles.id.is_(None),
                UserProfiles.is_visible.is_(None),
                UserProfiles.is_visible != 0,
            ),
        ]
        if user_id is not None:
            conditions.append(Users.id == user_id)
        if year is not None:
            conditions.append(Users.graduation_year == year)
        if search is not None:
            term = search.casefold()
            conditions.append(
                or_(
                    func.lower(Users.fullname).contains(term, autoescape=True),
                    func.lower(Users.first_name).contains(term, autoescape=True),
                    func.lower(Users.last_name).contains(term, autoescape=True),
                    func.lower(Users.name_in_school).contains(term, autoescape=True),
                )
            )

        base = Users.__table__.outerjoin(UserProfiles.__table__, UserProfiles.user_id == Users.id)
        total = int(
            self._session.scalar(select(func.count(Users.id)).select_from(base).where(*conditions))
            or 0
        )

        city_lookup = (
            select(
                func.lower(Cities.city).label("city_key"),
                func.min(Cities.city_id).label("city_id"),
                func.min(Cities.zone_id).label("zone_id"),
            )
            .group_by(func.lower(Cities.city))
            .subquery()
        )
        source = base.outerjoin(
            city_lookup, func.lower(Users.city) == city_lookup.c.city_key
        ).outerjoin(Zones.__table__, Zones.zone_id == city_lookup.c.zone_id)
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.first_name,
                    Users.last_name,
                    Users.graduation_year,
                    Users.name_in_school,
                    Users.nick_name,
                    Users.house_color,
                    Users.avatar,
                    Users.bio,
                    Users.phone,
                    Users.alternative_phone,
                    Users.birth_date,
                    Users.residential_address,
                    Users.area,
                    Users.city,
                    Users.state,
                    Users.employment_status,
                    Users.occupation,
                    Users.industry_sector,
                    Users.years_of_experience,
                    Users.is_coordinator,
                    Users.is_volunteer,
                    Users.user_role,
                    Users.active,
                    Users.email_verified,
                    Users.is_approved,
                    UserProfiles.linkedin,
                    UserProfiles.twitter,
                    UserProfiles.tiktok,
                    UserProfiles.facebook,
                    UserProfiles.website,
                    UserProfiles.instagram,
                    UserProfiles.current_company,
                    UserProfiles.current_position,
                    UserProfiles.city.label("profile_city"),
                    UserProfiles.country.label("profile_country"),
                    UserProfiles.year.label("profile_year"),
                    UserProfiles.is_visible,
                    UserProfiles.field_visibility,
                    city_lookup.c.city_id,
                    city_lookup.c.zone_id,
                    Zones.zone.label("zone_name"),
                )
                .select_from(source)
                .where(*conditions)
                .order_by(Users.id.desc())
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows], total

    def administrative_members(
        self,
        *,
        actor_user_id: int,
        action_type: str,
        manageable_role_aliases: frozenset[str],
        user_id: int | None,
        search: str | None,
        year: int | None,
        offset: int,
        limit: int,
    ) -> tuple[list[dict[str, Any]], int]:
        """Return only verified, downward-manageable account rows."""
        normalized_role = func.lower(
            func.trim(func.replace(func.replace(Users.user_role, "_", " "), "-", " "))
        )
        conditions: list[Any] = [
            Users.id != actor_user_id,
            Users.email_verified == 1,
            or_(Users.user_role.is_(None), normalized_role.in_(manageable_role_aliases)),
        ]
        if action_type == "pending_approval":
            conditions.extend((Users.is_approved == 0, Users.active == 1))
        if user_id is not None:
            conditions.append(Users.id == user_id)
        if year is not None:
            conditions.append(Users.graduation_year == year)
        if search is not None:
            term = search.casefold()
            conditions.append(
                or_(
                    func.lower(Users.fullname).contains(term, autoescape=True),
                    func.lower(Users.email).contains(term, autoescape=True),
                    func.lower(Users.phone).contains(term, autoescape=True),
                )
            )

        total = int(self._session.scalar(select(func.count(Users.id)).where(*conditions)) or 0)
        rows = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.email,
                    Users.phone,
                    Users.graduation_year,
                    Users.user_role,
                    Users.active,
                    Users.profile_status,
                    Users.is_approved,
                    Users.email_verified,
                )
                .where(*conditions)
                .order_by(Users.id.desc())
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows], total

    def upsert_profile_visibility(
        self,
        user_id: int,
        *,
        existing_profile_id: int | None,
        is_visible: bool,
        field_visibility: str,
        updated_at: datetime,
    ) -> None:
        """Persist only the global and allowlisted per-field visibility state."""
        if existing_profile_id is not None:
            self._session.execute(
                USER_PROFILES_TABLE.update()
                .where(UserProfiles.id == existing_profile_id)
                .values(
                    is_visible=int(is_visible),
                    field_visibility=field_visibility,
                    updated_at=updated_at,
                )
            )
            return
        self._session.execute(
            USER_PROFILES_TABLE.insert().values(
                user_id=user_id,
                instagram="",
                tiktok="",
                is_visible=int(is_visible),
                field_visibility=field_visibility,
                created_at=updated_at,
                updated_at=updated_at,
            )
        )

    def update_profile_user(
        self,
        user_id: int,
        *,
        changes: dict[str, object],
        updated_at: datetime,
    ) -> None:
        """Apply an allowlisted user-table partial profile update."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(**changes, onboarding_completion=1, updated_at=updated_at)
        )

    def chapter_exists(self, chapter_id: int) -> bool:
        """Check the reviewed chapter foreign key before attempting a profile update."""
        return (
            self._session.scalar(
                select(AlumniChapter.id).where(AlumniChapter.id == chapter_id).limit(1)
            )
            is not None
        )

    def upsert_profile_fields(
        self,
        user_id: int,
        *,
        existing_profile_id: int | None,
        changes: dict[str, str | None],
        updated_at: datetime,
    ) -> None:
        """Apply only allowlisted extended-profile fields without changing visibility."""
        if existing_profile_id is not None:
            self._session.execute(
                USER_PROFILES_TABLE.update()
                .where(UserProfiles.id == existing_profile_id)
                .values(**changes, updated_at=updated_at)
            )
            return
        insert_changes = dict(changes)
        self._session.execute(
            USER_PROFILES_TABLE.insert().values(
                user_id=user_id,
                instagram=insert_changes.pop("instagram", "") or "",
                tiktok=insert_changes.pop("tiktok", "") or "",
                **insert_changes,
                created_at=updated_at,
                updated_at=updated_at,
            )
        )

    def insert_avatar_attachment(
        self,
        user_id: int,
        *,
        original_filename: str,
        relative_path: str,
        created_at: datetime,
    ) -> None:
        """Record the legacy attachment metadata for one committed avatar path."""
        self._session.execute(
            ATTACHMENTS_TABLE.insert().values(
                user_id=user_id,
                file_type="profile_image",
                filename=original_filename,
                attachment_file=relative_path,
                dateadded=created_at,
            )
        )

    def approval_user(self, user_id: int) -> dict[str, Any] | None:
        """Return only fields in the administrator approval response contract."""
        row = (
            self._session.execute(
                select(
                    Users.id,
                    Users.fullname,
                    Users.email,
                    Users.user_role,
                    Users.active,
                    Users.is_approved,
                    Users.profile_status,
                )
                .where(Users.id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def profile_user(self, user_id: int) -> dict[str, Any] | None:
        """Fetch a credential-free, explicit user projection."""
        row = (
            self._session.execute(
                select(
                    Users.id,
                    Users.user_code,
                    Users.email,
                    Users.fullname,
                    Users.first_name,
                    Users.last_name,
                    Users.phone,
                    Users.user_role,
                    Users.avatar,
                    Users.active,
                    Users.email_verified,
                    Users.is_approved,
                    Users.chapter_id,
                    Users.graduation_year,
                    Users.department,
                    Users.bio,
                    Users.name_in_school,
                    Users.alternative_phone,
                    Users.birth_date,
                    Users.house_color,
                    Users.is_coordinator,
                    Users.residential_address,
                    Users.area,
                    Users.city,
                    Users.employment_status,
                    Users.occupation,
                    Users.industry_sector,
                    Users.years_of_experience,
                    Users.is_volunteer,
                    Users.nick_name,
                    Users.state,
                    Users.year,
                    Users.onboarding_completion,
                )
                .where(Users.id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def profile_details(self, user_id: int) -> dict[str, Any] | None:
        """Fetch the optional extended profile using an explicit field list."""
        row = (
            self._session.execute(
                select(
                    UserProfiles.linkedin,
                    UserProfiles.twitter,
                    UserProfiles.tiktok,
                    UserProfiles.facebook,
                    UserProfiles.instagram,
                    UserProfiles.field_visibility,
                    UserProfiles.website,
                    UserProfiles.current_company,
                    UserProfiles.current_position,
                    UserProfiles.city,
                    UserProfiles.country,
                    UserProfiles.skills,
                    UserProfiles.achievements,
                    UserProfiles.year,
                    UserProfiles.is_visible,
                )
                .where(UserProfiles.user_id == user_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def roles_for_user(self, user_id: int) -> list[str]:
        """Fetch metadata role labels in deterministic order."""
        return list(
            self._session.scalars(
                select(Roles.role_name).where(Roles.user_id == user_id).order_by(Roles.id)
            )
        )

    def zone_for_city(self, city: str | None) -> dict[str, Any] | None:
        """Resolve the legacy city-to-zone projection."""
        if not city:
            return None
        row = (
            self._session.execute(
                select(Cities.city_id, Cities.zone_id, Zones.zone.label("zone_name"))
                .select_from(
                    Cities.__table__.outerjoin(Zones.__table__, Cities.zone_id == Zones.zone_id)
                )
                .where(func.lower(Cities.city) == city.lower())
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    @staticmethod
    def _as_dict(row: RowMapping | None) -> dict[str, Any] | None:
        return dict(row) if row is not None else None
