"""Transactional authentication use cases."""

from __future__ import annotations

import json
import secrets
from datetime import UTC, datetime, timedelta
from typing import Any
from urllib.parse import urlencode, urljoin

import structlog
from sqlalchemy.exc import IntegrityError, SQLAlchemyError
from sqlalchemy.orm import Session

from app.core.config import Settings
from app.core.errors import (
    AccessCodeError,
    AccountVerificationError,
    InvalidCredentialsError,
    InvalidRefreshTokenError,
    InvalidResetTokenError,
    MailDeliveryError,
    PasswordChangeError,
)
from app.core.security import IssuedTokens, PasswordService, TokenService
from app.integrations.mail import Mailer
from app.integrations.uploads import AvatarStorage, PreparedAvatar
from app.repositories.auth import AuthRepository
from app.schemas.auth import (
    LoginResponse,
    ProfileResponse,
    RefreshResponse,
    RegistrationRequest,
    RegistrationResponse,
)

logger = structlog.get_logger(__name__)

_REGISTRATION_PROFILE_VISIBILITY = json.dumps(
    {
        "avatar": False,
        "phone": False,
        "alternative_phone": False,
        "birth_date": False,
        "residential_address": False,
        "area": False,
        "city": False,
        "employment_status": False,
        "occupation": False,
        "industry_sector": False,
        "years_of_experience": False,
        "is_volunteer": False,
    },
    separators=(",", ":"),
)
_MEMBER_GROUP_ID = 2


class AccountStateError(Exception):
    """Authenticated credentials belong to an account that cannot fully sign in."""

    def __init__(self, http_status: int, body_status: int, message: str, user_id: int) -> None:
        super().__init__(message)
        self.http_status = http_status
        self.body_status = body_status
        self.message = message
        self.user_id = user_id


