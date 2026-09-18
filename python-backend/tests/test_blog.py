"""Contract, schema, and storage tests for the Blog family routes."""

from __future__ import annotations

import json
import os
import time
import uuid
from collections.abc import Iterator
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pytest
from fastapi.testclient import TestClient
from PIL import Image
from sqlalchemy import create_engine, delete, insert

from app.core.config import Settings
from app.core.security import TokenService
from app.integrations.uploads import (
    BlogGalleryStorage,
    CarouselStorage,
    prepare_avatar,
)
from app.main import create_app
from app.models.generated import (
    BlogCategories,
    BlogGallery,
    BlogPosts,
    BlogSections,
    Faqs,
    Homepage,
    HomepageCarousel,
    Users,
)
from app.repositories.blog import BlogRepository
from app.schemas.blog import (
    BlogCategoryItem,
    BlogGalleryImageItem,
    BlogPostDetailItem,
    BlogPostSummaryItem,
    BlogSectionItem,
    FaqItem,
    HomepageCarouselImageItem,
    HomepageData,
    HomepageResponse,
)


def _create_sample_png() -> bytes:
    """Generate a minimal 10x10 PNG in memory."""
    from io import BytesIO

    buf = BytesIO()
    img = Image.new("RGB", (10, 10), color="blue")
    img.save(buf, format="PNG")
    return buf.getvalue()


def test_blog_api_openapi_route_registration(auth_settings: Settings) -> None:
    """Verify all 21 blog_api routes are registered in OpenAPI with correct methods."""
    app = create_app(auth_settings)
    openapi = app.openapi()
    paths = openapi["paths"]

    # 1. Homepage & Carousel (6 routes)
    assert "/blog_api/homepage" in paths
    assert "get" in paths["/blog_api/homepage"]
    assert "post" in paths["/blog_api/homepage"]
    assert "/blog_api/update_homepage_text" in paths
    assert "post" in paths["/blog_api/update_homepage_text"]
    assert "/blog_api/create_carousel_image" in paths
    assert "post" in paths["/blog_api/create_carousel_image"]
    assert "/blog_api/update_carousel_image" in paths
    assert "post" in paths["/blog_api/update_carousel_image"]
    assert "/blog_api/reorder_carousel" in paths
    assert "post" in paths["/blog_api/reorder_carousel"]
    assert "/blog_api/delete_carousel_image" in paths
    assert "post" in paths["/blog_api/delete_carousel_image"]

    # 2. FAQs (5 routes)
    assert "/blog_api/faqs" in paths
    assert "get" in paths["/blog_api/faqs"]
    assert "post" in paths["/blog_api/faqs"]
    assert "/blog_api/create_faq" in paths
    assert "post" in paths["/blog_api/create_faq"]
    assert "/blog_api/update_faq" in paths
    assert "post" in paths["/blog_api/update_faq"]
    assert "/blog_api/reorder_faqs" in paths
    assert "post" in paths["/blog_api/reorder_faqs"]
    assert "/blog_api/delete_faq" in paths
    assert "post" in paths["/blog_api/delete_faq"]

    # 3. Categories (5 routes)
    assert "/blog_api/blog_categories" in paths
    assert "get" in paths["/blog_api/blog_categories"]
    assert "post" in paths["/blog_api/blog_categories"]
    assert "/blog_api/create_blog_category" in paths
    assert "post" in paths["/blog_api/create_blog_category"]
    assert "/blog_api/update_blog_category" in paths
    assert "post" in paths["/blog_api/update_blog_category"]
    assert "/blog_api/delete_blog_category" in paths
    assert "post" in paths["/blog_api/delete_blog_category"]
    assert "/blog_api/reorder_categories" in paths
    assert "post" in paths["/blog_api/reorder_categories"]

    # 4. Posts (5 routes)
    assert "/blog_api/blog_posts" in paths
    assert "get" in paths["/blog_api/blog_posts"]
    assert "post" in paths["/blog_api/blog_posts"]
    assert "/blog_api/blog_post_detail/{id_or_slug}" in paths
    assert "get" in paths["/blog_api/blog_post_detail/{id_or_slug}"]
    assert "post" in paths["/blog_api/blog_post_detail/{id_or_slug}"]
    assert "/blog_api/create_blog_post" in paths
    assert "post" in paths["/blog_api/create_blog_post"]
    assert "/blog_api/update_blog_post" in paths
    assert "post" in paths["/blog_api/update_blog_post"]
    assert "/blog_api/delete_blog_post" in paths
    assert "post" in paths["/blog_api/delete_blog_post"]


