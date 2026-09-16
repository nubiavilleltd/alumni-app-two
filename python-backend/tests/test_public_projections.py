"""Regression tests for database enum values entering public response models."""

import json
from datetime import UTC, date, datetime
from decimal import Decimal
from urllib.parse import urlencode

import pytest
from fastapi.testclient import TestClient
from starlette.requests import Request

from app.api.announcements import _announcement_input
from app.api.leadership import _input as _leadership_input
from app.api.marketplace import _listing_input
from app.api.projects import _project_input
from app.core.config import Settings
from app.main import create_app
from app.models.generated import (
    AnnouncementsType,
    MarketplaceListingsCategory,
    MarketplaceListingsPriceType,
    MarketplaceListingsStatus,
)
from app.schemas.announcements import AnnouncementUpdateRequest
from app.services.announcements import AnnouncementError, AnnouncementService
from app.services.leadership import LeadershipError, LeadershipService
from app.services.marketplace import MarketplaceError, MarketplaceService
from app.services.projects import ProjectError, ProjectService


def _request(body: bytes, content_type: bytes) -> Request:
    sent = False

    async def receive() -> dict[str, object]:
        nonlocal sent
        if sent:
            return {"type": "http.disconnect"}
        sent = True
        return {"type": "http.request", "body": body, "more_body": False}

    return Request(
        {
            "type": "http",
            "method": "POST",
            "path": "/",
            "raw_path": b"/",
            "query_string": b"",
            "headers": [
                (b"content-type", content_type),
                (b"content-length", str(len(body)).encode("ascii")),
            ],
            "scheme": "http",
            "server": ("testserver", 80),
            "client": ("127.0.0.1", 50000),
        },
        receive,
    )


def _form_request(values: dict[str, str]) -> Request:
    return _request(
        urlencode(values).encode("utf-8"),
        b"application/x-www-form-urlencoded",
    )


def _json_request(values: dict[str, object]) -> Request:
    return _request(json.dumps(values).encode("utf-8"), b"application/json")


def test_announcement_projection_uses_enum_wire_value() -> None:
    item = AnnouncementService._item(
        {
            "id": 1,
            "title": "Test announcement",
            "content": "Test content",
            "imag": None,
            "type": AnnouncementsType.INFO,
            "created_by": 1,
            "created_by_name": "Test User",
            "chapter_id": 1,
            "year": None,
            "starts_at": None,
            "ends_at": None,
            "created_at": datetime(2026, 1, 1, tzinfo=UTC),
            "updated_at": None,
        }
    )

    assert item.type == "info"


def test_marketplace_projection_uses_enum_wire_values() -> None:
    item = MarketplaceService._listing(
        {
            "id": 1,
            "user_id": 1,
            "title": "Test listing",
            "business_name": "Test business",
            "phone": "08012345678",
            "chapter_id": None,
            "chapter_name": None,
            "year": None,
            "description": None,
            "category": MarketplaceListingsCategory.OTHER,
            "price": Decimal("0.00"),
            "price_type": MarketplaceListingsPriceType.FREE,
            "images": None,
            "contact_info": None,
            "whatsapp": None,
            "website": None,
            "location": None,
            "message_prompt": "",
            "status": MarketplaceListingsStatus.ACTIVE,
            "is_featured": 0,
            "expires_at": None,
            "created_at": datetime(2026, 1, 1, tzinfo=UTC),
            "seller_name": None,
            "seller_avatar": None,
        },
        None,
        date(2026, 1, 1),
    )

    assert item.category == "other"
    assert item.price_type == "free"
    assert item.status == "active"


def test_announcement_image_only_update_is_validated_for_service() -> None:
    request = AnnouncementUpdateRequest(function_type="update", id=4)
    assert request.id == 4


def test_announcement_helpers_reject_unauthorized_actors_and_validate_paths() -> None:
    with pytest.raises(AnnouncementError, match="Authentication required"):
        AnnouncementService._active_content_actor(None, 1)
    with pytest.raises(AnnouncementError, match="You cannot manage announcements"):
        AnnouncementService._active_content_actor(
            {"active": True, "user_role": "alumni", "is_coordinator": False}, 1
        )
    AnnouncementService._active_content_actor(
        {"active": True, "user_role": "content admin", "is_coordinator": False}, 1
    )

    stored = AnnouncementService._stored_from_path("uploads/announcements/photo.png")
    assert stored is not None
    assert stored.filename == "photo.png"
    assert AnnouncementService._stored_from_path("uploads/announcements/nested/photo.png") is None


