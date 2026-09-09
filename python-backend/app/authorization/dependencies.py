"""Reusable access-token authorization dependencies."""

from __future__ import annotations

from dataclasses import dataclass

from fastapi import HTTPException, Request, status

from app.core.config import Settings
from app.core.errors import InvalidAccessTokenError, TokenConfigurationError
from app.core.security import TokenService


@dataclass(frozen=True, slots=True)
class AccessPrincipal:
    """Identity and coarse legacy role asserted by a validated access JWT."""

    user_id: int
    email: str
    user_role: str | None


def require_access(request: Request) -> AccessPrincipal:
    """Validate a Bearer access token and return its bounded identity claims."""
    authorization = request.headers.get("authorization", "")
    scheme, separator, token = authorization.partition(" ")
    if separator != " " or scheme.casefold() != "bearer" or not token.strip():
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authentication required",
            headers={"WWW-Authenticate": "Bearer"},
        )
    settings: Settings = request.app.state.settings
    try:
        claims = TokenService(settings).decode_access(token.strip())
        user_id = int(claims["sub"])
        if user_id <= 0:
            raise ValueError
    except (InvalidAccessTokenError, KeyError, TypeError, ValueError) as exc:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid access token",
            headers={"WWW-Authenticate": "Bearer"},
        ) from exc
    except TokenConfigurationError as exc:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Authentication service is unavailable",
        ) from exc
    return AccessPrincipal(
        user_id=user_id,
        email=str(claims.get("email", "")),
        user_role=str(claims["user_role"]) if claims.get("user_role") is not None else None,
    )
