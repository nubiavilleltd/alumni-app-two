"""Public vacancy feed and member-owned vacancy management."""

from __future__ import annotations

from datetime import UTC, datetime
from enum import Enum
from pathlib import Path
from typing import Any, cast
from urllib.parse import urlparse

from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.integrations.uploads import PreparedAvatar, StoredAvatar, VacancyStorage
from app.repositories.vacancies import VacancyRepository
from app.schemas.vacancies import (
    VacancyCreateRequest,
    VacancyDeleteRequest,
    VacancyDetailResponse,
    VacancyFilters,
    VacancyItem,
    VacancyListResponse,
    VacancyMutationResponse,
    VacancyUpdateRequest,
)


class VacancyError(Exception):
    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code, self.message, self.http_status = code, message, http_status


def _enum_text(value: object) -> str:
    return str(value.value if isinstance(value, Enum) else value)


class VacancyService:
    def __init__(self, session: Session, storage: VacancyStorage | None = None) -> None:
        self._session, self._repository, self._storage = (
            session,
            VacancyRepository(session),
            storage,
        )

    @staticmethod
    def _safe_flyer(value: str | None) -> str | None:
        if not value:
            return None
        parsed = urlparse(value)
        return value if value.startswith("uploads/") or parsed.scheme in {"http", "https"} else None

    @classmethod
    def _item(cls, row: dict[str, Any]) -> VacancyItem:
        payload = {key: value for key, value in row.items() if key in VacancyItem.model_fields}
        for field in ("job_type", "workplace_type", "level_of_expertise", "application_type"):
            payload[field] = _enum_text(row[field])
        payload["flyer"] = cls._safe_flyer(row.get("flyer"))
        return VacancyItem(**payload)

    @staticmethod
    def _stored(value: str | None) -> StoredAvatar | None:
        prefix = "uploads/vacancies/"
        if not value or not value.startswith(prefix):
            return None
        filename = Path(value).name
        return StoredAvatar(value, filename, "") if filename == value.removeprefix(prefix) else None

    def _delete_stored(self, value: str | None) -> None:
        stored = self._stored(value)
        if stored is not None and self._storage is not None:
            self._storage.delete(stored)

    @staticmethod
    def _actor(actor: dict[str, Any] | None, actor_id: int) -> AuthorizationFacts:
        if actor is None or not bool(actor.get("active")):
            raise VacancyError("vacancy_actor_unavailable", "Authentication required", 401)
        if actor.get("chapter_id") is None:
            raise VacancyError(
                "vacancy_chapter_required", "Your account must belong to an enabled chapter", 403
            )
        return AuthorizationFacts(
            actor_id, actor.get("user_role"), is_coordinator=bool(actor.get("is_coordinator"))
        )

    def _require_actor_chapter(self, actor: dict[str, Any]) -> int:
        chapter_id = int(actor["chapter_id"])
        if not self._repository.chapter_enabled(chapter_id):
            raise VacancyError(
                "vacancy_chapter_invalid", "Your chapter is not available for job posts", 403
            )
        return chapter_id

    def _response(self, vacancy_id: int) -> VacancyItem:
        row = self._repository.details(vacancy_id)
        if row is None:
            raise RuntimeError("Vacancy mutation did not return its vacancy")
        return self._item(row)

    @staticmethod
    def _destination_valid(
        application_type: str, email: object | None, link: object | None
    ) -> bool:
        return bool(email) if application_type == "email" else bool(link)

    def get(self, request: VacancyFilters) -> VacancyListResponse | VacancyDetailResponse:
        with self._session.begin():
            filters = request.model_dump()
            if request.id is not None:
                row = self._repository.details(request.id)
                if row is None:
                    raise VacancyError("vacancy_not_found", "Vacancy not found", 404)
                return VacancyDetailResponse(
                    status=200, message="Vacancy retrieved successfully", vacancy=self._item(row)
                )
            rows = [self._item(row) for row in self._repository.list_rows(filters)]
            total = self._repository.count(filters)
        return VacancyListResponse(
            status=200,
            message="Vacancies retrieved successfully",
            vacancies=rows,
            total=total,
            limit=request.limit,
            offset=request.offset,
        )

    def create(
        self, actor_id: int, request: VacancyCreateRequest, upload: PreparedAvatar | None
    ) -> VacancyMutationResponse:
        stored: StoredAvatar | None = None
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_id)
                self._actor(actor, actor_id)
                actor = cast(dict[str, Any], actor)
                chapter_id = self._require_actor_chapter(actor)
                if not request.valid_application_destination():
                    raise VacancyError(
                        "vacancy_application_destination_invalid",
                        "A valid application destination is required",
                        422,
                    )
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Vacancy storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                values = request.model_dump(exclude={"currency", "chapter_id"})
                vacancy_id = self._repository.create(
                    {
                        **values,
                        "user_id": actor_id,
                        "chapter_id": chapter_id,
                        "flyer": stored.relative_path if stored else None,
                        "created_at": datetime.now(UTC).replace(tzinfo=None),
                    }
                )
                vacancy = self._response(vacancy_id)
        except Exception:
            if stored is not None:
                self._delete_stored(stored.relative_path)
            raise
        return VacancyMutationResponse(
            status=200, message="Vacancy created successfully", vacancy=vacancy
        )

    def update(
        self, actor_id: int, request: VacancyUpdateRequest, upload: PreparedAvatar | None
    ) -> VacancyMutationResponse:
        stored: StoredAvatar | None = None
        stale: str | None = None
        try:
            with self._session.begin():
                actor = self._repository.lock_actor(actor_id)
                facts = self._actor(actor, actor_id)
                current = self._repository.lock_vacancy(request.id)
                if current is None:
                    raise VacancyError("vacancy_not_found", "Vacancy not found", 404)
                if int(current["user_id"]) != actor_id and not has_permission(
                    facts, Permission.MANAGE_CONTENT
                ):
                    raise VacancyError(
                        "vacancy_forbidden", "You can only manage your own job vacancies", 403
                    )
                changes = request.model_dump(
                    exclude={"function_type", "id", "remove_flyer", "currency", "chapter_id"},
                    exclude_unset=True,
                )
                application_type = str(
                    changes.get("application_type")
                    or self._item(self._repository.details(request.id) or {}).application_type
                )
                email = changes.get("application_email") if "application_email" in changes else None
                link = changes.get("application_link") if "application_link" in changes else None
                if "application_type" in changes:
                    changes["application_email"] = email if application_type == "email" else None
                    changes["application_link"] = link if application_type == "link" else None
                    if not self._destination_valid(application_type, email, link):
                        raise VacancyError(
                            "vacancy_application_destination_invalid",
                            "A valid application destination is required",
                            422,
                        )
                if upload is not None:
                    if self._storage is None:
                        raise RuntimeError("Vacancy storage is not configured")
                    stored = self._storage.save(actor_id, upload)
                    changes["flyer"] = stored.relative_path
                    stale = current.get("flyer")
                elif request.remove_flyer:
                    changes["flyer"] = None
                    stale = current.get("flyer")
                if not changes:
                    raise VacancyError("vacancy_update_empty", "No fields provided to update", 422)
                changes["updated_at"] = datetime.now(UTC).replace(tzinfo=None)
                self._repository.update(request.id, changes)
                vacancy = self._response(request.id)
        except Exception:
            if stored is not None:
                self._delete_stored(stored.relative_path)
            raise
        self._delete_stored(stale)
        return VacancyMutationResponse(
            status=200, message="Vacancy updated successfully", vacancy=vacancy
        )

    def delete(self, actor_id: int, request: VacancyDeleteRequest) -> VacancyMutationResponse:
        stale: str | None = None
        with self._session.begin():
            actor = self._repository.lock_actor(actor_id)
            facts = self._actor(actor, actor_id)
            current = self._repository.lock_vacancy(request.id)
            if current is None:
                raise VacancyError("vacancy_not_found", "Vacancy not found", 404)
            if int(current["user_id"]) != actor_id and not has_permission(
                facts, Permission.MANAGE_CONTENT
            ):
                raise VacancyError(
                    "vacancy_forbidden", "You can only manage your own job vacancies", 403
                )
            stale = current.get("flyer")
            self._repository.delete(request.id)
        self._delete_stored(stale)
        return VacancyMutationResponse(status=200, message="Vacancy deleted successfully")
