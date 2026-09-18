"""End-to-end authentication tests against the sanitized disposable schema."""

from __future__ import annotations

import json
import os
import time
import uuid
from collections.abc import Iterator
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from datetime import UTC, date, datetime, timedelta
from io import BytesIO
from pathlib import Path
from typing import Any, cast
from urllib.parse import parse_qs, urlparse

import bcrypt
import pytest
from fastapi.testclient import TestClient
from openpyxl import Workbook  # type: ignore[import-untyped]
from PIL import Image
from sqlalchemy import Engine, Table, create_engine, func, select
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.core.config import Settings
from app.core.errors import MailDeliveryError
from app.core.security import PasswordService, TokenService
from app.integrations.alumni_import import AlumniImportRow
from app.integrations.geography_import import GeographyImportRow
from app.integrations.mail import Mailer
from app.integrations.uploads import (
    AnnouncementStorage,
    AvatarStorage,
    EventStorage,
    MarketplaceStorage,
    ProjectStorage,
    VacancyStorage,
    prepare_avatar,
)
from app.main import create_app
from app.models.generated import (
    AlumniCategory,
    AlumniChapter,
    Announcements,
    Attachments,
    AuditLog,
    Cities,
    EventAttendees,
    EventRegistrationAnswers,
    EventRegistrationFormQuestions,
    EventRegistrationForms,
    EventRegistrationFormVersions,
    Events,
    Groups,
    JobVacancies,
    JwtRefreshTokens,
    Leadership,
    MarketplaceListings,
    Messages,
    MessagesAttachments,
    MessageThreads,
    Notifications,
    Projects,
    RegisterUserOtp,
    Roles,
    SetupParameters,
    ThreadParticipants,
    UserProfiles,
    Users,
    UsersGroups,
    Vouches,
    Zones,
)
from app.repositories.announcements import AnnouncementRepository
from app.repositories.auth import AuthRepository
from app.repositories.events import EventRepository
from app.repositories.leadership import LeadershipRepository
from app.repositories.marketplace import MarketplaceRepository
from app.repositories.members import MemberRepository
from app.repositories.notifications import NotificationRepository
from app.repositories.projects import ProjectRepository
from app.repositories.vacancies import VacancyRepository
from app.schemas.announcements import AnnouncementCreateRequest
from app.schemas.events import (
    EventCreateRequest,
    EventRegistrationRequest,
)
from app.schemas.leadership import LeadershipCreateRequest
from app.schemas.marketplace import MarketplaceCreateRequest
from app.schemas.members import (
    ManageCityRequest,
    ManageZoneRequest,
    UpdateProfileRequest,
    VouchActionRequest,
)
from app.schemas.projects import ProjectCreateRequest
from app.schemas.vacancies import VacancyCreateRequest
from app.services import members as members_service_module
from app.services.announcements import AnnouncementService
from app.services.events import EventService
from app.services.leadership import LeadershipService
from app.services.marketplace import MarketplaceService
from app.services.members import MemberService
from app.services.notifications import NotificationService
from app.services.projects import ProjectService
from app.services.vacancies import VacancyService

USERS_TABLE = cast(Table, Users.__table__)
ALUMNI_CATEGORY_TABLE = cast(Table, AlumniCategory.__table__)
ALUMNI_CHAPTER_TABLE = cast(Table, AlumniChapter.__table__)
ATTACHMENTS_TABLE = cast(Table, Attachments.__table__)
PROFILES_TABLE = cast(Table, UserProfiles.__table__)
ROLES_TABLE = cast(Table, Roles.__table__)
SETUP_PARAMETERS_TABLE = cast(Table, SetupParameters.__table__)
CITIES_TABLE = cast(Table, Cities.__table__)
ZONES_TABLE = cast(Table, Zones.__table__)
REFRESH_TABLE = cast(Table, JwtRefreshTokens.__table__)
OTP_TABLE = cast(Table, RegisterUserOtp.__table__)
VOUCHES_TABLE = cast(Table, Vouches.__table__)
USERS_GROUPS_TABLE = cast(Table, UsersGroups.__table__)
GROUPS_TABLE = cast(Table, Groups.__table__)
NOTIFICATIONS_TABLE = cast(Table, Notifications.__table__)
MARKETPLACE_LISTINGS_TABLE = cast(Table, MarketplaceListings.__table__)
PROJECTS_TABLE = cast(Table, Projects.__table__)
LEADERSHIP_TABLE = cast(Table, Leadership.__table__)
EVENTS_TABLE = cast(Table, Events.__table__)
EVENT_ATTENDEES_TABLE = cast(Table, EventAttendees.__table__)
EVENT_REGISTRATION_ANSWERS_TABLE = cast(Table, EventRegistrationAnswers.__table__)
EVENT_REGISTRATION_FORMS_TABLE = cast(Table, EventRegistrationForms.__table__)
EVENT_REGISTRATION_QUESTIONS_TABLE = cast(Table, EventRegistrationFormQuestions.__table__)
EVENT_REGISTRATION_FORM_VERSIONS_TABLE = cast(Table, EventRegistrationFormVersions.__table__)
MESSAGE_THREADS_TABLE = cast(Table, MessageThreads.__table__)
MESSAGES_TABLE = cast(Table, Messages.__table__)
THREAD_PARTICIPANTS_TABLE = cast(Table, ThreadParticipants.__table__)
MESSAGE_ATTACHMENTS_TABLE = cast(Table, MessagesAttachments.__table__)
AUDIT_LOG_TABLE = cast(Table, AuditLog.__table__)
PASSPHRASE = "Correct horse battery staple!"
REGISTRATION_PASSPHRASE = "Correct horse battery staple! 7"
NEW_PASSPHRASE = "A different strong passphrase!"


class _FixedLagosDateTime(datetime):
    """Supply a deterministic date to the birthday endpoint without production hooks."""

    reference_date = date(2025, 2, 28)

    @classmethod
    def now(cls, tz: Any = None) -> _FixedLagosDateTime:
        return cls(
            cls.reference_date.year,
            cls.reference_date.month,
            cls.reference_date.day,
            tzinfo=tz,
        )


def _geography_xlsx_bytes(rows: list[tuple[str, str]]) -> bytes:
    """Build a small in-memory XLSX fixture without touching workspace files."""
    workbook = Workbook()
    sheet = workbook.active
    sheet.append(("zone", "city"))
    for zone, city in rows:
        sheet.append((zone, city))
    output = BytesIO()
    workbook.save(output)
    workbook.close()
    return output.getvalue()


def _alumni_import_row(
    email: str,
    city: str,
    *,
    source_row: int = 1,
    coordinator_requested: bool = False,
) -> AlumniImportRow:
    """Build one fully validated-equivalent synthetic roster row for service tests."""
    return AlumniImportRow(
        source_row=source_row,
        email=email,
        first_name="Synthetic",
        last_name="Imported",
        fullname="Synthetic Ada Imported",
        name_in_school="Synthetic School Name",
        phone="08000000001",
        alternative_phone=None,
        birth_date=date(1990, 1, 2),
        graduation_year=datetime.now(UTC).year,
        house_color="Blue",
        coordinator_requested=coordinator_requested,
        residential_address="Synthetic import address",
        area="Synthetic Area",
        city=city,
        employment_status="Employed",
        occupation="Engineer",
        industry_sector="Technology",
        years_of_experience="10",
        is_volunteer=True,
    )


