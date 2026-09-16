"""Generated mappings for the sanitized 2026-09-08 legacy schema snapshot."""

import datetime
import decimal
import enum
from typing import Optional

from sqlalchemy import (
    DECIMAL,
    TIMESTAMP,
    Computed,
    Date,
    DateTime,
    Enum,
    ForeignKeyConstraint,
    Index,
    LargeBinary,
    String,
    Text,
    Time,
    text,
)
from sqlalchemy.dialects.mysql import (
    BIGINT,
    DATETIME,
    INTEGER,
    LONGTEXT,
    MEDIUMINT,
    MEDIUMTEXT,
    SMALLINT,
    TINYINT,
)
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship


class Base(DeclarativeBase):
    pass


class AnnouncementsType(str, enum.Enum):
    INFO = "info"
    WARNING = "warning"
    SUCCESS = "success"
    EVENT = "event"


class BlogPostsStatus(str, enum.Enum):
    DRAFT = "draft"
    PUBLISHED = "published"


class CartsStatus(str, enum.Enum):
    ACTIVE = "active"
    CHECKED_OUT = "checked_out"
    ABANDONED = "abandoned"


class ChatGroupMembersRole(str, enum.Enum):
    MEMBER = "member"
    MODERATOR = "moderator"
    ADMIN = "admin"


class ChatGroupsRequiredRole(str, enum.Enum):
    MEMBER = "member"
    PREMIUM = "premium"
    MODERATOR = "moderator"
    ADMIN = "admin"


class ChatGroupsType(str, enum.Enum):
    DIRECT = "direct"
    GROUP = "group"


class ChatMessagesAttachmentType(str, enum.Enum):
    IMAGE = "image"
    FILE = "file"
    VIDEO = "video"


class ContactUsStatus(str, enum.Enum):
    NEW = "new"
    READ = "read"
    REPLIED = "replied"


class EventAttendeesStatus(str, enum.Enum):
    GOING = "going"
    MAYBE = "maybe"
    NOT_GOING = "not_going"


class EventRegistrationFormQuestionsType(str, enum.Enum):
    SHORT_ANSWER = "short_answer"
    LONG_ANSWER = "long_answer"
    MULTIPLE_CHOICE = "multiple_choice"
    CHECKBOX = "checkbox"
    DROPDOWN = "dropdown"


class EventsVisibility(str, enum.Enum):
    PUBLIC = "public"
    MEMBERS = "members"
    PREMIUM = "premium"


class FaqsIsPublished(str, enum.Enum):
    _0 = "0"
    _1 = "1"


class HomepageCarouselIsHidden(str, enum.Enum):
    _0 = "0"
    _1 = "1"


class HomepageCarouselShowGreeting(str, enum.Enum):
    _0 = "0"
    _1 = "1"


class JobVacanciesApplicationType(str, enum.Enum):
    EMAIL = "email"
    LINK = "link"


class JobVacanciesJobType(str, enum.Enum):
    FULL_TIME = "full_time"
    PART_TIME = "part_time"
    CONTRACT = "contract"
    INTERNSHIP = "internship"
    FREELANCE = "freelance"


class JobVacanciesLevelOfExpertise(str, enum.Enum):
    ENTRY_LEVEL = "entry_level"
    MID_LEVEL = "mid_level"
    SENIOR_LEVEL = "senior_level"
    EXECUTIVE = "executive"


class JobVacanciesWorkplaceType(str, enum.Enum):
    REMOTE = "remote"
    HYBRID = "hybrid"
    ON_SITE = "on_site"


class MarketplaceListingsCategory(str, enum.Enum):
    JOBS = "jobs"
    HOUSING = "housing"
    ITEMS = "items"
    SERVICES = "services"
    TUTORING = "tutoring"
    OTHER = "other"


class MarketplaceListingsPriceType(str, enum.Enum):
    FIXED = "fixed"
    NEGOTIABLE = "negotiable"
    FREE = "free"


class MarketplaceListingsStatus(str, enum.Enum):
    ACTIVE = "active"
    SOLD = "sold"
    EXPIRED = "expired"
    PENDING = "pending"


class MarketplaceListingsV2Category(str, enum.Enum):
    JOBS = "jobs"
    HOUSING = "housing"
    ITEMS = "items"
    SERVICES = "services"
    TUTORING = "tutoring"
    OTHER = "other"


class MarketplaceListingsV2PriceType(str, enum.Enum):
    FIXED = "fixed"
    NEGOTIABLE = "negotiable"
    FREE = "free"


class MarketplaceListingsV2Status(str, enum.Enum):
    ACTIVE = "active"
    SOLD = "sold"
    EXPIRED = "expired"
    PENDING = "pending"


class MessageThreadsCategory(str, enum.Enum):
    COMMUNITY = "community"
    MENTORSHIP = "mentorship"
    EVENTS = "events"
    MARKETPLACE = "marketplace"


class MessageThreadsType(str, enum.Enum):
    DIRECT = "direct"
    GROUP = "group"


class MessagesAttachmentsKind(str, enum.Enum):
    IMAGE = "image"
    AUDIO = "audio"
    FILE = "file"


class MessagesMessageType(str, enum.Enum):
    TEXT = "text"
    IMAGE = "image"
    AUDIO = "audio"
    FILE = "file"
    MIXED = "mixed"


class OrdersDeliveryType(str, enum.Enum):
    DOOR_DELIVERY = "door_delivery"
    SELF_PICKUP = "self_pickup"


class OrdersStatus(str, enum.Enum):
    PENDING = "pending"
    PAID = "paid"
    SHIPPED = "shipped"
    DELIVERED = "delivered"
    FAILED = "failed"
    CANCELLED = "cancelled"


class PaymentsStatus(str, enum.Enum):
    PENDING = "pending"
    SUCCESS = "success"
    FAILED = "failed"


class ProductsStatus(str, enum.Enum):
    ACTIVE = "active"
    INACTIVE = "inactive"


class ProjectsStatus(str, enum.Enum):
    ACTIVE = "active"
    COMPLETED = "completed"
    PAUSED = "paused"
    DRAFT = "draft"
    ONGOING = "ongoing"


class ThreadParticipantsRole(str, enum.Enum):
    MEMBER = "member"
    MODERATOR = "moderator"
    ADMIN = "admin"


class UserSocialAccountsProvider(str, enum.Enum):
    GOOGLE = "google"
    FACEBOOK = "facebook"


class UserSubscriptionsStatus(str, enum.Enum):
    ACTIVE = "active"
    EXPIRED = "expired"
    CANCELLED = "cancelled"


class Users2Role(str, enum.Enum):
    ADMIN = "admin"
    MODERATOR = "moderator"
    PREMIUM = "premium"
    MEMBER = "member"
    PENDING = "pending"


class VouchesStatus(str, enum.Enum):
    PENDING = "pending"
    APPROVED = "approved"
    DENIED = "denied"
    REJECTED = "rejected"


class AlumniCategoryV2(Base):
    __tablename__ = "alumni_category_v2"
    __table_args__ = (
        Index("fk_ac_chapter", "chapter_id"),
        Index("uq_user_chapter", "user_id", "chapter_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    chapter_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    year: Mapped[str] = mapped_column(String(4), nullable=False, comment="Alumni year e.g. 2024")
    location: Mapped[str] = mapped_column(String(200), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )


class AlumniChapter(Base):
    __tablename__ = "alumni_chapter"
    __table_args__ = (Index("uq_chapter_name", "chapter_name", unique=True),)

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    chapter_name: Mapped[str] = mapped_column(String(150), nullable=False)
    location: Mapped[str] = mapped_column(String(200), nullable=False)
    is_enabled: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )

    alumni_category: Mapped[list["AlumniCategory"]] = relationship(
        "AlumniCategory", back_populates="chapter"
    )
    announcements: Mapped[list["Announcements"]] = relationship(
        "Announcements", back_populates="chapter"
    )
    chat_groups: Mapped[list["ChatGroups"]] = relationship("ChatGroups", back_populates="chapter")
    events: Mapped[list["Events"]] = relationship("Events", back_populates="chapter")
    leadership: Mapped[list["Leadership"]] = relationship("Leadership", back_populates="chapter")
    marketplace_listings: Mapped[list["MarketplaceListings"]] = relationship(
        "MarketplaceListings", back_populates="chapter"
    )
    notifications: Mapped[list["Notifications"]] = relationship(
        "Notifications", back_populates="chapter"
    )
    payments: Mapped[list["Payments"]] = relationship("Payments", back_populates="chapter")
    projects: Mapped[list["Projects"]] = relationship("Projects", back_populates="chapter")
    user_profiles: Mapped[list["UserProfiles"]] = relationship(
        "UserProfiles", back_populates="chapter"
    )
    user_subscriptions: Mapped[list["UserSubscriptions"]] = relationship(
        "UserSubscriptions", back_populates="chapter"
    )
    users2: Mapped[list["Users2"]] = relationship("Users2", back_populates="chapter")


class ApiTable(Base):
    __tablename__ = "api_table"

    api_id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    api_name: Mapped[str] = mapped_column(String(20), nullable=False)
    api_token: Mapped[str] = mapped_column(String(225), nullable=False)
    api_key: Mapped[str] = mapped_column(String(225), nullable=False)


class Attachments(Base):
    __tablename__ = "attachments"
    __table_args__ = (Index("wkfl_attachments", "user_id"),)

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    file_type: Mapped[str] = mapped_column(String(20), nullable=False)
    filename: Mapped[str] = mapped_column(String(200), nullable=False)
    attachment_file: Mapped[str] = mapped_column(String(250), nullable=False)
    dateadded: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)


