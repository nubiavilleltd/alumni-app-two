"""Authorized member profile use cases."""

from __future__ import annotations

from typing import Any
from urllib.parse import urljoin

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.core.config import Settings
from app.repositories.members import MemberRepository
from app.schemas.members import MemberProfileDetails, UserProfileResponse


class MemberProfileError(Exception):
    """A member profile request failed an account or authorization rule."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MemberService:
    """Build bounded member projections after current-state authorization."""

    def __init__(self, session: Session, settings: Settings) -> None:
        self._session = session
        self._settings = settings
        self._repository = MemberRepository(session)

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

    def _absolute_avatar(self, avatar: Any) -> str | None:
        if not avatar:
            return None
        base_url = str(self._settings.public_base_url) if self._settings.public_base_url else None
        return urljoin(base_url, str(avatar)) if base_url else str(avatar)