@dataclass(slots=True)
class RecordingMailer(Mailer):
    """Capture reset links in memory without contacting an external provider."""

    deliveries: list[tuple[str, str, str]] = field(default_factory=list)
    verification_deliveries: list[tuple[str, str, str]] = field(default_factory=list)
    manager_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    voucher_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    voucher_approval_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    account_status_deliveries: list[tuple[str, str, str, str | None]] = field(default_factory=list)
    account_activity_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    order_status_deliveries: list[tuple[str, str, str, str]] = field(default_factory=list)
    contact_deliveries: list[tuple[str, str, str, str, str]] = field(default_factory=list)
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

    def send_voucher_approval_notification(
        self,
        recipient: str,
        recipient_name: str,
        voucher_name: str,
        member_name: str,
    ) -> None:
        """Record a manager voucher-approval notice or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.voucher_approval_deliveries.append(
            (recipient, recipient_name, voucher_name, member_name)
        )

    def send_account_status(
        self,
        recipient: str,
        display_name: str,
        action: str,
        reason: str | None = None,
    ) -> None:
        """Record an account-status notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.account_status_deliveries.append((recipient, display_name, action, reason))

    def send_account_activity(
        self,
        recipient: str,
        display_name: str,
        action: str,
        actor_kind: str,
    ) -> None:
        """Record an account activity notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.account_activity_deliveries.append((recipient, display_name, action, actor_kind))

    def send_order_status_email(
        self,
        recipient: str,
        display_name: str = "",
        order_number: str = "",
        status: str = "",
        delivery_type: str = "",
        note: str = "",
        rider_details: str = "",
        *,
        first_name: str = "",
        details: dict[str, Any] | None = None,
    ) -> None:
        """Record an order status notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.order_status_deliveries.append((recipient, order_number, status, delivery_type))

    def send_contact_form(
        self,
        recipient: str,
        recipient_name: str,
        full_name: str,
        email: str,
        message: str,
    ) -> None:
        """Record a contact-message notification or provider failure."""
        if self.fail:
            raise MailDeliveryError("synthetic delivery failure")
        self.contact_deliveries.append((recipient, recipient_name, full_name, email, message))


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
    alumni_category_ids: list[int] = field(default_factory=list)
    alumni_chapter_ids: list[int] = field(default_factory=list)
    setup_parameter_ids: list[int] = field(default_factory=list)
    created_member_group: bool = False

    def create_user(
        self,
        *,
        password: str = PASSPHRASE,
        active: int = 1,
        email_verified: int = 1,
        is_approved: int = 1,
        onboarding_completion: int = 1,
        city: str = "",
        voucher: str = "",
        user_role: str = "alumni",
        fullname: str = "Synthetic Member",
        graduation_year: int | None = None,
        profile_status: str = "active",
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
                    profile_status=profile_status,
                    voucher=voucher,
                    resetKey="",
                    first_name="Synthetic",
                    last_name="Member",
                    fullname=fullname,
                    phone="+2348000000000",
                    avatar="uploads/profiles/synthetic.png",
                    city=city,
                    active=active,
                    user_role=user_role,
                    is_approved=is_approved,
                    email_verified=email_verified,
                    graduation_year=graduation_year,
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

    def create_registration_location(self, *, enabled: int = 1) -> tuple[int, str]:
        """Create one chapter-backed city advertised by the registration catalogue."""
        self.ensure_member_group()
        suffix = uuid.uuid4().hex[:10]
        chapter_name = f"Synthetic Registration Chapter {suffix}"
        city = f"Synthetic Registration City {suffix}"
        zone = f"Synthetic Registration Zone {suffix}"
        with self.engine.begin() as connection:
            chapter_result = connection.execute(
                ALUMNI_CHAPTER_TABLE.insert().values(
                    chapter_name=chapter_name,
                    location="Synthetic Location",
                    is_enabled=enabled,
                )
            )
            chapter_key = chapter_result.inserted_primary_key
            assert chapter_key is not None
            chapter_id = int(chapter_key[0])
            zone_result = connection.execute(
                ZONES_TABLE.insert().values(zone=zone, chapter_id=chapter_id)
            )
            zone_key = zone_result.inserted_primary_key
            assert zone_key is not None
            connection.execute(
                CITIES_TABLE.insert().values(
                    city=city,
                    zone_id=int(zone_key[0]),
                    chapter_id=chapter_id,
                )
            )
        self.alumni_chapter_ids.append(chapter_id)
        self.city_names.append(city)
        self.zone_names.append(zone)
        return chapter_id, city

    def create_chapter(self, *, enabled: int = 1, chapter_id: int | None = None) -> int:
        """Create and track one chapter for geography-management tests."""
        suffix = uuid.uuid4().hex[:10]
        with self.engine.begin() as connection:
            result = connection.execute(
                ALUMNI_CHAPTER_TABLE.insert().values(
                    id=chapter_id,
                    chapter_name=f"Synthetic Geography Chapter {suffix}",
                    location="Synthetic Location",
                    is_enabled=enabled,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            created_chapter_id = int(inserted_key[0])
        self.alumni_chapter_ids.append(created_chapter_id)
        return created_chapter_id

    def ensure_member_group(self) -> None:
        """Seed the schema-only fixture's reviewed Ion Auth member group once."""
        if self.created_member_group:
            return
        with self.engine.begin() as connection:
            exists = connection.scalar(select(Groups.id).where(Groups.id == 2))
            if exists is None:
                connection.execute(
                    GROUPS_TABLE.insert().values(
                        id=2,
                        name="members",
                        description="General User",
                    )
                )
                self.created_member_group = True

    def create_vouch(
        self,
        registrant_user_id: int,
        voucher_user_id: int,
        *,
        status: str = "pending",
        reason: str | None = None,
    ) -> int:
        """Create one synthetic voucher relationship owned by tracked users."""
        now = datetime.now(UTC).replace(tzinfo=None)
        with self.engine.begin() as connection:
            result = connection.execute(
                VOUCHES_TABLE.insert().values(
                    register_id=registrant_user_id,
                    voucher_id=voucher_user_id,
                    status=status,
                    reason=reason,
                    created_at=now,
                    updated_at=now,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            return int(inserted_key[0])

    def create_zone(
        self,
        *,
        coordinator_user_id: int | None = None,
        chapter_id: int = 1,
        cities: tuple[str, ...] = (),
    ) -> tuple[int, dict[str, int]]:
        """Create one uniquely named zone and tracked city rows."""
        suffix = uuid.uuid4().hex[:10]
        zone_name = f"Synthetic Zone Catalogue {suffix}"
        now = datetime.now(UTC).replace(tzinfo=None)
        city_ids: dict[str, int] = {}
        with self.engine.begin() as connection:
            result = connection.execute(
                ZONES_TABLE.insert().values(
                    zone=zone_name,
                    coordinator_user_id=coordinator_user_id,
                    chapter_id=chapter_id,
                    created_at=now,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            zone_id = int(inserted_key[0])
            for city in cities:
                city_result = connection.execute(
                    CITIES_TABLE.insert().values(
                        city=city,
                        zone_id=zone_id,
                        chapter_id=chapter_id,
                        created_at=now,
                    )
                )
                city_key = city_result.inserted_primary_key
                assert city_key is not None
                city_ids[city] = int(city_key[0])
        self.zone_names.append(zone_name)
        self.city_names.extend(cities)
        return zone_id, city_ids

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
        if (
            not self.user_ids
            and not self.city_names
            and not self.zone_names
            and not self.alumni_category_ids
            and not self.alumni_chapter_ids
            and not self.setup_parameter_ids
            and not self.created_member_group
        ):
            return
        with self.engine.begin() as connection:
            connection.execute(AUDIT_LOG_TABLE.delete())
            tracked_user_ids = set(self.user_ids)
            if self.emails:
                tracked_user_ids.update(
                    connection.scalars(select(Users.id).where(Users.email.in_(self.emails)))
                )
            if self.setup_parameter_ids:
                connection.execute(
                    SETUP_PARAMETERS_TABLE.delete().where(
                        SetupParameters.setup_id.in_(self.setup_parameter_ids)
                    )
                )
            if self.alumni_category_ids:
                connection.execute(
                    ALUMNI_CATEGORY_TABLE.delete().where(
                        AlumniCategory.id.in_(self.alumni_category_ids)
                    )
                )
            if tracked_user_ids:
                tracked_event_ids = list(
                    connection.scalars(
                        select(Events.id).where(Events.created_by.in_(tracked_user_ids))
                    )
                )
                if tracked_event_ids:
                    tracked_form_ids = list(
                        connection.scalars(
                            select(EventRegistrationForms.id).where(
                                EventRegistrationForms.event_id.in_(tracked_event_ids)
                            )
                        )
                    )
                    if tracked_form_ids:
                        connection.execute(
                            EVENT_REGISTRATION_QUESTIONS_TABLE.delete().where(
                                EventRegistrationFormQuestions.form_id.in_(tracked_form_ids)
                            )
                        )
                        connection.execute(
                            EVENT_REGISTRATION_FORMS_TABLE.delete().where(
                                EventRegistrationForms.id.in_(tracked_form_ids)
                            )
                        )
                    connection.execute(
                        EVENTS_TABLE.delete().where(Events.id.in_(tracked_event_ids))
                    )
                connection.execute(
                    ALUMNI_CATEGORY_TABLE.delete().where(
                        AlumniCategory.user_id.in_(tracked_user_ids)
                    )
                )
                connection.execute(
                    VOUCHES_TABLE.delete().where(
                        Vouches.register_id.in_(tracked_user_ids)
                        | Vouches.voucher_id.in_(tracked_user_ids)
                    )
                )
                connection.execute(
                    REFRESH_TABLE.delete().where(JwtRefreshTokens.user_id.in_(tracked_user_ids))
                )
                connection.execute(
                    ATTACHMENTS_TABLE.delete().where(Attachments.user_id.in_(tracked_user_ids))
                )
                connection.execute(
                    PROFILES_TABLE.delete().where(UserProfiles.user_id.in_(tracked_user_ids))
                )
                connection.execute(ROLES_TABLE.delete().where(Roles.user_id.in_(tracked_user_ids)))
                connection.execute(
                    USERS_GROUPS_TABLE.delete().where(UsersGroups.user_id.in_(tracked_user_ids))
                )
                connection.execute(USERS_TABLE.delete().where(Users.id.in_(tracked_user_ids)))
            if self.created_member_group:
                connection.execute(GROUPS_TABLE.delete().where(Groups.id == 2))
            if self.emails:
                connection.execute(OTP_TABLE.delete().where(RegisterUserOtp.email.in_(self.emails)))
            if self.city_names:
                connection.execute(CITIES_TABLE.delete().where(Cities.city.in_(self.city_names)))
            if self.zone_names:
                connection.execute(ZONES_TABLE.delete().where(Zones.zone.in_(self.zone_names)))
            if self.alumni_chapter_ids:
                connection.execute(
                    ALUMNI_CHAPTER_TABLE.delete().where(
                        AlumniChapter.id.in_(self.alumni_chapter_ids)
                    )
                )


@pytest.fixture
def auth_harness(rsa_pem_pair: tuple[str, str], tmp_path: Path) -> Iterator[AuthHarness]:
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
        upload_root=tmp_path / "uploads",
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


def _registration_payload(
    auth_harness: AuthHarness,
    chapter_id: int,
    city: str,
    *,
    email: str | None = None,
    voucher_id: int | None = None,
) -> dict[str, object]:
    """Build one current-frontend-compatible registration request."""
    identity = email or f"codex-register-{auth_harness.marker}-{uuid.uuid4().hex}@example.com"
    auth_harness.emails.append(identity.lower())
    payload: dict[str, object] = {
        "email": identity,
        "password": REGISTRATION_PASSPHRASE,
        "first_name": "Synthetic",
        "last_name": "Registrant",
        "phone": "08000000001",
        "chapter_id": chapter_id,
        "graduation_year": datetime.now(UTC).year,
        "city": city,
        "department": "Science",
        "name_in_school": "Synthetic School Name",
        "nick_name": "Synth",
        "residential_address": "Synthetic registration address",
        "area": "Synthetic Area",
        "state": "Lagos",
        "is_volunteer": "1",
        "user_role": "superadmin",
        "is_coordinator": "1",
        "year": "1999",
    }
    if voucher_id is not None:
        payload["voucher_id"] = voucher_id
    return payload


@pytest.mark.integration
def test_registration_creates_complete_private_account_with_server_owned_privileges(
    auth_harness: AuthHarness,
) -> None:
    """Registration atomically creates every required row and ignores privilege fields."""
    chapter_id, city = auth_harness.create_registration_location()
    voucher_id, voucher_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == voucher_id).values(voucher="yes"))
    payload = _registration_payload(
        auth_harness,
        chapter_id,
        city,
        email=f"CODEX-REGISTER-{auth_harness.marker}@EXAMPLE.COM",
        voucher_id=voucher_id,
    )

    response = auth_harness.client.post(
        "/api/register",
        headers={"X-API-Key": "ignored-legacy-key"},
        json=payload,
    )
    body = response.json()

    assert response.status_code == 200
    assert body["status"] == 200
    assert body["user_role"] == "alumni"
    assert body["is_coordinator"] is False
    assert body["is_volunteer"] is True
    assert body["chapter_id"] == chapter_id
    assert body["city"] == city
    assert body["expires_in_minutes"] == 1440
    assert body["user_code"].startswith(f"MBR-{datetime.now(UTC).year}-")
    assert len(body["user_code"].rsplit("-", 1)[1]) == 6
    assert not ({"password", "verify_token", "ip_address", "voucher_id"} & body.keys())

    user_id = int(body["user_id"])
    auth_harness.user_ids.append(user_id)
    with auth_harness.engine.connect() as connection:
        user = (
            connection.execute(select(Users.__table__).where(Users.id == user_id)).mappings().one()
        )
        group_id = connection.scalar(
            select(UsersGroups.group_id).where(UsersGroups.user_id == user_id)
        )
        category = (
            connection.execute(
                select(AlumniCategory.__table__).where(AlumniCategory.user_id == user_id)
            )
            .mappings()
            .one()
        )
        profile = (
            connection.execute(
                select(UserProfiles.__table__).where(UserProfiles.user_id == user_id)
            )
            .mappings()
            .one()
        )
        vouch = (
            connection.execute(select(Vouches.__table__).where(Vouches.register_id == user_id))
            .mappings()
            .one()
        )
        otp = (
            connection.execute(
                select(RegisterUserOtp.__table__).where(RegisterUserOtp.email == body["email"])
            )
            .mappings()
            .one()
        )

    assert user["email"] == str(payload["email"]).lower()
    assert user["username"] == user["email"]
    assert PasswordService().verify(REGISTRATION_PASSPHRASE, str(user["password"]))
    assert str(user["password"]).startswith("$argon2id$")
    assert (user["active"], user["email_verified"], user["is_approved"]) == (0, 0, 0)
    assert (user["user_role"], user["is_coordinator"]) == ("alumni", 0)
    assert group_id == 2
    assert (category["chapter_id"], category["location"]) == (chapter_id, city)
    assert (profile["chapter_id"], profile["city"], profile["is_visible"]) == (
        chapter_id,
        city,
        0,
    )
    assert all(value is False for value in json.loads(profile["field_visibility"]).values())
    assert (vouch["voucher_id"], vouch["status"].value) == (voucher_id, "pending")
    assert (otp["is_active"], str(otp["otp"])) == (1, str(user["verify_token"]))
    assert auth_harness.mailer.verification_deliveries == [
        (body["email"], "Synthetic Registrant", str(otp["otp"]))
    ]
    assert auth_harness.mailer.voucher_deliveries == []
    assert voucher_email != body["email"]


@pytest.mark.integration
def test_registration_accepts_normalized_multipart_avatar(auth_harness: AuthHarness) -> None:
    """The legacy avatar option uses the shared bounded storage and attachment contract."""
    chapter_id, city = auth_harness.create_registration_location()
    payload = _registration_payload(auth_harness, chapter_id, city)
    image_output = BytesIO()
    Image.new("RGBA", (9, 7), color=(10, 20, 30, 120)).save(image_output, format="PNG")

    response = auth_harness.client.post(
        "/api/register",
        data={key: str(value) for key, value in payload.items()},
        files={"avatar": ("synthetic avatar.png", image_output.getvalue(), "image/png")},
    )

    assert response.status_code == 200
    body = response.json()
    user_id = int(body["user_id"])
    auth_harness.user_ids.append(user_id)
    assert body["avatar"].startswith("https://alumni.example.test/uploads/profiles/")
    with auth_harness.engine.connect() as connection:
        stored_avatar = connection.scalar(select(Users.avatar).where(Users.id == user_id))
        attachment = (
            connection.execute(select(Attachments.__table__).where(Attachments.user_id == user_id))
            .mappings()
            .one()
        )
    assert stored_avatar == attachment["attachment_file"]
    assert attachment["filename"] == "synthetic_avatar.png"
    assert attachment["file_type"] == "profile_image"
    stored_file = auth_harness.settings.upload_root / str(stored_avatar).removeprefix("uploads/")
    assert stored_file.is_file()
    with Image.open(stored_file) as stored_image:
        assert stored_image.format == "PNG"
        assert stored_image.size == (9, 7)


@pytest.mark.integration
def test_registration_rejects_invalid_relationships_and_duplicate_email(
    auth_harness: AuthHarness,
) -> None:
    """Invalid registration relationships and duplicate identities fail closed."""
    chapter_id, city = auth_harness.create_registration_location()
    other_chapter_id, other_city = auth_harness.create_registration_location()
    disabled_chapter_id, disabled_city = auth_harness.create_registration_location(enabled=0)
    voucher_id, _voucher_email, _ = auth_harness.create_user(active=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == voucher_id).values(voucher="yes"))

    requests = [
        _registration_payload(auth_harness, disabled_chapter_id, disabled_city),
        _registration_payload(auth_harness, chapter_id, other_city),
        _registration_payload(auth_harness, other_chapter_id, city),
        _registration_payload(auth_harness, chapter_id, city, voucher_id=voucher_id),
    ]
    responses = [auth_harness.client.post("/api/register", json=payload) for payload in requests]
    assert [response.status_code for response in responses] == [400, 400, 400, 400]
    assert [response.json()["code"] for response in responses] == [
        "registration_chapter_invalid",
        "registration_city_invalid",
        "registration_city_invalid",
        "registration_voucher_invalid",
    ]

    valid_payload = _registration_payload(auth_harness, chapter_id, city)
    created = auth_harness.client.post("/api/register", json=valid_payload)
    duplicate = auth_harness.client.post("/api/register", json=valid_payload)
    assert created.status_code == 200
    assert duplicate.status_code == 409
    assert duplicate.json()["code"] == "registration_email_exists"
    auth_harness.user_ids.append(int(created.json()["user_id"]))
    with auth_harness.engine.connect() as connection:
        duplicate_count = connection.scalar(
            select(func.count())
            .select_from(USERS_TABLE)
            .where(Users.email == str(valid_payload["email"]))
        )
        rejected_count = connection.scalar(
            select(func.count())
            .select_from(USERS_TABLE)
            .where(Users.email.in_([str(payload["email"]) for payload in requests]))
        )
    assert duplicate_count == 1
    assert rejected_count == 0


@pytest.mark.integration
def test_registration_mail_failure_preserves_resendable_account(auth_harness: AuthHarness) -> None:
    """A provider outage does not orphan the user or falsely claim successful delivery."""
    chapter_id, city = auth_harness.create_registration_location()
    payload = _registration_payload(auth_harness, chapter_id, city)
    auth_harness.mailer.fail = True

    response = auth_harness.client.post("/api/register", json=payload)

    assert response.status_code == 200
    assert response.json()["message"] == (
        "Registration saved. Please request a new verification code to continue."
    )
    user_id = int(response.json()["user_id"])
    auth_harness.user_ids.append(user_id)
    with auth_harness.engine.connect() as connection:
        original_code = connection.scalar(
            select(RegisterUserOtp.otp).where(
                RegisterUserOtp.email == str(payload["email"]),
                RegisterUserOtp.is_active == 1,
            )
        )
    assert original_code is not None

    auth_harness.mailer.fail = False
    resent = auth_harness.client.post("/api/resend_verify_email", json={"user_id": user_id})
    assert resent.status_code == 200
    assert auth_harness.mailer.verification_deliveries[-1][2] != str(original_code)


@pytest.mark.integration
def test_registration_avatar_database_failure_rolls_back_rows_and_file(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A late metadata failure removes the normalized file and every registration row."""
    chapter_id, city = auth_harness.create_registration_location()
    payload = _registration_payload(auth_harness, chapter_id, city)
    image_output = BytesIO()
    Image.new("RGB", (8, 8), color=(20, 40, 60)).save(image_output, format="PNG")

    def fail_attachment_insert(*_args: object, **_kwargs: object) -> None:
        raise SQLAlchemyError("synthetic registration metadata failure")

    monkeypatch.setattr(
        AuthRepository,
        "insert_registration_avatar_attachment",
        fail_attachment_insert,
    )
    response = auth_harness.client.post(
        "/api/register",
        data={key: str(value) for key, value in payload.items()},
        files={"avatar": ("synthetic.png", image_output.getvalue(), "image/png")},
    )

    assert response.status_code == 503
    assert response.json()["code"] == "registration_unavailable"
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count())
                .select_from(USERS_TABLE)
                .where(Users.email == str(payload["email"]))
            )
            == 0
        )
        assert (
            connection.scalar(
                select(func.count())
                .select_from(OTP_TABLE)
                .where(RegisterUserOtp.email == str(payload["email"]))
            )
            == 0
        )
    profile_directory = auth_harness.settings.upload_root / "profiles"
    assert not profile_directory.exists() or list(profile_directory.iterdir()) == []


@pytest.mark.integration
def test_voucher_discovery_is_filtered_deterministic_and_public_safe(
    auth_harness: AuthHarness,
) -> None:
    """Registration can discover active class-year vouchers without contact data."""
    first_id, _first_email, _ = auth_harness.create_user(
        voucher="yes",
        fullname="Synthetic Alpha Voucher",
        graduation_year=2001,
    )
    second_id, _second_email, _ = auth_harness.create_user(
        voucher="yes",
        fullname="Synthetic Beta Voucher",
        graduation_year=2001,
    )
    auth_harness.create_user(
        voucher="yes",
        active=0,
        fullname="Synthetic Inactive Voucher",
        graduation_year=2001,
    )
    auth_harness.create_user(
        fullname="Synthetic Non Voucher",
        graduation_year=2001,
    )
    auth_harness.create_user(
        voucher="yes",
        fullname="Synthetic Other Class Voucher",
        graduation_year=2002,
    )

    post_response = auth_harness.client.post(
        "/api/get_vouchers",
        json={"graduation_year": 2001},
        headers={"X-API-Key": "legacy-shared-key-is-ignored"},
    )
    get_response = auth_harness.client.get(
        "/api/get_vouchers",
        params={"graduation_year": 2001},
    )

    assert post_response.status_code == get_response.status_code == 200
    assert post_response.json() == get_response.json()
    body = post_response.json()
    assert body["total"] == 2
    assert [voucher["voucher_id"] for voucher in body["vouchers"]] == [first_id, second_id]
    assert [voucher["fullname"] for voucher in body["vouchers"]] == [
        "Synthetic Alpha Voucher",
        "Synthetic Beta Voucher",
    ]
    assert all(
        set(voucher) == {"voucher_id", "fullname", "graduation_year", "chapter_id"}
        for voucher in body["vouchers"]
    )
    assert all(
        {"email", "phone", "department", "avatar", "user_role"}.isdisjoint(voucher)
        for voucher in body["vouchers"]
    )


@pytest.mark.integration
def test_pending_vouches_are_current_role_checked_and_owner_scoped(
    auth_harness: AuthHarness,
) -> None:
    """A voucher sees only their pending registrations through either legacy method."""
    owner_id, owner_email, _ = auth_harness.create_user(voucher="yes")
    other_owner_id, _other_owner_email, _ = auth_harness.create_user(voucher="yes")
    nonvoucher_id, nonvoucher_email, _ = auth_harness.create_user()
    own_pending_id, own_pending_email, _ = auth_harness.create_user(
        is_approved=0,
        fullname="Synthetic Pending Registrant",
        graduation_year=2004,
    )
    own_denied_id, _own_denied_email, _ = auth_harness.create_user(is_approved=0)
    other_pending_id, _other_pending_email, _ = auth_harness.create_user(is_approved=0)
    own_vouch_id = auth_harness.create_vouch(own_pending_id, owner_id)
    auth_harness.create_vouch(own_denied_id, owner_id, status="denied")
    auth_harness.create_vouch(other_pending_id, other_owner_id)
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }

    get_response = auth_harness.client.get("/api/voucher_pending", headers=owner_headers)
    post_response = auth_harness.client.post("/api/voucher_pending", headers=owner_headers)

    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json()
    assert get_response.json() == {
        "status": 200,
        "message": "Pending vouches retrieved successfully",
        "total": 1,
        "pending": [
            {
                "vouch_id": own_vouch_id,
                "user_id": own_pending_id,
                "fullname": "Synthetic Pending Registrant",
                "email": own_pending_email,
                "graduation_year": 2004,
                "nick_name": "Synthetic",
                "status": "pending",
                "created_at": get_response.json()["pending"][0]["created_at"],
            }
        ],
    }

    nonvoucher_response = auth_harness.client.get(
        "/api/voucher_pending",
        headers={
            "Authorization": (
                f"Bearer {_access_token_for(auth_harness, nonvoucher_id, nonvoucher_email)}"
            )
        },
    )
    assert nonvoucher_response.status_code == 403
    assert nonvoucher_response.json()["code"] == "voucher_role_required"

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == owner_id).values(active=0))
    inactive_response = auth_harness.client.get("/api/voucher_pending", headers=owner_headers)
    assert inactive_response.status_code == 401
    assert inactive_response.json()["code"] == "voucher_actor_unavailable"


@pytest.mark.integration
def test_vouch_action_approves_or_denies_once_and_notifies_bounded_recipients(
    auth_harness: AuthHarness,
) -> None:
    """Owned decisions persist canonical states and only notify reviewed recipients."""
    owner_id, owner_email, _ = auth_harness.create_user(
        voucher="yes",
        fullname="Synthetic Trusted Voucher",
    )
    manager_id, manager_email, _ = auth_harness.create_user(
        user_role="admin",
        fullname="Synthetic Account Manager",
    )
    auth_harness.create_user(user_role="subadmin")
    approved_id, approved_email, _ = auth_harness.create_user(
        active=0,
        is_approved=0,
        fullname="Synthetic Approved Registrant",
        profile_status="pending",
    )
    denied_id, denied_email, _ = auth_harness.create_user(
        active=0,
        is_approved=0,
        fullname="Synthetic Denied Registrant",
        profile_status="pending",
    )
    approved_vouch_id = auth_harness.create_vouch(approved_id, owner_id)
    denied_vouch_id = auth_harness.create_vouch(denied_id, owner_id)
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"}

    approved = auth_harness.client.post(
        "/api/vouch_action",
        headers=headers,
        json={"vouch_id": approved_vouch_id, "action": "approve"},
    )
    denied = auth_harness.client.post(
        "/api/vouch_action",
        headers=headers,
        data={"vouch_id": str(denied_vouch_id), "action": "reject", "reason": "  Not known  "},
    )
    replay = auth_harness.client.post(
        "/api/vouch_action",
        headers=headers,
        json={"vouch_id": denied_vouch_id, "action": "deny"},
    )

    assert approved.status_code == denied.status_code == 200
    assert approved.json() == {
        "status": 200,
        "message": "Account approved by voucher",
        "vouch_id": approved_vouch_id,
        "register_id": approved_id,
        "action": "approve",
        "vouch_status": "approved",
        "account_approved": True,
        "account_active": True,
    }
    assert denied.json()["action"] == "deny"
    assert denied.json()["vouch_status"] == "denied"
    assert denied.json()["account_approved"] is False
    assert denied.json()["account_active"] is False
    assert replay.status_code == 409
    assert replay.json()["code"] == "vouch_already_actioned"

    with auth_harness.engine.connect() as connection:
        approved_vouch = (
            connection.execute(select(VOUCHES_TABLE).where(Vouches.id == approved_vouch_id))
            .mappings()
            .one()
        )
        denied_vouch = (
            connection.execute(select(VOUCHES_TABLE).where(Vouches.id == denied_vouch_id))
            .mappings()
            .one()
        )
        approved_user = (
            connection.execute(select(USERS_TABLE).where(Users.id == approved_id)).mappings().one()
        )
        denied_user = (
            connection.execute(select(USERS_TABLE).where(Users.id == denied_id)).mappings().one()
        )
    assert getattr(approved_vouch["status"], "value", approved_vouch["status"]) == "approved"
    assert approved_vouch["reason"] is None
    assert (
        approved_user["is_approved"],
        approved_user["active"],
        approved_user["profile_status"],
    ) == (
        1,
        1,
        "active",
    )
    assert getattr(denied_vouch["status"], "value", denied_vouch["status"]) == "denied"
    assert denied_vouch["reason"] == "Not known"
    assert (denied_user["is_approved"], denied_user["active"], denied_user["profile_status"]) == (
        0,
        0,
        "pending",
    )
    assert auth_harness.mailer.account_status_deliveries == [
        (approved_email, "Synthetic Approved Registrant", "approve", None),
        (denied_email, "Synthetic Denied Registrant", "reject", "Not known"),
    ]
    assert auth_harness.mailer.voucher_approval_deliveries == [
        (
            manager_email,
            "Synthetic Account Manager",
            "Synthetic Trusted Voucher",
            "Synthetic Approved Registrant",
        )
    ]
    assert manager_id in auth_harness.user_ids


@pytest.mark.integration
def test_vouch_action_rechecks_owner_and_registrant_eligibility(
    auth_harness: AuthHarness,
) -> None:
    """Cross-owner, unverified, and already-approved decisions fail closed."""
    owner_id, owner_email, _ = auth_harness.create_user(voucher="yes")
    other_owner_id, other_owner_email, _ = auth_harness.create_user(voucher="yes")
    unverified_id, _unverified_email, _ = auth_harness.create_user(
        email_verified=0,
        is_approved=0,
    )
    already_approved_id, _approved_email, _ = auth_harness.create_user(is_approved=1)
    unverified_vouch_id = auth_harness.create_vouch(unverified_id, owner_id)
    approved_vouch_id = auth_harness.create_vouch(already_approved_id, owner_id)
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    other_headers = {
        "Authorization": (
            f"Bearer {_access_token_for(auth_harness, other_owner_id, other_owner_email)}"
        )
    }

    wrong_owner = auth_harness.client.post(
        "/api/vouch_action",
        headers=other_headers,
        json={"vouch_id": unverified_vouch_id, "action": "approve"},
    )
    unverified = auth_harness.client.post(
        "/api/vouch_action",
        headers=owner_headers,
        json={"vouch_id": unverified_vouch_id, "action": "approve"},
    )
    already_approved = auth_harness.client.post(
        "/api/vouch_action",
        headers=owner_headers,
        json={"vouch_id": approved_vouch_id, "action": "approve"},
    )

    assert wrong_owner.status_code == 404
    assert wrong_owner.json()["code"] == "vouch_not_found"
    assert unverified.status_code == 409
    assert unverified.json()["code"] == "vouch_email_unverified"
    assert already_approved.status_code == 409
    assert already_approved.json()["code"] == "vouch_account_already_approved"
    with auth_harness.engine.connect() as connection:
        statuses = connection.execute(
            select(Vouches.id, Vouches.status).where(
                Vouches.id.in_((unverified_vouch_id, approved_vouch_id))
            )
        ).all()
    assert {vouch_id: getattr(status, "value", status) for vouch_id, status in statuses} == {
        unverified_vouch_id: "pending",
        approved_vouch_id: "pending",
    }


@pytest.mark.integration
def test_vouch_action_rolls_back_late_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failure after both mutations rolls the vouch and account back together."""
    owner_id, _owner_email, _ = auth_harness.create_user(voucher="yes")
    registrant_id, _registrant_email, _ = auth_harness.create_user(
        active=0,
        is_approved=0,
        profile_status="pending",
    )
    vouch_id = auth_harness.create_vouch(registrant_id, owner_id)

    def fail_manager_lookup(_self: MemberRepository) -> list[dict[str, Any]]:
        raise SQLAlchemyError("synthetic manager lookup failure")

    monkeypatch.setattr(MemberRepository, "account_manager_recipients", fail_manager_lookup)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(SQLAlchemyError, match="synthetic manager lookup failure"),
    ):
        MemberService(session, auth_harness.settings, auth_harness.mailer).decide_vouch(
            owner_id,
            VouchActionRequest(vouch_id=vouch_id, action="approve"),
        )

    with auth_harness.engine.connect() as connection:
        status = connection.scalar(select(Vouches.status).where(Vouches.id == vouch_id))
        account = (
            connection.execute(
                select(Users.is_approved, Users.active, Users.profile_status).where(
                    Users.id == registrant_id
                )
            )
            .mappings()
            .one()
        )
    assert getattr(status, "value", status) == "pending"
    assert (account["is_approved"], account["active"], account["profile_status"]) == (
        0,
        0,
        "pending",
    )
    assert auth_harness.mailer.account_status_deliveries == []
    assert auth_harness.mailer.voucher_approval_deliveries == []


@pytest.mark.integration
def test_vouch_action_commits_before_best_effort_notification(
    auth_harness: AuthHarness,
) -> None:
    """A mail outage cannot roll back a valid owned voucher approval."""
    owner_id, owner_email, _ = auth_harness.create_user(voucher="yes")
    registrant_id, _registrant_email, _ = auth_harness.create_user(
        active=0,
        is_approved=0,
        profile_status="pending",
    )
    vouch_id = auth_harness.create_vouch(registrant_id, owner_id)
    auth_harness.mailer.fail = True

    response = auth_harness.client.post(
        "/api/vouch_action",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
        },
        json={"vouch_id": vouch_id, "action": "approve"},
    )

    assert response.status_code == 200
    with auth_harness.engine.connect() as connection:
        status = connection.scalar(select(Vouches.status).where(Vouches.id == vouch_id))
        account = connection.execute(
            select(Users.is_approved, Users.active).where(Users.id == registrant_id)
        ).one()
    assert getattr(status, "value", status) == "approved"
    assert tuple(account) == (1, 1)
    assert auth_harness.mailer.account_status_deliveries == []


@pytest.mark.integration
def test_city_catalogue_is_public_deterministic_and_keeps_orphan_zone_ids(
    auth_harness: AuthHarness,
) -> None:
    """Registration receives bounded city metadata without the reusable application key."""
    suffix = uuid.uuid4().hex[:8]
    first_cities = (f"Synthetic Z City {suffix}", f"Synthetic A City {suffix}")
    first_zone_id, first_city_ids = auth_harness.create_zone(cities=first_cities)
    first_zone_name = auth_harness.zone_names[-1]
    second_city = f"Synthetic B City {suffix}"
    second_zone_id, second_city_ids = auth_harness.create_zone(cities=(second_city,))
    second_zone_name = auth_harness.zone_names[-1]
    orphan_city = f"Synthetic Orphan City {suffix}"
    orphan_zone_id = 2_000_000_000
    with auth_harness.engine.begin() as connection:
        orphan_result = connection.execute(
            CITIES_TABLE.insert().values(
                city=orphan_city,
                zone_id=orphan_zone_id,
                chapter_id=1,
                created_at=datetime.now(UTC).replace(tzinfo=None),
            )
        )
        orphan_key = orphan_result.inserted_primary_key
        assert orphan_key is not None
        orphan_city_id = int(orphan_key[0])
    auth_harness.city_names.append(orphan_city)

    get_response = auth_harness.client.get(
        "/api/get_cities",
        headers={"X-API-Key": "legacy-shared-key-is-ignored"},
    )
    post_response = auth_harness.client.post("/api/get_cities", json={"token": "ignored"})

    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json()
    body = get_response.json()
    assert body["status"] == 200
    assert body["message"] == "Cities retrieved successfully"
    tracked_ids = {*first_city_ids.values(), *second_city_ids.values(), orphan_city_id}
    tracked = [city for city in body["data"] if city["city_id"] in tracked_ids]
    assert tracked == [
        {
            "city_id": first_city_ids[first_cities[1]],
            "city": first_cities[1],
            "chapter_id": 1,
            "zone_id": first_zone_id,
            "zone": first_zone_name,
        },
        {
            "city_id": first_city_ids[first_cities[0]],
            "city": first_cities[0],
            "chapter_id": 1,
            "zone_id": first_zone_id,
            "zone": first_zone_name,
        },
        {
            "city_id": second_city_ids[second_city],
            "city": second_city,
            "chapter_id": 1,
            "zone_id": second_zone_id,
            "zone": second_zone_name,
        },
        {
            "city_id": orphan_city_id,
            "city": orphan_city,
            "chapter_id": 1,
            "zone_id": orphan_zone_id,
        },
    ]
    assert all(
        {"email", "phone", "avatar", "user_role", "coordinator_user_id"}.isdisjoint(city)
        for city in body["data"]
    )


@pytest.mark.integration
def test_zone_catalogue_is_public_nested_and_coordinator_privacy_aware(
    auth_harness: AuthHarness,
) -> None:
    """Welfare zones keep public structure while coordinator PII obeys eligibility/privacy."""
    eligible_id, eligible_email, _ = auth_harness.create_user(
        fullname="Synthetic Visible Coordinator"
    )
    malformed_id, malformed_email, _ = auth_harness.create_user(
        fullname="Synthetic Malformed Coordinator"
    )
    default_id, default_email, _ = auth_harness.create_user(
        fullname="Synthetic Default Visibility Coordinator"
    )
    hidden_id, hidden_email, _ = auth_harness.create_user(fullname="Synthetic Hidden Coordinator")
    inactive_id, inactive_email, _ = auth_harness.create_user(
        active=0,
        fullname="Synthetic Inactive Coordinator",
    )
    unapproved_id, unapproved_email, _ = auth_harness.create_user(
        is_approved=0,
        fullname="Synthetic Unapproved Coordinator",
    )
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert(),
            [
                {
                    "user_id": eligible_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": json.dumps({"phone": "public", "avatar": "private"}),
                },
                {
                    "user_id": malformed_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": "not-json",
                },
                {
                    "user_id": hidden_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 0,
                    "field_visibility": None,
                },
            ],
        )

    suffix = uuid.uuid4().hex[:8]
    zone_specs = (
        (eligible_id, (f"Synthetic Z Welfare City {suffix}", f"Synthetic A Welfare City {suffix}")),
        (malformed_id, (f"Synthetic Malformed City {suffix}",)),
        (default_id, (f"Synthetic Default Visibility City {suffix}",)),
        (hidden_id, (f"Synthetic Hidden City {suffix}",)),
        (inactive_id, (f"Synthetic Inactive City {suffix}",)),
        (unapproved_id, (f"Synthetic Unapproved City {suffix}",)),
        (None, (f"Synthetic Unassigned City {suffix}",)),
    )
    zone_ids: list[int] = []
    zone_city_ids: list[dict[str, int]] = []
    for coordinator_id, cities in zone_specs:
        zone_id, city_ids = auth_harness.create_zone(
            coordinator_user_id=coordinator_id,
            cities=cities,
        )
        zone_ids.append(zone_id)
        zone_city_ids.append(city_ids)

    get_response = auth_harness.client.get("/api/get_zones")
    post_response = auth_harness.client.post(
        "/api/get_zones",
        headers={"X-API-Key": "legacy-shared-key-is-ignored"},
    )

    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json()
    tracked = [zone for zone in get_response.json()["data"] if zone["zone_id"] in zone_ids]
    assert [zone["zone_id"] for zone in tracked] == zone_ids
    assert tracked[0]["cities"] == [
        {
            "city_id": zone_city_ids[0][zone_specs[0][1][1]],
            "city": zone_specs[0][1][1],
        },
        {
            "city_id": zone_city_ids[0][zone_specs[0][1][0]],
            "city": zone_specs[0][1][0],
        },
    ]
    assert tracked[0]["coordinator"] == {
        "user_id": eligible_id,
        "name": "Synthetic Visible Coordinator",
        "first_name": "Synthetic",
        "last_name": "Member",
        "phone": "+2348000000000",
    }
    assert tracked[1]["coordinator"] == {
        "user_id": malformed_id,
        "name": "Synthetic Malformed Coordinator",
        "first_name": "Synthetic",
        "last_name": "Member",
    }
    assert tracked[2]["coordinator"] == {
        "user_id": default_id,
        "name": "Synthetic Default Visibility Coordinator",
        "first_name": "Synthetic",
        "last_name": "Member",
        "phone": "+2348000000000",
        "avatar": "https://alumni.example.test/uploads/profiles/synthetic.png",
    }
    assert all(zone["coordinator"] is None for zone in tracked[3:])
    assert all(
        "email" not in coordinator
        for zone in get_response.json()["data"]
        if (coordinator := zone.get("coordinator")) is not None
    )
    for private_email in (
        eligible_email,
        malformed_email,
        default_email,
        hidden_email,
        inactive_email,
        unapproved_email,
    ):
        assert private_email not in get_response.text


@pytest.mark.integration
def test_zone_member_roster_uses_current_auth_and_membership_privacy(
    auth_harness: AuthHarness,
) -> None:
    """Zone-name lookup works and excludes hidden, private-city, or ineligible records."""
    suffix = uuid.uuid4().hex[:8]
    city = f"Synthetic Roster City {suffix}"
    actor_id, actor_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster A Actor",
    )
    coordinator_id, coordinator_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster B Coordinator",
    )
    private_contact_id, private_contact_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster C Private Contact",
    )
    default_id, default_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster D Default Visibility",
    )
    private_city_id, private_city_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster E Private City",
    )
    malformed_id, malformed_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster F Malformed Visibility",
    )
    hidden_id, hidden_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster G Globally Hidden",
    )
    inactive_id, inactive_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster H Inactive",
        active=0,
    )
    unapproved_id, unapproved_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster I Unapproved",
        is_approved=0,
    )
    unverified_id, unverified_email, _ = auth_harness.create_user(
        city=city,
        fullname="Roster J Unverified",
        email_verified=0,
    )
    zone_id, _ = auth_harness.create_zone(
        coordinator_user_id=coordinator_id,
        cities=(city,),
    )
    zone_name = auth_harness.zone_names[-1]
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert(),
            [
                {
                    "user_id": actor_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 0,
                    "field_visibility": "not-json",
                },
                {
                    "user_id": coordinator_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": json.dumps(
                        {"city": "public", "phone": "public", "avatar": "private"}
                    ),
                },
                {
                    "user_id": private_contact_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": json.dumps({"city": True, "phone": False, "avatar": False}),
                },
                {
                    "user_id": private_city_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": json.dumps({"city": False}),
                },
                {
                    "user_id": malformed_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 1,
                    "field_visibility": "not-json",
                },
                {
                    "user_id": hidden_id,
                    "instagram": "",
                    "tiktok": "",
                    "updated_at": now,
                    "is_visible": 0,
                    "field_visibility": None,
                },
            ],
        )

    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    by_name = auth_harness.client.get(
        "/api/get_users_by_zone",
        headers=headers,
        params={"zone": f"  {zone_name.upper()}  "},
    )
    first_page = auth_harness.client.post(
        "/api/get_users_by_zone",
        headers=headers,
        json={"zone_id": zone_id, "zone": "ignored-name", "page": 1, "limit": 2},
    )
    second_page = auth_harness.client.post(
        "/api/get_users_by_zone",
        headers=headers,
        data={"zone_id": str(zone_id), "page": "2", "limit": "2"},
    )

    assert by_name.status_code == first_page.status_code == second_page.status_code == 200
    body = by_name.json()
    assert body["zone"] == {
        "zone_id": zone_id,
        "zone": zone_name,
        "coordinator": {
            "user_id": coordinator_id,
            "name": "Roster B Coordinator",
            "first_name": "Synthetic",
            "last_name": "Member",
            "phone": "+2348000000000",
        },
    }
    assert body["count"] == body["total"] == 4
    assert body["has_more"] is False
    assert [user["user_id"] for user in body["users"]] == [
        actor_id,
        coordinator_id,
        private_contact_id,
        default_id,
    ]
    assert body["users"][0]["phone"] == "+2348000000000"
    assert body["users"][0]["avatar"].endswith("/uploads/profiles/synthetic.png")
    assert "phone" not in body["users"][2]
    assert "avatar" not in body["users"][2]
    assert body["users"][3]["phone"] == "+2348000000000"
    assert first_page.json()["total"] == second_page.json()["total"] == 4
    assert first_page.json()["has_more"] is True
    assert second_page.json()["has_more"] is False
    assert [user["user_id"] for user in first_page.json()["users"]] == [
        actor_id,
        coordinator_id,
    ]
    assert [user["user_id"] for user in second_page.json()["users"]] == [
        private_contact_id,
        default_id,
    ]

    excluded_ids = {
        private_city_id,
        malformed_id,
        hidden_id,
        inactive_id,
        unapproved_id,
        unverified_id,
    }
    assert excluded_ids.isdisjoint(user["user_id"] for user in body["users"])
    for private_email in (
        actor_email,
        coordinator_email,
        private_contact_email,
        default_email,
        private_city_email,
        malformed_email,
        hidden_email,
        inactive_email,
        unapproved_email,
        unverified_email,
    ):
        assert private_email not in by_name.text

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    stale_actor = auth_harness.client.get(
        "/api/get_users_by_zone",
        headers=headers,
        params={"zone_id": zone_id},
    )
    assert stale_actor.status_code == 401
    assert stale_actor.json()["code"] == "zone_members_actor_unavailable"


