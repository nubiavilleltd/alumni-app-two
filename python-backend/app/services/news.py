"""News aggregator service pulling, filtering, and enriching Nigerian news feeds."""

from __future__ import annotations

import contextlib
import datetime
import hashlib
import json
import re
import unicodedata
from email.utils import parsedate_to_datetime
from html import unescape as html_unescape
from html.parser import HTMLParser
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

import httpx
from defusedxml.ElementTree import fromstring  # type: ignore[import-untyped]
from sqlalchemy.orm import Session

from app.core.config import Settings
from app.repositories.news import NewsRepository
from app.schemas.news import (
    NewsArticleItem,
    NewsFeedsResponse,
)

AVAILABLE_CATEGORIES = [
    "all",
    "business",
    "entertainment",
    "sports",
    "politics",
    "technology",
    "health",
    "education",
]

CATEGORY_DEFS: dict[str, dict[str, Any]] = {
    "business": {
        "feeds": {
            "Premium Times (Business)": ("https://www.premiumtimesng.com/category/business/feed"),
            "Nairametrics (Business)": ("https://nairametrics.com/category/business-news/feed"),
            "Nairametrics (Markets)": ("https://nairametrics.com/category/market-news/feed"),
            "PM News (Business)": ("https://www.pmnewsnigeria.com/category/business/feed"),
            "Legit (Business)": "https://www.legit.ng/rss/business-economy.rss",
            "Legit (Money)": "https://www.legit.ng/rss/money.rss",
        },
        "tags": [
            "business",
            "economy",
            "finance",
            "financial",
            "money",
            "market",
            "markets",
            "banking",
            "oil",
            "energy",
            "agriculture",
            "real estate",
        ],
        "paths": ["/business", "/economy", "/finance", "/money", "/market"],
        "keywords": [
            "naira",
            "dollar",
            "inflation",
            "gdp",
            "cbn",
            "central bank",
            "economy",
            "economic",
            "stock",
            "stocks",
            "shares",
            "investors",
            "investment",
            "bank",
            "banks",
            "banking",
            "revenue",
            "profit",
            "earnings",
            "tax",
            "taxes",
            "budget",
            "trade",
            "exports",
            "imports",
            "oil price",
            "crude",
            "fuel price",
            "subsidy",
            "fintech",
            "startup funding",
            "ipo",
            "bond",
            "bonds",
            "interest rate",
            "exchange rate",
            "forex",
            "crypto",
            "bitcoin",
            "nse",
            "ngx",
            "debt",
            "loan",
            "loans",
            "salary",
            "wage",
            "business",
        ],
    },
    "entertainment": {
        "feeds": {
            "Premium Times (Entertainment)": (
                "https://www.premiumtimesng.com/category/entertainment/feed"
            ),
            "PM News (Entertainment)": (
                "https://www.pmnewsnigeria.com/category/entertainment/feed"
            ),
            "Legit (Entertainment)": "https://www.legit.ng/rss/entertainment.rss",
        },
        "tags": [
            "entertainment",
            "celebrity",
            "celebrities",
            "nollywood",
            "music",
            "movies",
            "film",
            "showbiz",
            "lifestyle",
            "arts",
            "culture",
        ],
        "paths": [
            "/entertainment",
            "/celebrity",
            "/nollywood",
            "/music",
            "/lifestyle",
            "/showbiz",
        ],
        "keywords": [
            "nollywood",
            "afrobeats",
            "singer",
            "rapper",
            "actress",
            "actor",
            "movie",
            "movies",
            "film",
            "album",
            "concert",
            "grammy",
            "amvca",
            "headies",
            "big brother",
            "bbnaija",
            "celebrity",
            "dj",
            "skit maker",
            "influencer",
            "reality show",
            "box office",
            "netflix",
            "premiere",
            "wizkid",
            "davido",
            "burna boy",
            "tiwa savage",
            "olamide",
            "asake",
            "rema",
            "ayra starr",
        ],
    },
    "sports": {
        "feeds": {
            "Premium Times (Sports)": ("https://www.premiumtimesng.com/category/sports/feed"),
            "PM News (Sports)": "https://www.pmnewsnigeria.com/category/sports/feed",
            "Legit (Sports)": "https://www.legit.ng/rss/sports.rss",
        },
        "tags": ["sport", "sports", "football", "soccer", "athletics", "boxing", "basketball"],
        "paths": ["/sport", "/sports", "/football"],
        "keywords": [
            "super eagles",
            "afcon",
            "fifa",
            "caf",
            "nff",
            "npfl",
            "premier league",
            "la liga",
            "serie a",
            "bundesliga",
            "champions league",
            "world cup",
            "football",
            "footballer",
            "striker",
            "midfielder",
            "goalkeeper",
            "coach",
            "transfer window",
            "olympics",
            "athletics",
            "boxing",
            "basketball",
            "nba",
            "tennis",
            "wrestling",
            "derby",
            "fixture",
            "fixtures",
            "kick-off",
            "goal",
            "goals",
            "trophy",
        ],
    },
    "politics": {
        "feeds": {
            "Premium Times (Politics)": (
                "https://www.premiumtimesng.com/category/news/politics/feed"
            ),
            "PM News (Politics)": "https://www.pmnewsnigeria.com/category/politics/feed",
            "Legit (Politics)": "https://www.legit.ng/rss/politics.rss",
        },
        "tags": ["politics", "political", "government", "election", "elections", "governance"],
        "paths": ["/politics", "/election", "/government"],
        "keywords": [
            "inec",
            "apc",
            "pdp",
            "labour party",
            "senate",
            "senator",
            "house of representatives",
            "national assembly",
            "governor",
            "governors",
            "president",
            "presidency",
            "tinubu",
            "minister",
            "ministry",
            "election",
            "elections",
            "campaign",
            "primaries",
            "ballot",
            "impeachment",
            "lawmaker",
            "lawmakers",
            "defection",
            "constitution",
            "bill",
            "motion",
        ],
    },
    "technology": {
        "feeds": {
            "Nairametrics (Tech)": "https://nairametrics.com/category/tech-news/feed",
            "Premium Times (Tech)": (
                "https://www.premiumtimesng.com/category/news/technology/feed"
            ),
            "Legit (Tech)": "https://www.legit.ng/rss/technology.rss",
        },
        "tags": ["tech", "technology", "science", "gadgets", "telecoms", "innovation"],
        "paths": ["/tech", "/technology", "/science", "/gadget"],
        "keywords": [
            "tech",
            "technology",
            "startup",
            "startups",
            "app",
            "software",
            "hardware",
            "smartphone",
            "iphone",
            "android",
            "google",
            "microsoft",
            "apple",
            "meta",
            "openai",
            "artificial intelligence",
            "ai",
            "machine learning",
            "data centre",
            "data center",
            "cybersecurity",
            "hackers",
            "hacked",
            "broadband",
            "telecom",
            "telecoms",
            "mtn",
            "airtel",
            "glo",
            "ncc",
            "5g",
            "internet",
            "developers",
            "blockchain",
            "satellite",
        ],
    },
    "health": {
        "feeds": {
            "Premium Times (Health)": ("https://www.premiumtimesng.com/category/news/health/feed"),
            "PM News (Health)": "https://www.pmnewsnigeria.com/category/health/feed",
        },
        "tags": ["health", "healthcare", "medical", "medicine", "wellness"],
        "paths": ["/health", "/medical"],
        "keywords": [
            "health",
            "hospital",
            "hospitals",
            "doctor",
            "doctors",
            "nurse",
            "nurses",
            "patients",
            "disease",
            "outbreak",
            "epidemic",
            "cholera",
            "malaria",
            "lassa fever",
            "covid",
            "vaccine",
            "vaccination",
            "nafdac",
            "nphcda",
            "mental health",
            "cancer",
            "hiv",
            "tuberculosis",
            "maternal",
            "drugs",
            "clinic",
            "surgery",
            "immunisation",
            "immunization",
        ],
    },
    "education": {
        "feeds": {
            "Premium Times (Education)": (
                "https://www.premiumtimesng.com/category/news/education/feed"
            ),
            "PM News (Education)": ("https://www.pmnewsnigeria.com/category/education/feed"),
            "Legit (Education)": "https://www.legit.ng/rss/education.rss",
        },
        "tags": ["education", "school", "schools", "campus", "students"],
        "paths": ["/education", "/campus", "/school"],
        "keywords": [
            "jamb",
            "waec",
            "neco",
            "utme",
            "asuu",
            "nysc",
            "university",
            "universities",
            "polytechnic",
            "college",
            "undergraduate",
            "postgraduate",
            "student",
            "students",
            "pupils",
            "school",
            "schools",
            "lecturer",
            "lecturers",
            "vice-chancellor",
            "admission",
            "admissions",
            "scholarship",
            "scholarships",
            "tuition",
            "curriculum",
            "exam",
            "exams",
            "graduation",
            "alumni",
            "tetfund",
        ],
    },
}

