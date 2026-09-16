"""Canonical permissions derived from current database authorization facts."""

from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum


class CanonicalRole(StrEnum):
    """Bounded roles recognized by the migrated service."""

    MEMBER = "member"
    MANAGER = "manager"
    ADMINISTRATOR = "administrator"
    APPROVAL_ADMINISTRATOR = "approval_administrator"
    CONTENT_ADMINISTRATOR = "content_administrator"
    EVENT_ADMINISTRATOR = "event_administrator"
    SUPER_ADMINISTRATOR = "super_administrator"
    FINANCE_ADMINISTRATOR = "finance_administrator"
    STOREKEEPER_ADMINISTRATOR = "storekeeper_administrator"


class Permission(StrEnum):
    """Business capabilities checked by service-layer use cases."""

    VERIFY_OWN_ACCESS_CODE = "verify_own_access_code"
    MANAGE_ACCOUNTS = "manage_accounts"
    MANAGE_CONTENT = "manage_content"
    MANAGE_EVENTS = "manage_events"
    MANAGE_ROLES = "manage_roles"
    MANAGE_STORE = "manage_store"
    MANAGE_ZONES = "manage_zones"
    COORDINATE_CLASS = "coordinate_class"


_ROLE_ALIASES = {
    "": CanonicalRole.MEMBER,
    "alumni": CanonicalRole.MEMBER,
    "member": CanonicalRole.MEMBER,
    "manager": CanonicalRole.MANAGER,
    "admin": CanonicalRole.ADMINISTRATOR,
    "administrator": CanonicalRole.ADMINISTRATOR,
    "approval admin": CanonicalRole.APPROVAL_ADMINISTRATOR,
    "approval administrator": CanonicalRole.APPROVAL_ADMINISTRATOR,
    "content admin": CanonicalRole.CONTENT_ADMINISTRATOR,
    "content administrator": CanonicalRole.CONTENT_ADMINISTRATOR,
    "event admin": CanonicalRole.EVENT_ADMINISTRATOR,
    "event administrator": CanonicalRole.EVENT_ADMINISTRATOR,
    "super admin": CanonicalRole.SUPER_ADMINISTRATOR,
    "superadmin": CanonicalRole.SUPER_ADMINISTRATOR,
    "super administrator": CanonicalRole.SUPER_ADMINISTRATOR,
    "finance admin": CanonicalRole.FINANCE_ADMINISTRATOR,
    "finance administrator": CanonicalRole.FINANCE_ADMINISTRATOR,
    "storekeeper admin": CanonicalRole.STOREKEEPER_ADMINISTRATOR,
    "storekeeper administrator": CanonicalRole.STOREKEEPER_ADMINISTRATOR,
}

_ROLE_PERMISSIONS = {
    CanonicalRole.MEMBER: frozenset({Permission.VERIFY_OWN_ACCESS_CODE}),
    CanonicalRole.MANAGER: frozenset(
        {
            Permission.VERIFY_OWN_ACCESS_CODE,
            Permission.MANAGE_ACCOUNTS,
            Permission.MANAGE_CONTENT,
        }
    ),
    CanonicalRole.ADMINISTRATOR: frozenset(
        {
            Permission.VERIFY_OWN_ACCESS_CODE,
            Permission.MANAGE_ACCOUNTS,
            Permission.MANAGE_CONTENT,
            Permission.MANAGE_ROLES,
            Permission.MANAGE_STORE,
            Permission.MANAGE_ZONES,
        }
    ),
    CanonicalRole.APPROVAL_ADMINISTRATOR: frozenset(
        {
            Permission.VERIFY_OWN_ACCESS_CODE,
            Permission.MANAGE_ACCOUNTS,
        }
    ),
    CanonicalRole.CONTENT_ADMINISTRATOR: frozenset(
        {
            Permission.VERIFY_OWN_ACCESS_CODE,
            Permission.MANAGE_CONTENT,
        }
    ),
    CanonicalRole.EVENT_ADMINISTRATOR: frozenset(
        {
            Permission.VERIFY_OWN_ACCESS_CODE,
            Permission.MANAGE_EVENTS,
        }
    ),
    CanonicalRole.SUPER_ADMINISTRATOR: frozenset(Permission),
    CanonicalRole.FINANCE_ADMINISTRATOR: frozenset(
        {Permission.VERIFY_OWN_ACCESS_CODE, Permission.MANAGE_STORE}
    ),
    CanonicalRole.STOREKEEPER_ADMINISTRATOR: frozenset(
        {Permission.VERIFY_OWN_ACCESS_CODE, Permission.MANAGE_STORE}
    ),
}

_ACCOUNT_MANAGEMENT_RANK = {
    CanonicalRole.MEMBER: 0,
    CanonicalRole.MANAGER: 10,
    CanonicalRole.ADMINISTRATOR: 20,
    CanonicalRole.APPROVAL_ADMINISTRATOR: 20,
    CanonicalRole.CONTENT_ADMINISTRATOR: 20,
    CanonicalRole.EVENT_ADMINISTRATOR: 20,
    CanonicalRole.FINANCE_ADMINISTRATOR: 20,
    CanonicalRole.STOREKEEPER_ADMINISTRATOR: 20,
    CanonicalRole.SUPER_ADMINISTRATOR: 30,
}