@pytest.mark.integration
def test_my_zone_is_self_scoped_deterministic_and_privacy_aware(
    auth_harness: AuthHarness,
) -> None:
    """Self lookup chooses the oldest city mapping and never exposes coordinator email."""
    suffix = uuid.uuid4().hex[:8]
    city = f"Synthetic Self Zone City {suffix}"
    coordinator_id, coordinator_email, _ = auth_harness.create_user(
        fullname="Synthetic Self Zone Coordinator"
    )
    actor_id, actor_email, _ = auth_harness.create_user(city=f"  {city.upper()}  ")
    no_city_id, no_city_email, _ = auth_harness.create_user(city="")
    orphan_id, orphan_email, _ = auth_harness.create_user(city=f"Synthetic Unmapped City {suffix}")
    inactive_id, inactive_email, _ = auth_harness.create_user(city=city, active=0)
    first_zone_id, _ = auth_harness.create_zone(
        coordinator_user_id=coordinator_id,
        cities=(city,),
    )
    first_zone_name = auth_harness.zone_names[-1]
    second_zone_id, _ = auth_harness.create_zone(cities=(city,))
    assert first_zone_id < second_zone_id
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=coordinator_id,
                instagram="",
                tiktok="",
                updated_at=now,
                is_visible=1,
                field_visibility=json.dumps({"phone": False, "avatar": True}),
            )
        )

    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    get_response = auth_harness.client.get("/api/get_my_zone", headers=headers)
    post_response = auth_harness.client.post(
        "/api/get_my_zone",
        headers=headers,
        json={"user_id": no_city_id, "token": "ignored"},
    )

    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json()
    assert get_response.json() == {
        "status": 200,
        "message": "Zone retrieved successfully",
        "city": city.upper(),
        "zone": {
            "zone_id": first_zone_id,
            "zone": first_zone_name,
            "coordinator": {
                "user_id": coordinator_id,
                "name": "Synthetic Self Zone Coordinator",
                "first_name": "Synthetic",
                "last_name": "Member",
                "avatar": "https://alumni.example.test/uploads/profiles/synthetic.png",
            },
        },
    }
    assert coordinator_email not in get_response.text

    no_city = auth_harness.client.get(
        "/api/get_my_zone",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, no_city_id, no_city_email)}"
        },
    )
    orphan = auth_harness.client.get(
        "/api/get_my_zone",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, orphan_id, orphan_email)}"
        },
    )
    inactive = auth_harness.client.get(
        "/api/get_my_zone",
        headers={
            "Authorization": (
                f"Bearer {_access_token_for(auth_harness, inactive_id, inactive_email)}"
            )
        },
    )

    assert no_city.status_code == orphan.status_code == 200
    assert no_city.json() == {
        "status": 200,
        "message": "Zone not yet available",
        "city": None,
        "zone": "Not Yet Available",
    }
    assert orphan.json() == {
        "status": 200,
        "message": "Zone not yet available",
        "city": f"Synthetic Unmapped City {suffix}",
        "zone": "Not Yet Available",
    }
    assert inactive.status_code == 401
    assert inactive.json()["code"] == "my_zone_actor_unavailable"


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


@pytest.mark.integration
def test_member_approval_uses_database_role_and_returns_allowlisted_state(
    auth_harness: AuthHarness,
) -> None:
    """A current manager can approve a verified member despite a stale JWT role claim."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user(is_approved=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == target_id).values(profile_status="No", active=1)
        )
    token = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")

    response = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": str(target_id), "action": " APPROVE ", "token": "ignored"},
    )
    body = response.json()

    assert response.status_code == 200
    assert body["message"] == "User account approved successfully"
    assert body["user"] == {
        "id": target_id,
        "fullname": "Synthetic Member",
        "email": target_email,
        "user_role": "alumni",
        "active": True,
        "is_approved": True,
        "profile_status": "active",
    }
    assert {
        "password",
        "reset_token",
        "verify_token",
        "userAccessCode",
        "device_token",
    }.isdisjoint(body["user"])
    assert auth_harness.mailer.account_status_deliveries == [
        (target_email, "Synthetic Member", "approve", None)
    ]


@pytest.mark.integration
def test_member_rejection_commits_before_best_effort_notification(
    auth_harness: AuthHarness,
) -> None:
    """A provider outage cannot roll back a valid rejection transaction."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, _target_email, _ = auth_harness.create_user(is_approved=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == target_id).values(profile_status="No", active=1)
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)
    auth_harness.mailer.fail = True

    response = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={
            "user_id": target_id,
            "action": "reject",
            "reject_reason": "  Synthetic review reason  ",
        },
    )
    auth_harness.mailer.fail = False

    assert response.status_code == 200
    assert response.json()["user"]["profile_status"] == "rejected"
    with auth_harness.engine.connect() as connection:
        state = connection.execute(
            select(Users.active, Users.is_approved, Users.profile_status).where(
                Users.id == target_id
            )
        ).one()
    assert tuple(state) == (0, 0, "rejected")


@pytest.mark.integration
def test_member_approval_blocks_forged_claims_self_action_and_role_escalation(
    auth_harness: AuthHarness,
) -> None:
    """Current database facts and hierarchy rules fail closed for privileged actions."""
    actor_id, actor_email, _ = auth_harness.create_user()
    member_id, _member_email, _ = auth_harness.create_user(is_approved=0)
    admin_id, _admin_email, _ = auth_harness.create_user(is_approved=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((member_id, admin_id)))
            .values(profile_status="No", active=1)
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == admin_id).values(user_role="admin")
        )
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="superadmin")

    denied = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": member_id, "action": "approve"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "approval_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
    stale_token = _access_token_for(auth_harness, actor_id, actor_email)
    escalated = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {stale_token}"},
        json={"user_id": admin_id, "action": "approve"},
    )
    self_action = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {stale_token}"},
        json={"user_id": actor_id, "action": "reject"},
    )
    assert escalated.status_code == 403
    assert escalated.json()["code"] == "approval_role_forbidden"
    assert self_action.status_code == 403
    assert self_action.json()["code"] == "approval_self_forbidden"


@pytest.mark.integration
def test_member_approval_rejects_unverified_and_invalid_existing_states(
    auth_harness: AuthHarness,
) -> None:
    """Approval decisions cannot bypass verification or misuse the pending workflow."""
    actor_id, actor_email, _ = auth_harness.create_user()
    unverified_id, _email, _ = auth_harness.create_user(
        active=1,
        email_verified=0,
        is_approved=0,
    )
    approved_id, _approved_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)

    missing = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": 2_000_000_000, "action": "approve"},
    )
    unverified = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": unverified_id, "action": "approve"},
    )
    duplicate = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": approved_id, "action": "approve"},
    )
    wrong_workflow = auth_harness.client.post(
        "/api/approve_user",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": approved_id, "action": "reject"},
    )
    assert missing.status_code == 404
    assert missing.json()["code"] == "approval_target_not_found"
    assert unverified.status_code == 409
    assert unverified.json()["code"] == "approval_email_unverified"
    assert duplicate.status_code == 409
    assert duplicate.json()["code"] == "approval_already_approved"
    assert wrong_workflow.status_code == 409
    assert wrong_workflow.json()["code"] == "approval_reject_active_forbidden"


@pytest.mark.integration
def test_member_approval_transaction_rolls_back_on_repository_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failure after the update statement leaves the pending member unchanged."""
    actor_id, _actor_email, _ = auth_harness.create_user()
    target_id, _target_email, _ = auth_harness.create_user(is_approved=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == target_id).values(profile_status="No", active=1)
        )

    original = MemberRepository.update_approval_state

    def fail_after_update(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original(self, *args, **kwargs)
        raise RuntimeError("synthetic post-update failure")

    monkeypatch.setattr(MemberRepository, "update_approval_state", fail_after_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic post-update failure"),
    ):
        MemberService(session, auth_harness.settings, auth_harness.mailer).decide_member_approval(
            actor_id,
            target_id,
            "approve",
            None,
        )

    with auth_harness.engine.connect() as connection:
        state = connection.execute(
            select(Users.active, Users.is_approved, Users.profile_status).where(
                Users.id == target_id
            )
        ).one()
    assert tuple(state) == (1, 0, "No")


@pytest.mark.integration
def test_member_self_deactivation_revokes_sessions_and_returns_allowlisted_state(
    auth_harness: AuthHarness,
) -> None:
    """A member can deactivate only self and all refresh sessions are revoked atomically."""
    user_id, email, _ = auth_harness.create_user()
    login = auth_harness.client.post(
        "/api/login",
        json={"identity": email, "password": PASSPHRASE},
    )
    token = login.json()["access_token"]
    assert any(row["revoked"] == 0 for row in auth_harness.refresh_rows(user_id))

    response = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"action": " DEACTIVATE ", "token": "ignored"},
    )

    assert response.status_code == 200
    assert response.json()["user"] == {
        "id": user_id,
        "fullname": "Synthetic Member",
        "email": email,
        "phone": "+2348000000000",
        "user_role": "alumni",
        "active": False,
        "profile_status": "active",
    }
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(user_id))
    assert auth_harness.mailer.account_activity_deliveries == [
        (email, "Synthetic Member", "deactivate", "self")
    ]


@pytest.mark.integration
def test_account_management_uses_database_role_and_downward_hierarchy(
    auth_harness: AuthHarness,
) -> None:
    """Forged claims cannot grant management, while a current manager can deactivate a member."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="superadmin")

    denied = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": target_id, "action": "deactivate"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "account_role_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
    stale_token = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    allowed = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {stale_token}"},
        json={"user_id": str(target_id), "action": "deactivate"},
    )
    assert allowed.status_code == 200
    assert allowed.json()["user"]["active"] is False
    assert auth_harness.mailer.account_activity_deliveries == [
        (target_email, "Synthetic Member", "deactivate", "administrator")
    ]


@pytest.mark.integration
def test_account_activation_requires_approved_verified_inactive_member(
    auth_harness: AuthHarness,
) -> None:
    """Activation cannot bypass approval/email gates or become a silent no-op."""
    actor_id, actor_email, _ = auth_harness.create_user()
    valid_id, _valid_email, _ = auth_harness.create_user(active=0)
    unapproved_id, _email, _ = auth_harness.create_user(active=0, is_approved=0)
    unverified_id, _email, _ = auth_harness.create_user(active=0, email_verified=0)
    active_id, _email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)

    valid = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": valid_id, "action": "activate"},
    )
    unapproved = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": unapproved_id, "action": "activate"},
    )
    unverified = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": unverified_id, "action": "activate"},
    )
    duplicate = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": active_id, "action": "activate"},
    )

    assert valid.status_code == 200
    assert valid.json()["user"]["active"] is True
    assert unapproved.status_code == unverified.status_code == 409
    assert unapproved.json()["code"] == "account_activation_state_invalid"
    assert unverified.json()["code"] == "account_activation_state_invalid"
    assert duplicate.status_code == 409
    assert duplicate.json()["code"] == "account_state_unchanged"


@pytest.mark.integration
def test_account_management_blocks_self_activation_hierarchy_and_missing_target(
    auth_harness: AuthHarness,
) -> None:
    """Self escalation, peer changes, unknown roles, and absent accounts fail closed."""
    actor_id, actor_email, _ = auth_harness.create_user()
    peer_id, _email, _ = auth_harness.create_user()
    unknown_id, _email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == peer_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == unknown_id).values(user_role="mystery admin")
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)
    headers = {"Authorization": f"Bearer {token}"}

    self_activate = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"action": "activate"},
    )
    peer = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": peer_id, "action": "deactivate"},
    )
    unknown = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": unknown_id, "action": "deactivate"},
    )
    missing = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": 2_000_000_000, "action": "deactivate"},
    )

    assert self_activate.status_code == 403
    assert self_activate.json()["code"] == "account_self_activate_forbidden"
    assert peer.status_code == unknown.status_code == 403
    assert peer.json()["code"] == unknown.json()["code"] == "account_role_forbidden"
    assert missing.status_code == 404
    assert missing.json()["code"] == "account_target_not_found"


@pytest.mark.integration
def test_account_deactivation_commits_before_mail_failure_and_rolls_back_on_sql_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Mail is post-commit, while repository failures roll back state and token revocation."""
    actor_id, actor_email, _ = auth_harness.create_user()
    committed_id, _email, _ = auth_harness.create_user()
    rollback_id, rollback_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)
    rollback_login = auth_harness.client.post(
        "/api/login",
        json={"identity": rollback_email, "password": PASSPHRASE},
    )
    assert rollback_login.status_code == 200
    assert any(row["revoked"] == 0 for row in auth_harness.refresh_rows(rollback_id))
    auth_harness.mailer.fail = True
    committed = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": committed_id, "action": "deactivate"},
    )
    auth_harness.mailer.fail = False
    assert committed.status_code == 200

    original = MemberRepository.update_active_state

    def fail_after_update(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original(self, *args, **kwargs)
        raise RuntimeError("synthetic account update failure")

    monkeypatch.setattr(MemberRepository, "update_active_state", fail_after_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic account update failure"),
    ):
        MemberService(session, auth_harness.settings, auth_harness.mailer).manage_member_account(
            actor_id,
            rollback_id,
            "deactivate",
            None,
        )

    with auth_harness.engine.connect() as connection:
        committed_active = connection.scalar(select(Users.active).where(Users.id == committed_id))
        rollback_active = connection.scalar(select(Users.active).where(Users.id == rollback_id))
    assert committed_active == 0
    assert rollback_active == 1
    assert any(row["revoked"] == 0 for row in auth_harness.refresh_rows(rollback_id))
    assert auth_harness.mailer.account_activity_deliveries == []


@pytest.mark.integration
def test_role_change_uses_current_superadmin_facts_and_revokes_sessions(
    auth_harness: AuthHarness,
) -> None:
    """A current super administrator can apply one reviewed role despite a stale token."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    target_login = auth_harness.client.post(
        "/api/login",
        json={"identity": target_email, "password": PASSPHRASE},
    )
    assert target_login.status_code == 200
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="super admin")
        )
    stale_token = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")

    response = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {stale_token}"},
        json={"user_id": str(target_id), "user_role": " Content_Administrator "},
    )

    assert response.status_code == 200
    assert response.json() == {
        "status": 200,
        "message": "User role updated to content admin",
        "user": {
            "id": target_id,
            "fullname": "Synthetic Member",
            "email": target_email,
            "phone": "+2348000000000",
            "user_role": "content admin",
            "active": True,
            "profile_status": "active",
        },
    }
    assert all(row["revoked"] == 1 for row in auth_harness.refresh_rows(target_id))
    assert auth_harness.mailer.account_activity_deliveries == []


@pytest.mark.integration
def test_role_change_blocks_forged_claims_category_admins_and_self_change(
    auth_harness: AuthHarness,
) -> None:
    """JWT text, category-admin access, and self-targeting cannot grant roles."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, _target_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")

    forged_denial = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": target_id, "user_role": "content admin"},
    )
    assert forged_denial.status_code == 403
    assert forged_denial.json()["code"] == "account_role_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="approval admin")
        )
    category_denial = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": target_id, "user_role": "content admin"},
    )
    assert category_denial.status_code == 403
    assert category_denial.json()["code"] == "account_role_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="super admin")
        )
    self_denial = auth_harness.client.post(
        "/api/manage_user_account",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": actor_id, "user_role": "alumni"},
    )
    assert self_denial.status_code == 403
    assert self_denial.json()["code"] == "account_role_self_forbidden"


@pytest.mark.integration
def test_role_change_enforces_hierarchy_state_and_duplicate_guards(
    auth_harness: AuthHarness,
) -> None:
    """Administrative grants cannot bypass hierarchy or account-readiness checks."""
    actor_id, actor_email, _ = auth_harness.create_user()
    member_id, _member_email, _ = auth_harness.create_user()
    unverified_id, _email, _ = auth_harness.create_user(email_verified=0)
    category_id, _category_email, _ = auth_harness.create_user()
    unknown_id, _unknown_email, _ = auth_harness.create_user()
    super_peer_id, _super_peer_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == category_id).values(user_role="content admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == unknown_id).values(user_role="auditor admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == super_peer_id).values(user_role="super admin")
        )
    token = _access_token_for(auth_harness, actor_id, actor_email)
    headers = {"Authorization": f"Bearer {token}"}

    allowed = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": member_id, "user_role": "approval admin"},
    )
    unverified_super_grant = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": unverified_id, "user_role": "super admin"},
    )
    peer_denial = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": category_id, "user_role": "alumni"},
    )

    assert allowed.status_code == 200
    assert allowed.json()["user"]["user_role"] == "approval admin"
    assert unverified_super_grant.status_code == 409
    assert unverified_super_grant.json()["code"] == "account_role_state_invalid"
    assert peer_denial.status_code == 403
    assert peer_denial.json()["code"] == "account_role_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="super admin")
        )
    unverified = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": unverified_id, "user_role": "event admin"},
    )
    duplicate = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": category_id, "user_role": "content administrator"},
    )
    unknown_target = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": unknown_id, "user_role": "alumni"},
    )
    peer_demotion = auth_harness.client.post(
        "/api/manage_user_account",
        headers=headers,
        json={"user_id": super_peer_id, "user_role": "alumni"},
    )
    assert unverified.status_code == duplicate.status_code == 409
    assert unverified.json()["code"] == "account_role_state_invalid"
    assert duplicate.json()["code"] == "account_role_unchanged"
    assert unknown_target.status_code == 403
    assert unknown_target.json()["code"] == "account_role_forbidden"
    assert peer_demotion.status_code == 200
    assert peer_demotion.json()["user"]["user_role"] == "alumni"


@pytest.mark.integration
def test_role_change_rolls_back_role_and_refresh_revocation_on_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Role persistence and refresh revocation share one rollback boundary."""
    actor_id, _actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="super admin")
        )
    login = auth_harness.client.post(
        "/api/login",
        json={"identity": target_email, "password": PASSPHRASE},
    )
    assert login.status_code == 200
    original = MemberRepository.update_user_role

    def fail_after_update(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original(self, *args, **kwargs)
        self.revoke_refresh_tokens(target_id)
        raise RuntimeError("synthetic role update failure")

    monkeypatch.setattr(MemberRepository, "update_user_role", fail_after_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic role update failure"),
    ):
        MemberService(session, auth_harness.settings, auth_harness.mailer).manage_member_account(
            actor_id,
            target_id,
            None,
            "event admin",
        )

    with auth_harness.engine.connect() as connection:
        role = connection.scalar(select(Users.user_role).where(Users.id == target_id))
    assert role == "alumni"
    assert any(row["revoked"] == 0 for row in auth_harness.refresh_rows(target_id))


@pytest.mark.integration
def test_profile_visibility_self_defaults_create_and_merge(auth_harness: AuthHarness) -> None:
    """Self-service visibility creates a missing profile and preserves unspecified fields."""
    user_id, email, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, user_id, email)
    headers = {"Authorization": f"Bearer {token}"}

    defaults = auth_harness.client.post(
        "/api/get_profile_visibility",
        headers=headers,
        json={},
    )
    assert defaults.status_code == 200
    assert defaults.json()["user_id"] == user_id
    assert defaults.json()["is_visible"] is True
    assert set(defaults.json()["field_visibility"].values()) == {"public"}

    created = auth_harness.client.post(
        "/api/update_profile_visibility",
        headers=headers,
        json={
            "is_visible": False,
            "phone_visible": False,
            "socials_visible": False,
            "token": "ignored",
        },
    )
    assert created.status_code == 200
    assert created.json()["is_visible"] is False
    assert created.json()["field_visibility"]["phone"] == "private"
    assert created.json()["field_visibility"]["socials"] == "private"
    assert created.json()["field_visibility"]["avatar"] == "public"

    merged = auth_harness.client.post(
        "/api/update_profile_visibility",
        headers=headers,
        json={"avatar_visible": False},
    )
    assert merged.status_code == 200
    assert merged.json()["is_visible"] is False
    assert merged.json()["field_visibility"]["avatar"] == "private"
    assert merged.json()["field_visibility"]["phone"] == "private"
    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(UserProfiles.instagram, UserProfiles.tiktok, UserProfiles.field_visibility)
            .where(UserProfiles.user_id == user_id)
            .limit(1)
        ).one()
    assert stored[0:2] == ("", "")
    assert stored[2] == '{"avatar":false,"phone":false,"socials":false}'


@pytest.mark.integration
def test_profile_visibility_cross_user_access_uses_current_database_hierarchy(
    auth_harness: AuthHarness,
) -> None:
    """JWT role claims cannot grant cross-user access, while a current manager can act down."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, _target_email, _ = auth_harness.create_user()
    peer_id, _peer_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="superadmin")

    denied = auth_harness.client.post(
        "/api/update_profile_visibility",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": target_id, "phone_visible": False},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "visibility_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == peer_id).values(user_role="manager")
        )
    stale = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    allowed = auth_harness.client.post(
        "/api/update_profile_visibility",
        headers={"Authorization": f"Bearer {stale}"},
        json={"user_id": target_id, "phone_visible": False},
    )
    peer_denied = auth_harness.client.post(
        "/api/get_profile_visibility",
        headers={"Authorization": f"Bearer {stale}"},
        json={"user_id": peer_id},
    )
    assert allowed.status_code == 200
    assert allowed.json()["field_visibility"]["phone"] == "private"
    assert peer_denied.status_code == 403
    assert peer_denied.json()["code"] == "visibility_forbidden"


@pytest.mark.integration
def test_profile_visibility_fails_closed_for_bad_json_and_account_state(
    auth_harness: AuthHarness,
) -> None:
    """Malformed stored settings become private and missing/inactive principals fail closed."""
    user_id, email, _ = auth_harness.create_user()
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=user_id,
                instagram="",
                tiktok="",
                updated_at=now,
                field_visibility="not-json",
            )
        )
    token = _access_token_for(auth_harness, user_id, email)
    headers = {"Authorization": f"Bearer {token}"}
    response = auth_harness.client.post(
        "/api/get_profile_visibility",
        headers=headers,
        json={},
    )
    assert response.status_code == 200
    assert set(response.json()["field_visibility"].values()) == {"private"}

    missing = auth_harness.client.post(
        "/api/get_profile_visibility",
        headers=headers,
        json={"user_id": 2_000_000_000},
    )
    assert missing.status_code == 403
    assert missing.json()["code"] == "visibility_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == user_id).values(active=0))
    inactive = auth_harness.client.post(
        "/api/get_profile_visibility",
        headers=headers,
        json={},
    )
    assert inactive.status_code == 401
    assert inactive.json()["code"] == "visibility_actor_unavailable"


@pytest.mark.integration
def test_profile_visibility_update_rolls_back_on_repository_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failure after the visibility write leaves the prior profile unchanged."""
    user_id, _email, _ = auth_harness.create_user()
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=user_id,
                instagram="",
                tiktok="",
                updated_at=now,
                is_visible=1,
                field_visibility='{"phone":true}',
            )
        )

    original = MemberRepository.upsert_profile_visibility

    def fail_after_update(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original(self, *args, **kwargs)
        raise RuntimeError("synthetic visibility update failure")

    monkeypatch.setattr(MemberRepository, "upsert_profile_visibility", fail_after_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic visibility update failure"),
    ):
        MemberService(session, auth_harness.settings).update_profile_visibility(
            user_id,
            None,
            False,
            {"phone": False},
        )

    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(UserProfiles.is_visible, UserProfiles.field_visibility).where(
                UserProfiles.user_id == user_id
            )
        ).one()
    assert tuple(stored) == (1, '{"phone":true}')


@pytest.mark.integration
def test_member_directory_enforces_global_and_field_visibility(
    auth_harness: AuthHarness,
) -> None:
    """Other members never receive hidden profiles or private field values."""
    actor_id, actor_email, _ = auth_harness.create_user()
    private_id, private_email, _ = auth_harness.create_user(city="Private City")
    hidden_id, hidden_email, _ = auth_harness.create_user(city="Hidden City")
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == private_id)
            .values(
                fullname="Private Directory Member",
                phone="+2348111111111",
                alternative_phone="+2348222222222",
                avatar="uploads/profiles/private.png",
                birth_date=date(1990, 1, 2),
                residential_address="Private Street",
                area="Private Area",
                state="Private State",
                employment_status="employed",
                occupation="Engineer",
                industry_sector="Technology",
                years_of_experience="10",
                is_volunteer=1,
            )
        )
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=private_id,
                instagram="private-instagram",
                tiktok="private-tiktok",
                updated_at=now,
                linkedin="private-linkedin",
                facebook="private-facebook",
                current_company="Private Company",
                current_position="Private Position",
                city="Private Profile City",
                country="Nigeria",
                is_visible=1,
                field_visibility=json.dumps(
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
                        "socials": False,
                    }
                ),
            )
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == hidden_id)
            .values(fullname="Globally Hidden Member", phone="+2348333333333")
        )
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=hidden_id,
                instagram="hidden-instagram",
                tiktok="hidden-tiktok",
                updated_at=now,
                is_visible=0,
                field_visibility='{"phone":false}',
            )
        )

    actor_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"
    }
    private_response = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=actor_headers,
        json={"action_type": "approved", "user_id": private_id},
    )
    hidden_response = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=actor_headers,
        json={"action_type": "approved", "user_id": hidden_id},
    )
    owner_response = auth_harness.client.post(
        "/api/get_users_by_action",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, hidden_id, hidden_email)}"
        },
        json={"action_type": "approved", "user_id": hidden_id},
    )

    assert private_response.status_code == hidden_response.status_code == 200
    private_user = private_response.json()["users"][0]
    assert private_response.json()["count"] == private_response.json()["total"] == 1
    assert private_user["fullname"] == "Private Directory Member"
    for private_field in (
        "avatar",
        "phone",
        "alternative_phone",
        "birth_date",
        "residential_address",
        "area",
        "city",
        "state",
        "employment_status",
        "occupation",
        "industry_sector",
        "years_of_experience",
        "is_volunteer",
    ):
        assert private_field not in private_user
    assert "linkedin" not in private_user["profile"]
    assert "current_company" not in private_user["profile"]
    assert "email" not in private_user
    assert "password" not in private_user
    assert "ip_address" not in private_user
    assert set(private_user["profile"]["field_visibility"].values()) == {
        "public",
        "private",
    }
    assert hidden_response.json()["users"] == []
    assert hidden_response.json()["total"] == 0
    assert owner_response.status_code == 200
    assert owner_response.json()["users"][0]["phone"] == "+2348333333333"
    assert owner_response.json()["users"][0]["profile"]["instagram"] == "hidden-instagram"
    assert private_email not in private_response.text


