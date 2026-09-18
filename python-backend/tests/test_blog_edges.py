"""Goal 7 blog/carousel/FAQ/category/post edge and error-path coverage.

``tests/test_blog.py`` proves the happy paths; this module drives the validation,
authorization, not-found, no-field, cleanup, and malformed-input branches for the
same 21 routes against the sanitized disposable schema with synthetic rows.
"""

from __future__ import annotations

import json
from collections.abc import Iterator
from pathlib import Path
from typing import Any

import pytest

from tests.test_blog import BlogHarness, _create_sample_png
from tests.test_blog import blog_harness as _shared_blog_harness


@pytest.fixture(name="blog_harness")
def _blog_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[BlogHarness]:
    """Delegate to the shared blog harness generator without shadowing its name."""
    yield from _shared_blog_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


def _status_message(response: Any) -> str:
    payload = response.json()
    assert payload["status"] >= 400
    message = payload["message"]
    assert isinstance(message, str)
    return message


def _create_category(harness: BlogHarness, name: str = "Edge Category") -> int:
    response = harness.client.post(
        "/blog_api/create_blog_category",
        headers=harness.admin_headers,
        json={"name": name},
    )
    assert response.status_code == 200, response.text
    category_id = response.json()["category"]["id"]
    assert isinstance(category_id, int)
    return category_id


def _create_post(
    harness: BlogHarness,
    category_id: int,
    *,
    title: str = "Edge Post",
    images: list[tuple[str, bytes, str]] | None = None,
    main_image_index: str | None = None,
) -> dict[str, Any]:
    data: dict[str, Any] = {
        "title": title,
        "excerpt": "Edge-case excerpt for coverage.",
        "category_id": str(category_id),
        "status": "published",
        "sections": json.dumps([{"heading": "Intro", "body": "<p>Body</p>", "sort_order": 0}]),
    }
    if main_image_index is not None:
        data["main_image_index"] = main_image_index
    response = harness.client.post(
        "/blog_api/create_blog_post",
        headers=harness.admin_headers,
        data=data,
        files=images or [],
    )
    assert response.status_code == 200, response.text
    post = response.json()["post"]
    assert isinstance(post, dict)
    return post


# ═════════════════════════════════════════════════════════════
# HOMEPAGE AND CAROUSEL
# ═════════════════════════════════════════════════════════════


def test_update_homepage_text_rejects_blank_fields(blog_harness: BlogHarness) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    blank_title = client.post(
        "/blog_api/update_homepage_text",
        headers=headers,
        json={"greeting_title": "   ", "greeting_message": "Hello"},
    )
    assert blank_title.status_code == 400
    assert _status_message(blank_title) == "greeting_title and greeting_message are required"

    blank_message = client.post(
        "/blog_api/update_homepage_text",
        headers=headers,
        json={"greeting_title": "Welcome", "greeting_message": ""},
    )
    assert blank_message.status_code == 400
    assert _status_message(blank_message) == "greeting_title and greeting_message are required"


