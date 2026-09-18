"""Service layer for Homepage, Carousel, FAQs, Categories, and Blog Posts."""

from __future__ import annotations

import math
import re
from typing import Any
from urllib.parse import urljoin

import structlog
from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.core.config import Settings
from app.integrations.uploads import (
    AvatarUploadError,
    BlogGalleryStorage,
    CarouselStorage,
    StoredAvatar,
    prepare_avatar,
)
from app.models.generated import (
    BlogCategories,
    Faqs,
    HomepageCarousel,
    HomepageCarouselIsHidden,
    HomepageCarouselShowGreeting,
)
from app.repositories.blog import BlogRepository
from app.schemas.blog import (
    BlogCategoryItem,
    BlogGalleryImageItem,
    BlogPagination,
    BlogPostDetailItem,
    BlogPostsFilters,
    BlogPostSummaryItem,
    BlogSectionItem,
    FaqItem,
    HomepageCarouselImageItem,
    HomepageData,
)

logger = structlog.get_logger(__name__)


class BlogError(Exception):
    """An operation in the blog/content family failed."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class BlogService:
    """Business logic for all blog, FAQ, and homepage features."""

    def __init__(
        self,
        session: Session,
        settings: Settings,
        carousel_storage: CarouselStorage | None = None,
        gallery_storage: BlogGalleryStorage | None = None,
    ) -> None:
        self._session = session
        self._settings = settings
        self._repository = BlogRepository(session)
        self._carousel_storage = carousel_storage or CarouselStorage(settings.upload_root)
        self._gallery_storage = gallery_storage or BlogGalleryStorage(settings.upload_root)

    def _require_manage_content(self, actor_user_id: int) -> None:
        """Enforce that the authenticated user possesses current MANAGE_CONTENT permission."""
        actor = self._repository.lock_actor(actor_user_id)
        if actor is None or not bool(actor.get("active")):
            raise BlogError("unauthorized", "Admin authentication required", 401)
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, Permission.MANAGE_CONTENT):
            raise BlogError("forbidden", "Admin access required", 403)

    def is_admin_actor(self, actor_user_id: int | None) -> bool:
        """Check if an optional actor has current MANAGE_CONTENT permission."""
        if actor_user_id is None:
            return False
        actor = self._repository.lock_actor(actor_user_id)
        if actor is None or not bool(actor.get("active")):
            return False
        facts = AuthorizationFacts(
            user_id=actor_user_id,
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        return has_permission(facts, Permission.MANAGE_CONTENT)

    def _public_url(self, stored_path: str | None) -> str | None:
        """Expand stored relative path to a full public URL."""
        if not stored_path:
            return None
        cleaned = stored_path.replace("/./", "/").lstrip("./")
        if cleaned.startswith(("http://", "https://")):
            return cleaned
        base_url = str(self._settings.public_base_url) if self._settings.public_base_url else None
        if base_url:
            return urljoin(base_url.rstrip("/") + "/", cleaned)
        return f"/{cleaned}"

    def _map_carousel_item(self, item: HomepageCarousel) -> HomepageCarouselImageItem:
        """Map HomepageCarousel model to wire schema."""
        hidden_val = "1" if item.is_hidden == HomepageCarouselIsHidden._1 else "0"
        greeting_val = "1" if item.show_greeting == HomepageCarouselShowGreeting._1 else "0"
        return HomepageCarouselImageItem(
            id=item.id,
            image_url=self._public_url(item.image_url) or item.image_url,
            file_name=item.file_name,
            alt_text=item.alt_text,
            sort_order=item.sort_order or 0,
            is_hidden=hidden_val,
            show_greeting=greeting_val,
            created_at=item.created_at.isoformat() if item.created_at else None,
            updated_at=item.updated_at.isoformat() if item.updated_at else None,
        )

    def _map_faq_item(self, item: Faqs) -> FaqItem:
        """Map Faqs model to wire schema."""
        published_val = (
            "1"
            if item.is_published == "1" or getattr(item.is_published, "value", "") == "1"
            else "0"
        )
        return FaqItem(
            id=item.id,
            question=item.question,
            answer=item.answer,
            sort_order=item.sort_order or 0,
            is_published=published_val,
            created_at=item.created_at.isoformat() if item.created_at else None,
            updated_at=item.updated_at.isoformat() if item.updated_at else None,
        )

    def _map_category_item(self, item: BlogCategories) -> BlogCategoryItem:
        """Map BlogCategories model to wire schema."""
        return BlogCategoryItem(
            id=item.id,
            name=item.name,
            slug=item.slug or "",
            sort_order=item.sort_order or 0,
            is_active=item.is_active if item.is_active is not None else 1,
            created_at=item.created_at.isoformat() if item.created_at else None,
            updated_at=item.updated_at.isoformat() if item.updated_at else None,
        )

    # ═════════════════════════════════════════════════════════════
    # HOMEPAGE & CAROUSEL USE CASES
    # ═════════════════════════════════════════════════════════════

    def get_homepage(self, is_admin: bool = False) -> HomepageData:
        """Read homepage greeting and carousel items."""
        greeting = self._repository.get_homepage()
        carousel_items = self._repository.list_carousel(admin=is_admin)
        greeting_image_id = self._repository.get_greeting_image_id()

        return HomepageData(
            greeting_title=greeting.greeting_title if greeting and greeting.greeting_title else "",
            greeting_message=greeting.greeting_message
            if greeting and greeting.greeting_message
            else "",
            greeting_image_id=greeting_image_id,
            carousel_images=[self._map_carousel_item(item) for item in carousel_items],
        )

    def update_homepage_text(
        self, actor_user_id: int, greeting_title: str, greeting_message: str
    ) -> dict[str, str]:
        """Update greeting title and message."""
        self._require_manage_content(actor_user_id)
        clean_title = greeting_title.strip()
        clean_message = greeting_message.strip()
        if not clean_title or not clean_message:
            raise BlogError(
                "validation_error", "greeting_title and greeting_message are required", 400
            )

        self._repository.update_or_create_homepage(clean_title, clean_message)
        self._session.commit()
        return {"greeting_title": clean_title, "greeting_message": clean_message}

    def create_carousel_image(
        self,
        actor_user_id: int,
        content: bytes,
        filename: str | None,
        alt_text: str | None = None,
        sort_order: int | None = None,
        show_greeting: bool = False,
    ) -> tuple[HomepageCarouselImageItem, int | None]:
        """Validate, store, and create a carousel image."""
        self._require_manage_content(actor_user_id)
        try:
            prepared = prepare_avatar(filename, content)
        except AvatarUploadError as exc:
            raise BlogError("invalid_image", str(exc), 400) from exc

        stored: StoredAvatar | None = None
        try:
            stored = self._carousel_storage.save(actor_user_id, prepared)
            image_id = self._repository.add_carousel_image(
                image_url=stored.relative_path,
                file_name=stored.filename,
                alt_text=alt_text.strip() if alt_text else None,
                sort_order=sort_order,
            )
            if show_greeting:
                self._repository.set_greeting_image(image_id)
            self._session.commit()
        except Exception:
            self._session.rollback()
            if stored is not None:
                self._carousel_storage.delete(stored)
            raise

        created = self._repository.get_carousel_image(image_id)
        if created is None:
            raise BlogError("carousel_failed", "Carousel image creation failed", 500)

        greeting_image_id = self._repository.get_greeting_image_id()
        return self._map_carousel_item(created), greeting_image_id

    def update_carousel_image(
        self,
        actor_user_id: int,
        image_id: int,
        alt_text: str | None = None,
        is_hidden: bool | None = None,
        show_greeting: bool | None = None,
        new_image_content: bytes | None = None,
        new_image_filename: str | None = None,
    ) -> tuple[HomepageCarouselImageItem, int | None, list[HomepageCarouselImageItem]]:
        """Update carousel image metadata, replacement image, and greeting state."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_carousel_image(image_id, lock=True)
        if existing is None:
            raise BlogError("not_found", "Carousel image not found", 404)

        update_values: dict[str, Any] = {}
        if alt_text is not None:
            update_values["alt_text"] = alt_text.strip()

        hiding = False
        if is_hidden is not None:
            hiding = is_hidden
            update_values["is_hidden"] = (
                HomepageCarouselIsHidden._1 if is_hidden else HomepageCarouselIsHidden._0
            )

        stored: StoredAvatar | None = None
        if new_image_content:
            try:
                prepared = prepare_avatar(new_image_filename, new_image_content)
                stored = self._carousel_storage.save(actor_user_id, prepared)
                update_values["image_url"] = stored.relative_path
                update_values["file_name"] = stored.filename
            except AvatarUploadError as exc:
                raise BlogError("invalid_image", str(exc), 400) from exc

        if not update_values and show_greeting is None:
            raise BlogError("no_fields", "No fields to update", 400)

        try:
            if update_values:
                self._repository.update_carousel_image(image_id, update_values)

            if show_greeting is True:
                self._repository.set_greeting_image(image_id)
            elif show_greeting is False:
                self._repository.clear_greeting_image(image_id)

            if hiding:
                self._repository.reassign_greeting_image(image_id)

            self._session.commit()
        except Exception:
            self._session.rollback()
            if stored is not None:
                self._carousel_storage.delete(stored)
            raise

        updated = self._repository.get_carousel_image(image_id)
        if updated is None:
            raise BlogError("not_found", "Carousel image not found", 404)

        greeting_image_id = self._repository.get_greeting_image_id()
        all_images = [
            self._map_carousel_item(i) for i in self._repository.list_carousel(admin=True)
        ]
        return self._map_carousel_item(updated), greeting_image_id, all_images

    def reorder_carousel(
        self, actor_user_id: int, images: list[dict[str, Any]]
    ) -> list[HomepageCarouselImageItem]:
        """Reorder carousel images."""
        self._require_manage_content(actor_user_id)
        if not images:
            raise BlogError("validation_error", "images array is required", 400)
        image_orders = [(int(item["id"]), int(item["sort_order"])) for item in images]
        self._repository.reorder_carousel(image_orders)
        self._session.commit()
        return [self._map_carousel_item(i) for i in self._repository.list_carousel(admin=True)]

    def delete_carousel_image(
        self, actor_user_id: int, image_id: int
    ) -> tuple[int | None, list[HomepageCarouselImageItem]]:
        """Soft-delete a carousel image and reassign greeting if needed."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_carousel_image(image_id, lock=True)
        if existing is None:
            raise BlogError("not_found", "Carousel image not found", 404)

        self._repository.delete_carousel_image(image_id)
        self._session.commit()

        greeting_image_id = self._repository.get_greeting_image_id()
        all_images = [
            self._map_carousel_item(i) for i in self._repository.list_carousel(admin=True)
        ]
        return greeting_image_id, all_images

    # ═════════════════════════════════════════════════════════════
    # FAQ USE CASES
    # ═════════════════════════════════════════════════════════════

    def list_faqs(self, is_admin: bool = False) -> list[FaqItem]:
        """Fetch FAQ list."""
        faqs = self._repository.list_faqs(admin=is_admin)
        return [self._map_faq_item(f) for f in faqs]

    def create_faq(
        self,
        actor_user_id: int,
        question: str,
        answer: str,
        sort_order: int | None = None,
        is_published: str = "1",
    ) -> FaqItem:
        """Create a new FAQ."""
        self._require_manage_content(actor_user_id)
        q = question.strip()
        a = answer.strip()
        if not q or not a:
            raise BlogError("validation_error", "question and answer are required", 400)

        faq_id = self._repository.create_faq(q, a, sort_order=sort_order, is_published=is_published)
        self._session.commit()

        faq = self._repository.get_faq(faq_id)
        if faq is None:
            raise BlogError("faq_failed", "FAQ creation failed", 500)
        return self._map_faq_item(faq)

    def update_faq(
        self,
        actor_user_id: int,
        faq_id: int,
        question: str | None = None,
        answer: str | None = None,
        sort_order: int | None = None,
        is_published: str | None = None,
    ) -> FaqItem:
        """Update FAQ fields."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_faq(faq_id)
        if existing is None:
            raise BlogError("not_found", "FAQ not found", 404)

        update_values: dict[str, Any] = {}
        if question is not None:
            update_values["question"] = question.strip()
        if answer is not None:
            update_values["answer"] = answer.strip()
        if sort_order is not None:
            update_values["sort_order"] = sort_order
        if is_published is not None:
            pub_enum = "1" if is_published in {"1", "true", "yes", True} else "0"
            update_values["is_published"] = pub_enum

        if not update_values:
            raise BlogError("no_fields", "No fields to update", 400)

        self._repository.update_faq(faq_id, update_values)
        self._session.commit()

        updated = self._repository.get_faq(faq_id)
        if updated is None:
            raise BlogError("not_found", "FAQ not found", 404)
        return self._map_faq_item(updated)

    def reorder_faqs(self, actor_user_id: int, faqs: list[dict[str, Any]]) -> list[FaqItem]:
        """Reorder FAQs."""
        self._require_manage_content(actor_user_id)
        if not faqs:
            raise BlogError("validation_error", "faqs array is required", 400)
        faq_orders = [(int(item["id"]), int(item["sort_order"])) for item in faqs]
        self._repository.reorder_faqs(faq_orders)
        self._session.commit()
        return [self._map_faq_item(f) for f in self._repository.list_faqs(admin=True)]

    def delete_faq(self, actor_user_id: int, faq_id: int) -> None:
        """Soft-delete a FAQ."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_faq(faq_id)
        if existing is None:
            raise BlogError("not_found", "FAQ not found", 404)

        self._repository.delete_faq(faq_id)
        self._session.commit()

    # ═════════════════════════════════════════════════════════════
    # BLOG CATEGORY USE CASES
    # ═════════════════════════════════════════════════════════════

    def list_categories(self, is_admin: bool = False) -> list[BlogCategoryItem]:
        """Fetch categories."""
        cats = self._repository.list_categories(admin=is_admin)
        return [self._map_category_item(c) for c in cats]

    def create_category(
        self,
        actor_user_id: int,
        name: str,
        slug: str | None = None,
        is_active: int = 1,
        sort_order: int | None = None,
    ) -> BlogCategoryItem:
        """Create or revive a blog category."""
        self._require_manage_content(actor_user_id)
        clean_name = name.strip()
        if not clean_name:
            raise BlogError("validation_error", "name is required", 400)

        clean_slug = (
            slug.strip() if slug else re.sub(r"[^a-z0-9]+", "-", clean_name.lower()).strip("-")
        )
        if not clean_slug:
            clean_slug = "category"

        cat_id = self._repository.create_category(
            clean_name, clean_slug, is_active=is_active, sort_order=sort_order
        )
        self._session.commit()

        cat = self._repository.get_category(cat_id)
        if cat is None:
            raise BlogError("category_failed", "Category creation failed", 500)
        return self._map_category_item(cat)

    def update_category(
        self,
        actor_user_id: int,
        category_id: int,
        name: str | None = None,
        slug: str | None = None,
        is_active: int | None = None,
        sort_order: int | None = None,
    ) -> BlogCategoryItem:
        """Update blog category."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_category(category_id)
        if existing is None:
            raise BlogError("not_found", "Category not found", 404)

        update_values: dict[str, Any] = {}
        if name is not None:
            clean_name = name.strip()
            update_values["name"] = clean_name
            if slug is None:
                update_values["slug"] = re.sub(r"[^a-z0-9]+", "-", clean_name.lower()).strip("-")

        if slug is not None:
            update_values["slug"] = re.sub(r"[^a-z0-9]+", "-", slug.strip().lower()).strip("-")

        if is_active is not None:
            update_values["is_active"] = is_active

        if sort_order is not None:
            update_values["sort_order"] = sort_order

        if not update_values:
            raise BlogError("no_fields", "No fields to update", 400)

        self._repository.update_category(category_id, update_values)
        self._session.commit()

        updated = self._repository.get_category(category_id)
        if updated is None:
            raise BlogError("not_found", "Category not found", 404)
        return self._map_category_item(updated)

    def delete_category(self, actor_user_id: int, category_id: int) -> list[BlogCategoryItem]:
        """Soft-delete category and return remaining active categories."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_category(category_id)
        if existing is None:
            raise BlogError("not_found", "Category not found", 404)

        self._repository.delete_category(category_id)
        self._session.commit()

        return [self._map_category_item(c) for c in self._repository.list_categories(admin=True)]

    def reorder_categories(
        self, actor_user_id: int, categories: list[dict[str, Any]]
    ) -> list[BlogCategoryItem]:
        """Reorder categories."""
        self._require_manage_content(actor_user_id)
        if not categories:
            raise BlogError("validation_error", "categories array is required", 400)
        cat_orders = [(int(item["id"]), int(item["sort_order"])) for item in categories]
        self._repository.reorder_categories(cat_orders)
        self._session.commit()
        return [self._map_category_item(c) for c in self._repository.list_categories(admin=True)]

    # ═════════════════════════════════════════════════════════════
    # BLOG POST USE CASES
    # ═════════════════════════════════════════════════════════════

    def list_posts(
        self, filters: BlogPostsFilters, is_admin: bool = False
    ) -> tuple[list[BlogPostSummaryItem], BlogPagination]:
        """List posts with pagination, status filtering, category, and search."""
        rows, total = self._repository.list_posts(filters, admin=is_admin)
        items: list[BlogPostSummaryItem] = []
        for r in rows:
            items.append(
                BlogPostSummaryItem(
                    id=int(r["id"]),
                    slug=str(r["slug"]),
                    title=str(r["title"]),
                    excerpt=r.get("excerpt"),
                    category_id=r.get("category_id"),
                    category_name=r.get("category_name"),
                    status=str(getattr(r.get("status"), "value", r.get("status") or "draft")),
                    cover_image_url=self._public_url(r.get("cover_image_url")),
                    read_time_minutes=r.get("read_time_minutes"),
                    published_at=r["published_at"].isoformat() if r.get("published_at") else None,
                    created_at=r["created_at"].isoformat() if r.get("created_at") else None,
                    updated_at=r["updated_at"].isoformat() if r.get("updated_at") else None,
                )
            )

        total_pages = math.ceil(total / filters.limit) if filters.limit > 0 else 1
        pagination = BlogPagination(
            page=filters.page,
            limit=filters.limit,
            total=total,
            total_pages=total_pages,
        )
        return items, pagination

    def get_post_detail(self, id_or_slug: str | int, is_admin: bool = False) -> BlogPostDetailItem:
        """Fetch complete detail of a blog post."""
        row = self._repository.get_post_detail(id_or_slug, admin=is_admin)
        if row is None:
            raise BlogError("not_found", "Post not found", 404)

        sections: list[BlogSectionItem] = []
        for s in row.get("sections", []):
            sections.append(
                BlogSectionItem(
                    id=s.get("id"),
                    post_id=s.get("post_id"),
                    heading=s.get("heading"),
                    body=s.get("body"),
                    sort_order=s.get("sort_order", 0),
                    created_at=s["created_at"].isoformat() if s.get("created_at") else None,
                    updated_at=s["updated_at"].isoformat() if s.get("updated_at") else None,
                )
            )

        gallery: list[BlogGalleryImageItem] = []
        for g in row.get("gallery_images", []):
            gallery.append(
                BlogGalleryImageItem(
                    id=g.get("id"),
                    post_id=g.get("post_id"),
                    image_url=self._public_url(g.get("image_url")) or g["image_url"],
                    file_name=g.get("file_name"),
                    alt_text=g.get("alt_text"),
                    sort_order=g.get("sort_order", 0),
                    created_at=g["created_at"].isoformat() if g.get("created_at") else None,
                    updated_at=g["updated_at"].isoformat() if g.get("updated_at") else None,
                )
            )

        return BlogPostDetailItem(
            id=int(row["id"]),
            slug=str(row["slug"]),
            title=str(row["title"]),
            excerpt=row.get("excerpt"),
            category_id=row.get("category_id"),
            category_name=row.get("category_name"),
            status=str(getattr(row.get("status"), "value", row.get("status") or "draft")),
            cover_image_url=self._public_url(row.get("cover_image_url")),
            read_time_minutes=row.get("read_time_minutes"),
            published_at=row["published_at"].isoformat() if row.get("published_at") else None,
            created_at=row["created_at"].isoformat() if row.get("created_at") else None,
            updated_at=row["updated_at"].isoformat() if row.get("updated_at") else None,
            sections=sections,
            gallery_images=gallery,
        )

    def create_post(
        self,
        actor_user_id: int,
        title: str,
        category_id: int,
        excerpt: str,
        status: str,
        sections: list[dict[str, Any]],
        uploaded_images: list[tuple[str | None, bytes]] | None = None,
        main_image_index: int | None = None,
    ) -> BlogPostDetailItem:
        """Create a blog post with sections and optional gallery images."""
        self._require_manage_content(actor_user_id)
        clean_title = title.strip()
        clean_excerpt = excerpt.strip()
        clean_status = "published" if status.lower() == "published" else "draft"

        if not clean_title or not category_id or not clean_excerpt:
            raise BlogError("validation_error", "title, category_id, and excerpt are required", 400)

        if not sections:
            raise BlogError("validation_error", "At least one section is required", 400)

        category = self._repository.get_category(category_id)
        if category is None:
            raise BlogError("invalid_category", "Category does not exist", 400)

        stored_images: list[StoredAvatar] = []
        gallery_payload: list[dict[str, Any]] = []

        if uploaded_images:
            for idx, (fname, raw_bytes) in enumerate(uploaded_images):
                try:
                    prepared = prepare_avatar(fname, raw_bytes)
                    saved = self._gallery_storage.save(actor_user_id, prepared)
                    stored_images.append(saved)
                    gallery_payload.append(
                        {
                            "image_url": saved.relative_path,
                            "file_name": saved.filename,
                            "alt_text": "",
                            "sort_order": idx,
                        }
                    )
                except AvatarUploadError as exc:
                    # Clean up already saved images
                    for s in stored_images:
                        self._gallery_storage.delete(s)
                    raise BlogError("invalid_image", str(exc), 400) from exc

        if (
            main_image_index is not None
            and gallery_payload
            and (main_image_index < 0 or main_image_index >= len(gallery_payload))
        ):
            for s in stored_images:
                self._gallery_storage.delete(s)
            raise BlogError(
                "invalid_index",
                (
                    "main_image_index must be a valid zero-based index into uploaded images. "
                    f"Provided: {main_image_index}, Available: {len(gallery_payload)}"
                ),
                400,
            )

        try:
            post_id = self._repository.create_post(
                title=clean_title,
                category_id=category_id,
                excerpt=clean_excerpt,
                status=clean_status,
                sections=sections,
                gallery=gallery_payload,
                main_image_index=main_image_index,
            )
            self._session.commit()
        except Exception:
            self._session.rollback()
            for s in stored_images:
                self._gallery_storage.delete(s)
            raise

        return self.get_post_detail(post_id, is_admin=True)

    def update_post(
        self,
        actor_user_id: int,
        post_id: int,
        title: str | None = None,
        category_id: int | None = None,
        excerpt: str | None = None,
        status: str | None = None,
        sections: list[dict[str, Any]] | None = None,
        uploaded_images: list[tuple[str | None, bytes]] | None = None,
        main_image_url: str | None = None,
        main_image_index: int | None = None,
    ) -> BlogPostDetailItem:
        """Update blog post fields, replacing sections or gallery if provided."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_post_raw(post_id)
        if existing is None:
            raise BlogError("not_found", "Post not found", 404)

        if category_id is not None:
            category = self._repository.get_category(category_id)
            if category is None:
                raise BlogError("invalid_category", "Category does not exist", 400)

        stored_images: list[StoredAvatar] = []
        gallery_payload: list[dict[str, Any]] = []

        if uploaded_images:
            for idx, (fname, raw_bytes) in enumerate(uploaded_images):
                try:
                    prepared = prepare_avatar(fname, raw_bytes)
                    saved = self._gallery_storage.save(actor_user_id, prepared)
                    stored_images.append(saved)
                    gallery_payload.append(
                        {
                            "image_url": saved.relative_path,
                            "file_name": saved.filename,
                            "alt_text": "",
                            "sort_order": idx,
                        }
                    )
                except AvatarUploadError as exc:
                    for s in stored_images:
                        self._gallery_storage.delete(s)
                    raise BlogError("invalid_image", str(exc), 400) from exc

        cover_image_url: str | None = None
        if main_image_url:
            resolved = self._repository.resolve_post_image_url(post_id, main_image_url)
            if resolved is None:
                for s in stored_images:
                    self._gallery_storage.delete(s)
                raise BlogError("invalid_cover", "main_image_url does not belong to this post", 400)
            cover_image_url = resolved
        elif (
            gallery_payload
            and main_image_index is not None
            and (main_image_index < 0 or main_image_index >= len(gallery_payload))
        ):
            for s in stored_images:
                self._gallery_storage.delete(s)
            raise BlogError(
                "invalid_index",
                (
                    "main_image_index must be a valid zero-based index into uploaded images. "
                    f"Provided: {main_image_index}, Available: {len(gallery_payload)}"
                ),
                400,
            )
        elif gallery_payload and main_image_index is not None:
            cover_image_url = gallery_payload[main_image_index]["image_url"]
        elif gallery_payload:
            cover_image_url = gallery_payload[0]["image_url"]

        try:
            self._repository.update_post(
                post_id=post_id,
                title=title.strip() if title is not None else None,
                category_id=category_id,
                excerpt=excerpt.strip() if excerpt is not None else None,
                status=status.lower() if status is not None else None,
                sections=sections,
                gallery=gallery_payload if gallery_payload else None,
                cover_image_url=cover_image_url,
            )
            self._session.commit()
        except Exception:
            self._session.rollback()
            for s in stored_images:
                self._gallery_storage.delete(s)
            raise

        return self.get_post_detail(post_id, is_admin=True)

    def delete_post(self, actor_user_id: int, post_id: int) -> None:
        """Soft-delete a blog post."""
        self._require_manage_content(actor_user_id)
        existing = self._repository.get_post_raw(post_id)
        if existing is None:
            raise BlogError("not_found", "Post not found", 404)

        self._repository.delete_post(post_id)
        self._session.commit()
