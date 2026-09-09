"""End-to-end authentication tests against the sanitized disposable schema."""

from __future__ import annotations

import os
import time
import uuid
from collections.abc import Iterator
from dataclasses import dataclass, field
from datetime import UTC, datetime, timedelta
from typing import Any, cast
from urllib.parse import parse_qs, urlparse

import bcrypt
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import Engine, Table, create_engine, func, select

from app.core.config import Settings
from app.core.errors import MailDeliveryError
from app.core.security import TokenService
from app.integrations.mail import Mailer
from app.main import create_app
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
PROFILES_TABLE = cast(Table, UserProfiles.__table__)
ROLES_TABLE = cast(Table, Roles.__table__)
CITIES_TABLE = cast(Table, Cities.__table__)
ZONES_TABLE = cast(Table, Zones.__table__)
REFRESH_TABLE = cast(Table, JwtRefreshTokens.__table__)
OTP_TABLE = cast(Table, RegisterUserOtp.__table__)
VOUCHES_TABLE = cast(Table, Vouches.__table__)
PASSPHRASE = "Correct horse battery staple!"
NEW_PASSPHRASE = "A different strong passphrase!"


@dataclass(slots=True)
class RecordingMailer(Mailer):
    """Capture reset links in memory without contacting an external provider."""

    deliveries: list[tuple[str, str, str]] = field(default_factory=list)
    verification_deliveries: list[tuple[str, str, str]] = field(default_factory=list)
    manager_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    voucher_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    fail: bool = False

    def send_password_reset(self, recipient: str, display_name: str, reset_url: str) -> None:
        """Record a delivery or simulate a provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.deliveries.append((recipient, display_name, reset_url))

    def send_verification_code(self, recipient: str, display_name: str, code: str) -> None:
        """Record a verification code or simulate a provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.verification_deliveries.append((recipient, display_name, code))

    def send_new_account_notification(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Record an account-manager notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.manager_deliveries.append((recipient, recipient_name, member_name, member_email))

    def send_voucher_request(
        self,
        recipient: str,
        recipient_name: str,
        member_name: str,
        member_email: str,
    ) -> None:
        """Record a voucher notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.voucher_deliveries.append((recipient, recipient_name, member_name, member_email))


@dataclass(slots=True)
class AuthHarness:
    """Create and clean only uniquely tagged synthetic authentication rows."""

    engine: Engine
    client: TestClient
    settings: Settings
    mailer: RecordingMailer
    marker: str
    user_ids: list[int] = field(default_factory=list)
    city_names: list[str] = field(default_factory=list)
    zone_names: list[str] = field(default_factory=list)
    emails: list[str] = field(default_factory=list)

    def create_user(
        self,
        *,
        password: str = PASSPHRASE,
        active: int = 1,
        email_verified: int = 1,
        is_approved: int = 1,
        onboarding_completion: int = 1,
        city: str = "",
    ) -> tuple[int, str, str]:
        """Insert one fully synthetic user with a legacy `$2y$` password hash."""
        unique = uuid.uuid4().hex[:10]
        email = f"codex-auth-{self.marker}-{unique}@example.com"
        bcrypt_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt(rounds=8)).decode("ascii")
        legacy_hash = bcrypt_hash.replace("$2b$", "$2y$", 1)
        with self.engine.begin() as connection:
            result = connection.execute(
                USERS_TABLE.insert().values(
                    chapter_id=1,
                    ip_address="127.0.0.1",
                    username=email,
                    email=email,
                    password=legacy_hash,
                    has_password=1,
                    onboarding_completion=onboarding_completion,
                    nick_name="Synthetic",
                    state="Lagos",
                    country="Nigeria",
                    created_on=int(time.time()),
                    userAccessCode=f"SYN-{unique}",
                    profile_status="active",
                    voucher="",
                    resetKey="",
                    first_name="Synthetic",
                    last_name="Member",
                    fullname="Synthetic Member",
                    phone="+2348000000000",
                    avatar="uploads/profiles/synthetic.png",
                    city=city,
                    active=active,
                    user_role="alumni",
                    is_approved=is_approved,
                    email_verified=email_verified,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            user_id = int(inserted_key[0])
        self.user_ids.append(user_id)
        self.emails.append(email)
        return user_id, email, legacy_hash

    def add_profile_role_and_zone(self, user_id: int, city: str) -> None:
        """Populate the three optional projections returned by legacy login."""
        suffix = uuid.uuid4().hex[:10]
        zone_name = f"Synthetic Zone {suffix}"
        role_name = f"synthetic-role-{suffix}"
        now = datetime.now(UTC).replace(tzinfo=None)
        with self.engine.begin() as connection:
            zone_result = connection.execute(
                ZONES_TABLE.insert().values(zone=zone_name, chapter_id=1)
            )
            inserted_key = zone_result.inserted_primary_key
            assert inserted_key is not None
            zone_id = int(inserted_key[0])
            connection.execute(
                CITIES_TABLE.insert().values(city=city, zone_id=zone_id, chapter_id=1)
            )
            connection.execute(
                PROFILES_TABLE.insert().values(
                    user_id=user_id,
                    instagram="synthetic_handle",
                    tiktok="synthetic_handle",
                    updated_at=now,
                    linkedin="https://example.test/member",
                    city=city,
                    country="Nigeria",
                )
            )
            connection.execute(ROLES_TABLE.insert().values(user_id=user_id, role_name=role_name))
        self.city_names.append(city)
        self.zone_names.append(zone_name)

    def refresh_rows(self, user_id: int) -> list[dict[str, Any]]:
        """Read refresh-token metadata without exposing the plaintext token."""
        with self.engine.connect() as connection:
            rows = connection.execute(
                select(REFRESH_TABLE)
                .where(JwtRefreshTokens.user_id == user_id)
                .order_by(JwtRefreshTokens.id)
            ).mappings()
            return [dict(row) for row in rows]

    def cleanup(self) -> None:
        """Delete only rows created by this fixture, respecting foreign keys."""
        if not self.user_ids and not self.city_names and not self.zone_names:
            return
        with self.engine.begin() as connection:
            if self.user_ids:
                connection.execute(
                    VOUCHES_TABLE.delete().where(
                        Vouches.register_id.in_(self.user_ids)
                        | Vouches.voucher_id.in_(self.user_ids)
                    )
                )
                connection.execute(
                    REFRESH_TABLE.delete().where(JwtRefreshTokens.user_id.in_(self.user_ids))
                )
                connection.execute(
                    PROFILES_TABLE.delete().where(UserProfiles.user_id.in_(self.user_ids))
                )
                connection.execute(ROLES_TABLE.delete().where(Roles.user_id.in_(self.user_ids)))
                connection.execute(USERS_TABLE.delete().where(Users.id.in_(self.user_ids)))
            if self.emails:
                connection.execute(OTP_TABLE.delete().where(RegisterUserOtp.email.in_(self.emails)))
            if self.city_names:
                connection.execute(CITIES_TABLE.delete().where(Cities.city.in_(self.city_names)))
            if self.zone_names:
                connection.execute(ZONES_TABLE.delete().where(Zones.zone.in_(self.zone_names)))


@pytest.fixture
def auth_harness(rsa_pem_pair: tuple[str, str]) -> Iterator[AuthHarness]:
    """Provide an API client and direct SQL inspection over an isolated test database."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")
    private_pem, public_pem = rsa_pem_pair
    settings = Settings(
        environment="test",
        database_url=database_url,
        jwt_signing_key=private_pem,
        jwt_verification_key=public_pem,
        public_base_url="https://alumni.example.test/",
        frontend_base_url="https://frontend.example.test/",
    )
    engine = create_engine(database_url, pool_pre_ping=True)
    mailer = RecordingMailer()
    app = create_app(settings, mailer=mailer)
    with TestClient(app) as client:
        harness = AuthHarness(
            engine=engine,
            client=client,
            settings=settings,
            mailer=mailer,
            marker=uuid.uuid4().hex[:8],
        )
        try:
            yield harness
        finally:
            harness.cleanup()
    engine.dispose()