class RegistrationError(Exception):
    """A public registration request cannot safely be completed."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class AuthService:
    """Coordinate password verification, account policy, and token persistence."""

    def __init__(self, session: Session, settings: Settings, mailer: Mailer | None = None) -> None:
        self._session = session
        self._settings = settings
        self._repository = AuthRepository(session)
        self._passwords = PasswordService()
        self._tokens = TokenService(settings)
        self._mailer = mailer

    def register(
        self,
        request: RegistrationRequest,
        ip_address: str,
        avatar: PreparedAvatar | None = None,
        storage: AvatarStorage | None = None,
    ) -> RegistrationResponse:
        """Create all required registration rows atomically, then deliver verification mail."""
        normalized_email = str(request.email).strip().lower()
        fullname = f"{request.first_name} {request.last_name}"
        now = datetime.now(UTC).replace(tzinfo=None)
        registration_year = str(now.year)
        verification_code = str(secrets.randbelow(900_000) + 100_000)
        password_hash = self._passwords.hash(request.password)
        legacy_presence_flag = 1
        stored_avatar = None
        user_id: int | None = None
        user_code = ""

        try:
            with self._session.begin():
                if self._repository.user_by_email(normalized_email) is not None:
                    raise RegistrationError(
                        "registration_email_exists",
                        "Email already exists, kindly use another email",
                        409,
                    )
                if not self._repository.enabled_chapter_exists(request.chapter_id):
                    raise RegistrationError(
                        "registration_chapter_invalid",
                        "Selected chapter is unavailable",
                        400,
                    )
                if not self._repository.city_belongs_to_chapter(request.city, request.chapter_id):
                    raise RegistrationError(
                        "registration_city_invalid",
                        "Selected city does not belong to the selected chapter",
                        400,
                    )
                if not self._repository.member_group_exists(_MEMBER_GROUP_ID):
                    raise RegistrationError(
                        "registration_configuration_unavailable",
                        "Registration service is temporarily unavailable",
                        503,
                    )
                if request.voucher_id is not None and not self._repository.eligible_voucher_exists(
                    request.voucher_id
                ):
                    raise RegistrationError(
                        "registration_voucher_invalid",
                        "Selected voucher is unavailable",
                        400,
                    )

                user_code = self._generate_user_code(request.graduation_year, normalized_email)
                user_id = self._repository.insert_registration_user(
                    {
                        "chapter_id": request.chapter_id,
                        "ip_address": ip_address,
                        "username": normalized_email,
                        "email": normalized_email,
                        "password": password_hash,
                        "has_password": legacy_presence_flag,
                        "onboarding_completion": 1,
                        "nick_name": request.nick_name,
                        "state": request.state,
                        "country": "Nigeria",
                        "created_on": int(now.replace(tzinfo=UTC).timestamp()),
                        "userAccessCode": "",
                        "profile_status": "No",
                        "voucher": "",
                        "resetKey": "",
                        "user_code": user_code,
                        "first_name": request.first_name,
                        "last_name": request.last_name,
                        "fullname": fullname,
                        "phone": request.phone,
                        "user_role": "alumni",
                        "graduation_year": request.graduation_year,
                        "department": request.department,
                        "email_verified": 0,
                        "verify_token": verification_code,
                        "is_approved": 0,
                        "active": 0,
                        "name_in_school": request.name_in_school,
                        "alternative_phone": request.alternative_phone,
                        "birth_date": request.birth_date,
                        "house_color": request.house_color,
                        "is_coordinator": 0,
                        "residential_address": request.residential_address,
                        "area": request.area,
                        "city": request.city,
                        "employment_status": request.employment_status,
                        "occupation": request.occupation,
                        "industry_sector": request.industry_sector,
                        "years_of_experience": request.years_of_experience,
                        "is_volunteer": int(request.is_volunteer),
                    }
                )
                self._repository.add_user_to_group(user_id, _MEMBER_GROUP_ID)
                self._repository.insert_alumni_category(
                    user_id,
                    request.chapter_id,
                    registration_year,
                    request.city,
                    now,
                )
                self._repository.insert_registration_profile(
                    user_id,
                    request.chapter_id,
                    registration_year,
                    request.city,
                    _REGISTRATION_PROFILE_VISIBILITY,
                    now,
                )
                if request.voucher_id is not None:
                    self._repository.insert_pending_vouch(user_id, request.voucher_id, now)
                self._repository.replace_verification_code(
                    user_id,
                    normalized_email,
                    int(verification_code),
                )
                if avatar is not None:
                    if storage is None:
                        raise RuntimeError("Avatar storage is not configured")
                    stored_avatar = storage.save(user_id, avatar)
                    self._repository.update_registration_avatar(
                        user_id,
                        stored_avatar.relative_path,
                    )
                    self._repository.insert_registration_avatar_attachment(
                        user_id,
                        stored_avatar.original_filename,
                        stored_avatar.relative_path,
                        now,
                    )
        except IntegrityError as exc:
            if stored_avatar is not None and storage is not None:
                storage.delete(stored_avatar)
            self._session.rollback()
            with self._session.begin():
                duplicate = self._repository.user_by_email(normalized_email)
            if duplicate is not None:
                raise RegistrationError(
                    "registration_email_exists",
                    "Email already exists, kindly use another email",
                    409,
                ) from exc
            raise RegistrationError(
                "registration_unavailable",
                "Registration service is temporarily unavailable",
                503,
            ) from exc
        except SQLAlchemyError as exc:
            if stored_avatar is not None and storage is not None:
                storage.delete(stored_avatar)
            raise RegistrationError(
                "registration_unavailable",
                "Registration service is temporarily unavailable",
                503,
            ) from exc
        except Exception:
            if stored_avatar is not None and storage is not None:
                storage.delete(stored_avatar)
            raise

        if user_id is None:
            raise RuntimeError("Registration did not return a user identity")
        delivered = True
        if self._mailer is None:
            delivered = False
        else:
            try:
                self._mailer.send_verification_code(
                    normalized_email,
                    fullname,
                    verification_code,
                )
            except MailDeliveryError:
                delivered = False
                logger.error("registration_verification_delivery_failed", user_id=user_id)

        message = (
            "Registration successful. Please check your email for your verification code."
            if delivered
            else "Registration saved. Please request a new verification code to continue."
        )
        avatar_url = None
        if stored_avatar is not None:
            base_url = str(self._settings.public_base_url or "")
            avatar_url = (
                urljoin(base_url, stored_avatar.relative_path)
                if base_url
                else stored_avatar.relative_path
            )
        logger.info(
            "member_registered",
            user_id=user_id,
            chapter_id=request.chapter_id,
            voucher_assigned=request.voucher_id is not None,
            avatar_uploaded=stored_avatar is not None,
            verification_mail_delivered=delivered,
        )
        return RegistrationResponse(
            message=message,
            user_id=user_id,
            user_code=user_code,
            email=normalized_email,
            fullname=fullname,
            first_name=request.first_name,
            last_name=request.last_name,
            phone=request.phone,
            chapter_id=request.chapter_id,
            year=registration_year,
            graduation_year=request.graduation_year,
            department=request.department,
            avatar=avatar_url,
            name_in_school=request.name_in_school,
            alternative_phone=request.alternative_phone,
            birth_date=request.birth_date,
            house_color=request.house_color,
            residential_address=request.residential_address,
            area=request.area,
            city=request.city,
            employment_status=request.employment_status,
            occupation=request.occupation,
            industry_sector=request.industry_sector,
            years_of_experience=request.years_of_experience,
            is_volunteer=request.is_volunteer,
            nick_name=request.nick_name,
            state=request.state,
        )

    def login(self, identity: str, password: str) -> LoginResponse:
        """Authenticate a user and return a legacy-compatible projection."""
        normalized_email = identity.strip().lower()
        with self._session.begin():
            user = self._repository.user_by_email(normalized_email)
            encoded_hash = str(user["password"]) if user else None
            if not self._passwords.verify(password, encoded_hash):
                raise InvalidCredentialsError
            if user is None:  # Defensive narrowing; verification cannot succeed without a hash.
                raise InvalidCredentialsError
            encoded_hash = str(user["password"])

            user_id = int(user["id"])
            if not bool(user.get("email_verified")):
                raise AccountStateError(
                    403,
                    403,
                    "Email not verified. Please check your inbox for the verification code.",
                    user_id,
                )
            if not bool(user.get("active")):
                raise AccountStateError(
                    423,
                    423,
                    "Account has been deactivated. Please contact support.",
                    user_id,
                )

            if self._passwords.needs_rehash(encoded_hash):
                self._repository.update_password(user_id, self._passwords.hash(password))

            if not bool(user.get("onboarding_completion")):
                tokens = self._issue_and_store(user)
                return LoginResponse(
                    status=407,
                    message=(
                        "Account profile is incomplete. Please continue your onboarding process."
                    ),
                    access_token=tokens.access_token,
                    refresh_token=tokens.refresh_token,
                    expires_in=tokens.access_expires_in,
                    user_id=user_id,
                    email=str(user["email"]),
                    fullname=user.get("fullname"),
                )
            if not bool(user.get("is_approved")):
                raise AccountStateError(
                    406,
                    406,
                    "Account pending admin approval. You will be notified once approved.",
                    user_id,
                )

            now = datetime.now(UTC)
            self._repository.update_last_login(user_id, int(now.timestamp()))
            tokens = self._issue_and_store(user, now=now)
            profile = self._repository.profile_for_user(user_id)
            roles = self._repository.roles_for_user(user_id)
            zone = self._repository.zone_for_city(user.get("city"))
            return self._login_response(user, profile, roles, zone, tokens)

    def refresh(self, refresh_token: str) -> RefreshResponse:
        """Rotate a refresh token atomically and return a fresh pair."""
        token_hash = self._tokens.hash_refresh_token(refresh_token)
        error_code: str | None = None
        response: RefreshResponse | None = None
        with self._session.begin():
            stored = self._repository.lock_refresh_token(token_hash)
            if stored is None:
                error_code = "refresh_invalid"
            else:
                user_id = int(stored["user_id"])
                expires_at = stored["expires_at"]
                now = datetime.now(UTC).replace(tzinfo=None)
                if bool(stored["revoked"]):
                    self._repository.revoke_all_refresh_tokens(user_id)
                    error_code = "refresh_reused"
                elif expires_at <= now:
                    self._repository.revoke_refresh_token(int(stored["id"]))
                    error_code = "refresh_expired"
                else:
                    user = self._repository.user_by_id(user_id)
                    if user is None or not bool(user.get("active")):
                        self._repository.revoke_all_refresh_tokens(user_id)
                        error_code = "refresh_invalid"
                    else:
                        self._repository.revoke_refresh_token(int(stored["id"]))
                        tokens = self._issue_and_store(user)
                        response = RefreshResponse(
                            access_token=tokens.access_token,
                            refresh_token=tokens.refresh_token,
                            expires_in=tokens.access_expires_in,
                        )
        if error_code is not None:
            raise InvalidRefreshTokenError(error_code)
        if response is None:
            raise InvalidRefreshTokenError()
        return response

    def logout(self, refresh_token: str | None) -> None:
        """Idempotently revoke a supplied refresh token without revealing its validity."""
        if not refresh_token:
            return
        token_hash = self._tokens.hash_refresh_token(refresh_token)
        with self._session.begin():
            stored = self._repository.lock_refresh_token(token_hash)
            if stored is not None and not bool(stored["revoked"]):
                self._repository.revoke_refresh_token(int(stored["id"]))

    def request_password_reset(self, identity: str) -> None:
        """Create and deliver a finite reset token without revealing account existence."""
        normalized_email = identity.strip().lower()
        with self._session.begin():
            user = self._repository.user_by_email(normalized_email)
        if user is None:
            return
        if self._mailer is None or self._settings.frontend_base_url is None:
            raise MailDeliveryError("Password-reset delivery is not configured")

        raw_token = secrets.token_urlsafe(48)
        token_hash = self._tokens.hash_refresh_token(raw_token)
        expires_at = datetime.now(UTC).replace(tzinfo=None) + timedelta(minutes=30)
        user_id = int(user["id"])
        with self._session.begin():
            self._repository.set_password_reset(user_id, token_hash, expires_at)
        reset_url = urljoin(str(self._settings.frontend_base_url), "reset-password")
        reset_url = f"{reset_url}?{urlencode({'code': raw_token})}"
        try:
            self._mailer.send_password_reset(
                str(user["email"]),
                str(user.get("fullname") or "member"),
                reset_url,
            )
        except MailDeliveryError:
            with self._session.begin():
                current = self._repository.user_by_id(user_id)
                if current is not None and current.get("reset_token") == token_hash:
                    self._repository.set_password_reset(user_id, None, None)
            raise

    def reset_password(self, raw_token: str, new_password: str) -> None:
        """Consume a finite reset token, replace the password, and revoke all sessions."""
        token_hash = self._tokens.hash_refresh_token(raw_token)
        invalid = False
        with self._session.begin():
            user = self._repository.lock_user_by_reset_token(token_hash)
            now = datetime.now(UTC).replace(tzinfo=None)
            if user is None or user.get("reset_expires") is None:
                invalid = True
            elif user["reset_expires"] <= now:
                self._repository.set_password_reset(int(user["id"]), None, None)
                invalid = True
            else:
                user_id = int(user["id"])
                self._repository.update_password(user_id, self._passwords.hash(new_password))
                self._repository.set_password_reset(user_id, None, None)
                self._repository.revoke_all_refresh_tokens(user_id)
        if invalid:
            raise InvalidResetTokenError

    def change_password(self, user_id: int, old_password: str, new_password: str) -> None:
        """Change an active user's password after verifying the current password."""
        with self._session.begin():
            user = self._repository.user_by_id(user_id)
            if user is None:
                raise PasswordChangeError("User not found", 404)
            if not bool(user.get("active")):
                raise PasswordChangeError("User account is inactive", 423)
            if not self._passwords.verify(old_password, str(user["password"])):
                raise PasswordChangeError(
                    "Unable to update password. Check old_password and try again."
                )
            if old_password == new_password:
                raise PasswordChangeError("New password must be different from old password")
            self._repository.update_password(user_id, self._passwords.hash(new_password))
            self._repository.revoke_all_refresh_tokens(user_id)

    def resend_email_verification(self, user_id: int) -> str:
        """Replace an unverified user's code and deliver it after committing the row."""
        if self._mailer is None:
            raise MailDeliveryError("Email-verification delivery is not configured")
        code = str(secrets.randbelow(900_000) + 100_000)
        with self._session.begin():
            user = self._repository.lock_user_by_id(user_id)
            if user is None:
                raise AccountVerificationError("user_not_found", "User not found", 404)
            if bool(user.get("email_verified")):
                return "Email is already verified"
            code_id = self._repository.replace_verification_code(
                user_id,
                str(user["email"]),
                int(code),
            )
        try:
            self._mailer.send_verification_code(
                str(user["email"]),
                str(user.get("fullname") or "member"),
                code,
            )
        except MailDeliveryError:
            with self._session.begin():
                self._repository.deactivate_verification_code(
                    code_id,
                    datetime.now(UTC).replace(tzinfo=None),
                )
                self._repository.clear_verification_mirror(user_id, code)
            raise
        return "A new verification code has been sent. It expires in 24 hours."

    def verify_email(self, user_id: int, code: str) -> str:
        """Consume a current verification code and activate the verified email."""
        deferred_error: AccountVerificationError | None = None
        result = "Email verified successfully. Your account is pending admin approval."
        notification_user: dict[str, Any] | None = None
        managers: list[dict[str, Any]] = []
        voucher: dict[str, Any] | None = None
        with self._session.begin():
            user = self._repository.lock_user_by_id(user_id)
            if user is None:
                raise AccountVerificationError("user_not_found", "User not found", 404)
            if bool(user.get("email_verified")):
                return "Email already verified"
            otp = self._repository.lock_verification_code(str(user["email"]), int(code))
            if otp is None:
                raise AccountVerificationError(
                    "verification_invalid",
                    "Invalid verification code",
                    400,
                )
            now = datetime.now(UTC).replace(tzinfo=None)
            created_at = otp.get("created_at")
            if created_at is None or now > created_at + timedelta(hours=24):
                self._repository.deactivate_verification_code(int(otp["id"]), now)
                deferred_error = AccountVerificationError(
                    "verification_expired",
                    "Verification code has expired. Please request a new one.",
                    410,
                )
            else:
                self._repository.deactivate_verification_code(int(otp["id"]), now)
                self._repository.mark_email_verified(user_id)
                notification_user = user
                managers = self._repository.account_manager_recipients()
                voucher = self._repository.pending_voucher_recipient(user_id)
        if deferred_error is not None:
            raise deferred_error
        if notification_user is not None:
            self._send_verification_notifications(notification_user, managers, voucher)
        return result

    def verify_access_code(self, user_id: int, supplied_code: str) -> None:
        """Verify an active member's own legacy access code without disclosing identity."""
        with self._session.begin():
            user = self._repository.user_by_id(user_id)
            stored_code = str(user.get("userAccessCode") or "") if user else ""
            valid = bool(user and user.get("active") and stored_code) and secrets.compare_digest(
                stored_code,
                supplied_code,
            )
            if not valid:
                raise AccessCodeError

    def _send_verification_notifications(
        self,
        member: dict[str, Any],
        managers: list[dict[str, Any]],
        voucher: dict[str, Any] | None,
    ) -> None:
        """Best-effort external notifications after the verification transaction commits."""
        if self._mailer is None:
            return
        member_name = str(member.get("fullname") or "member")
        member_email = str(member["email"])
        for manager in managers:
            try:
                self._mailer.send_new_account_notification(
                    str(manager["email"]),
                    str(manager.get("fullname") or "administrator"),
                    member_name,
                    member_email,
                )
            except MailDeliveryError:
                logger.error("verification_manager_notification_failed")
        if voucher is not None:
            try:
                self._mailer.send_voucher_request(
                    str(voucher["email"]),
                    str(voucher.get("fullname") or "member"),
                    member_name,
                    member_email,
                )
            except MailDeliveryError:
                logger.error("verification_voucher_notification_failed")

    def _generate_user_code(self, graduation_year: int, normalized_email: str) -> str:
        """Preserve the legacy MBR-year-hex shape with deterministic collision retries."""
        for counter in range(1001):
            seed = normalized_email if counter == 0 else f"{normalized_email}|{counter}"
            legacy_hash = 0
            for character in seed.encode():
                legacy_hash = ((legacy_hash << 5) - legacy_hash + character) & 0xFFFFFFFF
            suffix = f"{legacy_hash:x}".rjust(6, "0")[:6]
            candidate = f"MBR-{graduation_year}-{suffix}"
            if not self._repository.user_code_exists(candidate):
                return candidate
        raise RegistrationError(
            "registration_code_unavailable",
            "Registration service is temporarily unavailable",
            503,
        )

    def _issue_and_store(
        self,
        user: dict[str, Any],
        *,
        now: datetime | None = None,
    ) -> IssuedTokens:
        tokens = self._tokens.issue(user, now)
        self._repository.store_refresh_token(
            int(user["id"]),
            self._tokens.hash_refresh_token(tokens.refresh_token),
            tokens.refresh_expires_at,
        )
        return tokens

    def _login_response(
        self,
        user: dict[str, Any],
        profile: dict[str, Any] | None,
        roles: list[str],
        zone: dict[str, Any] | None,
        tokens: IssuedTokens,
    ) -> LoginResponse:
        avatar = self._absolute_avatar(user.get("avatar"))
        profile_data = profile or {}
        return LoginResponse(
            status=200,
            message="Login successful",
            access_token=tokens.access_token,
            refresh_token=tokens.refresh_token,
            expires_in=tokens.access_expires_in,
            user_id=int(user["id"]),
            user_code=user.get("user_code"),
            email=str(user["email"]),
            fullname=user.get("fullname"),
            first_name=user.get("first_name"),
            last_name=user.get("last_name"),
            phone=user.get("phone"),
            user_role=user.get("user_role"),
            avatar=avatar,
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
            zone_id=zone.get("zone_id") if zone else None,
            zone_name=zone.get("zone_name") if zone else None,
            city_id=zone.get("city_id") if zone else None,
            profile=ProfileResponse(
                linkedin=profile_data.get("linkedin"),
                twitter=profile_data.get("twitter"),
                tiktok=profile_data.get("tiktok"),
                facebook=profile_data.get("facebook"),
                instagram=profile_data.get("instagram"),
                field_visibility=profile_data.get("field_visibility"),
                website=profile_data.get("website"),
                current_company=profile_data.get("current_company"),
                current_position=profile_data.get("current_position"),
                city=profile_data.get("city"),
                country=profile_data.get("country"),
                skills=profile_data.get("skills"),
                achievements=profile_data.get("achievements"),
                year=profile_data.get("year"),
                is_visible=bool(profile_data.get("is_visible", True)),
                system_role=";".join(roles),
            ),
        )

    def _absolute_avatar(self, avatar: Any) -> str | None:
        if not avatar:
            return None
        base_url = str(self._settings.public_base_url) if self._settings.public_base_url else None
        return urljoin(base_url, str(avatar)) if base_url else str(avatar)
