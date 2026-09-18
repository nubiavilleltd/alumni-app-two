"""Goal 4 social login provider verification and account-mapping tests."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path
from typing import Any, cast

import pytest
from sqlalchemy import select

from app.integrations.social import (
    FacebookIdentityProvider,
    GoogleIdentityProvider,
    SocialIdentity,
    SocialVerificationError,
)
from app.models.generated import Users, UserSocialAccounts
from tests.test_auth_integration import AuthHarness
from tests.test_auth_integration import auth_harness as _shared_auth_harness


@pytest.fixture(name="auth_harness")
def _auth_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[AuthHarness]:
    yield from _shared_auth_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


class _FakeResponse:
    def __init__(self, status_code: int, payload: dict[str, object]) -> None:
        self.status_code = status_code
        self._payload = payload

    def json(self) -> dict[str, object]:
        return self._payload


class _FakeHttpClient:
    def __init__(self, responses: list[tuple[int, dict[str, object]]]) -> None:
        self._responses = responses

    def get(self, url: str, timeout: float) -> _FakeResponse:
        del url, timeout
        status_code, payload = self._responses.pop(0)
        return _FakeResponse(status_code, payload)


def test_google_provider_enforces_audience_and_email_verification() -> None:
    client = _FakeHttpClient(
        [
            (
                200,
                {
                    "sub": "g-123",
                    "aud": "my-client",
                    "email": "a@example.com",
                    "email_verified": "true",
                    "name": "Ada Lovelace",
                },
            )
        ]
    )
    identity = GoogleIdentityProvider("my-client", cast(Any, client)).verify("tok")
    assert identity.provider_user_id == "g-123"
    assert identity.email == "a@example.com"

    wrong_audience = _FakeHttpClient(
        [(200, {"sub": "g-123", "aud": "someone-else", "email": "a@example.com"})]
    )
    with pytest.raises(SocialVerificationError, match="not issued for this application"):
        GoogleIdentityProvider("my-client", cast(Any, wrong_audience)).verify("tok")

    unverified_email = _FakeHttpClient(
        [(200, {"sub": "g-123", "aud": "my-client", "email_verified": "false"})]
    )
    with pytest.raises(SocialVerificationError, match="not verified"):
        GoogleIdentityProvider("my-client", cast(Any, unverified_email)).verify("tok")


def test_facebook_provider_enforces_app_id() -> None:
    client = _FakeHttpClient(
        [
            (200, {"data": {"is_valid": True, "app_id": "my-app"}}),
            (200, {"id": "fb-123", "name": "Ada Lovelace", "email": "a@example.com"}),
        ]
    )
    identity = FacebookIdentityProvider("my-app", "secret", cast(Any, client)).verify("tok")
    assert identity.provider == "facebook"
    assert identity.provider_user_id == "fb-123"

    wrong_app = _FakeHttpClient([(200, {"data": {"is_valid": True, "app_id": "other"}})])
    with pytest.raises(SocialVerificationError, match="not issued for this application"):
        FacebookIdentityProvider("my-app", "secret", cast(Any, wrong_app)).verify("tok")


@pytest.mark.integration
def test_social_login_maps_to_existing_account_and_links(
    auth_harness: AuthHarness, monkeypatch: pytest.MonkeyPatch
) -> None:
    """A provider-verified identity for an existing email signs in and auto-links."""
    user_id, email, _ = auth_harness.create_user()
    monkeypatch.setattr(
        "app.api.social._verify_identity",
        lambda settings, provider, id_token, access_token: SocialIdentity(
            provider, f"{provider}-id", email, "Ada Lovelace", None
        ),
    )
    response = auth_harness.client.post(
        "/socials/social_login", json={"provider": "google", "id_token": "x"}
    )
    assert response.status_code == 200
    assert response.json()["access_token"]
    assert response.json()["user_id"] == user_id
    with auth_harness.engine.connect() as connection:
        providers = (
            connection.execute(
                select(UserSocialAccounts.provider).where(UserSocialAccounts.user_id == user_id)
            )
            .mappings()
            .all()
        )
        assert any(row["provider"].value == "google" for row in providers)


@pytest.mark.integration
def test_social_login_unknown_email_returns_406(
    auth_harness: AuthHarness, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setattr(
        "app.api.social._verify_identity",
        lambda settings, provider, id_token, access_token: SocialIdentity(
            provider, f"{provider}-id", "nobody@example.com", "Nobody", None
        ),
    )
    response = auth_harness.client.post(
        "/socials/social_login", json={"provider": "google", "id_token": "x"}
    )
    assert response.status_code == 406
    assert response.json()["message"] == "No account found. Please sign up to continue."


@pytest.mark.integration
def test_social_signup_creates_minimal_account(
    auth_harness: AuthHarness, monkeypatch: pytest.MonkeyPatch
) -> None:
    """A new provider identity creates an unapproved, email-verified social account."""
    email = f"social-{auth_harness.marker}@example.com"
    auth_harness.emails.append(email)
    monkeypatch.setattr(
        "app.api.social._verify_identity",
        lambda settings, provider, id_token, access_token: SocialIdentity(
            provider, f"{provider}-id", email, "Ada Lovelace", None
        ),
    )
    response = auth_harness.client.post(
        "/socials/social_signup", json={"provider": "google", "id_token": "x"}
    )
    assert response.status_code == 201
    user_id = int(response.json()["user_id"])
    auth_harness.user_ids.append(user_id)
    with auth_harness.engine.connect() as connection:
        user = (
            connection.execute(
                select(
                    Users.email_verified,
                    Users.is_approved,
                    Users.active,
                    Users.has_password,
                    Users.onboarding_completion,
                ).where(Users.id == user_id)
            )
            .mappings()
            .one()
        )
        assert user["email_verified"] == 1
        assert user["is_approved"] == 0
        assert user["active"] == 0
        assert user["has_password"] == 0
        assert user["onboarding_completion"] == 0
        providers = (
            connection.execute(
                select(UserSocialAccounts.provider).where(UserSocialAccounts.user_id == user_id)
            )
            .mappings()
            .all()
        )
        assert any(row["provider"].value == "google" for row in providers)


def test_social_login_unconfigured_returns_503(auth_harness: AuthHarness) -> None:
    """With no Google client id configured, social login fails closed with 503."""
    response = auth_harness.client.post(
        "/socials/social_login", json={"provider": "google", "id_token": "x"}
    )
    assert response.status_code == 503
    assert response.json()["message"] == "Social login is not configured"