def _access_token_for(
    auth_harness: AuthHarness,
    user_id: int,
    email: str,
    *,
    user_role: str = "alumni",
) -> str:
    """Issue a test-only access token without creating a refresh session."""
    return (
        TokenService(auth_harness.settings)
        .issue(
            {
                "id": user_id,
                "email": email,
                "user_role": user_role,
                "fullname": "Synthetic Member",
            }
        )
        .access_token
    )


@pytest.mark.integration
def test_login_upgrades_hash_and_returns_legacy_projection(auth_harness: AuthHarness) -> None:
    """A valid legacy account receives the expected profile and a modern hash."""
    city = f"Synthetic City {uuid.uuid4().hex[:8]}"
    user_id, email, legacy_hash = auth_harness.create_user(city=city)
    auth_harness.add_profile_role_and_zone(user_id, city)

    response = auth_harness.client.post(
        "/api/login",
        json={"identity": email.upper(), "password": PASSPHRASE},
    )
    body = response.json()
    assert response.status_code == 200
    assert body["status"] == 200
    assert body["user_id"] == user_id
    assert body["avatar"] == "https://alumni.example.test/uploads/profiles/synthetic.png"
    assert body["zone_name"].startswith("Synthetic Zone")
    assert body["profile"]["linkedin"] == "https://example.test/member"
    assert body["profile"]["system_role"].startswith("synthetic-role-")
    assert len(auth_harness.refresh_rows(user_id)) == 1

    with auth_harness.engine.connect() as connection:
        stored_hash = connection.scalar(select(Users.password).where(Users.id == user_id))
        last_login = connection.scalar(select(Users.last_login).where(Users.id == user_id))
    assert stored_hash != legacy_hash
    assert str(stored_hash).startswith("$argon2id$")
    assert isinstance(last_login, int)
    assert TokenService(auth_harness.settings).decode_access(body["access_token"])["sub"] == str(
        user_id
    )


