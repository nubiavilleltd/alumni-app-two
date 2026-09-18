"""SQLAlchemy Core persistence for the authentication slice."""

from __future__ import annotations

from datetime import datetime
from typing import Any, cast

from sqlalchemy import CursorResult, RowMapping, Table, func, select
from sqlalchemy.orm import Session

from app.models.generated import (
    AlumniCategory,
    AlumniChapter,
    Attachments,
    Cities,
    Groups,
    JwtRefreshTokens,
    RegisterUserOtp,
    Roles,
    UserProfiles,
    Users,
    UsersGroups,
    UserSocialAccounts,
    Vouches,
    Zones,
)

USERS_TABLE = cast(Table, Users.__table__)
USERS_GROUPS_TABLE = cast(Table, UsersGroups.__table__)
ALUMNI_CATEGORY_TABLE = cast(Table, AlumniCategory.__table__)
PROFILES_TABLE = cast(Table, UserProfiles.__table__)
VOUCHES_TABLE = cast(Table, Vouches.__table__)
ATTACHMENTS_TABLE = cast(Table, Attachments.__table__)
REFRESH_TOKENS_TABLE = cast(Table, JwtRefreshTokens.__table__)
OTP_TABLE = cast(Table, RegisterUserOtp.__table__)
SOCIAL_ACCOUNTS_TABLE = cast(Table, UserSocialAccounts.__table__)


