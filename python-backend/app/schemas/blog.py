"""Pydantic schemas for the Blog family compatibility endpoints."""

from __future__ import annotations

from pydantic import BaseModel, ConfigDict, Field


class BlogMessageResponse(BaseModel):
    """Standard message response used by delete operations."""

    status: int = 200
    message: str


# ═════════════════════════════════════════════════════════════
# HOMEPAGE & CAROUSEL SCHEMAS
# ═════════════════════════════════════════════════════════════


class HomepageCarouselImageItem(BaseModel):
    """Single carousel item matching PHP wire contract."""

    model_config = ConfigDict(from_attributes=True)

    id: int
    image_url: str
    file_name: str | None = None
    alt_text: str | None = None
    sort_order: int = 0
    is_hidden: str = "0"
    show_greeting: str = "0"
    created_at: str | None = None
    updated_at: str | None = None


class HomepageData(BaseModel):
    """Aggregated homepage content."""

    greeting_title: str = ""
    greeting_message: str = ""
    greeting_image_id: int | None = None
    carousel_images: list[HomepageCarouselImageItem] = Field(default_factory=list)


class HomepageResponse(BaseModel):
    """Response returned by /blog_api/homepage."""

    status: int = 200
    homepage: HomepageData


class UpdateHomepageTextInput(BaseModel):
    """Request payload for updating homepage greeting text."""

    greeting_title: str = Field(min_length=1)
    greeting_message: str = Field(min_length=1)


class UpdateHomepageTextData(BaseModel):
    greeting_title: str
    greeting_message: str


class UpdateHomepageTextResponse(BaseModel):
    """Response returned by /blog_api/update_homepage_text."""

    status: int = 200
    message: str = "Homepage text updated successfully"
    data: UpdateHomepageTextData


class CarouselImageCreatedResponse(BaseModel):
    """Response returned by /blog_api/create_carousel_image."""

    status: int = 200
    message: str = "Carousel image created successfully"
    image: HomepageCarouselImageItem
    greeting_image_id: int | None = None


class CarouselImageUpdatedResponse(BaseModel):
    """Response returned by /blog_api/update_carousel_image."""

    status: int = 200
    message: str = "Carousel image updated successfully"
    image: HomepageCarouselImageItem
    greeting_image_id: int | None = None
    carousel_images: list[HomepageCarouselImageItem] = Field(default_factory=list)


class ReorderCarouselItem(BaseModel):
    id: int
    sort_order: int


class ReorderCarouselRequest(BaseModel):
    images: list[ReorderCarouselItem] = Field(min_length=1)


class ReorderCarouselResponse(BaseModel):
    """Response returned by /blog_api/reorder_carousel."""

    status: int = 200
    message: str = "Carousel reordered successfully"
    carousel_images: list[HomepageCarouselImageItem] = Field(default_factory=list)


class DeleteCarouselImageResponse(BaseModel):
    """Response returned by /blog_api/delete_carousel_image."""

    status: int = 200
    message: str = "Carousel image deleted successfully"
    greeting_image_id: int | None = None
    carousel_images: list[HomepageCarouselImageItem] = Field(default_factory=list)


# ═════════════════════════════════════════════════════════════
# FAQ SCHEMAS
# ═════════════════════════════════════════════════════════════


class FaqItem(BaseModel):
    """Single FAQ item matching PHP wire contract."""

    model_config = ConfigDict(from_attributes=True)

    id: int
    question: str
    answer: str
    sort_order: int = 0
    is_published: str = "1"
    created_at: str | None = None
    updated_at: str | None = None


class FaqsResponse(BaseModel):
    """Response returned by /blog_api/faqs."""

    status: int = 200
    faqs: list[FaqItem] = Field(default_factory=list)


class CreateFaqRequest(BaseModel):
    """Request payload for /blog_api/create_faq."""

    question: str = Field(min_length=1)
    answer: str = Field(min_length=1)
    sort_order: int | None = None
    is_published: str = "1"


class FaqMutationResponse(BaseModel):
    """Response returned by create/update FAQ."""

    status: int = 200
    message: str
    faq: FaqItem


class UpdateFaqRequest(BaseModel):
    """Request payload for /blog_api/update_faq."""

    id: int = Field(gt=0)
    question: str | None = None
    answer: str | None = None
    sort_order: int | None = None
    is_published: str | None = None


class ReorderFaqItem(BaseModel):
    id: int
    sort_order: int


class ReorderFaqsRequest(BaseModel):
    faqs: list[ReorderFaqItem] = Field(min_length=1)


class ReorderFaqsResponse(BaseModel):
    """Response returned by /blog_api/reorder_faqs."""

    status: int = 200
    message: str = "FAQs reordered successfully"
    faqs: list[FaqItem] = Field(default_factory=list)


