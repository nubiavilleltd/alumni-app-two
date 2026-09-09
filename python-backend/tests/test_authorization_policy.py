"""Tests for the canonical database-fact authorization policy."""

import pytest

from app.authorization.policy import (
    AuthorizationFacts,
    CanonicalRole,
    Permission,
    can_act_on_vouch,
    has_permission,
    normalize_role,
    permissions_for,
)


@pytest.mark.parametrize(
    ("legacy", "canonical"),
    [
        (None, CanonicalRole.MEMBER),
        ("alumni", CanonicalRole.MEMBER),
        (" MEMBER ", CanonicalRole.MEMBER),
        ("Admin", CanonicalRole.ADMINISTRATOR),
        ("superadmin", CanonicalRole.SUPER_ADMINISTRATOR),
        ("super_admin", CanonicalRole.SUPER_ADMINISTRATOR),
        ("finance-admin", CanonicalRole.FINANCE_ADMINISTRATOR),
        ("storekeeper admin", CanonicalRole.STOREKEEPER_ADMINISTRATOR),
    ],
)
def test_reviewed_legacy_roles_normalize_to_canonical_values(
    legacy: str | None,
    canonical: CanonicalRole,
) -> None:
    """Spacing and spelling aliases converge without substring matching."""
    assert normalize_role(legacy) is canonical


def test_unknown_or_free_form_roles_never_gain_admin_permissions() -> None:
    """Names containing 'admin' and arbitrary roles rows fail to member access."""
    facts = AuthorizationFacts(
        user_id=7,
        user_role="event admin",
        system_roles=("superadmin", "content administrator"),
    )
    assert permissions_for(facts) == frozenset({Permission.VERIFY_OWN_ACCESS_CODE})
    assert not has_permission(facts, Permission.MANAGE_CONTENT)


def test_manager_and_store_roles_remain_separated() -> None:
    """Legacy manager access does not silently grant store or role administration."""
    manager = AuthorizationFacts(user_id=2, user_role="manager")
    finance = AuthorizationFacts(user_id=3, user_role="finance admin")
    assert has_permission(manager, Permission.MANAGE_ACCOUNTS)
    assert not has_permission(manager, Permission.MANAGE_STORE)
    assert has_permission(finance, Permission.MANAGE_STORE)
    assert not has_permission(finance, Permission.MANAGE_ACCOUNTS)


def test_coordinator_and_voucher_capabilities_come_from_relationship_facts() -> None:
    """Scoped capabilities never become global administrator roles."""
    coordinator = AuthorizationFacts(user_id=11, user_role="alumni", is_coordinator=True)
    assert has_permission(coordinator, Permission.COORDINATE_CLASS)
    assert not has_permission(coordinator, Permission.MANAGE_ACCOUNTS)
    assert can_act_on_vouch(actor_user_id=11, assigned_voucher_user_id=11)
    assert not can_act_on_vouch(actor_user_id=11, assigned_voucher_user_id=12)
    assert not can_act_on_vouch(actor_user_id=0, assigned_voucher_user_id=0)
