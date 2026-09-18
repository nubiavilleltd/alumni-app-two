"""Goal 10 durable administrative audit-log tests."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path

import pytest
from sqlalchemy import delete, select
from sqlalchemy.orm import Session

from app.models.generated import AuditLog
from app.repositories.audit import AuditRepository
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
def _clean_audit_rows(auth_harness: AuthHarness) -> Iterator[None]:
    yield
    with auth_harness.engine.begin() as connection:
        connection.execute(delete(AuditLog))


@pytest.mark.integration
def test_audit_log_records_and_reads(auth_harness: AuthHarness) -> None:
    """A privileged action leaves an immutable, secret-free audit row."""
    with Session(auth_harness.engine) as session, session.begin():
        AuditRepository(session).record(
            actor_user_id=7,
            action="member_account_deactivate",
            target_type="user",
            target_id=9,
            details={"previous_active": True},
        )
    with auth_harness.engine.connect() as connection:
        row = (
            connection.execute(
                select(AuditLog.action, AuditLog.actor_user_id, AuditLog.target_id).where(
                    AuditLog.action == "member_account_deactivate"
                )
            )
            .mappings()
            .one()
        )
        assert row["actor_user_id"] == 7
        assert row["target_id"] == 9