# ═════════════════════════════════════════════════════════════
# BLOG CATEGORY SCHEMAS
# ═════════════════════════════════════════════════════════════


class BlogCategoryItem(BaseModel):
    """Single blog category item matching PHP wire contract."""

    model_config = ConfigDict(from_attributes=True)

    id: int
    name: str
    slug: str
    sort_order: int = 0
    is_active: int = 1
    created_at: str | None = None
    updated_at: str | None = None


class BlogCategoriesResponse(BaseModel):
    """Response returned by /blog_api/blog_categories."""

    status: int = 200
    categories: list[BlogCategoryItem] = Field(default_factory=list)


class CreateBlogCategoryRequest(BaseModel):
    """Request payload for /blog_api/create_blog_category."""

    name: str = Field(min_length=1)
    slug: str | None = None
    sort_order: int | None = None
    is_active: int = 1


class BlogCategoryMutationResponse(BaseModel):
    """Response returned by create/update category."""

    status: int = 200
    message: str
    category: BlogCategoryItem


class UpdateBlogCategoryRequest(BaseModel):
    """Request payload for /blog_api/update_blog_category."""

    id: int = Field(gt=0)
    name: str | None = None
    slug: str | None = None
    sort_order: int | None = None
    is_active: int | None = None


class DeleteBlogCategoryResponse(BaseModel):
    """Response returned by /blog_api/delete_blog_category."""

    status: int = 200
    message: str = "Category deleted successfully"
    categories: list[BlogCategoryItem] = Field(default_factory=list)


class ReorderCategoryItem(BaseModel):
    id: int
    sort_order: int


class ReorderCategoriesRequest(BaseModel):
    categories: list[ReorderCategoryItem] = Field(min_length=1)


class ReorderCategoriesResponse(BaseModel):
    """Response returned by /blog_api/reorder_categories."""

    status: int = 200
    message: str = "Categories reordered successfully"
    categories: list[BlogCategoryItem] = Field(default_factory=list)


# ═════════════════════════════════════════════════════════════
# BLOG POST, SECTION & GALLERY SCHEMAS
# ═════════════════════════════════════════════════════════════


class BlogSectionItem(BaseModel):
    """Single section of a blog post."""

    model_config = ConfigDict(from_attributes=True)

    id: int | None = None
    post_id: int | None = None
    heading: str | None = None
    body: str | None = None
    sort_order: int = 0
    created_at: str | None = None
    updated_at: str | None = None


class BlogGalleryImageItem(BaseModel):
    """Single image in a blog post gallery."""

    model_config = ConfigDict(from_attributes=True)

    id: int | None = None
    post_id: int | None = None
    image_url: str
    file_name: str | None = None
    alt_text: str | None = None
    sort_order: int = 0
    created_at: str | None = None
    updated_at: str | None = None


class BlogPostSummaryItem(BaseModel):
    """Summary item in post listings."""

    model_config = ConfigDict(from_attributes=True)

    id: int
    slug: str
    title: str
    excerpt: str | None = None
    category_id: int | None = None
    category_name: str | None = None
    status: str = "draft"
    cover_image_url: str | None = None
    read_time_minutes: int | None = None
    published_at: str | None = None
    created_at: str | None = None
    updated_at: str | None = None


class BlogPostDetailItem(BlogPostSummaryItem):
    """Full detail of a blog post with sections and gallery."""

    sections: list[BlogSectionItem] = Field(default_factory=list)
    gallery_images: list[BlogGalleryImageItem] = Field(default_factory=list)


class BlogPagination(BaseModel):
    """Pagination metadata for blog posts."""

    page: int = 1
    limit: int = 10
    total: int = 0
    total_pages: int = 0


class BlogPostsListResponse(BaseModel):
    """Response returned by /blog_api/blog_posts."""

    status: int = 200
    posts: list[BlogPostSummaryItem] = Field(default_factory=list)
    pagination: BlogPagination


class BlogPostDetailResponse(BaseModel):
    """Response returned by /blog_api/blog_post_detail/{id_or_slug}."""

    status: int = 200
    post: BlogPostDetailItem


class BlogPostMutationResponse(BaseModel):
    """Response returned by create/update blog post."""

    status: int = 200
    message: str
    post: BlogPostDetailItem


class BlogPostsFilters(BaseModel):
    """Validated query filters for listing blog posts."""

    status: str = "published"
    category: int | None = None
    search: str | None = None
    page: int = Field(default=1, ge=1)
    limit: int = Field(default=10, ge=1, le=100)


class IdRequest(BaseModel):
    """Generic ID selector for delete operations."""

    id: int = Field(gt=0)
