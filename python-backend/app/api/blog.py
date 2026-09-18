"""Router for Homepage, Carousel, FAQs, Categories, and Blog Posts."""

from __future__ import annotations

import json
from typing import Annotated, Any, cast

from fastapi import APIRouter, Depends, Request
from fastapi.responses import JSONResponse
from sqlalchemy.orm import Session
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import UploadFile

from app.api.common import (
    body_mapping,
    json_response,
    with_session,
)
from app.authorization.dependencies import AccessPrincipal, optional_access, require_access
from app.core.config import Settings
from app.db.session import Database
from app.schemas.auth import StatusResponse
from app.schemas.blog import (
    BlogCategoriesResponse,
    BlogCategoryItem,
    BlogCategoryMutationResponse,
    BlogMessageResponse,
    BlogPostDetailResponse,
    BlogPostMutationResponse,
    BlogPostsFilters,
    BlogPostsListResponse,
    BlogPostSummaryItem,
    CarouselImageCreatedResponse,
    CarouselImageUpdatedResponse,
    DeleteBlogCategoryResponse,
    DeleteCarouselImageResponse,
    FaqItem,
    FaqMutationResponse,
    FaqsResponse,
    HomepageCarouselImageItem,
    HomepageData,
    HomepageResponse,
    ReorderCarouselResponse,
    ReorderCategoriesResponse,
    ReorderFaqsResponse,
    UpdateHomepageTextResponse,
)
from app.services.blog import BlogError, BlogService

router = APIRouter(prefix="/blog_api", tags=["blog"])


def _to_bool(value: Any) -> bool:
    """Normalize boolean flag from json or string form-data."""
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value != 0
    if isinstance(value, str):
        return value.strip().lower() in {"1", "true", "yes", "on"}
    return False


async def _parse_sections(raw_sections: Any) -> list[dict[str, Any]]:
    """Parse sections from either JSON string or structured list."""
    if isinstance(raw_sections, str):
        try:
            parsed = json.loads(raw_sections)
            return parsed if isinstance(parsed, list) else []
        except (ValueError, json.JSONDecodeError):
            return []
    if isinstance(raw_sections, list):
        return raw_sections
    return []


# ═════════════════════════════════════════════════════════════
# HOMEPAGE & CAROUSEL ROUTES
# ═════════════════════════════════════════════════════════════


@router.get("/homepage", response_model=HomepageResponse)
@router.post("/homepage", response_model=HomepageResponse)
async def get_homepage(request: Request) -> JSONResponse:
    principal = optional_access(request)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    def call(session: Session) -> HomepageData:
        service = BlogService(session, settings)
        is_admin = service.is_admin_actor(principal.user_id if principal else None)
        return service.get_homepage(is_admin=is_admin)

    result = await run_in_threadpool(with_session, database, call)
    return json_response(HomepageResponse(status=200, homepage=result), 200)