@pytest.mark.integration
def test_access_code_verification_is_authenticated_and_self_only(
    auth_harness: AuthHarness,
) -> None:
    """Caller-supplied user IDs cannot redirect the comparison to another member."""
    user_id, email, _ = auth_harness.create_user()
    other_user_id, _other_email, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, user_id, email)
    headers = {"Authorization": f"Bearer {token}"}
    with auth_harness.engine.connect() as connection:
        own_code = str(connection.scalar(select(Users.userAccessCode).where(Users.id == user_id)))
        other_code = str(
            connection.scalar(select(Users.userAccessCode).where(Users.id == other_user_id))
        )

    valid = auth_harness.client.post(
        "/api/verify_user_access_code",
        headers=headers,
        json={"user_id": other_user_id, "access_code": own_code},
    )
    redirected = auth_harness.client.post(
        "/api/verify_user_access_code",
        headers=headers,
        json={"user_id": other_user_id, "access_code": other_code},
    )
    assert valid.status_code == 200
    assert valid.json() == {"status": 200, "message": "Access code verified successfully"}
    assert redirected.status_code == 400
    assert redirected.json()["code"] == "access_code_invalid"
    assert "Synthetic Member" not in valid.text


@pytest.mark.integration
def test_access_code_verification_rechecks_current_account_state(
    auth_harness: AuthHarness,
) -> None:
    """A previously issued JWT cannot verify a code after account deactivation."""
    user_id, email, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, user_id, email)
    with auth_harness.engine.begin() as connection:
        code = str(connection.scalar(select(Users.userAccessCode).where(Users.id == user_id)))
        connection.execute(USERS_TABLE.update().where(Users.id == user_id).values(active=0))
    response = auth_harness.client.post(
        "/api/verify_user_access_code",
        headers={"Authorization": f"Bearer {token}"},
        json={"access_code": code},
    )
    assert response.status_code == 400
    assert response.json() == {
        "status": 400,
        "message": "Invalid access code",
        "code": "access_code_invalid",
    }


@pytest.mark.integration
def test_json_and_form_failures_do_not_enumerate_accounts(auth_harness: AuthHarness) -> None:
    """Unknown users and bad passwords share an identical 401 response."""
    _, email, legacy_hash = auth_harness.create_user()
    wrong = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": "wrong password"}
    )
    unknown = auth_harness.client.post(
        "/api/login",
        data={"identity": f"unknown-{uuid.uuid4().hex}@example.com", "password": "wrong password"},
    )
    assert wrong.status_code == unknown.status_code == 401
    assert (
        wrong.json()
        == unknown.json()
        == {
            "status": 401,
            "message": "Invalid email or password",
            "code": "invalid_credentials",
        }
    )
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.password).where(Users.email == email)) == legacy_hash