GENERAL_FEEDS: dict[str, str] = {
    "Premium Times": "https://www.premiumtimesng.com/feed",
    "Nairametrics": "https://nairametrics.com/feed",
    "The Cable": "https://www.thecable.ng/feed",
    "PM News": "https://www.pmnewsnigeria.com/feed",
    "Vanguard": "https://www.vanguardngr.com/feed",
    "Daily Post": "https://dailypost.ng/feed",
    "The Nation": "https://thenationonlineng.net/feed",
    "Channels TV": "https://www.channelstv.com/feed",
    "Naija News": "https://www.naijanews.com/feed",
    "Legit": "https://www.legit.ng/rss/all.rss",
    "Nigerian Eye": "https://feeds.feedburner.com/Nigerianeye",
    "Sahara Reporters": "https://saharareporters.com/feed",
}

CATEGORY_ALIASES: dict[str, str] = {
    "finance": "business",
    "financial": "business",
    "financies": "business",
    "money": "business",
    "economy": "business",
    "economics": "business",
    "market": "business",
    "markets": "business",
    "showbiz": "entertainment",
    "celebrity": "entertainment",
    "nollywood": "entertainment",
    "lifestyle": "entertainment",
    "music": "entertainment",
    "sport": "sports",
    "football": "sports",
    "tech": "technology",
    "science": "technology",
    "political": "politics",
    "government": "politics",
    "medical": "health",
    "wellness": "health",
    "school": "education",
    "schools": "education",
    "campus": "education",
}


