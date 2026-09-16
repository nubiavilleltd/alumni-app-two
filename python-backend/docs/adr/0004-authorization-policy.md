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
| Approval administrator | `approval admin`, `approval administrator` | Account management only |
| Content administrator | `content admin`, `content administrator` | Content management only |
| Event administrator | `event admin`, `event administrator` | Event management only |
| Super administrator | `superadmin`, `super admin`, `super administrator` | All currently defined permissions |
| Finance administrator | `finance admin`, `finance administrator` | Store management only |
| Storekeeper administrator | `storekeeper admin`, `storekeeper administrator` | Store management only |

Unknown values—including strings merely containing `admin`—receive member access.
The free-form `roles.role_name` rows remain display metadata until each value has an
approved permission mapping. `is_coordinator` grants only the class-coordination
capability. Voucher actions depend on `vouches.voucher_id` ownership and pending row
state, never a global role or `users.voucher` string.

Role changes are server-validated against this allowlist. Managers and category
administrators cannot grant roles. A legacy administrator may assign any reviewed
role to a currently lower target, including `super admin` because the active frontend
maps legacy `admin` to that label and PHP permits the same grant. A super administrator
may change another recognized target, including another super administrator. Self,
unknown-role, and unauthorized target transitions always fail closed.

Member approval uses the same hierarchy. Managers may approve or reject reviewed
member aliases only; administrators may also act on managers; super administrators
may act on every lower reviewed role. Nobody may approve or reject their own account,
an equal/higher role, or an unrecognized free-form role. Approval also requires prior
email verification, and the approval route cannot be used to reject/deactivate an
already approved account. Those constraints intentionally narrow the PHP route, which
trusted JWT role text and had no hierarchy or state-transition guard.

Account activation/deactivation uses that hierarchy for cross-user changes, while
any active member may deactivate only their own account. Self-activation is denied.
Reactivation requires an already approved account with verified email, so the route
cannot bypass the approval workflow. Deactivation revokes every active refresh token
in the same transaction. `manage_user_account` now accepts exactly one state or
reviewed role transition; administrative promotion additionally requires an active,
approved, verified target, and every successful role transition revokes the target's
refresh sessions transactionally.

The legacy `create_role`, `manage_role`, and `get_roles` routes are not copied.
They trust a reusable application key without an authenticated actor, operate on a
globally unique free-form `roles.role_name`, and conflict with the table's mandatory
`user_id` plus the fixed `users.user_role` authorization contract. `create_role`
does not supply the mandatory user ID; `manage_role` can update/delete an arbitrary
row without target hierarchy; and `get_roles` can disclose complete rows including
user metadata while emitting a malformed no-data status. The reviewed SQL snapshot
contains no role rows, the current source/built frontend has no caller for the trio,
and the active role UI uses `manage_user_account`. Both GET and POST therefore return
tested HTTP 410 guidance. `Live/KEEP` remains reachability evidence only.

The legacy `deactivate_staff/{user_id}` route is also not copied. It accepts a
caller-selected path target without authenticating or authorizing the actor,
deactivates and emails that target, and then permanently deletes the user row.
Both GET and POST return tested HTTP 410 guidance to `manage_user_account`, whose
state-only transition enforces current-database hierarchy and revokes refresh
sessions in the same transaction.

The legacy `user_tokens` route is security-retired rather than treated as an
authorization exception. A reusable application key and caller-selected user ID
cannot establish token ownership; the old code also logs and returns token values
and writes through a `push_tokens` table absent from the reviewed SQL snapshot.
Any future replacement requires confirmed hidden/mobile callers and provider/schema
evidence, then an authenticated self-service registration and revocation contract
that neither logs nor returns token material.

Profile-visibility reads and writes are self-service by default. Cross-user access
requires current account-management permission and a strictly lower reviewed target
role. Visibility input is a fixed allowlist; malformed stored JSON fails closed to
private instead of exposing fields. Missing visibility keys retain the PHP-compatible
public default, and a missing profile row is created with only the legacy-required
empty social fields plus visibility state.

`get_users_by_action` is split by use case. Approved-directory requests return only
an explicit credential-free member projection, exclude globally hidden profiles for
other viewers, and remove controlled values on the server before serialization.
Missing visibility keys remain public for PHP compatibility; malformed/non-object
visibility JSON makes every controlled field private. The account-management modes
require current `MANAGE_ACCOUNTS` permission and return only verified accounts whose
reviewed role is strictly below the actor. Unknown action types fail validation rather
than falling through to a broad all-user query. Both modes use bounded pagination.

`get_alumni_stats` preserves the four reviewed PHP aggregate definitions: active,
approved exact-role alumni; distinct non-null alumni-category years; enabled chapters;
and distinct non-empty departments on approved users. Both documented GET and POST
access patterns return only these four non-negative integers. The legacy application
token is not accepted; callers need a valid Bearer token and the service reloads the
actor's current active state before querying. No current source or built-frontend caller
was found, so authenticated UI/runtime compatibility remains a separate cutover gate.

