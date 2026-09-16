"""Tests for the canonical database-fact authorization policy."""

import pytest

from app.authorization.policy import (
    AuthorizationFacts,
    CanonicalRole,
    Permission,
    can_act_on_vouch,
    can_change_account_role,
    can_manage_account_target,
    has_permission,
    manageable_account_role_aliases,
    normalize_role,
    permissions_for,
    role_storage_value,
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
        ("approval admin", CanonicalRole.APPROVAL_ADMINISTRATOR),
        ("content-admin", CanonicalRole.CONTENT_ADMINISTRATOR),
        ("event administrator", CanonicalRole.EVENT_ADMINISTRATOR),
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
        user_role="auditor admin",
        system_roles=("superadmin", "content administrator"),
    )
    assert permissions_for(facts) == frozenset({Permission.VERIFY_OWN_ACCESS_CODE})
    assert not has_permission(facts, Permission.MANAGE_CONTENT)


def test_frontend_admin_categories_map_to_separate_capabilities() -> None:
    """UI category labels grant only the matching reviewed server capability."""
    approval = AuthorizationFacts(user_id=2, user_role="approval admin")
    content = AuthorizationFacts(user_id=3, user_role="content admin")
    events = AuthorizationFacts(user_id=4, user_role="event admin")

    assert has_permission(approval, Permission.MANAGE_ACCOUNTS)
    assert not has_permission(approval, Permission.MANAGE_CONTENT)
    assert has_permission(content, Permission.MANAGE_CONTENT)
    assert not has_permission(content, Permission.MANAGE_ACCOUNTS)
    assert has_permission(events, Permission.MANAGE_EVENTS)
    assert not has_permission(events, Permission.MANAGE_CONTENT)


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


def test_account_managers_can_act_only_down_the_reviewed_role_hierarchy() -> None:
    """Approval cannot activate peers, higher roles, or unknown free-form roles."""
    manager = AuthorizationFacts(user_id=2, user_role="manager")
    administrator = AuthorizationFacts(user_id=3, user_role="admin")
    super_administrator = AuthorizationFacts(user_id=4, user_role="superadmin")

    assert can_manage_account_target(manager, "alumni")
    assert not can_manage_account_target(manager, "admin")
    assert can_manage_account_target(administrator, "manager")
    assert not can_manage_account_target(administrator, "finance admin")
    assert not can_manage_account_target(super_administrator, "superadmin")
    assert can_manage_account_target(super_administrator, "event admin")


def test_account_listing_aliases_include_only_downward_reviewed_roles() -> None:
    """Administrative listings cannot expose peer, higher, or free-form roles."""
    manager = manageable_account_role_aliases(AuthorizationFacts(user_id=2, user_role="manager"))
    administrator = manageable_account_role_aliases(
        AuthorizationFacts(user_id=3, user_role="admin")
    )
    ordinary_member = manageable_account_role_aliases(
        AuthorizationFacts(user_id=4, user_role="alumni")
    )
    super_administrator = manageable_account_role_aliases(
        AuthorizationFacts(user_id=5, user_role="super admin")
    )

    assert {"", "alumni", "member"}.issubset(manager)
    assert "manager" not in manager
    assert "manager" in administrator
    assert "admin" not in administrator
    assert "event admin" not in administrator
    assert ordinary_member == frozenset()
    assert {"approval admin", "content admin", "event admin"}.issubset(super_administrator)


def test_role_changes_require_reviewed_authority_and_transition_values() -> None:
    """Only role managers can apply bounded grants without free-form escalation."""
    member = AuthorizationFacts(user_id=1, user_role="alumni")
    administrator = AuthorizationFacts(user_id=2, user_role="admin")
    super_administrator = AuthorizationFacts(user_id=3, user_role="super admin")

    assert role_storage_value(" Content_Administrator ") == "content admin"
    assert role_storage_value("mystery admin") is None
    assert role_storage_value("   ") is None
    assert not can_change_account_role(member, "alumni", "content admin")
    assert can_change_account_role(administrator, "alumni", "content admin")
    assert can_change_account_role(administrator, "alumni", "super admin")
    assert not can_change_account_role(administrator, "content admin", "alumni")
    assert can_change_account_role(super_administrator, "content admin", "alumni")
    assert can_change_account_role(super_administrator, "super admin", "alumni")