class NewsInvalidCategoryError(Exception):
    """Raised when request provides unrecognized categories."""

    def __init__(self, invalid: list[str]) -> None:
        super().__init__(f"Unknown categories: {invalid}")
        self.invalid = invalid


class ArticleHtmlParser(HTMLParser):
    """Parses article HTML for og:image/twitter:image and main paragraph text."""

    def __init__(self) -> None:
        super().__init__()
        self.image: str | None = None
        self.paragraphs: list[str] = []
        self._current_p: list[str] = []
        self._in_p = False
        self._ignore_stack: list[str] = []
        self._ignore_tags = {
            "script",
            "style",
            "nav",
            "header",
            "footer",
            "aside",
            "form",
            "figure",
        }

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag_lower = tag.lower()
        attr_dict = {k.lower(): v for k, v in attrs if v is not None}
        if tag_lower in self._ignore_tags:
            self._ignore_stack.append(tag_lower)
            return
        if self._ignore_stack:
            return

        if tag_lower == "meta" and not self.image:
            prop = attr_dict.get("property", "").lower()
            name = attr_dict.get("name", "").lower()
            if prop in ("og:image",) or name in ("og:image", "twitter:image"):
                val = attr_dict.get("content", "").strip()
                if val:
                    self.image = val
        elif tag_lower == "p":
            self._in_p = True
            self._current_p = []

    def handle_endtag(self, tag: str) -> None:
        tag_lower = tag.lower()
        if self._ignore_stack:
            if self._ignore_stack[-1] == tag_lower:
                self._ignore_stack.pop()
            return

        if tag_lower == "p" and self._in_p:
            self._in_p = False
            raw_text = " ".join(self._current_p)
            cleaned = re.sub(r"\s+", " ", raw_text).strip()
            if len(cleaned) > 40:
                self.paragraphs.append(cleaned)
            self._current_p = []

    def handle_data(self, data: str) -> None:
        if not self._ignore_stack and self._in_p:
            self._current_p.append(data)


def html_to_text(html: str | None) -> str | None:
    """Strip tags and scripts from RSS descriptions, preserving paragraphs."""
    if not html:
        return None
    cleaned = re.sub(r"<(script|style)[^>]*>.*?</\1>", "", html, flags=re.IGNORECASE | re.DOTALL)
    cleaned = re.sub(r"</p>", "</p>\n\n", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"<[^>]+>", "", cleaned)
    text = html_unescape(cleaned).strip()
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text if text else None