@pytest.mark.integration
def test_member_directory_fails_closed_on_bad_json_and_defaults_missing_profile_public(
    auth_harness: AuthHarness,
) -> None:
    """Corrupt legacy visibility hides controlled fields while absent state stays compatible."""
    actor_id, actor_email, _ = auth_harness.create_user()
    corrupt_id, _corrupt_email, _ = auth_harness.create_user(city="Corrupt City")
    default_id, _default_email, _ = auth_harness.create_user(city="Default City")
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=corrupt_id,
                instagram="corrupt-instagram",
                tiktok="corrupt-tiktok",
                updated_at=now,
                current_position="Corrupt Position",
                is_visible=1,
                field_visibility="not-json",
            )
        )
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    corrupt = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"user_id": corrupt_id},
    )
    defaults = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"user_id": default_id},
    )

    corrupt_user = corrupt.json()["users"][0]
    assert corrupt.status_code == defaults.status_code == 200
    assert "avatar" not in corrupt_user
    assert "phone" not in corrupt_user
    assert "city" not in corrupt_user
    assert "current_position" not in corrupt_user["profile"]
    assert "instagram" not in corrupt_user["profile"]
    assert set(corrupt_user["profile"]["field_visibility"].values()) == {"private"}
    default_user = defaults.json()["users"][0]
    assert default_user["phone"] == "+2348000000000"
    assert default_user["city"] == "Default City"
    assert default_user["avatar"] == ("https://alumni.example.test/uploads/profiles/synthetic.png")
    assert set(default_user["profile"]["field_visibility"].values()) == {"public"}


@pytest.mark.integration
def test_administrative_member_list_uses_database_role_and_downward_projection(
    auth_harness: AuthHarness,
) -> None:
    """Forged claims cannot list accounts and current managers see only lower roles."""
    actor_id, actor_email, _ = auth_harness.create_user()
    member_id, member_email, _ = auth_harness.create_user()
    manager_id, manager_email, _ = auth_harness.create_user()
    peer_id, _peer_email, _ = auth_harness.create_user()
    unknown_id, _unknown_email, _ = auth_harness.create_user()
    unverified_id, _unverified_email, _ = auth_harness.create_user(email_verified=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == member_id).values(user_role="alumni")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == manager_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == peer_id).values(user_role="admin")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == unknown_id).values(user_role="event admin")
        )

    stale_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"
    }
    first_page = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=stale_headers,
        json={"action_type": "all_users", "page": 1, "limit": 1},
    )
    second_page = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=stale_headers,
        json={"action_type": "all users", "page": 2, "limit": 1},
    )
    forged = auth_harness.client.post(
        "/api/get_users_by_action",
        headers={
            "Authorization": "Bearer "
            + _access_token_for(
                auth_harness,
                member_id,
                member_email,
                user_role="superadmin",
            )
        },
        json={"action_type": "all_users"},
    )

    assert first_page.status_code == second_page.status_code == 200
    assert first_page.json()["total"] == 2
    assert first_page.json()["count"] == 1
    assert first_page.json()["has_more"] is True
    listed_ids = {
        first_page.json()["users"][0]["id"],
        second_page.json()["users"][0]["id"],
    }
    assert listed_ids == {member_id, manager_id}
    admin_user = first_page.json()["users"][0]
    assert set(admin_user) == {
        "id",
        "fullname",
        "email",
        "phone",
        "user_role",
        "active",
        "profile_status",
        "is_approved",
        "email_verified",
    }
    assert admin_user["email"] in {member_email, manager_email}
    assert peer_id not in listed_ids
    assert unknown_id not in listed_ids
    assert unverified_id not in listed_ids
    assert forged.status_code == 403
    assert forged.json()["code"] == "members_admin_forbidden"


@pytest.mark.integration
def test_pending_member_list_and_bounded_filters(auth_harness: AuthHarness) -> None:
    """Pending status, year/search filters, and current actor state are enforced in SQL."""
    actor_id, actor_email, _ = auth_harness.create_user()
    pending_id, pending_email, _ = auth_harness.create_user(is_approved=0)
    approved_id, _approved_email, _ = auth_harness.create_user(is_approved=1)
    inactive_pending_id, _inactive_email, _ = auth_harness.create_user(
        active=0,
        is_approved=0,
    )
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == pending_id)
            .values(fullname="Pending Searchable Member", graduation_year=1999)
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == approved_id)
            .values(fullname="Pending Searchable Approved", graduation_year=1999)
        )

    token = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    headers = {"Authorization": f"Bearer {token}"}
    pending = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={
            "action_type": "pending Approval",
            "search": "searchable",
            "year": 1999,
        },
    )
    assert pending.status_code == 200
    assert pending.json()["total"] == pending.json()["count"] == 1
    assert pending.json()["users"][0]["id"] == pending_id
    assert pending.json()["users"][0]["email"] == pending_email
    assert approved_id != pending.json()["users"][0]["id"]
    assert inactive_pending_id != pending.json()["users"][0]["id"]

    approved = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"action_type": "approved", "user_id": approved_id},
    )
    pending_in_directory = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"action_type": "approved", "user_id": pending_id},
    )
    inactive_in_directory = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"action_type": "approved", "user_id": inactive_pending_id},
    )
    assert approved.json()["total"] == 1
    assert pending_in_directory.json()["total"] == 0
    assert inactive_in_directory.json()["total"] == 0

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive_actor = auth_harness.client.post(
        "/api/get_users_by_action",
        headers=headers,
        json={"action_type": "approved"},
    )
    assert inactive_actor.status_code == 401
    assert inactive_actor.json()["code"] == "members_actor_unavailable"


@pytest.mark.integration
def test_alumni_stats_preserve_reviewed_counts_and_recheck_current_actor(
    auth_harness: AuthHarness,
) -> None:
    """GET/POST return only four PHP-compatible aggregates to a current active user."""
    actor_id, actor_email, _ = auth_harness.create_user()
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    baseline_response = auth_harness.client.get("/api/get_alumni_stats", headers=headers)
    assert baseline_response.status_code == 200
    baseline = baseline_response.json()["stats"]

    qualified_id, qualified_email, _ = auth_harness.create_user()
    inactive_id, inactive_email, _ = auth_harness.create_user(active=0)
    unapproved_id, unapproved_email, _ = auth_harness.create_user(is_approved=0)
    manager_id, manager_email, _ = auth_harness.create_user()
    suffix = uuid.uuid4().hex[:10]
    department_a = f"Stats Department A {suffix}"
    department_b = f"Stats Department B {suffix}"
    excluded_department = f"Stats Excluded Department {suffix}"
    chapter_name = f"Stats Enabled Chapter {suffix}"
    disabled_chapter_name = f"Stats Disabled Chapter {suffix}"

    with auth_harness.engine.begin() as connection:
        existing_years = {
            str(value)
            for value in connection.scalars(select(AlumniCategory.year))
            if value is not None
        }
        selected_years = [
            str(year) for year in range(2199, 1799, -1) if str(year) not in existing_years
        ][:2]
        assert len(selected_years) == 2

        enabled_result = connection.execute(
            ALUMNI_CHAPTER_TABLE.insert().values(
                chapter_name=chapter_name,
                location=f"Stats Location {suffix}",
                is_enabled=1,
            )
        )
        disabled_result = connection.execute(
            ALUMNI_CHAPTER_TABLE.insert().values(
                chapter_name=disabled_chapter_name,
                location=f"Stats Disabled Location {suffix}",
                is_enabled=0,
            )
        )
        enabled_key = enabled_result.inserted_primary_key
        disabled_key = disabled_result.inserted_primary_key
        assert enabled_key is not None
        assert disabled_key is not None
        enabled_chapter_id = int(enabled_key[0])
        auth_harness.alumni_chapter_ids.extend((enabled_chapter_id, int(disabled_key[0])))

        connection.execute(
            USERS_TABLE.update().where(Users.id == qualified_id).values(department=department_a)
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == inactive_id).values(department=department_a)
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == unapproved_id)
            .values(department=excluded_department)
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == manager_id)
            .values(user_role="manager", department=department_b)
        )

        for user_id, year in (
            (qualified_id, selected_years[0]),
            (inactive_id, selected_years[0]),
            (manager_id, selected_years[1]),
        ):
            category_result = connection.execute(
                ALUMNI_CATEGORY_TABLE.insert().values(
                    user_id=user_id,
                    chapter_id=enabled_chapter_id,
                    year=year,
                    location=f"Stats Category Location {suffix}",
                )
            )
            category_key = category_result.inserted_primary_key
            assert category_key is not None
            auth_harness.alumni_category_ids.append(int(category_key[0]))

    expected = {
        "total_alumni": baseline["total_alumni"] + 1,
        "total_years": baseline["total_years"] + 2,
        "total_chapters": baseline["total_chapters"] + 1,
        "total_departments": baseline["total_departments"] + 2,
    }
    get_response = auth_harness.client.get("/api/get_alumni_stats", headers=headers)
    post_response = auth_harness.client.post(
        "/api/get_alumni_stats",
        headers=headers,
        json={"token": "ignored-legacy-value", "user_id": manager_id},
    )
    expected_body = {
        "status": 200,
        "message": "Alumni stats retrieved successfully",
        "stats": expected,
    }
    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json() == expected_body
    for sensitive_value in (
        qualified_email,
        inactive_email,
        unapproved_email,
        manager_email,
        department_a,
        department_b,
    ):
        assert sensitive_value not in get_response.text

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive_actor = auth_harness.client.get("/api/get_alumni_stats", headers=headers)
    assert inactive_actor.status_code == 401
    assert inactive_actor.json()["code"] == "alumni_stats_actor_unavailable"


@pytest.mark.integration
def test_birthdays_preserve_windows_privacy_frontend_contract_and_current_actor(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """GET/bodyless POST honor Lagos dates, opt-in privacy, leap days, and safe fields."""
    reference_date = date(2025, 2, 28)
    monkeypatch.setattr(
        members_service_module,
        "datetime",
        _FixedLagosDateTime,
    )
    _FixedLagosDateTime.reference_date = reference_date

    actor_id, actor_email, _ = auth_harness.create_user(fullname="020 Birthday Actor")
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    baseline_today = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"limit": 200},
    ).json()
    baseline_week = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "week", "limit": 200},
    ).json()
    baseline_upcoming = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "upcoming", "days": 2, "limit": 200},
    ).json()
    baseline_month = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "month", "month": 3, "limit": 200},
    ).json()
    assert baseline_today["total"] == 0
    assert baseline_today["message"] == "No birthdays found for this period"

    synthetic: dict[str, int] = {"actor": actor_id}
    for name, active, verified, approved, dob in (
        ("010 Alpha Today", 1, 1, 1, date(1985, 2, 28)),
        ("030 Beta Leap", 1, 1, 1, date(1988, 2, 29)),
        ("040 Gamma Unverified", 1, 0, 0, date(1991, 2, 28)),
        ("050 Private Birthday", 1, 1, 1, date(1990, 2, 28)),
        ("060 Malformed Visibility", 1, 1, 1, date(1990, 2, 28)),
        ("070 Inactive Birthday", 0, 1, 1, date(1990, 2, 28)),
        ("080 Delta Tomorrow", 1, 1, 1, date(1990, 3, 1)),
        ("090 Echo Week", 1, 1, 1, date(1990, 3, 6)),
        ("100 Foxtrot Outside", 1, 1, 1, date(1990, 3, 7)),
    ):
        user_id, _, _ = auth_harness.create_user(
            active=active,
            email_verified=verified,
            is_approved=approved,
            fullname=name,
            graduation_year=2004,
        )
        synthetic[name] = user_id
        with auth_harness.engine.begin() as connection:
            connection.execute(
                USERS_TABLE.update()
                .where(Users.id == user_id)
                .values(birth_date=dob, name_in_school=f"School {name}")
            )

    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == actor_id)
            .values(birth_date=date(1990, 2, 28), graduation_year=2001)
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == synthetic["030 Beta Leap"])
            .values(fullname=None, first_name="Beta", last_name="Leap")
        )
        for user_id, visible, fields in (
            (synthetic["010 Alpha Today"], 0, '{"birth_date":true,"avatar":false}'),
            (synthetic["050 Private Birthday"], 1, '{"birth_date":false}'),
            (synthetic["060 Malformed Visibility"], 1, "{bad-json"),
        ):
            connection.execute(
                PROFILES_TABLE.insert().values(
                    user_id=user_id,
                    instagram="",
                    tiktok="",
                    updated_at=now,
                    is_visible=visible,
                    field_visibility=fields,
                )
            )

    get_response = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"limit": 200},
    )
    post_response = auth_harness.client.post(
        "/api/get_birthdays",
        headers=headers,
        params={"limit": 200},
    )
    assert get_response.status_code == post_response.status_code == 200
    assert get_response.json() == post_response.json()
    today_body = get_response.json()
    assert today_body["scope"] == "today"
    assert today_body["date"] == "2025-02-28"
    assert today_body["total"] == baseline_today["total"] + 4
    assert today_body["returned"] == len(today_body["birthdays"])

    today_by_id = {item["user_id"]: item for item in today_body["birthdays"]}
    expected_today_ids = {
        actor_id,
        synthetic["010 Alpha Today"],
        synthetic["030 Beta Leap"],
        synthetic["040 Gamma Unverified"],
    }
    assert expected_today_ids <= set(today_by_id)
    assert synthetic["050 Private Birthday"] not in today_by_id
    assert synthetic["060 Malformed Visibility"] not in today_by_id
    assert synthetic["070 Inactive Birthday"] not in today_by_id
    assert today_by_id[synthetic["010 Alpha Today"]]["avatar"] is None
    assert today_by_id[synthetic["010 Alpha Today"]]["class_label"] == "Class '04"
    assert today_by_id[synthetic["030 Beta Leap"]]["date"] == "2025-02-28"
    assert today_by_id[synthetic["030 Beta Leap"]]["fullname"] == "Beta Leap"
    assert today_by_id[actor_id]["is_self"] is True
    expected_item_keys = {
        "user_id",
        "fullname",
        "name_in_school",
        "avatar",
        "class_label",
        "date",
        "days_until",
        "is_today",
        "is_self",
        "message",
    }
    assert set(today_by_id[actor_id]) == expected_item_keys
    for forbidden in ("birth_date", "age", "email", "phone", "user_code", "department"):
        assert forbidden not in today_by_id[actor_id]

    without_self = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"include_self": "0", "limit": 200},
    ).json()
    assert without_self["total"] == today_body["total"] - 1
    assert actor_id not in {item["user_id"] for item in without_self["birthdays"]}

    week = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "week", "limit": 200},
    ).json()
    assert week["total"] == baseline_week["total"] + 6
    week_ids = {item["user_id"] for item in week["birthdays"]}
    assert synthetic["080 Delta Tomorrow"] in week_ids
    assert synthetic["090 Echo Week"] in week_ids
    assert synthetic["100 Foxtrot Outside"] not in week_ids

    upcoming = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "upcoming", "days": 2, "limit": 200},
    ).json()
    assert upcoming["total"] == baseline_upcoming["total"] + 5
    upcoming_ids = {item["user_id"] for item in upcoming["birthdays"]}
    assert synthetic["080 Delta Tomorrow"] in upcoming_ids
    assert synthetic["090 Echo Week"] not in upcoming_ids

    month = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "month", "month": 3, "limit": 200},
    ).json()
    assert month["total"] == baseline_month["total"] + 3
    month_ids = {item["user_id"] for item in month["birthdays"]}
    assert {
        synthetic["080 Delta Tomorrow"],
        synthetic["090 Echo Week"],
        synthetic["100 Foxtrot Outside"],
    } <= month_ids

    limited = auth_harness.client.get(
        "/api/get_birthdays",
        headers=headers,
        params={"scope": "week", "limit": 2},
    ).json()
    assert limited["total"] == week["total"]
    assert limited["returned"] == len(limited["birthdays"]) == 2
    assert [
        (item["days_until"], item["fullname"].casefold(), item["user_id"])
        for item in limited["birthdays"]
    ] == sorted(
        (item["days_until"], item["fullname"].casefold(), item["user_id"])
        for item in limited["birthdays"]
    )

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive_actor = auth_harness.client.get("/api/get_birthdays", headers=headers)
    assert inactive_actor.status_code == 401
    assert inactive_actor.json()["code"] == "birthdays_actor_unavailable"


@pytest.mark.integration
def test_birthdays_fail_safely_when_the_candidate_scan_bound_is_exceeded(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A pathological catalogue is rejected instead of creating an unbounded API workload."""
    reference_date = date(2025, 4, 15)
    _FixedLagosDateTime.reference_date = reference_date
    monkeypatch.setattr(members_service_module, "datetime", _FixedLagosDateTime)
    monkeypatch.setattr(members_service_module, "MAX_BIRTHDAY_CANDIDATES", 1)
    actor_id, actor_email, _ = auth_harness.create_user()
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    candidate_ids = [
        auth_harness.create_user(fullname=f"Bounded Birthday {i}")[0] for i in range(2)
    ]
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_(candidate_ids))
            .values(birth_date=date(1990, 4, 15))
        )

    response = auth_harness.client.get("/api/get_birthdays", headers=headers)
    assert response.status_code == 503
    assert response.json()["code"] == "birthdays_candidate_limit_exceeded"


@pytest.mark.integration
def test_chapter_list_is_public_bounded_and_user_assignment_is_deterministic(
    auth_harness: AuthHarness,
) -> None:
    """Public metadata excludes disabled chapters; self lookup returns the oldest assignment."""
    user_id, email, _ = auth_harness.create_user()
    suffix = uuid.uuid4().hex[:10]
    chapter_rows = (
        (f"A Stats Chapter {suffix}", 1),
        (f"B Stats Chapter {suffix}", 1),
        (f"Disabled Stats Chapter {suffix}", 0),
    )
    chapter_ids: list[int] = []
    with auth_harness.engine.begin() as connection:
        for name, enabled in chapter_rows:
            result = connection.execute(
                ALUMNI_CHAPTER_TABLE.insert().values(
                    chapter_name=name,
                    location=f"Chapter Location {suffix}",
                    is_enabled=enabled,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            chapter_ids.append(int(inserted_key[0]))
        auth_harness.alumni_chapter_ids.extend(chapter_ids)

        for chapter_id, year in ((chapter_ids[1], "1998"), (chapter_ids[0], "1999")):
            result = connection.execute(
                ALUMNI_CATEGORY_TABLE.insert().values(
                    user_id=user_id,
                    chapter_id=chapter_id,
                    year=year,
                    location=f"Assignment Location {suffix}",
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            auth_harness.alumni_category_ids.append(int(inserted_key[0]))

    public_get = auth_harness.client.get("/api/get_chapters")
    public_post = auth_harness.client.post(
        "/api/get_chapters",
        json={"token": "ignored-legacy-value", "user_id_typo": user_id},
    )
    assert public_get.status_code == public_post.status_code == 200
    assert public_get.json() == public_post.json()
    body = public_get.json()
    assert body["status"] == 200
    assert body["message"] == "Chapters retrieved successfully"
    listed = [chapter for chapter in body["chapters"] if chapter["id"] in chapter_ids]
    assert [chapter["chapter_name"] for chapter in listed] == [
        chapter_rows[0][0],
        chapter_rows[1][0],
    ]
    assert chapter_ids[2] not in {chapter["id"] for chapter in body["chapters"]}
    assert all(
        set(chapter) == {"id", "chapter_name", "location", "is_enabled", "created_at"}
        for chapter in listed
    )

    self_lookup = auth_harness.client.post(
        "/api/get_chapters",
        headers={"Authorization": f"Bearer {_access_token_for(auth_harness, user_id, email)}"},
        json={"user_id": user_id, "token": "ignored-legacy-value"},
    )
    assert self_lookup.status_code == 200
    assignment = self_lookup.json()
    assert assignment["message"] == "User chapter retrieved successfully"
    assert assignment["user_id"] == user_id
    assert assignment["chapter"] == {
        "category_id": auth_harness.alumni_category_ids[0],
        "user_id": user_id,
        "year": "1998",
        "location": f"Assignment Location {suffix}",
        "joined_at": assignment["chapter"]["joined_at"],
        "chapter_id": chapter_ids[1],
        "chapter_name": chapter_rows[1][0],
        "is_enabled": True,
    }
    assert email not in self_lookup.text
    assert "password" not in self_lookup.text


@pytest.mark.integration
def test_user_chapter_lookup_uses_current_downward_authorization(
    auth_harness: AuthHarness,
) -> None:
    """Stale positive claims work only with current authority; forged claims fail closed."""
    manager_id, manager_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    unassigned_id, _unassigned_email, _ = auth_harness.create_user()
    suffix = uuid.uuid4().hex[:10]
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == manager_id).values(user_role="manager")
        )
        chapter_result = connection.execute(
            ALUMNI_CHAPTER_TABLE.insert().values(
                chapter_name=f"Authorization Chapter {suffix}",
                location=f"Authorization Location {suffix}",
                is_enabled=1,
            )
        )
        chapter_key = chapter_result.inserted_primary_key
        assert chapter_key is not None
        chapter_id = int(chapter_key[0])
        auth_harness.alumni_chapter_ids.append(chapter_id)
        category_result = connection.execute(
            ALUMNI_CATEGORY_TABLE.insert().values(
                user_id=target_id,
                chapter_id=chapter_id,
                year="2001",
                location=f"Target Assignment {suffix}",
            )
        )
        category_key = category_result.inserted_primary_key
        assert category_key is not None
        auth_harness.alumni_category_ids.append(int(category_key[0]))

    stale_manager_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, manager_id, manager_email)}"
    }
    allowed = auth_harness.client.get(
        "/api/get_chapters",
        headers=stale_manager_headers,
        params={"user_id": target_id},
    )
    forged = auth_harness.client.get(
        "/api/get_chapters",
        headers={
            "Authorization": "Bearer "
            + _access_token_for(
                auth_harness,
                target_id,
                target_email,
                user_role="super admin",
            )
        },
        params={"user_id": manager_id},
    )
    missing = auth_harness.client.get(
        "/api/get_chapters",
        headers=stale_manager_headers,
        params={"user_id": 2_000_000_000},
    )
    unassigned = auth_harness.client.post(
        "/api/get_chapters",
        headers=stale_manager_headers,
        json={"user_id": unassigned_id},
    )

    assert allowed.status_code == 200
    assert allowed.json()["chapter"]["chapter_id"] == chapter_id
    assert forged.status_code == 403
    assert forged.json()["code"] == "chapter_forbidden"
    assert missing.status_code == 404
    assert missing.json()["code"] == "chapter_user_not_found"
    assert unassigned.status_code == 200
    assert unassigned.json() == {
        "status": 200,
        "message": "User is not assigned to any chapter",
        "chapter": None,
    }

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == manager_id).values(active=0))
    inactive_actor = auth_harness.client.get(
        "/api/get_chapters",
        headers=stale_manager_headers,
        params={"user_id": target_id},
    )
    assert inactive_actor.status_code == 401
    assert inactive_actor.json()["code"] == "chapter_actor_unavailable"


@pytest.mark.integration
def test_setup_parameters_is_bounded_deterministic_and_rechecks_current_actor(
    auth_harness: AuthHarness,
) -> None:
    """The oldest matching setup row is returned only while the Bearer actor remains active."""
    actor_id, actor_email, _ = auth_harness.create_user()
    suffix = uuid.uuid4().hex[:10]
    setup_name = f"Currency {suffix}"
    raw_value = " NGN, USD, , GBP, 0 "
    with auth_harness.engine.begin() as connection:
        for value in (raw_value, "SHOULD_NOT_WIN"):
            result = connection.execute(
                SETUP_PARAMETERS_TABLE.insert().values(
                    setup_name=setup_name,
                    setup_value=value,
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            auth_harness.setup_parameter_ids.append(int(inserted_key[0]))

    headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}",
        "X-API-Key": "ignored-legacy-value",
    }
    json_response = auth_harness.client.post(
        "/api/get_setup_parameters",
        headers=headers,
        json={"action_type": setup_name.lower(), "token": "ignored-legacy-value"},
    )
    form_response = auth_harness.client.post(
        "/api/get_setup_parameters",
        headers=headers,
        data={"action_type": setup_name},
    )
    expected = {
        "status": 200,
        "message": "Setup parameters retrieved successfully",
        "data": {
            "setup_id": auth_harness.setup_parameter_ids[0],
            "setup_name": setup_name,
            "setup_value": raw_value,
            "values": ["NGN", "USD", "GBP", "0"],
        },
    }
    assert json_response.status_code == form_response.status_code == 200
    assert json_response.json() == form_response.json() == expected
    assert set(json_response.json()["data"]) == {
        "setup_id",
        "setup_name",
        "setup_value",
        "values",
    }

    missing = auth_harness.client.post(
        "/api/get_setup_parameters",
        headers=headers,
        json={"action_type": f"Missing {suffix}"},
    )
    assert missing.status_code == 404
    assert missing.json()["code"] == "setup_parameters_not_found"

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive_actor = auth_harness.client.post(
        "/api/get_setup_parameters",
        headers=headers,
        json={"action_type": setup_name},
    )
    assert inactive_actor.status_code == 401
    assert inactive_actor.json()["code"] == "setup_parameters_actor_unavailable"


@pytest.mark.integration
def test_profile_update_merges_json_fields_and_returns_a_fresh_bounded_projection(
    auth_harness: AuthHarness,
) -> None:
    """JSON updates trim supplied fields, preserve omissions, and never expose credentials."""
    user_id, email, original_password = auth_harness.create_user(city="Old City")
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=user_id,
                instagram="old-instagram",
                tiktok="old-tiktok",
                updated_at=now,
                current_company="Old Company",
                current_position="Old Position",
                city="Old Profile City",
                field_visibility='{"phone":false}',
            )
        )
    token = _access_token_for(auth_harness, user_id, email)
    response = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={
            "user_id": user_id,
            "first_name": "  Updated  ",
            "phone": "",
            "graduation_year": "",
            "city": "  New City  ",
            "is_volunteer": "1",
            "email": "ignored@example.com",
            "password": "ignored-password",
            "profile": {
                "instagram": "  new-instagram  ",
                "current_position": "",
            },
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == 200
    assert body["user"]["id"] == user_id
    assert body["user"]["first_name"] == "Updated"
    assert body["user"]["last_name"] == "Member"
    assert body["user"]["fullname"] == "Updated Member"
    assert body["user"]["phone"] == ""
    assert "graduation_year" not in body["user"]
    assert body["user"]["city"] == "New City"
    assert body["user"]["is_volunteer"] is True
    assert body["user"]["email"] == email
    assert body["profile"]["instagram"] == "new-instagram"
    assert body["profile"]["tiktok"] == "old-tiktok"
    assert body["profile"]["current_position"] == ""
    assert body["profile"]["city"] == "New City"
    assert body["profile"]["field_visibility"] == '{"phone":false}'
    assert "password" not in body["user"]
    assert "reset_token" not in body["user"]

    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(
                Users.first_name,
                Users.last_name,
                Users.fullname,
                Users.phone,
                Users.graduation_year,
                Users.city,
                Users.is_volunteer,
                Users.password,
                Users.active,
                Users.onboarding_completion,
            ).where(Users.id == user_id)
        ).one()
    assert tuple(stored) == (
        "Updated",
        "Member",
        "Updated Member",
        "",
        None,
        "New City",
        1,
        original_password,
        1,
        1,
    )
    invalid_chapter = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={"chapter_id": 2_000_000_000},
    )
    assert invalid_chapter.status_code == 400
    assert invalid_chapter.json()["code"] == "profile_update_chapter_invalid"


@pytest.mark.integration
def test_profile_update_authorization_uses_current_database_hierarchy(
    auth_harness: AuthHarness,
) -> None:
    """Forged role claims fail while a current manager can edit only a lower role."""
    actor_id, actor_email, _ = auth_harness.create_user()
    target_id, target_email, _ = auth_harness.create_user()
    peer_id, _peer_email, _ = auth_harness.create_user()
    inactive_id, _inactive_email, _ = auth_harness.create_user(active=0)
    forged = _access_token_for(
        auth_harness,
        actor_id,
        actor_email,
        user_role="superadmin",
    )
    denied = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": target_id, "bio": "forged"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "profile_update_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="manager")
        )
        connection.execute(
            USERS_TABLE.update().where(Users.id == peer_id).values(user_role="manager")
        )
    stale = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    allowed = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {stale}"},
        json={"user_id": target_id, "bio": "managed update"},
    )
    peer_denied = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {stale}"},
        json={"user_id": peer_id, "bio": "peer update"},
    )
    inactive_target = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {stale}"},
        json={"user_id": inactive_id, "bio": "remains inactive"},
    )
    assert allowed.status_code == 200
    assert allowed.json()["user"]["bio"] == "managed update"
    assert allowed.json()["user"]["email"] == target_email
    assert peer_denied.status_code == 403
    assert inactive_target.status_code == 200
    assert inactive_target.json()["user"]["active"] is False
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.bio).where(Users.id == peer_id)) is None


@pytest.mark.integration
def test_profile_update_accepts_nested_multipart_avatar_and_records_attachment(
    auth_harness: AuthHarness,
) -> None:
    """A verified image is normalized, stored under a server name, and committed with metadata."""
    user_id, email, _ = auth_harness.create_user()
    image_output = BytesIO()
    Image.new("RGB", (12, 10), color=(120, 40, 20)).save(image_output, format="PNG")
    token = _access_token_for(auth_harness, user_id, email)
    response = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {token}"},
        data={
            "user_id": str(user_id),
            "profile[current_company]": "  Multipart Company  ",
        },
        files={
            "avatar": (
                "../../unsafe name.png",
                image_output.getvalue(),
                "application/octet-stream",
            )
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["profile"]["current_company"] == "Multipart Company"
    relative_path = body["user"]["avatar"].removeprefix("https://alumni.example.test/")
    assert relative_path.startswith("uploads/profiles/")
    stored_path = auth_harness.settings.upload_root / relative_path.removeprefix("uploads/")
    assert stored_path.is_file()
    with Image.open(stored_path) as image:
        assert image.format == "PNG"
        assert image.size == (12, 10)
    with auth_harness.engine.connect() as connection:
        attachment = connection.execute(
            select(
                Attachments.file_type,
                Attachments.filename,
                Attachments.attachment_file,
            ).where(Attachments.user_id == user_id)
        ).one()
    assert attachment.file_type == "profile_image"
    assert attachment.filename == "unsafe_name.png"
    assert attachment.attachment_file == relative_path
    served = auth_harness.client.get(f"/uploads/profiles/{stored_path.name}")
    assert served.status_code == 200
    assert served.headers["content-type"] == "image/png"
    assert served.content == stored_path.read_bytes()


@pytest.mark.integration
def test_profile_only_update_creates_required_legacy_profile_defaults(
    auth_harness: AuthHarness,
) -> None:
    """A profile-only update can upsert the legacy row without changing onboarding state."""
    user_id, email, _ = auth_harness.create_user(onboarding_completion=0)
    token = _access_token_for(auth_harness, user_id, email)
    response = auth_harness.client.post(
        "/api/update_profile",
        headers={"Authorization": f"Bearer {token}"},
        json={"profile": {"linkedin": "  https://example.test/profile  "}},
    )
    assert response.status_code == 200
    assert response.json()["profile"]["linkedin"] == "https://example.test/profile"
    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(
                UserProfiles.linkedin,
                UserProfiles.instagram,
                UserProfiles.tiktok,
                Users.onboarding_completion,
            )
            .select_from(
                Users.__table__.join(
                    UserProfiles.__table__,
                    UserProfiles.user_id == Users.id,
                )
            )
            .where(Users.id == user_id)
        ).one()
    assert tuple(stored) == ("https://example.test/profile", "", "", 0)


@pytest.mark.integration
def test_profile_update_rolls_back_database_and_avatar_on_repository_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failure after file creation leaves no database changes, metadata, or orphan file."""
    user_id, _email, _ = auth_harness.create_user()
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="JPEG")
    avatar = prepare_avatar("rollback.jpg", image_output.getvalue())
    storage = AvatarStorage(auth_harness.settings.upload_root)
    original = MemberRepository.insert_avatar_attachment

    def fail_after_attachment(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original(self, *args, **kwargs)
        raise RuntimeError("synthetic profile update failure")

    monkeypatch.setattr(MemberRepository, "insert_avatar_attachment", fail_after_attachment)
    request = UpdateProfileRequest.model_validate({"bio": "must roll back"})
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic profile update failure"),
    ):
        MemberService(session, auth_harness.settings).update_profile(
            user_id,
            request,
            avatar,
            storage,
        )

    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Users.bio).where(Users.id == user_id)) is None
        assert (
            connection.scalar(
                select(func.count(Attachments.id)).where(Attachments.user_id == user_id)
            )
            == 0
        )
    profile_directory = auth_harness.settings.upload_root / "profiles"
    assert not profile_directory.exists() or list(profile_directory.iterdir()) == []