def test_create_carousel_image_rejects_a_missing_upload_and_invalid_bytes(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    missing = client.post("/blog_api/create_carousel_image", headers=headers, data={})
    assert missing.status_code == 400
    assert _status_message(missing) == "Image upload failed"

    blank_name = client.post(
        "/blog_api/create_carousel_image",
        headers=headers,
        files=[("image", ("", b"", "image/png"))],
    )
    assert blank_name.status_code == 400
    assert _status_message(blank_name) == "Image upload failed"

    invalid = client.post(
        "/blog_api/create_carousel_image",
        headers=headers,
        data={"alt_text": "Broken", "sort_order": "1", "show_greeting": "not-a-number"},
        files=[("image", ("broken.png", b"not-an-image", "image/png"))],
    )
    assert invalid.status_code == 400
    assert _status_message(invalid) == "Avatar file is not a valid image"


def test_create_carousel_image_accepts_numeric_and_boolean_flags(
    blog_harness: BlogHarness,
) -> None:
    """``_to_bool`` must accept the numeric flags the admin UI sends."""
    response = blog_harness.client.post(
        "/blog_api/create_carousel_image",
        headers=blog_harness.admin_headers,
        data={"alt_text": "Numeric flag", "sort_order": "0", "show_greeting": "1"},
        files=[("image", ("greeting.png", _create_sample_png(), "image/png"))],
    )
    assert response.status_code == 200, response.text
    payload = response.json()
    assert payload["image"]["show_greeting"] == "1"
    assert payload["greeting_image_id"] == payload["image"]["id"]


def test_update_carousel_image_validates_id_fields_and_target(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    missing_id = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        json={"alt_text": "No id"},
    )
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        json={"id": 999999, "alt_text": "Unknown"},
    )
    assert unknown.status_code == 404
    assert _status_message(unknown) == "Carousel image not found"

    created = client.post(
        "/blog_api/create_carousel_image",
        headers=headers,
        data={"alt_text": "Editable"},
        files=[("image", ("editable.png", _create_sample_png(), "image/png"))],
    )
    assert created.status_code == 200, created.text
    image_id = created.json()["image"]["id"]

    no_fields = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        json={"id": image_id},
    )
    assert no_fields.status_code == 400
    assert _status_message(no_fields) == "No fields to update"

    invalid_replacement = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        data={"id": str(image_id), "alt_text": "Replaced"},
        files=[("image", ("broken.png", b"not-an-image", "image/png"))],
    )
    assert invalid_replacement.status_code == 400
    assert _status_message(invalid_replacement) == "Avatar file is not a valid image"

    # The multipart form path must accept a valid replacement image and flags.
    replaced = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        data={"id": str(image_id), "alt_text": "Replaced", "is_hidden": "0"},
        files=[("image", ("replacement.png", _create_sample_png(), "image/png"))],
    )
    assert replaced.status_code == 200, replaced.text
    assert replaced.json()["image"]["alt_text"] == "Replaced"

    # JSON booleans also normalize through the same helper.
    hidden = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        json={"id": image_id, "show_greeting": True, "is_hidden": False},
    )
    assert hidden.status_code == 200, hidden.text
    assert hidden.json()["greeting_image_id"] == image_id

    # Hiding the greeting image reassigns the greeting to another visible image.
    hidden = client.post(
        "/blog_api/update_carousel_image",
        headers=headers,
        json={"id": image_id, "is_hidden": True},
    )
    assert hidden.status_code == 200, hidden.text
    assert hidden.json()["image"]["is_hidden"] == "1"


