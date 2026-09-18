"""Server-side Google and Facebook identity verification."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, cast
from urllib.parse import quote

import httpx
import structlog

logger = structlog.get_logger(__name__)


@dataclass(frozen=True, slots=True)
class SocialIdentity:
    """A provider-verified identity, never trusted from the client."""

    provider: str
    provider_user_id: str
    email: str | None
    name: str | None
    avatar_url: str | None


class SocialVerificationError(Exception):
    """A provider token could not be verified."""

    def __init__(self, message: str, http_status: int = 401) -> None:
        super().__init__(message)
        self.message = message
        self.http_status = http_status


class GoogleIdentityProvider:
    """Verify a Google ID token via the tokeninfo endpoint and enforce the client-id audience."""

    def __init__(self, client_id: str, http_client: httpx.Client | None = None) -> None:
        self._client_id = client_id
        self._http_client = http_client

    def verify(self, id_token: str) -> SocialIdentity:
        url = f"https://oauth2.googleapis.com/tokeninfo?id_token={quote(id_token)}"
        payload = self._get_json(url)
        if not payload or not payload.get("sub"):
            raise SocialVerificationError("Invalid Google token response")
        audience = payload.get("aud")
        if not audience or audience != self._client_id:
            raise SocialVerificationError("Token was not issued for this application")
        email_verified = payload.get("email_verified")
        if email_verified is not None and email_verified not in (True, "true"):
            raise SocialVerificationError("Google email is not verified")
        name = payload.get("name") or " ".join(
            filter(None, [payload.get("given_name"), payload.get("family_name")])
        )
        return SocialIdentity(
            provider="google",
            provider_user_id=str(payload["sub"]),
            email=payload.get("email"),
            name=name or None,
            avatar_url=payload.get("picture"),
        )

    def _get_json(self, url: str) -> dict[str, Any] | None:
        try:
            if self._http_client is not None:
                response = self._http_client.get(url, timeout=8.0)
            else:
                with httpx.Client(timeout=8.0) as client:
                    response = client.get(url)
        except httpx.HTTPError as exc:
            logger.error("google_verify_request_failed", error=str(exc))
            raise SocialVerificationError("Could not reach Google to verify token") from exc
        if response.status_code != 200:
            raise SocialVerificationError("Invalid or expired Google token")
        try:
            return cast(dict[str, Any], response.json())
        except ValueError as exc:
            raise SocialVerificationError("Invalid Google token response") from exc


class FacebookIdentityProvider:
    """Verify a Facebook access token via debug_token and fetch the verified profile."""

    def __init__(
        self,
        app_id: str,
        app_secret: str,
        http_client: httpx.Client | None = None,
    ) -> None:
        self._app_id = app_id
        self._app_token = f"{app_id}|{app_secret}"
        self._http_client = http_client

    def verify(self, access_token: str) -> SocialIdentity:
        debug = self._get_json(
            "https://graph.facebook.com/debug_token?"
            f"input_token={quote(access_token)}&access_token={quote(self._app_token)}"
        )
        if not debug or not debug.get("data", {}).get("is_valid"):
            raise SocialVerificationError("Invalid or expired Facebook token")
        app_id = debug["data"].get("app_id")
        if not app_id or str(app_id) != self._app_id:
            raise SocialVerificationError("Token was not issued for this application")
        profile = self._get_json(
            "https://graph.facebook.com/me?"
            f"fields=id,name,email,picture.type(large)&access_token={quote(access_token)}"
        )
        if not profile or not profile.get("id"):
            raise SocialVerificationError("Invalid Facebook profile response")
        return SocialIdentity(
            provider="facebook",
            provider_user_id=str(profile["id"]),
            email=profile.get("email"),
            name=profile.get("name"),
            avatar_url=(profile.get("picture", {}) or {}).get("data", {}).get("url"),
        )

    def _get_json(self, url: str) -> dict[str, Any] | None:
        try:
            if self._http_client is not None:
                response = self._http_client.get(url, timeout=8.0)
            else:
                with httpx.Client(timeout=8.0) as client:
                    response = client.get(url)
        except httpx.HTTPError as exc:
            logger.error("facebook_verify_request_failed", error=str(exc))
            raise SocialVerificationError("Could not reach Facebook to verify token") from exc
        if response.status_code != 200:
            raise SocialVerificationError("Invalid or expired Facebook token")
        try:
            return cast(dict[str, Any], response.json())
        except ValueError as exc:
            raise SocialVerificationError("Invalid Facebook response") from exc