@pytest.mark.integration
@pytest.mark.parametrize(
    ("fields", "http_status", "body_status"),
    [
        ({"email_verified": 0}, 403, 403),
        ({"active": 0}, 423, 423),
        ({"is_approved": 0}, 406, 406),
    ],
)
def test_login_enforces_account_state_after_password(
    auth_harness: AuthHarness,
    fields: dict[str, int],
    http_status: int,
    body_status: int,
) -> None:
    """Verified credentials still respect email, active, and approval gates."""
    user_id, email, _ = auth_harness.create_user(
        active=fields.get("active", 1),
        email_verified=fields.get("email_verified", 1),
        is_approved=fields.get("is_approved", 1),
    )
    response = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": PASSPHRASE}
    )
    assert response.status_code == http_status
    assert response.json()["status"] == body_status
    assert response.json()["user_id"] == user_id
    assert auth_harness.refresh_rows(user_id) == []


@pytest.mark.integration
def test_incomplete_onboarding_retains_legacy_406_407_signal(auth_harness: AuthHarness) -> None:
    """Incomplete profiles receive tokens and the established split status signal."""
    user_id, email, _ = auth_harness.create_user(onboarding_completion=0)
    response = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": PASSPHRASE}
    )
    assert response.status_code == 406
    assert response.json()["status"] == 407
    assert response.json()["user_id"] == user_id
    assert response.json()["token_type"] == "Bearer"
    assert len(auth_harness.refresh_rows(user_id)) == 1


@pytest.mark.integration
def test_refresh_rotation_and_replay_revoke_all_sessions(auth_harness: AuthHarness) -> None:
    """Refresh credentials are one-time and replay containment revokes the replacement."""
    user_id, email, _ = auth_harness.create_user()
    login = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": PASSPHRASE}
    ).json()
    old_refresh = login["refresh_token"]
    rotated = auth_harness.client.post("/api/refresh_token", json={"refresh_token": old_refresh})
    assert rotated.status_code == 200
    assert rotated.json()["refresh_token"] != old_refresh
    assert [row["revoked"] for row in auth_harness.refresh_rows(user_id)] == [1, 0]

    replay = auth_harness.client.post("/api/refresh_token", json={"refresh_token": old_refresh})
    assert replay.status_code == 401
    assert replay.json()["code"] == "refresh_reused"
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(user_id))


@pytest.mark.integration
def test_logout_is_idempotent_and_refresh_token_cap_is_enforced(auth_harness: AuthHarness) -> None:
    """Only five login sessions stay active, and logout revokes the supplied session."""
    user_id, email, _ = auth_harness.create_user()
    refresh_tokens: list[str] = []
    for _ in range(6):
        body = auth_harness.client.post(
            "/api/login", json={"identity": email, "password": PASSPHRASE}
        ).json()
        refresh_tokens.append(body["refresh_token"])
    rows = auth_harness.refresh_rows(user_id)
    assert len(rows) == 6
    assert sum(row["revoked"] == 0 for row in rows) == 5

    logout = auth_harness.client.post("/api/logout", json={"refresh_token": refresh_tokens[-1]})
    repeated = auth_harness.client.post("/api/logout", json={"refresh_token": refresh_tokens[-1]})
    assert logout.status_code == repeated.status_code == 200
    assert sum(row["revoked"] == 0 for row in auth_harness.refresh_rows(user_id)) == 4


@pytest.mark.integration
def test_unknown_expired_and_inactive_refresh_tokens_fail_closed(auth_harness: AuthHarness) -> None:
    """Every unusable refresh-token state produces a bounded 401 response."""
    unknown = auth_harness.client.post("/api/refresh_token", json={"refresh_token": "x" * 64})
    assert unknown.status_code == 401
    assert unknown.json()["code"] == "refresh_invalid"

    user_id, _, _ = auth_harness.create_user(active=0)
    raw_token = "expired-synthetic-refresh-token-" + uuid.uuid4().hex
    token_hash = TokenService.hash_refresh_token(raw_token)
    with auth_harness.engine.begin() as connection:
        result = connection.execute(
            REFRESH_TABLE.insert().values(
                user_id=user_id,
                token=token_hash,
                revoked=0,
                expires_at=datetime.now(UTC).replace(tzinfo=None) - timedelta(minutes=1),
            )
        )
        inserted_key = result.inserted_primary_key
        assert inserted_key is not None
        token_id = int(inserted_key[0])
    expired = auth_harness.client.post("/api/refresh_token", json={"refresh_token": raw_token})
    assert expired.status_code == 401
    assert expired.json()["code"] == "refresh_expired"
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(JwtRefreshTokens.revoked).where(JwtRefreshTokens.id == token_id)
            )
            == 1
        )

    active_raw = "inactive-synthetic-refresh-token-" + uuid.uuid4().hex
    with auth_harness.engine.begin() as connection:
        connection.execute(
            REFRESH_TABLE.insert().values(
                user_id=user_id,
                token=TokenService.hash_refresh_token(active_raw),
                revoked=0,
                expires_at=datetime.now(UTC).replace(tzinfo=None) + timedelta(minutes=5),
            )
        )
    inactive = auth_harness.client.post("/api/refresh_token", json={"refresh_token": active_raw})
    assert inactive.status_code == 401
    assert inactive.json()["code"] == "refresh_invalid"
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(user_id))


