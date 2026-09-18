"""Goal 9 Web Push subscription and delivery tests."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path

import pytest
from sqlalchemy import delete, select

from app.integrations.push import PushDeliveryError, send_web_push
from app.models.generated import UserPushSubscriptions
from tests.test_auth_integration import AuthHarness, _access_token_for
from tests.test_auth_integration import auth_harness as _shared_auth_harness


@pytest.fixture(name="auth_harness")
def _auth_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[AuthHarness]:
    yield from _shared_auth_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


@pytest.fixture(autouse=True)
def _clean_subscriptions(auth_harness: AuthHarness) -> Iterator[None]:
    yield
    with auth_harness.engine.begin() as connection:
        connection.execute(delete(UserPushSubscriptions))


def test_get_vapid_key_requires_configuration(auth_harness: AuthHarness) -> None:
    """Without a configured public key the endpoint fails closed."""
    response = auth_harness.client.get("/chat_api/get_vapid_key")
    assert response.status_code == 503


@pytest.mark.integration
def test_register_push_subscription_persists(auth_harness: AuthHarness) -> None:
    """The current account's browser subscription is stored keyed by endpoint."""
    user_id, email, _ = auth_harness.create_user()
    headers = {"Authorization": f"Bearer {_access_token_for(auth_harness, user_id, email)}"}
    response = auth_harness.client.post(
        "/chat_api/register_push_subscription",
        headers=headers,
        json={
            "endpoint": "https://push.example/abc123",
            "p256dh": "BP256DHKEY",
            "auth": "AUTHKEY",
            "browser": "chrome",
        },
    )
    assert response.status_code == 200
    with auth_harness.engine.connect() as connection:
        row = (
            connection.execute(
                select(UserPushSubscriptions.endpoint, UserPushSubscriptions.browser).where(
                    UserPushSubscriptions.user_id == user_id
                )
            )
            .mappings()
            .one()
        )
        assert row["endpoint"] == "https://push.example/abc123"
        assert row["browser"] == "chrome"


def test_send_web_push_requires_private_key() -> None:
    """A missing VAPID private key raises a typed delivery error."""
    with pytest.raises(PushDeliveryError, match="not configured"):
        send_web_push(
            {"endpoint": "https://x", "p256dh": "k", "auth": "a"},
            {"title": "t", "body": "b"},
            private_key="",
            subject="mailto:admin@example.test",
        )