@router.post("/update_homepage_text", response_model=UpdateHomepageTextResponse | StatusResponse)
async def update_homepage_text(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    title = str(payload.get("greeting_title") or "").strip()
    message = str(payload.get("greeting_message") or "").strip()

    if not title or not message:
        return json_response(
            StatusResponse(status=400, message="greeting_title and greeting_message are required"),
            400,
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> dict[str, str]:
            return BlogService(session, settings).update_homepage_text(
                principal.user_id, title, message
            )

        data = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        UpdateHomepageTextResponse(
            status=200,
            message="Homepage text updated successfully",
            data=cast(Any, data),
        ),
        200,
    )


@router.post("/create_carousel_image", response_model=CarouselImageCreatedResponse | StatusResponse)
async def create_carousel_image(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    form = await request.form()
    image_file = form.get("image")
    if not isinstance(image_file, UploadFile) or not image_file.filename:
        return json_response(
            StatusResponse(status=400, message="Image upload failed"),
            400,
        )

    alt_text = form.get("alt_text")
    sort_order_raw = form.get("sort_order")
    sort_order = (
        int(str(sort_order_raw)) if sort_order_raw and str(sort_order_raw).isdigit() else None
    )
    show_greeting = _to_bool(form.get("show_greeting"))

    raw_bytes = await image_file.read(5 * 1024 * 1024 + 1)
    await image_file.close()

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> tuple[HomepageCarouselImageItem, int | None]:
            return BlogService(session, settings).create_carousel_image(
                principal.user_id,
                raw_bytes,
                image_file.filename,
                alt_text=str(alt_text) if alt_text else None,
                sort_order=sort_order,
                show_greeting=show_greeting,
            )

        image_item, greeting_image_id = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        CarouselImageCreatedResponse(
            status=200,
            message="Carousel image created successfully",
            image=image_item,
            greeting_image_id=greeting_image_id,
        ),
        200,
    )


@router.post("/update_carousel_image", response_model=CarouselImageUpdatedResponse | StatusResponse)
async def update_carousel_image(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    content_type = request.headers.get("content-type", "").lower()
    payload: dict[str, Any] = {}
    image_file: UploadFile | None = None

    if "application/json" in content_type:
        payload = await body_mapping(request)
    else:
        form = await request.form()
        for key, value in form.multi_items():
            if key == "image" and isinstance(value, UploadFile):
                image_file = value
            elif isinstance(value, str):
                payload[key] = value

    image_id_raw = payload.get("id")
    if not image_id_raw or not str(image_id_raw).isdigit():
        if image_file:
            await image_file.close()
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    image_id = int(image_id_raw)
    alt_text = payload.get("alt_text")
    is_hidden = _to_bool(payload["is_hidden"]) if "is_hidden" in payload else None
    show_greeting = _to_bool(payload["show_greeting"]) if "show_greeting" in payload else None

    raw_bytes: bytes | None = None
    filename: str | None = None
    if image_file and image_file.filename:
        raw_bytes = await image_file.read(5 * 1024 * 1024 + 1)
        filename = image_file.filename
        await image_file.close()

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(
            session: Session,
        ) -> tuple[HomepageCarouselImageItem, int | None, list[HomepageCarouselImageItem]]:
            return BlogService(session, settings).update_carousel_image(
                principal.user_id,
                image_id,
                alt_text=str(alt_text) if alt_text is not None else None,
                is_hidden=is_hidden,
                show_greeting=show_greeting,
                new_image_content=raw_bytes,
                new_image_filename=filename,
            )

        updated_item, greeting_id, all_images = await run_in_threadpool(
            with_session, database, call
        )
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        CarouselImageUpdatedResponse(
            status=200,
            message="Carousel image updated successfully",
            image=updated_item,
            greeting_image_id=greeting_id,
            carousel_images=all_images,
        ),
        200,
    )


@router.post("/reorder_carousel", response_model=ReorderCarouselResponse | StatusResponse)
async def reorder_carousel(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    images_raw = payload.get("images")
    if not isinstance(images_raw, list) or not images_raw:
        return json_response(StatusResponse(status=400, message="images array is required"), 400)

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> list[HomepageCarouselImageItem]:
            return BlogService(session, settings).reorder_carousel(principal.user_id, images_raw)

        carousel_images = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        ReorderCarouselResponse(
            status=200,
            message="Carousel reordered successfully",
            carousel_images=carousel_images,
        ),
        200,
    )


@router.post("/delete_carousel_image", response_model=DeleteCarouselImageResponse | StatusResponse)
async def delete_carousel_image(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    image_id_raw = payload.get("id")
    if not image_id_raw or not str(image_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    image_id = int(image_id_raw)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> tuple[int | None, list[HomepageCarouselImageItem]]:
            return BlogService(session, settings).delete_carousel_image(principal.user_id, image_id)

        greeting_id, all_images = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        DeleteCarouselImageResponse(
            status=200,
            message="Carousel image deleted successfully",
            greeting_image_id=greeting_id,
            carousel_images=all_images,
        ),
        200,
    )


# ═════════════════════════════════════════════════════════════
# FAQ ROUTES
# ═════════════════════════════════════════════════════════════


@router.get("/faqs", response_model=FaqsResponse)
@router.post("/faqs", response_model=FaqsResponse)
async def get_faqs(request: Request) -> JSONResponse:
    principal = optional_access(request)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    def call(session: Session) -> list[FaqItem]:
        service = BlogService(session, settings)
        is_admin = service.is_admin_actor(principal.user_id if principal else None)
        return service.list_faqs(is_admin=is_admin)

    result = await run_in_threadpool(with_session, database, call)
    return json_response(FaqsResponse(status=200, faqs=result), 200)


@router.post("/create_faq", response_model=FaqMutationResponse | StatusResponse)
async def create_faq(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    question = str(payload.get("question") or "").strip()
    answer = str(payload.get("answer") or "").strip()
    sort_order_raw = payload.get("sort_order")
    sort_order = (
        int(sort_order_raw)
        if sort_order_raw is not None and str(sort_order_raw).isdigit()
        else None
    )
    is_published = str(payload.get("is_published") or "1")

    if not question or not answer:
        return json_response(
            StatusResponse(status=400, message="question and answer are required"), 400
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> FaqItem:
            return BlogService(session, settings).create_faq(
                principal.user_id,
                question,
                answer,
                sort_order=sort_order,
                is_published=is_published,
            )

        faq = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        FaqMutationResponse(status=200, message="FAQ created successfully", faq=faq),
        200,
    )


@router.post("/update_faq", response_model=FaqMutationResponse | StatusResponse)
async def update_faq(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    faq_id_raw = payload.get("id")
    if not faq_id_raw or not str(faq_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    faq_id = int(faq_id_raw)
    question = str(payload["question"]).strip() if "question" in payload else None
    answer = str(payload["answer"]).strip() if "answer" in payload else None
    sort_order_raw = payload.get("sort_order")
    sort_order = (
        int(sort_order_raw)
        if sort_order_raw is not None and str(sort_order_raw).isdigit()
        else None
    )
    is_published = str(payload["is_published"]) if "is_published" in payload else None

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> FaqItem:
            return BlogService(session, settings).update_faq(
                principal.user_id,
                faq_id,
                question=question,
                answer=answer,
                sort_order=sort_order,
                is_published=is_published,
            )

        faq = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        FaqMutationResponse(status=200, message="FAQ updated successfully", faq=faq),
        200,
    )


@router.post("/reorder_faqs", response_model=ReorderFaqsResponse | StatusResponse)
async def reorder_faqs(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    faqs_raw = payload.get("faqs")
    if not isinstance(faqs_raw, list) or not faqs_raw:
        return json_response(StatusResponse(status=400, message="faqs array is required"), 400)

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> list[FaqItem]:
            return BlogService(session, settings).reorder_faqs(principal.user_id, faqs_raw)

        faqs = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        ReorderFaqsResponse(status=200, message="FAQs reordered successfully", faqs=faqs),
        200,
    )


@router.post("/delete_faq", response_model=BlogMessageResponse | StatusResponse)
async def delete_faq(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    faq_id_raw = payload.get("id")
    if not faq_id_raw or not str(faq_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    faq_id = int(faq_id_raw)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> None:
            BlogService(session, settings).delete_faq(principal.user_id, faq_id)

        await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(BlogMessageResponse(status=200, message="FAQ deleted successfully"), 200)


# ═════════════════════════════════════════════════════════════
# BLOG CATEGORY ROUTES
# ═════════════════════════════════════════════════════════════


@router.get("/blog_categories", response_model=BlogCategoriesResponse)
@router.post("/blog_categories", response_model=BlogCategoriesResponse)
async def get_blog_categories(request: Request) -> JSONResponse:
    principal = optional_access(request)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    def call(session: Session) -> list[BlogCategoryItem]:
        service = BlogService(session, settings)
        is_admin = service.is_admin_actor(principal.user_id if principal else None)
        return service.list_categories(is_admin=is_admin)

    categories = await run_in_threadpool(with_session, database, call)
    return json_response(BlogCategoriesResponse(status=200, categories=categories), 200)


@router.post("/create_blog_category", response_model=BlogCategoryMutationResponse | StatusResponse)
async def create_blog_category(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    name = str(payload.get("name") or "").strip()
    slug = str(payload.get("slug") or "").strip() or None
    is_active_raw = payload.get("is_active", 1)
    is_active = int(is_active_raw) if str(is_active_raw).isdigit() else 1
    sort_order_raw = payload.get("sort_order")
    sort_order = (
        int(sort_order_raw)
        if sort_order_raw is not None and str(sort_order_raw).isdigit()
        else None
    )

    if not name:
        return json_response(StatusResponse(status=400, message="name is required"), 400)

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> BlogCategoryItem:
            return BlogService(session, settings).create_category(
                principal.user_id, name, slug=slug, is_active=is_active, sort_order=sort_order
            )

        category = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        BlogCategoryMutationResponse(
            status=200, message="Category created successfully", category=category
        ),
        200,
    )


@router.post("/update_blog_category", response_model=BlogCategoryMutationResponse | StatusResponse)
async def update_blog_category(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    cat_id_raw = payload.get("id")
    if not cat_id_raw or not str(cat_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    cat_id = int(cat_id_raw)
    name = str(payload["name"]).strip() if "name" in payload else None
    slug = str(payload["slug"]).strip() if "slug" in payload else None
    is_active_raw = payload.get("is_active")
    is_active = (
        int(is_active_raw) if is_active_raw is not None and str(is_active_raw).isdigit() else None
    )
    sort_order_raw = payload.get("sort_order")
    sort_order = (
        int(sort_order_raw)
        if sort_order_raw is not None and str(sort_order_raw).isdigit()
        else None
    )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> BlogCategoryItem:
            return BlogService(session, settings).update_category(
                principal.user_id,
                cat_id,
                name=name,
                slug=slug,
                is_active=is_active,
                sort_order=sort_order,
            )

        category = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        BlogCategoryMutationResponse(
            status=200, message="Category updated successfully", category=category
        ),
        200,
    )


@router.post("/delete_blog_category", response_model=DeleteBlogCategoryResponse | StatusResponse)
async def delete_blog_category(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    cat_id_raw = payload.get("id")
    if not cat_id_raw or not str(cat_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    cat_id = int(cat_id_raw)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> list[BlogCategoryItem]:
            return BlogService(session, settings).delete_category(principal.user_id, cat_id)

        categories = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        DeleteBlogCategoryResponse(
            status=200, message="Category deleted successfully", categories=categories
        ),
        200,
    )


@router.post("/reorder_categories", response_model=ReorderCategoriesResponse | StatusResponse)
async def reorder_categories(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    cats_raw = payload.get("categories")
    if not isinstance(cats_raw, list) or not cats_raw:
        return json_response(
            StatusResponse(status=400, message="categories array is required"), 400
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> list[BlogCategoryItem]:
            return BlogService(session, settings).reorder_categories(principal.user_id, cats_raw)

        categories = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        ReorderCategoriesResponse(
            status=200, message="Categories reordered successfully", categories=categories
        ),
        200,
    )


# ═════════════════════════════════════════════════════════════
# BLOG POST ROUTES
# ═════════════════════════════════════════════════════════════


@router.get("/blog_posts", response_model=BlogPostsListResponse)
@router.post("/blog_posts", response_model=BlogPostsListResponse)
async def get_blog_posts(
    request: Request,
    status: str | None = None,
    category: int | None = None,
    search: str | None = None,
    page: int = 1,
    limit: int = 10,
) -> JSONResponse:
    payload = await body_mapping(request) if request.method == "POST" else {}
    post_status = payload.get("status") or status
    post_category = payload.get("category") or category
    post_search = payload.get("search") or search
    post_page = int(payload.get("page") or page or 1)
    post_limit = int(payload.get("limit") or limit or 10)

    filters = BlogPostsFilters(
        status=str(post_status) if post_status else "published",
        category=int(post_category) if post_category and str(post_category).isdigit() else None,
        search=str(post_search).strip() if post_search else None,
        page=max(1, post_page),
        limit=min(100, max(1, post_limit)),
    )

    principal = optional_access(request)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    def call(session: Session) -> tuple[list[BlogPostSummaryItem], Any]:
        service = BlogService(session, settings)
        is_admin = service.is_admin_actor(principal.user_id if principal else None)
        if not is_admin and (not post_status or post_status == "all"):
            filters.status = "published"
        return service.list_posts(filters, is_admin=is_admin)

    posts, pagination = await run_in_threadpool(with_session, database, call)
    return json_response(BlogPostsListResponse(status=200, posts=posts, pagination=pagination), 200)


@router.get(
    "/blog_post_detail/{id_or_slug}", response_model=BlogPostDetailResponse | StatusResponse
)
@router.post(
    "/blog_post_detail/{id_or_slug}", response_model=BlogPostDetailResponse | StatusResponse
)
async def get_blog_post_detail(id_or_slug: str, request: Request) -> JSONResponse:
    if not id_or_slug.strip():
        return json_response(StatusResponse(status=400, message="id or slug is required"), 400)

    principal = optional_access(request)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> Any:
            service = BlogService(session, settings)
            is_admin = service.is_admin_actor(principal.user_id if principal else None)
            return service.get_post_detail(id_or_slug, is_admin=is_admin)

        post = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(BlogPostDetailResponse(status=200, post=post), 200)


@router.post("/create_blog_post", response_model=BlogPostMutationResponse | StatusResponse)
async def create_blog_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    content_type = request.headers.get("content-type", "").lower()
    payload: dict[str, Any] = {}
    images: list[tuple[str | None, bytes]] = []

    if "application/json" in content_type:
        payload = await body_mapping(request)
    else:
        form = await request.form()
        for key, value in form.multi_items():
            if key in {"images", "images[]", "image"} and isinstance(value, UploadFile):
                if value.filename:
                    content = await value.read(5 * 1024 * 1024 + 1)
                    images.append((value.filename, content))
                await value.close()
            elif isinstance(value, str):
                payload[key] = value

    title = str(payload.get("title") or "").strip()
    category_id_raw = payload.get("category_id")
    category_id = int(category_id_raw) if category_id_raw and str(category_id_raw).isdigit() else 0
    excerpt = str(payload.get("excerpt") or "").strip()
    status = str(payload.get("status") or "draft").strip()
    main_image_index_raw = payload.get("main_image_index")
    main_image_index = (
        int(main_image_index_raw)
        if main_image_index_raw is not None and str(main_image_index_raw).isdigit()
        else None
    )

    sections = await _parse_sections(payload.get("sections"))

    if not title or not category_id or not excerpt:
        return json_response(
            StatusResponse(status=400, message="title, category_id, and excerpt are required"),
            400,
        )

    if not sections:
        return json_response(
            StatusResponse(status=400, message="At least one section is required"),
            400,
        )

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> Any:
            return BlogService(session, settings).create_post(
                principal.user_id,
                title=title,
                category_id=category_id,
                excerpt=excerpt,
                status=status,
                sections=sections,
                uploaded_images=images if images else None,
                main_image_index=main_image_index,
            )

        post = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        BlogPostMutationResponse(status=200, message="Blog post created successfully", post=post),
        200,
    )


@router.post("/update_blog_post", response_model=BlogPostMutationResponse | StatusResponse)
async def update_blog_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    content_type = request.headers.get("content-type", "").lower()
    payload: dict[str, Any] = {}
    images: list[tuple[str | None, bytes]] = []

    if "application/json" in content_type:
        payload = await body_mapping(request)
    else:
        form = await request.form()
        for key, value in form.multi_items():
            if key in {"images", "images[]", "image"} and isinstance(value, UploadFile):
                if value.filename:
                    content = await value.read(5 * 1024 * 1024 + 1)
                    images.append((value.filename, content))
                await value.close()
            elif isinstance(value, str):
                payload[key] = value

    post_id_raw = payload.get("id")
    if not post_id_raw or not str(post_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    post_id = int(post_id_raw)
    title = str(payload["title"]).strip() if "title" in payload else None
    category_id_raw = payload.get("category_id")
    category_id = (
        int(category_id_raw) if category_id_raw and str(category_id_raw).isdigit() else None
    )
    excerpt = str(payload["excerpt"]).strip() if "excerpt" in payload else None
    status = str(payload["status"]).strip() if "status" in payload else None
    main_image_url = str(payload["main_image_url"]).strip() if "main_image_url" in payload else None
    main_image_index_raw = payload.get("main_image_index")
    main_image_index = (
        int(main_image_index_raw)
        if main_image_index_raw is not None and str(main_image_index_raw).isdigit()
        else None
    )

    sections = await _parse_sections(payload["sections"]) if "sections" in payload else None

    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> Any:
            return BlogService(session, settings).update_post(
                principal.user_id,
                post_id=post_id,
                title=title,
                category_id=category_id,
                excerpt=excerpt,
                status=status,
                sections=sections,
                uploaded_images=images if images else None,
                main_image_url=main_image_url,
                main_image_index=main_image_index,
            )

        post = await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        BlogPostMutationResponse(status=200, message="Blog post updated successfully", post=post),
        200,
    )


@router.post("/delete_blog_post", response_model=BlogMessageResponse | StatusResponse)
async def delete_blog_post(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> JSONResponse:
    payload = await body_mapping(request)
    post_id_raw = payload.get("id")
    if not post_id_raw or not str(post_id_raw).isdigit():
        return json_response(StatusResponse(status=400, message="id is required"), 400)

    post_id = int(post_id_raw)
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    try:

        def call(session: Session) -> None:
            BlogService(session, settings).delete_post(principal.user_id, post_id)

        await run_in_threadpool(with_session, database, call)
    except BlogError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message), exc.http_status
        )

    return json_response(
        BlogMessageResponse(status=200, message="Blog post deleted successfully"), 200
    )