@pytest.mark.integration
def test_announcements_are_public_but_writes_use_current_content_permission(
    auth_harness: AuthHarness,
) -> None:
    """The legacy public feed cannot turn an old JWT role claim into a write grant."""
    actor_id, actor_email, _ = auth_harness.create_user(user_role="alumni")
    token = _access_token_for(auth_harness, actor_id, actor_email)

    denied = auth_harness.client.post(
        "/api/create_announcement",
        headers={"Authorization": f"Bearer {token}"},
        json={"title": "Denied", "content": "member cannot publish"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "announcement_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="content admin")
        )
    image_output = BytesIO()
    Image.new("RGB", (8, 6), color=(6, 70, 120)).save(image_output, format="PNG")
    created = auth_harness.client.post(
        "/api/create_announcement",
        headers={"Authorization": f"Bearer {token}"},
        data={
            "title": "  Reunion update  ",
            "content": "  Registration opens today.  ",
            "type": "event",
            "year": "2001",
        },
        files={"images": ("../../../announcement.png", image_output.getvalue(), "image/png")},
    )
    assert created.status_code == 200
    created_body = created.json()
    assert created_body["data"]["title"] == "Reunion update"
    announcement_id = created_body["data"]["id"]
    relative_image = created_body["data"]["images"]
    assert relative_image.startswith("uploads/announcements/")
    image_path = auth_harness.settings.upload_root / relative_image.removeprefix("uploads/")
    assert image_path.is_file()

    public_feed = auth_harness.client.post("/api/get_announcements", json={"type": "event"})
    assert public_feed.status_code == 200
    assert public_feed.json()["total"] == 1
    assert public_feed.json()["data"][0]["created_by"] == actor_id
    assert "user_role" not in public_feed.json()["data"][0]

    updated = auth_harness.client.post(
        "/api/manage_announcement",
        headers={"Authorization": f"Bearer {token}"},
        json={"function_type": "update", "id": announcement_id, "title": "Reunion reminder"},
    )
    assert updated.status_code == 200
    assert updated.json()["data"]["title"] == "Reunion reminder"

    replacement_output = BytesIO()
    Image.new("RGB", (7, 5), color=(180, 30, 20)).save(replacement_output, format="JPEG")
    image_replaced = auth_harness.client.post(
        "/api/manage_announcement",
        headers={"Authorization": f"Bearer {token}"},
        data={"function_type": "update", "id": str(announcement_id)},
        files={"image": ("replacement.jpg", replacement_output.getvalue(), "image/jpeg")},
    )
    assert image_replaced.status_code == 200
    replacement_relative_image = image_replaced.json()["data"]["images"]
    replacement_image_path = (
        auth_harness.settings.upload_root / replacement_relative_image.removeprefix("uploads/")
    )
    assert replacement_image_path.is_file()
    assert not image_path.exists()

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="alumni")
        )
    stale_claim_denied = auth_harness.client.post(
        "/api/manage_announcement",
        headers={"Authorization": f"Bearer {token}"},
        json={"function_type": "delete", "id": announcement_id},
    )
    assert stale_claim_denied.status_code == 403

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="content admin")
        )
    deleted = auth_harness.client.post(
        "/api/manage_announcement",
        headers={"Authorization": f"Bearer {token}"},
        json={"function_type": "delete", "id": announcement_id},
    )
    assert deleted.status_code == 200
    assert not replacement_image_path.exists()
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(func.count(Announcements.id))) == 0


@pytest.mark.integration
def test_announcement_create_rolls_back_stored_image_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A database exception after file persistence leaves no announcement or orphan file."""
    actor_id, _email, _ = auth_harness.create_user(user_role="content admin")
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    image = prepare_avatar("rollback.png", image_output.getvalue())
    original = AnnouncementRepository.create

    def fail_after_insert(self: AnnouncementRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic announcement insert failure")

    monkeypatch.setattr(AnnouncementRepository, "create", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic announcement insert failure"),
    ):
        AnnouncementService(session, AnnouncementStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            AnnouncementCreateRequest.model_validate({"title": "Rollback", "content": "No row"}),
            image,
        )
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(func.count(Announcements.id))) == 0
    directory = auth_harness.settings.upload_root / "announcements"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_marketplace_listing_writes_use_server_ownership_and_current_store_permission(
    auth_harness: AuthHarness,
) -> None:
    """Legacy user IDs and JWT roles never authorize another seller's listing."""
    chapter_id = auth_harness.create_chapter()
    owner_id, owner_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    other_token = _access_token_for(auth_harness, other_id, other_email, user_role="admin")
    other_headers = {"Authorization": f"Bearer {other_token}"}

    created = auth_harness.client.post(
        "/api/create_listing",
        headers=owner_headers,
        json={
            "user_id": other_id,
            "title": "  Carefully made chair  ",
            "business_name": "  Alumni Crafts  ",
            "phone": "08000000001",
            "chapter_id": chapter_id,
            "category": "items",
            "status": "sold",
            "is_featured": True,
            "social_instagram": "https://instagram.example.test/alumni-crafts",
        },
    )
    assert created.status_code == 200
    listing = created.json()["listing"]
    listing_id = int(listing["id"])
    assert listing["user_id"] == owner_id
    assert listing["status"] == "active"
    assert listing["is_featured"] is False
    assert listing["images"] == []
    assert (
        listing["social_media"]["instagram_url"] == "https://instagram.example.test/alumni-crafts"
    )

    public = auth_harness.client.post("/api/get_listings", json={"id": listing_id})
    assert public.status_code == 200
    assert public.json()["listing"]["id"] == listing_id
    assert "email" not in public.json()["listing"]

    denied = auth_harness.client.post(
        "/api/manage_listing",
        headers=other_headers,
        json={"function_type": "update", "id": listing_id, "title": "Not allowed"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "marketplace_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == other_id).values(user_role="storekeeper admin")
        )
    allowed = auth_harness.client.post(
        "/api/manage_listing",
        headers=other_headers,
        json={"function_type": "update", "id": listing_id, "title": "Current policy allowed"},
    )
    assert allowed.status_code == 200
    assert allowed.json()["listing"]["title"] == "Current policy allowed"

    replacement_image = BytesIO()
    Image.new("RGB", (7, 7), color=(100, 90, 80)).save(replacement_image, format="PNG")
    updated_images = auth_harness.client.post(
        "/api/manage_listing",
        headers=owner_headers,
        data={"function_type": "update", "id": str(listing_id), "image_action": "replace"},
        files={"images[]": ("listing.png", replacement_image.getvalue(), "image/png")},
    )
    assert updated_images.status_code == 200
    listing_image = updated_images.json()["listing"]["images"][0]
    listing_path = auth_harness.settings.upload_root / listing_image.removeprefix("uploads/")
    assert listing_path.exists()
    deleted_listing = auth_harness.client.post(
        "/api/manage_listing",
        headers=owner_headers,
        json={"function_type": "delete", "id": listing_id},
    )
    assert deleted_listing.status_code == 200
    assert not listing_path.exists()


@pytest.mark.integration
def test_marketplace_create_rolls_back_stored_images_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A late database failure cannot leave a listing or marketplace image behind."""
    actor_id, _email, _ = auth_harness.create_user()
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    image = prepare_avatar("rollback.png", image_output.getvalue())
    original = MarketplaceRepository.create

    def fail_after_insert(self: MarketplaceRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic marketplace insert failure")

    monkeypatch.setattr(MarketplaceRepository, "create", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic marketplace insert failure"),
    ):
        MarketplaceService(session, MarketplaceStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            MarketplaceCreateRequest.model_validate(
                {"title": "Rollback", "business_name": "Rollback", "phone": "08000000001"}
            ),
            [image],
            {},
        )
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(func.count(MarketplaceListings.id))) == 0
    directory = auth_harness.settings.upload_root / "marketplace"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_projects_use_current_content_policy_public_shaping_and_soft_deletion(
    auth_harness: AuthHarness,
) -> None:
    """JWT role claims and creator IDs cannot bypass the reviewed content policy."""
    chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((actor_id, other_id)))
            .values(chapter_id=chapter_id)
        )
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")
    denied = auth_harness.client.post(
        "/api/create_project",
        headers={"Authorization": f"Bearer {forged}"},
        json={"title": "Denied project"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "project_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="content admin")
        )
    image_output = BytesIO()
    Image.new("RGB", (8, 6), color=(6, 70, 120)).save(image_output, format="PNG")
    created = auth_harness.client.post(
        "/api/create_project",
        headers={"Authorization": f"Bearer {forged}"},
        data={
            "title": "  Synthetic community library  ",
            "description": "  New books for the community.  ",
            "status": "ongoing",
            "location": "Lagos",
            "created_by": str(other_id),
            "is_deleted": "1",
        },
        files={"images[]": ("../../../project.png", image_output.getvalue(), "image/png")},
    )
    assert created.status_code == 200
    project = created.json()["project"]
    project_id = int(project["id"])
    assert project["title"] == "Synthetic community library"
    assert project["chapter_id"] == chapter_id
    assert project["status"] == "ongoing"
    assert project["images"][0].startswith("uploads/projects/")
    image_path = auth_harness.settings.upload_root / project["images"][0].removeprefix("uploads/")
    assert image_path.is_file()

    public = auth_harness.client.post("/api/get_projects", json={"id": project_id})
    assert public.status_code == 200
    assert public.json()["project"]["id"] == project_id
    assert "created_by" not in public.json()["project"]
    assert "email" not in public.json()["project"]

    replacement_image = BytesIO()
    Image.new("RGB", (7, 7), color=(100, 90, 80)).save(replacement_image, format="PNG")
    replaced = auth_harness.client.post(
        "/api/manage_project",
        headers={"Authorization": f"Bearer {forged}"},
        data={"function_type": "update", "id": str(project_id), "image_action": "replace"},
        files={"images[]": ("replacement.png", replacement_image.getvalue(), "image/png")},
    )
    assert replaced.status_code == 200
    replacement_path = auth_harness.settings.upload_root / replaced.json()["project"]["images"][
        0
    ].removeprefix("uploads/")
    assert replacement_path.exists()
    assert not image_path.exists()

    other_token = _access_token_for(auth_harness, other_id, other_email, user_role="content admin")
    denied_update = auth_harness.client.post(
        "/api/manage_project",
        headers={"Authorization": f"Bearer {other_token}"},
        json={"function_type": "update", "id": project_id, "status": "draft"},
    )
    assert denied_update.status_code == 403
    assert denied_update.json()["code"] == "project_forbidden"

    drafted = auth_harness.client.post(
        "/api/manage_project",
        headers={"Authorization": f"Bearer {forged}"},
        json={"function_type": "update", "id": project_id, "status": "draft"},
    )
    assert drafted.status_code == 200
    assert drafted.json()["project"]["status"] == "draft"
    concealed = auth_harness.client.post("/api/get_projects", json={"id": project_id})
    assert concealed.status_code == 404

    deleted = auth_harness.client.post(
        "/api/manage_project",
        headers={"Authorization": f"Bearer {forged}"},
        json={"function_type": "delete", "id": project_id},
    )
    assert deleted.status_code == 200
    assert not replacement_path.exists()
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Projects.is_deleted).where(Projects.id == project_id)) == 1


@pytest.mark.integration
def test_project_create_rolls_back_stored_images_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A late failure must not retain a soft-visible project or its generated files."""
    chapter_id = auth_harness.create_chapter()
    actor_id, _email, _ = auth_harness.create_user(user_role="content admin")
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(chapter_id=chapter_id)
        )
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    image = prepare_avatar("rollback.png", image_output.getvalue())
    original = ProjectRepository.create

    def fail_after_insert(self: ProjectRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic project insert failure")

    monkeypatch.setattr(ProjectRepository, "create", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic project insert failure"),
    ):
        ProjectService(session, ProjectStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            ProjectCreateRequest.model_validate({"title": "Rollback"}),
            [image],
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(Projects.id)).where(Projects.created_by == actor_id)
            )
            == 0
        )
    directory = auth_harness.settings.upload_root / "projects"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_leadership_uses_current_content_policy_public_shaping_and_soft_deletion(
    auth_harness: AuthHarness,
) -> None:
    """Only current content authority may change leadership and public output omits member PII."""
    chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user()
    member_id, _member_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")
    denied = auth_harness.client.post(
        "/api/create_leader",
        headers={"Authorization": f"Bearer {forged}"},
        json={"user_id": member_id, "position_title": "Denied"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "leadership_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="content admin")
        )
    created = auth_harness.client.post(
        "/api/create_leader",
        headers={"Authorization": f"Bearer {forged}"},
        json={
            "user_id": member_id,
            "position_title": "  President  ",
            "chapter_id": chapter_id,
            "year": "2026",
            "is_featured": True,
            "created_by": other_id,
            "is_deleted": 1,
        },
    )
    assert created.status_code == 200
    leader_id = int(created.json()["leader"]["id"])
    assert created.json()["leader"]["position_title"] == "President"

    photo = BytesIO()
    Image.new("RGB", (7, 7), color=(100, 90, 80)).save(photo, format="PNG")
    with_photo = auth_harness.client.post(
        "/api/manage_leader",
        headers={"Authorization": f"Bearer {forged}"},
        data={"function_type": "update", "id": str(leader_id), "is_featured": "true"},
        files={"leadership_photo": ("leader.png", photo.getvalue(), "image/png")},
    )
    assert with_photo.status_code == 200
    leader_photo = with_photo.json()["leader"]["photo"]
    assert leader_photo.startswith("uploads/leadership/")
    photo_path = auth_harness.settings.upload_root / leader_photo.removeprefix("uploads/")
    assert photo_path.exists()
    reordered = auth_harness.client.post(
        "/api/manage_leader",
        headers={"Authorization": f"Bearer {forged}"},
        json={"function_type": "reorder", "order": [{"id": leader_id, "sort_order": 0}]},
    )
    assert reordered.status_code == 200
    photo_removed = auth_harness.client.post(
        "/api/manage_leader",
        headers={"Authorization": f"Bearer {forged}"},
        json={"function_type": "update", "id": leader_id, "remove_photo": True},
    )
    assert photo_removed.status_code == 200
    assert not photo_path.exists()

    public = auth_harness.client.post("/api/get_leadership", json={"id": leader_id})
    assert public.status_code == 200
    assert public.json()["leader"]["id"] == leader_id
    assert "email" not in public.json()["leader"]
    assert "phone" not in public.json()["leader"]
    listed = auth_harness.client.post("/api/get_leadership", json={"chapter_id": chapter_id})
    assert listed.status_code == 200
    assert listed.json()["total"] >= 1

    other_token = _access_token_for(auth_harness, other_id, other_email, user_role="content admin")
    denied_update = auth_harness.client.post(
        "/api/manage_leader",
        headers={"Authorization": f"Bearer {other_token}"},
        json={"function_type": "update", "id": leader_id, "position_title": "Blocked"},
    )
    assert denied_update.status_code == 403

    deleted = auth_harness.client.post(
        "/api/manage_leader",
        headers={"Authorization": f"Bearer {forged}"},
        json={"function_type": "delete", "id": leader_id},
    )
    assert deleted.status_code == 200
    assert (
        auth_harness.client.post("/api/get_leadership", json={"id": leader_id}).status_code == 404
    )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(select(Leadership.is_deleted).where(Leadership.id == leader_id)) == 1
        )


