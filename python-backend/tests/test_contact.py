"""Goal 9 public contact-form route tests."""

from __future__ import annotations

import uuid
from collections.abc import Iterator
from pathlib import Path

import pytest
from sqlalchemy import delete, func, select

from app.models.generated import ContactUs
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


@pytest.fixture(autouse=True)
def _clean_contact_rows(auth_harness: AuthHarness) -> Iterator[None]:
    """Contact rows have no user foreign key, so remove the ones this module created."""
    yield
    with auth_harness.engine.begin() as connection:
        connection.execute(delete(ContactUs))


@pytest.mark.integration
def test_contact_submission_persists_and_notifies_manager(auth_harness: AuthHarness) -> None:
    """A valid message is stored as 'new' and emailed to active managers after commit."""
    _manager_id, manager_email, _ = auth_harness.create_user(user_role="admin")
    email = f"contact-{uuid.uuid4().hex[:8]}@example.com"
    response = auth_harness.client.post(
        "/api/contact_us",
        json={"firstName": "Ada", "lastName": "Lovelace", "email": email, "message": "Hello"},
    )
    assert response.status_code == 200
    with auth_harness.engine.connect() as connection:
        row = (
            connection.execute(
                select(ContactUs.status, ContactUs.message, ContactUs.first_name).where(
                    ContactUs.email == email
                )
            )
            .mappings()
            .one()
        )
        assert row["status"].value == "new"
        assert row["message"] == "Hello"
        assert row["first_name"] == "Ada"
    assert any(delivery[0] == manager_email for delivery in auth_harness.mailer.contact_deliveries)


@pytest.mark.integration
def test_contact_accepts_form_body(auth_harness: AuthHarness) -> None:
    """The legacy form encoding is accepted alongside JSON."""
    auth_harness.create_user(user_role="admin")
    email = f"contact-{uuid.uuid4().hex[:8]}@example.com"
    response = auth_harness.client.post(
        "/api/contact_us",
        data={"firstName": "Ada", "lastName": "Lovelace", "email": email, "message": "Form body"},
    )
    assert response.status_code == 200


@pytest.mark.integration
def test_contact_rejects_missing_fields_and_invalid_email(auth_harness: AuthHarness) -> None:
    """Validation fails closed on partial or malformed input."""
    missing = auth_harness.client.post("/api/contact_us", json={"firstName": "Ada"})
    assert missing.status_code == 400
    invalid_email = auth_harness.client.post(
        "/api/contact_us",
        json={"firstName": "Ada", "lastName": "Lovelace", "email": "nope", "message": "x"},
    )
    assert invalid_email.status_code == 400


@pytest.mark.integration
def test_contact_mail_failure_still_persists(auth_harness: AuthHarness) -> None:
    """A provider failure cannot roll back the committed contact message."""
    auth_harness.create_user(user_role="admin")
    auth_harness.mailer.fail = True
    email = f"contact-{uuid.uuid4().hex[:8]}@example.com"
    response = auth_harness.client.post(
        "/api/contact_us",
        json={"firstName": "Ada", "lastName": "Lovelace", "email": email, "message": "Persist me"},
    )
    assert response.status_code == 200
    with auth_harness.engine.connect() as connection:
        total = connection.scalar(
            select(func.count()).select_from(ContactUs).where(ContactUs.email == email)
        )
        assert total == 1
