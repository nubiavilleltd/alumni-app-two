"""FastAPI application factory and production entry point."""

from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Any

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from starlette.staticfiles import StaticFiles

from app.api.announcements import router as announcements_router
from app.api.auth import router as auth_router
from app.api.blog import router as blog_router
from app.api.chat import router as chat_router
from app.api.contact import router as contact_router
from app.api.events import router as events_router
from app.api.health import router as health_router
from app.api.leadership import router as leadership_router
from app.api.marketplace import router as marketplace_router
from app.api.members import router as members_router
from app.api.metrics import router as metrics_router
from app.api.news import router as news_router
from app.api.notifications import router as notifications_router
from app.api.product import paystack_webhook_alias_router, product_router
from app.api.projects import router as projects_router
from app.api.push import router as push_router
from app.api.retired import router as retired_router
from app.api.social import router as social_router
from app.api.vacancies import router as vacancies_router
from app.core.config import Settings, get_settings
from app.core.logging import configure_logging
from app.core.metrics import MetricsMiddleware
from app.core.middleware import CorrelationIdMiddleware, SecurityHeadersMiddleware
from app.core.rate_limit import RateLimiter, build_rate_limiter
from app.db.session import Database
from app.integrations.mail import Mailer, SmtpMailer


def create_app(
    settings: Settings | None = None,
    mailer: Mailer | None = None,
    rate_limiter: RateLimiter | None = None,
) -> FastAPI:
    """Build an application using explicit settings when supplied by tests."""
    resolved_settings = settings or get_settings()
    configure_logging()
    database = Database(resolved_settings)
    resolved_rate_limiter = (
        rate_limiter if rate_limiter is not None else build_rate_limiter(resolved_settings)
    )

    @asynccontextmanager
    async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
        yield
        database.dispose()
        resolved_rate_limiter.close()

    docs_url = "/docs" if resolved_settings.expose_docs else None
    redoc_url = "/redoc" if resolved_settings.expose_docs else None
    openapi_url = "/openapi.json" if resolved_settings.expose_docs else None
    app = FastAPI(
        title=resolved_settings.app_name,
        docs_url=docs_url,
        redoc_url=redoc_url,
        openapi_url=openapi_url,
        lifespan=lifespan,
    )
    app.state.database = database
    app.state.settings = resolved_settings
    app.state.mailer = mailer if mailer is not None else SmtpMailer(resolved_settings)
    app.state.rate_limiter = resolved_rate_limiter
    app.add_middleware(CorrelationIdMiddleware)
    app.add_middleware(SecurityHeadersMiddleware)
    if resolved_settings.metrics_enabled:
        app.add_middleware(MetricsMiddleware)
    if resolved_settings.cors_origins:
        app.add_middleware(
            CORSMiddleware,
            allow_origins=resolved_settings.cors_origins,
            allow_credentials=False,
            allow_methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
            allow_headers=["Authorization", "Content-Type", "X-API-Key", "X-Correlation-ID"],
        )
    app.include_router(health_router)
    app.include_router(metrics_router)
    app.include_router(auth_router)
    app.include_router(chat_router)
    app.include_router(events_router)
    app.include_router(contact_router)
    app.include_router(announcements_router)
    app.include_router(members_router)
    app.include_router(marketplace_router)
    app.include_router(projects_router)
    app.include_router(leadership_router)
    app.include_router(vacancies_router)
    app.include_router(blog_router)
    app.include_router(news_router)
    app.include_router(notifications_router)
    app.include_router(push_router)
    app.include_router(retired_router)
    app.include_router(social_router)
    app.include_router(product_router)
    app.include_router(paystack_webhook_alias_router)
    app.mount(
        "/uploads/profiles",
        StaticFiles(directory=resolved_settings.upload_root / "profiles", check_dir=False),
        name="profile-uploads",
    )
    app.mount(
        "/uploads/announcements",
        StaticFiles(directory=resolved_settings.upload_root / "announcements", check_dir=False),
        name="announcement-uploads",
    )
    app.mount(
        "/uploads/marketplace",
        StaticFiles(directory=resolved_settings.upload_root / "marketplace", check_dir=False),
        name="marketplace-uploads",
    )
    app.mount(
        "/uploads/projects",
        StaticFiles(directory=resolved_settings.upload_root / "projects", check_dir=False),
        name="project-uploads",
    )
    app.mount(
        "/uploads/leadership",
        StaticFiles(directory=resolved_settings.upload_root / "leadership", check_dir=False),
        name="leadership-uploads",
    )
    app.mount(
        "/uploads/vacancies",
        StaticFiles(directory=resolved_settings.upload_root / "vacancies", check_dir=False),
        name="vacancy-uploads",
    )
    app.mount(
        "/uploads/events",
        StaticFiles(directory=resolved_settings.upload_root / "events", check_dir=False),
        name="event-uploads",
    )
    app.mount(
        "/uploads/homepage/carousel",
        StaticFiles(
            directory=resolved_settings.upload_root / "homepage" / "carousel", check_dir=False
        ),
        name="carousel-uploads",
    )
    app.mount(
        "/uploads/blog/gallery",
        StaticFiles(directory=resolved_settings.upload_root / "blog" / "gallery", check_dir=False),
        name="blog-gallery-uploads",
    )
    app.mount(
        "/uploads/products",
        StaticFiles(directory=resolved_settings.upload_root / "products", check_dir=False),
        name="product-uploads",
    )

    @app.exception_handler(HTTPException)
    async def http_exception_handler(_request: Request, exc: HTTPException) -> JSONResponse:
        if isinstance(exc.detail, str):
            message = exc.detail
        elif isinstance(exc.detail, dict) and "message" in exc.detail:
            message = str(exc.detail["message"])
        else:
            message = "Request failed"
        return JSONResponse(
            status_code=exc.status_code,
            content={"error": {"code": "http_error", "message": message}},
            headers=exc.headers,
        )

    @app.exception_handler(RequestValidationError)
    async def validation_exception_handler(
        _request: Request,
        exc: RequestValidationError,
    ) -> JSONResponse:
        details: list[dict[str, Any]] = []
        for error in exc.errors():
            details.append(
                {
                    "type": error.get("type"),
                    "location": list(error.get("loc", ())),
                    "message": error.get("msg"),
                }
            )
        return JSONResponse(
            status_code=422,
            content={
                "error": {
                    "code": "validation_error",
                    "message": "Request validation failed",
                    "details": details,
                }
            },
        )

    return app


app = create_app()