@pytest.mark.integration
def test_leadership_create_rolls_back_stored_image_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A late database failure cannot leave a leadership row or generated image behind."""
    actor_id, _email, _ = auth_harness.create_user(user_role="content admin")
    member_id, _member_email, _ = auth_harness.create_user()
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    image = prepare_avatar("rollback.png", image_output.getvalue())
    original = LeadershipRepository.create

    def fail_after_insert(self: LeadershipRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic leadership insert failure")

    monkeypatch.setattr(LeadershipRepository, "create", fail_after_insert)
    from app.integrations.uploads import LeadershipStorage

    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic leadership insert failure"),
    ):
        LeadershipService(session, LeadershipStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            LeadershipCreateRequest.model_validate(
                {"user_id": member_id, "position_title": "Rollback"}
            ),
            image,
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(Leadership.id)).where(Leadership.created_by == actor_id)
            )
            == 0
        )
    directory = auth_harness.settings.upload_root / "leadership"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_vacancies_are_member_owned_and_derive_the_current_chapter(
    auth_harness: AuthHarness,
) -> None:
    """The public job board retains its active contract without legacy IDOR writes."""
    actor_chapter = auth_harness.create_chapter()
    other_chapter = auth_harness.create_chapter()
    owner_id, owner_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    admin_id, admin_email, _ = auth_harness.create_user(user_role="content admin")
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((owner_id, other_id, admin_id)))
            .values(chapter_id=actor_chapter)
        )
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    image_output = BytesIO()
    Image.new("RGB", (8, 6), color=(6, 70, 120)).save(image_output, format="PNG")
    created = auth_harness.client.post(
        "/api/create_vacancy",
        headers=owner_headers,
        data={
            "job_title": "  Community developer  ",
            "company_name": "  Alumni Labs  ",
            "chapter_id": str(other_chapter),
            "currency": "USD",
            "application_type": "email",
            "application_email": "jobs@example.com",
            "application_deadline": "2027-01-10",
        },
        files={"flyer": ("../../../vacancy.png", image_output.getvalue(), "image/png")},
    )
    assert created.status_code == 200
    vacancy = created.json()["vacancy"]
    vacancy_id = int(vacancy["id"])
    assert vacancy["user_id"] == owner_id
    assert vacancy["chapter_id"] == actor_chapter
    assert vacancy["job_title"] == "Community developer"
    assert vacancy["flyer"].startswith("uploads/vacancies/")
    flyer_path = auth_harness.settings.upload_root / vacancy["flyer"].removeprefix("uploads/")
    assert flyer_path.is_file()

    public = auth_harness.client.post("/api/get_vacancies", json={"id": vacancy_id})
    assert public.status_code == 200
    assert public.json()["vacancy"]["posted_by"]
    assert "email" not in public.json()["vacancy"]
    listed = auth_harness.client.post(
        "/api/get_vacancies", json={"search": "Community", "limit": 1, "offset": 0}
    )
    assert listed.status_code == 200
    assert listed.json()["total"] >= 1

    denied = auth_harness.client.post(
        "/api/manage_vacancy",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, other_id, other_email)}"
        },
        json={"function_type": "update", "id": vacancy_id, "job_title": "Not allowed"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "vacancy_forbidden"

    allowed = auth_harness.client.post(
        "/api/manage_vacancy",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, admin_id, admin_email)}"
        },
        json={"function_type": "update", "id": vacancy_id, "job_title": "Reviewed role"},
    )
    assert allowed.status_code == 200
    assert allowed.json()["vacancy"]["job_title"] == "Reviewed role"

    replacement_image = BytesIO()
    Image.new("RGB", (7, 7), color=(100, 90, 80)).save(replacement_image, format="PNG")
    replaced = auth_harness.client.post(
        "/api/manage_vacancy",
        headers=owner_headers,
        data={
            "function_type": "update",
            "id": str(vacancy_id),
            "application_type": "link",
            "application_link": "https://jobs.example.com/community-developer",
        },
        files={"flyer": ("replacement.png", replacement_image.getvalue(), "image/png")},
    )
    assert replaced.status_code == 200
    replacement_flyer = replaced.json()["vacancy"]["flyer"]
    assert replacement_flyer.startswith("uploads/vacancies/")
    assert not flyer_path.exists()
    replacement_path = auth_harness.settings.upload_root / replacement_flyer.removeprefix(
        "uploads/"
    )
    assert replacement_path.exists()

    flyer_removed = auth_harness.client.post(
        "/api/manage_vacancy",
        headers=owner_headers,
        json={"function_type": "update", "id": vacancy_id, "remove_flyer": True},
    )
    assert flyer_removed.status_code == 200
    assert "flyer" not in flyer_removed.json()["vacancy"]
    assert not replacement_path.exists()

    deleted = auth_harness.client.post(
        "/api/manage_vacancy",
        headers=owner_headers,
        json={"function_type": "delete", "id": vacancy_id},
    )
    assert deleted.status_code == 200
    assert not flyer_path.exists()
    assert (
        auth_harness.client.post("/api/get_vacancies", json={"id": vacancy_id}).status_code == 404
    )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(select(JobVacancies.id).where(JobVacancies.id == vacancy_id)) is None
        )


@pytest.mark.integration
def test_vacancy_create_rolls_back_stored_flyer_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failed vacancy insert must leave neither a row nor a generated flyer."""
    chapter_id = auth_harness.create_chapter()
    actor_id, _email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(chapter_id=chapter_id)
        )
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    image = prepare_avatar("rollback.png", image_output.getvalue())
    original = VacancyRepository.create

    def fail_after_insert(self: VacancyRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic vacancy insert failure")

    monkeypatch.setattr(VacancyRepository, "create", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic vacancy insert failure"),
    ):
        VacancyService(session, VacancyStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            VacancyCreateRequest.model_validate(
                {
                    "job_title": "Rollback",
                    "company_name": "Rollback",
                    "application_email": "jobs@example.com",
                }
            ),
            image,
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(JobVacancies.id)).where(JobVacancies.user_id == actor_id)
            )
            == 0
        )
    directory = auth_harness.settings.upload_root / "vacancies"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_events_use_current_database_permission_and_public_safe_projections(
    auth_harness: AuthHarness,
) -> None:
    """Event writes load the current role, while public reads expose no account identifiers."""
    chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((actor_id, other_id)))
            .values(chapter_id=chapter_id)
        )

    payload = {
        "title": "  Safe public event  ",
        "start_date": "2027-01-10",
        "location": "Alumni Hall",
        "chapter_id": str(chapter_id),
        "created_by": str(other_id),
        "is_approved": "0",
    }
    forged = auth_harness.client.post(
        "/api/create_event",
        headers={
            "Authorization": (
                "Bearer "
                f"{_access_token_for(auth_harness, actor_id, actor_email, user_role='event admin')}"
            )
        },
        data=payload,
    )
    assert forged.status_code == 403
    assert forged.json()["code"] == "event_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="event admin")
        )
    image_output = BytesIO()
    Image.new("RGB", (8, 6), color=(6, 70, 120)).save(image_output, format="PNG")
    stale = {
        "Authorization": (
            f"Bearer {_access_token_for(auth_harness, actor_id, actor_email, user_role='alumni')}"
        )
    }
    created = auth_harness.client.post(
        "/api/create_event",
        headers=stale,
        data=payload,
        files={"event_banner": ("../../../event.png", image_output.getvalue(), "image/png")},
    )
    assert created.status_code == 200
    event = created.json()["event"]
    event_id = int(event["id"])
    assert event["title"] == "Safe public event"
    assert event["event_banner"].startswith("uploads/events/")
    banner_path = auth_harness.settings.upload_root / event["event_banner"].removeprefix("uploads/")
    assert banner_path.is_file()
    with auth_harness.engine.connect() as connection:
        row = connection.execute(
            select(Events.created_by, Events.is_approved).where(Events.id == event_id)
        ).one()
        assert row.created_by == actor_id
        assert row.is_approved == 1

    public = auth_harness.client.post("/api/get_events", json={"id": event_id})
    assert public.status_code == 200
    public_event = public.json()["event"]
    assert public_event["created_by_name"] == "Synthetic Member"
    assert "email" not in public_event
    assert "created_by" not in public_event

    denied = auth_harness.client.post(
        "/api/manage_event",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, other_id, other_email)}"
        },
        json={"function_type": "update", "id": event_id, "title": "Not allowed"},
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "event_forbidden"

    hidden = auth_harness.client.post(
        "/api/manage_event",
        headers=stale,
        json={"function_type": "update", "id": event_id, "status": "draft"},
    )
    assert hidden.status_code == 200
    assert auth_harness.client.post("/api/get_events", json={"id": event_id}).status_code == 404

    deleted = auth_harness.client.post(
        "/api/manage_event",
        headers=stale,
        json={"function_type": "delete", "id": event_id},
    )
    assert deleted.status_code == 200
    assert not banner_path.exists()
    with auth_harness.engine.connect() as connection:
        assert connection.scalar(select(Events.id).where(Events.id == event_id)) is None


@pytest.mark.integration
def test_event_create_rolls_back_stored_banner_on_database_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failed event insert cannot leave an orphaned public banner or row."""
    chapter_id = auth_harness.create_chapter()
    actor_id, _email, _ = auth_harness.create_user(user_role="event admin")
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(chapter_id=chapter_id)
        )
    image_output = BytesIO()
    Image.new("RGB", (5, 5), color=(1, 2, 3)).save(image_output, format="PNG")
    banner = prepare_avatar("rollback.png", image_output.getvalue())
    original = EventRepository.create

    def fail_after_insert(self: EventRepository, values: dict[str, Any]) -> int:
        original(self, values)
        raise RuntimeError("synthetic event insert failure")

    monkeypatch.setattr(EventRepository, "create", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic event insert failure"),
    ):
        EventService(session, EventStorage(auth_harness.settings.upload_root)).create(
            actor_id,
            EventCreateRequest.model_validate(
                {"title": "Rollback", "start_date": date(2027, 1, 10)}
            ),
            banner,
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(select(func.count(Events.id)).where(Events.created_by == actor_id))
            == 0
        )
    directory = auth_harness.settings.upload_root / "events"
    assert not directory.exists() or list(directory.iterdir()) == []


@pytest.mark.integration
def test_event_rsvps_are_owned_capacity_safe_and_attendee_pii_is_current_role_protected(
    auth_harness: AuthHarness,
) -> None:
    """Legacy body user IDs cannot cross account boundaries or bypass current event permissions."""
    organizer_id, organizer_email, _ = auth_harness.create_user()
    member_id, member_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        inserted = connection.execute(
            EVENTS_TABLE.insert().values(
                title="Capacity-safe RSVP event",
                event_banner="",
                status="upcoming",
                created_by=organizer_id,
                event_date=date(2027, 1, 10),
                start_date=date(2027, 1, 10),
                visibility="public",
                is_approved=1,
                max_attendees=1,
                year="2001",
            )
        )
        inserted_key = inserted.inserted_primary_key
        assert inserted_key is not None
        event_id = int(inserted_key[0])

    member_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, member_id, member_email)}"
    }
    other_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, other_id, other_email)}"
    }
    registered = auth_harness.client.post(
        "/api/register_event",
        headers=member_headers,
        json={
            "event_id": event_id,
            "user_id": other_id,
            "status": "going",
            "additional_info": "Synthetic RSVP",
        },
    )
    assert registered.status_code == 200
    assert registered.json()["rsvp"]["user_id"] == member_id
    assert registered.json()["rsvp"]["year"] == "2001"

    duplicate = auth_harness.client.post(
        "/api/register_event",
        headers=member_headers,
        json={"event_id": event_id, "status": "maybe"},
    )
    assert duplicate.status_code == 200
    assert duplicate.json()["rsvp"]["status"] == "maybe"
    assert duplicate.json()["rsvp"]["additional_info"] == "Synthetic RSVP"
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(EventAttendees.id)).where(
                    EventAttendees.event_id == event_id,
                    EventAttendees.user_id == member_id,
                )
            )
            == 1
        )

    other_registered = auth_harness.client.post(
        "/api/register_event",
        headers=other_headers,
        json={"event_id": event_id, "status": "going"},
    )
    assert other_registered.status_code == 200
    full = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=member_headers,
        json={"event_id": event_id, "function_type": "update", "status": "going"},
    )
    assert full.status_code == 409
    assert full.json()["code"] == "event_full"

    spoofed_cancel = auth_harness.client.post(
        "/api/manage_event_rsvp",
        headers=member_headers,
        json={"event_id": event_id, "function_type": "cancel", "user_id": other_id},
    )
    assert spoofed_cancel.status_code == 200
    with auth_harness.engine.connect() as connection:
        statuses = {
            int(row.user_id): str(row.status.value if hasattr(row.status, "value") else row.status)
            for row in connection.execute(
                select(EventAttendees.user_id, EventAttendees.status).where(
                    EventAttendees.event_id == event_id
                )
            )
        }
    assert statuses == {member_id: "not_going", other_id: "going"}

    member_attendees = auth_harness.client.post(
        "/api/get_event_attendees", headers=member_headers, json={"event_id": event_id}
    )
    assert member_attendees.status_code == 403
    assert member_attendees.json()["code"] == "event_forbidden"

    stale_organizer_headers = {
        "Authorization": (
            "Bearer "
            f"{
                _access_token_for(
                    auth_harness, organizer_id, organizer_email, user_role='event admin'
                )
            }"
        )
    }
    forged = auth_harness.client.post(
        "/api/get_event_attendees", headers=stale_organizer_headers, json={"event_id": event_id}
    )
    assert forged.status_code == 403
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == organizer_id).values(user_role="event admin")
        )
    attendees = auth_harness.client.post(
        "/api/get_event_attendees", headers=stale_organizer_headers, json={"event_id": event_id}
    )
    assert attendees.status_code == 200
    body = attendees.json()
    assert body["summary"] == {"going": 1, "maybe": 0, "not_going": 1, "total": 2}
    assert {attendee["user_id"] for attendee in body["attendees"]} == {member_id, other_id}
    assert all("email" in attendee and "phone" in attendee for attendee in body["attendees"])


@pytest.mark.integration
def test_event_rsvp_rolls_back_a_late_attendee_insert_failure(
    auth_harness: AuthHarness, monkeypatch: pytest.MonkeyPatch
) -> None:
    """The registration transaction cannot leave an attendee row after a late repository error."""
    organizer_id, _, _ = auth_harness.create_user(user_role="event admin")
    member_id, _, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        inserted = connection.execute(
            EVENTS_TABLE.insert().values(
                title="RSVP rollback event",
                event_banner="",
                status="upcoming",
                created_by=organizer_id,
                start_date=date(2027, 1, 11),
                visibility="public",
                is_approved=1,
                max_attendees=0,
            )
        )
        inserted_key = inserted.inserted_primary_key
        assert inserted_key is not None
        event_id = int(inserted_key[0])

    original = EventRepository.create_attendee

    def fail_after_insert(repository: EventRepository, values: dict[str, Any]) -> int:
        original(repository, values)
        raise RuntimeError("synthetic attendee insert failure")

    monkeypatch.setattr(EventRepository, "create_attendee", fail_after_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic attendee insert failure"),
    ):
        EventService(session).register(
            member_id,
            EventRegistrationRequest(event_id=event_id, status="going"),
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(EventAttendees.id)).where(
                    EventAttendees.event_id == event_id,
                    EventAttendees.user_id == member_id,
                )
            )
            == 0
        )


@pytest.mark.integration
def test_event_registration_forms_are_permissioned_validated_and_snapshot_answers(
    auth_harness: AuthHarness,
) -> None:
    """Forms are event-admin managed; a member RSVP and immutable answer snapshots are atomic."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="event admin")
    member_id, member_email, _ = auth_harness.create_user()
    other_id, other_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        inserted = connection.execute(
            EVENTS_TABLE.insert().values(
                title="Structured answers event",
                event_banner="",
                status="upcoming",
                created_by=admin_id,
                start_date=date(2027, 1, 12),
                visibility="public",
                is_approved=1,
                max_attendees=5,
            )
        )
        inserted_key = inserted.inserted_primary_key
        assert inserted_key is not None
        event_id = int(inserted_key[0])
    admin_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, admin_id, admin_email)}"
    }
    member_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, member_id, member_email)}"
    }
    other_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, other_id, other_email)}"
    }

    created = auth_harness.client.post(
        "/api/create_event_registration_form",
        headers=admin_headers,
        json={
            "event_id": event_id,
            "name": "Food preferences",
            "questions": [
                {
                    "label": "Meal",
                    "type": "dropdown",
                    "required": True,
                    "options": ["Rice", "Pasta"],
                    "sort_order": 0,
                },
                {
                    "label": "Allergies",
                    "type": "checkbox",
                    "required": False,
                    "options": ["Peanuts", "Dairy"],
                    "maxSelections": 1,
                    "sort_order": 1,
                },
            ],
        },
    )
    assert created.status_code == 200
    form = created.json()["form"]
    form_id = int(form["id"])
    meal_id = int(form["questions"][0]["id"])
    allergies_id = int(form["questions"][1]["id"])
    event_with_forms = auth_harness.client.post("/api/get_events", json={"id": event_id})
    assert event_with_forms.status_code == 200
    assert event_with_forms.json()["event"]["has_registration_questions"] is True
    assert event_with_forms.json()["event"]["registration_form_count"] == 1

    added_question = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "add_question",
            "formId": form_id,
            "question": {"label": "Accessibility note", "type": "long_answer"},
        },
    )
    assert added_question.status_code == 200
    accessibility_id = int(added_question.json()["form"]["questions"][2]["id"])
    updated_question = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "update_question",
            "form_id": form_id,
            "question_id": accessibility_id,
            "question": {"label": "Accessibility requirements", "type": "long_answer"},
        },
    )
    assert updated_question.status_code == 200
    reordered_questions = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "reorder_questions",
            "form_id": form_id,
            "order": [accessibility_id, meal_id, allergies_id],
        },
    )
    assert reordered_questions.status_code == 200
    updated_form = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "update_form",
            "form_id": form_id,
            "name": "Food and access preferences",
            "description": "Used only to prepare this event.",
        },
    )
    assert updated_form.status_code == 200
    assert updated_form.json()["form"]["version"] == 5

    second_form = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "upsert",
            "event_id": event_id,
            "name": "Optional arrival details",
            "sort_order": 1,
            "questions": [{"label": "Arrival time", "type": "short_answer"}],
        },
    )
    assert second_form.status_code == 200
    second_form_id = int(second_form.json()["form"]["id"])
    reordered_forms = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={
            "action": "reorder_forms",
            "eventId": event_id,
            "forms": [
                {"formId": form_id, "sortOrder": 0},
                {"formId": second_form_id, "sortOrder": 1},
            ],
        },
    )
    assert reordered_forms.status_code == 200

    member_forms = auth_harness.client.post(
        "/api/get_event_registration_forms",
        headers=member_headers,
        json={"eventId": event_id},
    )
    assert member_forms.status_code == 200
    assert [item["id"] for item in member_forms.json()["forms"]] == [form_id, second_form_id]
    admin_forms = auth_harness.client.post(
        "/api/get_event_registration_forms",
        headers=admin_headers,
        json={"event_id": event_id, "include_inactive": True},
    )
    assert len(admin_forms.json()["forms"]) == 2

    missing_required = auth_harness.client.post(
        "/api/register_event_with_forms",
        headers=member_headers,
        json={"event_id": event_id, "answers": []},
    )
    assert missing_required.status_code == 400
    assert missing_required.json()["code"] == "event_form_answers_invalid"

    too_many_checkbox_answers = auth_harness.client.post(
        "/api/register_event_with_forms",
        headers=member_headers,
        json={
            "event_id": event_id,
            "answers": [
                {"form_id": form_id, "question_id": meal_id, "value": "Rice"},
                {
                    "form_id": form_id,
                    "question_id": allergies_id,
                    "value": ["Peanuts", "Dairy"],
                },
            ],
        },
    )
    assert too_many_checkbox_answers.status_code == 400
    assert too_many_checkbox_answers.json()["code"] == "event_form_answers_invalid"

    registered = auth_harness.client.post(
        "/api/register_event_with_forms",
        headers=member_headers,
        json={
            "eventId": event_id,
            "rsvpStatus": "going",
            "additionalInfo": "No extra note",
            "user_id": other_id,
            "answers": [
                {"formId": form_id, "questionId": meal_id, "value": "Rice"},
                {
                    "formId": form_id,
                    "questionId": allergies_id,
                    "value": ["Peanuts"],
                },
            ],
        },
    )
    assert registered.status_code == 200
    attendee_id = int(registered.json()["rsvp"]["id"])
    assert registered.json()["rsvp"]["user_id"] == member_id
    assert registered.json()["answers_saved"] == 2

    deleted_question = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "delete_question", "form_id": form_id, "question_id": accessibility_id},
    )
    assert deleted_question.status_code == 200
    assert deleted_question.json()["form"]["version"] == 6

    forbidden_submissions = auth_harness.client.post(
        "/api/get_event_registration_submissions",
        headers=other_headers,
        json={"event_id": event_id},
    )
    assert forbidden_submissions.status_code == 403

    submissions = auth_harness.client.post(
        "/api/get_event_registration_submissions",
        headers=admin_headers,
        json={"event_id": event_id, "page": 1, "per_page": 20},
    )
    assert submissions.status_code == 200
    assert submissions.json()["registrations"][0]["answer_count"] == 2
    assert submissions.json()["registrations"][0]["has_form_answers"] is True

    detail = auth_harness.client.post(
        "/api/get_event_registration_submission_detail",
        headers=admin_headers,
        json={"eventId": event_id, "userId": member_id},
    )
    assert detail.status_code == 200
    assert detail.json()["registration"]["attendee_id"] == attendee_id
    answers = detail.json()["registration"]["forms"][0]["answers"]
    assert [answer["answer"] for answer in answers] == ["Rice", ["Peanuts"]]
    assert answers[0]["options"] == ["Rice", "Pasta"]
    assert answers[1]["max_selections"] == 1

    archived = auth_harness.client.post(
        "/api/manage_event_registration_form",
        headers=admin_headers,
        json={"action": "archive", "form_id": form_id},
    )
    assert archived.status_code == 200
    assert archived.json()["form"]["is_active"] is False
    assert [
        item["id"]
        for item in auth_harness.client.post(
            "/api/get_event_registration_forms", headers=member_headers, json={"event_id": event_id}
        ).json()["forms"]
    ] == [second_form_id]
    preserved_detail = auth_harness.client.post(
        "/api/get_event_registration_submission_detail",
        headers=admin_headers,
        json={"event_id": event_id, "attendee_id": attendee_id},
    )
    assert preserved_detail.status_code == 200
    assert preserved_detail.json()["registration"]["forms"][0]["answers"][0]["label"] == "Meal"
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(EventRegistrationForms.id)).where(
                    EventRegistrationForms.id == form_id
                )
            )
            == 1
        )
        assert (
            connection.scalar(
                select(func.count(EventRegistrationFormQuestions.id)).where(
                    EventRegistrationFormQuestions.form_id == form_id
                )
            )
            == 2
        )
        assert (
            connection.scalar(
                select(func.count(EventRegistrationAnswers.id)).where(
                    EventRegistrationAnswers.attendee_id == attendee_id
                )
            )
            == 2
        )
        assert (
            connection.scalar(
                select(func.count(EventRegistrationFormVersions.id)).where(
                    EventRegistrationFormVersions.form_id == form_id
                )
            )
            == 6
        )


@pytest.mark.integration
def test_geography_management_uses_current_database_permission_and_bounded_contracts(
    auth_harness: AuthHarness,
) -> None:
    """JWT claims cannot grant writes; current admin and super-admin facts can."""
    chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user()
    coordinator_id, _coordinator_email, _ = auth_harness.create_user()
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((actor_id, coordinator_id)))
            .values(chapter_id=chapter_id)
        )

    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")
    missing_auth = auth_harness.client.post(
        "/api/manage_zone",
        json={"action": "create", "zone": "Unauthorized Zone", "chapter_id": chapter_id},
    )
    forged_zone = auth_harness.client.post(
        "/api/manage_zone",
        headers={"Authorization": f"Bearer {forged}"},
        json={"action": "create", "zone": "Forged Zone", "chapter_id": chapter_id},
    )
    forged_city = auth_harness.client.post(
        "/api/manage_city",
        headers={"Authorization": f"Bearer {forged}"},
        json={"action": "delete", "city_id": 2_000_000_000},
    )
    assert missing_auth.status_code == 401
    assert forged_zone.status_code == forged_city.status_code == 403
    assert forged_zone.json()["code"] == forged_city.json()["code"] == "geography_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
    stale = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    headers = {"Authorization": f"Bearer {stale}"}
    suffix = uuid.uuid4().hex[:8]
    zone_name = f"Managed Zone {suffix}"
    created_zone = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={
            "action": " CREATE ",
            "zone": f"  Managed   Zone {suffix}  ",
            "chapter_id": chapter_id,
            "coordinator_user_id": coordinator_id,
            "token": "ignored",
        },
    )
    assert created_zone.status_code == 200
    zone_id = created_zone.json()["zone_id"]
    assert created_zone.json() == {
        "status": 200,
        "message": "Zone created successfully",
        "zone_id": zone_id,
    }
    auth_harness.zone_names.append(zone_name)

    city_name = f"Managed City {suffix}"
    created_city = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        data={
            "action": "create",
            "city": city_name,
            "zone_id": str(zone_id),
            "chapter_id": str(chapter_id),
            "user_role": "ignored",
        },
    )
    assert created_city.status_code == 200
    city_id = created_city.json()["city_id"]
    assert created_city.json() == {
        "status": 200,
        "message": "City created successfully",
        "city_id": city_id,
    }
    auth_harness.city_names.append(city_name)

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="super admin")
        )
    revised_zone_name = f"Managed Zone Revised {suffix}"
    updated_zone = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        data={
            "action": "update",
            "zone_id": str(zone_id),
            "zone": revised_zone_name,
            "coordinator_user_id": "",
        },
    )
    assert updated_zone.status_code == 200
    assert updated_zone.json() == {"status": 200, "message": "Zone updated successfully"}
    auth_harness.zone_names.append(revised_zone_name)
    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(Zones.zone, Zones.coordinator_user_id, Zones.chapter_id).where(
                Zones.zone_id == zone_id
            )
        ).one()
    assert tuple(stored) == (revised_zone_name, None, chapter_id)

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == actor_id).values(active=0))
    inactive = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "delete", "city_id": city_id},
    )
    assert inactive.status_code == 401
    assert inactive.json()["code"] == "geography_actor_unavailable"


@pytest.mark.integration
def test_zone_management_rejects_duplicates_invalid_coordinators_and_orphans(
    auth_harness: AuthHarness,
) -> None:
    """Zone writes preserve unique names, valid assignments, and city relationships."""
    first_chapter_id = auth_harness.create_chapter()
    second_chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user(user_role="admin")
    inactive_id, _inactive_email, _ = auth_harness.create_user(active=0)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id.in_((actor_id, inactive_id)))
            .values(chapter_id=first_chapter_id)
        )
    zone_id, city_ids = auth_harness.create_zone(
        chapter_id=first_chapter_id,
        cities=(f"Zone Child City {uuid.uuid4().hex[:8]}",),
    )
    zone_name = auth_harness.zone_names[-1]
    city_id = next(iter(city_ids.values()))
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}

    duplicate = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={
            "action": "create",
            "zone": f"  {zone_name.upper()}  ",
            "chapter_id": first_chapter_id,
        },
    )
    ineligible = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={
            "action": "create",
            "zone": f"Ineligible Zone {uuid.uuid4().hex[:8]}",
            "chapter_id": first_chapter_id,
            "coordinator_user_id": inactive_id,
        },
    )
    missing_chapter = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={
            "action": "create",
            "zone": f"Orphan Zone {uuid.uuid4().hex[:8]}",
            "chapter_id": 2_000_000_000,
        },
    )
    blocked_delete = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={"action": "delete", "zone_id": zone_id},
    )
    assert duplicate.status_code == ineligible.status_code == blocked_delete.status_code == 409
    assert duplicate.json()["code"] == "zone_already_exists"
    assert ineligible.json()["code"] == "coordinator_ineligible"
    assert missing_chapter.status_code == 404
    assert missing_chapter.json()["code"] == "chapter_not_found"
    assert blocked_delete.json()["code"] == "zone_has_cities"

    moved = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={"action": "update", "zone_id": zone_id, "chapter_id": second_chapter_id},
    )
    assert moved.status_code == 200
    with auth_harness.engine.connect() as connection:
        zone_chapter = connection.scalar(select(Zones.chapter_id).where(Zones.zone_id == zone_id))
        city_chapter = connection.scalar(select(Cities.chapter_id).where(Cities.city_id == city_id))
    assert zone_chapter == city_chapter == second_chapter_id

    deleted_city = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "delete", "city_id": city_id},
    )
    deleted_zone = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={"action": "delete", "zone_id": zone_id},
    )
    missing_zone = auth_harness.client.post(
        "/api/manage_zone",
        headers=headers,
        json={"action": "update", "zone_id": zone_id, "zone": zone_name},
    )
    assert deleted_city.json() == {"status": 200, "message": "City deleted successfully"}
    assert deleted_zone.json() == {"status": 200, "message": "Zone deleted successfully"}
    assert missing_zone.status_code == 404
    assert missing_zone.json()["code"] == "zone_not_found"


