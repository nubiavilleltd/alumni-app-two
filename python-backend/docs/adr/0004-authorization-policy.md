# ADR 0004: Canonical authorization policy

- Status: accepted
- Date: 2026-09-09

## Context

The PHP application has overlapping authorization sources: Ion Auth `groups` and
`users_groups`, the free-form `users.user_role` column, arbitrary per-user `roles`
rows, `users.is_coordinator`, and ownership rows in `vouches`. Route checks are
inconsistent: common administration accepts `admin`, `manager`, and `superadmin`;
store administration accepts `super admin`, `admin`, `finance admin`, and
`storekeeper admin`; other helpers grant access to any string containing `admin`.

## Decision

FastAPI uses named permissions derived from freshly loaded database facts at the
service boundary. JWT role claims are display/cache hints only and never sufficient
for privileged authorization.

Reviewed `users.user_role` aliases normalize to these bounded roles:

| Canonical role | Accepted legacy aliases | Permissions |
| --- | --- | --- |
| Member | empty, `member`, `alumni`, unknown values | Own access-code verification |
| Manager | `manager` | Account and content management |
| Administrator | `admin`, `administrator` | Account, content, role, store, and zone management |
| Super administrator | `superadmin`, `super admin`, `super administrator` | All currently defined permissions |
| Finance administrator | `finance admin`, `finance administrator` | Store management only |
| Storekeeper administrator | `storekeeper admin`, `storekeeper administrator` | Store management only |

Unknown values—including strings merely containing `admin`—receive member access.
The free-form `roles.role_name` rows remain display metadata until each value has an
approved permission mapping. `is_coordinator` grants only the class-coordination
capability. Voucher actions depend on `vouches.voucher_id` ownership and pending row
state, never a global role or `users.voucher` string.

Role changes must be server-validated against this allowlist. Managers cannot grant
roles or store access. Only a super administrator may grant or revoke administrator
or super-administrator status; this hierarchy will be enforced when role-management
routes are migrated.

## Consequences

Some permissive PHP behavior is intentionally narrowed. Existing unrecognized role
labels require explicit review before they can grant privileges. Protected use cases
must reload account state and authorization facts from the database so role changes
and deactivation take effect without waiting for JWT expiry.