def test_blog_mutations_require_authentication(auth_settings: Settings) -> None:
    """Unauthenticated calls to modifying endpoints must fail with 401 before touching database."""
    app = create_app(auth_settings)
    with TestClient(app) as client:
        # Homepage & Carousel
        assert client.post("/blog_api/update_homepage_text", json={}).status_code == 401
        assert client.post("/blog_api/create_carousel_image", data={}).status_code == 401
        assert client.post("/blog_api/update_carousel_image", json={}).status_code == 401
        assert client.post("/blog_api/reorder_carousel", json={}).status_code == 401
        assert client.post("/blog_api/delete_carousel_image", json={}).status_code == 401

        # FAQs
        assert client.post("/blog_api/create_faq", json={}).status_code == 401
        assert client.post("/blog_api/update_faq", json={}).status_code == 401
        assert client.post("/blog_api/reorder_faqs", json={}).status_code == 401
        assert client.post("/blog_api/delete_faq", json={}).status_code == 401

        # Categories
        assert client.post("/blog_api/create_blog_category", json={}).status_code == 401
        assert client.post("/blog_api/update_blog_category", json={}).status_code == 401
        assert client.post("/blog_api/delete_blog_category", json={}).status_code == 401
        assert client.post("/blog_api/reorder_categories", json={}).status_code == 401

        # Posts
        assert client.post("/blog_api/create_blog_post", data={}).status_code == 401
        assert client.post("/blog_api/update_blog_post", data={}).status_code == 401
        assert client.post("/blog_api/delete_blog_post", json={}).status_code == 401


