"""Goal 11 edge-data tests: Unicode, emoji, Nigerian phones, and null legacy fields."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path
from typing import cast

import pytest
from sqlalchemy import Table, select

from app.models.generated import Users
from tests.test_auth_integration import AuthHarness, _access_token_for, _registration_payload
from tests.test_auth_integration import auth_harness as _shared_auth_harness

USERS_TABLE: Table = cast(Table, Users.__table__)


@pytest.fixture(name="auth_harness")
def _auth_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[AuthHarness]:
    yield from _shared_auth_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


def _register(auth_harness: AuthHarness, **overrides: object) -> tuple[int, str]:
    chapter_id, city = auth_harness.create_registration_location()
    payload = _registration_payload(auth_harness, chapter_id, city)
    payload.update(overrides)
    response = auth_harness.client.post("/api/register", json=payload)
    assert response.status_code == 200, response.text
    user_id = int(response.json()["user_id"])
    auth_harness.user_ids.append(user_id)
    return user_id, str(payload["email"])


def _user_row(auth_harness: AuthHarness, user_id: int) -> dict[str, object]:
    with auth_harness.engine.connect() as connection:
        row = (
            connection.execute(
                select(Users.fullname, Users.nick_name, Users.phone).where(Users.id == user_id)
            )
            .mappings()
            .one()
        )
        return dict(row)


@pytest.mark.integration
def test_unicode_name_and_nigerian_phone_round_trip(auth_harness: AuthHarness) -> None:
    """A non-ASCII name and a valid local Nigerian phone survive registration."""
    user_id, _email = _register(
        auth_harness,
        first_name="Adéyẹmí",
        last_name="Olúwáṣẹ́gun",
        phone="08012345678",
    )
    row = _user_row(auth_harness, user_id)
    assert row["fullname"] == "Adéyẹmí Olúwáṣẹ́gun"
    assert row["phone"] == "08012345678"


@pytest.mark.integration
def test_emoji_round_trip_in_utf8mb4_column(auth_harness: AuthHarness) -> None:
    """Four-byte UTF-8 survives in a utf8mb4 text column."""
    user_id, _email = _register(auth_harness, nick_name="Synth 😀")
    row = _user_row(auth_harness, user_id)
    assert row["nick_name"] == "Synth 😀"


@pytest.mark.integration
def test_nigerian_phone_contract_is_enforced(auth_harness: AuthHarness) -> None:
    """Registration accepts the local `0[789]xxxxxxxxx` format and rejects variants."""
    chapter_id, city = auth_harness.create_registration_location()
    for valid in ("08012345678", "07012345678", "09012345678"):
        payload = _registration_payload(auth_harness, chapter_id, city)
        payload["phone"] = valid
        assert auth_harness.client.post("/api/register", json=payload).status_code == 200
    for invalid in ("+2348012345678", "2348012345678", "+234 801 234 5678", "0801234567"):
        payload = _registration_payload(auth_harness, chapter_id, city)
        payload["phone"] = invalid
        assert auth_harness.client.post("/api/register", json=payload).status_code == 400


@pytest.mark.integration
def test_legacy_null_fields_do_not_break_the_directory(auth_harness: AuthHarness) -> None:
    """A member with null graduation_year and avatar still lists safely."""
    user_id, email, _ = auth_harness.create_user(fullname="Legacy Null Fields")
    with auth_harness.engine.begin() as connection:
        connection.execute(
            USERS_TABLE.update()
            .where(Users.id == user_id)
            .values(graduation_year=None, avatar=None)
        )
    token = _access_token_for(auth_harness, user_id, email)
    directory = auth_harness.client.post(
        "/api/get_users_by_action", headers={"Authorization": f"Bearer {token}"}, json={}
    )
    assert directory.status_code == 200
    assert directory.json()["total"] >= 1
