"""Allowlisted persistence queries for member profile use cases."""

from __future__ import annotations

from typing import Any

from sqlalchemy import RowMapping, func, select
from sqlalchemy.orm import Session

from app.models.generated import Cities, Roles, UserProfiles, Users, Zones


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