@pytest.mark.integration
def test_missing_signing_key_rolls_back_login_changes(
    auth_harness: AuthHarness,
    rsa_pem_pair: tuple[str, str],
) -> None:
    """Token misconfiguration returns 503 without persisting a hash upgrade or session."""
    user_id, email, legacy_hash = auth_harness.create_user()
    settings = Settings(
        environment="test",
        database_url=auth_harness.settings.database_url_value(),
        jwt_verification_key=rsa_pem_pair[1],
    )
    with TestClient(create_app(settings)) as client:
        response = client.post("/api/login", json={"identity": email, "password": PASSPHRASE})
    assert response.status_code == 503
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.password).where(Users.id == user_id)) == legacy_hash
        assert (
            connection.scalar(
                select(func.count())
                .select_from(REFRESH_TABLE)
                .where(JwtRefreshTokens.user_id == user_id)
            )
            == 0
        )


@pytest.mark.integration
def test_password_recovery_is_finite_one_time_and_revokes_sessions(
    auth_harness: AuthHarness,
) -> None:
    """Recovery delivers only a raw link, stores its hash, and invalidates old sessions."""
    user_id, email, _ = auth_harness.create_user()
    login = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": PASSPHRASE}
    ).json()
    old_refresh = login["refresh_token"]

    requested = auth_harness.client.post("/api/forgot_password", json={"identity": email})
    assert requested.status_code == 200
    assert len(auth_harness.mailer.deliveries) == 1
    recipient, display_name, reset_url = auth_harness.mailer.deliveries[0]
    assert recipient == email
    assert display_name == "Synthetic Member"
    raw_token = parse_qs(urlparse(reset_url).query)["code"][0]
    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(Users.reset_token, Users.reset_expires).where(Users.id == user_id)
        ).one()
    assert stored.reset_token == TokenService.hash_refresh_token(raw_token)
    assert raw_token not in str(stored.reset_token)
    assert stored.reset_expires > datetime.now(UTC).replace(tzinfo=None)

    reset = auth_harness.client.post(
        "/api/reset_password",
        json={
            "code": raw_token,
            "new_password": NEW_PASSPHRASE,
            "new_password_confirm": NEW_PASSPHRASE,
        },
    )
    assert reset.status_code == 200
    assert reset.json()["message"] == "Password has been reset successfully"
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(user_id))
    replay = auth_harness.client.post(
        "/api/reset_password",
        json={
            "code": raw_token,
            "new_password": NEW_PASSPHRASE,
            "new_password_confirm": NEW_PASSPHRASE,
        },
    )
    assert replay.status_code == 400
    assert replay.json()["code"] == "reset_invalid"
    assert (
        auth_harness.client.post(
            "/api/refresh_token", json={"refresh_token": old_refresh}
        ).status_code
        == 401
    )
    assert (
        auth_harness.client.post(
            "/api/login", json={"identity": email, "password": NEW_PASSPHRASE}
        ).status_code
        == 200
    )


@pytest.mark.integration
def test_recovery_response_hides_account_and_delivery_state(auth_harness: AuthHarness) -> None:
    """Unknown accounts and mail failures expose the same successful public response."""
    user_id, email, _ = auth_harness.create_user()
    auth_harness.mailer.fail = True
    known = auth_harness.client.post("/api/forgot_password", json={"identity": email})
    unknown = auth_harness.client.post(
        "/api/forgot_password",
        json={"identity": f"unknown-{uuid.uuid4().hex}@example.com"},
    )
    assert known.status_code == unknown.status_code == 200
    assert known.json() == unknown.json()
    with auth_harness.engine.connect() as connection:
        reset_fields = connection.execute(
            select(Users.reset_token, Users.reset_expires).where(Users.id == user_id)
        ).one()
    assert reset_fields.reset_token is None
    assert reset_fields.reset_expires is None