class BlogCategories(Base):
    __tablename__ = "blog_categories"
    __table_args__ = (
        Index("idx_deleted_at", "deleted_at"),
        Index("idx_is_active", "is_active"),
        Index("idx_sort_order", "sort_order"),
        Index("slug", "slug", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    slug: Mapped[str | None] = mapped_column(String(255))
    sort_order: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    is_active: Mapped[int | None] = mapped_column(INTEGER(40), server_default=text("1"))
    deleted_at: Mapped[datetime.datetime | None] = mapped_column(TIMESTAMP)

    blog_posts: Mapped[list["BlogPosts"]] = relationship("BlogPosts", back_populates="category")


class CiSessions(Base):
    __tablename__ = "ci_sessions"
    __table_args__ = (Index("ci_sessions_timestamp", "timestamp"),)

    id: Mapped[str] = mapped_column(String(128), primary_key=True)
    ip_address: Mapped[str] = mapped_column(String(45), nullable=False)
    timestamp: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, server_default=text("0")
    )
    data: Mapped[bytes] = mapped_column(LargeBinary, nullable=False)


class Cities(Base):
    __tablename__ = "cities"

    city_id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    city: Mapped[str] = mapped_column(String(150), nullable=False)
    zone_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    chapter_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False, server_default=text("1"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )


class Cities1(Base):
    __tablename__ = "cities1"

    city_id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    city: Mapped[str] = mapped_column(String(150), nullable=False)
    zone_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    chapter_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False, server_default=text("1"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )


class ContactUs(Base):
    __tablename__ = "contact_us"

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    first_name: Mapped[str] = mapped_column(String(100), nullable=False)
    last_name: Mapped[str] = mapped_column(String(100), nullable=False)
    email: Mapped[str] = mapped_column(String(255), nullable=False)
    message: Mapped[str] = mapped_column(MEDIUMTEXT, nullable=False)
    status: Mapped[ContactUsStatus] = mapped_column(
        Enum(ContactUsStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'new'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )


class DeliveryZones(Base):
    __tablename__ = "delivery_zones"
    __table_args__ = (
        Index("idx_delivery_zones_state", "state"),
        Index("unique_zone", "state", "area", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    state: Mapped[str] = mapped_column(String(100), nullable=False)
    fee: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    area: Mapped[str | None] = mapped_column(String(255))


class EventRegistrationFormQuestions(Base):
    __tablename__ = "event_registration_form_questions"
    __table_args__ = (Index("idx_form_id", "form_id"),)

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    form_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    label: Mapped[str] = mapped_column(String(500), nullable=False)
    type: Mapped[EventRegistrationFormQuestionsType] = mapped_column(
        Enum(
            EventRegistrationFormQuestionsType,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        nullable=False,
    )
    required: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    sort_order: Mapped[int] = mapped_column(
        SMALLINT(5, unsigned=True), nullable=False, server_default=text("0")
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    placeholder: Mapped[str | None] = mapped_column(String(255))
    options_json: Mapped[str | None] = mapped_column(
        LONGTEXT(charset="utf8mb4", collation="utf8mb4_bin")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)


class EventRegistrationForms(Base):
    __tablename__ = "event_registration_forms"
    __table_args__ = (Index("idx_event_id", "event_id"),)

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    event_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    sort_order: Mapped[int] = mapped_column(
        TINYINT(3, unsigned=True), nullable=False, server_default=text("0")
    )
    version: Mapped[int] = mapped_column(
        SMALLINT(5, unsigned=True), nullable=False, server_default=text("1")
    )
    is_active: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    description: Mapped[str | None] = mapped_column(Text)
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)


class Faqs(Base):
    __tablename__ = "faqs"
    __table_args__ = (
        Index("idx_deleted_at", "deleted_at"),
        Index("idx_is_published", "is_published"),
        Index("idx_sort_order", "sort_order"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    question: Mapped[str] = mapped_column(Text, nullable=False)
    answer: Mapped[str] = mapped_column(Text, nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    sort_order: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    is_published: Mapped[FaqsIsPublished | None] = mapped_column(
        Enum(FaqsIsPublished, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'1'"),
    )
    deleted_at: Mapped[datetime.datetime | None] = mapped_column(TIMESTAMP)


class Groups(Base):
    __tablename__ = "groups"

    id: Mapped[int] = mapped_column(
        MEDIUMINT(8, unsigned=True), primary_key=True, autoincrement=True
    )
    name: Mapped[str] = mapped_column(String(20), nullable=False)
    description: Mapped[str] = mapped_column(String(100), nullable=False)


class Homepage(Base):
    __tablename__ = "homepage"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    greeting_title: Mapped[str | None] = mapped_column(String(255))
    greeting_message: Mapped[str | None] = mapped_column(Text)


class HomepageCarousel(Base):
    __tablename__ = "homepage_carousel"
    __table_args__ = (Index("idx_sort_order", "sort_order"),)

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    image_url: Mapped[str] = mapped_column(String(500), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    file_name: Mapped[str | None] = mapped_column(String(255))
    alt_text: Mapped[str | None] = mapped_column(String(255))
    sort_order: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    is_hidden: Mapped[HomepageCarouselIsHidden | None] = mapped_column(
        Enum(
            HomepageCarouselIsHidden, values_callable=lambda cls: [member.value for member in cls]
        ),
        server_default=text("'0'"),
    )
    show_greeting: Mapped[HomepageCarouselShowGreeting | None] = mapped_column(
        Enum(
            HomepageCarouselShowGreeting,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'0'"),
    )
    deleted_at: Mapped[datetime.datetime | None] = mapped_column(TIMESTAMP)


class JobVacancies(Base):
    __tablename__ = "job_vacancies"

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    chapter_id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, server_default=text("1")
    )
    job_title: Mapped[str] = mapped_column(String(255), nullable=False)
    company_name: Mapped[str] = mapped_column(String(255), nullable=False)
    job_type: Mapped[JobVacanciesJobType] = mapped_column(
        Enum(JobVacanciesJobType, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'full_time'"),
    )
    workplace_type: Mapped[JobVacanciesWorkplaceType] = mapped_column(
        Enum(
            JobVacanciesWorkplaceType, values_callable=lambda cls: [member.value for member in cls]
        ),
        nullable=False,
        server_default=text("'remote'"),
    )
    level_of_expertise: Mapped[JobVacanciesLevelOfExpertise] = mapped_column(
        Enum(
            JobVacanciesLevelOfExpertise,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        nullable=False,
        server_default=text("'entry_level'"),
    )
    application_type: Mapped[JobVacanciesApplicationType] = mapped_column(
        Enum(
            JobVacanciesApplicationType,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        nullable=False,
        server_default=text("'email'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    location: Mapped[str | None] = mapped_column(String(255))
    salary: Mapped[str | None] = mapped_column(String(100))
    application_deadline: Mapped[datetime.date | None] = mapped_column(Date)
    keywords: Mapped[str | None] = mapped_column(MEDIUMTEXT)
    about_role: Mapped[str | None] = mapped_column(LONGTEXT)
    responsibilities: Mapped[str | None] = mapped_column(LONGTEXT)
    requirements: Mapped[str | None] = mapped_column(LONGTEXT)
    application_email: Mapped[str | None] = mapped_column(String(255))
    application_link: Mapped[str | None] = mapped_column(String(500))
    flyer: Mapped[str | None] = mapped_column(String(500))
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)


class LoginAttempts(Base):
    __tablename__ = "login_attempts"

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    ip_address: Mapped[str] = mapped_column(String(15), nullable=False)
    login: Mapped[str] = mapped_column(String(100), nullable=False)
    time: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))


class MarketplaceListingsV2(Base):
    __tablename__ = "marketplace_listings_v2"
    __table_args__ = (
        Index("fk_marketplace_chapter", "chapter_id"),
        Index("marketplace_listings_ibfk_1", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    title: Mapped[str] = mapped_column(String(250), nullable=False)
    message_prompt: Mapped[str] = mapped_column(Text, nullable=False, server_default=text("''"))
    business_name: Mapped[str] = mapped_column(String(255), nullable=False)
    phone: Mapped[str] = mapped_column(String(255), nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="Chapter the listing belongs to"
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="NULL = all years, set to scope listing to a year"
    )
    description: Mapped[str | None] = mapped_column(Text)
    category: Mapped[MarketplaceListingsV2Category | None] = mapped_column(
        Enum(
            MarketplaceListingsV2Category,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'other'"),
    )
    price: Mapped[decimal.Decimal | None] = mapped_column(
        DECIMAL(10, 2), server_default=text("0.00")
    )
    price_type: Mapped[MarketplaceListingsV2PriceType | None] = mapped_column(
        Enum(
            MarketplaceListingsV2PriceType,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'fixed'"),
    )
    images: Mapped[str | None] = mapped_column(Text)
    contact_info: Mapped[str | None] = mapped_column(String(255))
    whatsapp: Mapped[str | None] = mapped_column(
        String(50), comment="WhatsApp number e.g. 08022222222"
    )
    website: Mapped[str | None] = mapped_column(String(255))
    location: Mapped[str | None] = mapped_column(String(200))
    status: Mapped[MarketplaceListingsV2Status | None] = mapped_column(
        Enum(
            MarketplaceListingsV2Status,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'pending'"),
    )
    is_featured: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    views: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    expires_at: Mapped[datetime.date | None] = mapped_column(Date)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )


class MarketplaceSocialMediaV2(Base):
    __tablename__ = "marketplace_social_media_v2"
    __table_args__ = (
        Index("fk_social_market_id", "market_id"),
        Index("uq_market_social", "market_id", unique=True),
        {"comment": "Social media profiles linked to marketplace listings"},
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    market_id: Mapped[int] = mapped_column(
        INTEGER(11), nullable=False, comment="FK → marketplace_listings.id"
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    instagram_handle: Mapped[str | None] = mapped_column(
        String(150), comment="Instagram username e.g. @mybusiness"
    )
    instagram_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://instagram.com/mybusiness"
    )
    instagram_hashtag: Mapped[str | None] = mapped_column(String(255))
    twitter_handle: Mapped[str | None] = mapped_column(
        String(150), comment="Twitter username e.g. @mybusiness"
    )
    twitter_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://twitter.com/mybusiness"
    )
    linkedin_handle: Mapped[str | None] = mapped_column(String(150), comment="LinkedIn page name")
    linkedin_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://linkedin.com/company/mybusiness"
    )
    facebook_handle: Mapped[str | None] = mapped_column(String(150), comment="Facebook page name")
    facebook_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://facebook.com/mybusiness"
    )
    tiktok_handle: Mapped[str | None] = mapped_column(
        String(150), comment="TikTok username e.g. @mybusiness"
    )
    tiktok_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://tiktok.com/@mybusiness"
    )
    youtube_handle: Mapped[str | None] = mapped_column(String(150), comment="YouTube channel name")
    youtube_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://youtube.com/@mybusiness"
    )


class MessageThreads(Base):
    __tablename__ = "message_threads"
    __table_args__ = (Index("uq_direct_key", "direct_key", unique=True),)

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    type: Mapped[MessageThreadsType] = mapped_column(
        Enum(MessageThreadsType, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
    )
    created_by: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, comment="users.id"
    )
    category: Mapped[MessageThreadsCategory] = mapped_column(
        Enum(MessageThreadsCategory, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'community'"),
    )
    attachment_enabled: Mapped[int] = mapped_column(
        TINYINT(1), nullable=False, server_default=text("1")
    )
    audio_enabled: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    title: Mapped[str | None] = mapped_column(String(255), comment="Group name; null for DMs")
    direct_key: Mapped[str | None] = mapped_column(
        String(100), comment="Sorted user ids e.g. 12__48 — prevents duplicate DM threads"
    )
    last_message_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    last_message_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    last_message_preview: Mapped[str | None] = mapped_column(String(300))
    last_message_sender_name: Mapped[str | None] = mapped_column(String(150))
    last_message_sender_member_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)


class Messages(Base):
    __tablename__ = "messages"
    __table_args__ = (
        ForeignKeyConstraint(
            ["reply_to_message_id"], ["messages.id"], ondelete="SET NULL", name="fk_msg_reply"
        ),
        Index("fk_msg_reply", "reply_to_message_id"),
        Index("idx_created_at", "created_at"),
        Index("idx_thread_id", "thread_id"),
        Index("uq_thread_client_id", "thread_id", "client_generated_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    thread_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    sender_member_id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, comment="users.id"
    )
    message_type: Mapped[MessagesMessageType] = mapped_column(
        Enum(MessagesMessageType, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'text'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    body: Mapped[str | None] = mapped_column(Text)
    reply_to_message_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    client_generated_id: Mapped[str | None] = mapped_column(
        String(100), comment="Frontend dedup key"
    )
    deleted_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, comment="Soft delete — body cleared when set"
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    reply_to_message: Mapped[Optional["Messages"]] = relationship(
        "Messages", remote_side=[id], back_populates="reply_to_message_reverse"
    )
    reply_to_message_reverse: Mapped[list["Messages"]] = relationship(
        "Messages", remote_side=[reply_to_message_id], back_populates="reply_to_message"
    )


class MessagesAttachments(Base):
    __tablename__ = "messages_attachments"
    __table_args__ = (Index("idx_message_id", "message_id"), Index("idx_thread_id", "thread_id"))

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    thread_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    kind: Mapped[MessagesAttachmentsKind] = mapped_column(
        Enum(MessagesAttachmentsKind, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'file'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    message_id: Mapped[int | None] = mapped_column(
        INTEGER(10, unsigned=True), comment="NULL until message is sent"
    )
    file_name: Mapped[str | None] = mapped_column(String(255))
    mime_type: Mapped[str | None] = mapped_column(String(100))
    size_in_bytes: Mapped[int | None] = mapped_column(BIGINT(20, unsigned=True))
    storage_path: Mapped[str | None] = mapped_column(String(500))
    public_url: Mapped[str | None] = mapped_column(String(500))
    duration_seconds: Mapped[int | None] = mapped_column(
        INTEGER(10, unsigned=True), comment="Audio voice notes only"
    )


class RegisterUserOtp(Base):
    __tablename__ = "register_user_otp"

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    email: Mapped[str] = mapped_column(String(255), nullable=False)
    otp: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    is_active: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )


class Roles(Base):
    __tablename__ = "roles"
    __table_args__ = (Index("role_name", "role_name", unique=True),)

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(255), nullable=False)
    role_name: Mapped[str] = mapped_column(String(100), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        TIMESTAMP, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        TIMESTAMP, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )


class SetupParameters(Base):
    __tablename__ = "setup_parameters"

    setup_id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    setup_name: Mapped[str | None] = mapped_column(String(100))
    setup_value: Mapped[str | None] = mapped_column(Text)


class SubscriptionPlans(Base):
    __tablename__ = "subscription_plans"
    __table_args__ = (Index("slug", "slug", unique=True),)

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    name: Mapped[str] = mapped_column(String(100), nullable=False)
    slug: Mapped[str] = mapped_column(String(100), nullable=False)
    price: Mapped[decimal.Decimal | None] = mapped_column(
        DECIMAL(10, 2), server_default=text("0.00")
    )
    duration_days: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("30"))
    features: Mapped[str | None] = mapped_column(Text)
    max_marketplace_items: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    can_chat: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("1"))
    can_create_events: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    is_active: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("1"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    user_subscriptions: Mapped[list["UserSubscriptions"]] = relationship(
        "UserSubscriptions", back_populates="plan"
    )


class ThreadParticipants(Base):
    __tablename__ = "thread_participants"
    __table_args__ = (
        Index("idx_member_id", "member_id"),
        Index("idx_thread_id", "thread_id"),
        Index("uq_thread_member", "thread_id", "member_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    thread_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    member_id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, comment="users.id"
    )
    role: Mapped[ThreadParticipantsRole] = mapped_column(
        Enum(ThreadParticipantsRole, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'member'"),
    )
    joined_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    is_pinned: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    left_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, comment="Soft-leave; NULL means still active"
    )
    last_read_message_id: Mapped[int | None] = mapped_column(
        INTEGER(10, unsigned=True), comment="Cursor — Seen state"
    )
    last_delivered_message_id: Mapped[int | None] = mapped_column(
        INTEGER(10, unsigned=True), comment="Cursor — Delivered state"
    )


class UserProfilesV2(Base):
    __tablename__ = "user_profiles_v2"
    __table_args__ = (
        Index("fk_user_profiles_chapter", "chapter_id"),
        Index("user_id", "user_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    instagram: Mapped[str] = mapped_column(String(255), nullable=False)
    tiktok: Mapped[str] = mapped_column(String(255), nullable=False)
    updated_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(INTEGER(11))
    year: Mapped[str | None] = mapped_column(String(4), comment="Profile year context")
    linkedin: Mapped[str | None] = mapped_column(String(255))
    twitter: Mapped[str | None] = mapped_column(String(255))
    facebook: Mapped[str | None] = mapped_column(String(255))
    website: Mapped[str | None] = mapped_column(String(255))
    current_company: Mapped[str | None] = mapped_column(String(200))
    current_position: Mapped[str | None] = mapped_column(String(200))
    city: Mapped[str | None] = mapped_column(String(100))
    country: Mapped[str | None] = mapped_column(String(100))
    skills: Mapped[str | None] = mapped_column(Text)
    achievements: Mapped[str | None] = mapped_column(Text)
    is_visible: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("1"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    field_visibility: Mapped[str | None] = mapped_column(Text)


class UserPushSubscriptions(Base):
    __tablename__ = "user_push_subscriptions"
    __table_args__ = (Index("idx_user_id", "user_id"),)

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    endpoint: Mapped[str] = mapped_column(Text, nullable=False)
    p256dh: Mapped[str] = mapped_column(String(255), nullable=False)
    auth: Mapped[str] = mapped_column(String(255), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    updated_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    browser: Mapped[str | None] = mapped_column(String(50))


class Users(Base):
    __tablename__ = "users"
    __table_args__ = (
        Index("uc_activation_selector", "activation_selector", unique=True),
        Index("uc_email", "email", unique=True),
        Index("uc_forgotten_password_selector", "forgotten_password_selector", unique=True),
        Index("uc_remember_selector", "remember_selector", unique=True),
        Index("uq_users_user_code", "user_code", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    chapter_id: Mapped[int] = mapped_column(INTEGER(50), nullable=False)
    ip_address: Mapped[str] = mapped_column(String(15), nullable=False)
    username: Mapped[str] = mapped_column(String(100), nullable=False)
    email: Mapped[str] = mapped_column(String(150), nullable=False)
    password: Mapped[str] = mapped_column(String(255), nullable=False)
    has_password: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    onboarding_completion: Mapped[int] = mapped_column(
        TINYINT(1), nullable=False, server_default=text("1")
    )
    nick_name: Mapped[str] = mapped_column(String(255), nullable=False)
    state: Mapped[str] = mapped_column(String(255), nullable=False)
    country: Mapped[str] = mapped_column(String(255), nullable=False)
    created_on: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    userAccessCode: Mapped[str] = mapped_column(String(100), nullable=False)
    profile_status: Mapped[str] = mapped_column(String(255), nullable=False)
    voucher: Mapped[str] = mapped_column(String(225), nullable=False)
    resetKey: Mapped[str] = mapped_column(String(255), nullable=False)
    user_code: Mapped[str | None] = mapped_column(
        String(25), comment="6-digit unique numeric code for frontend use"
    )
    first_name: Mapped[str | None] = mapped_column(String(50))
    last_name: Mapped[str | None] = mapped_column(String(50))
    fullname: Mapped[str | None] = mapped_column(String(100))
    name_in_school: Mapped[str | None] = mapped_column(
        String(200), comment="Name used in school e.g. First Name + Surname"
    )
    phone: Mapped[str | None] = mapped_column(String(20))
    alternative_phone: Mapped[str | None] = mapped_column(
        String(20), comment="Alternative / secondary phone number"
    )
    avatar: Mapped[str | None] = mapped_column(String(255), server_default=text("'default.png'"))
    birth_date: Mapped[datetime.date | None] = mapped_column(Date, comment="Date of birth")
    house_color: Mapped[str | None] = mapped_column(
        String(50), comment="House colour e.g. Yellow, Red, Blue, Green"
    )
    is_coordinator: Mapped[int | None] = mapped_column(
        TINYINT(1), server_default=text("0"), comment="True = class coordinator, 0 = not"
    )
    residential_address: Mapped[str | None] = mapped_column(
        Text, comment="Full residential address with landmark"
    )
    area: Mapped[str | None] = mapped_column(String(100), comment="Area e.g. Mainland, Island")
    city: Mapped[str | None] = mapped_column(String(100), comment="City e.g. Ikeja, Lekki, Abuja")
    employment_status: Mapped[str | None] = mapped_column(
        String(100), comment="Current employment status"
    )
    occupation: Mapped[str | None] = mapped_column(Text, comment="Occupation(s) / Profession(s)")
    industry_sector: Mapped[str | None] = mapped_column(Text, comment="Industry sector(s)")
    years_of_experience: Mapped[str | None] = mapped_column(
        String(50), comment="Years of professional experience"
    )
    is_volunteer: Mapped[int | None] = mapped_column(
        TINYINT(1), server_default=text("0"), comment="True = interested in volunteering, 0 = no"
    )
    graduation_year: Mapped[int | None] = mapped_column(INTEGER(11))
    department: Mapped[str | None] = mapped_column(String(200))
    bio: Mapped[str | None] = mapped_column(Text)
    salt: Mapped[str | None] = mapped_column(String(255))
    activation_selector: Mapped[str | None] = mapped_column(String(255))
    activation_code: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_selector: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_code: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_time: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    remember_selector: Mapped[str | None] = mapped_column(String(255))
    remember_code: Mapped[str | None] = mapped_column(String(255))
    last_login: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    active: Mapped[int | None] = mapped_column(TINYINT(3, unsigned=True))
    user_role: Mapped[str | None] = mapped_column(String(20))
    is_approved: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    email_verified: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    verify_token: Mapped[str | None] = mapped_column(String(100))
    reset_token: Mapped[str | None] = mapped_column(String(100))
    reset_expires: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    device_token: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="Alumni graduation/membership year e.g. 2024"
    )

    alumni_category: Mapped[list["AlumniCategory"]] = relationship(
        "AlumniCategory", back_populates="user"
    )
    announcements: Mapped[list["Announcements"]] = relationship(
        "Announcements", back_populates="users"
    )
    carts: Mapped[list["Carts"]] = relationship("Carts", back_populates="user")
    chat_groups: Mapped[list["ChatGroups"]] = relationship("ChatGroups", back_populates="users")
    direct_messages_receiver: Mapped[list["DirectMessages"]] = relationship(
        "DirectMessages", foreign_keys="[DirectMessages.receiver_id]", back_populates="receiver"
    )
    direct_messages_sender: Mapped[list["DirectMessages"]] = relationship(
        "DirectMessages", foreign_keys="[DirectMessages.sender_id]", back_populates="sender"
    )
    events: Mapped[list["Events"]] = relationship("Events", back_populates="users")
    jwt_refresh_tokens: Mapped[list["JwtRefreshTokens"]] = relationship(
        "JwtRefreshTokens", back_populates="user"
    )
    leadership_created_by: Mapped[list["Leadership"]] = relationship(
        "Leadership", foreign_keys="[Leadership.created_by]", back_populates="users"
    )
    leadership_user: Mapped[list["Leadership"]] = relationship(
        "Leadership", foreign_keys="[Leadership.user_id]", back_populates="user"
    )
    marketplace_listings: Mapped[list["MarketplaceListings"]] = relationship(
        "MarketplaceListings", back_populates="user"
    )
    notifications: Mapped[list["Notifications"]] = relationship(
        "Notifications", back_populates="user"
    )
    payments: Mapped[list["Payments"]] = relationship("Payments", back_populates="user")
    products: Mapped[list["Products"]] = relationship("Products", back_populates="user")
    projects: Mapped[list["Projects"]] = relationship("Projects", back_populates="users")
    user_addresses: Mapped[list["UserAddresses"]] = relationship(
        "UserAddresses", back_populates="user"
    )
    user_profiles: Mapped[list["UserProfiles"]] = relationship(
        "UserProfiles", back_populates="user"
    )
    user_social_accounts: Mapped[list["UserSocialAccounts"]] = relationship(
        "UserSocialAccounts", back_populates="user"
    )
    user_subscriptions: Mapped[list["UserSubscriptions"]] = relationship(
        "UserSubscriptions", back_populates="user"
    )
    chat_group_members: Mapped[list["ChatGroupMembers"]] = relationship(
        "ChatGroupMembers", back_populates="user"
    )
    chat_messages: Mapped[list["ChatMessages"]] = relationship(
        "ChatMessages", back_populates="user"
    )
    event_attendees: Mapped[list["EventAttendees"]] = relationship(
        "EventAttendees", back_populates="user"
    )
    orders: Mapped[list["Orders"]] = relationship("Orders", back_populates="user")


class UsersGroups(Base):
    __tablename__ = "users_groups"
    __table_args__ = (
        Index("fk_users_groups_groups1_idx", "group_id"),
        Index("fk_users_groups_users1_idx", "user_id"),
        Index("uc_users_groups", "user_id", "group_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    group_id: Mapped[int] = mapped_column(MEDIUMINT(8, unsigned=True), nullable=False)


class UsersGroupsV2(Base):
    __tablename__ = "users_groups_v2"
    __table_args__ = (
        Index("fk_users_groups_groups1_idx", "group_id"),
        Index("fk_users_groups_users1_idx", "user_id"),
        Index("uc_users_groups", "user_id", "group_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    group_id: Mapped[int] = mapped_column(MEDIUMINT(8, unsigned=True), nullable=False)


class UsersLog(Base):
    __tablename__ = "users_log"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    ip_address: Mapped[str] = mapped_column(String(50), nullable=False)
    location: Mapped[str] = mapped_column(String(255), nullable=False)
    device_type: Mapped[str] = mapped_column(String(50), nullable=False)
    operating_system: Mapped[str] = mapped_column(String(50), nullable=False)
    device_model: Mapped[str] = mapped_column(String(255), nullable=False)
    name: Mapped[str | None] = mapped_column(String(100))
    email: Mapped[str | None] = mapped_column(String(100))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )


class UsersV2(Base):
    __tablename__ = "users_v2"
    __table_args__ = (
        Index("uc_activation_selector", "activation_selector", unique=True),
        Index("uc_email", "email", unique=True),
        Index("uc_forgotten_password_selector", "forgotten_password_selector", unique=True),
        Index("uc_remember_selector", "remember_selector", unique=True),
        Index("uq_users_user_code", "user_code", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    chapter_id: Mapped[int] = mapped_column(INTEGER(50), nullable=False)
    ip_address: Mapped[str] = mapped_column(String(15), nullable=False)
    username: Mapped[str] = mapped_column(String(100), nullable=False)
    email: Mapped[str] = mapped_column(String(150), nullable=False)
    password: Mapped[str] = mapped_column(String(255), nullable=False)
    has_password: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    onboarding_completion: Mapped[int] = mapped_column(
        TINYINT(1), nullable=False, server_default=text("1")
    )
    nick_name: Mapped[str] = mapped_column(String(255), nullable=False)
    state: Mapped[str] = mapped_column(String(255), nullable=False)
    country: Mapped[str] = mapped_column(String(255), nullable=False)
    created_on: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    userAccessCode: Mapped[str] = mapped_column(String(100), nullable=False)
    profile_status: Mapped[str] = mapped_column(String(255), nullable=False)
    voucher: Mapped[str] = mapped_column(String(225), nullable=False)
    resetKey: Mapped[str] = mapped_column(String(255), nullable=False)
    user_code: Mapped[str | None] = mapped_column(
        String(25), comment="6-digit unique numeric code for frontend use"
    )
    first_name: Mapped[str | None] = mapped_column(String(50))
    last_name: Mapped[str | None] = mapped_column(String(50))
    fullname: Mapped[str | None] = mapped_column(String(100))
    name_in_school: Mapped[str | None] = mapped_column(
        String(200), comment="Name used in school e.g. First Name + Surname"
    )
    phone: Mapped[str | None] = mapped_column(String(20))
    alternative_phone: Mapped[str | None] = mapped_column(
        String(20), comment="Alternative / secondary phone number"
    )
    avatar: Mapped[str | None] = mapped_column(String(255), server_default=text("'default.png'"))
    birth_date: Mapped[datetime.date | None] = mapped_column(Date, comment="Date of birth")
    house_color: Mapped[str | None] = mapped_column(
        String(50), comment="House colour e.g. Yellow, Red, Blue, Green"
    )
    is_coordinator: Mapped[int | None] = mapped_column(
        TINYINT(1), server_default=text("0"), comment="True = class coordinator, 0 = not"
    )
    residential_address: Mapped[str | None] = mapped_column(
        Text, comment="Full residential address with landmark"
    )
    area: Mapped[str | None] = mapped_column(String(100), comment="Area e.g. Mainland, Island")
    city: Mapped[str | None] = mapped_column(String(100), comment="City e.g. Ikeja, Lekki, Abuja")
    employment_status: Mapped[str | None] = mapped_column(
        String(100), comment="Current employment status"
    )
    occupation: Mapped[str | None] = mapped_column(Text, comment="Occupation(s) / Profession(s)")
    industry_sector: Mapped[str | None] = mapped_column(Text, comment="Industry sector(s)")
    years_of_experience: Mapped[str | None] = mapped_column(
        String(50), comment="Years of professional experience"
    )
    is_volunteer: Mapped[int | None] = mapped_column(
        TINYINT(1), server_default=text("0"), comment="True = interested in volunteering, 0 = no"
    )
    graduation_year: Mapped[int | None] = mapped_column(INTEGER(11))
    department: Mapped[str | None] = mapped_column(String(200))
    bio: Mapped[str | None] = mapped_column(Text)
    salt: Mapped[str | None] = mapped_column(String(255))
    activation_selector: Mapped[str | None] = mapped_column(String(255))
    activation_code: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_selector: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_code: Mapped[str | None] = mapped_column(String(255))
    forgotten_password_time: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    remember_selector: Mapped[str | None] = mapped_column(String(255))
    remember_code: Mapped[str | None] = mapped_column(String(255))
    last_login: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    active: Mapped[int | None] = mapped_column(TINYINT(3, unsigned=True))
    user_role: Mapped[str | None] = mapped_column(String(20))
    is_approved: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    email_verified: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    verify_token: Mapped[str | None] = mapped_column(String(100))
    reset_token: Mapped[str | None] = mapped_column(String(100))
    reset_expires: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    device_token: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="Alumni graduation/membership year e.g. 2024"
    )


class Vouches(Base):
    __tablename__ = "vouches"
    __table_args__ = (
        Index("idx_register_id", "register_id"),
        Index("idx_status", "status"),
        Index("idx_voucher_id", "voucher_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    register_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    voucher_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    status: Mapped[VouchesStatus] = mapped_column(
        Enum(VouchesStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'pending'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    updated_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    reason: Mapped[str | None] = mapped_column(MEDIUMTEXT)


class Zones(Base):
    __tablename__ = "zones"

    zone_id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    zone: Mapped[str] = mapped_column(String(100), nullable=False)
    chapter_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False, server_default=text("1"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    coordinator_user_id: Mapped[int | None] = mapped_column(INTEGER(11))
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)


class AlumniCategory(Base):
    __tablename__ = "alumni_category"
    __table_args__ = (
        ForeignKeyConstraint(["chapter_id"], ["alumni_chapter.id"], name="fk_ac_chapter"),
        ForeignKeyConstraint(
            ["user_id"], ["users.id"], ondelete="CASCADE", onupdate="CASCADE", name="fk_ac_user"
        ),
        Index("fk_ac_chapter", "chapter_id"),
        Index("uq_user_chapter", "user_id", "chapter_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    chapter_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    year: Mapped[str] = mapped_column(String(4), nullable=False, comment="Alumni year e.g. 2024")
    location: Mapped[str] = mapped_column(String(200), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )

    chapter: Mapped["AlumniChapter"] = relationship(
        "AlumniChapter", back_populates="alumni_category"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="alumni_category")


class Announcements(Base):
    __tablename__ = "announcements"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_announcements_chapter",
        ),
        ForeignKeyConstraint(
            ["created_by"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="announcements_ibfk_1",
        ),
        Index("announcements_ibfk_1", "created_by"),
        Index("fk_announcements_chapter", "chapter_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    title: Mapped[str] = mapped_column(String(255), nullable=False)
    content: Mapped[str] = mapped_column(Text, nullable=False)
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    imag: Mapped[str | None] = mapped_column(String(255))
    type: Mapped[AnnouncementsType | None] = mapped_column(
        Enum(AnnouncementsType, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'info'"),
    )
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11),
        server_default=text("1"),
        comment="NULL means announcement is global/all chapters",
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="NULL = all years, set value to target a specific year"
    )
    starts_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    ends_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="announcements"
    )
    users: Mapped["Users"] = relationship("Users", back_populates="announcements")


class BlogPosts(Base):
    __tablename__ = "blog_posts"
    __table_args__ = (
        ForeignKeyConstraint(["category_id"], ["blog_categories.id"], name="blog_posts_ibfk_1"),
        Index("idx_category_id", "category_id"),
        Index("idx_deleted_at", "deleted_at"),
        Index("idx_published_at", "published_at"),
        Index("idx_slug", "slug"),
        Index("idx_status", "status"),
        Index("slug", "slug", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    slug: Mapped[str] = mapped_column(String(255), nullable=False)
    title: Mapped[str] = mapped_column(String(255), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    excerpt: Mapped[str | None] = mapped_column(Text)
    category_id: Mapped[int | None] = mapped_column(INTEGER(11))
    status: Mapped[BlogPostsStatus | None] = mapped_column(
        Enum(BlogPostsStatus, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'draft'"),
    )
    cover_image_url: Mapped[str | None] = mapped_column(String(500))
    read_time_minutes: Mapped[int | None] = mapped_column(INTEGER(11))
    published_at: Mapped[datetime.datetime | None] = mapped_column(TIMESTAMP)
    deleted_at: Mapped[datetime.datetime | None] = mapped_column(TIMESTAMP)

    category: Mapped[Optional["BlogCategories"]] = relationship(
        "BlogCategories", back_populates="blog_posts"
    )
    blog_gallery: Mapped[list["BlogGallery"]] = relationship("BlogGallery", back_populates="post")
    blog_sections: Mapped[list["BlogSections"]] = relationship(
        "BlogSections", back_populates="post"
    )


class Carts(Base):
    __tablename__ = "carts"
    __table_args__ = (
        ForeignKeyConstraint(["user_id"], ["users.id"], ondelete="CASCADE", name="fk_cart_user"),
        Index("idx_carts_user_id", "user_id"),
        Index("unique_active_cart", "user_id", "active_flag", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    status: Mapped[CartsStatus] = mapped_column(
        Enum(CartsStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'active'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    active_flag: Mapped[int | None] = mapped_column(
        TINYINT(1), Computed("(if(`status` = 'active',1,NULL))", persisted=False)
    )

    user: Mapped["Users"] = relationship("Users", back_populates="carts")
    orders: Mapped[list["Orders"]] = relationship("Orders", back_populates="cart")
    cart_items: Mapped[list["CartItems"]] = relationship("CartItems", back_populates="cart")


class ChatGroups(Base):
    __tablename__ = "chat_groups"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_chat_groups_chapter",
        ),
        ForeignKeyConstraint(
            ["created_by"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="chat_groups_ibfk_1",
        ),
        Index("chat_groups_ibfk_1", "created_by"),
        Index("fk_chat_groups_chapter", "chapter_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    type: Mapped[ChatGroupsType] = mapped_column(
        Enum(ChatGroupsType, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'group'"),
    )
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    name: Mapped[str | None] = mapped_column(String(200))
    description: Mapped[str | None] = mapped_column(Text)
    cover_image: Mapped[str | None] = mapped_column(String(255))
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="NULL means group spans all chapters"
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="NULL = cross-year group, set to scope group to a year"
    )
    is_private: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    required_role: Mapped[ChatGroupsRequiredRole | None] = mapped_column(
        Enum(ChatGroupsRequiredRole, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'member'"),
    )
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="chat_groups"
    )
    users: Mapped["Users"] = relationship("Users", back_populates="chat_groups")
    chat_group_members: Mapped[list["ChatGroupMembers"]] = relationship(
        "ChatGroupMembers", back_populates="group"
    )
    chat_messages: Mapped[list["ChatMessages"]] = relationship(
        "ChatMessages", back_populates="group"
    )


class DirectMessages(Base):
    __tablename__ = "direct_messages"
    __table_args__ = (
        ForeignKeyConstraint(
            ["receiver_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="direct_messages_ibfk_2",
        ),
        ForeignKeyConstraint(
            ["sender_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="direct_messages_ibfk_1",
        ),
        Index("direct_messages_ibfk_1", "sender_id"),
        Index("direct_messages_ibfk_2", "receiver_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    sender_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    receiver_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    message: Mapped[str] = mapped_column(Text, nullable=False)
    is_read: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    is_deleted: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    receiver: Mapped["Users"] = relationship(
        "Users", foreign_keys=[receiver_id], back_populates="direct_messages_receiver"
    )
    sender: Mapped["Users"] = relationship(
        "Users", foreign_keys=[sender_id], back_populates="direct_messages_sender"
    )


class Events(Base):
    __tablename__ = "events"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_events_chapter",
        ),
        ForeignKeyConstraint(
            ["created_by"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="events_ibfk_1",
        ),
        Index("events_ibfk_1", "created_by"),
        Index("fk_events_chapter", "chapter_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    title: Mapped[str] = mapped_column(String(250), nullable=False)
    event_banner: Mapped[str] = mapped_column(String(255), nullable=False)
    status: Mapped[str] = mapped_column(String(255), nullable=False)
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    location: Mapped[str | None] = mapped_column(String(255))
    event_date: Mapped[datetime.date | None] = mapped_column(Date)
    start_date: Mapped[datetime.date | None] = mapped_column(Date)
    end_date: Mapped[datetime.date | None] = mapped_column(Date)
    start_time: Mapped[datetime.time | None] = mapped_column(Time)
    end_time: Mapped[datetime.time | None] = mapped_column(Time)
    color: Mapped[str | None] = mapped_column(String(20), server_default=text("'#4f46e5'"))
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="NULL means event is open to all chapters"
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="NULL = all years, set value to scope event to a year"
    )
    visibility: Mapped[EventsVisibility | None] = mapped_column(
        Enum(EventsVisibility, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'members'"),
    )
    is_approved: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    max_attendees: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="events"
    )
    users: Mapped["Users"] = relationship("Users", back_populates="events")
    event_attendees: Mapped[list["EventAttendees"]] = relationship(
        "EventAttendees", back_populates="event"
    )


class JwtRefreshTokens(Base):
    __tablename__ = "jwt_refresh_tokens"
    __table_args__ = (
        ForeignKeyConstraint(
            ["user_id"], ["users.id"], ondelete="CASCADE", onupdate="CASCADE", name="fk_jwt_user"
        ),
        Index("idx_revoked", "revoked"),
        Index("idx_token", "token"),
        Index("idx_user_id", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    token: Mapped[str] = mapped_column(
        String(64), nullable=False, comment="SHA256 hash of the refresh token"
    )
    revoked: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    expires_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)

    user: Mapped["Users"] = relationship("Users", back_populates="jwt_refresh_tokens")


class Leadership(Base):
    __tablename__ = "leadership"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_leadership_chapter",
        ),
        ForeignKeyConstraint(
            ["created_by"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="fk_leadership_created_by",
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="fk_leadership_user",
        ),
        Index("idx_leadership_chapter", "chapter_id"),
        Index("idx_leadership_created_by", "created_by"),
        Index("idx_leadership_deleted", "is_deleted"),
        Index("idx_leadership_featured", "is_featured"),
        Index("idx_leadership_sort", "sort_order"),
        Index("uq_leadership_user_year", "user_id", "year", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), nullable=False, comment="Must exist in users table"
    )
    position_title: Mapped[str] = mapped_column(
        String(150), nullable=False, comment="e.g. President, Vice President, P.R.O"
    )
    sort_order: Mapped[int] = mapped_column(
        INTEGER(11),
        nullable=False,
        server_default=text("0"),
        comment="Lower = shown first (President = 1)",
    )
    is_featured: Mapped[int] = mapped_column(
        TINYINT(1),
        nullable=False,
        server_default=text("0"),
        comment="1 = show in hero President block",
    )
    is_active: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("1"))
    is_deleted: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    message: Mapped[str | None] = mapped_column(
        Text, comment="Welcome/speech text (mainly for President)"
    )
    leadership_photo: Mapped[str | None] = mapped_column(
        String(500), comment="Override photo; if NULL, falls back to users.avatar"
    )
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="NULL = all chapters / global"
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment='NULL = all years, e.g. "2026" for annual exco'
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="leadership"
    )
    users: Mapped["Users"] = relationship(
        "Users", foreign_keys=[created_by], back_populates="leadership_created_by"
    )
    user: Mapped["Users"] = relationship(
        "Users", foreign_keys=[user_id], back_populates="leadership_user"
    )


class MarketplaceListings(Base):
    __tablename__ = "marketplace_listings"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_marketplace_chapter",
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="marketplace_listings_ibfk_1",
        ),
        Index("fk_marketplace_chapter", "chapter_id"),
        Index("marketplace_listings_ibfk_1", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    title: Mapped[str] = mapped_column(String(250), nullable=False)
    message_prompt: Mapped[str] = mapped_column(Text, nullable=False, server_default=text("''"))
    business_name: Mapped[str] = mapped_column(String(255), nullable=False)
    phone: Mapped[str] = mapped_column(String(255), nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="Chapter the listing belongs to"
    )
    year: Mapped[str | None] = mapped_column(
        String(4), comment="NULL = all years, set to scope listing to a year"
    )
    description: Mapped[str | None] = mapped_column(Text)
    category: Mapped[MarketplaceListingsCategory | None] = mapped_column(
        Enum(
            MarketplaceListingsCategory,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'other'"),
    )
    price: Mapped[decimal.Decimal | None] = mapped_column(
        DECIMAL(10, 2), server_default=text("0.00")
    )
    price_type: Mapped[MarketplaceListingsPriceType | None] = mapped_column(
        Enum(
            MarketplaceListingsPriceType,
            values_callable=lambda cls: [member.value for member in cls],
        ),
        server_default=text("'fixed'"),
    )
    images: Mapped[str | None] = mapped_column(Text)
    contact_info: Mapped[str | None] = mapped_column(String(255))
    whatsapp: Mapped[str | None] = mapped_column(
        String(50), comment="WhatsApp number e.g. 08022222222"
    )
    website: Mapped[str | None] = mapped_column(String(255))
    location: Mapped[str | None] = mapped_column(String(200))
    status: Mapped[MarketplaceListingsStatus | None] = mapped_column(
        Enum(
            MarketplaceListingsStatus, values_callable=lambda cls: [member.value for member in cls]
        ),
        server_default=text("'pending'"),
    )
    is_featured: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    views: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))
    expires_at: Mapped[datetime.date | None] = mapped_column(Date)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="marketplace_listings"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="marketplace_listings")
    marketplace_social_media: Mapped[list["MarketplaceSocialMedia"]] = relationship(
        "MarketplaceSocialMedia", back_populates="market"
    )


class Notifications(Base):
    __tablename__ = "notifications"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_notifications_chapter",
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="notifications_ibfk_1",
        ),
        Index("fk_notifications_chapter", "chapter_id"),
        Index("notifications_ibfk_1", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    type: Mapped[str] = mapped_column(String(50), nullable=False)
    message: Mapped[str] = mapped_column(Text, nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="NULL means notification is user-specific, not chapter-based"
    )
    year: Mapped[str | None] = mapped_column(String(4), comment="NULL = all years")
    link: Mapped[str | None] = mapped_column(String(255))
    is_read: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="notifications"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="notifications")


class Payments(Base):
    __tablename__ = "payments"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_payments_chapter",
        ),
        ForeignKeyConstraint(["user_id"], ["users.id"], onupdate="CASCADE", name="payments_ibfk_1"),
        Index("fk_payments_chapter", "chapter_id"),
        Index("payments_ibfk_1", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    amount: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(INTEGER(11))
    year: Mapped[str | None] = mapped_column(
        String(4), comment="Payment year context e.g. dues year"
    )
    plan_id: Mapped[int | None] = mapped_column(INTEGER(11))
    currency: Mapped[str | None] = mapped_column(String(10), server_default=text("'NGN'"))
    payment_method: Mapped[str | None] = mapped_column(String(50))
    transaction_id: Mapped[str | None] = mapped_column(String(150))
    status: Mapped[PaymentsStatus | None] = mapped_column(
        Enum(PaymentsStatus, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'pending'"),
    )
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="payments"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="payments")


class Products(Base):
    __tablename__ = "products"
    __table_args__ = (
        ForeignKeyConstraint(["user_id"], ["users.id"], name="fk_products_user"),
        Index("idx_products_category", "category"),
        Index("idx_products_user_id", "user_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    product_name: Mapped[str] = mapped_column(String(100), nullable=False)
    category: Mapped[str] = mapped_column(String(50), nullable=False)
    price: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    description: Mapped[str] = mapped_column(Text, nullable=False)
    has_size: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    has_color: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    status: Mapped[ProductsStatus] = mapped_column(
        Enum(ProductsStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'active'"),
    )
    pin_item: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    quantity: Mapped[int | None] = mapped_column(INTEGER(11))

    user: Mapped["Users"] = relationship("Users", back_populates="products")
    product_images: Mapped[list["ProductImages"]] = relationship(
        "ProductImages", back_populates="product"
    )
    product_variants: Mapped[list["ProductVariants"]] = relationship(
        "ProductVariants", back_populates="product"
    )
    cart_items: Mapped[list["CartItems"]] = relationship("CartItems", back_populates="product")
    order_items: Mapped[list["OrderItems"]] = relationship("OrderItems", back_populates="product")


class Projects(Base):
    __tablename__ = "projects"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_projects_chapter",
        ),
        ForeignKeyConstraint(
            ["created_by"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="fk_projects_created_by",
        ),
        Index("idx_projects_chapter", "chapter_id"),
        Index("idx_projects_created_by", "created_by"),
        Index("idx_projects_deleted", "is_deleted"),
        Index("idx_projects_status", "status"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    title: Mapped[str] = mapped_column(String(255), nullable=False)
    amount_raised: Mapped[decimal.Decimal] = mapped_column(
        DECIMAL(15, 2), nullable=False, server_default=text("0.00")
    )
    status: Mapped[ProjectsStatus] = mapped_column(
        Enum(ProjectsStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'active'"),
    )
    location: Mapped[str] = mapped_column(String(255), nullable=False)
    sort_order: Mapped[int] = mapped_column(
        INTEGER(11), nullable=False, server_default=text("0"), comment="Lower = shown first"
    )
    is_featured: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    is_deleted: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_by: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    images: Mapped[str | None] = mapped_column(Text)
    target_amount: Mapped[decimal.Decimal | None] = mapped_column(DECIMAL(15, 2))
    chapter_id: Mapped[int | None] = mapped_column(
        INTEGER(11), comment="NULL = global / all chapters"
    )
    year: Mapped[str | None] = mapped_column(String(4), comment="NULL = all years")
    start_date: Mapped[datetime.datetime | None] = mapped_column(DATETIME(fsp=5))
    end_date: Mapped[datetime.datetime | None] = mapped_column(DATETIME(fsp=5))
    conducted_by: Mapped[str | None] = mapped_column(String(255))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="projects"
    )
    users: Mapped["Users"] = relationship("Users", back_populates="projects")


class UserAddresses(Base):
    __tablename__ = "user_addresses"
    __table_args__ = (
        ForeignKeyConstraint(
            ["user_id"], ["users.id"], ondelete="CASCADE", name="fk_user_addresses_user"
        ),
        Index("idx_user_addresses_user_id", "user_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    first_name: Mapped[str] = mapped_column(String(75), nullable=False)
    last_name: Mapped[str] = mapped_column(String(75), nullable=False)
    phone: Mapped[str] = mapped_column(String(20), nullable=False)
    address: Mapped[str] = mapped_column(String(255), nullable=False)
    state: Mapped[str] = mapped_column(String(100), nullable=False)
    area: Mapped[str] = mapped_column(String(100), nullable=False)
    is_default: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    additional_phone: Mapped[str | None] = mapped_column(String(20))
    landmark: Mapped[str | None] = mapped_column(String(255))

    user: Mapped["Users"] = relationship("Users", back_populates="user_addresses")


class UserProfiles(Base):
    __tablename__ = "user_profiles"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_user_profiles_chapter",
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="user_profiles_ibfk_1",
        ),
        Index("fk_user_profiles_chapter", "chapter_id"),
        Index("user_id", "user_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    instagram: Mapped[str] = mapped_column(String(255), nullable=False)
    tiktok: Mapped[str] = mapped_column(String(255), nullable=False)
    updated_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(INTEGER(11))
    year: Mapped[str | None] = mapped_column(String(4), comment="Profile year context")
    linkedin: Mapped[str | None] = mapped_column(String(255))
    twitter: Mapped[str | None] = mapped_column(String(255))
    facebook: Mapped[str | None] = mapped_column(String(255))
    website: Mapped[str | None] = mapped_column(String(255))
    current_company: Mapped[str | None] = mapped_column(String(200))
    current_position: Mapped[str | None] = mapped_column(String(200))
    city: Mapped[str | None] = mapped_column(String(100))
    country: Mapped[str | None] = mapped_column(String(100))
    skills: Mapped[str | None] = mapped_column(Text)
    achievements: Mapped[str | None] = mapped_column(Text)
    is_visible: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("1"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    field_visibility: Mapped[str | None] = mapped_column(Text)

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="user_profiles"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="user_profiles")


class UserSocialAccounts(Base):
    __tablename__ = "user_social_accounts"
    __table_args__ = (
        ForeignKeyConstraint(["user_id"], ["users.id"], ondelete="CASCADE", name="fk_social_user"),
        Index("idx_user_id", "user_id"),
        Index("provider_user_unique", "provider", "provider_user_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    provider: Mapped[UserSocialAccountsProvider] = mapped_column(
        Enum(
            UserSocialAccountsProvider, values_callable=lambda cls: [member.value for member in cls]
        ),
        nullable=False,
    )
    provider_user_id: Mapped[str] = mapped_column(String(255), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    email: Mapped[str | None] = mapped_column(String(255))

    user: Mapped["Users"] = relationship("Users", back_populates="user_social_accounts")


class UserSubscriptions(Base):
    __tablename__ = "user_subscriptions"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_user_subscriptions_chapter",
        ),
        ForeignKeyConstraint(
            ["plan_id"], ["subscription_plans.id"], name="user_subscriptions_ibfk_2"
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="user_subscriptions_ibfk_1",
        ),
        Index("fk_user_subscriptions_chapter", "chapter_id"),
        Index("plan_id", "plan_id"),
        Index("user_subscriptions_ibfk_1", "user_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    plan_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    starts_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    expires_at: Mapped[datetime.datetime] = mapped_column(DateTime, nullable=False)
    chapter_id: Mapped[int | None] = mapped_column(INTEGER(11))
    year: Mapped[str | None] = mapped_column(String(4), comment="Subscription year")
    status: Mapped[UserSubscriptionsStatus | None] = mapped_column(
        Enum(UserSubscriptionsStatus, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'active'"),
    )
    payment_ref: Mapped[str | None] = mapped_column(String(100))
    amount_paid: Mapped[decimal.Decimal | None] = mapped_column(DECIMAL(10, 2))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="user_subscriptions"
    )
    plan: Mapped["SubscriptionPlans"] = relationship(
        "SubscriptionPlans", back_populates="user_subscriptions"
    )
    user: Mapped["Users"] = relationship("Users", back_populates="user_subscriptions")


class Users2(Base):
    __tablename__ = "users2"
    __table_args__ = (
        ForeignKeyConstraint(
            ["chapter_id"],
            ["alumni_chapter.id"],
            ondelete="SET NULL",
            onupdate="CASCADE",
            name="fk_users2_chapter",
        ),
        Index("email", "email", unique=True),
        Index("fk_users2_chapter", "chapter_id"),
        Index("username", "username", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    username: Mapped[str] = mapped_column(String(100), nullable=False)
    email: Mapped[str] = mapped_column(String(150), nullable=False)
    password: Mapped[str] = mapped_column(String(255), nullable=False)
    full_name: Mapped[str] = mapped_column(String(200), nullable=False)
    phone: Mapped[str | None] = mapped_column(String(20))
    avatar: Mapped[str | None] = mapped_column(String(255), server_default=text("'default.png'"))
    graduation_year: Mapped[int | None] = mapped_column(INTEGER(11))
    department: Mapped[str | None] = mapped_column(String(200))
    bio: Mapped[str | None] = mapped_column(Text)
    role: Mapped[Users2Role | None] = mapped_column(
        Enum(Users2Role, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'pending'"),
    )
    chapter_id: Mapped[int | None] = mapped_column(INTEGER(11))
    is_active: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    is_approved: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    email_verified: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    verify_token: Mapped[str | None] = mapped_column(String(100))
    reset_token: Mapped[str | None] = mapped_column(String(100))
    reset_expires: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    last_login: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp() ON UPDATE current_timestamp()")
    )

    chapter: Mapped[Optional["AlumniChapter"]] = relationship(
        "AlumniChapter", back_populates="users2"
    )


class BlogGallery(Base):
    __tablename__ = "blog_gallery"
    __table_args__ = (
        ForeignKeyConstraint(
            ["post_id"], ["blog_posts.id"], ondelete="CASCADE", name="blog_gallery_ibfk_1"
        ),
        Index("idx_post_id", "post_id"),
        Index("idx_sort_order", "sort_order"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    post_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    image_url: Mapped[str] = mapped_column(String(500), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    file_name: Mapped[str | None] = mapped_column(String(255))
    alt_text: Mapped[str | None] = mapped_column(String(255))
    sort_order: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))

    post: Mapped["BlogPosts"] = relationship("BlogPosts", back_populates="blog_gallery")


class BlogSections(Base):
    __tablename__ = "blog_sections"
    __table_args__ = (
        ForeignKeyConstraint(
            ["post_id"], ["blog_posts.id"], ondelete="CASCADE", name="blog_sections_ibfk_1"
        ),
        Index("idx_post_id", "post_id"),
        Index("idx_sort_order", "sort_order"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    post_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    heading: Mapped[str | None] = mapped_column(String(255))
    body: Mapped[str | None] = mapped_column(LONGTEXT)
    sort_order: Mapped[int | None] = mapped_column(INTEGER(11), server_default=text("0"))

    post: Mapped["BlogPosts"] = relationship("BlogPosts", back_populates="blog_sections")


class ChatGroupMembers(Base):
    __tablename__ = "chat_group_members"
    __table_args__ = (
        ForeignKeyConstraint(
            ["group_id"], ["chat_groups.id"], ondelete="CASCADE", name="chat_group_members_ibfk_1"
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="chat_group_members_ibfk_2",
        ),
        Index("chat_group_members_ibfk_2", "user_id"),
        Index("uq_group_user", "group_id", "user_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    group_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    is_pinned: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    role: Mapped[ChatGroupMembersRole | None] = mapped_column(
        Enum(ChatGroupMembersRole, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'member'"),
    )
    joined_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )
    last_read_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    group: Mapped["ChatGroups"] = relationship("ChatGroups", back_populates="chat_group_members")
    user: Mapped["Users"] = relationship("Users", back_populates="chat_group_members")


class ChatMessages(Base):
    __tablename__ = "chat_messages"
    __table_args__ = (
        ForeignKeyConstraint(
            ["group_id"], ["chat_groups.id"], ondelete="CASCADE", name="chat_messages_ibfk_1"
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="chat_messages_ibfk_2",
        ),
        Index("chat_messages_ibfk_2", "user_id"),
        Index("group_id", "group_id"),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    group_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    message: Mapped[str] = mapped_column(Text, nullable=False)
    attachment: Mapped[str | None] = mapped_column(String(255))
    attachment_type: Mapped[ChatMessagesAttachmentType | None] = mapped_column(
        Enum(
            ChatMessagesAttachmentType, values_callable=lambda cls: [member.value for member in cls]
        )
    )
    is_deleted: Mapped[int | None] = mapped_column(TINYINT(1), server_default=text("0"))
    created_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    group: Mapped["ChatGroups"] = relationship("ChatGroups", back_populates="chat_messages")
    user: Mapped["Users"] = relationship("Users", back_populates="chat_messages")


class EventAttendees(Base):
    __tablename__ = "event_attendees"
    __table_args__ = (
        ForeignKeyConstraint(
            ["event_id"], ["events.id"], ondelete="CASCADE", name="event_attendees_ibfk_1"
        ),
        ForeignKeyConstraint(
            ["user_id"],
            ["users.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="event_attendees_ibfk_2",
        ),
        Index("event_attendees_ibfk_2", "user_id"),
        Index("uq_event_user", "event_id", "user_id", unique=True),
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    event_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    year: Mapped[str | None] = mapped_column(
        String(4), comment="Inherited from event or set explicitly"
    )
    additional_info: Mapped[str | None] = mapped_column(Text)
    status: Mapped[EventAttendeesStatus | None] = mapped_column(
        Enum(EventAttendeesStatus, values_callable=lambda cls: [member.value for member in cls]),
        server_default=text("'going'"),
    )
    registered_at: Mapped[datetime.datetime | None] = mapped_column(
        DateTime, server_default=text("current_timestamp()")
    )

    event: Mapped["Events"] = relationship("Events", back_populates="event_attendees")
    user: Mapped["Users"] = relationship("Users", back_populates="event_attendees")
    event_registration_answers: Mapped[list["EventRegistrationAnswers"]] = relationship(
        "EventRegistrationAnswers", back_populates="attendee"
    )


class MarketplaceSocialMedia(Base):
    __tablename__ = "marketplace_social_media"
    __table_args__ = (
        ForeignKeyConstraint(
            ["market_id"],
            ["marketplace_listings.id"],
            ondelete="CASCADE",
            onupdate="CASCADE",
            name="fk_social_market_id",
        ),
        Index("fk_social_market_id", "market_id"),
        Index("uq_market_social", "market_id", unique=True),
        {"comment": "Social media profiles linked to marketplace listings"},
    )

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True, autoincrement=True)
    market_id: Mapped[int] = mapped_column(
        INTEGER(11), nullable=False, comment="FK → marketplace_listings.id"
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    instagram_handle: Mapped[str | None] = mapped_column(
        String(150), comment="Instagram username e.g. @mybusiness"
    )
    instagram_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://instagram.com/mybusiness"
    )
    instagram_hashtag: Mapped[str | None] = mapped_column(String(255))
    twitter_handle: Mapped[str | None] = mapped_column(
        String(150), comment="Twitter username e.g. @mybusiness"
    )
    twitter_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://twitter.com/mybusiness"
    )
    linkedin_handle: Mapped[str | None] = mapped_column(String(150), comment="LinkedIn page name")
    linkedin_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://linkedin.com/company/mybusiness"
    )
    facebook_handle: Mapped[str | None] = mapped_column(String(150), comment="Facebook page name")
    facebook_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://facebook.com/mybusiness"
    )
    tiktok_handle: Mapped[str | None] = mapped_column(
        String(150), comment="TikTok username e.g. @mybusiness"
    )
    tiktok_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://tiktok.com/@mybusiness"
    )
    youtube_handle: Mapped[str | None] = mapped_column(String(150), comment="YouTube channel name")
    youtube_url: Mapped[str | None] = mapped_column(
        String(500), comment="https://youtube.com/@mybusiness"
    )

    market: Mapped["MarketplaceListings"] = relationship(
        "MarketplaceListings", back_populates="marketplace_social_media"
    )


class Orders(Base):
    __tablename__ = "orders"
    __table_args__ = (
        ForeignKeyConstraint(["cart_id"], ["carts.id"], ondelete="SET NULL", name="fk_orders_cart"),
        ForeignKeyConstraint(["user_id"], ["users.id"], name="fk_orders_user"),
        Index("fk_orders_cart", "cart_id"),
        Index("idx_orders_paystack_reference", "paystack_reference"),
        Index("idx_orders_status", "status"),
        Index("idx_orders_user_id", "user_id"),
        Index("paystack_reference", "paystack_reference", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    user_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    order_number: Mapped[str] = mapped_column(String(50), nullable=False)
    paystack_reference: Mapped[str] = mapped_column(String(100), nullable=False)
    subtotal: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    shipping_fee: Mapped[decimal.Decimal] = mapped_column(
        DECIMAL(10, 2), nullable=False, server_default=text("0.00")
    )
    amount: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    delivery_type: Mapped[OrdersDeliveryType] = mapped_column(
        Enum(OrdersDeliveryType, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'self_pickup'"),
    )
    status: Mapped[OrdersStatus] = mapped_column(
        Enum(OrdersStatus, values_callable=lambda cls: [member.value for member in cls]),
        nullable=False,
        server_default=text("'pending'"),
    )
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    cart_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    paystack_access_code: Mapped[str | None] = mapped_column(String(100))
    first_name: Mapped[str | None] = mapped_column(String(75))
    last_name: Mapped[str | None] = mapped_column(String(75))
    phone: Mapped[str | None] = mapped_column(String(20))
    additional_phone: Mapped[str | None] = mapped_column(String(20))
    address: Mapped[str | None] = mapped_column(String(255))
    landmark: Mapped[str | None] = mapped_column(String(255))
    state: Mapped[str | None] = mapped_column(String(100))
    area: Mapped[str | None] = mapped_column(String(100))
    shipped_note: Mapped[str | None] = mapped_column(Text)
    rider_details: Mapped[str | None] = mapped_column(Text)
    delivered_note: Mapped[str | None] = mapped_column(Text)
    payment_payload: Mapped[str | None] = mapped_column(
        LONGTEXT(charset="utf8mb4", collation="utf8mb4_bin")
    )
    paid_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    completion_date: Mapped[datetime.datetime | None] = mapped_column(DateTime)
    cancelled_at: Mapped[datetime.datetime | None] = mapped_column(DateTime)

    cart: Mapped[Optional["Carts"]] = relationship("Carts", back_populates="orders")
    user: Mapped["Users"] = relationship("Users", back_populates="orders")
    order_items: Mapped[list["OrderItems"]] = relationship("OrderItems", back_populates="order")


class ProductImages(Base):
    __tablename__ = "product_images"
    __table_args__ = (
        ForeignKeyConstraint(
            ["product_id"], ["products.id"], ondelete="CASCADE", name="fk_product_images_product"
        ),
        Index("idx_product_images_product_id", "product_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    product_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    image_path: Mapped[str] = mapped_column(Text, nullable=False)
    is_spotlight: Mapped[int] = mapped_column(TINYINT(1), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )

    product: Mapped["Products"] = relationship("Products", back_populates="product_images")
    product_variants: Mapped[list["ProductVariants"]] = relationship(
        "ProductVariants", back_populates="image"
    )


class EventRegistrationAnswers(Base):
    __tablename__ = "event_registration_answers"
    __table_args__ = (
        ForeignKeyConstraint(
            ["attendee_id"], ["event_attendees.id"], ondelete="CASCADE", name="fk_era_attendee"
        ),
        Index("idx_attendee_id", "attendee_id"),
        Index("idx_form_question", "form_id", "question_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    attendee_id: Mapped[int] = mapped_column(INTEGER(11), nullable=False)
    form_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    form_version: Mapped[int] = mapped_column(SMALLINT(5, unsigned=True), nullable=False)
    question_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    question_label_snapshot: Mapped[str] = mapped_column(Text, nullable=False)
    question_type: Mapped[str] = mapped_column(String(50), nullable=False)
    question_order: Mapped[int] = mapped_column(SMALLINT(5, unsigned=True), nullable=False)
    required_snapshot: Mapped[int] = mapped_column(TINYINT(1), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    answer_text: Mapped[str | None] = mapped_column(Text)
    answer_json: Mapped[str | None] = mapped_column(
        LONGTEXT(charset="utf8mb4", collation="utf8mb4_bin")
    )

    attendee: Mapped["EventAttendees"] = relationship(
        "EventAttendees", back_populates="event_registration_answers"
    )


class ProductVariants(Base):
    __tablename__ = "product_variants"
    __table_args__ = (
        ForeignKeyConstraint(
            ["image_id"],
            ["product_images.id"],
            ondelete="SET NULL",
            name="fk_product_variants_image",
        ),
        ForeignKeyConstraint(
            ["product_id"], ["products.id"], ondelete="CASCADE", name="fk_product_variants_product"
        ),
        Index("idx_product_variants_image_id", "image_id"),
        Index("idx_product_variants_product_id", "product_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    product_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    quantity: Mapped[int] = mapped_column(INTEGER(11), nullable=False, server_default=text("0"))
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    image_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    color: Mapped[str | None] = mapped_column(String(50))
    size: Mapped[str | None] = mapped_column(String(50))

    image: Mapped[Optional["ProductImages"]] = relationship(
        "ProductImages", back_populates="product_variants"
    )
    product: Mapped["Products"] = relationship("Products", back_populates="product_variants")
    cart_items: Mapped[list["CartItems"]] = relationship("CartItems", back_populates="variant")
    order_items: Mapped[list["OrderItems"]] = relationship("OrderItems", back_populates="variant")


class CartItems(Base):
    __tablename__ = "cart_items"
    __table_args__ = (
        ForeignKeyConstraint(
            ["cart_id"], ["carts.id"], ondelete="CASCADE", name="fk_cart_item_cart"
        ),
        ForeignKeyConstraint(
            ["product_id"], ["products.id"], ondelete="CASCADE", name="fk_cart_item_product"
        ),
        ForeignKeyConstraint(
            ["variant_id"], ["product_variants.id"], ondelete="CASCADE", name="fk_cart_item_variant"
        ),
        Index("fk_cart_item_variant", "variant_id"),
        Index("idx_cart_items_cart_id", "cart_id"),
        Index("idx_cart_items_product_id", "product_id"),
        Index("unique_cart_item", "cart_id", "product_id", "variant_id", unique=True),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    cart_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    product_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    unit_price: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    quantity: Mapped[int] = mapped_column(INTEGER(11), nullable=False, server_default=text("1"))
    line_total: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP, nullable=False, server_default=text("current_timestamp()")
    )
    updated_at: Mapped[datetime.datetime] = mapped_column(
        TIMESTAMP,
        nullable=False,
        server_default=text("current_timestamp() ON UPDATE current_timestamp()"),
    )
    variant_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))

    cart: Mapped["Carts"] = relationship("Carts", back_populates="cart_items")
    product: Mapped["Products"] = relationship("Products", back_populates="cart_items")
    variant: Mapped[Optional["ProductVariants"]] = relationship(
        "ProductVariants", back_populates="cart_items"
    )


class OrderItems(Base):
    __tablename__ = "order_items"
    __table_args__ = (
        ForeignKeyConstraint(
            ["order_id"], ["orders.id"], ondelete="CASCADE", name="fk_order_items_order"
        ),
        ForeignKeyConstraint(
            ["product_id"], ["products.id"], ondelete="SET NULL", name="fk_order_items_product"
        ),
        ForeignKeyConstraint(
            ["variant_id"],
            ["product_variants.id"],
            ondelete="SET NULL",
            name="fk_order_items_variant",
        ),
        Index("fk_order_items_product", "product_id"),
        Index("fk_order_items_variant", "variant_id"),
        Index("idx_order_items_order_id", "order_id"),
    )

    id: Mapped[int] = mapped_column(
        INTEGER(10, unsigned=True), primary_key=True, autoincrement=True
    )
    order_id: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    product_name: Mapped[str] = mapped_column(String(100), nullable=False)
    unit_price: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    quantity: Mapped[int] = mapped_column(INTEGER(10, unsigned=True), nullable=False)
    line_total: Mapped[decimal.Decimal] = mapped_column(DECIMAL(10, 2), nullable=False)
    created_at: Mapped[datetime.datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("current_timestamp()")
    )
    product_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    variant_id: Mapped[int | None] = mapped_column(INTEGER(10, unsigned=True))
    color: Mapped[str | None] = mapped_column(String(50))
    size: Mapped[str | None] = mapped_column(String(50))

    order: Mapped["Orders"] = relationship("Orders", back_populates="order_items")
    product: Mapped[Optional["Products"]] = relationship("Products", back_populates="order_items")
    variant: Mapped[Optional["ProductVariants"]] = relationship(
        "ProductVariants", back_populates="order_items"
    )
