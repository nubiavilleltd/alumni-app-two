"""Public project presentation and current-policy content administration."""

from __future__ import annotations

import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import PreparedAvatar, ProjectStorage, StoredAvatar
from app.repositories.projects import ProjectRepository
from app.schemas.projects import (
    ProjectCreateRequest,
    ProjectDeleteRequest,
    ProjectDetailResponse,
    ProjectFilters,
    ProjectItem,
    ProjectListResponse,
    ProjectMutationResponse,
    ProjectUpdateRequest,
)


class ProjectError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class ProjectService:
    def __init__(self, session: Session, storage: ProjectStorage | None = None) -> None:
        self._session = session
        self._repository = ProjectRepository(session)
        self._storage = storage

    @staticmethod
    def _status(value: Any) -> str:
        return str(getattr(value, "value", value) or "active")

    @staticmethod
    def _images(value: str | None) -> list[str]:
        try:
            decoded = json.loads(value) if value else []
        except json.JSONDecodeError:
            return []
        return (
            [item for item in decoded if isinstance(item, str)] if isinstance(decoded, list) else []
        )

    @classmethod
    def _project(cls, row: dict[str, Any]) -> ProjectItem:
        images = []
        for value in cls._images(row.get("images")):
            parsed = urlparse(value)
            if value.startswith("uploads/") or parsed.scheme in {"http", "https"}:
                images.append(value)
        payload = {key: value for key, value in row.items() if key in ProjectItem.model_fields}
        payload.update(
            status=cls._status(row.get("status")),
            images=images,
            is_featured=bool(row.get("is_featured")),
        )
        return ProjectItem(**payload)

    @staticmethod
    def _stored_from_path(value: str) -> StoredAvatar | None:
        prefix = "uploads/projects/"
        if not value.startswith(prefix):
            return None
        filename = Path(value).name
        if not filename or filename != value.removeprefix(prefix):
            return None
        return StoredAvatar(relative_path=value, filename=filename, original_filename="")

    def _delete_stored(self, paths: list[str]) -> None:
        if self._storage is None:
            return
        for path in paths:
            stored = self._stored_from_path(path)
            if stored is not None:
                self._storage.delete(stored)

    def _save_images(self, actor_user_id: int, uploads: list[PreparedAvatar]) -> list[StoredAvatar]:
        if not uploads:
            return []
        if self._storage is None:
            raise RuntimeError("Project storage is not configured")
        return [self._storage.save(actor_user_id, upload) for upload in uploads]

    @staticmethod
    def _content_actor(actor: dict[str, Any] | None, actor_user_id: int) -> dict[str, Any]:
        if actor is None or not bool(actor.get("active")):
            raise ProjectError("project_actor_unavailable", "Authentication required", 401)
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, Permission.MANAGE_CONTENT):
            raise ProjectError("project_forbidden", "You cannot manage projects", 403)
        return actor

    def _require_enabled_chapter(self, chapter_id: int | None) -> None:
        if chapter_id is not None and not self._repository.chapter_enabled(chapter_id):
            raise ProjectError("project_chapter_invalid", "Chapter not found or not enabled", 400)

    def _response(self, project_id: int) -> ProjectItem:
        row = self._repository.project(project_id)
        if row is None:
            raise RuntimeError("Project mutation did not return its project")
        return self._project(row)

    def get(self, request: ProjectFilters) -> ProjectListResponse | ProjectDetailResponse:
        with self._session.begin():
            if request.id is not None:
                row = self._repository.public_project(request.id)
                if row is None:
                    raise ProjectError("project_not_found", "Project not found", 404)
                return ProjectDetailResponse(
                    status=200, message="Project retrieved successfully", project=self._project(row)
                )
            filters = request.model_dump(exclude={"id"})
            rows = self._repository.list_rows(filters)
            total = self._repository.count(filters)
        return ProjectListResponse(
            status=200,
            message="Projects retrieved successfully",
            projects=[self._project(row) for row in rows],
            total=total,
            limit=request.limit,
            offset=request.offset,
        )

    def create(
        self, actor_user_id: int, request: ProjectCreateRequest, uploads: list[PreparedAvatar]
    ) -> ProjectMutationResponse:
        stored: list[StoredAvatar] = []
        try:
            with self._session.begin():
                actor = self._content_actor(
                    self._repository.lock_actor(actor_user_id), actor_user_id
                )
                if len(uploads) > 6:
                    raise ProjectError(
                        "project_images_invalid", "A project may contain at most 6 images", 400
                    )
                chapter_id = request.chapter_id
                if chapter_id is None:
                    chapter_id = int(actor["chapter_id"])
                self._require_enabled_chapter(chapter_id)
                stored = self._save_images(actor_user_id, uploads)
                project_id = self._repository.create(
                    {
                        **request.model_dump(),
                        "chapter_id": chapter_id,
                        "created_by": actor_user_id,
                        "images": json.dumps([item.relative_path for item in stored]),
                        "is_deleted": 0,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                project = self._response(project_id)
        except Exception:
            self._delete_stored([item.relative_path for item in stored])
            raise
        return ProjectMutationResponse(
            status=200,
            message="Project created successfully",
            project=project,
            image_count=len(stored),
        )

    def update(
        self, actor_user_id: int, request: ProjectUpdateRequest, uploads: list[PreparedAvatar]
    ) -> ProjectMutationResponse:
        stored: list[StoredAvatar] = []
        stale: list[str] = []
        try:
            with self._session.begin():
                self._content_actor(self._repository.lock_actor(actor_user_id), actor_user_id)
                current = self._repository.lock_project(request.id)
                if current is None or bool(current["is_deleted"]):
                    raise ProjectError("project_not_found", "Project not found", 404)
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
                    raise ProjectError(
                        "project_images_invalid", "A project may contain at most 6 images", 400
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
                if not changes:
                    raise ProjectError("project_update_empty", "No fields provided to update", 422)
                changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                self._repository.update_project(request.id, changes)
                project = self._response(request.id)
        except Exception:
            self._delete_stored([item.relative_path for item in stored])
            raise
        self._delete_stored(stale)
        return ProjectMutationResponse(
            status=200,
            message="Project updated successfully",
            project=project,
            image_count=len(project.images),
        )

    def delete(self, actor_user_id: int, request: ProjectDeleteRequest) -> ProjectMutationResponse:
        stale: list[str] = []
        with self._session.begin():
            self._content_actor(self._repository.lock_actor(actor_user_id), actor_user_id)
            current = self._repository.lock_project(request.id)
            if current is None or bool(current["is_deleted"]):
                raise ProjectError("project_not_found", "Project not found", 404)
            stale = self._images(current.get("images"))
            self._repository.update_project(
                request.id,
                {"is_deleted": 1, "updated_at": datetime.now(UTC).replace(tzinfo=None)},
            )
        self._delete_stored(stale)
        return ProjectMutationResponse(status=200, message="Project deleted successfully")