def test_reorder_and_delete_carousel_validate_payloads(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    invalid_payloads: list[dict[str, Any]] = [{}, {"images": []}, {"images": "not-a-list"}]
    for payload in invalid_payloads:
        response = client.post("/blog_api/reorder_carousel", headers=headers, json=payload)
        assert response.status_code == 400, response.text
        assert _status_message(response) == "images array is required"

    # Reordering is an idempotent batch write: ids outside the current carousel
    # are ignored rather than failing the whole request.
    unknown_reorder = client.post(
        "/blog_api/reorder_carousel",
        headers=headers,
        json={"images": [{"id": 999999, "sort_order": 0}]},
    )
    assert unknown_reorder.status_code == 200, unknown_reorder.text
    assert unknown_reorder.json()["carousel_images"] == []

    missing_id = client.post(
        "/blog_api/delete_carousel_image",
        headers=headers,
        json={"id": "abc"},
    )
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown_delete = client.post(
        "/blog_api/delete_carousel_image",
        headers=headers,
        json={"id": 999999},
    )
    assert unknown_delete.status_code == 404
    assert _status_message(unknown_delete) == "Carousel image not found"


# ═════════════════════════════════════════════════════════════
# FAQS
# ═════════════════════════════════════════════════════════════


def test_faq_routes_validate_payloads_and_targets(blog_harness: BlogHarness) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    blank = client.post(
        "/blog_api/create_faq",
        headers=headers,
        json={"question": "   ", "answer": ""},
    )
    assert blank.status_code == 400
    assert _status_message(blank) == "question and answer are required"

    created = client.post(
        "/blog_api/create_faq",
        headers=headers,
        json={"question": "How do I join?", "answer": "Use the register page.", "sort_order": 3},
    )
    assert created.status_code == 200, created.text
    faq_id = created.json()["faq"]["id"]

    missing_id = client.post("/blog_api/update_faq", headers=headers, json={"question": "x"})
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown_update = client.post(
        "/blog_api/update_faq",
        headers=headers,
        json={"id": 999999, "question": "Unknown"},
    )
    assert unknown_update.status_code == 404
    assert _status_message(unknown_update) == "FAQ not found"

    no_fields = client.post("/blog_api/update_faq", headers=headers, json={"id": faq_id})
    assert no_fields.status_code == 400
    assert _status_message(no_fields) == "No fields to update"

    updated = client.post(
        "/blog_api/update_faq",
        headers=headers,
        json={
            "id": faq_id,
            "answer": "Updated answer.",
            "sort_order": "5",
            "is_published": "0",
        },
    )
    assert updated.status_code == 200, updated.text
    assert updated.json()["faq"]["answer"] == "Updated answer."

    invalid_payloads: list[dict[str, Any]] = [{}, {"faqs": []}, {"faqs": "nope"}]
    for payload in invalid_payloads:
        response = client.post("/blog_api/reorder_faqs", headers=headers, json=payload)
        assert response.status_code == 400, response.text
        assert _status_message(response) == "faqs array is required"

    unknown_reorder = client.post(
        "/blog_api/reorder_faqs",
        headers=headers,
        json={"faqs": [{"id": 999999, "sort_order": 0}]},
    )
    assert unknown_reorder.status_code == 200, unknown_reorder.text
    assert all(faq["id"] != 999999 for faq in unknown_reorder.json()["faqs"])

    missing_delete = client.post("/blog_api/delete_faq", headers=headers, json={})
    assert missing_delete.status_code == 400
    assert _status_message(missing_delete) == "id is required"

    unknown_delete = client.post(
        "/blog_api/delete_faq",
        headers=headers,
        json={"id": 999999},
    )
    assert unknown_delete.status_code == 404
    assert _status_message(unknown_delete) == "FAQ not found"


def test_faq_visibility_follows_publication_state(blog_harness: BlogHarness) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    unpublished = client.post(
        "/blog_api/create_faq",
        headers=headers,
        json={"question": "Hidden question?", "answer": "Hidden answer", "is_published": "0"},
    )
    assert unpublished.status_code == 200, unpublished.text
    hidden_id = unpublished.json()["faq"]["id"]

    public_view = client.get("/blog_api/faqs")
    assert public_view.status_code == 200
    assert all(faq["id"] != hidden_id for faq in public_view.json()["faqs"])

    admin_view = client.post(
        "/blog_api/faqs",
        headers=headers,
        json={"all": True},
    )
    assert admin_view.status_code == 200
    assert any(faq["id"] == hidden_id for faq in admin_view.json()["faqs"])


# ═════════════════════════════════════════════════════════════
# CATEGORIES
# ═════════════════════════════════════════════════════════════


def test_category_routes_validate_payloads_slugs_and_targets(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers

    blank = client.post("/blog_api/create_blog_category", headers=headers, json={"name": "   "})
    assert blank.status_code == 400
    assert _status_message(blank) == "name is required"

    # A name without any alphanumeric character falls back to the stable slug.
    symbol_only = client.post(
        "/blog_api/create_blog_category",
        headers=headers,
        json={"name": "***"},
    )
    assert symbol_only.status_code == 200, symbol_only.text
    assert symbol_only.json()["category"]["slug"] == "category"

    category_id = _create_category(blog_harness, "Slug Normalising Category")

    missing_id = client.post(
        "/blog_api/update_blog_category",
        headers=headers,
        json={"name": "No id"},
    )
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown = client.post(
        "/blog_api/update_blog_category",
        headers=headers,
        json={"id": 999999, "name": "Unknown"},
    )
    assert unknown.status_code == 404
    assert _status_message(unknown) == "Category not found"

    no_fields = client.post(
        "/blog_api/update_blog_category",
        headers=headers,
        json={"id": category_id},
    )
    assert no_fields.status_code == 400
    assert _status_message(no_fields) == "No fields to update"

    updated = client.post(
        "/blog_api/update_blog_category",
        headers=headers,
        json={
            "id": category_id,
            "slug": "  Mixed CASE Slug!  ",
            "is_active": "0",
            "sort_order": "7",
        },
    )
    assert updated.status_code == 200, updated.text
    assert updated.json()["category"]["slug"] == "mixed-case-slug"

    invalid_payloads: list[dict[str, Any]] = [{}, {"categories": []}, {"categories": "nope"}]
    for payload in invalid_payloads:
        response = client.post("/blog_api/reorder_categories", headers=headers, json=payload)
        assert response.status_code == 400, response.text
        assert _status_message(response) == "categories array is required"

    unknown_reorder = client.post(
        "/blog_api/reorder_categories",
        headers=headers,
        json={"categories": [{"id": 999999, "sort_order": 0}]},
    )
    assert unknown_reorder.status_code == 200, unknown_reorder.text
    assert all(category["id"] != 999999 for category in unknown_reorder.json()["categories"])

    missing_delete = client.post("/blog_api/delete_blog_category", headers=headers, json={})
    assert missing_delete.status_code == 400
    assert _status_message(missing_delete) == "id is required"

    unknown_delete = client.post(
        "/blog_api/delete_blog_category",
        headers=headers,
        json={"id": 999999},
    )
    assert unknown_delete.status_code == 404
    assert _status_message(unknown_delete) == "Category not found"


# ═════════════════════════════════════════════════════════════
# POSTS
# ═════════════════════════════════════════════════════════════


def test_blog_post_detail_requires_a_non_blank_identifier(blog_harness: BlogHarness) -> None:
    response = blog_harness.client.get("/blog_api/blog_post_detail/%20")
    assert response.status_code == 400
    assert _status_message(response) == "id or slug is required"


def test_create_blog_post_validates_required_fields_and_sections(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers
    category_id = _create_category(blog_harness, "Post Validation Category")
    valid_sections = json.dumps([{"heading": "Intro", "body": "<p>Body</p>"}])

    missing_title = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={"excerpt": "No title", "category_id": str(category_id), "sections": valid_sections},
    )
    assert missing_title.status_code == 400
    assert _status_message(missing_title) == "title, category_id, and excerpt are required"

    missing_category = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={"title": "No category", "excerpt": "Text", "sections": valid_sections},
    )
    assert missing_category.status_code == 400
    assert _status_message(missing_category) == "title, category_id, and excerpt are required"

    missing_excerpt = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={"title": "No excerpt", "category_id": str(category_id), "sections": valid_sections},
    )
    assert missing_excerpt.status_code == 400
    assert _status_message(missing_excerpt) == "title, category_id, and excerpt are required"

    # Absent, empty, and malformed section payloads all fail closed.
    for sections in (None, "[]", "not-json", "", "{}"):
        data: dict[str, Any] = {
            "title": "Section guard",
            "excerpt": "Text",
            "category_id": str(category_id),
        }
        if sections is not None:
            data["sections"] = sections
        response = client.post("/blog_api/create_blog_post", headers=headers, data=data)
        assert response.status_code == 400, response.text
        assert _status_message(response) == "At least one section is required"

    unknown_category = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={
            "title": "Unknown category",
            "excerpt": "Text",
            "category_id": "999999",
            "sections": valid_sections,
        },
    )
    assert unknown_category.status_code == 400
    assert _status_message(unknown_category) == "Category does not exist"


