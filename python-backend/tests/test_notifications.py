"""Goal 9 create_notification authorization and persistence tests."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path

import pytest
from sqlalchemy import delete, select

from app.models.generated import Notifications
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
def _clean_notifications(auth_harness: AuthHarness) -> Iterator[None]:
    yield
    with auth_harness.engine.begin() as connection:
        connection.execute(delete(Notifications))


def _bearer(harness: AuthHarness, user_id: int, email: str, role: str = "alumni") -> dict[str, str]:
    return {"Authorization": f"Bearer {_access_token_for(harness, user_id, email, user_role=role)}"}


@pytest.mark.integration
def test_create_notification_requires_content_admin(auth_harness: AuthHarness) -> None:
    """An ordinary member cannot send in-app notifications."""
    member_id, member_email, _ = auth_harness.create_user()
    target_id, _target_email, _ = auth_harness.create_user()
    response = auth_harness.client.post(
        "/api/create_notification",
        headers=_bearer(auth_harness, member_id, member_email),
        json={"user_id": target_id, "type": "announcement", "message": "Hello"},
    )
    assert response.status_code == 403
    assert response.json()["code"] == "notification_forbidden"


@pytest.mark.integration
def test_create_notification_persists_for_active_recipient(auth_harness: AuthHarness) -> None:
    """A content administrator can notify an active member."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="content admin")
    target_id, _target_email, _ = auth_harness.create_user()
    response = auth_harness.client.post(
        "/api/create_notification",
        headers=_bearer(auth_harness, admin_id, admin_email, role="content admin"),
        json={"user_id": target_id, "type": "announcement", "message": "Welcome back"},
    )
    assert response.status_code == 200
    with auth_harness.engine.connect() as connection:
        row = (
            connection.execute(
                select(Notifications.type, Notifications.message, Notifications.is_read).where(
                    Notifications.user_id == target_id
                )
            )
            .mappings()
            .one()
        )
        assert row["type"] == "announcement"
        assert row["message"] == "Welcome back"
        assert row["is_read"] == 0


@pytest.mark.integration
def test_create_notification_rejects_unknown_recipient(auth_harness: AuthHarness) -> None:
    """A missing or inactive recipient is not accepted."""
    admin_id, admin_email, _ = auth_harness.create_user(user_role="content admin")
    response = auth_harness.client.post(
        "/api/create_notification",
        headers=_bearer(auth_harness, admin_id, admin_email, role="content admin"),
        json={"user_id": 999_999, "type": "announcement", "message": "Hello"},
    )
    assert response.status_code == 404
    assert response.json()["code"] == "notification_recipient_not_found"
