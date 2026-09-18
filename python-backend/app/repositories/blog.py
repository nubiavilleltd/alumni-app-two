"""Database repository for Homepage, Carousel, FAQs, Categories, and Blog Posts."""

from __future__ import annotations

import math
import re
from datetime import UTC, datetime
from typing import Any, cast
from urllib.parse import urlparse

from sqlalchemy import CursorResult, Table, func, or_, select, update
from sqlalchemy.orm import Session

from app.models.generated import (
    BlogCategories,
    BlogGallery,
    BlogPosts,
    BlogPostsStatus,
    BlogSections,
    Faqs,
    FaqsIsPublished,
    Homepage,
    HomepageCarousel,
    HomepageCarouselIsHidden,
    HomepageCarouselShowGreeting,
    Users,
)
from app.schemas.blog import BlogPostsFilters

HOMEPAGE_TABLE = cast(Table, Homepage.__table__)
HOMEPAGE_CAROUSEL_TABLE = cast(Table, HomepageCarousel.__table__)
FAQS_TABLE = cast(Table, Faqs.__table__)
BLOG_CATEGORIES_TABLE = cast(Table, BlogCategories.__table__)
BLOG_POSTS_TABLE = cast(Table, BlogPosts.__table__)
BLOG_SECTIONS_TABLE = cast(Table, BlogSections.__table__)
BLOG_GALLERY_TABLE = cast(Table, BlogGallery.__table__)