@pytest.mark.integration
def test_expired_password_reset_token_is_cleared(auth_harness: AuthHarness) -> None:
    """Expired reset credentials fail closed and cannot be retried."""
    user_id, _, _ = auth_harness.create_user()
    raw_token = "expired-password-reset-token-" + uuid.uuid4().hex
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(
                reset_token=TokenService.hash_refresh_token(raw_token),
                reset_expires=datetime.now(UTC).replace(tzinfo=None) - timedelta(seconds=1),
            )
        )
    response = auth_harness.client.post(
        "/api/reset_password",
        json={
            "code": raw_token,
            "new_password": NEW_PASSPHRASE,
            "new_password_confirm": NEW_PASSPHRASE,
        },
    )
    assert response.status_code == 400
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.reset_token).where(Users.id == user_id)) is None


@pytest.mark.integration
def test_authenticated_password_change_is_self_only_and_revokes_refresh(
    auth_harness: AuthHarness,
) -> None:
    """Bearer identity controls the target and a successful change revokes sessions."""
    user_id, email, _ = auth_harness.create_user()
    login = auth_harness.client.post(
        "/api/login", json={"identity": email, "password": PASSPHRASE}
    ).json()
    headers = {"Authorization": f"Bearer {login['access_token']}"}
    wrong = auth_harness.client.post(
        "/api/change_user_password",
        headers=headers,
        json={
            "user_id": user_id + 999,
            "old_password": "wrong password",
            "new_password": NEW_PASSPHRASE,
            "confirm_password": NEW_PASSPHRASE,
        },
    )
    assert wrong.status_code == 400
    same = auth_harness.client.post(
        "/api/change_user_password",
        headers=headers,
        json={
            "old_password": PASSPHRASE,
            "new_password": PASSPHRASE,
            "confirm_password": PASSPHRASE,
        },
    )
    assert same.status_code == 400
    assert same.json()["message"] == "New password must be different from old password"

    changed = auth_harness.client.post(
        "/api/change_user_password",
        headers=headers,
        json={
            "user_id": user_id + 999,
            "old_password": PASSPHRASE,
            "new_password": NEW_PASSPHRASE,
            "confirm_password": NEW_PASSPHRASE,
        },
    )
    assert changed.status_code == 200
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(user_id))
    assert (
        auth_harness.client.post(
            "/api/login", json={"identity": email, "password": PASSPHRASE}
        ).status_code
        == 401
    )
    assert (
        auth_harness.client.post(
            "/api/login", json={"identity": email, "password": NEW_PASSPHRASE}
        ).status_code
        == 200
    )


@pytest.mark.integration
def test_password_change_rejects_missing_inactive_and_deleted_principals(
    auth_harness: AuthHarness,
) -> None:
    """Authorization and current account state are rechecked for password changes."""
    no_auth = auth_harness.client.post("/api/change_user_password", json={})
    assert no_auth.status_code == 401
    assert no_auth.headers["www-authenticate"] == "Bearer"

    inactive_id, inactive_email, _ = auth_harness.create_user(active=0)
    inactive_token = _access_token_for(auth_harness, inactive_id, inactive_email)
    body = {
        "old_password": PASSPHRASE,
        "new_password": NEW_PASSPHRASE,
        "confirm_password": NEW_PASSPHRASE,
    }
    inactive = auth_harness.client.post(
        "/api/change_user_password",
        headers={"Authorization": f"Bearer {inactive_token}"},
        json=body,
    )
    assert inactive.status_code == 423

    deleted_token = _access_token_for(auth_harness, 2_000_000_000, "deleted@example.com")
    deleted = auth_harness.client.post(
        "/api/change_user_password",
        headers={"Authorization": f"Bearer {deleted_token}"},
        json=body,
    )
    assert deleted.status_code == 404