@pytest.mark.integration
def test_city_management_aligns_zone_chapters_and_protects_member_locations(
    auth_harness: AuthHarness,
) -> None:
    """City moves remain consistent while referenced names cannot be renamed or deleted."""
    first_chapter_id = auth_harness.create_chapter()
    second_chapter_id = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user(user_role="admin")
    first_zone_id, _ = auth_harness.create_zone(chapter_id=first_chapter_id)
    second_zone_id, _ = auth_harness.create_zone(chapter_id=second_chapter_id)
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"}
    suffix = uuid.uuid4().hex[:8]
    city_name = f"Movable City {suffix}"

    created = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "create", "city": city_name, "zone_id": first_zone_id},
    )
    assert created.status_code == 200
    city_id = created.json()["city_id"]
    auth_harness.city_names.append(city_name)
    duplicate = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        data={
            "action": "create",
            "city": f"  {city_name.upper()}  ",
            "zone_id": str(first_zone_id),
        },
    )
    mismatch = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={
            "action": "update",
            "city_id": city_id,
            "zone_id": first_zone_id,
            "chapter_id": second_chapter_id,
        },
    )
    assert duplicate.status_code == mismatch.status_code == 409
    assert duplicate.json()["code"] == "city_already_exists"
    assert mismatch.json()["code"] == "city_chapter_mismatch"

    moved = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "update", "city_id": city_id, "zone_id": second_zone_id},
    )
    assert moved.json() == {"status": 200, "message": "City updated successfully"}
    with auth_harness.engine.connect() as connection:
        stored = connection.execute(
            select(Cities.zone_id, Cities.chapter_id).where(Cities.city_id == city_id)
        ).one()
    assert tuple(stored) == (second_zone_id, second_chapter_id)

    auth_harness.create_user(city=f"  {city_name.upper()}  ")
    rename = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "update", "city_id": city_id, "city": f"Renamed City {suffix}"},
    )
    delete = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "delete", "city_id": city_id},
    )
    invalid = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "update", "city_id": city_id},
    )
    missing = auth_harness.client.post(
        "/api/manage_city",
        headers=headers,
        json={"action": "delete", "city_id": 2_000_000_000},
    )
    assert rename.status_code == delete.status_code == 409
    assert rename.json()["code"] == delete.json()["code"] == "city_in_use"
    assert invalid.status_code == 400
    assert invalid.json()["code"] == "city_management_invalid_request"
    assert missing.status_code == 404
    assert missing.json()["code"] == "city_not_found"


@pytest.mark.integration
def test_geography_mutations_roll_back_after_late_repository_failures(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Zone inserts and city updates remain atomic when persistence fails late."""
    chapter_id = auth_harness.create_chapter()
    actor_id, _actor_email, _ = auth_harness.create_user(user_role="admin")
    rollback_zone_name = f"Rollback Zone {uuid.uuid4().hex[:8]}"
    original_insert_zone = MemberRepository.insert_zone

    def fail_after_zone_insert(self: MemberRepository, *args: Any, **kwargs: Any) -> int:
        original_insert_zone(self, *args, **kwargs)
        raise RuntimeError("synthetic zone insert failure")

    monkeypatch.setattr(MemberRepository, "insert_zone", fail_after_zone_insert)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic zone insert failure"),
    ):
        MemberService(session, auth_harness.settings).manage_zone(
            actor_id,
            ManageZoneRequest(
                action="create",
                zone=rollback_zone_name,
                chapter_id=chapter_id,
            ),
        )
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(Zones.zone_id)).where(Zones.zone == rollback_zone_name)
            )
            == 0
        )

    monkeypatch.setattr(MemberRepository, "insert_zone", original_insert_zone)
    original_city = f"Rollback City {uuid.uuid4().hex[:8]}"
    revised_city = f"Rollback City Revised {uuid.uuid4().hex[:8]}"
    _zone_id, city_ids = auth_harness.create_zone(
        chapter_id=chapter_id,
        cities=(original_city,),
    )
    city_id = city_ids[original_city]
    original_update_city = MemberRepository.update_city

    def fail_after_city_update(self: MemberRepository, *args: Any, **kwargs: Any) -> None:
        original_update_city(self, *args, **kwargs)
        raise RuntimeError("synthetic city update failure")

    monkeypatch.setattr(MemberRepository, "update_city", fail_after_city_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic city update failure"),
    ):
        MemberService(session, auth_harness.settings).manage_city(
            actor_id,
            ManageCityRequest(action="update", city_id=city_id, city=revised_city),
        )
    with auth_harness.engine.connect() as connection:
        stored_city = connection.scalar(select(Cities.city).where(Cities.city_id == city_id))
    assert stored_city == original_city


@pytest.mark.integration
def test_geography_import_requires_current_permission_and_reconciles_csv_atomically(
    auth_harness: AuthHarness,
) -> None:
    """Current database permission controls an idempotent, chapter-aligned bulk import."""
    chapter_id = auth_harness.create_chapter(chapter_id=1)
    actor_id, actor_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")
    suffix = uuid.uuid4().hex[:8]
    denied_zone = f"Denied Import Zone {suffix}"
    denied = auth_harness.client.post(
        "/api/upload_zones_cities",
        headers={"Authorization": f"Bearer {forged}", "X-API-Key": "ignored"},
        files={
            "file": (
                "locations.csv",
                f"zone,city\n{denied_zone},Denied City {suffix}\n".encode(),
                "text/csv",
            )
        },
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "geography_forbidden"

    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
    stale = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    headers = {"Authorization": f"Bearer {stale}"}
    existing_zone_id, existing_city_ids = auth_harness.create_zone(
        chapter_id=chapter_id,
        cities=(f"Imported Existing City {suffix}",),
    )
    existing_city = next(iter(existing_city_ids))
    new_zone = f"Imported New Zone {suffix}"
    new_city = f"Imported New City {suffix}"
    csv_content = (f"city,zone\r\n{existing_city},{new_zone}\r\n{new_city},{new_zone}\r\n").encode()

    imported = auth_harness.client.post(
        "/api/upload_zones_cities",
        headers=headers,
        files={"file": ("locations.csv", csv_content, "text/csv")},
    )
    assert imported.status_code == 200
    assert imported.json() == {
        "status": 200,
        "message": "Upload processed successfully",
        "summary": {
            "zones_added": 1,
            "cities_added": 1,
            "cities_updated": 1,
            "total_rows": 2,
        },
    }
    auth_harness.zone_names.append(new_zone)
    auth_harness.city_names.append(new_city)

    with auth_harness.engine.connect() as connection:
        new_zone_row = connection.execute(
            select(Zones.zone_id, Zones.chapter_id).where(Zones.zone == new_zone)
        ).one()
        existing_mapping = connection.execute(
            select(Cities.zone_id, Cities.chapter_id).where(Cities.city == existing_city)
        ).one()
        new_mapping = connection.execute(
            select(Cities.zone_id, Cities.chapter_id).where(Cities.city == new_city)
        ).one()
    new_zone_id, new_zone_chapter = map(int, new_zone_row)
    assert new_zone_chapter == 1
    assert tuple(map(int, existing_mapping)) == (new_zone_id, 1)
    assert tuple(map(int, new_mapping)) == (new_zone_id, 1)
    assert new_zone_id != existing_zone_id

    repeated = auth_harness.client.post(
        "/api/upload_zones_cities",
        headers=headers,
        files={
            "file": (
                "locations.xlsx",
                _geography_xlsx_bytes([(new_zone, existing_city), (new_zone, new_city)]),
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            )
        },
    )
    assert repeated.json()["summary"] == {
        "zones_added": 0,
        "cities_added": 0,
        "cities_updated": 0,
        "total_rows": 2,
    }


@pytest.mark.integration
def test_geography_import_rolls_back_every_row_after_a_late_write_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A failure after an earlier insert leaves no partial zone or city catalogue."""
    auth_harness.create_chapter(chapter_id=1)
    actor_id, _actor_email, _ = auth_harness.create_user(user_role="admin")
    suffix = uuid.uuid4().hex[:8]
    zone_name = f"Atomic Import Zone {suffix}"
    first_city = f"Atomic First City {suffix}"
    second_city = f"Atomic Second City {suffix}"
    original_insert_city = MemberRepository.insert_city
    insert_count = 0

    def fail_on_second_city(self: MemberRepository, *args: Any, **kwargs: Any) -> int:
        nonlocal insert_count
        insert_count += 1
        result = original_insert_city(self, *args, **kwargs)
        if insert_count == 2:
            raise RuntimeError("synthetic bulk city failure")
        return result

    monkeypatch.setattr(MemberRepository, "insert_city", fail_on_second_city)
    rows = (
        GeographyImportRow(source_row=2, zone=zone_name, city=first_city),
        GeographyImportRow(source_row=3, zone=zone_name, city=second_city),
    )
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic bulk city failure"),
    ):
        MemberService(session, auth_harness.settings).upload_zones_cities(actor_id, rows)

    with auth_harness.engine.connect() as connection:
        zone_count = connection.scalar(
            select(func.count(Zones.zone_id)).where(Zones.zone == zone_name)
        )
        city_count = connection.scalar(
            select(func.count(Cities.city_id)).where(Cities.city.in_((first_city, second_city)))
        )
    assert zone_count == city_count == 0


@pytest.mark.integration
def test_geography_import_rejects_ambiguous_existing_zones_before_writing(
    auth_harness: AuthHarness,
) -> None:
    """Legacy duplicate zone names fail closed instead of selecting an arbitrary target."""
    auth_harness.create_chapter(chapter_id=1)
    actor_id, actor_email, _ = auth_harness.create_user(user_role="admin")
    suffix = uuid.uuid4().hex[:8]
    first_zone = f"Ambiguous Zone {suffix}"
    second_zone = f"  {first_zone.upper()}  "
    city_name = f"Unwritten Ambiguous City {suffix}"
    with auth_harness.engine.begin() as connection:
        connection.execute(
            ZONES_TABLE.insert(),
            [
                {"zone": first_zone, "chapter_id": 1},
                {"zone": second_zone, "chapter_id": 1},
            ],
        )
    auth_harness.zone_names.extend((first_zone, second_zone))
    response = auth_harness.client.post(
        "/api/upload_zones_cities",
        headers={
            "Authorization": f"Bearer {_access_token_for(auth_harness, actor_id, actor_email)}"
        },
        files={
            "file": (
                "locations.csv",
                f"zone,city\n{first_zone},{city_name}\n".encode(),
                "text/csv",
            )
        },
    )

    assert response.status_code == 409
    assert response.json()["code"] == "geography_import_ambiguous_zone"
    with auth_harness.engine.connect() as connection:
        city_count = connection.scalar(
            select(func.count(Cities.city_id)).where(Cities.city == city_name)
        )
    assert city_count == 0


@pytest.mark.integration
def test_alumni_import_uses_current_permission_preserves_accounts_and_is_idempotent(
    auth_harness: AuthHarness,
) -> None:
    """The hardened roster import owns privileges and credentials but reconciles profile data."""
    chapter_id, city = auth_harness.create_registration_location()
    actor_id, actor_email, _ = auth_harness.create_user()
    forged = _access_token_for(auth_harness, actor_id, actor_email, user_role="super admin")
    denied = auth_harness.client.post(
        "/api/import_alumni",
        headers={"Authorization": f"Bearer {forged}", "X-API-Key": "ignored"},
        json={
            "chapter_id": chapter_id,
            "records": [
                {
                    "email": f"denied-{auth_harness.marker}@example.com",
                    "last_name": "Imported",
                    "first_name": "Synthetic Ada",
                    "graduation_year": datetime.now(UTC).year,
                    "city": city,
                }
            ],
        },
    )
    assert denied.status_code == 403
    assert denied.json()["code"] == "alumni_import_forbidden"

    existing_id, existing_email, existing_password = auth_harness.create_user(
        active=0,
        email_verified=0,
        is_approved=0,
        profile_status="pending",
    )
    suffix = uuid.uuid4().hex[:8]
    kept_code = f"KEEP-{suffix}"
    kept_access_code = f"PRIVATE-{suffix}"
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == actor_id).values(user_role="admin")
        )
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == existing_id)
            .values(
                has_password=1,
                is_coordinator=1,
                user_code=kept_code,
                userAccessCode=kept_access_code,
            )
        )
        category_result = connection.execute(
            ALUMNI_CATEGORY_TABLE.insert().values(
                user_id=existing_id,
                chapter_id=chapter_id,
                year="1990",
                location="Old Location",
                created_at=now,
            )
        )
        category_key = category_result.inserted_primary_key
        assert category_key is not None
        auth_harness.alumni_category_ids.append(int(category_key[0]))
        connection.execute(
            PROFILES_TABLE.insert().values(
                user_id=existing_id,
                instagram="keep_me",
                tiktok="keep_me",
                updated_at=now,
                chapter_id=chapter_id,
                year="1990",
                city="Old Location",
                is_visible=1,
                field_visibility='{"phone":true}',
            )
        )

    new_email = f"codex-import-{auth_harness.marker}@example.com"
    auth_harness.emails.append(new_email)
    stale = _access_token_for(auth_harness, actor_id, actor_email, user_role="alumni")
    headers = {"Authorization": f"Bearer {stale}"}
    records = [
        {
            "email": existing_email,
            "last_name": "Imported",
            "first_name": "Synthetic Ada",
            "name_in_school": "Synthetic School Name",
            "phone": "08000000001",
            "birth_date": "1990-01-02",
            "graduation_year": datetime.now(UTC).year,
            "city": city,
            "is_coordinator": True,
            "is_volunteer": True,
        },
        {
            "email": new_email,
            "last_name": "Imported",
            "first_name": "Synthetic Ada",
            "graduation_year": datetime.now(UTC).year,
            "city": city,
            "is_coordinator": True,
        },
    ]
    imported = auth_harness.client.post(
        "/api/import_alumni",
        headers=headers,
        json={"chapter_id": chapter_id, "records": records},
    )

    assert imported.status_code == 200
    body = imported.json()
    assert body["summary"] == {
        "total": 2,
        "imported": 1,
        "updated": 1,
        "unchanged": 0,
        "coordinator_requests_ignored": 2,
    }
    assert [result["status"] for result in body["results"]] == ["updated", "imported"]
    assert all(set(result) == {"row", "status", "user_id"} for result in body["results"])

    with auth_harness.engine.connect() as connection:
        existing = dict(
            connection.execute(select(USERS_TABLE).where(Users.id == existing_id)).mappings().one()
        )
        existing_profile = dict(
            connection.execute(select(PROFILES_TABLE).where(UserProfiles.user_id == existing_id))
            .mappings()
            .one()
        )
        new_user = dict(
            connection.execute(select(USERS_TABLE).where(Users.email == new_email)).mappings().one()
        )
        new_id = int(new_user["id"])
        new_category = dict(
            connection.execute(
                select(ALUMNI_CATEGORY_TABLE).where(AlumniCategory.user_id == new_id)
            )
            .mappings()
            .one()
        )
        new_profile = dict(
            connection.execute(select(PROFILES_TABLE).where(UserProfiles.user_id == new_id))
            .mappings()
            .one()
        )
        new_group = connection.scalar(
            select(UsersGroups.id).where(
                UsersGroups.user_id == new_id,
                UsersGroups.group_id == 2,
            )
        )

    assert existing["password"] == existing_password
    assert existing["user_code"] == kept_code
    assert existing["userAccessCode"] == kept_access_code
    assert (existing["active"], existing["is_approved"], existing["email_verified"]) == (0, 0, 0)
    assert (existing["user_role"], existing["is_coordinator"], existing["profile_status"]) == (
        "alumni",
        1,
        "pending",
    )
    assert existing_profile["field_visibility"] == '{"phone":true}'
    assert existing_profile["is_visible"] == 1
    assert (existing_profile["city"], existing_profile["chapter_id"]) == (city, chapter_id)

    assert (new_user["user_role"], new_user["is_coordinator"]) == ("alumni", 0)
    assert (new_user["active"], new_user["is_approved"], new_user["email_verified"]) == (1, 1, 1)
    assert new_user["has_password"] == 0
    assert new_user["userAccessCode"] == ""
    assert new_user["user_code"].startswith(f"MBR-{datetime.now(UTC).year}-")
    assert not PasswordService().verify("Alumni@2026", new_user["password"])
    assert not PasswordService().verify(PASSPHRASE, new_user["password"])
    assert new_group is not None
    assert (new_category["chapter_id"], new_category["location"]) == (chapter_id, city)
    assert new_profile["is_visible"] == 0
    assert all(value is False for value in json.loads(new_profile["field_visibility"]).values())

    repeated = auth_harness.client.post(
        "/api/import_alumni",
        headers=headers,
        json={"chapter_id": chapter_id, "records": records},
    )
    assert repeated.status_code == 200
    assert repeated.json()["summary"] == {
        "total": 2,
        "imported": 0,
        "updated": 0,
        "unchanged": 2,
        "coordinator_requests_ignored": 2,
    }


@pytest.mark.integration
def test_alumni_import_rolls_back_all_rows_after_a_late_profile_failure(
    auth_harness: AuthHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A write failure on a later member leaves no account, category, group, or profile rows."""
    chapter_id, city = auth_harness.create_registration_location()
    actor_id, _actor_email, _ = auth_harness.create_user(user_role="admin")
    first_email = f"codex-atomic-first-{auth_harness.marker}@example.com"
    second_email = f"codex-atomic-second-{auth_harness.marker}@example.com"
    auth_harness.emails.extend((first_email, second_email))
    original = MemberRepository.insert_alumni_import_profile
    inserts = 0

    def fail_on_second_profile(self: MemberRepository, **kwargs: Any) -> None:
        nonlocal inserts
        inserts += 1
        original(self, **kwargs)
        if inserts == 2:
            raise RuntimeError("synthetic alumni profile failure")

    monkeypatch.setattr(MemberRepository, "insert_alumni_import_profile", fail_on_second_profile)
    rows = (
        _alumni_import_row(first_email, city, source_row=1),
        _alumni_import_row(second_email, city, source_row=2),
    )
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic alumni profile failure"),
    ):
        MemberService(session, auth_harness.settings).import_alumni(
            actor_id,
            chapter_id,
            rows,
            "127.0.0.1",
        )

    with auth_harness.engine.connect() as connection:
        user_count = connection.scalar(
            select(func.count(Users.id)).where(Users.email.in_((first_email, second_email)))
        )
        category_count = connection.scalar(
            select(func.count(AlumniCategory.id)).where(
                AlumniCategory.user_id.in_(
                    select(Users.id).where(Users.email.in_((first_email, second_email)))
                )
            )
        )
    assert user_count == category_count == 0


@pytest.mark.integration
def test_alumni_import_rejects_higher_targets_and_ambiguous_memberships_before_writing(
    auth_harness: AuthHarness,
) -> None:
    """Roster reconciliation fails closed for peer/higher accounts and legacy multi-membership."""
    chapter_id, city = auth_harness.create_registration_location()
    other_chapter = auth_harness.create_chapter()
    actor_id, actor_email, _ = auth_harness.create_user(user_role="admin")
    target_id, target_email, _ = auth_harness.create_user(user_role="admin")
    token = _access_token_for(auth_harness, actor_id, actor_email)
    records = [
        {
            "email": target_email,
            "last_name": "Imported",
            "first_name": "Synthetic Ada",
            "graduation_year": datetime.now(UTC).year,
            "city": city,
        }
    ]
    forbidden = auth_harness.client.post(
        "/api/import_alumni",
        headers={"Authorization": f"Bearer {token}"},
        json={"chapter_id": chapter_id, "records": records},
    )
    assert forbidden.status_code == 403
    assert forbidden.json()["code"] == "alumni_import_target_forbidden"

    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update().where(Users.id == target_id).values(user_role="alumni")
        )
        result = connection.execute(
            ALUMNI_CATEGORY_TABLE.insert(),
            [
                {
                    "user_id": target_id,
                    "chapter_id": chapter_id,
                    "year": "2000",
                    "location": city,
                    "created_at": now,
                },
                {
                    "user_id": target_id,
                    "chapter_id": other_chapter,
                    "year": "2001",
                    "location": "Other City",
                    "created_at": now,
                },
            ],
        )
        assert result.rowcount == 2
    ambiguous = auth_harness.client.post(
        "/api/import_alumni",
        headers={"Authorization": f"Bearer {token}"},
        json={"chapter_id": chapter_id, "records": records},
    )
    assert ambiguous.status_code == 409
    assert ambiguous.json()["code"] == "alumni_import_membership_ambiguous"


@pytest.mark.integration
def test_notifications_are_self_scoped_paginated_and_idempotently_marked(
    auth_harness: AuthHarness,
) -> None:
    """Legacy body targets cannot read or mutate another member's notification rows."""
    owner_id, owner_email, _ = auth_harness.create_user()
    other_id, _, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, owner_id, owner_email, user_role="super admin")
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        old_result = connection.execute(
            NOTIFICATIONS_TABLE.insert().values(
                user_id=owner_id,
                type="old",
                message="Before this synthetic account existed",
                is_read=0,
                created_at=datetime(2000, 1, 1, tzinfo=UTC).replace(tzinfo=None),
            )
        )
        first_result = connection.execute(
            NOTIFICATIONS_TABLE.insert().values(
                user_id=owner_id,
                type="event",
                message="Owned unread notification",
                link="https://frontend.example.test/events/1",
                is_read=0,
                created_at=now,
            )
        )
        second_result = connection.execute(
            NOTIFICATIONS_TABLE.insert().values(
                user_id=owner_id,
                type="account",
                message="Owned read notification",
                is_read=1,
                created_at=now + timedelta(seconds=1),
            )
        )
        other_result = connection.execute(
            NOTIFICATIONS_TABLE.insert().values(
                user_id=other_id,
                type="private",
                message="Other member notification",
                is_read=0,
                created_at=now,
            )
        )
        old_key = old_result.inserted_primary_key
        first_key = first_result.inserted_primary_key
        second_key = second_result.inserted_primary_key
        other_key = other_result.inserted_primary_key
        assert old_key is not None
        assert first_key is not None
        assert second_key is not None
        assert other_key is not None
        old_id = int(old_key[0])
        first_id = int(first_key[0])
        second_id = int(second_key[0])
        other_notification_id = int(other_key[0])

    headers = {"Authorization": f"Bearer {token}"}
    response = auth_harness.client.get(
        "/api/get_notifications", headers=headers, params={"limit": 1}
    )
    assert response.status_code == 200
    body = response.json()
    assert body["count"] == 1
    assert body["total"] == 2
    assert body["unread_count"] == 1
    assert body["has_more"] is True
    assert body["notifications"][0]["id"] == second_id
    assert "user_id" not in body["notifications"][0]

    spoofed = auth_harness.client.post(
        "/api/get_notifications",
        headers=headers,
        json={"user_id": other_id, "token": "ignored-legacy-value", "unread_only": True},
    )
    assert spoofed.status_code == 200
    assert [item["id"] for item in spoofed.json()["notifications"]] == [first_id]

    concealed = auth_harness.client.post(
        "/api/mark_notification_read",
        headers=headers,
        json={"notification_id": other_notification_id, "user_id": other_id},
    )
    assert concealed.status_code == 404
    assert concealed.json()["code"] == "notification_not_found"

    marked = auth_harness.client.post(
        "/api/mark_notification_read",
        headers=headers,
        json={"notification_id": first_id, "user_id": other_id},
    )
    assert marked.status_code == 200
    assert marked.json()["updated_count"] == 1
    repeated = auth_harness.client.post(
        "/api/mark_notification_read",
        headers=headers,
        json={"notification_id": first_id},
    )
    assert repeated.status_code == 200
    assert repeated.json()["updated_count"] == 0

    with auth_harness.engine.connect() as connection:
        states: dict[int, int | None] = {
            int(notification_id): is_read
            for notification_id, is_read in connection.execute(
                select(Notifications.id, Notifications.is_read).where(
                    Notifications.id.in_((old_id, first_id, second_id, other_notification_id))
                )
            ).tuples()
        }
    assert states == {old_id: 0, first_id: 1, second_id: 1, other_notification_id: 0}


@pytest.mark.integration
def test_mark_all_notifications_is_owned_bounded_and_transactional(
    auth_harness: AuthHarness, monkeypatch: pytest.MonkeyPatch
) -> None:
    """Mark-all updates owned rows atomically and rolls back a late repository failure."""
    owner_id, owner_email, _ = auth_harness.create_user()
    other_id, _, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, owner_id, owner_email)
    now = datetime.now(UTC).replace(tzinfo=None)
    with auth_harness.engine.begin() as connection:
        owner_ids = []
        for index in range(2):
            result = connection.execute(
                NOTIFICATIONS_TABLE.insert().values(
                    user_id=owner_id,
                    type="system",
                    message=f"Owned notification {index}",
                    is_read=0,
                    created_at=now + timedelta(seconds=index),
                )
            )
            inserted_key = result.inserted_primary_key
            assert inserted_key is not None
            owner_ids.append(int(inserted_key[0]))
        other_result = connection.execute(
            NOTIFICATIONS_TABLE.insert().values(
                user_id=other_id,
                type="system",
                message="Other notification",
                is_read=0,
                created_at=now,
            )
        )
        other_key = other_result.inserted_primary_key
        assert other_key is not None
        other_notification_id = int(other_key[0])

    original_mark_read = NotificationRepository.mark_read

    def fail_after_update(
        repository: NotificationRepository, user_id: int, notification_ids: list[int]
    ) -> int:
        original_mark_read(repository, user_id, notification_ids)
        raise RuntimeError("synthetic late notification failure")

    monkeypatch.setattr(NotificationRepository, "mark_read", fail_after_update)
    with (
        Session(auth_harness.engine) as session,
        pytest.raises(RuntimeError, match="synthetic late notification failure"),
    ):
        NotificationService(session).mark_read(owner_id, None)
    monkeypatch.setattr(NotificationRepository, "mark_read", original_mark_read)

    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(Notifications.id)).where(
                    Notifications.id.in_(owner_ids), Notifications.is_read == 0
                )
            )
            == 2
        )

    response = auth_harness.client.post(
        "/api/mark_notification_read",
        headers={"Authorization": f"Bearer {token}"},
        json={"user_id": other_id},
    )
    assert response.status_code == 200
    assert response.json() == {
        "status": 200,
        "message": "All unread notifications marked as read",
        "updated_count": 2,
    }
    with auth_harness.engine.connect() as connection:
        assert (
            connection.scalar(
                select(func.count(Notifications.id)).where(
                    Notifications.id.in_(owner_ids), Notifications.is_read == 1
                )
            )
            == 2
        )
        assert (
            connection.scalar(
                select(Notifications.is_read).where(Notifications.id == other_notification_id)
            )
            == 0
        )