`get_chapters` has two explicitly separated access modes under the legacy path. With
no `user_id`, GET or POST returns only enabled chapter ID, name, location, enabled
state, and creation time; this registration-safe metadata is public and rate-limited.
Supplying `user_id` requires a valid Bearer token, a current active actor, and either
self access or strictly downward account-management authority. The oldest assignment
ID is selected deterministically when inconsistent legacy data contains more than one
row. This removes the reusable application token without making arbitrary users'
chapter/year/location assignments public. Registration now derives the chapter ID
from the selected city's live catalogue row instead of hard-coding `1`; the backend
still verifies that the chapter is enabled and that the city belongs to it. Direct
authenticated use of the assignment lookup and production chapter-data comparison
remain cutover gates.

`get_cities` remains public because registration needs the city/chapter/zone mapping
before authentication. GET and the active frontend's POST pattern return only city
ID/name, chapter ID, source zone ID, and the optional joined zone name in deterministic
zone/name/ID order. The source zone ID is retained even when inconsistent legacy data
has no matching zone row. The reusable application key is ignored and requests are
peer-rate-limited.

`get_zones` also remains public because the current welfare page is not an authenticated
route, but the PHP coordinator projection is intentionally narrowed. GET/POST returns
zone ID/name/chapter and nested city ID/name rows. A coordinator is shown only when the
assigned account currently exists and is active, approved, email-verified, and not
globally hidden. Phone and avatar follow the existing profile-visibility map; malformed
JSON fails private and missing keys preserve the reviewed PHP-compatible public default.
Coordinator email, role, department, and other account/profile fields are never selected
or serialized. The frontend uses coordinator member ID rather than email to prevent
self-messaging. Approved production geography, privacy, and browser comparison remain
cutover gates.

`get_users_by_zone` does not inherit that public treatment. The PHP method trusted only
the reusable application key and returned email, phone, residential address, birth date,
employment data, role, and account state for every active city match. It also resolved a
zone name but mistakenly queried members with the still-zero input ID. FastAPI requires a
current active Bearer account, gives an explicit ID precedence over an exact normalized
name, and returns a paginated minimum roster. Candidate members must be active, approved,
and email-verified. A globally hidden profile or private city removes another member from
the roster so the zone itself does not disclose hidden location; the owner can still see
their own membership. Phone/avatar remain field-aware, malformed visibility JSON fails
private, missing visibility keeps the reviewed public default, and broad PII/account data
is never selected or serialized.

`get_my_zone` remains self-only and reloads current active account state. Caller-supplied
target data has no effect. The member's trimmed city resolves case-insensitively through
the oldest city row so duplicate legacy mappings are deterministic; a missing city,
unknown city, or orphaned zone retains the legacy HTTP 200 `Not Yet Available` result.
Resolved coordinators use the same eligibility/global/phone/avatar privacy projection as
the public zone catalogue, including explicit null when unavailable and no email.

`register` is a public, peer- and identity-rate-limited JSON/form/multipart route; it
does not accept the legacy reusable application key or require a Bearer principal.
The server fixes the initial authorization-sensitive values to `alumni`, not a
coordinator, the current registration year, and an inactive/unverified/unapproved
account regardless of extra client fields. It enforces the frontend password and
phone constraints, validates the enabled chapter/city pair and any current active
voucher, requires the reviewed Ion Auth member group, and hashes the password with
Argon2id. User, group, category, private-by-default profile, pending vouch, finite
verification code, and optional normalized avatar metadata are committed in one
transaction; a late failure also removes the new file. Verification mail is attempted
after commit so provider failure cannot orphan database state or make a safe retry
look like a fresh registration. The response then gives resend guidance without
exposing credentials. Current production chapter/city/group/voucher data, live SMTP,
and the authenticated post-registration frontend journey remain unverified.

`get_vouchers` is the only public voucher operation. GET and the active frontend's
POST pattern accept an optional bounded graduation year and return only voucher ID,
name, graduation year, and chapter ID for active accounts currently marked as
vouchers. Contact details, avatar, department, and authorization metadata are not
public, and the reusable application key is ignored. The frontend now requests the
selected class year from the server instead of downloading every voucher and no
longer labels options with email addresses.

`voucher_pending` and `vouch_action` require a valid Bearer principal and reload the
actor's current active/voucher state. Pending results are limited to rows owned by
that voucher. Decisions lock the vouch first and then both accounts in deterministic
ID order, conceal cross-owner rows as not found, accept only a still-pending row,
and reject unverified or already-approved registrants. Approval updates both the
vouch and account atomically; denial leaves the account unchanged. Notifications run
after commit: both outcomes notify the registrant, while approval additionally
notifies only active exact reviewed account-manager aliases. Provider failure cannot
roll back the decision. Production role distribution, live SMTP, and the complete
authenticated voucher UI journey remain cutover gates.

