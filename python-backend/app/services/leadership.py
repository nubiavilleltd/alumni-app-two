"""Public leadership presentation and current-policy administration."""

from __future__ import annotations

from datetime import UTC, datetime
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import LeadershipStorage, PreparedAvatar, StoredAvatar
from app.repositories.leadership import LeadershipRepository
from app.schemas.leadership import (
    LeaderItem,
    LeadershipCreateRequest,
    LeadershipDeleteRequest,
    LeadershipDetailResponse,
    LeadershipFilters,
    LeadershipListResponse,
    LeadershipMutationResponse,
    LeadershipReorderRequest,
    LeadershipUpdateRequest,
)


class LeadershipError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code, self.message, self.http_status = code, message, http_status


class LeadershipService:
    def __init__(self, session: Session, storage: LeadershipStorage | None = None) -> None:
        self._session, self._repository, self._storage = (
            session,
            LeadershipRepository(session),
            storage,
        )

    @staticmethod
    def _safe_photo(value: str | None) -> str | None:
        if not value:
            return None
        parsed = urlparse(value)
        return value if value.startswith("uploads/") or parsed.scheme in {"http", "https"} else None

    @classmethod
    def _item(cls, row: dict[str, Any]) -> LeaderItem:
        payload = {key: value for key, value in row.items() if key in LeaderItem.model_fields}
        payload.update(
            photo=cls._safe_photo(row.get("leadership_photo"))
            or cls._safe_photo(row.get("user_avatar")),
            is_featured=bool(row.get("is_featured")),
            is_active=bool(row.get("is_active")),
        )
        return LeaderItem(**payload)

    @staticmethod
    def _stored(value: str | None) -> StoredAvatar | None:
        prefix = "uploads/leadership/"
        if not value or not value.startswith(prefix):
            return None
        filename = Path(value).name
        return StoredAvatar(value, filename, "") if filename == value.removeprefix(prefix) else None

    def _delete(self, value: str | None) -> None:
        stored = self._stored(value)
        if stored is not None and self._storage is not None:
            self._storage.delete(stored)

    def _actor(self, actor: dict[str, Any] | None, actor_id: int) -> None:
        if actor is None or not bool(actor.get("active")):
            raise LeadershipError("leadership_actor_unavailable", "Authentication required", 401)
        facts = AuthorizationFacts(
            actor_id, actor.get("user_role"), is_coordinator=bool(actor.get("is_coordinator"))
        )
        if not has_permission(facts, Permission.MANAGE_CONTENT):
            raise LeadershipError(
                "leadership_forbidden", "You cannot manage leadership records", 403
            )

    def _validate_refs(self, user_id: int, chapter_id: int | None) -> None:
        if not self._repository.user_exists(user_id):
            raise LeadershipError("leadership_user_not_found", "User not found", 404)
        if chapter_id is not None and not self._repository.chapter_enabled(chapter_id):
            raise LeadershipError(
                "leadership_chapter_invalid", "Chapter not found or not enabled", 400
            )

    def _response(self, leader_id: int) -> LeaderItem:
        row = self._repository.leader(leader_id)
        if row is None:
            raise RuntimeError("Leadership mutation did not return its leader")
        return self._item(row)

    def get(self, request: LeadershipFilters) -> LeadershipListResponse | LeadershipDetailResponse:
        with self._session.begin():
            if request.id is not None or request.user_id is not None:
                row = self._repository.public_leader(request.id, request.user_id)
                if row is None:
                    raise LeadershipError("leader_not_found", "Leader not found", 404)
                return LeadershipDetailResponse(
                    status=200, message="Leader retrieved successfully", leader=self._item(row)
                )
            rows = [self._item(row) for row in self._repository.list_rows(request.model_dump())]
        return LeadershipListResponse(
            status=200,
            message="Leadership retrieved successfully",
            total=len(rows),
            featured=[row for row in rows if row.is_featured],
            team=[row for row in rows if not row.is_featured],
            all=rows,
        )

    def create(
        self, actor_id: int, request: LeadershipCreateRequest, upload: PreparedAvatar | None
    ) -> LeadershipMutationResponse:
        stored: StoredAvatar | None = None
        try:
            with self._session.begin():
                self._actor(self._repository.lock_actor(actor_id), actor_id)
                self._validate_refs(request.user_id, request.chapter_id)
                if self._repository.active_duplicate(request.user_id, request.year):
                    raise LeadershipError(
                        "leader_duplicate",
                        "This user already has a leadership position for this year",
                        409,
                    )
                if request.is_featured:
                    self._repository.clear_featured(request.year)
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Leadership storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                leader_id = self._repository.create(
                    {
                        **request.model_dump(),
                        "leadership_photo": stored.relative_path if stored else None,
                        "created_by": actor_id,
                        "is_deleted": 0,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                leader = self._response(leader_id)
        except Exception:
            if stored is not None:
                self._delete(stored.relative_path)
            raise
        return LeadershipMutationResponse(
            status=200, message="Leader created successfully", leader=leader
        )

    def update(
        self, actor_id: int, request: LeadershipUpdateRequest, upload: PreparedAvatar | None
    ) -> LeadershipMutationResponse:
        stored: StoredAvatar | None = None
        stale: str | None = None
        try:
            with self._session.begin():
                self._actor(self._repository.lock_actor(actor_id), actor_id)
                current = self._repository.lock_leader(request.id)
                if current is None or bool(current["is_deleted"]):
                    raise LeadershipError("leader_not_found", "Leader not found", 404)
                changes = request.model_dump(
                    exclude={"function_type", "id", "remove_photo"}, exclude_unset=True
                )
                target_user = int(changes.get("user_id", current["user_id"]))
                year = changes.get("year", current.get("year"))
                self._validate_refs(target_user, changes.get("chapter_id"))
                if (
                    target_user != int(current["user_id"]) or year != current.get("year")
                ) and self._repository.active_duplicate(target_user, year, except_id=request.id):
                    raise LeadershipError(
                        "leader_duplicate",
                        "This user already has a leadership position for this year",
                        409,
                    )
                if changes.get("is_featured") is True:
                    self._repository.clear_featured(year, except_id=request.id)
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Leadership storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                    changes["leadership_photo"] = stored.relative_path
                    stale = current.get("leadership_photo")
                elif request.remove_photo:
                    changes["leadership_photo"] = None
                    stale = current.get("leadership_photo")
                if not changes:
                    raise LeadershipError(
                        "leader_update_empty", "No fields provided to update", 422
                    )
                changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                self._repository.update(request.id, changes)
                leader = self._response(request.id)
        except Exception:
            if stored is not None:
                self._delete(stored.relative_path)
            raise
        self._delete(stale)
        return LeadershipMutationResponse(
            status=200, message="Leader updated successfully", leader=leader
        )

    def delete(self, actor_id: int, request: LeadershipDeleteRequest) -> LeadershipMutationResponse:
        stale: str | None = None
        with self._session.begin():
            self._actor(self._repository.lock_actor(actor_id), actor_id)
            current = self._repository.lock_leader(request.id)
            if current is None or bool(current["is_deleted"]):
                raise LeadershipError("leader_not_found", "Leader not found", 404)
            stale = current.get("leadership_photo")
            self._repository.update(
                request.id, {"is_deleted": 1, "updated_at": datetime.now(UTC).replace(tzinfo=None)}
            )
        self._delete(stale)
        return LeadershipMutationResponse(status=200, message="Leader removed successfully")

    def reorder(
        self, actor_id: int, request: LeadershipReorderRequest
    ) -> LeadershipMutationResponse:
        with self._session.begin():
            self._actor(self._repository.lock_actor(actor_id), actor_id)
            if len({item.id for item in request.order}) != len(request.order):
                raise LeadershipError(
                    "leader_reorder_invalid", "Each leader may appear only once", 400
                )
            for item in request.order:
                current = self._repository.lock_leader(item.id)
                if current is None or bool(current["is_deleted"]):
                    raise LeadershipError("leader_not_found", "Leader not found", 404)
            now = datetime.now(UTC).replace(tzinfo=None)
            for item in request.order:
                self._repository.update(item.id, {"sort_order": item.sort_order, "updated_at": now})
        return LeadershipMutationResponse(
            status=200, message="Leadership reordered successfully", updated=len(request.order)
        )
