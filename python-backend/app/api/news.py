"""Router for news feeds aggregation matching CodeIgniter News controller."""

from __future__ import annotations

from typing import Any

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse
from starlette.concurrency import run_in_threadpool

from app.api.common import body_mapping, with_session
from app.core.config import Settings
from app.db.session import Database
from app.repositories.news import NewsRepository
from app.schemas.news import (
    NewsAuthErrorResponse,
    NewsErrorResponse,
    NewsFeedsResponse,
)
from app.services.news import (
    AVAILABLE_CATEGORIES,
    NewsInvalidCategoryError,
    NewsService,
)

router = APIRouter(prefix="/news", tags=["news"])


def _extract_param(mapping: dict[str, Any], query_params: Any, key: str) -> Any:
    """Extract parameter from either parsed body or query string."""
    if key in mapping:
        return mapping[key]
    if key in query_params:
        return query_params[key]
    return None


async def _handle_feeds(request: Request) -> JSONResponse:
    """Shared handler for GET and POST /news/feeds."""
    # 1. Verify API Key from X-API-Key header
    api_key = request.headers.get("x-api-key")
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings

    is_valid_key = await run_in_threadpool(
        with_session,
        database,
        lambda session: NewsRepository(session).verify_api_key(api_key),
    )
    if not is_valid_key:
        return JSONResponse(
            status_code=401,
            content=NewsAuthErrorResponse().model_dump(),
        )

    # 2. Extract inputs from query and body
    body = await body_mapping(request)
    query_params = request.query_params

    raw_category = _extract_param(body, query_params, "category")
    raw_limit = _extract_param(body, query_params, "limit")
    raw_refresh = _extract_param(body, query_params, "refresh")

    limit = 50
    if raw_limit is not None:
        try:
            limit = int(raw_limit)
        except (ValueError, TypeError):
            limit = 50

    refresh = False
    if raw_refresh is not None:
        if isinstance(raw_refresh, bool):
            refresh = raw_refresh
        elif isinstance(raw_refresh, (int, float)):
            refresh = raw_refresh != 0
        elif isinstance(raw_refresh, str):
            refresh = raw_refresh.strip().lower() in ("1", "true", "yes", "on")

    # 3. Execute aggregator service
    try:
        response, is_hit = await run_in_threadpool(
            with_session,
            database,
            lambda session: NewsService(session, settings).get_feeds(
                category_param=raw_category,
                limit=limit,
                refresh=refresh,
            ),
        )
    except NewsInvalidCategoryError as exc:
        msg = "Unknown category" if len(exc.invalid) == 1 else "Unknown categories"
        return JSONResponse(
            status_code=400,
            content=NewsErrorResponse(
                status=400,
                message=msg,
                invalid_categories=exc.invalid,
                available_categories=AVAILABLE_CATEGORIES,
            ).model_dump(),
        )

    headers = {"X-Cache": "HIT" if is_hit else "MISS"}
    return JSONResponse(
        status_code=200,
        content=response.model_dump(),
        headers=headers,
    )


@router.get(
    "/feeds",
    response_model=NewsFeedsResponse,
    responses={
        200: {"model": NewsFeedsResponse, "description": "News feeds retrieved"},
        400: {"model": NewsErrorResponse, "description": "Invalid category"},
        401: {"model": NewsAuthErrorResponse, "description": "Invalid API token"},
    },
)
async def get_feeds(request: Request) -> JSONResponse:
    """Retrieve aggregated Nigerian news feeds (GET)."""
    return await _handle_feeds(request)


@router.post(
    "/feeds",
    response_model=NewsFeedsResponse,
    responses={
        200: {"model": NewsFeedsResponse, "description": "News feeds retrieved"},
        400: {"model": NewsErrorResponse, "description": "Invalid category"},
        401: {"model": NewsAuthErrorResponse, "description": "Invalid API token"},
    },
)
async def post_feeds(request: Request) -> JSONResponse:
    """Retrieve aggregated Nigerian news feeds (POST)."""
    return await _handle_feeds(request)