`get_setup_parameters` reads an arbitrary named row rather than a fixed public
configuration allowlist. Although the reviewed SQL snapshot currently contains only
currency, user-role, expiry-date, and news-category names, future rows could contain
more sensitive operational values. The replacement therefore requires a valid Bearer
token, reloads the actor's current active state, rate-limits per user, and ignores the
legacy reusable `X-API-Key`. It preserves JSON/form `action_type` lookup plus the raw
value and trimmed comma-separated values, selects the oldest duplicate row
deterministically, and returns real HTTP 400/404 statuses. No current source or built
frontend caller was found, so hidden usage and approved production-data comparison
remain cutover gates.

General profile updates are self-service by default; cross-user edits use the same
current-database permission and strictly downward reviewed-role rule. JSON, flat form,
and the active frontend's nested `profile[...]` multipart keys converge on one explicit
partial-update allowlist. Email, role, approval, credential, token, and arbitrary
columns are ignored. Editing an inactive target does not reactivate it. Chapter IDs are
validated before the foreign-key write, and name parts rebuild `fullname` atomically.

Avatar input is limited to 5 MB, verified from decoded image content rather than its
extension or declared MIME type, bounded by pixel count, re-encoded as JPEG/PNG/GIF to
strip metadata, and stored under a server-generated path beneath `upload_root/profiles`.
The user path and legacy attachment metadata commit in the same database transaction;
if any later database operation or commit fails, the newly written file is deleted.
Only that normalized profile directory is mounted for direct image reads.

The legacy `update_user_account` route is not copied. It accepted a caller-selected
`user_id` after checking only bearer validity, logged the raw request and token, and
attempted to write ambiguous field names that do not exist in the reviewed `users`
schema. Its supported profile-editing purpose is consolidated into the allowlisted
`update_profile` route; both previous HTTP methods receive a bounded HTTP 410
response so hidden clients fail explicitly rather than retaining an arbitrary
cross-account write primitive.

The active frontend's account roles are stored as a closed set: `alumni`, legacy
`manager`/`admin`, and the six explicit administrator categories (`super`,
`approval`, `content`, `event`, `finance`, and `storekeeper`). These categories map
to distinct server permissions; a label merely containing "admin" grants nothing.
Role changes use current locked database facts, reject self and unknown-role targets,
require an active/approved/verified target for administrative grants, and revoke the
target's refresh sessions in the same transaction. A super administrator may manage
another super administrator; a legacy administrator may grant any reviewed role to a
currently lower target. That includes `super admin` because the active frontend maps
legacy `admin` to that label and PHP allowed the same grant; peer, higher, and self
targets remain forbidden.

The standalone `update_user_role` and `manage_user_roles` routes are not copied.
Neither has a current frontend caller; both accept free-form role text, and the latter
treats `roles` as a per-user assignment table even though the reviewed schema makes
`role_name` globally unique. Ion Auth `groups`/`users_groups` are a separate legacy
mechanism. Both old routes return HTTP 410 guidance to the bounded
`manage_user_account` contract, and free-form `roles` rows remain non-authoritative.

## Consequences

Some permissive PHP behavior is intentionally narrowed. Existing unrecognized role
labels require explicit review before they can grant privileges. Protected use cases
must reload account state and authorization facts from the database so role changes
and deactivation take effect without waiting for JWT expiry.

Approval decisions lock actor and target rows in deterministic identifier order and
commit before best-effort account-status email. Structured audit events contain only
actor/target identifiers, action, and prior state; a durable audit table remains a
separate schema decision because compatibility work must not add an unreviewed table.
Account-state changes use the same lock, audit, rollback, and post-commit notification
rules. They intentionally preserve `profile_status` because the PHP route changes only
`users.active`; approval state remains authoritative for reactivation eligibility.
Visibility writes lock both account facts and the target profile before merging, emit
only identifiers and changed field names to structured audit logs, and never return
profile or credential data. Directory responses now apply that visibility state before
serialization and never select password, token, selector, IP-address, or other legacy
credential columns. Privileged list responses expose only the minimum account fields
used by the administrator screen and never include extended profile data.
Profile updates return a fresh explicit user/profile projection, never `users.*`.
Structured profile-update audit events contain only actor/target IDs, changed field
names, and whether an avatar changed; they do not include field values or filenames.

Notification reads and read-state changes derive their recipient exclusively from the
active Bearer principal and recheck current account state in the database. The legacy
body `user_id` is ignored. List, single-row, and bounded mark-all operations add the
same recipient predicate and account-creation boundary in SQL; mutations lock eligible
rows and update only the reviewed per-row `notifications.is_read` field. This prevents
caller-selected cross-account access and does not recreate PHP's absent
`notifications_read` or `target_user_id` model. Notification creation and push delivery
remain out of scope until an authorised creator/recipient/provider contract is approved.
