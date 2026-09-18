"""Tests for news feeds aggregation endpoint and service."""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any
from unittest.mock import MagicMock

import bcrypt
import httpx
import pytest
from sqlalchemy import create_engine, text
from starlette.testclient import TestClient

from app.core.config import Settings
from app.db.session import Database
from app.main import create_app
from app.repositories.news import NewsRepository
from app.schemas.news import (
    NewsFeedsResponse,
)
from app.services.news import (
    NewsService,
    candidate_matches_category,
    candidate_matches_keyword,
    canonical_category,
    html_to_text,
    parse_categories,
    slugify_title,
)

SAMPLE_RSS_XML = """<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Sample News</title>
    <item>
      <title>CBN introduces new monetary guidelines for banks</title>
      <link>https://example.com/cbn-banking-guidelines</link>
      <description>&lt;p&gt;The Central Bank has issued directives.&lt;/p&gt;</description>
      <pubDate>Mon, 15 Sep 2026 10:00:00 +0100</pubDate>
      <category>Economy</category>
      <category>Banking</category>
    </item>
    <item>
      <title>Super Eagles announce squad for upcoming qualifiers</title>
      <link>https://example.com/sports/super-eagles-squad</link>
      <description>&lt;p&gt;The coach unveiled the 25-man squad list.&lt;/p&gt;</description>
      <pubDate>Mon, 15 Sep 2026 09:30:00 +0100</pubDate>
      <category>Sports</category>
      <category>Football</category>
    </item>
    <item>
      <title>Empowering women with vocational skills in tech hubs</title>
      <link>https://example.com/tech/women-vocational-skills</link>
      <description>&lt;p&gt;An initiative to support women entrepreneurs.&lt;/p&gt;</description>
      <pubDate>Mon, 15 Sep 2026 08:00:00 +0100</pubDate>
      <category>Technology</category>
    </item>
    <item>
      <title>Story without image should be dropped</title>
      <link>https://example.com/no-image-story</link>
      <description>&lt;p&gt;This article has no image metadata on page.&lt;/p&gt;</description>
      <pubDate>Mon, 15 Sep 2026 07:00:00 +0100</pubDate>
    </item>
  </channel>
</rss>
"""

SAMPLE_ARTICLE_HTML_WITH_IMAGE = """<!DOCTYPE html>
<html>
<head>
  <meta property="og:image" content="https://example.com/images/cbn-headline.jpg" />
</head>
<body>
  <div class="entry-content">
    <p>The Central Bank of Nigeria rolled out revised guidelines for lenders nationwide.</p>
    <p>Financial analysts report the monetary policy shift aims to curb inflation rates.</p>
  </div>
</body>
</html>
"""

SAMPLE_ARTICLE_HTML_NO_IMAGE = """<!DOCTYPE html>
<html>
<body>
  <p>Article body with enough characters to pass length threshold, but missing open graph image.</p>
</body>
</html>
"""


class MockTransport(httpx.BaseTransport):
    """Deterministic in-memory HTTP transport mocking feeds and page scrapes."""

    def handle_request(self, request: httpx.Request) -> httpx.Response:
        url_str = str(request.url)
        if "/feed" in url_str or url_str.endswith(".rss"):
            return httpx.Response(
                status_code=200,
                headers={"Content-Type": "application/xml"},
                content=SAMPLE_RSS_XML.encode("utf-8"),
            )
        if "no-image" in url_str:
            return httpx.Response(
                status_code=200,
                headers={"Content-Type": "text/html"},
                content=SAMPLE_ARTICLE_HTML_NO_IMAGE.encode("utf-8"),
            )
        return httpx.Response(
            status_code=200,
            headers={"Content-Type": "text/html"},
            content=SAMPLE_ARTICLE_HTML_WITH_IMAGE.encode("utf-8"),
        )


@pytest.fixture
def mock_http_client() -> httpx.Client:
    return httpx.Client(transport=MockTransport())


@pytest.fixture
def default_db_url() -> str:
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")
    return database_url