_ROLE_STORAGE_VALUES = {
    CanonicalRole.MEMBER: "alumni",
    CanonicalRole.MANAGER: "manager",
    CanonicalRole.ADMINISTRATOR: "admin",
    CanonicalRole.APPROVAL_ADMINISTRATOR: "approval admin",
    CanonicalRole.CONTENT_ADMINISTRATOR: "content admin",
    CanonicalRole.EVENT_ADMINISTRATOR: "event admin",
    CanonicalRole.SUPER_ADMINISTRATOR: "super admin",
    CanonicalRole.FINANCE_ADMINISTRATOR: "finance admin",
    CanonicalRole.STOREKEEPER_ADMINISTRATOR: "storekeeper admin",
}


@dataclass(frozen=True, slots=True)
class AuthorizationFacts:
    """Server-loaded facts used to derive permissions for one request."""

    user_id: int
    user_role: str | None
    system_roles: tuple[str, ...] = ()
    is_coordinator: bool = False


def normalize_role(value: str | None) -> CanonicalRole:
    """Normalize only reviewed legacy aliases; unknown values fail to member access."""
    return recognized_role(value) or CanonicalRole.MEMBER


def recognized_role(value: str | None) -> CanonicalRole | None:
    """Return a reviewed canonical role, leaving unknown free-form labels untrusted."""
    normalized = " ".join((value or "").replace("_", " ").replace("-", " ").split()).casefold()
    return _ROLE_ALIASES.get(normalized)


def permissions_for(facts: AuthorizationFacts) -> frozenset[Permission]:
    """Return permissions without treating free-form system-role labels as grants."""
    permissions = set(_ROLE_PERMISSIONS[normalize_role(facts.user_role)])
    if facts.is_coordinator:
        permissions.add(Permission.COORDINATE_CLASS)
    return frozenset(permissions)


def has_permission(facts: AuthorizationFacts, permission: Permission) -> bool:
    """Check one canonical permission from freshly loaded database facts."""
    return permission in permissions_for(facts)


def can_manage_account_target(facts: AuthorizationFacts, target_user_role: str | None) -> bool:
    """Allow account changes only down the reviewed role hierarchy."""
    actor_role = recognized_role(facts.user_role)
    target_role = recognized_role(target_user_role)
    if (
        actor_role is None
        or target_role is None
        or not has_permission(facts, Permission.MANAGE_ACCOUNTS)
    ):
        return False
    return _ACCOUNT_MANAGEMENT_RANK[actor_role] > _ACCOUNT_MANAGEMENT_RANK[target_role]


def manageable_account_role_aliases(facts: AuthorizationFacts) -> frozenset[str]:
    """Return normalized legacy aliases strictly below an account manager's role."""
    actor_role = recognized_role(facts.user_role)
    if actor_role is None or not has_permission(facts, Permission.MANAGE_ACCOUNTS):
        return frozenset()
    actor_rank = _ACCOUNT_MANAGEMENT_RANK[actor_role]
    return frozenset(
        alias
        for alias, canonical_role in _ROLE_ALIASES.items()
        if _ACCOUNT_MANAGEMENT_RANK[canonical_role] < actor_rank
    )


def role_storage_value(value: str | None) -> str | None:
    """Return the stable database spelling for one reviewed account role."""
    if value is None or not value.strip():
        return None
    role = recognized_role(value)
    return _ROLE_STORAGE_VALUES.get(role) if role is not None else None


def can_change_account_role(
    facts: AuthorizationFacts,
    target_user_role: str | None,
    requested_user_role: str | None,
) -> bool:
    """Authorize a reviewed role transition without trusting free-form role labels."""
    actor_role = recognized_role(facts.user_role)
    target_role = recognized_role(target_user_role)
    requested_storage = role_storage_value(requested_user_role)
    requested_role = recognized_role(requested_storage) if requested_storage else None
    if (
        actor_role is None
        or target_role is None
        or requested_role is None
        or not has_permission(facts, Permission.MANAGE_ROLES)
    ):
        return False
    if actor_role is CanonicalRole.SUPER_ADMINISTRATOR:
        return True
    if actor_role is not CanonicalRole.ADMINISTRATOR:
        return False
    return (
        _ACCOUNT_MANAGEMENT_RANK[target_role]
        < _ACCOUNT_MANAGEMENT_RANK[CanonicalRole.ADMINISTRATOR]
    )


def can_act_on_vouch(*, actor_user_id: int, assigned_voucher_user_id: int) -> bool:
    """Authorize a voucher action by row ownership, never by a reusable role flag."""
    return actor_user_id > 0 and actor_user_id == assigned_voucher_user_id