def slugify_title(text: str) -> str:
    """Build a clean URL-friendly slug from a headline, max 80 characters."""
    unescaped = html_unescape(text)
    stripped = re.sub(r"<[^>]+>", "", unescaped).strip().lower()
    normalized = unicodedata.normalize("NFKD", stripped).encode("ascii", "ignore").decode("ascii")
    slug = re.sub(r"[^a-z0-9]+", "-", normalized).strip("-")
    slug = re.sub(r"-+", "-", slug)
    if not slug:
        return "article"
    return slug[:80]


def canonical_category(requested: str) -> str | None:
    """Normalize a requested topic slug or alias, or return None if not a topic."""
    norm = requested.strip().lower()
    if norm in ("", "all", "general"):
        return "all"
    if norm in CATEGORY_ALIASES:
        return CATEGORY_ALIASES[norm]
    if norm in CATEGORY_DEFS:
        return norm
    return None


def parse_categories(
    raw_input: Any,
) -> tuple[list[str], list[str], list[str]]:
    """Parse categories parameter into canonical slugs, free keywords, and invalid values."""
    if isinstance(raw_input, list):
        parts: list[str] = [str(x) for x in raw_input if x is not None]
    else:
        parts = re.split(r"[,|;\r\n]+", str(raw_input or ""))

    slugs_dict: dict[str, bool] = {}
    keywords_dict: dict[str, bool] = {}
    invalid: list[str] = []
    saw_all = False

    for part in parts:
        raw = part.strip()
        if not raw:
            continue
        canon = canonical_category(raw)
        if canon == "all":
            saw_all = True
            continue
        if canon is not None:
            slugs_dict[canon] = True
            continue

        # Free keyword
        kw = re.sub(r"\s+", " ", raw).strip().lower()
        if not kw or not re.search(r"[\w]", kw) or len(kw) > 60:
            invalid.append(raw)
            continue
        keywords_dict[kw] = True

    slugs = [] if saw_all else sorted(slugs_dict.keys())
    keywords = sorted(keywords_dict.keys())
    dedup_invalid = list(dict.fromkeys(invalid))

    return slugs, keywords, dedup_invalid


def candidate_matches_category(candidate: dict[str, Any], def_data: dict[str, Any]) -> bool:
    """Check if candidate item matches a category definition by tags, URL path, or keywords."""
    # 1. RSS Category tags
    for cat in candidate.get("categories", []):
        cat_lower = str(cat).lower()
        for tag in def_data["tags"]:
            if tag in cat_lower:
                return True

    # 2. URL path segment
    path = urlparse(candidate.get("url", "")).path.lower()
    if path:
        for seg in def_data["paths"]:
            if seg in path:
                return True

    # 3. Whole-word keywords in headline + description
    haystack = f"{candidate.get('title', '')} {candidate.get('description', '')}".strip().lower()
    if not haystack:
        return False

    for kw in def_data["keywords"]:
        pattern = rf"(?<![\w]){re.escape(kw)}(?![\w])"
        if re.search(pattern, haystack):
            return True

    return False


def candidate_matches_keyword(candidate: dict[str, Any], keyword: str) -> bool:
    """Check if candidate matches a free keyword (whole word with up to 3 suffix letters)."""
    suffix = r"[\w]{0,3}" if len(keyword) >= 5 else ""
    pattern = rf"(?<![\w]){re.escape(keyword)}{suffix}(?![\w])"

    for cat in candidate.get("categories", []):
        if re.search(pattern, str(cat).lower()):
            return True

    path = urlparse(candidate.get("url", "")).path.lower()
    if path and re.search(pattern, path):
        return True

    haystack = f"{candidate.get('title', '')} {candidate.get('description', '')}".strip().lower()
    return bool(haystack and re.search(pattern, haystack))