def test_marketplace_projection_filters_unsafe_images_and_helpers() -> None:
    row = {
        "id": 1,
        "user_id": 1,
        "title": "Test listing",
        "business_name": "Test business",
        "phone": "08012345678",
        "chapter_id": None,
        "chapter_name": None,
        "year": None,
        "description": None,
        "category": MarketplaceListingsCategory.OTHER,
        "price": Decimal("0.00"),
        "price_type": MarketplaceListingsPriceType.FREE,
        "images": '["uploads/marketplace/a.png", "https://example.test/b.png", "ftp://bad", 3]',
        "contact_info": None,
        "whatsapp": None,
        "website": None,
        "location": None,
        "message_prompt": "",
        "status": MarketplaceListingsStatus.ACTIVE,
        "is_featured": 0,
        "expires_at": None,
        "created_at": datetime(2026, 1, 1, tzinfo=UTC),
        "seller_name": None,
        "seller_avatar": None,
    }
    item = MarketplaceService._listing(row, None, date(2026, 1, 1))
    assert item.images == ["uploads/marketplace/a.png", "https://example.test/b.png"]
    assert MarketplaceService._images("not-json") == []
    assert MarketplaceService._images('{"not": "a list"}') == []
    assert MarketplaceService._stored_from_path("uploads/marketplace/a.png") is not None
    assert MarketplaceService._stored_from_path("uploads/marketplace/a/b.png") is None
    assert MarketplaceService._social_values(
        {"social_instagram": " @test ", "social_tiktok": ""}
    ) == {"instagram_url": "@test", "tiktok_url": None}


def test_marketplace_actor_guards_current_permission() -> None:
    with pytest.raises(MarketplaceError, match="Authentication required"):
        MarketplaceService._marketplace_actor(None, 1)
    with pytest.raises(MarketplaceError, match="Authentication required"):
        MarketplaceService._marketplace_actor({"active": False}, 1)
    member_facts = MarketplaceService._marketplace_actor({"active": True, "user_role": "alumni"}, 1)
    assert member_facts.user_id == 1
    facts = MarketplaceService._marketplace_actor(
        {"active": True, "user_role": "storekeeper admin"}, 1
    )
    assert facts.user_id == 1


def test_project_projection_and_actor_guards() -> None:
    item = ProjectService._project(
        {
            "id": 1,
            "title": "Project",
            "description": None,
            "images": '["uploads/projects/a.png", "https://example.test/b.png", "ftp://bad"]',
            "amount_raised": Decimal("0.00"),
            "target_amount": None,
            "status": "ongoing",
            "location": "Lagos",
            "sort_order": 0,
            "is_featured": 0,
            "chapter_id": None,
            "chapter_name": None,
            "year": None,
            "start_date": None,
            "end_date": None,
            "conducted_by": None,
            "created_at": None,
            "created_by_name": None,
        }
    )
    assert item.images == ["uploads/projects/a.png", "https://example.test/b.png"]
    assert ProjectService._status(None) == "active"
    assert ProjectService._images("not-json") == []
    assert ProjectService._stored_from_path("uploads/projects/a.png") is not None
    assert ProjectService._stored_from_path("uploads/projects/a/b.png") is None
    with pytest.raises(ProjectError, match="Authentication required"):
        ProjectService._content_actor(None, 1)
    with pytest.raises(ProjectError, match="You cannot manage projects"):
        ProjectService._content_actor({"active": True, "user_role": "alumni"}, 1)
    assert ProjectService._content_actor({"active": True, "user_role": "content admin"}, 1)


