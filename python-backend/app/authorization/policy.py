"""Canonical permissions derived from current database authorization facts."""

from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum


class CanonicalRole(StrEnum):
    """Bounded roles recognized by the migrated service."""

    MEMBER = "member"
    MANAGER = "manager"
    ADMINISTRATOR = "administrator"
    SUPER_ADMINISTRATOR = "super_administrator"
    FINANCE_ADMINISTRATOR = "finance_administrator"
    STOREKEEPER_ADMINISTRATOR = "storekeeper_administrator"


class Permission(StrEnum):
    """Business capabilities checked by service-layer use cases."""

    VERIFY_OWN_ACCESS_CODE = "verify_own_access_code"
    MANAGE_ACCOUNTS = "manage_accounts"
    MANAGE_CONTENT = "manage_content"
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
    CanonicalRole.SUPER_ADMINISTRATOR: frozenset(Permission),
    CanonicalRole.FINANCE_ADMINISTRATOR: frozenset(
        {Permission.VERIFY_OWN_ACCESS_CODE, Permission.MANAGE_STORE}
    ),
    CanonicalRole.STOREKEEPER_ADMINISTRATOR: frozenset(
        {Permission.VERIFY_OWN_ACCESS_CODE, Permission.MANAGE_STORE}
    ),
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
    normalized = " ".join((value or "").replace("_", " ").replace("-", " ").split()).casefold()
    return _ROLE_ALIASES.get(normalized, CanonicalRole.MEMBER)


def permissions_for(facts: AuthorizationFacts) -> frozenset[Permission]:
    """Return permissions without treating free-form system-role labels as grants."""
    permissions = set(_ROLE_PERMISSIONS[normalize_role(facts.user_role)])
    if facts.is_coordinator:
        permissions.add(Permission.COORDINATE_CLASS)
    return frozenset(permissions)


def has_permission(facts: AuthorizationFacts, permission: Permission) -> bool:
    """Check one canonical permission from freshly loaded database facts."""
    return permission in permissions_for(facts)


def can_act_on_vouch(*, actor_user_id: int, assigned_voucher_user_id: int) -> bool:
    """Authorize a voucher action by row ownership, never by a reusable role flag."""
    return actor_user_id > 0 and actor_user_id == assigned_voucher_user_id