def test_create_blog_post_cleans_up_images_when_validation_fails(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers
    category_id = _create_category(blog_harness, "Post Cleanup Category")
    gallery_root = blog_harness.upload_root / "blog" / "gallery"
    before = sorted(gallery_root.glob("**/*")) if gallery_root.exists() else []

    # A second image fails validation, so the already-saved first image is removed.
    invalid_second = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={
            "title": "Cleanup on invalid image",
            "excerpt": "Text",
            "category_id": str(category_id),
            "sections": json.dumps([{"heading": "Intro", "body": "<p>Body</p>"}]),
        },
        files=[
            ("images", ("good.png", _create_sample_png(), "image/png")),
            ("images", ("bad.png", b"not-an-image", "image/png")),
        ],
    )
    assert invalid_second.status_code == 400
    assert _status_message(invalid_second) == "Avatar file is not a valid image"
    after = sorted(gallery_root.glob("**/*")) if gallery_root.exists() else []
    assert after == before

    # An out-of-range main_image_index also cleans up every staged file.
    invalid_index = client.post(
        "/blog_api/create_blog_post",
        headers=headers,
        data={
            "title": "Cleanup on bad index",
            "excerpt": "Text",
            "category_id": str(category_id),
            "sections": json.dumps([{"heading": "Intro", "body": "<p>Body</p>"}]),
            "main_image_index": "5",
        },
        files=[("images", ("only.png", _create_sample_png(), "image/png"))],
    )
    assert invalid_index.status_code == 400
    assert _status_message(invalid_index).startswith(
        "main_image_index must be a valid zero-based index"
    )
    remaining = sorted(gallery_root.glob("**/*")) if gallery_root.exists() else []
    assert remaining == before


