"""Allowlisted marketplace-listing queries over the reviewed schema."""

from __future__ import annotations

from datetime import date
from typing import Any, cast

from sqlalchemy import CursorResult, Table, func, or_, select, update
from sqlalchemy.orm import Session

from app.models.generated import AlumniChapter, MarketplaceListings, MarketplaceSocialMedia, Users

MARKETPLACE_TABLE: Table = cast(Table, MarketplaceListings.__table__)
MARKETPLACE_SOCIAL_TABLE: Table = cast(Table, MarketplaceSocialMedia.__table__)


class MarketplaceRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    @staticmethod
    def _conditions(filters: dict[str, Any], today: date) -> list[Any]:
        conditions: list[Any] = [
            MarketplaceListings.status == "active",
            or_(MarketplaceListings.expires_at.is_(None), MarketplaceListings.expires_at >= today),
        ]
        if filters.get("chapter_id") is not None:
            conditions.append(
                or_(
                    MarketplaceListings.chapter_id == filters["chapter_id"],
                    MarketplaceListings.chapter_id.is_(None),
                )
            )
        if filters.get("year") is not None:
            conditions.append(
                or_(MarketplaceListings.year == filters["year"], MarketplaceListings.year.is_(None))
            )
        for field in ("id", "user_id", "category"):
            if filters.get(field) is not None:
                conditions.append(getattr(MarketplaceListings, field) == filters[field])
        if filters.get("search"):
            escaped = (
                str(filters["search"]).replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_")
            )
            conditions.append(MarketplaceListings.title.ilike(f"%{escaped}%", escape="\\"))
        return conditions

    @staticmethod
    def _columns() -> list[Any]:
        return [
            MarketplaceListings.id,
            MarketplaceListings.user_id,
            MarketplaceListings.title,
            MarketplaceListings.business_name,
            MarketplaceListings.phone,
            MarketplaceListings.chapter_id,
            MarketplaceListings.year,
            MarketplaceListings.description,
            MarketplaceListings.category,
            MarketplaceListings.price,
            MarketplaceListings.price_type,
            MarketplaceListings.images,
            MarketplaceListings.contact_info,
            MarketplaceListings.whatsapp,
            MarketplaceListings.website,
            MarketplaceListings.location,
            MarketplaceListings.status,
            MarketplaceListings.is_featured,
            MarketplaceListings.expires_at,
            MarketplaceListings.created_at,
            Users.fullname.label("seller_name"),
            Users.avatar.label("seller_avatar"),
            AlumniChapter.chapter_name,
        ]

    def list_rows(
        self, filters: dict[str, Any], *, today: date, offset: int, limit: int
    ) -> list[dict[str, Any]]:
        rows = (
            self._session.execute(
                select(*self._columns())
                .select_from(MarketplaceListings)
                .outerjoin(Users, Users.id == MarketplaceListings.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == MarketplaceListings.chapter_id)
                .where(*self._conditions(filters, today))
                .order_by(
                    MarketplaceListings.is_featured.desc(),
                    MarketplaceListings.created_at.desc(),
                    MarketplaceListings.id.desc(),
                )
                .offset(offset)
                .limit(limit)
            )
            .mappings()
            .all()
        )
        return [dict(row) for row in rows]

    def count(self, filters: dict[str, Any], *, today: date) -> int:
        return int(
            self._session.scalar(
                select(func.count(MarketplaceListings.id)).where(*self._conditions(filters, today))
            )
            or 0
        )

    def socials(self, listing_ids: list[int]) -> dict[int, dict[str, Any]]:
        if not listing_ids:
            return {}
        rows = (
            self._session.execute(
                select(
                    MarketplaceSocialMedia.market_id,
                    MarketplaceSocialMedia.instagram_handle,
                    MarketplaceSocialMedia.instagram_url,
                    MarketplaceSocialMedia.instagram_hashtag,
                    MarketplaceSocialMedia.twitter_handle,
                    MarketplaceSocialMedia.twitter_url,
                    MarketplaceSocialMedia.linkedin_handle,
                    MarketplaceSocialMedia.linkedin_url,
                    MarketplaceSocialMedia.facebook_handle,
                    MarketplaceSocialMedia.facebook_url,
                    MarketplaceSocialMedia.tiktok_handle,
                    MarketplaceSocialMedia.tiktok_url,
                    MarketplaceSocialMedia.youtube_handle,
                    MarketplaceSocialMedia.youtube_url,
                ).where(MarketplaceSocialMedia.market_id.in_(listing_ids))
            )
            .mappings()
            .all()
        )
        return {int(row["market_id"]): dict(row) for row in rows}

    def single(self, listing_id: int, *, today: date) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(MarketplaceListings)
                .outerjoin(Users, Users.id == MarketplaceListings.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == MarketplaceListings.chapter_id)
                .where(
                    MarketplaceListings.id == listing_id,
                    MarketplaceListings.status == "active",
                    or_(
                        MarketplaceListings.expires_at.is_(None),
                        MarketplaceListings.expires_at >= today,
                    ),
                )
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def details(self, listing_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(*self._columns())
                .select_from(MarketplaceListings)
                .outerjoin(Users, Users.id == MarketplaceListings.user_id)
                .outerjoin(AlumniChapter, AlumniChapter.id == MarketplaceListings.chapter_id)
                .where(MarketplaceListings.id == listing_id)
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def increment_views(self, listing_id: int) -> None:
        self._session.execute(
            update(MarketplaceListings)
            .where(MarketplaceListings.id == listing_id)
            .values(views=MarketplaceListings.views + 1)
        )

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
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
        return dict(row) if row is not None else None

    def chapter_enabled(self, chapter_id: int) -> bool:
        return (
            self._session.scalar(
                select(AlumniChapter.id).where(
                    AlumniChapter.id == chapter_id, AlumniChapter.is_enabled == 1
                )
            )
            is not None
        )

    def lock_listing(self, listing_id: int) -> dict[str, Any] | None:
        row = (
            self._session.execute(
                select(
                    MarketplaceListings.id, MarketplaceListings.user_id, MarketplaceListings.images
                )
                .where(MarketplaceListings.id == listing_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    def create(self, values: dict[str, Any]) -> int:
        result: CursorResult[Any] = cast(
            CursorResult[Any], self._session.execute(MARKETPLACE_TABLE.insert().values(**values))
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Listing insert did not return a primary key")
        return int(key[0])

    def update_listing(self, listing_id: int, values: dict[str, Any]) -> None:
        self._session.execute(
            update(MarketplaceListings).where(MarketplaceListings.id == listing_id).values(**values)
        )

    def delete_listing(self, listing_id: int) -> None:
        self._session.execute(
            MARKETPLACE_TABLE.delete().where(MarketplaceListings.id == listing_id)
        )

    def upsert_socials(self, listing_id: int, values: dict[str, Any]) -> None:
        existing = self._session.scalar(
            select(MarketplaceSocialMedia.id).where(MarketplaceSocialMedia.market_id == listing_id)
        )
        if existing is None:
            self._session.execute(
                MARKETPLACE_SOCIAL_TABLE.insert().values(market_id=listing_id, **values)
            )
        else:
            self._session.execute(
                update(MarketplaceSocialMedia)
                .where(MarketplaceSocialMedia.market_id == listing_id)
                .values(**values)
            )