def test_slugify_and_html_helpers() -> None:
    assert (
        slugify_title("CBN: Naira Gains Against Dollar (Sept 2026)!")
        == "cbn-naira-gains-against-dollar-sept-2026"
    )
    assert slugify_title("") == "article"

    raw_html = (
        "<p>First paragraph.</p><p>Second paragraph with <script>alert(1)</script> details.</p>"
    )
    text_out = html_to_text(raw_html)
    assert text_out is not None
    assert "alert(1)" not in text_out
    assert "First paragraph." in text_out
    assert "Second paragraph with  details." in text_out


def test_category_parsing_and_aliases() -> None:
    assert canonical_category("financies") == "business"
    assert canonical_category("football") == "sports"
    assert canonical_category("tech") == "technology"
    assert canonical_category("all") == "all"
    assert canonical_category("unknown_xyz") is None

    # Parsing list with aliases and keywords
    slugs, keywords, invalid = parse_categories(
        ["financies", "sports", "women", "vocational training"]
    )
    assert slugs == ["business", "sports"]
    assert "vocational training" in keywords
    assert "women" in keywords
    assert invalid == []

    # 'all' wins over topics but preserves keywords
    slugs_all, keywords_all, _ = parse_categories("all;women")
    assert slugs_all == []
    assert keywords_all == ["women"]

    # Invalid category (punctuation only or too long)
    _, _, invalid_cats = parse_categories(["$$$", "a" * 70, "valid-topic"])
    assert "$$$" in invalid_cats
    assert "a" * 70 in invalid_cats


def test_candidate_matching_rules() -> None:
    candidate = {
        "title": "Super Eagles qualify for championship",
        "description": "The team won their group qualifier match yesterday.",
        "url": "https://example.com/sports/eagles-win",
        "categories": ["Sports", "Football"],
    }
    # Matches sports category by tag, path, and keywords
    assert candidate_matches_category(
        candidate,
        {
            "tags": ["football"],
            "paths": ["/sports"],
            "keywords": ["super eagles"],
        },
    )

    # Free keyword matching: "vocation" matches "vocational" (suffix rule)
    kw_cand = {
        "title": "New vocational center opens",
        "description": "Skills acquisition for youth.",
        "url": "https://example.com/news/123",
        "categories": ["Skills"],
    }
    assert candidate_matches_keyword(kw_cand, "vocation")
    assert not candidate_matches_keyword(kw_cand, "mining")


def test_news_service_caching_and_enrichment(
    tmp_path: Path, mock_http_client: httpx.Client
) -> None:
    settings = Settings(upload_root=tmp_path / "uploads")
    mock_session = MagicMock()

    service = NewsService(mock_session, settings, http_client=mock_http_client)
    service._repo = MagicMock()
    service._repo.get_setup_news_category.return_value = ""

    # 1. First fetch: MISS, caches response
    resp1, hit1 = service.get_feeds(category_param="business", limit=10, refresh=False)
    assert hit1 is False
    assert resp1.status == 200
    assert resp1.count > 0
    assert len(resp1.articles) > 0
    assert resp1.articles[0].image is not None
    assert resp1.articles[0].fullcontent is not None
    assert resp1.category == "business"

    # 2. Second fetch: HIT from cache
    resp2, hit2 = service.get_feeds(category_param="business", limit=10, refresh=False)
    assert hit2 is True
    assert resp2.count == resp1.count

    # 3. Third fetch with refresh=True: bypass cache (MISS)
    resp3, hit3 = service.get_feeds(category_param="business", limit=10, refresh=True)
    assert hit3 is False
    assert resp3.count == resp1.count


def test_openapi_route_registration() -> None:
    settings = Settings()
    app = create_app(settings)
    openapi = app.openapi()
    assert "/news/feeds" in openapi["paths"]
    assert "get" in openapi["paths"]["/news/feeds"]
    assert "post" in openapi["paths"]["/news/feeds"]


def test_news_endpoint_authentication_and_validation(tmp_path: Path, default_db_url: str) -> None:
    settings = Settings(
        database_url=default_db_url,
        upload_root=tmp_path / "uploads",
    )
    app = create_app(settings)
    client = TestClient(app)

    # 1. Missing API Key -> 401
    r_no_key = client.get("/news/feeds")
    assert r_no_key.status_code == 401
    assert r_no_key.json()["message"] == "Invalid API token"

    # 2. Invalid API Key -> 401
    r_bad_key = client.post("/news/feeds", headers={"X-API-Key": "wrong-key"})
    assert r_bad_key.status_code == 401
    assert r_bad_key.json()["message"] == "Invalid API token"