def test_leadership_projection_and_actor_guards() -> None:
    item = LeadershipService._item(
        {
            "id": 1,
            "user_id": 2,
            "position_title": "President",
            "message": None,
            "leadership_photo": "unsafe/photo.png",
            "user_avatar": "uploads/profiles/avatar.png",
            "fullname": "Test User",
            "chapter_id": None,
            "chapter_name": None,
            "year": "2026",
            "sort_order": 0,
            "is_featured": 1,
            "is_active": 1,
            "created_at": None,
            "updated_at": None,
        }
    )
    assert item.photo == "uploads/profiles/avatar.png"
    assert LeadershipService._safe_photo("ftp://bad") is None
    assert LeadershipService._stored("uploads/leadership/photo.png") is not None
    assert LeadershipService._stored("uploads/leadership/nested/photo.png") is None
    service = LeadershipService.__new__(LeadershipService)
    with pytest.raises(LeadershipError, match="Authentication required"):
        service._actor(None, 1)
    with pytest.raises(LeadershipError, match="You cannot manage leadership"):
        service._actor({"active": True, "user_role": "alumni"}, 1)
    service._actor({"active": True, "user_role": "content admin"}, 1)


async def test_form_input_adapters_preserve_legacy_field_shapes() -> None:
    announcement, announcement_upload = await _announcement_input(
        _form_request({"title": "Announcement", "content": "Content"})
    )
    assert announcement == {"title": "Announcement", "content": "Content"}
    assert announcement_upload is None

    project, project_uploads = await _project_input(
        _form_request(
            {
                "title": "Project",
                "target_amount": "",
                "year": "",
                "remove_images": '["uploads/projects/old.png"]',
            }
        )
    )
    assert project["target_amount"] is None
    assert project["year"] is None
    assert project["remove_images"] == ["uploads/projects/old.png"]
    assert project_uploads == []

    marketplace, marketplace_uploads = await _listing_input(
        _form_request(
            {
                "title": "Listing",
                "remove_images": '["uploads/marketplace/old.png"]',
            }
        )
    )
    assert marketplace["remove_images"] == ["uploads/marketplace/old.png"]
    assert marketplace_uploads == []

    leadership, leadership_upload = await _leadership_input(
        _form_request({"user_id": "2", "position_title": "President"})
    )
    assert leadership == {"user_id": "2", "position_title": "President"}
    assert leadership_upload is None


async def test_json_input_adapters_preserve_json_payloads() -> None:
    announcement, announcement_upload = await _announcement_input(
        _json_request({"title": "Announcement", "content": "Content"})
    )
    assert announcement == {"title": "Announcement", "content": "Content"}
    assert announcement_upload is None

    project, project_uploads = await _project_input(
        _json_request({"title": "Project", "remove_images": ["uploads/projects/old.png"]})
    )
    assert project == {"title": "Project", "remove_images": ["uploads/projects/old.png"]}
    assert project_uploads == []

    marketplace, marketplace_uploads = await _listing_input(
        _json_request({"title": "Listing", "remove_images": ["uploads/marketplace/old.png"]})
    )
    assert marketplace == {
        "title": "Listing",
        "remove_images": ["uploads/marketplace/old.png"],
    }
    assert marketplace_uploads == []

    leadership, leadership_upload = await _leadership_input(
        _json_request({"user_id": 2, "position_title": "President"})
    )
    assert leadership == {"user_id": 2, "position_title": "President"}
    assert leadership_upload is None


async def test_form_input_adapters_leave_malformed_image_removals_for_validation() -> None:
    project, project_uploads = await _project_input(
        _form_request({"title": "Project", "remove_images": "not-json"})
    )
    assert project["remove_images"] == "not-json"
    assert project_uploads == []

    marketplace, marketplace_uploads = await _listing_input(
        _form_request({"title": "Listing", "remove_images": "not-json"})
    )
    assert marketplace["remove_images"] == "not-json"
    assert marketplace_uploads == []


def test_public_feed_invalid_filters_fail_before_database_access() -> None:
    checks = (
        ("/api/get_announcements", {"page": 0}, "announcement_filters_invalid"),
        ("/api/get_listings", {"limit": 101}, "marketplace_filters_invalid"),
        ("/api/get_projects", {"offset": -1}, "project_filters_invalid"),
        ("/api/get_leadership", {"year": "invalid"}, "leadership_filters_invalid"),
    )
    with TestClient(create_app(Settings(environment="test"))) as client:
        for path, payload, code in checks:
            response = client.post(path, json=payload)
            assert response.status_code == 400
            assert response.json()["code"] == code
