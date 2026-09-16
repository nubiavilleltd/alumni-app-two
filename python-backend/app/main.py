"""FastAPI application factory and production entry point."""

from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Any

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.staticfiles import StaticFiles

from app.api.announcements import router as announcements_router
from app.api.auth import router as auth_router
from app.api.health import router as health_router
from app.api.leadership import router as leadership_router
from app.api.marketplace import router as marketplace_router
from app.api.members import router as members_router
from app.api.notifications import router as notifications_router
from app.api.projects import router as projects_router
from app.api.retired import router as retired_router
from app.core.config import Settings, get_settings
from app.core.logging import configure_logging
from app.core.middleware import CorrelationIdMiddleware
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
    app.include_router(health_router)
    app.include_router(auth_router)
    app.include_router(announcements_router)
    app.include_router(members_router)
    app.include_router(marketplace_router)
    app.include_router(projects_router)
    app.include_router(leadership_router)
    app.include_router(notifications_router)
    app.include_router(retired_router)
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

    @app.exception_handler(HTTPException)
    async def http_exception_handler(_request: Request, exc: HTTPException) -> JSONResponse:
        message = exc.detail if isinstance(exc.detail, str) else "Request failed"
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