def test_news_mariadb_integration(
    tmp_path: Path, mock_http_client: httpx.Client, default_db_url: str
) -> None:
    """Full integration test against isolated local MariaDB instance."""
    settings = Settings(
        database_url=default_db_url,
        upload_root=tmp_path / "uploads",
    )
    database = Database(settings)

    # Seed api_table and setup_parameters in MariaDB
    raw_api_key = "test-secret-key-12345"
    hashed_token = (
        bcrypt.hashpw(raw_api_key.encode("utf-8"), bcrypt.gensalt())
        .decode("utf-8")
        .replace("$2b$", "$2y$")
    )

    engine = create_engine(default_db_url, pool_pre_ping=True)
    with engine.begin() as conn:
        conn.execute(text("DELETE FROM api_table WHERE api_name = 'alumni_key'"))
        conn.execute(
            text(
                "INSERT INTO api_table (api_name, api_token, api_key) VALUES (:name, :token, :key)"
            ),
            {"name": "alumni_key", "token": hashed_token, "key": raw_api_key},
        )
        conn.execute(text("DELETE FROM setup_parameters WHERE setup_name = 'news_category'"))
        conn.execute(
            text(
                "INSERT INTO setup_parameters (setup_name, setup_value) "
                "VALUES ('news_category', 'financies;sports')"
            )
        )

    # Test repository verification directly
    sessions = database.sessions()
    session = next(sessions)
    try:
        repo = NewsRepository(session)
        assert repo.verify_api_key(raw_api_key) is True
        assert repo.verify_api_key("wrong-secret") is False
        assert repo.get_setup_news_category() == "financies;sports"
    finally:
        sessions.close()

    # Test via FastAPI test client with mocked HTTP client injected into service
    app = create_app(settings)
    test_client = TestClient(app)

    original_get_feeds = NewsService.get_feeds

    def mock_get_feeds(self: NewsService, **kwargs: Any) -> tuple[NewsFeedsResponse, bool]:
        self._http_client = mock_http_client
        return original_get_feeds(self, **kwargs)

    NewsService.get_feeds = mock_get_feeds  # type: ignore[method-assign]

    try:
        # 1. GET with valid API key, fallback to setup_parameters category
        resp = test_client.get("/news/feeds", headers={"X-API-Key": raw_api_key})
        assert resp.status_code == 200
        data = resp.json()
        assert data["status"] == 200
        assert data["category_source"] == "setup_parameters"
        assert "business" in data["selected_categories"]
        assert "sports" in data["selected_categories"]
        assert resp.headers.get("X-Cache") in ("MISS", "HIT")

        # 2. POST with explicit category override and invalid category handling
        bad_cat_resp = test_client.post(
            "/news/feeds",
            headers={"X-API-Key": raw_api_key},
            json={"category": ["!@#$%^"]},
        )
        assert bad_cat_resp.status_code == 400
        bad_data = bad_cat_resp.json()
        assert bad_data["status"] == 400
        assert bad_data["message"] == "Unknown category"
        assert "!@#$%^" in bad_data["invalid_categories"]
        assert "business" in bad_data["available_categories"]

        # 3. POST with keyword category and refresh=1
        kw_resp = test_client.post(
            "/news/feeds",
            headers={"X-API-Key": raw_api_key},
            json={"category": "technology,women", "limit": 10, "refresh": 1},
        )
        assert kw_resp.status_code == 200
        kw_data = kw_resp.json()
        assert kw_data["category_source"] == "request"
        assert "technology" in kw_data["selected_categories"]
        assert "women" in kw_data["keywords"]
        assert kw_resp.headers.get("X-Cache") == "MISS"
    finally:
        NewsService.get_feeds = original_get_feeds  # type: ignore[method-assign]
        with engine.begin() as conn:
            conn.execute(text("DELETE FROM api_table WHERE api_name = 'alumni_key'"))
            conn.execute(text("DELETE FROM setup_parameters WHERE setup_name = 'news_category'"))
        engine.dispose()