@pytest.mark.integration
def test_email_verification_code_is_replaced_consumed_and_activates_user(
    auth_harness: AuthHarness,
) -> None:
    """The current six-digit code is one-time and applies the legacy activation transition."""
    user_id, email, _ = auth_harness.create_user(active=0, email_verified=0)
    sent = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert sent.status_code == 200
    assert len(auth_harness.mailer.verification_deliveries) == 1
    recipient, display_name, code = auth_harness.mailer.verification_deliveries[0]
    assert recipient == email
    assert display_name == "Synthetic Member"
    assert len(code) == 6
    assert code.isdigit()
    with auth_harness.engine.connect() as connection:
        otp = connection.execute(
            select(RegisterUserOtp.otp, RegisterUserOtp.is_active).where(
                RegisterUserOtp.email == email
            )
        ).one()
        assert str(otp.otp) == code
        assert otp.is_active == 1
        assert connection.scalar(select(Users.verify_token).where(Users.id == user_id)) == code

    invalid = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": "999999"}
    )
    assert invalid.status_code == 400
    verified = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": code}
    )
    assert verified.status_code == 200
    with auth_harness.engine.connect() as connection:
        user_state = connection.execute(
            select(Users.email_verified, Users.active, Users.verify_token).where(
                Users.id == user_id
            )
        ).one()
        active_code = connection.scalar(
            select(RegisterUserOtp.is_active).where(
                RegisterUserOtp.email == email,
                RegisterUserOtp.otp == int(code),
            )
        )
    assert (user_state.email_verified, user_state.active, user_state.verify_token) == (1, 1, None)
    assert active_code == 0
    already = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": code}
    )
    assert already.status_code == 200
    assert already.json()["message"] == "Email already verified"


@pytest.mark.integration
def test_email_verification_notifies_account_manager_and_assigned_voucher(
    auth_harness: AuthHarness,
) -> None:
    """A newly verified account triggers only reviewed manager and owned-voucher mail."""
    user_id, email, _ = auth_harness.create_user(active=0, email_verified=0)
    manager_id, manager_email, _ = auth_harness.create_user()
    voucher_id, voucher_email, _ = auth_harness.create_user()
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == manager_id).values(user_role="manager")
        )
        connection.execute(
            VOUCHES_TABLE.insert().values(
                register_id=user_id,
                voucher_id=voucher_id,
                status="pending",
                created_at=now,
                updated_at=now,
            )
        )

    resent = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    code = auth_harness.mailer.verification_deliveries[-1][2]
    verified = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": code}
    )

    assert resent.status_code == verified.status_code == 200
    assert auth_harness.mailer.manager_deliveries == [
        (manager_email, "Synthetic Member", "Synthetic Member", email)
    ]
    assert auth_harness.mailer.voucher_deliveries == [
        (voucher_email, "Synthetic Member", "Synthetic Member", email)
    ]


@pytest.mark.integration
def test_notification_failure_does_not_rollback_consumed_verification(
    auth_harness: AuthHarness,
) -> None:
    """External notification outages cannot make a successfully consumed code reusable."""
    user_id, _email, _ = auth_harness.create_user(active=0, email_verified=0)
    manager_id, _manager_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == manager_id).values(user_role="admin")
        )
    resent = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert resent.status_code == 200
    code = auth_harness.mailer.verification_deliveries[-1][2]
    auth_harness.mailer.fail = True

    verified = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": code}
    )
    assert verified.status_code == 200
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.email_verified).where(Users.id == user_id)) == 1
        assert (
            connection.scalar(
                select(RegisterUserOtp.is_active).where(
                    RegisterUserOtp.email == _email,
                    RegisterUserOtp.otp == int(code),
                )
            )
            == 0
        )


@pytest.mark.integration
def test_verification_resend_replaces_old_code_and_cleans_delivery_failure(
    auth_harness: AuthHarness,
) -> None:
    """Only the latest delivered code remains active and failed delivery leaves none active."""
    user_id, email, _ = auth_harness.create_user(active=0, email_verified=0)
    first = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    second = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert first.status_code == second.status_code == 200
    first_code = auth_harness.mailer.verification_deliveries[0][2]
    second_code = auth_harness.mailer.verification_deliveries[1][2]
    assert first_code != second_code
    with auth_harness.engine.connect() as connection:
        rows = connection.execute(
            select(RegisterUserOtp.otp, RegisterUserOtp.is_active)
            .where(RegisterUserOtp.email == email)
            .order_by(RegisterUserOtp.id)
        ).all()
    assert [row.is_active for row in rows] == [0, 1]

    auth_harness.mailer.fail = True
    failed = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert failed.status_code == 503
    with auth_harness.engine.connect() as connection:
        active_count = connection.scalar(
            select(func.count())
            .select_from(OTP_TABLE)
            .where(RegisterUserOtp.email == email, RegisterUserOtp.is_active == 1)
        )
        mirror = connection.scalar(select(Users.verify_token).where(Users.id == user_id))
    assert active_count == 0
    assert mirror is None