class NewsService:
    """Encapsulates RSS feeds aggregation, filtering, enrichment, and caching."""

    def __init__(
        self,
        session: Session,
        settings: Settings,
        *,
        http_client: httpx.Client | None = None,
    ) -> None:
        self._session = session
        self._settings = settings
        self._repo = NewsRepository(session)
        self._http_client = http_client

    def get_cache_path(self, cache_key: str, limit: int) -> Path:
        """Resolve destination path for 15-minute file cache."""
        cache_dir = self._settings.upload_root / "cache" / "news"
        cache_dir.mkdir(parents=True, exist_ok=True)
        return cache_dir / f"news_feeds_{cache_key}_{limit}.json"

    def fetch_feed_xml(self, client: httpx.Client, url: str) -> tuple[Any | None, str | None]:
        """Fetch and parse a single RSS feed safely."""
        try:
            resp = client.get(url)
            if resp.status_code in (403, 503) or "Just a moment" in resp.text:
                return None, f"blocked (HTTP {resp.status_code})"
            if resp.status_code != 200:
                return None, f"HTTP {resp.status_code}"
            root = fromstring(resp.content)
            return root, None
        except Exception as exc:
            return None, str(exc)

    def enrich_article_page(
        self, client: httpx.Client, page_url: str
    ) -> tuple[str | None, str | None]:
        """Fetch article HTML page and extract plain text paragraphs + og:image."""
        try:
            resp = client.get(page_url)
            if resp.status_code in (403, 503) or not resp.text.strip():
                return None, None
            parser = ArticleHtmlParser()
            parser.feed(resp.text)
            text = "\n\n".join(parser.paragraphs).strip() if parser.paragraphs else None
            return text, parser.image
        except Exception:
            return None, None

    def get_feeds(
        self,
        *,
        category_param: Any | None = None,
        limit: int = 50,
        refresh: bool = False,
    ) -> tuple[NewsFeedsResponse, bool]:
        """Orchestrate news aggregation with category filtering, enrichment, and caching."""
        target_count = min(max(int(limit), 1), 100)

        # 1. Category source determination
        has_sent_cat = (
            category_param is not None
            and (isinstance(category_param, list) and len(category_param) > 0)
        ) or (isinstance(category_param, str) and category_param.strip() != "")

        if has_sent_cat:
            category_source = "request"
            requested = category_param
        else:
            db_cat = self._repo.get_setup_news_category()
            if db_cat:
                category_source = "setup_parameters"
                requested = db_cat
            else:
                category_source = "default"
                requested = ""

        selected, keywords, invalid = parse_categories(requested)

        ignored_categories: list[str] = []
        if invalid and category_source != "request":
            ignored_categories = invalid
            invalid = []

        if invalid:
            raise NewsInvalidCategoryError(invalid)

        filters = selected + keywords
        category_str = ",".join(filters) if filters else "all"

        # 2. Cache key calculation
        cache_key = "-".join(selected) if selected else "all"
        if keywords:
            kw_hash = hashlib.md5(
                ",".join(keywords).encode("utf-8"), usedforsecurity=False
            ).hexdigest()[:10]
            cache_key = f"{cache_key}-kw{kw_hash}"

        cache_file = self.get_cache_path(cache_key, target_count)
        cache_ttl = 900  # 15 minutes

        if not refresh and cache_file.exists():
            with contextlib.suppress(Exception):
                mtime = cache_file.stat().st_mtime
                now_ts = datetime.datetime.now(datetime.UTC).timestamp()
                if (now_ts - mtime) < cache_ttl:
                    content = cache_file.read_text(encoding="utf-8")
                    cached_data = json.loads(content)
                    return NewsFeedsResponse.model_validate(cached_data), True

        # 3. Assemble feeds list
        cat_feed_names: dict[str, str] = {}
        topic_feeds: dict[str, str] = {}
        for slug in selected:
            for c_name, c_url in CATEGORY_DEFS[slug]["feeds"].items():
                cat_feed_names[c_name] = slug
                topic_feeds[c_name] = c_url

        all_feeds = {**topic_feeds, **GENERAL_FEEDS}

        # 4. Fetch RSS feeds
        per_feed_limit = 20
        candidates: list[dict[str, Any]] = []
        candidate_index: dict[str, int] = {}
        feed_report: dict[str, str] = {}

        client = self._http_client or httpx.Client(
            timeout=25.0,
            follow_redirects=True,
            headers={"User-Agent": "Mozilla/5.0 (compatible; NewsAggregator/1.0)"},
        )

        try:
            for feed_name, feed_url in all_feeds.items():
                root, err = self.fetch_feed_xml(client, feed_url)
                if root is None or err is not None:
                    feed_report[feed_name] = f"failed: {err or 'empty response'}"
                    continue

                items = root.findall(".//item")
                count = 0

                for item in items:
                    if count >= per_feed_limit:
                        break

                    title = (item.findtext("title") or "").strip()
                    link = (item.findtext("link") or "").strip()
                    raw_desc = item.findtext("description") or ""
                    desc = html_to_text(raw_desc) or ""
                    clean_source = re.sub(r"\s*\([^)]*\)$", "", feed_name).strip()

                    # Categories
                    item_cats: list[str] = []
                    for c_el in item.findall("category"):
                        c_text = (c_el.text or "").strip()
                        if c_text:
                            item_cats.append(html_unescape(c_text))
                    item_cats = list(dict.fromkeys(item_cats))

                    # Pub date
                    pub_ts = 0.0
                    pub_date_str = item.findtext("pubDate")
                    if pub_date_str:
                        with contextlib.suppress(Exception):
                            dt = parsedate_to_datetime(pub_date_str)
                            pub_ts = dt.timestamp()

                    candidate: dict[str, Any] = {
                        "title": title,
                        "description": desc,
                        "source": clean_source,
                        "url": link,
                        "pub_ts": pub_ts,
                        "categories": item_cats,
                    }

                    # Matching
                    matched: list[str] = []
                    if feed_name in cat_feed_names:
                        matched.append(cat_feed_names[feed_name])

                    for slug in selected:
                        if slug not in matched and candidate_matches_category(
                            candidate, CATEGORY_DEFS[slug]
                        ):
                            matched.append(slug)

                    for kw in keywords:
                        if candidate_matches_keyword(candidate, kw):
                            matched.append(kw)

                    if filters and not matched:
                        continue

                    candidate["matched_categories"] = matched

                    # Deduplication by URL
                    dup_key = link.rstrip("/").lower()
                    if dup_key and dup_key in candidate_index:
                        prev_idx = candidate_index[dup_key]
                        merged = list(
                            dict.fromkeys(candidates[prev_idx]["matched_categories"] + matched)
                        )
                        candidates[prev_idx]["matched_categories"] = merged
                        count += 1
                        continue

                    candidate_index[dup_key] = len(candidates)
                    candidates.append(candidate)
                    count += 1

                feed_report[feed_name] = (
                    f"ok ({count} items matching {', '.join(filters)})"
                    if filters
                    else f"ok ({count} items)"
                )

            # Sort newest first across all feeds
            candidates.sort(key=lambda c: float(c.get("pub_ts", 0.0)), reverse=True)

            # 5. Page enrichment
            articles: list[NewsArticleItem] = []
            enriched = 0
            max_enrich = 110
            seen_urls: set[str] = set()

            for cand in candidates:
                if len(articles) >= target_count or enriched >= max_enrich:
                    break
                url = cand.get("url", "")
                if not url:
                    continue
                url_key = url.rstrip("/").lower()
                if url_key in seen_urls:
                    continue
                seen_urls.add(url_key)

                enriched += 1
                text, image = self.enrich_article_page(client, url)

                # Only keep articles that have both full text and an image
                if not text or not image:
                    continue

                pub_iso: str | None = None
                if cand.get("pub_ts"):
                    pub_iso = datetime.datetime.fromtimestamp(
                        cand["pub_ts"], datetime.UTC
                    ).isoformat()

                article_id = hashlib.md5(url.encode("utf-8"), usedforsecurity=False).hexdigest()[
                    :12
                ]
                article_slug = slugify_title(cand.get("title", ""))

                articles.append(
                    NewsArticleItem(
                        id=article_id,
                        slug=article_slug,
                        title=cand.get("title", ""),
                        description=cand.get("description"),
                        source=cand.get("source", ""),
                        image=image,
                        url=url,
                        published_at=pub_iso,
                        fullcontent=text,
                        content_source="page",
                        categories=cand.get("categories", []),
                        matched_categories=cand.get("matched_categories", []),
                    )
                )

            # Rollup per-source counts
            source_counts: dict[str, int] = {}
            for a in articles:
                source_counts[a.source] = source_counts.get(a.source, 0) + 1
            sorted_sources = dict(
                sorted(source_counts.items(), key=lambda item: item[1], reverse=True)
            )

            selected_categories = selected if selected else ([] if keywords else ["all"])

            response = NewsFeedsResponse(
                status=200,
                message="News feeds retrieved successfully",
                count=len(articles),
                category=category_str,
                selected_categories=selected_categories,
                keywords=keywords,
                category_source=category_source,
                ignored_categories=ignored_categories,
                available_categories=AVAILABLE_CATEGORIES,
                generated_at=datetime.datetime.now(datetime.UTC).isoformat(),
                pages_fetched=enriched,
                sources=sorted_sources,
                feeds=feed_report,
                articles=articles,
            )

            # Write cache
            with contextlib.suppress(Exception):
                cache_file.write_text(
                    response.model_dump_json(exclude_none=False), encoding="utf-8"
                )

            return response, False

        finally:
            if self._http_client is None:
                client.close()
