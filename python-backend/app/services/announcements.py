"""Current-policy announcement publishing and public-feed use cases."""

from __future__ import annotations

from datetime import UTC, datetime
from enum import Enum
from pathlib import Path
from typing import Any

import structlog
from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import AnnouncementStorage, PreparedAvatar, StoredAvatar
from app.repositories.announcements import AnnouncementRepository
from app.schemas.announcements import (
    AnnouncementCreateRequest,
    AnnouncementFilters,
    AnnouncementItem,
    AnnouncementListResponse,
    AnnouncementMutationResponse,
    AnnouncementUpdateRequest,
)

logger = structlog.get_logger(__name__)


def _enum_text(value: object, default: str) -> str:
    """Return the wire value when SQLAlchemy supplies a Python enum instance."""
    raw_value = value.value if isinstance(value, Enum) else value
    return str(raw_value or default)


class AnnouncementError(Exception):
    """A bounded announcement operation cannot proceed."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class AnnouncementService:
    """Apply current database permission checks instead of legacy JWT role claims."""

    def __init__(self, session: Session, storage: AnnouncementStorage | None = None) -> None:
        self._session = session
        self._repository = AnnouncementRepository(session)
        self._storage = storage

    @staticmethod
    def _active_content_actor(actor: dict[str, Any] | None, actor_user_id: int) -> None:
        if actor is None or not bool(actor.get("active")):
            raise AnnouncementError(
                "announcement_actor_unavailable", "Authentication required", 401
            )
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, Permission.MANAGE_CONTENT):
            raise AnnouncementError(
                "announcement_forbidden", "You cannot manage announcements", 403
            )

    @staticmethod
    def _item(row: dict[str, Any]) -> AnnouncementItem:
        return AnnouncementItem(
            id=int(row["id"]),
            title=str(row["title"]),
            content=str(row["content"]),
            images=str(row["imag"]) if row.get("imag") else None,
            type=_enum_text(row.get("type"), "info"),
            created_by=int(row["created_by"]),
            created_by_name=(str(row["created_by_name"]) if row.get("created_by_name") else None),
            chapter_id=int(row["chapter_id"]) if row.get("chapter_id") is not None else None,
            year=str(row["year"]) if row.get("year") else None,
            starts_at=row.get("starts_at"),
            ends_at=row.get("ends_at"),
            created_at=row.get("created_at"),
            updated_at=row.get("updated_at"),
        )

    @staticmethod
    def _stored_from_path(relative_path: str) -> StoredAvatar | None:
        prefix = "uploads/announcements/"
        if not relative_path.startswith(prefix):
            return None
        filename = Path(relative_path).name
        if filename != relative_path.removeprefix(prefix) or not filename:
            return None
        return StoredAvatar(relative_path=relative_path, filename=filename, original_filename="")

    def _require_chapter(self, chapter_id: int | None) -> None:
        if chapter_id is not None and not self._repository.chapter_exists(chapter_id):
            raise AnnouncementError("announcement_chapter_invalid", "Chapter not found", 400)

    def list_announcements(self, request: AnnouncementFilters) -> AnnouncementListResponse:
        filters = request.model_dump(exclude={"page", "limit"}, exclude_none=True)
        offset = (request.page - 1) * request.limit
        with self._session.begin():
            rows = self._repository.list_rows(filters, offset=offset, limit=request.limit)
            total = self._repository.count(filters)
        items = [self._item(row) for row in rows]
        return AnnouncementListResponse(
            status=200,
            message="Announcements retrieved",
            data=items,
            total=total,
            page=request.page,
            limit=request.limit,
            has_more=offset + len(items) < total,
        )

    def create(
        self,
        actor_user_id: int,
        request: AnnouncementCreateRequest,
        image: PreparedAvatar | None,
    ) -> AnnouncementMutationResponse:
        stored: StoredAvatar | None = None
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_user_id)
                self._active_content_actor(actor, actor_user_id)
                self._require_chapter(request.chapter_id)
                if image is not None:
                    if self._storage is None:
                        raise RuntimeError("Announcement storage is not configured")
                    stored = self._storage.save(actor_user_id, image)
                announcement_id = self._repository.create(
                    {
                        **request.model_dump(),
                        "created_by": actor_user_id,
                        "imag": stored.relative_path if stored else None,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                row = self._repository.announcement(announcement_id)
                if row is None:
                    raise RuntimeError("Announcement creation did not return the new row")
        except Exception:
            if stored is not None and self._storage is not None:
                self._storage.delete(stored)
            raise
        logger.info(
            "announcement_created", actor_user_id=actor_user_id, announcement_id=announcement_id
        )
        return AnnouncementMutationResponse(
            status=200, message="Announcement created", data=self._item(row)
        )

    def update(
        self,
        actor_user_id: int,
        request: AnnouncementUpdateRequest,
        image: PreparedAvatar | None,
    ) -> AnnouncementMutationResponse:
        stored: StoredAvatar | None = None
        old_image: StoredAvatar | None = None
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_user_id)
                self._active_content_actor(actor, actor_user_id)
                current = self._repository.announcement(request.id, lock=True)
                if current is None:
                    raise AnnouncementError("announcement_not_found", "Announcement not found", 404)
                changes = request.model_dump(
                    exclude={
                        "function_type",
                        "id",
                        "clear_chapter_id",
                        "clear_year",
                        "clear_starts_at",
                        "clear_ends_at",
                    },
                    exclude_unset=True,
                )
                for field in ("chapter_id", "year", "starts_at", "ends_at"):
                    if field not in request.model_fields_set:
                        changes.pop(field, None)
                if request.clear_chapter_id:
                    changes["chapter_id"] = None
                if request.clear_year:
                    changes["year"] = None
                if request.clear_starts_at:
                    changes["starts_at"] = None
                if request.clear_ends_at:
                    changes["ends_at"] = None
                if changes.get("chapter_id") is not None:
                    self._require_chapter(int(changes["chapter_id"]))
                if not changes and image is None:
                    raise AnnouncementError(
                        "announcement_update_empty",
                        "At least one field or image must be provided",
                        422,
                    )
                if image is not None:
                    if self._storage is None:
                        raise RuntimeError("Announcement storage is not configured")
                    stored = self._storage.save(actor_user_id, image)
                    changes["imag"] = stored.relative_path
                    old_image = self._stored_from_path(str(current.get("imag") or ""))
                changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                self._repository.update(request.id, changes)
                row = self._repository.announcement(request.id)
                if row is None:
                    raise RuntimeError("Announcement update did not return the target row")
        except Exception:
            if stored is not None and self._storage is not None:
                self._storage.delete(stored)
            raise
        if old_image is not None and self._storage is not None:
            self._storage.delete(old_image)
        logger.info("announcement_updated", actor_user_id=actor_user_id, announcement_id=request.id)
        return AnnouncementMutationResponse(
            status=200, message="Announcement updated", data=self._item(row)
        )

    def delete(self, actor_user_id: int, announcement_id: int) -> AnnouncementMutationResponse:
        old_image: StoredAvatar | None = None
        with self._session.begin():
            actor = self._repository.lock_actor(actor_user_id)
            self._active_content_actor(actor, actor_user_id)
            current = self._repository.announcement(announcement_id, lock=True)
            if current is None:
                raise AnnouncementError("announcement_not_found", "Announcement not found", 404)
            old_image = self._stored_from_path(str(current.get("imag") or ""))
            self._repository.delete(announcement_id)
        if old_image is not None and self._storage is not None:
            self._storage.delete(old_image)
        logger.info(
            "announcement_deleted", actor_user_id=actor_user_id, announcement_id=announcement_id
        )
        return AnnouncementMutationResponse(status=200, message="Announcement deleted")
