"""Wire schemas for news feeds aggregation matching PHP and frontend contracts."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class NewsArticleItem(BaseModel):
    """Normalized article representation returned to the frontend."""

    model_config = ConfigDict(extra="ignore")

    id: str
    slug: str
    title: str
    description: str | None = None
    source: str
    image: str | None = None
    url: str
    published_at: str | None = None
    fullcontent: str | None = None
    content_source: str = "page"
    categories: list[str] = Field(default_factory=list)
    matched_categories: list[str] = Field(default_factory=list)


class NewsFeedsQuery(BaseModel):
    """Input parameters for retrieving aggregated news feeds."""

    model_config = ConfigDict(extra="ignore")

    category: Any | None = None
    limit: int | None = 50
    refresh: Any | None = None


class NewsFeedsResponse(BaseModel):
    """Full envelope response matching CodeIgniter News::feeds output."""

    model_config = ConfigDict(extra="ignore")

    status: int = 200
    message: str = "News feeds retrieved successfully"
    count: int = 0
    category: str = "all"
    selected_categories: list[str] = Field(default_factory=list)
    keywords: list[str] = Field(default_factory=list)
    category_source: str = "default"
    ignored_categories: list[str] = Field(default_factory=list)
    available_categories: list[str] = Field(default_factory=list)
    generated_at: str
    pages_fetched: int = 0
    sources: dict[str, int] = Field(default_factory=dict)
    feeds: dict[str, str] = Field(default_factory=dict)
    articles: list[NewsArticleItem] = Field(default_factory=list)


class NewsErrorResponse(BaseModel):
    """Error envelope returned on invalid topic categories."""

    model_config = ConfigDict(extra="ignore")

    status: int = 400
    message: str
    invalid_categories: list[str] = Field(default_factory=list)
    available_categories: list[str] = Field(default_factory=list)


class NewsAuthErrorResponse(BaseModel):
    """Error envelope returned when API key verification fails."""

    model_config = ConfigDict(extra="ignore")

    status: int = 401
    message: str = "Invalid API token"
