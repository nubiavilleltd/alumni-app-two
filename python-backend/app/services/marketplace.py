"""Bounded public marketplace listing read use cases."""

from __future__ import annotations

import json
from datetime import UTC, date, datetime, timedelta
from enum import Enum
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import MarketplaceStorage, PreparedAvatar, StoredAvatar
from app.repositories.marketplace import MarketplaceRepository
from app.schemas.marketplace import (
    MarketplaceCreateRequest,
    MarketplaceDeleteRequest,
    MarketplaceFilters,
    MarketplaceItemResponse,
    MarketplaceListing,
    MarketplaceListResponse,
    MarketplaceMutationResponse,
    MarketplaceSocial,
    MarketplaceUpdateRequest,
)


def _enum_text(value: object, default: str) -> str:
    """Return the wire value when SQLAlchemy supplies a Python enum instance."""
    raw_value = value.value if isinstance(value, Enum) else value
    return str(raw_value or default)


class MarketplaceError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class MarketplaceService:
    def __init__(self, session: Session, storage: MarketplaceStorage | None = None) -> None:
        self._session = session
        self._repository = MarketplaceRepository(session)
        self._storage = storage

    @staticmethod
    def _listing(
        row: dict[str, Any], social: dict[str, Any] | None, today: date
    ) -> MarketplaceListing:
        raw_images = row.get("images")
        try:
            images = json.loads(raw_images) if isinstance(raw_images, str) else []
        except json.JSONDecodeError:
            images = []
        safe_images = []
        for value in images:
            if not isinstance(value, str):
                continue
            parsed = urlparse(value)
            if value.startswith("uploads/") or parsed.scheme in {"http", "https"}:
                safe_images.append(value)
        payload = {
            key: value for key, value in row.items() if key in MarketplaceListing.model_fields
        }
        payload.update(
            category=_enum_text(row.get("category"), "other"),
            price_type=_enum_text(row.get("price_type"), "fixed"),
            status=_enum_text(row.get("status"), "active"),
            images=safe_images,
            is_featured=bool(row.get("is_featured")),
            is_expired=bool(row.get("expires_at") and row["expires_at"] < today),
            social_media=MarketplaceSocial.model_validate(social) if social else None,
        )
        return MarketplaceListing(**payload)

    @staticmethod
    def _stored_from_path(value: str) -> StoredAvatar | None:
        prefix = "uploads/marketplace/"
        if not value.startswith(prefix):
            return None
        filename = Path(value).name
        if not filename or filename != value.removeprefix(prefix):
            return None
        return StoredAvatar(relative_path=value, filename=filename, original_filename="")

    @staticmethod
    def _images(value: str | None) -> list[str]:
        try:
            decoded = json.loads(value) if value else []
        except json.JSONDecodeError:
            return []
        return (
            [item for item in decoded if isinstance(item, str)] if isinstance(decoded, list) else []
        )

    @staticmethod
    def _marketplace_actor(actor: dict[str, Any] | None, actor_user_id: int) -> AuthorizationFacts:
        if actor is None or not bool(actor.get("active")):
            raise MarketplaceError("marketplace_actor_unavailable", "Authentication required", 401)
        return AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )

    @staticmethod
    def _social_values(payload: dict[str, Any]) -> dict[str, str | None]:
        aliases = {
            "social_instagram": "instagram_url",
            "social_instagram_hashtag": "instagram_hashtag",
            "social_facebook": "facebook_url",
            "social_linkedin": "linkedin_url",
            "social_x": "twitter_url",
            "social_tiktok": "tiktok_url",
        }
        return {
            destination: (str(payload[source]).strip() or None)
            for source, destination in aliases.items()
            if source in payload
        }

    def _require_enabled_chapter(self, chapter_id: int | None) -> None:
        if chapter_id is not None and not self._repository.chapter_enabled(chapter_id):
            raise MarketplaceError(
                "marketplace_chapter_invalid", "Chapter not found or not enabled", 400
            )

    def _response(self, listing_id: int, today: date) -> MarketplaceListing:
        row = self._repository.details(listing_id)
        if row is None:
            raise RuntimeError("Marketplace mutation did not return its listing")
        return self._listing(row, self._repository.socials([listing_id]).get(listing_id), today)

    def _save_images(self, actor_user_id: int, uploads: list[PreparedAvatar]) -> list[StoredAvatar]:
        if not uploads:
            return []
        if self._storage is None:
            raise RuntimeError("Marketplace storage is not configured")
        return [self._storage.save(actor_user_id, upload) for upload in uploads]

    def _delete_stored(self, paths: list[str]) -> None:
        if self._storage is None:
            return
        for path in paths:
            stored = self._stored_from_path(path)
            if stored is not None:
                self._storage.delete(stored)

    def get(self, filters: MarketplaceFilters) -> MarketplaceListResponse | MarketplaceItemResponse:
        today = datetime.now(UTC).date()
        with self._session.begin():
            if filters.id is not None:
                row = self._repository.single(filters.id, today=today)
                if row is None:
                    raise MarketplaceError("listing_not_found", "Listing not found", 404)
                social = self._repository.socials([filters.id]).get(filters.id)
                self._repository.increment_views(filters.id)
                return MarketplaceItemResponse(
                    status=200,
                    message="Listing retrieved",
                    listing=self._listing(row, social, today),
                )
            offset = (filters.page - 1) * filters.limit
            rows = self._repository.list_rows(
                filters.model_dump(exclude={"page", "limit", "id"}, exclude_none=True),
                today=today,
                offset=offset,
                limit=filters.limit,
            )
            total = self._repository.count(
                filters.model_dump(exclude={"page", "limit", "id"}, exclude_none=True), today=today
            )
            socials = self._repository.socials([int(row["id"]) for row in rows])
        listings = [self._listing(row, socials.get(int(row["id"])), today) for row in rows]
        return MarketplaceListResponse(
            status=200,
            message="Listings retrieved",
            listings=listings,
            total=total,
            page=filters.page,
            limit=filters.limit,
            has_more=offset + len(listings) < total,
        )

    def create(
        self,
        actor_user_id: int,
        request: MarketplaceCreateRequest,
        uploads: list[PreparedAvatar],
        social_input: dict[str, Any],
    ) -> MarketplaceMutationResponse:
        stored: list[StoredAvatar] = []
        today = datetime.now(UTC).date()
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_user_id)
                self._marketplace_actor(actor, actor_user_id)
                self._require_enabled_chapter(request.chapter_id)
                if len(uploads) > 6:
                    raise MarketplaceError(
                        "marketplace_images_invalid", "A listing may contain at most 6 images", 400
                    )
                stored = self._save_images(actor_user_id, uploads)
                listing_id = self._repository.create(
                    {
                        **request.model_dump(),
                        "user_id": actor_user_id,
                        "images": json.dumps([item.relative_path for item in stored]),
                        "status": "active",
                        "is_featured": 0,
                        "expires_at": request.expires_at or today + timedelta(days=30),
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                social = self._social_values(social_input)
                if social:
                    self._repository.upsert_socials(listing_id, social)
                listing = self._response(listing_id, today)
        except Exception:
            self._delete_stored([item.relative_path for item in stored])
            raise
        return MarketplaceMutationResponse(
            status=200,
            message="Listing created successfully",
            listing=listing,
            image_count=len(stored),
        )

    def update(
        self,
        actor_user_id: int,
        request: MarketplaceUpdateRequest,
        uploads: list[PreparedAvatar],
        social_input: dict[str, Any],
    ) -> MarketplaceMutationResponse:
        stored: list[StoredAvatar] = []
        stale: list[str] = []
        today = datetime.now(UTC).date()
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_user_id)
                facts = self._marketplace_actor(actor, actor_user_id)
                current = self._repository.lock_listing(request.id)
                if current is None:
                    raise MarketplaceError("listing_not_found", "Listing not found", 404)
                if int(current["user_id"]) != actor_user_id and not has_permission(
                    facts, Permission.MANAGE_STORE
                ):
                    raise MarketplaceError(
                        "marketplace_forbidden", "You can only edit your own listings", 403
                    )
                changes = request.model_dump(
                    exclude={"function_type", "id", "image_action", "remove_images"},
                    exclude_unset=True,
                )
                if changes.get("chapter_id") is not None:
                    self._require_enabled_chapter(int(changes["chapter_id"]))
                existing = self._images(current.get("images"))
                removed = set(request.remove_images)
                retained = [image for image in existing if image not in removed]
                if len(uploads) + (0 if request.image_action == "replace" else len(retained)) > 6:
                    raise MarketplaceError(
                        "marketplace_images_invalid", "A listing may contain at most 6 images", 400
                    )
                stored = self._save_images(actor_user_id, uploads)
                new_paths = [item.relative_path for item in stored]
                if request.image_action == "replace":
                    final_images = new_paths
                    stale = existing
                else:
                    final_images = retained + new_paths
                    stale = [image for image in existing if image not in retained]
                if uploads or removed or request.image_action == "replace":
                    changes["images"] = json.dumps(final_images) if final_images else None
                social = self._social_values(social_input)
                if not changes and not social:
                    raise MarketplaceError(
                        "marketplace_update_empty", "No fields provided to update", 422
                    )
                if changes:
                    changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                    self._repository.update_listing(request.id, changes)
                if social:
                    self._repository.upsert_socials(request.id, social)
                listing = self._response(request.id, today)
        except Exception:
            self._delete_stored([item.relative_path for item in stored])
            raise
        self._delete_stored(stale)
        return MarketplaceMutationResponse(
            status=200,
            message="Listing updated successfully",
            listing=listing,
            image_count=len(listing.images),
        )

    def delete(
        self, actor_user_id: int, request: MarketplaceDeleteRequest
    ) -> MarketplaceMutationResponse:
        stale: list[str] = []
        with self._session.begin():
            actor = self._repository.lock_actor(actor_user_id)
            facts = self._marketplace_actor(actor, actor_user_id)
            current = self._repository.lock_listing(request.id)
            if current is None:
                raise MarketplaceError("listing_not_found", "Listing not found", 404)
            if int(current["user_id"]) != actor_user_id and not has_permission(
                facts, Permission.MANAGE_STORE
            ):
                raise MarketplaceError(
                    "marketplace_forbidden", "You can only delete your own listings", 403
                )
            stale = self._images(current.get("images"))
            self._repository.delete_listing(request.id)
        self._delete_stored(stale)
        return MarketplaceMutationResponse(status=200, message="Listing deleted successfully")