@pytest.mark.integration
def test_expired_verification_and_missing_users_fail_closed(auth_harness: AuthHarness) -> None:
    """Expired codes are consumed and absent users return bounded 404 responses."""
    user_id, email, _ = auth_harness.create_user(active=0, email_verified=0)
    code = 123456
    with auth_harness.engine.begin() as connection:
        connection.execute(
            OTP_TABLE.insert().values(
                email=email,
                otp=code,
                is_active=1,
                created_at=datetime.now(UTC).replace(tzinfo=None) - timedelta(hours=25),
            )
        )
    expired = auth_harness.client.post(
        "/api/verify_email", json={"user_id": user_id, "verify_code": str(code)}
    )
    assert expired.status_code == 410
    assert expired.json()["code"] == "verification_expired"
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(RegisterUserOtp.is_active).where(
                    RegisterUserOtp.email == email,
                    RegisterUserOtp.otp == code,
                )
            )
            == 0
        )

    missing_resend = auth_harness.client.post(
        "/api/resend_verify_email", json={"user_id": 2_000_000_000}
    )
    missing_verify = auth_harness.client.post(
        "/api/verify_email",
        json={"user_id": 2_000_000_000, "verify_code": str(code)},
    )
    assert missing_resend.status_code == missing_verify.status_code == 404


@pytest.mark.integration
def test_resend_for_verified_email_is_idempotent(auth_harness: AuthHarness) -> None:
    """Verified users do not receive or store another verification code."""
    user_id, _, _ = auth_harness.create_user(email_verified=1)
    response = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert response.status_code == 200
    assert response.json()["message"] == "Email is already verified"
    assert auth_harness.mailer.verification_deliveries == []


@pytest.mark.integration
def test_member_profile_self_read_returns_only_allowlisted_projection(
    auth_harness: AuthHarness,
) -> None:
    """A member can read their complete compatibility profile without credential fields."""
    city = f"Synthetic Profile City {uuid.uuid4().hex[:8]}"
    user_id, email, _ = auth_harness.create_user(city=city)
    auth_harness.add_profile_role_and_zone(user_id, city)
    token = _access_token_for(auth_harness, user_id, email)

    response = auth_harness.client.post(
        "/api/get_user_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={},
    )
    body = response.json()

    assert response.status_code == 200
    assert body["status"] == 200
    assert body["user_id"] == user_id
    assert body["email"] == email
    assert body["avatar"] == "https://alumni.example.test/uploads/profiles/synthetic.png"
    assert body["zone_name"].startswith("Synthetic Zone ")
    assert body["profile"]["tiktok"] == "synthetic_handle"
    assert body["profile"]["system_role"].startswith("synthetic-role-")
    assert {
        "password",
        "reset_token",
        "verify_token",
        "userAccessCode",
        "device_token",
    }.isdisjoint(body)


@pytest.mark.integration
def test_member_profile_cross_user_access_uses_current_database_role(
    auth_harness: AuthHarness,
) -> None:
    """JWT role claims cannot elevate access, while a current manager role can grant it."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    forged_token = _access_token_for(
        auth_harness,
        actor_id,
        actor_email,
        user_role="superadmin",
    )

    denied = auth_harness.client.post(
        "/api/get_user_profile",
        headers={"Authorization": f"Bearer {forged_token}"},
        json={"user_id": target_id},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "profile_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
    stale_member_token = _access_token_for(auth_harness, actor_id, actor_email)
    allowed = auth_harness.client.post(
        "/api/get_user_profile",
        headers={"Authorization": f"Bearer {stale_member_token}"},
        json={"user_id": target_id},
    )
    assert allowed.status_code == 200
    assert allowed.json()["email"] == target_email


@pytest.mark.integration
def test_member_profile_rechecks_actor_state_and_missing_target(
    auth_harness: AuthHarness,
) -> None:
    """Deleted or inactive principals fail closed and managers receive bounded 404s."""
    actor_id, actor_email, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, actor_id, actor_email)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )

    missing = auth_harness.client.post(
        "/api/get_user_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": 2_000_000_000},
    )
    assert missing.status_code == 404
    assert missing.json()["code"] == "profile_not_found"

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive = auth_harness.client.post(
        "/api/get_user_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={},
    )
    assert inactive.status_code == 401
    assert inactive.json()["code"] == "profile_actor_unavailable"