@pytest.mark.integration
def test_notification_routes_recheck_current_account_state_and_validate_input(
    auth_harness: AuthHarness,
) -> None:
    """A valid stale token cannot bypass deactivation and malformed IDs fail safely."""
    user_id, email, _ = auth_harness.create_user()
    token = _access_token_for(auth_harness, user_id, email, user_role="admin")
    headers = {"Authorization": f"Bearer {token}"}
    invalid = auth_harness.client.post(
        "/api/mark_notification_read",
        headers=headers,
        json={"notification_id": 0},
    )
    assert invalid.status_code == 400
    assert invalid.json()["code"] == "notification_mark_invalid"

    with auth_harness.engine.begin() as connection:
        connection.execute(USERS_TABLE.update().where(Users.id == user_id).values(active=0))
    listed = auth_harness.client.get("/api/get_notifications", headers=headers)
    marked = auth_harness.client.post("/api/mark_notification_read", headers=headers, json={})
    assert listed.status_code == marked.status_code == 401
    assert listed.json()["code"] == marked.json()["code"] == "notification_actor_unavailable"


@pytest.mark.integration
def test_v2_chat_core_is_participant_scoped_idempotent_and_sender_owned(
    auth_harness: AuthHarness,
) -> None:
    """V2 chat denies cross-thread access and binds idempotency and deletion to the actor."""
    sender_id, sender_email, _ = auth_harness.create_user(fullname="Synthetic Sender")
    recipient_id, recipient_email, _ = auth_harness.create_user(fullname="Synthetic Recipient")
    outsider_id, outsider_email, _ = auth_harness.create_user(fullname="Synthetic Outsider")
    sender_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, sender_id, sender_email)}"
    }
    recipient_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, recipient_id, recipient_email)}"
    }
    outsider_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, outsider_id, outsider_email)}"
    }
    try:
        first_send = auth_harness.client.post(
            "/chat_api/v2_send_direct",
            headers=sender_headers,
            json={
                "recipient_id": recipient_id,
                "body": "A private synthetic message",
                "client_generated_id": "synthetic-direct-1",
            },
        )
        assert first_send.status_code == 200
        direct_thread_id = int(first_send.json()["data"]["thread_id"])
        direct_message_id = int(first_send.json()["data"]["message"]["id"])
        duplicate = auth_harness.client.post(
            "/chat_api/v2_send_direct",
            headers=sender_headers,
            json={
                "recipient_id": recipient_id,
                "body": "A private synthetic message",
                "client_generated_id": "synthetic-direct-1",
            },
        )
        assert duplicate.status_code == 200
        assert int(duplicate.json()["data"]["thread_id"]) == direct_thread_id
        assert int(duplicate.json()["data"]["message"]["id"]) == direct_message_id
        inbox = auth_harness.client.post("/chat_api/v2_get_threads", headers=recipient_headers)
        assert inbox.status_code == 200
        assert [item["thread_id"] for item in inbox.json()["threads"]] == [direct_thread_id]
        detail = auth_harness.client.post(
            "/chat_api/v2_get_thread",
            headers=recipient_headers,
            json={"thread_id": direct_thread_id},
        )
        assert detail.status_code == 200
        assert detail.json()["thread"]["messages"][0]["id"] == direct_message_id
        assert detail.json()["thread"]["unread_count"] == 0

        denied_detail = auth_harness.client.post(
            "/chat_api/v2_get_thread",
            headers=outsider_headers,
            json={"thread_id": direct_thread_id},
        )
        assert denied_detail.status_code == 403
        assert denied_detail.json()["code"] == "chat_membership_forbidden"

        group = auth_harness.client.post(
            "/chat_api/v2_create_group",
            headers=sender_headers,
            json={"title": "Synthetic group", "member_ids": [outsider_id]},
        )
        assert group.status_code == 200
        assert group.json()["thread_id"] == group.json()["thread"]["thread_id"]
        group_thread_id = int(group.json()["thread_id"])
        cross_thread_reply = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=outsider_headers,
            json={
                "thread_id": group_thread_id,
                "body": "Wrong-thread reply",
                "reply_to_message_id": direct_message_id,
            },
        )
        assert cross_thread_reply.status_code == 422
        assert cross_thread_reply.json()["code"] == "chat_reply_invalid"

        forbidden_delete = auth_harness.client.post(
            "/chat_api/v2_delete_message",
            headers=recipient_headers,
            json={"message_id": direct_message_id},
        )
        assert forbidden_delete.status_code == 404
        deleted = auth_harness.client.post(
            "/chat_api/v2_delete_message",
            headers=sender_headers,
            json={"message_id": direct_message_id},
        )
        assert deleted.status_code == 200
        marked = auth_harness.client.post(
            "/chat_api/v2_mark_read",
            headers=outsider_headers,
            json={"thread_id": group_thread_id},
        )
        assert marked.status_code == 200
        assert marked.json()["data"]["unread_count"] == 0
        retired_sync = auth_harness.client.post(
            "/chat_api/v2_sync_year_groups",
            headers=sender_headers,
            json={"sync_all": True},
        )
        assert retired_sync.status_code == 410
        assert retired_sync.json()["code"] == "chat_year_sync_unavailable"
        retired_legacy = auth_harness.client.post(
            "/chat_api/get_threads",
            headers=sender_headers,
        )
        assert retired_legacy.status_code == 410
        assert retired_legacy.json()["code"] == "chat_legacy_unavailable"
    finally:
        with auth_harness.engine.begin() as connection:
            thread_ids = list(
                connection.scalars(
                    select(MessageThreads.id).where(
                        MessageThreads.created_by.in_([sender_id, recipient_id, outsider_id])
                    )
                )
            )
            if thread_ids:
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids))
                )


@pytest.mark.integration
def test_v2_chat_inbox_is_bounded_and_reports_complete_unread_totals(
    auth_harness: AuthHarness,
) -> None:
    """The inbox is bounded, ordered, page-complete, and badges cover every thread."""
    owner_id, owner_email, _ = auth_harness.create_user(fullname="Synthetic Inbox Owner")
    peer_id, peer_email, _ = auth_harness.create_user(fullname="Synthetic Inbox Peer")
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    peer_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, peer_id, peer_email)}"
    }
    try:
        thread_ids: list[int] = []
        for index in range(4):
            created = auth_harness.client.post(
                "/chat_api/v2_create_group",
                headers=owner_headers,
                json={"title": f"Synthetic inbox {index}", "member_ids": [peer_id]},
            )
            assert created.status_code == 200
            thread_id = int(created.json()["thread_id"])
            thread_ids.append(thread_id)
            for sequence in range(2):
                sent = auth_harness.client.post(
                    "/chat_api/v2_send_message",
                    headers=peer_headers,
                    json={"thread_id": thread_id, "body": f"Synthetic unread {index}-{sequence}"},
                )
                assert sent.status_code == 200

        first_page = auth_harness.client.post(
            "/chat_api/v2_get_threads", headers=owner_headers, json={"limit": 2}
        )
        assert first_page.status_code == 200
        body = first_page.json()
        assert body["count"] == 2
        assert body["limit"] == 2
        assert body["offset"] == 0
        assert body["has_more"] is True
        # Pinned first, then newest activity, so the two most recent groups lead.
        assert [item["thread_id"] for item in body["threads"]] == [thread_ids[3], thread_ids[2]]
        # The page carries two unread messages per thread, but the badges count the
        # whole mailbox rather than only the rows on this page.
        assert [item["unread_count"] for item in body["threads"]] == [2, 2]
        assert body["unread_count"] == 8
        assert body["unread_thread_count"] == 4
        assert body["thread_total"] == 4
        assert all(len(item["participants"]) == 2 for item in body["threads"])

        second_page = auth_harness.client.post(
            "/chat_api/v2_get_threads", headers=owner_headers, json={"limit": 2, "offset": 2}
        )
        assert second_page.status_code == 200
        second_body = second_page.json()
        assert second_body["has_more"] is False
        assert second_body["count"] == 2
        assert {item["thread_id"] for item in second_body["threads"]} == set(thread_ids[:2])
        assert {item["thread_id"] for item in body["threads"]}.isdisjoint(
            item["thread_id"] for item in second_body["threads"]
        )

        # A body-less legacy call keeps working and receives the default page.
        default_page = auth_harness.client.post("/chat_api/v2_get_threads", headers=owner_headers)
        assert default_page.status_code == 200
        assert default_page.json()["limit"] == 100
        assert {item["thread_id"] for item in default_page.json()["threads"]} == set(thread_ids)

        rejected = auth_harness.client.post(
            "/chat_api/v2_get_threads", headers=owner_headers, json={"limit": 500}
        )
        assert rejected.status_code == 400
        assert rejected.json()["code"] == "chat_invalid_request"
    finally:
        with auth_harness.engine.begin() as connection:
            tracked_threads = list(
                connection.scalars(
                    select(MessageThreads.id).where(
                        MessageThreads.created_by.in_([owner_id, peer_id])
                    )
                )
            )
            if tracked_threads:
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(tracked_threads))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(tracked_threads)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(tracked_threads))
                )


@pytest.mark.integration
def test_v2_chat_attachments_are_private_owned_and_linked_only_by_the_stager(
    auth_harness: AuthHarness,
) -> None:
    """Chat uploads are content-checked, private, participant-gated, and one-message owned."""
    owner_id, owner_email, _ = auth_harness.create_user(fullname="Synthetic Attachment Owner")
    member_id, member_email, _ = auth_harness.create_user(fullname="Synthetic Attachment Member")
    outsider_id, outsider_email, _ = auth_harness.create_user(
        fullname="Synthetic Attachment Outsider"
    )
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    member_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, member_id, member_email)}"
    }
    outsider_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, outsider_id, outsider_email)}"
    }
    image_bytes = BytesIO()
    Image.new("RGB", (6, 4), color=(8, 16, 32)).save(image_bytes, format="PNG")
    try:
        group = auth_harness.client.post(
            "/chat_api/v2_create_group",
            headers=owner_headers,
            json={"title": "Synthetic private attachments", "member_ids": [member_id]},
        )
        assert group.status_code == 200
        thread_id = int(group.json()["thread_id"])
        empty_detail = auth_harness.client.post(
            "/chat_api/v2_get_thread", headers=owner_headers, json={"thread_id": thread_id}
        )
        assert empty_detail.status_code == 200
        assert empty_detail.json()["thread"]["messages"] == []
        empty_read = auth_harness.client.post(
            "/chat_api/v2_mark_read", headers=owner_headers, json={"thread_id": thread_id}
        )
        assert empty_read.status_code == 200
        staged = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"thread_id": str(thread_id)},
            files={"file": ("synthetic.png", image_bytes.getvalue(), "image/png")},
        )
        assert staged.status_code == 200
        attachment = staged.json()["data"]
        attachment_id = int(attachment["attachment_id"])
        assert attachment["download_path"] == f"/chat_api/v2_attachments/{attachment_id}"
        assert "public_url" not in attachment
        stored_files = list((auth_harness.settings.upload_root / "chat").glob("*"))
        assert len(stored_files) == 1

        staged_owner_download = auth_harness.client.get(
            attachment["download_path"], headers=owner_headers
        )
        assert staged_owner_download.status_code == 200
        assert staged_owner_download.headers["x-content-type-options"] == "nosniff"
        assert staged_owner_download.headers["cache-control"] == "private, no-store"
        staged_member_download = auth_harness.client.get(
            attachment["download_path"], headers=member_headers
        )
        assert staged_member_download.status_code == 404

        invalid_svg = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"thread_id": str(thread_id)},
            files={"file": ("unsafe.svg", b"<svg onload='alert(1)'/>", "image/svg+xml")},
        )
        assert invalid_svg.status_code == 400
        assert invalid_svg.json()["code"] == "chat_attachment_invalid_content"

        second_group = auth_harness.client.post(
            "/chat_api/v2_create_group",
            headers=owner_headers,
            json={"title": "Synthetic wrong thread", "member_ids": [member_id]},
        )
        assert second_group.status_code == 200
        wrong_thread = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={
                "thread_id": int(second_group.json()["thread_id"]),
                "attachment_ids": [attachment_id],
            },
        )
        assert wrong_thread.status_code == 403
        assert wrong_thread.json()["code"] == "chat_attachment_forbidden"

        sent = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={
                "thread_id": thread_id,
                "body": "Private image",
                "attachment_ids": [attachment_id],
            },
        )
        assert sent.status_code == 200
        message = sent.json()["data"]["message"]
        assert message["message_type"] == "mixed"
        assert message["attachments"] == [
            {
                "attachment_id": attachment_id,
                "thread_id": thread_id,
                "kind": "image",
                "file_name": "synthetic.png",
                "mime_type": "image/png",
                "size_in_bytes": attachment["size_in_bytes"],
                "download_path": attachment["download_path"],
            }
        ]
        assert "storage_path" not in message["attachments"][0]
        recipient_download = auth_harness.client.get(
            attachment["download_path"], headers=member_headers
        )
        assert recipient_download.status_code == 200
        outsider_download = auth_harness.client.get(
            attachment["download_path"], headers=outsider_headers
        )
        assert outsider_download.status_code == 403

        reused = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={"thread_id": thread_id, "attachment_ids": [attachment_id]},
        )
        assert reused.status_code == 403
        assert reused.json()["code"] == "chat_attachment_forbidden"
    finally:
        with auth_harness.engine.begin() as connection:
            thread_ids = list(
                connection.scalars(
                    select(MessageThreads.id).where(
                        MessageThreads.created_by.in_([owner_id, member_id, outsider_id])
                    )
                )
            )
            if thread_ids:
                connection.execute(
                    MESSAGE_ATTACHMENTS_TABLE.delete().where(
                        MessagesAttachments.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids))
                )


@pytest.mark.integration
def test_v2_chat_attachment_failure_paths_keep_staging_private(
    auth_harness: AuthHarness,
) -> None:
    """Invalid, expired, foreign, and disabled attachment writes never become messages."""
    owner_id, owner_email, _ = auth_harness.create_user(fullname="Synthetic Attachment Errors")
    member_id, member_email, _ = auth_harness.create_user(fullname="Synthetic Attachment Peer")
    outsider_id, outsider_email, _ = auth_harness.create_user(
        fullname="Synthetic Attachment Foreign"
    )
    owner_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, owner_id, owner_email)}"
    }
    member_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, member_id, member_email)}"
    }
    outsider_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, outsider_id, outsider_email)}"
    }
    image_bytes = BytesIO()
    Image.new("RGB", (4, 4), color=(4, 8, 16)).save(image_bytes, format="PNG")
    try:
        malformed = auth_harness.client.post(
            "/chat_api/v2_upload_attachment", headers=owner_headers, data={"thread_id": "broken"}
        )
        assert malformed.status_code == 400
        invalid_detail = auth_harness.client.post(
            "/chat_api/v2_get_thread", headers=owner_headers, json={"thread_id": "broken"}
        )
        assert invalid_detail.status_code == 400
        group = auth_harness.client.post(
            "/chat_api/v2_create_group",
            headers=owner_headers,
            json={"title": "Synthetic attachment errors", "member_ids": [member_id]},
        )
        assert group.status_code == 200
        thread_id = int(group.json()["thread_id"])
        empty_message = auth_harness.client.post(
            "/chat_api/v2_send_message", headers=owner_headers, json={"thread_id": thread_id}
        )
        assert empty_message.status_code == 422
        missing_attachment = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={"thread_id": thread_id, "attachment_ids": [999_999_999]},
        )
        assert missing_attachment.status_code == 404
        foreign_upload = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=outsider_headers,
            data={"thread_id": str(thread_id)},
            files={"file": ("foreign.png", image_bytes.getvalue(), "image/png")},
        )
        assert foreign_upload.status_code == 403

        staged = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"thread_id": str(thread_id)},
            files={"file": ("owned.png", image_bytes.getvalue(), "image/png")},
        )
        assert staged.status_code == 200
        attachment_id = int(staged.json()["data"]["attachment_id"])
        foreign_link = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=member_headers,
            json={"thread_id": thread_id, "attachment_ids": [attachment_id]},
        )
        assert foreign_link.status_code == 403
        with auth_harness.engine.begin() as connection:
            connection.execute(
                MESSAGE_ATTACHMENTS_TABLE.update()
                .where(MessagesAttachments.id == attachment_id)
                .values(expires_at=datetime.now(UTC).replace(tzinfo=None) - timedelta(seconds=1))
            )
        expired_download = auth_harness.client.get(
            staged.json()["data"]["download_path"], headers=owner_headers
        )
        assert expired_download.status_code == 404
        expired_link = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={"thread_id": thread_id, "attachment_ids": [attachment_id]},
        )
        assert expired_link.status_code == 403

        direct_stage = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"recipient_id": str(member_id)},
            files={"file": ("direct.png", image_bytes.getvalue(), "image/png")},
        )
        assert direct_stage.status_code == 200
        direct_thread_id = int(direct_stage.json()["data"]["thread_id"])
        assert direct_thread_id != thread_id
        direct_stage_reuse = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"recipient_id": str(member_id)},
            files={"file": ("direct-again.png", image_bytes.getvalue(), "image/png")},
        )
        assert direct_stage_reuse.status_code == 200
        assert int(direct_stage_reuse.json()["data"]["thread_id"]) == direct_thread_id
        direct_self = auth_harness.client.post(
            "/chat_api/v2_send_direct",
            headers=owner_headers,
            json={"recipient_id": owner_id, "body": "Not permitted"},
        )
        assert direct_self.status_code == 422
        direct_add_member = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=owner_headers,
            json={"thread_id": direct_thread_id, "member_id": outsider_id},
        )
        assert direct_add_member.status_code == 422
        direct_leave = auth_harness.client.post(
            "/chat_api/v2_leave_group",
            headers=owner_headers,
            json={"thread_id": direct_thread_id},
        )
        assert direct_leave.status_code == 422
        for endpoint, payload in (
            ("/chat_api/v2_delete_message", {"message_id": 999_999_999}),
            ("/chat_api/v2_pin_thread", {"thread_id": 999_999_999}),
            (
                "/chat_api/v2_mark_delivered",
                {"thread_id": thread_id, "message_id": 999_999_999},
            ),
        ):
            missing = auth_harness.client.post(endpoint, headers=owner_headers, json=payload)
            assert missing.status_code == 404
        invalid_reply = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={"thread_id": thread_id, "body": "No target", "reply_to_message_id": 999_999_999},
        )
        assert invalid_reply.status_code == 422
        first_client_id = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=member_headers,
            json={
                "thread_id": thread_id,
                "body": "Client key",
                "client_generated_id": "shared-key",
            },
        )
        assert first_client_id.status_code == 200
        conflicting_client_id = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=owner_headers,
            json={
                "thread_id": thread_id,
                "body": "Client key",
                "client_generated_id": "shared-key",
            },
        )
        assert conflicting_client_id.status_code == 409
        added_member = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=owner_headers,
            json={"thread_id": thread_id, "member_id": outsider_id},
        )
        assert added_member.status_code == 200
        left_member = auth_harness.client.post(
            "/chat_api/v2_leave_group",
            headers=outsider_headers,
            json={"thread_id": thread_id},
        )
        assert left_member.status_code == 200
        readded_member = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=owner_headers,
            json={"thread_id": thread_id, "member_id": outsider_id},
        )
        assert readded_member.status_code == 200
        with auth_harness.engine.begin() as connection:
            connection.execute(USERS_TABLE.update().where(Users.id == outsider_id).values(active=0))
        inactive_member = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=owner_headers,
            json={"thread_id": thread_id, "member_id": outsider_id},
        )
        assert inactive_member.status_code == 404
        with auth_harness.engine.begin() as connection:
            connection.execute(
                MESSAGE_THREADS_TABLE.update()
                .where(MessageThreads.id == thread_id)
                .values(attachment_enabled=0)
            )
        disabled = auth_harness.client.post(
            "/chat_api/v2_upload_attachment",
            headers=owner_headers,
            data={"thread_id": str(thread_id)},
            files={"file": ("disabled.png", image_bytes.getvalue(), "image/png")},
        )
        assert disabled.status_code == 422
    finally:
        with auth_harness.engine.begin() as connection:
            thread_ids = list(
                connection.scalars(
                    select(MessageThreads.id).where(
                        MessageThreads.created_by.in_([owner_id, member_id, outsider_id])
                    )
                )
            )
            if thread_ids:
                connection.execute(
                    MESSAGE_ATTACHMENTS_TABLE.delete().where(
                        MessagesAttachments.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids))
                )


@pytest.mark.integration
def test_v2_chat_concurrent_first_direct_message_reuses_one_thread(
    auth_harness: AuthHarness,
) -> None:
    """Two first sends in opposite directions converge on the direct-key thread."""
    first_id, first_email, _ = auth_harness.create_user(fullname="Synthetic Concurrent One")
    second_id, second_email, _ = auth_harness.create_user(fullname="Synthetic Concurrent Two")
    first_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, first_id, first_email)}"
    }
    second_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, second_id, second_email)}"
    }

    def send(headers: dict[str, str], recipient_id: int, client_id: str) -> Any:
        return auth_harness.client.post(
            "/chat_api/v2_send_direct",
            headers=headers,
            json={
                "recipient_id": recipient_id,
                "body": "Concurrent synthetic direct message",
                "client_generated_id": client_id,
            },
        )

    try:
        with ThreadPoolExecutor(max_workers=2) as executor:
            first, second = list(
                executor.map(
                    lambda args: send(*args),
                    (
                        (first_headers, second_id, "synthetic-concurrent-one"),
                        (second_headers, first_id, "synthetic-concurrent-two"),
                    ),
                )
            )
        assert first.status_code == second.status_code == 200
        assert first.json()["data"]["thread_id"] == second.json()["data"]["thread_id"]
    finally:
        with auth_harness.engine.begin() as connection:
            thread_ids = list(
                connection.scalars(
                    select(MessageThreads.id).where(
                        MessageThreads.created_by.in_([first_id, second_id])
                    )
                )
            )
            if thread_ids:
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids))
                )


@pytest.mark.integration
def test_v2_chat_group_administration_delivery_pin_and_leave_are_member_scoped(
    auth_harness: AuthHarness,
) -> None:
    """Group administration is admin-only and leaving removes future thread access."""
    creator_id, creator_email, _ = auth_harness.create_user(fullname="Synthetic Chat Creator")
    member_id, member_email, _ = auth_harness.create_user(fullname="Synthetic Chat Member")
    third_id, third_email, _ = auth_harness.create_user(fullname="Synthetic Chat Third")
    fourth_id, _, _ = auth_harness.create_user(fullname="Synthetic Chat Fourth")
    creator_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, creator_id, creator_email)}"
    }
    member_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, member_id, member_email)}"
    }
    third_headers = {
        "Authorization": f"Bearer {_access_token_for(auth_harness, third_id, third_email)}"
    }
    try:
        group = auth_harness.client.post(
            "/chat_api/v2_create_group",
            headers=creator_headers,
            json={"title": "Synthetic governed group", "member_ids": [member_id]},
        )
        assert group.status_code == 200
        thread_id = int(group.json()["thread_id"])
        sent = auth_harness.client.post(
            "/chat_api/v2_send_message",
            headers=creator_headers,
            json={"thread_id": thread_id, "body": "Synthetic governed message"},
        )
        assert sent.status_code == 200
        message_id = int(sent.json()["data"]["message"]["id"])

        denied_add = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=member_headers,
            json={"thread_id": thread_id, "member_id": third_id},
        )
        assert denied_add.status_code == 403
        assert denied_add.json()["code"] == "chat_group_admin_required"
        added = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=creator_headers,
            json={"thread_id": thread_id, "user_id": third_id},
        )
        assert added.status_code == 200
        assert added.json()["data"]["member_id"] == third_id
        duplicate = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=creator_headers,
            json={"thread_id": thread_id, "member_id": third_id},
        )
        assert duplicate.status_code == 409
        assert duplicate.json()["code"] == "chat_member_exists"

        delivered = auth_harness.client.post(
            "/chat_api/v2_mark_delivered",
            headers=third_headers,
            json={"thread_id": thread_id, "message_id": message_id},
        )
        assert delivered.status_code == 200
        assert delivered.json()["data"]["last_delivered_message_id"] == message_id
        pinned = auth_harness.client.post(
            "/chat_api/v2_pin_thread",
            headers=third_headers,
            json={"thread_id": thread_id, "pin": True},
        )
        assert pinned.status_code == 200
        assert pinned.json()["data"]["is_pinned"] is True

        left = auth_harness.client.post(
            "/chat_api/v2_leave_group",
            headers=creator_headers,
            json={"thread_id": thread_id},
        )
        assert left.status_code == 200
        denied_after_leave = auth_harness.client.post(
            "/chat_api/v2_get_thread",
            headers=creator_headers,
            json={"thread_id": thread_id},
        )
        assert denied_after_leave.status_code == 403
        assert denied_after_leave.json()["code"] == "chat_membership_forbidden"
        promoted_add = auth_harness.client.post(
            "/chat_api/v2_add_member",
            headers=member_headers,
            json={"thread_id": thread_id, "member_id": fourth_id},
        )
        assert promoted_add.status_code == 200
        with auth_harness.engine.connect() as connection:
            left_row = connection.execute(
                select(ThreadParticipants.left_at).where(
                    ThreadParticipants.thread_id == thread_id,
                    ThreadParticipants.member_id == creator_id,
                )
            ).scalar_one()
            successor_role = connection.execute(
                select(ThreadParticipants.role).where(
                    ThreadParticipants.thread_id == thread_id,
                    ThreadParticipants.member_id == member_id,
                )
            ).scalar_one()
        assert left_row is not None
        assert successor_role == "admin"
    finally:
        with auth_harness.engine.begin() as connection:
            thread_ids = list(
                connection.scalars(
                    select(MessageThreads.id).where(MessageThreads.created_by == creator_id)
                )
            )
            if thread_ids:
                connection.execute(
                    MESSAGES_TABLE.delete().where(Messages.thread_id.in_(thread_ids))
                )
                connection.execute(
                    THREAD_PARTICIPANTS_TABLE.delete().where(
                        ThreadParticipants.thread_id.in_(thread_ids)
                    )
                )
                connection.execute(
                    MESSAGE_THREADS_TABLE.delete().where(MessageThreads.id.in_(thread_ids))
                )