def test_blog_mutations_reject_empty_or_malformed_inputs(auth_settings: Settings) -> None:
    """Authenticated calls with missing required fields return 400."""
    token = (
        TokenService(auth_settings)
        .issue({"id": 1, "email": "admin@example.com", "user_role": "admin"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {token}"}
    app = create_app(auth_settings)

    with TestClient(app) as client:
        # update_homepage_text missing fields
        res = client.post("/blog_api/update_homepage_text", headers=headers, json={})
        assert res.status_code == 400
        assert "required" in res.json()["message"]

        # create_carousel_image missing image
        res = client.post("/blog_api/create_carousel_image", headers=headers, data={})
        assert res.status_code == 400

        # update_carousel_image missing id
        res = client.post("/blog_api/update_carousel_image", headers=headers, json={})
        assert res.status_code == 400

        # reorder_carousel missing images
        res = client.post("/blog_api/reorder_carousel", headers=headers, json={})
        assert res.status_code == 400

        # delete_carousel_image missing id
        res = client.post("/blog_api/delete_carousel_image", headers=headers, json={})
        assert res.status_code == 400

        # create_faq missing question/answer
        res = client.post("/blog_api/create_faq", headers=headers, json={})
        assert res.status_code == 400

        # update_faq missing id
        res = client.post("/blog_api/update_faq", headers=headers, json={})
        assert res.status_code == 400

        # reorder_faqs missing faqs
        res = client.post("/blog_api/reorder_faqs", headers=headers, json={})
        assert res.status_code == 400

        # delete_faq missing id
        res = client.post("/blog_api/delete_faq", headers=headers, json={})
        assert res.status_code == 400

        # create_blog_category missing name
        res = client.post("/blog_api/create_blog_category", headers=headers, json={})
        assert res.status_code == 400

        # update_blog_category missing id
        res = client.post("/blog_api/update_blog_category", headers=headers, json={})
        assert res.status_code == 400

        # delete_blog_category missing id
        res = client.post("/blog_api/delete_blog_category", headers=headers, json={})
        assert res.status_code == 400

        # reorder_categories missing categories
        res = client.post("/blog_api/reorder_categories", headers=headers, json={})
        assert res.status_code == 400

        # create_blog_post missing required fields
        res = client.post("/blog_api/create_blog_post", headers=headers, data={})
        assert res.status_code == 400

        # update_blog_post missing id
        res = client.post("/blog_api/update_blog_post", headers=headers, data={})
        assert res.status_code == 400

        # delete_blog_post missing id
        res = client.post("/blog_api/delete_blog_post", headers=headers, json={})
        assert res.status_code == 400


def test_carousel_and_blog_gallery_storage(tmp_path: Path) -> None:
    """Validate CarouselStorage and BlogGalleryStorage save, read, and delete securely."""
    png_bytes = _create_sample_png()
    prepared = prepare_avatar("test.png", png_bytes)

    # 1. CarouselStorage
    carousel_storage = CarouselStorage(tmp_path)
    stored_carousel = carousel_storage.save(user_id=42, avatar=prepared)
    assert stored_carousel.relative_path.startswith("uploads/homepage/carousel/")
    assert stored_carousel.filename.endswith(".png")

    # Verify file physically exists on disk
    expected_path = tmp_path / "homepage" / "carousel" / stored_carousel.filename
    assert expected_path.is_file()

    # Delete
    carousel_storage.delete(stored_carousel)
    assert not expected_path.exists()

    # 2. BlogGalleryStorage
    gallery_storage = BlogGalleryStorage(tmp_path)
    stored_gallery = gallery_storage.save(user_id=42, avatar=prepared)
    assert stored_gallery.relative_path.startswith("uploads/blog/gallery/")
    assert stored_gallery.filename.endswith(".png")

    expected_gallery_path = tmp_path / "blog" / "gallery" / stored_gallery.filename
    assert expected_gallery_path.is_file()

    gallery_storage.delete(stored_gallery)
    assert not expected_gallery_path.exists()


def test_calculate_read_time() -> None:
    """Test read time computation based on ~200 wpm."""
    # Under 200 words -> 1 minute minimum
    short_sections = [{"heading": "Short", "body": "<p>Hello world from our alumni portal.</p>"}]
    assert BlogRepository.calculate_read_time(short_sections) == 1

    # 450 words -> ceil(450 / 200) = 3 minutes
    words = "word " * 450
    long_sections = [{"heading": "Long", "body": f"<div>{words}</div>"}]
    assert BlogRepository.calculate_read_time(long_sections) == 3

    # Empty sections -> 1 minute
    assert BlogRepository.calculate_read_time([]) == 1


def test_blog_schemas_validation() -> None:
    """Test schema serialization and compatibility."""
    item = HomepageCarouselImageItem(
        id=1,
        image_url="uploads/homepage/carousel/1_abc_carousel.png",
        file_name="1_abc_carousel.png",
        alt_text="Hero Banner",
        sort_order=0,
        is_hidden="0",
        show_greeting="1",
    )
    assert item.show_greeting == "1"
    assert item.is_hidden == "0"

    data = HomepageData(
        greeting_title="Welcome Alumni",
        greeting_message="Stay connected",
        greeting_image_id=1,
        carousel_images=[item],
    )
    resp = HomepageResponse(status=200, homepage=data)
    assert resp.status == 200
    assert resp.homepage.greeting_image_id == 1

    faq = FaqItem(
        id=5,
        question="How to join?",
        answer="Register online",
        sort_order=0,
        is_published="1",
    )
    assert faq.is_published == "1"

    category = BlogCategoryItem(
        id=10,
        name="General",
        slug="general",
        sort_order=0,
        is_active=1,
    )
    assert category.name == "General"

    post_summary = BlogPostSummaryItem(
        id=100,
        slug="welcome-post",
        title="Welcome",
        excerpt="An excerpt",
        status="published",
        read_time_minutes=2,
    )
    detail = BlogPostDetailItem(
        **post_summary.model_dump(),
        sections=[BlogSectionItem(id=1, post_id=100, heading="H1", body="Content", sort_order=0)],
        gallery_images=[
            BlogGalleryImageItem(
                id=1, post_id=100, image_url="uploads/blog/gallery/img.png", sort_order=0
            )
        ],
    )
    assert len(detail.sections) == 1
    assert len(detail.gallery_images) == 1


# ═════════════════════════════════════════════════════════════
# DATABASE INTEGRATION TESTS (MARIADB)
# ═════════════════════════════════════════════════════════════


def _create_test_user(
    engine: Any,
    *,
    user_role: str = "admin",
    active: int = 1,
) -> tuple[int, str]:
    """Insert a synthetic user for integration testing."""
    unique = uuid.uuid4().hex[:8]
    email = f"blog-{user_role}-{unique}@example.com"
    with engine.begin() as conn:
        result = conn.execute(
            insert(Users).values(
                chapter_id=1,
                ip_address="127.0.0.1",
                username=email,
                email=email,
                password="test_password_hash",  # noqa: S106
                has_password=1,
                onboarding_completion=1,
                nick_name=f"BlogTester{unique}",
                state="Lagos",
                country="Nigeria",
                created_on=int(time.time()),
                userAccessCode=f"BLG-{unique}",
                profile_status="active",
                voucher="",
                resetKey="",
                first_name="Blog",
                last_name="Tester",
                fullname=f"Blog Tester {user_role.capitalize()}",
                phone="+2348000000000",
                city="Lagos",
                active=active,
                user_role=user_role,
                is_approved=1,
                email_verified=1,
            )
        )
        user_id = int(result.inserted_primary_key[0])
    return user_id, email


@dataclass
class BlogHarness:
    """Encapsulates test client, authenticated actors, settings, and database engine."""

    client: TestClient
    admin_headers: dict[str, str]
    admin_user_id: int
    member_headers: dict[str, str]
    member_user_id: int
    settings: Settings
    upload_root: Path
    engine: Any


@pytest.fixture
def blog_harness(rsa_pem_pair: tuple[str, str], tmp_path: Path) -> Iterator[BlogHarness]:
    """Provide an isolated test client with admin and member users against MariaDB."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")

    private_pem, public_pem = rsa_pem_pair
    upload_root = tmp_path / "uploads"
    upload_root.mkdir(parents=True, exist_ok=True)

    settings = Settings(
        environment="test",
        database_url=database_url,
        jwt_signing_key=private_pem,
        jwt_verification_key=public_pem,
        public_base_url="https://alumni.example.test/",
        frontend_base_url="https://frontend.example.test/",
        upload_root=upload_root,
    )
    engine = create_engine(database_url, pool_pre_ping=True)

    admin_user_id, admin_email = _create_test_user(engine, user_role="admin")
    member_user_id, member_email = _create_test_user(engine, user_role="alumni")

    admin_token = (
        TokenService(settings)
        .issue(
            {
                "id": admin_user_id,
                "email": admin_email,
                "user_role": "admin",
                "fullname": "Blog Admin",
            }
        )
        .access_token
    )
    member_token = (
        TokenService(settings)
        .issue(
            {
                "id": member_user_id,
                "email": member_email,
                "user_role": "alumni",
                "fullname": "Blog Member",
            }
        )
        .access_token
    )

    app = create_app(settings)
    with TestClient(app) as client:
        harness = BlogHarness(
            client=client,
            admin_headers={"Authorization": f"Bearer {admin_token}"},
            admin_user_id=admin_user_id,
            member_headers={"Authorization": f"Bearer {member_token}"},
            member_user_id=member_user_id,
            settings=settings,
            upload_root=upload_root,
            engine=engine,
        )
        try:
            yield harness
        finally:
            with engine.begin() as conn:
                conn.execute(delete(BlogGallery))
                conn.execute(delete(BlogSections))
                conn.execute(delete(BlogPosts))
                conn.execute(delete(BlogCategories))
                conn.execute(delete(Faqs))
                conn.execute(delete(HomepageCarousel))
                conn.execute(delete(Homepage))
                conn.execute(delete(Users).where(Users.id.in_([admin_user_id, member_user_id])))
    engine.dispose()


def test_blog_homepage_and_carousel_db(blog_harness: BlogHarness) -> None:
    """Test full lifecycle of homepage text and carousel management in MariaDB."""
    client = blog_harness.client

    # 1. Initial public read
    resp = client.get("/blog_api/homepage")
    assert resp.status_code == 200
    home_data = resp.json()["homepage"]
    assert home_data["greeting_title"] == ""
    assert home_data["carousel_images"] == []

    # 2. Non-admin cannot update homepage text
    resp = client.post(
        "/blog_api/update_homepage_text",
        headers=blog_harness.member_headers,
        json={"greeting_title": "Hacked Title", "greeting_message": "Hacked Message"},
    )
    assert resp.status_code == 403

    # 3. Admin updates homepage text
    resp = client.post(
        "/blog_api/update_homepage_text",
        headers=blog_harness.admin_headers,
        json={
            "greeting_title": "Welcome Home Alumni",
            "greeting_message": "Stay connected with your peers",
        },
    )
    assert resp.status_code == 200
    assert resp.json()["message"] == "Homepage text updated successfully"

    # 4. Public read reflects updated text (both GET and POST supported)
    for method in [client.get, client.post]:
        resp = method("/blog_api/homepage")
        assert resp.status_code == 200
        data = resp.json()["homepage"]
        assert data["greeting_title"] == "Welcome Home Alumni"
        assert data["greeting_message"] == "Stay connected with your peers"

    # 5. Admin uploads carousel image 1 (with show_greeting=1)
    png_bytes = _create_sample_png()
    resp = client.post(
        "/blog_api/create_carousel_image",
        headers=blog_harness.admin_headers,
        data={"alt_text": "Main Campus Gate", "show_greeting": "1", "sort_order": "0"},
        files={"image": ("gate.png", png_bytes, "image/png")},
    )
    assert resp.status_code == 200
    img1 = resp.json()["image"]
    img1_id = img1["id"]
    assert img1["alt_text"] == "Main Campus Gate"
    assert resp.json()["greeting_image_id"] == img1_id

    # Verify image physically saved on disk
    expected_file = blog_harness.upload_root / "homepage" / "carousel" / img1["file_name"]
    assert expected_file.is_file()

    # 6. Admin uploads carousel image 2 (with show_greeting=0)
    resp2 = client.post(
        "/blog_api/create_carousel_image",
        headers=blog_harness.admin_headers,
        data={"alt_text": "University Library", "show_greeting": "0", "sort_order": "1"},
        files={"image": ("library.png", png_bytes, "image/png")},
    )
    assert resp2.status_code == 200
    img2 = resp2.json()["image"]
    img2_id = img2["id"]
    # greeting_image_id remains img1_id
    assert resp2.json()["greeting_image_id"] == img1_id

    # 7. Public read returns both images
    resp = client.get("/blog_api/homepage")
    assert resp.status_code == 200
    images = resp.json()["homepage"]["carousel_images"]
    assert len(images) == 2

    # 8. Admin updates image 2 to show greeting
    resp_up = client.post(
        "/blog_api/update_carousel_image",
        headers=blog_harness.admin_headers,
        json={"id": img2_id, "show_greeting": "1", "alt_text": "Library Central"},
    )
    assert resp_up.status_code == 200
    assert resp_up.json()["greeting_image_id"] == img2_id

    # 9. Admin reorders carousel images
    resp_reorder = client.post(
        "/blog_api/reorder_carousel",
        headers=blog_harness.admin_headers,
        json={"images": [{"id": img2_id, "sort_order": 0}, {"id": img1_id, "sort_order": 1}]},
    )
    assert resp_reorder.status_code == 200
    assert resp_reorder.json()["carousel_images"][0]["id"] == img2_id
    assert resp_reorder.json()["carousel_images"][1]["id"] == img1_id

    # 10. Hide image 2 (the current greeting image) -> automatically reassigns greeting to img1!
    resp_hide = client.post(
        "/blog_api/update_carousel_image",
        headers=blog_harness.admin_headers,
        json={"id": img2_id, "is_hidden": "1"},
    )
    assert resp_hide.status_code == 200
    assert resp_hide.json()["greeting_image_id"] == img1_id

    # 11. Delete image 1 (soft delete)
    resp_del = client.post(
        "/blog_api/delete_carousel_image",
        headers=blog_harness.admin_headers,
        json={"id": img1_id},
    )
    assert resp_del.status_code == 200
    remaining_ids = [img["id"] for img in resp_del.json()["carousel_images"]]
    assert img1_id not in remaining_ids
    # Public homepage no longer includes deleted img1
    home_after = client.get("/blog_api/homepage").json()["homepage"]
    assert img1_id not in [img["id"] for img in home_after["carousel_images"]]


def test_blog_faqs_db(blog_harness: BlogHarness) -> None:
    """Test FAQs creation, filtering, ordering, update, and soft-delete in MariaDB."""
    client = blog_harness.client

    # 1. Non-admin cannot create FAQ
    resp = client.post(
        "/blog_api/create_faq",
        headers=blog_harness.member_headers,
        json={"question": "Unauthorized?", "answer": "No", "is_published": "1"},
    )
    assert resp.status_code == 403

    # 2. Admin creates published FAQ 1
    resp1 = client.post(
        "/blog_api/create_faq",
        headers=blog_harness.admin_headers,
        json={
            "question": "How do I join a chapter?",
            "answer": "Select a chapter on signup.",
            "is_published": "1",
            "sort_order": 1,
        },
    )
    assert resp1.status_code == 200
    faq1_id = resp1.json()["faq"]["id"]

    # 3. Admin creates draft/unpublished FAQ 2
    resp2 = client.post(
        "/blog_api/create_faq",
        headers=blog_harness.admin_headers,
        json={
            "question": "Draft Question?",
            "answer": "Draft Answer.",
            "is_published": "0",
            "sort_order": 0,
        },
    )
    assert resp2.status_code == 200
    faq2_id = resp2.json()["faq"]["id"]

    # 4. Public GET returns only published FAQ 1
    resp = client.get("/blog_api/faqs")
    assert resp.status_code == 200
    faqs = resp.json()["faqs"]
    assert len(faqs) == 1
    assert faqs[0]["id"] == faq1_id

    # 5. Non-admin GET with ?all=true still gets only published FAQs
    resp = client.get("/blog_api/faqs?all=true", headers=blog_harness.member_headers)
    assert resp.status_code == 200
    assert len(resp.json()["faqs"]) == 1

    # 6. Admin GET with ?all=true gets all FAQs (both published and draft), ordered by sort_order
    resp = client.get("/blog_api/faqs?all=true", headers=blog_harness.admin_headers)
    assert resp.status_code == 200
    all_faqs = resp.json()["faqs"]
    assert len(all_faqs) == 2
    assert all_faqs[0]["id"] == faq2_id  # sort_order 0
    assert all_faqs[1]["id"] == faq1_id  # sort_order 1

    # 7. Admin reorders FAQs
    resp = client.post(
        "/blog_api/reorder_faqs",
        headers=blog_harness.admin_headers,
        json={"faqs": [{"id": faq1_id, "sort_order": 0}, {"id": faq2_id, "sort_order": 1}]},
    )
    assert resp.status_code == 200
    assert resp.json()["faqs"][0]["id"] == faq1_id

    # 8. Admin updates FAQ 2 to published
    resp = client.post(
        "/blog_api/update_faq",
        headers=blog_harness.admin_headers,
        json={"id": faq2_id, "is_published": "1", "question": "Now Published Question?"},
    )
    assert resp.status_code == 200

    # Public GET now includes both FAQs
    resp = client.get("/blog_api/faqs")
    assert resp.status_code == 200
    assert len(resp.json()["faqs"]) == 2

    # 9. Admin deletes FAQ 1 (soft delete)
    resp = client.post(
        "/blog_api/delete_faq",
        headers=blog_harness.admin_headers,
        json={"id": faq1_id},
    )
    assert resp.status_code == 200

    # Public GET now only has FAQ 2
    resp = client.get("/blog_api/faqs")
    assert resp.status_code == 200
    remaining = resp.json()["faqs"]
    assert len(remaining) == 1
    assert remaining[0]["id"] == faq2_id


def test_blog_categories_db(blog_harness: BlogHarness) -> None:
    """Test category creation, unique slugs, reordering, and soft-delete revival in MariaDB."""
    client = blog_harness.client

    # 1. Non-admin cannot create category
    resp = client.post(
        "/blog_api/create_blog_category",
        headers=blog_harness.member_headers,
        json={"name": "Forbidden Category"},
    )
    assert resp.status_code == 403

    # 2. Admin creates category 1
    resp1 = client.post(
        "/blog_api/create_blog_category",
        headers=blog_harness.admin_headers,
        json={"name": "Campus News"},
    )
    assert resp1.status_code == 200
    cat1 = resp1.json()["category"]
    assert cat1["slug"] == "campus-news"
    cat1_id = cat1["id"]

    # 3. Public GET returns category
    resp = client.get("/blog_api/blog_categories")
    assert resp.status_code == 200
    categories = resp.json()["categories"]
    assert len(categories) == 1
    assert categories[0]["slug"] == "campus-news"

    # 4. Admin creates category 2
    resp2 = client.post(
        "/blog_api/create_blog_category",
        headers=blog_harness.admin_headers,
        json={"name": "Alumni Achievements"},
    )
    assert resp2.status_code == 200
    cat2_id = resp2.json()["category"]["id"]

    # 5. Admin updates category 1
    resp = client.post(
        "/blog_api/update_blog_category",
        headers=blog_harness.admin_headers,
        json={"id": cat1_id, "name": "Campus Bulletins"},
    )
    assert resp.status_code == 200
    assert resp.json()["category"]["name"] == "Campus Bulletins"

    # 6. Admin reorders categories
    resp = client.post(
        "/blog_api/reorder_categories",
        headers=blog_harness.admin_headers,
        json={"categories": [{"id": cat2_id, "sort_order": 0}, {"id": cat1_id, "sort_order": 1}]},
    )
    assert resp.status_code == 200
    assert resp.json()["categories"][0]["id"] == cat2_id

    # 7. Admin deletes category 1 (soft delete)
    resp = client.post(
        "/blog_api/delete_blog_category",
        headers=blog_harness.admin_headers,
        json={"id": cat1_id},
    )
    assert resp.status_code == 200

    # Public GET now only returns category 2
    resp = client.get("/blog_api/blog_categories")
    assert resp.status_code == 200
    assert len(resp.json()["categories"]) == 1
    assert resp.json()["categories"][0]["id"] == cat2_id

    # 8. Admin re-creates category with same name "Campus Bulletins" -> handled safely without crash
    resp = client.post(
        "/blog_api/create_blog_category",
        headers=blog_harness.admin_headers,
        json={"name": "Campus Bulletins"},
    )
    assert resp.status_code == 200
    assert resp.json()["category"]["is_active"] == 1


def test_blog_posts_db(blog_harness: BlogHarness) -> None:
    """Test blog posts creation, filtering, detail, update, and deletion in MariaDB."""
    client = blog_harness.client

    # 1. Admin creates category
    cat_resp = client.post(
        "/blog_api/create_blog_category",
        headers=blog_harness.admin_headers,
        json={"name": "Events and Spotlights"},
    )
    assert cat_resp.status_code == 200
    cat_id = cat_resp.json()["category"]["id"]

    # 2. Non-admin cannot create post
    resp = client.post(
        "/blog_api/create_blog_post",
        headers=blog_harness.member_headers,
        data={
            "title": "Unauthorized",
            "excerpt": "No",
            "category_id": str(cat_id),
            "sections": json.dumps([{"heading": "Intro", "body": "<p>Content</p>"}]),
        },
    )
    assert resp.status_code == 403

    # 3. Admin creates published blog post with sections & gallery images
    png = _create_sample_png()
    post_resp = client.post(
        "/blog_api/create_blog_post",
        headers=blog_harness.admin_headers,
        data={
            "title": "Alumni Leadership Summit 2026",
            "excerpt": "A recap of our flagship leadership summit in Lagos.",
            "category_id": str(cat_id),
            "status": "published",
            "sections": json.dumps(
                [
                    {
                        "heading": "Keynote Speech",
                        "body": "<p>Opening remarks delivered by notable alumni.</p>",
                        "sort_order": 0,
                    },
                    {
                        "heading": "Breakout Sessions",
                        "body": "<p>Career mentorship and networking workshops.</p>",
                        "sort_order": 1,
                    },
                ]
            ),
            "main_image_index": "0",
        },
        files=[
            ("images", ("keynote.png", png, "image/png")),
            ("images", ("workshop.png", png, "image/png")),
        ],
    )
    assert post_resp.status_code == 200
    post_data = post_resp.json()["post"]
    post_id = post_data["id"]
    assert post_data["slug"] == "alumni-leadership-summit-2026"
    assert post_data["status"] == "published"
    assert post_data["read_time_minutes"] >= 1
    assert len(post_data["sections"]) == 2
    assert len(post_data["gallery_images"]) == 2
    assert post_data["cover_image_url"] is not None

    # 4. Public list query
    list_resp = client.get("/blog_api/blog_posts")
    assert list_resp.status_code == 200
    posts = list_resp.json()["posts"]
    assert len(posts) == 1
    assert list_resp.json()["pagination"]["total"] == 1

    # 6. Filter queries
    # By category
    assert len(client.get(f"/blog_api/blog_posts?category={cat_id}").json()["posts"]) == 1
    assert len(client.get("/blog_api/blog_posts?category=99999").json()["posts"]) == 0
    # By search keyword
    assert len(client.get("/blog_api/blog_posts?search=Summit").json()["posts"]) == 1
    assert len(client.get("/blog_api/blog_posts?search=IrrelevantWord").json()["posts"]) == 0
    # By POST body
    post_search = client.post("/blog_api/blog_posts", json={"search": "Summit"}).json()
    assert len(post_search["posts"]) == 1

    # 7. Detail view: by ID and by Slug (GET and POST)
    for lookup in [str(post_id), "alumni-leadership-summit-2026"]:
        for method in [client.get, client.post]:
            resp = method(f"/blog_api/blog_post_detail/{lookup}")
            assert resp.status_code == 200
            detail = resp.json()["post"]
            assert detail["id"] == post_id
            assert detail["slug"] == "alumni-leadership-summit-2026"
            assert len(detail["sections"]) == 2
            assert len(detail["gallery_images"]) == 2

    # Non-existent slug returns 404
    assert client.get("/blog_api/blog_post_detail/non-existent-slug-12345").status_code == 404

    # 8. Admin updates post
    up_resp = client.post(
        "/blog_api/update_blog_post",
        headers=blog_harness.admin_headers,
        data={
            "id": str(post_id),
            "title": "Alumni Leadership Summit 2026 - Official Highlights",
            "sections": json.dumps(
                [
                    {
                        "heading": "Full Highlights",
                        "body": "<p>A comprehensive summary of the summit.</p>",
                        "sort_order": 0,
                    },
                ]
            ),
        },
    )
    assert up_resp.status_code == 200
    assert up_resp.json()["post"]["title"] == "Alumni Leadership Summit 2026 - Official Highlights"
    assert len(up_resp.json()["post"]["sections"]) == 1

    # 9. Admin creates draft post
    draft_resp = client.post(
        "/blog_api/create_blog_post",
        headers=blog_harness.admin_headers,
        data={
            "title": "Draft Confidential Roadmap",
            "excerpt": "Under embargo.",
            "category_id": str(cat_id),
            "status": "draft",
            "sections": json.dumps(
                [{"heading": "Draft Section", "body": "<p>Content pending.</p>"}]
            ),
        },
    )
    assert draft_resp.status_code == 200
    assert draft_resp.json()["post"]["id"] > 0

    # Public list only returns published post
    public_list = client.get("/blog_api/blog_posts").json()
    assert public_list["pagination"]["total"] == 1

    # Admin list with ?status=all returns both
    admin_list = client.get(
        "/blog_api/blog_posts?status=all", headers=blog_harness.admin_headers
    ).json()
    assert admin_list["pagination"]["total"] == 2

    # 10. Admin deletes post
    del_resp = client.post(
        "/blog_api/delete_blog_post",
        headers=blog_harness.admin_headers,
        json={"id": post_id},
    )
    assert del_resp.status_code == 200

    # Detail now returns 404
    assert client.get(f"/blog_api/blog_post_detail/{post_id}").status_code == 404


def test_blog_file_rollback_on_failed_transaction(blog_harness: BlogHarness) -> None:
    """Validate that staged gallery files are cleaned up from disk if database transaction fails."""
    client = blog_harness.client
    png = _create_sample_png()

    # Attempt to create a blog post with non-existent category_id 999999
    resp = client.post(
        "/blog_api/create_blog_post",
        headers=blog_harness.admin_headers,
        data={
            "title": "Will Fail Due To FK",
            "excerpt": "Excerpt",
            "category_id": "999999",
            "status": "draft",
            "sections": "[]",
        },
        files=[("images", ("failing_upload.png", png, "image/png"))],
    )
    # The endpoint should return a failure status
    assert resp.status_code in {400, 500}

    # Verify no leaked files in blog/gallery upload directory
    gallery_dir = blog_harness.upload_root / "blog" / "gallery"
    if gallery_dir.exists():
        files = list(gallery_dir.glob("*"))
        assert len(files) == 0, f"Expected no leftover files after rollback, but found: {files}"