def test_update_blog_post_validates_target_fields_and_cover_image(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers
    category_id = _create_category(blog_harness, "Post Update Category")
    post = _create_post(blog_harness, category_id, title="Update Target Post")

    missing_id = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        data={"title": "No id"},
    )
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        json={"id": 999999, "title": "Unknown"},
    )
    assert unknown.status_code == 404
    assert _status_message(unknown) == "Post not found"

    invalid_category = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        json={"id": post["id"], "category_id": 999999},
    )
    assert invalid_category.status_code == 400
    assert _status_message(invalid_category) == "Category does not exist"

    foreign_cover = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        json={"id": post["id"], "main_image_url": "uploads/blog/gallery/not-ours.png"},
    )
    assert foreign_cover.status_code == 400
    assert _status_message(foreign_cover) == "main_image_url does not belong to this post"

    invalid_gallery = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        data={"id": str(post["id"]), "title": "Gallery guard"},
        files=[("images", ("bad.png", b"not-an-image", "image/png"))],
    )
    assert invalid_gallery.status_code == 400
    assert _status_message(invalid_gallery) == "Avatar file is not a valid image"

    invalid_index = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        data={"id": str(post["id"]), "main_image_index": "4"},
        files=[("images", ("one.png", _create_sample_png(), "image/png"))],
    )
    assert invalid_index.status_code == 400
    assert _status_message(invalid_index).startswith(
        "main_image_index must be a valid zero-based index"
    )

    # A valid multipart update replaces the gallery and selects a cover image.
    updated = client.post(
        "/blog_api/update_blog_post",
        headers=headers,
        data={
            "id": str(post["id"]),
            "title": "Updated title",
            "sections": json.dumps([{"heading": "New", "body": "<p>New body</p>"}]),
            "main_image_index": "0",
        },
        files=[("images", ("cover.png", _create_sample_png(), "image/png"))],
    )
    assert updated.status_code == 200, updated.text
    assert updated.json()["post"]["title"] == "Updated title"
    assert updated.json()["post"]["cover_image_url"]

    # The slug is derived from the title, so look the post up by its stable id.
    detail = client.get(f"/blog_api/blog_post_detail/{post['id']}")
    assert detail.status_code == 200, detail.text


def test_delete_blog_post_validates_targets(blog_harness: BlogHarness) -> None:
    client = blog_harness.client
    headers = blog_harness.admin_headers
    category_id = _create_category(blog_harness, "Post Delete Category")
    post = _create_post(blog_harness, category_id, title="Delete Target Post")

    missing_id = client.post("/blog_api/delete_blog_post", headers=headers, json={})
    assert missing_id.status_code == 400
    assert _status_message(missing_id) == "id is required"

    unknown = client.post("/blog_api/delete_blog_post", headers=headers, json={"id": 999999})
    assert unknown.status_code == 404
    assert _status_message(unknown) == "Post not found"

    deleted = client.post("/blog_api/delete_blog_post", headers=headers, json={"id": post["id"]})
    assert deleted.status_code == 200, deleted.text

    again = client.post("/blog_api/delete_blog_post", headers=headers, json={"id": post["id"]})
    assert again.status_code == 404


def test_blog_mutation_authorization_distinguishes_missing_and_insufficient_actors(
    blog_harness: BlogHarness,
) -> None:
    client = blog_harness.client
    member = blog_harness.member_headers

    denied = client.post(
        "/blog_api/update_homepage_text",
        headers=member,
        json={"greeting_title": "Nope", "greeting_message": "Nope"},
    )
    assert denied.status_code == 403
    assert _status_message(denied) == "Admin access required"

    # An inactive account cannot retain content authority.
    with blog_harness.engine.begin() as conn:
        from sqlalchemy import update

        from app.models.generated import Users

        conn.execute(update(Users).where(Users.id == blog_harness.member_user_id).values(active=0))
    inactive = client.post(
        "/blog_api/update_homepage_text",
        headers=member,
        json={"greeting_title": "Nope", "greeting_message": "Nope"},
    )
    assert inactive.status_code in (401, 403)


def test_blog_public_reads_default_to_published_content(blog_harness: BlogHarness) -> None:
    """Anonymous and member reads must never unlock drafts."""
    client = blog_harness.client
    category_id = _create_category(blog_harness, "Visibility Category")
    draft = _create_post(blog_harness, category_id, title="Draft Post")
    draft_id = draft["id"]
    with blog_harness.engine.begin() as conn:
        from sqlalchemy import update

        from app.models.generated import BlogPosts

        conn.execute(update(BlogPosts).where(BlogPosts.id == draft_id).values(status="draft"))

    public_list = client.get("/blog_api/blog_posts")
    assert public_list.status_code == 200
    assert all(item["id"] != draft_id for item in public_list.json()["posts"])

    requested_all = client.post("/blog_api/blog_posts", json={"status": "all"})
    assert requested_all.status_code == 200
    assert all(item["id"] != draft_id for item in requested_all.json()["posts"])

    admin_list = client.post(
        "/blog_api/blog_posts",
        headers=blog_harness.admin_headers,
        json={"status": "all"},
    )
    assert admin_list.status_code == 200
    assert any(item["id"] == draft_id for item in admin_list.json()["posts"])