class AuthRepository:
    """Keep authentication queries explicit and transaction-neutral."""

    def __init__(self, session: Session) -> None:
        self._session = session

    def user_by_email(self, email: str) -> dict[str, Any] | None:
        """Fetch one user by normalized email."""
        row = (
            self._session.execute(
                select(Users.__table__).where(func.lower(Users.email) == email.lower()).limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def enabled_chapter_exists(self, chapter_id: int) -> bool:
        """Return whether registration may target the selected chapter."""
        return (
            self._session.scalar(
                select(AlumniChapter.id)
                .where(AlumniChapter.id == chapter_id, AlumniChapter.is_enabled == 1)
                .limit(1)
            )
            is not None
        )

    def city_belongs_to_chapter(self, city: str, chapter_id: int) -> bool:
        """Bind the frontend city selection to the chapter advertised with it."""
        return (
            self._session.scalar(
                select(Cities.city_id)
                .where(
                    func.lower(func.trim(Cities.city)) == city.strip().lower(),
                    Cities.chapter_id == chapter_id,
                )
                .limit(1)
            )
            is not None
        )

    def eligible_voucher_exists(self, voucher_id: int) -> bool:
        """Accept only a current active member explicitly enabled as a voucher."""
        return (
            self._session.scalar(
                select(Users.id)
                .where(
                    Users.id == voucher_id,
                    Users.active == 1,
                    func.lower(func.trim(Users.voucher)) == "yes",
                )
                .limit(1)
            )
            is not None
        )

    def member_group_exists(self, group_id: int) -> bool:
        """Verify the reviewed Ion Auth member-group target before inserting."""
        return (
            self._session.scalar(select(Groups.id).where(Groups.id == group_id).limit(1))
            is not None
        )

    def user_code_exists(self, user_code: str) -> bool:
        """Check the compatibility code before attempting the unique insert."""
        return (
            self._session.scalar(select(Users.id).where(Users.user_code == user_code).limit(1))
            is not None
        )

    def insert_registration_user(self, values: dict[str, Any]) -> int:
        """Insert the server-controlled user row and return its generated identity."""
        result = cast(
            CursorResult[Any], self._session.execute(USERS_TABLE.insert().values(**values))
        )
        inserted_key = result.inserted_primary_key
        if inserted_key is None:
            raise RuntimeError("Registration insert did not return a user identity")
        return int(inserted_key[0])

    def add_user_to_group(self, user_id: int, group_id: int) -> None:
        """Preserve the legacy default alumni membership group."""
        self._session.execute(
            USERS_GROUPS_TABLE.insert().values(user_id=user_id, group_id=group_id)
        )

    def insert_alumni_category(
        self,
        user_id: int,
        chapter_id: int,
        year: str,
        location: str,
        created_at: datetime,
    ) -> None:
        """Create the chapter membership record required by member workflows."""
        self._session.execute(
            ALUMNI_CATEGORY_TABLE.insert().values(
                user_id=user_id,
                chapter_id=chapter_id,
                year=year,
                location=location,
                created_at=created_at,
            )
        )

    def insert_registration_profile(
        self,
        user_id: int,
        chapter_id: int,
        year: str,
        city: str,
        field_visibility: str,
        now: datetime,
    ) -> None:
        """Seed a private-by-default profile using explicit non-null legacy values."""
        self._session.execute(
            PROFILES_TABLE.insert().values(
                user_id=user_id,
                chapter_id=chapter_id,
                year=year,
                city=city,
                instagram="",
                tiktok="",
                is_visible=0,
                field_visibility=field_visibility,
                created_at=now,
                updated_at=now,
            )
        )

    def insert_pending_vouch(
        self,
        registrant_user_id: int,
        voucher_id: int,
        now: datetime,
    ) -> None:
        """Create the owned pending voucher relationship without sending mail yet."""
        self._session.execute(
            VOUCHES_TABLE.insert().values(
                register_id=registrant_user_id,
                voucher_id=voucher_id,
                status="pending",
                created_at=now,
                updated_at=now,
            )
        )

    def update_registration_avatar(self, user_id: int, relative_path: str) -> None:
        """Store the normalized avatar path on the new account."""
        self._session.execute(
            USERS_TABLE.update().where(Users.id == user_id).values(avatar=relative_path)
        )

    def insert_registration_avatar_attachment(
        self,
        user_id: int,
        original_filename: str,
        relative_path: str,
        created_at: datetime,
    ) -> None:
        """Preserve attachment metadata for an optional registration avatar."""
        self._session.execute(
            ATTACHMENTS_TABLE.insert().values(
                user_id=user_id,
                file_type="profile_image",
                filename=original_filename,
                attachment_file=relative_path,
                dateadded=created_at,
            )
        )

    def user_by_id(self, user_id: int) -> dict[str, Any] | None:
        """Fetch one user by primary key."""
        row = (
            self._session.execute(select(Users.__table__).where(Users.id == user_id).limit(1))
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def lock_user_by_id(self, user_id: int) -> dict[str, Any] | None:
        """Lock one user row for an account-state transition."""
        row = (
            self._session.execute(
                select(Users.__table__).where(Users.id == user_id).with_for_update().limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def profile_for_user(self, user_id: int) -> dict[str, Any] | None:
        """Fetch the optional extended profile."""
        row = (
            self._session.execute(
                select(UserProfiles.__table__).where(UserProfiles.user_id == user_id).limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def roles_for_user(self, user_id: int) -> list[str]:
        """Fetch system role names in deterministic order."""
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

    def update_password(self, user_id: int, encoded_hash: str) -> None:
        """Replace a legacy password hash after successful verification."""
        self._session.execute(
            USERS_TABLE.update().where(Users.id == user_id).values(password=encoded_hash)
        )

    def set_password_reset(
        self,
        user_id: int,
        token_hash: str | None,
        expires_at: datetime | None,
    ) -> None:
        """Set or clear a user's hashed, finite password-reset credential."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(reset_token=token_hash, reset_expires=expires_at)
        )

    def lock_user_by_reset_token(self, token_hash: str) -> dict[str, Any] | None:
        """Lock the user that owns a reset token so consumption is one-time."""
        row = (
            self._session.execute(
                select(Users.__table__)
                .where(Users.reset_token == token_hash)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def update_last_login(self, user_id: int, unix_timestamp: int) -> None:
        """Record a successful full login using the legacy integer timestamp."""
        self._session.execute(
            USERS_TABLE.update().where(Users.id == user_id).values(last_login=unix_timestamp)
        )

    def replace_verification_code(self, user_id: int, email: str, code: int) -> int:
        """Deactivate older codes, store a replacement, and mirror it on the user row."""
        self._session.execute(
            OTP_TABLE.update()
            .where(RegisterUserOtp.email == email, RegisterUserOtp.is_active == 1)
            .values(is_active=0)
        )
        result = cast(
            CursorResult[Any],
            self._session.execute(OTP_TABLE.insert().values(email=email, otp=code, is_active=1)),
        )
        self._session.execute(
            USERS_TABLE.update().where(Users.id == user_id).values(verify_token=str(code))
        )
        inserted_key = result.inserted_primary_key
        if inserted_key is None:
            raise RuntimeError("Verification code insert did not return a primary key")
        return int(inserted_key[0])

    def lock_verification_code(self, email: str, code: int) -> dict[str, Any] | None:
        """Lock one active verification-code row for one-time consumption."""
        row = (
            self._session.execute(
                select(RegisterUserOtp.__table__)
                .where(
                    RegisterUserOtp.email == email,
                    RegisterUserOtp.otp == code,
                    RegisterUserOtp.is_active == 1,
                )
                .order_by(RegisterUserOtp.id.desc())
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def deactivate_verification_code(self, code_id: int, now: datetime) -> None:
        """Consume or invalidate one verification-code row."""
        self._session.execute(
            OTP_TABLE.update()
            .where(RegisterUserOtp.id == code_id)
            .values(is_active=0, updated_at=now)
        )

    def mark_email_verified(self, user_id: int) -> None:
        """Apply the legacy verified-and-active account transition."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(email_verified=1, verify_token=None, active=1)
        )

    def clear_verification_mirror(self, user_id: int, code: str) -> None:
        """Clear the user-row mirror only when it still contains the failed code."""
        self._session.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id, Users.verify_token == code)
            .values(verify_token=None)
        )

    def account_manager_recipients(self) -> list[dict[str, Any]]:
        """Return active recipients with reviewed account-management roles."""
        rows = (
            self._session.execute(
                select(Users.email, Users.fullname)
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

    def pending_voucher_recipient(self, registrant_user_id: int) -> dict[str, Any] | None:
        """Return the first assigned user for a pending voucher relationship."""
        row = (
            self._session.execute(
                select(Users.email, Users.fullname)
                .select_from(
                    Vouches.__table__.join(Users.__table__, Vouches.voucher_id == Users.id)
                )
                .where(
                    Vouches.register_id == registrant_user_id,
                    Vouches.status == "pending",
                    Users.active == 1,
                )
                .order_by(Vouches.id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def store_refresh_token(
        self,
        user_id: int,
        token_hash: str,
        expires_at: datetime,
        *,
        max_active: int = 5,
    ) -> None:
        """Store a token hash and revoke active tokens beyond the per-user cap."""
        self._session.execute(
            REFRESH_TOKENS_TABLE.insert().values(
                user_id=user_id,
                token=token_hash,
                revoked=0,
                expires_at=expires_at,
            )
        )
        active_ids = list(
            self._session.scalars(
                select(JwtRefreshTokens.id)
                .where(
                    JwtRefreshTokens.user_id == user_id,
                    JwtRefreshTokens.revoked == 0,
                )
                .order_by(JwtRefreshTokens.created_at.desc(), JwtRefreshTokens.id.desc())
                .offset(max_active)
            )
        )
        if active_ids:
            self._session.execute(
                REFRESH_TOKENS_TABLE.update()
                .where(JwtRefreshTokens.id.in_(active_ids))
                .values(revoked=1)
            )

    def lock_refresh_token(self, token_hash: str) -> dict[str, Any] | None:
        """Lock a refresh-token row so concurrent rotations serialize."""
        row = (
            self._session.execute(
                select(JwtRefreshTokens.__table__)
                .where(JwtRefreshTokens.token == token_hash)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def revoke_refresh_token(self, token_id: int) -> None:
        """Revoke one refresh token."""
        self._session.execute(
            REFRESH_TOKENS_TABLE.update().where(JwtRefreshTokens.id == token_id).values(revoked=1)
        )

    def revoke_all_refresh_tokens(self, user_id: int) -> None:
        """Revoke every active refresh token for a user after reuse detection."""
        self._session.execute(
            REFRESH_TOKENS_TABLE.update()
            .where(JwtRefreshTokens.user_id == user_id, JwtRefreshTokens.revoked == 0)
            .values(revoked=1)
        )

    def social_account_by_provider(
        self, provider: str, provider_user_id: str
    ) -> dict[str, Any] | None:
        """Find the user linked to a provider identity."""
        row = (
            self._session.execute(
                select(UserSocialAccounts.__table__)
                .where(
                    UserSocialAccounts.provider == provider,
                    UserSocialAccounts.provider_user_id == provider_user_id,
                )
                .limit(1)
            )
            .mappings()
            .first()
        )
        return self._as_dict(row)

    def link_social_account(
        self, user_id: int, provider: str, provider_user_id: str, email: str | None, now: datetime
    ) -> None:
        """Link a verified provider identity to a user account."""
        self._session.execute(
            SOCIAL_ACCOUNTS_TABLE.insert().values(
                user_id=user_id,
                provider=provider,
                provider_user_id=provider_user_id,
                email=email or None,
                created_at=now,
            )
        )

    def unlink_social_account(self, user_id: int, provider: str) -> bool:
        """Remove one provider link; return whether a row was removed."""
        result = cast(
            CursorResult[Any],
            self._session.execute(
                SOCIAL_ACCOUNTS_TABLE.delete().where(
                    UserSocialAccounts.user_id == user_id,
                    UserSocialAccounts.provider == provider,
                )
            ),
        )
        return result.rowcount == 1

    def social_account_count(self, user_id: int) -> int:
        """Count the social providers linked to a user."""
        return int(
            self._session.scalar(
                select(func.count())
                .select_from(UserSocialAccounts.__table__)
                .where(UserSocialAccounts.user_id == user_id)
            )
            or 0
        )

    @staticmethod
    def _as_dict(row: RowMapping | None) -> dict[str, Any] | None:
        return dict(row) if row is not None else None
