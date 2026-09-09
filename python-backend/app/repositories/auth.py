"""SQLAlchemy Core persistence for the authentication slice."""

from __future__ import annotations

from datetime import datetime
from typing import Any, cast

from sqlalchemy import CursorResult, RowMapping, Table, func, select
from sqlalchemy.orm import Session

from app.models.generated import (
    Cities,
    JwtRefreshTokens,
    RegisterUserOtp,
    Roles,
    UserProfiles,
    Users,
    Vouches,
    Zones,
)

USERS_TABLE = cast(Table, Users.__table__)
REFRESH_TOKENS_TABLE = cast(Table, JwtRefreshTokens.__table__)
OTP_TABLE = cast(Table, RegisterUserOtp.__table__)


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

    @staticmethod
    def _as_dict(row: RowMapping | None) -> dict[str, Any] | None:
        return dict(row) if row is not None else None