class BlogRepository:
    """Explicit repository for the content and blog family using the compatibility schema."""

    def __init__(self, session: Session) -> None:
        self._session = session

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
        """Lock and fetch current actor facts for authorization checks."""
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

    # ═════════════════════════════════════════════════════════════
    # HOMEPAGE & CAROUSEL METHODS
    # ═════════════════════════════════════════════════════════════

    def get_homepage(self) -> Homepage | None:
        """Fetch the single homepage greeting row."""
        return self._session.execute(
            select(Homepage).order_by(Homepage.id.asc()).limit(1)
        ).scalar_one_or_none()

    def update_or_create_homepage(self, greeting_title: str, greeting_message: str) -> None:
        """Update or initialize the single homepage row."""
        existing = self.get_homepage()
        if existing is None:
            self._session.execute(
                HOMEPAGE_TABLE.insert().values(
                    id=1,
                    greeting_title=greeting_title,
                    greeting_message=greeting_message,
                )
            )
        else:
            self._session.execute(
                update(HOMEPAGE_TABLE)
                .where(Homepage.id == existing.id)
                .values(
                    greeting_title=greeting_title,
                    greeting_message=greeting_message,
                )
            )

    def list_carousel(self, admin: bool = False) -> list[HomepageCarousel]:
        """Fetch non-deleted carousel items ordered by sort_order."""
        query = select(HomepageCarousel).where(HomepageCarousel.deleted_at.is_(None))
        if not admin:
            query = query.where(HomepageCarousel.is_hidden == HomepageCarouselIsHidden._0)
        query = query.order_by(HomepageCarousel.sort_order.asc())
        return list(self._session.execute(query).scalars().all())

    def get_carousel_image(self, image_id: int, *, lock: bool = False) -> HomepageCarousel | None:
        """Fetch a single carousel item by ID."""
        query = select(HomepageCarousel).where(
            HomepageCarousel.id == image_id,
            HomepageCarousel.deleted_at.is_(None),
        )
        if lock:
            query = query.with_for_update()
        return self._session.execute(query.limit(1)).scalar_one_or_none()

    def get_greeting_image_id(self) -> int | None:
        """Identify which active carousel image currently carries the greeting."""
        query = (
            select(HomepageCarousel.id)
            .where(
                HomepageCarousel.show_greeting == HomepageCarouselShowGreeting._1,
                HomepageCarousel.deleted_at.is_(None),
            )
            .limit(1)
        )
        return self._session.execute(query).scalar_one_or_none()

    def add_carousel_image(
        self,
        image_url: str,
        file_name: str | None,
        alt_text: str | None,
        sort_order: int | None = None,
    ) -> int:
        """Insert a new carousel item."""
        if sort_order is None:
            max_order = self._session.scalar(
                select(func.max(HomepageCarousel.sort_order)).where(
                    HomepageCarousel.deleted_at.is_(None)
                )
            )
            sort_order = (max_order or 0) + 1

        result = cast(
            CursorResult[Any],
            self._session.execute(
                HOMEPAGE_CAROUSEL_TABLE.insert().values(
                    image_url=image_url,
                    file_name=file_name,
                    alt_text=alt_text,
                    sort_order=sort_order,
                    is_hidden=HomepageCarouselIsHidden._0,
                    show_greeting=HomepageCarouselShowGreeting._0,
                )
            ),
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Carousel insert did not return a primary key")
        return int(key[0])

    def update_carousel_image(self, image_id: int, values: dict[str, Any]) -> None:
        """Update fields of a carousel image."""
        if not values:
            return
        self._session.execute(
            update(HOMEPAGE_CAROUSEL_TABLE).where(HomepageCarousel.id == image_id).values(**values)
        )
        self._session.expire_all()

    def set_greeting_image(self, image_id: int) -> bool:
        """Set show_greeting on target image, clearing it from all others."""
        self._session.execute(
            update(HOMEPAGE_CAROUSEL_TABLE)
            .where(
                HomepageCarousel.id != image_id,
                HomepageCarousel.show_greeting == HomepageCarouselShowGreeting._1,
            )
            .values(show_greeting=HomepageCarouselShowGreeting._0)
        )
        self._session.execute(
            update(HOMEPAGE_CAROUSEL_TABLE)
            .where(HomepageCarousel.id == image_id)
            .values(show_greeting=HomepageCarouselShowGreeting._1)
        )
        row = self.get_carousel_image(image_id)
        return row is not None and row.show_greeting == HomepageCarouselShowGreeting._1

    def clear_greeting_image(self, image_id: int) -> None:
        """Clear show_greeting flag on a specific image."""
        self._session.execute(
            update(HOMEPAGE_CAROUSEL_TABLE)
            .where(HomepageCarousel.id == image_id)
            .values(show_greeting=HomepageCarouselShowGreeting._0)
        )

    def reassign_greeting_image(self, image_id: int) -> int | None:
        """If image carried greeting, move it to the next visible image by sort_order."""
        image = self.get_carousel_image(image_id)
        if image is None or image.show_greeting != HomepageCarouselShowGreeting._1:
            return None

        self.clear_greeting_image(image_id)
        next_image = self._session.execute(
            select(HomepageCarousel)
            .where(
                HomepageCarousel.id != image_id,
                HomepageCarousel.is_hidden == HomepageCarouselIsHidden._0,
                HomepageCarousel.deleted_at.is_(None),
            )
            .order_by(HomepageCarousel.sort_order.asc())
            .limit(1)
        ).scalar_one_or_none()

        if next_image is not None:
            self.set_greeting_image(next_image.id)
            return next_image.id
        return None

    def reorder_carousel(self, image_orders: list[tuple[int, int]]) -> None:
        """Update sort_order for multiple carousel items."""
        for img_id, order in image_orders:
            self._session.execute(
                update(HOMEPAGE_CAROUSEL_TABLE)
                .where(HomepageCarousel.id == img_id)
                .values(sort_order=order)
            )

    def delete_carousel_image(self, image_id: int) -> None:
        """Soft-delete a carousel image, moving greeting first if held."""
        self.reassign_greeting_image(image_id)
        self._session.execute(
            update(HOMEPAGE_CAROUSEL_TABLE)
            .where(HomepageCarousel.id == image_id)
            .values(deleted_at=datetime.now(UTC).replace(tzinfo=None))
        )
        self.normalize_carousel_order()

    def normalize_carousel_order(self) -> None:
        """Renumber carousel sort_orders from 0 onwards sequentially."""
        items = list(
            self._session.execute(
                select(HomepageCarousel)
                .where(HomepageCarousel.deleted_at.is_(None))
                .order_by(HomepageCarousel.sort_order.asc())
            )
            .scalars()
            .all()
        )
        for index, item in enumerate(items):
            self._session.execute(
                update(HOMEPAGE_CAROUSEL_TABLE)
                .where(HomepageCarousel.id == item.id)
                .values(sort_order=index)
            )

    # ═════════════════════════════════════════════════════════════
    # FAQ METHODS
    # ═════════════════════════════════════════════════════════════

    def list_faqs(self, admin: bool = False) -> list[Faqs]:
        """Fetch FAQs ordered by sort_order."""
        query = select(Faqs).where(Faqs.deleted_at.is_(None))
        if not admin:
            query = query.where(Faqs.is_published == FaqsIsPublished._1)
        query = query.order_by(Faqs.sort_order.asc())
        return list(self._session.execute(query).scalars().all())

    def get_faq(self, faq_id: int) -> Faqs | None:
        """Fetch single non-deleted FAQ by ID."""
        return self._session.execute(
            select(Faqs).where(Faqs.id == faq_id, Faqs.deleted_at.is_(None)).limit(1)
        ).scalar_one_or_none()

    def create_faq(
        self,
        question: str,
        answer: str,
        sort_order: int | None = None,
        is_published: str = "1",
    ) -> int:
        """Insert a new FAQ."""
        if sort_order is None:
            max_order = self._session.scalar(
                select(func.max(Faqs.sort_order)).where(Faqs.deleted_at.is_(None))
            )
            sort_order = (max_order or 0) + 1

        published_enum = (
            FaqsIsPublished._1 if is_published in {"1", "true", "yes", True} else FaqsIsPublished._0
        )
        result = cast(
            CursorResult[Any],
            self._session.execute(
                FAQS_TABLE.insert().values(
                    question=question,
                    answer=answer,
                    sort_order=sort_order,
                    is_published=published_enum,
                )
            ),
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("FAQ insert did not return a primary key")
        return int(key[0])

    def update_faq(self, faq_id: int, values: dict[str, Any]) -> None:
        """Update FAQ fields."""
        if not values:
            return
        self._session.execute(update(FAQS_TABLE).where(Faqs.id == faq_id).values(**values))
        self._session.expire_all()

    def reorder_faqs(self, faq_orders: list[tuple[int, int]]) -> None:
        """Update sort_order for multiple FAQs."""
        for faq_id, order in faq_orders:
            self._session.execute(
                update(FAQS_TABLE).where(Faqs.id == faq_id).values(sort_order=order)
            )

    def delete_faq(self, faq_id: int) -> None:
        """Soft-delete a FAQ and normalize ordering."""
        self._session.execute(
            update(FAQS_TABLE)
            .where(Faqs.id == faq_id)
            .values(deleted_at=datetime.now(UTC).replace(tzinfo=None))
        )
        self.normalize_faq_order()

    def normalize_faq_order(self) -> None:
        """Renumber active FAQs sequentially."""
        items = list(
            self._session.execute(
                select(Faqs).where(Faqs.deleted_at.is_(None)).order_by(Faqs.sort_order.asc())
            )
            .scalars()
            .all()
        )
        for index, item in enumerate(items):
            self._session.execute(
                update(FAQS_TABLE).where(Faqs.id == item.id).values(sort_order=index)
            )

    # ═════════════════════════════════════════════════════════════
    # BLOG CATEGORY METHODS
    # ═════════════════════════════════════════════════════════════

    def list_categories(self, admin: bool = False) -> list[BlogCategories]:
        """Fetch categories ordered by sort_order."""
        query = select(BlogCategories).where(BlogCategories.deleted_at.is_(None))
        if not admin:
            query = query.where(BlogCategories.is_active == 1)
        query = query.order_by(BlogCategories.sort_order.asc())
        return list(self._session.execute(query).scalars().all())

    def get_category(self, category_id: int) -> BlogCategories | None:
        """Fetch single non-deleted category by ID."""
        return self._session.execute(
            select(BlogCategories)
            .where(BlogCategories.id == category_id, BlogCategories.deleted_at.is_(None))
            .limit(1)
        ).scalar_one_or_none()

    def get_category_by_slug(
        self, slug: str, include_deleted: bool = False
    ) -> BlogCategories | None:
        """Fetch category by unique slug."""
        query = select(BlogCategories).where(BlogCategories.slug == slug)
        if not include_deleted:
            query = query.where(BlogCategories.deleted_at.is_(None))
        return self._session.execute(query.limit(1)).scalar_one_or_none()

    def create_category(
        self,
        name: str,
        slug: str,
        is_active: int = 1,
        sort_order: int | None = None,
    ) -> int:
        """Insert or revive a category by unique slug."""
        if sort_order is None:
            max_order = self._session.scalar(
                select(func.max(BlogCategories.sort_order)).where(
                    BlogCategories.deleted_at.is_(None)
                )
            )
            sort_order = (max_order or 0) + 1

        deleted = self.get_category_by_slug(slug, include_deleted=True)
        if deleted is not None and deleted.deleted_at is not None:
            self._session.execute(
                update(BLOG_CATEGORIES_TABLE)
                .where(BlogCategories.id == deleted.id)
                .values(
                    name=name,
                    is_active=is_active,
                    sort_order=sort_order,
                    deleted_at=None,
                )
            )
            return deleted.id

        result = cast(
            CursorResult[Any],
            self._session.execute(
                BLOG_CATEGORIES_TABLE.insert().values(
                    name=name,
                    slug=slug,
                    is_active=is_active,
                    sort_order=sort_order,
                )
            ),
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Category insert did not return a primary key")
        return int(key[0])

    def update_category(self, category_id: int, values: dict[str, Any]) -> None:
        """Update category fields."""
        if not values:
            return
        self._session.execute(
            update(BLOG_CATEGORIES_TABLE).where(BlogCategories.id == category_id).values(**values)
        )
        self._session.expire_all()

    def delete_category(self, category_id: int) -> None:
        """Soft-delete a category (preserves blog_posts FK) and normalize order."""
        self._session.execute(
            update(BLOG_CATEGORIES_TABLE)
            .where(BlogCategories.id == category_id)
            .values(deleted_at=datetime.now(UTC).replace(tzinfo=None))
        )
        self.normalize_category_order()

    def reorder_categories(self, category_orders: list[tuple[int, int]]) -> None:
        """Update sort_order for multiple categories."""
        for cat_id, order in category_orders:
            self._session.execute(
                update(BLOG_CATEGORIES_TABLE)
                .where(BlogCategories.id == cat_id)
                .values(sort_order=order)
            )

    def normalize_category_order(self) -> None:
        """Renumber active categories sequentially."""
        items = list(
            self._session.execute(
                select(BlogCategories)
                .where(BlogCategories.deleted_at.is_(None))
                .order_by(BlogCategories.sort_order.asc())
            )
            .scalars()
            .all()
        )
        for index, item in enumerate(items):
            self._session.execute(
                update(BLOG_CATEGORIES_TABLE)
                .where(BlogCategories.id == item.id)
                .values(sort_order=index)
            )

    # ═════════════════════════════════════════════════════════════
    # BLOG POST, SECTION & GALLERY METHODS
    # ═════════════════════════════════════════════════════════════

    def list_posts(
        self, filters: BlogPostsFilters, admin: bool = False
    ) -> tuple[list[dict[str, Any]], int]:
        """List posts with category names, status filtering, search, and pagination."""
        base_conditions: list[Any] = [BlogPosts.deleted_at.is_(None)]

        if not admin:
            base_conditions.append(BlogPosts.status == BlogPostsStatus.PUBLISHED)
        elif filters.status != "all":
            base_conditions.append(BlogPosts.status == filters.status)

        if filters.category is not None:
            base_conditions.append(BlogPosts.category_id == filters.category)

        if filters.search:
            search_pattern = f"%{filters.search}%"
            base_conditions.append(
                or_(
                    BlogPosts.title.ilike(search_pattern),
                    BlogPosts.excerpt.ilike(search_pattern),
                )
            )

        # Count total
        count_stmt = select(func.count(BlogPosts.id)).where(*base_conditions)
        total = self._session.scalar(count_stmt) or 0

        # Query items
        offset = (filters.page - 1) * filters.limit
        stmt = (
            select(
                BlogPosts.id,
                BlogPosts.slug,
                BlogPosts.title,
                BlogPosts.excerpt,
                BlogPosts.category_id,
                BlogCategories.name.label("category_name"),
                BlogPosts.status,
                BlogPosts.cover_image_url,
                BlogPosts.read_time_minutes,
                BlogPosts.published_at,
                BlogPosts.created_at,
                BlogPosts.updated_at,
            )
            .outerjoin(BlogCategories, BlogPosts.category_id == BlogCategories.id)
            .where(*base_conditions)
            .order_by(BlogPosts.published_at.desc(), BlogPosts.created_at.desc())
            .offset(offset)
            .limit(filters.limit)
        )
        rows = [dict(r) for r in self._session.execute(stmt).mappings().all()]
        return rows, total

    def get_post_detail(self, id_or_slug: str | int, admin: bool = False) -> dict[str, Any] | None:
        """Fetch complete post detail including sections and gallery."""
        conditions: list[Any] = [BlogPosts.deleted_at.is_(None)]

        if isinstance(id_or_slug, int) or (isinstance(id_or_slug, str) and id_or_slug.isdigit()):
            conditions.append(BlogPosts.id == int(id_or_slug))
        else:
            conditions.append(BlogPosts.slug == str(id_or_slug))

        if not admin:
            conditions.append(BlogPosts.status == BlogPostsStatus.PUBLISHED)

        stmt = (
            select(
                BlogPosts.id,
                BlogPosts.slug,
                BlogPosts.title,
                BlogPosts.excerpt,
                BlogPosts.category_id,
                BlogCategories.name.label("category_name"),
                BlogPosts.status,
                BlogPosts.cover_image_url,
                BlogPosts.read_time_minutes,
                BlogPosts.published_at,
                BlogPosts.created_at,
                BlogPosts.updated_at,
            )
            .outerjoin(BlogCategories, BlogPosts.category_id == BlogCategories.id)
            .where(*conditions)
            .limit(1)
        )
        post_row = self._session.execute(stmt).mappings().first()
        if post_row is None:
            return None

        post = dict(post_row)
        post_id = int(post["id"])

        # Fetch sections
        sections_stmt = (
            select(
                BlogSections.id,
                BlogSections.post_id,
                BlogSections.heading,
                BlogSections.body,
                BlogSections.sort_order,
                BlogSections.created_at,
                BlogSections.updated_at,
            )
            .where(BlogSections.post_id == post_id)
            .order_by(BlogSections.sort_order.asc())
        )
        post["sections"] = [dict(s) for s in self._session.execute(sections_stmt).mappings().all()]

        # Fetch gallery
        gallery_stmt = (
            select(
                BlogGallery.id,
                BlogGallery.post_id,
                BlogGallery.image_url,
                BlogGallery.file_name,
                BlogGallery.alt_text,
                BlogGallery.sort_order,
                BlogGallery.created_at,
                BlogGallery.updated_at,
            )
            .where(BlogGallery.post_id == post_id)
            .order_by(BlogGallery.sort_order.asc())
        )
        post["gallery_images"] = [
            dict(g) for g in self._session.execute(gallery_stmt).mappings().all()
        ]

        return post

    def get_post_raw(self, post_id: int) -> BlogPosts | None:
        """Fetch raw BlogPosts model instance by ID."""
        return self._session.execute(
            select(BlogPosts)
            .where(BlogPosts.id == post_id, BlogPosts.deleted_at.is_(None))
            .limit(1)
        ).scalar_one_or_none()

    def resolve_post_image_url(self, post_id: int, image_url: str) -> str | None:
        """Resolve caller-supplied image URL to the URL stored in post's gallery."""
        cleaned = image_url.strip()
        if not cleaned:
            return None

        gallery = list(
            self._session.execute(select(BlogGallery).where(BlogGallery.post_id == post_id))
            .scalars()
            .all()
        )
        if not gallery:
            return None

        # 1. Exact match
        for img in gallery:
            if img.image_url == cleaned:
                return img.image_url

        # 2. Basename match
        parsed = urlparse(cleaned)
        needle_name = parsed.path.split("/")[-1] if parsed.path else cleaned
        if needle_name:
            for img in gallery:
                stored_name = img.file_name or (
                    urlparse(img.image_url).path.split("/")[-1]
                    if urlparse(img.image_url).path
                    else img.image_url
                )
                if stored_name == needle_name:
                    return img.image_url

        return None

    def generate_unique_slug(self, title: str, exclude_post_id: int | None = None) -> str:
        """Generate a unique URL slug from post title."""
        slug = title.strip().lower()
        slug = re.sub(r"[^a-z0-9]+", "-", slug).strip("-")
        if not slug:
            slug = "post"

        original_slug = slug
        counter = 1
        while True:
            stmt = select(func.count(BlogPosts.id)).where(BlogPosts.slug == slug)
            if exclude_post_id is not None:
                stmt = stmt.where(BlogPosts.id != exclude_post_id)
            count = self._session.scalar(stmt) or 0
            if count == 0:
                return slug
            slug = f"{original_slug}-{counter}"
            counter += 1

    def create_post(
        self,
        title: str,
        category_id: int,
        excerpt: str,
        status: str,
        sections: list[dict[str, Any]],
        gallery: list[dict[str, Any]],
        main_image_index: int | None = None,
    ) -> int:
        """Insert post, sections, and gallery atomically."""
        slug = self.generate_unique_slug(title)
        now = datetime.now(UTC).replace(tzinfo=None)
        published_at = now if status == "published" else None

        cover_image_url: str | None = None
        if gallery:
            idx = (
                main_image_index
                if main_image_index is not None and 0 <= main_image_index < len(gallery)
                else 0
            )
            cover_image_url = gallery[idx].get("image_url")

        status_enum = BlogPostsStatus.PUBLISHED if status == "published" else BlogPostsStatus.DRAFT

        result = cast(
            CursorResult[Any],
            self._session.execute(
                BLOG_POSTS_TABLE.insert().values(
                    slug=slug,
                    title=title,
                    excerpt=excerpt,
                    category_id=category_id,
                    status=status_enum,
                    cover_image_url=cover_image_url,
                    published_at=published_at,
                    read_time_minutes=1,
                )
            ),
        )
        key = result.inserted_primary_key
        if not key or key[0] is None:
            raise RuntimeError("Post insert did not return a primary key")
        post_id = int(key[0])

        # Add sections
        self.add_post_sections(post_id, sections)

        # Add gallery
        self.add_gallery_images(post_id, gallery)

        # Compute read time
        read_time = self.calculate_read_time(sections)
        self._session.execute(
            update(BLOG_POSTS_TABLE)
            .where(BlogPosts.id == post_id)
            .values(read_time_minutes=read_time)
        )

        return post_id

    def update_post(
        self,
        post_id: int,
        title: str | None = None,
        category_id: int | None = None,
        excerpt: str | None = None,
        status: str | None = None,
        sections: list[dict[str, Any]] | None = None,
        gallery: list[dict[str, Any]] | None = None,
        cover_image_url: str | None = None,
    ) -> None:
        """Update post, replacing sections/gallery if supplied."""
        update_data: dict[str, Any] = {}
        if title is not None:
            update_data["slug"] = self.generate_unique_slug(title, exclude_post_id=post_id)
            update_data["title"] = title

        if category_id is not None:
            update_data["category_id"] = category_id

        if excerpt is not None:
            update_data["excerpt"] = excerpt

        if status is not None:
            status_enum = (
                BlogPostsStatus.PUBLISHED if status == "published" else BlogPostsStatus.DRAFT
            )
            update_data["status"] = status_enum
            if status == "published":
                current = self.get_post_raw(post_id)
                if current is not None and current.published_at is None:
                    update_data["published_at"] = datetime.now(UTC).replace(tzinfo=None)

        if sections is not None:
            self._session.execute(
                BLOG_SECTIONS_TABLE.delete().where(BlogSections.post_id == post_id)
            )
            self.add_post_sections(post_id, sections)
            update_data["read_time_minutes"] = self.calculate_read_time(sections)
        elif "read_time_minutes" not in update_data:
            existing_sections = [
                dict(s)
                for s in self._session.execute(
                    select(BlogSections.body).where(BlogSections.post_id == post_id)
                )
                .mappings()
                .all()
            ]
            update_data["read_time_minutes"] = self.calculate_read_time(existing_sections)

        if gallery is not None and len(gallery) > 0:
            self._session.execute(BLOG_GALLERY_TABLE.delete().where(BlogGallery.post_id == post_id))
            self.add_gallery_images(post_id, gallery)
            if cover_image_url is not None:
                update_data["cover_image_url"] = cover_image_url
            else:
                update_data["cover_image_url"] = gallery[0].get("image_url")
        elif cover_image_url is not None:
            update_data["cover_image_url"] = cover_image_url

        if update_data:
            self._session.execute(
                update(BLOG_POSTS_TABLE).where(BlogPosts.id == post_id).values(**update_data)
            )
            self._session.expire_all()

    def add_post_sections(self, post_id: int, sections: list[dict[str, Any]]) -> None:
        """Insert section rows for a blog post."""
        for idx, section in enumerate(sections):
            self._session.execute(
                BLOG_SECTIONS_TABLE.insert().values(
                    post_id=post_id,
                    heading=section.get("heading") or "",
                    body=section.get("body") or "",
                    sort_order=section.get("sort_order", idx),
                )
            )

    def add_gallery_images(self, post_id: int, images: list[dict[str, Any]]) -> None:
        """Insert gallery rows for a blog post."""
        for idx, img in enumerate(images):
            self._session.execute(
                BLOG_GALLERY_TABLE.insert().values(
                    post_id=post_id,
                    image_url=img["image_url"],
                    file_name=img.get("file_name"),
                    alt_text=img.get("alt_text"),
                    sort_order=img.get("sort_order", idx),
                )
            )

    def delete_post(self, post_id: int) -> None:
        """Soft-delete a post."""
        self._session.execute(
            update(BLOG_POSTS_TABLE)
            .where(BlogPosts.id == post_id)
            .values(deleted_at=datetime.now(UTC).replace(tzinfo=None))
        )

    @staticmethod
    def calculate_read_time(sections: list[dict[str, Any]]) -> int:
        """Compute reading time in minutes based on ~200 words per minute."""
        total_words = 0
        for section in sections:
            body = section.get("body") or ""
            # Strip simple html tags
            clean_text = re.sub(r"<[^>]+>", " ", body)
            words = len(re.findall(r"\b\w+\b", clean_text))
            total_words += words
        return max(1, math.ceil(total_words / 200))
