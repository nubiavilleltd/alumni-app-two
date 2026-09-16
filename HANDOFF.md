# Project Handoff

Last updated: 2026-09-16 (Africa/Lagos)
Branch: `backend-dev`
HEAD: `a5da0b94`

## Current Objective

Complete the retained PHP-to-FastAPI migration in verified, production-quality slices while preserving the legacy database during compatibility work and keeping the Markdown ledger and re-reviewed Excel catalogue current.

## Current State

The FastAPI foundation, registration plus nine authentication/recovery/verification routes, public city and privacy-aware welfare-zone catalogues, protected privacy-aware zone roster/self assignment, authorized transaction-safe zone/city management plus bounded bulk geography and alumni import, class-year-filtered public voucher discovery, owned pending-vouch listing/decision, credential-free profile read, allowlisted profile update with normalized avatar storage, protected profile-visibility read/update, privacy-aware member directory/downward account listing, member approval/rejection, protected account activation/deactivation plus bounded role changes, four-card alumni statistics, privacy-minimized birthday discovery, public/protected chapter lookup, authenticated setup-parameter lookup, authenticated notification feed/read state, public announcement feed/content administration, public project feed/content administration, and sixteen security/supersession tombstones are implemented. The latest disposable-MariaDB run passed all 260 tests and all non-coverage gates, but its exact coverage was 89.98% against the required 90%; parser regression tests have since been added and need one full rerun. Full route parity, live providers, production database/media proof, deployment, and cutover remain incomplete.

## Current Task

Goal 7 now has safe announcement, marketplace, project, and leadership slices. Leadership includes public bounded `POST /api/get_leadership` plus authenticated `POST /api/create_leader` and `POST /api/manage_leader` over the reviewed `leadership` table. Public reads preserve the active frontend featured/team/all shape but omit member PII; JSON/form/multipart writes use current-database `MANAGE_CONTENT`, row locks, explicit member/chapter/year validation, featured-row uniqueness, bounded reorder, soft deletion, and safe generated-photo cleanup. The immediate task is one final disposable-MariaDB verification run to confirm the added parser coverage clears 90%, then proceed to the next re-reviewed retained Goal 7 family. The earlier `/api/create_market`, `/api/manage_market`, and `/api/get_market` handlers target an absent `market` table and have no current frontend caller, so they must not be copied without separate legacy-traffic/schema proof. Goal 9 notification read state remains complete; `/api/create_notification` and push delivery remain a design gate.

## Relevant Files

- `PYTHON_FASTAPI_MIGRATION_TASK_BREAKDOWN.md`
- `python-backend/app/api/members.py`
- `python-backend/app/services/members.py`
- `python-backend/app/repositories/members.py`
- `python-backend/app/schemas/members.py`
- `python-backend/app/authorization/policy.py`
- `python-backend/app/integrations/mail.py`
- `python-backend/app/integrations/uploads.py`
- `python-backend/app/integrations/geography_import.py`
- `python-backend/app/integrations/alumni_import.py`
- `python-backend/app/api/auth.py`
- `python-backend/app/services/auth.py`
- `python-backend/app/repositories/auth.py`
- `python-backend/app/schemas/auth.py`
- `python-backend/app/main.py`
- `python-backend/app/api/marketplace.py`
- `python-backend/app/services/marketplace.py`
- `python-backend/app/repositories/marketplace.py`
- `python-backend/app/schemas/marketplace.py`
- `python-backend/app/api/projects.py`
- `python-backend/app/services/projects.py`
- `python-backend/app/repositories/projects.py`
- `python-backend/app/schemas/projects.py`
- `python-backend/app/api/leadership.py`
- `python-backend/app/services/leadership.py`
- `python-backend/app/repositories/leadership.py`
- `python-backend/app/schemas/leadership.py`
- `python-backend/app/api/retired.py`
- `python-backend/app/api/notifications.py`
- `python-backend/app/api/announcements.py`
- `python-backend/app/api/marketplace.py`
- `python-backend/app/services/notifications.py`
- `python-backend/app/services/announcements.py`
- `python-backend/app/services/marketplace.py`
- `python-backend/app/repositories/notifications.py`
- `python-backend/app/repositories/announcements.py`
- `python-backend/app/repositories/marketplace.py`
- `python-backend/app/schemas/notifications.py`
- `python-backend/app/schemas/announcements.py`
- `python-backend/app/schemas/marketplace.py`
- `python-backend/scripts/inventory_php_endpoints.py`
- `python-backend/tests/test_auth_integration.py`
- `python-backend/tests/test_geography_import.py`
- `python-backend/tests/test_alumni_import.py`
- `python-backend/tests/test_uploads.py`
- `python-backend/tests/test_endpoint_inventory.py`
- `python-backend/docs/adr/0004-authorization-policy.md`
- `frontend/src/features/authentication/api/adapters/register.adapter.ts`
- `frontend/src/features/authentication/api/adapters/voucher.adapter.ts`
- `frontend/src/features/authentication/services/auth.service.ts`
- `frontend/src/features/authentication/pages/RegisterDetailsPage.tsx`
- `frontend/src/features/welfare/api/adapters/welfare.adapter.ts`
- `frontend/src/features/welfare/pages/WelfareZonesPage.tsx`
- `frontend/src/features/welfare/types/welfare.type.ts`
- `frontend/src/features/announcements/services/announcement.service.ts`
- `frontend/src/features/announcements/api/adapters/announcement.adapter.ts`
- `frontend/src/features/announcements/types/announcement.types.ts`
- `output/spreadsheet/alumni_portal_fastapi_route_catalogue_re-reviewed.xlsx`

## Recent Changes

- Fixed announcement and marketplace enum serialization, multipart upload type
  handling, model comment encoding, and test URL isolation. The current disposable
  database run passed 260 tests, Alembic drift detection, Ruff, strict mypy,
  Bandit, and pip-audit; coverage missed the gate by 0.02 percentage points.
- Added deterministic JSON and malformed-form parser regression tests for the
  announcement, marketplace, project, and leadership input adapters. The focused
  test file has 12 passing tests, and its Ruff/mypy checks pass. Run the complete
  disposable database gate once to record the final repository-wide percentage;
  do not lower the 90% threshold.

- Added bounded public `POST /api/get_leadership` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_leader` and `POST /api/manage_leader`. Public output preserves the active featured/team/all contract but excludes legacy member PII; mutations validate references, enforce annual duplicate/featured rules, lock rows, bound reorder, soft-delete, and safely store/revoke generated photo overrides.
- Added focused integration coverage for forged/stale role claims, public shaping, soft deletion, and generated-photo rollback after late database failure. Static checks pass; the two new database tests skip without the disposable MariaDB URL. The workbook reconciliation is the immediate documentation follow-up.

- Added bounded public `POST /api/get_projects` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_project` and `POST /api/manage_project`. The project feed only exposes non-deleted active/completed/paused/ongoing presentation data; creation derives ownership and chapter from current database facts, mutations lock rows and allowlist fields, and delete is soft.
- Added JSON/form/multipart project handling with at most six validated/re-encoded generated images, safe add/replace/removal behavior, reference validation, and transaction-safe cleanup after rollback/replacement/deletion. Focused integration coverage proves forged/stale role claims cannot grant content access, public/draft/deletion boundaries, server-owned fields, and late-failure cleanup. Static checks pass; focused database tests skip without the disposable MariaDB URL.
- Updated the ledger, README, handoff, and route catalogue. The three project records distinguish source/static completion from outstanding database/browser/cutover proof.

- Added public bounded `GET|POST /api/get_announcements` plus active-Bearer/current-database-`MANAGE_CONTENT` `POST /api/create_announcement` and `POST /api/manage_announcement`. The routes accept existing JSON/form/multipart frontend shapes, validate explicit fields and chapters, lock state before writes, rate limit, shape public output, and use generated metadata-stripped JPEG/PNG/GIF/WEBP images under a separate announcements directory. Failed database writes remove newly stored images; successful replacement/deletion removes only generated local announcement images.
- Added focused integration coverage for member denial, current-database role changes despite a stale token, public output shaping, create/update/delete, and image/database rollback. Static checks pass; the focused tests are currently skipped without the disposable MariaDB test URL.
- Updated the ledger, README, handoff, and route catalogue. The three records now distinguish implemented source/static evidence from outstanding database/browser/cutover proof.

- Re-reviewed both marketplace API families. The active frontend service calls only `/api/get_listings`, `/api/create_listing`, and `/api/manage_listing`; their current PHP implementation uses the reviewed `marketplace_listings` and `marketplace_social_media` tables, multi-image JSON, social metadata, listing expiry, and owner-or-administrator mutation. The older `market`-table handlers are source-disconnected from both the frontend and reviewed schema and must remain a disposition/retirement decision rather than an implementation shortcut.
- Added public bounded `POST /api/get_listings` over the actual marketplace tables. It applies active/unexpired constraints before selection, chapter/year global scope, fixed search/filter/page bounds, selected presentation fields, batched social data, and a single-listing view increment. It deliberately does not expose legacy user email or write scopes. Ruff and strict mypy pass; database-backed contract proof and all mutations remain next.
- Completed the active marketplace family: `POST /api/create_listing` and `POST /api/manage_listing` accept reviewed JSON/multipart shapes, derive actor ownership/status/featured state server-side, validate/re-encode generated marketplace images, atomically write listing/social rows, support bounded add/replace/removal cleanup, and use owner-or-fresh-current-database `MANAGE_STORE` authorization. Public reads retain legacy http(s) image records for compatibility but do not expose seller email. Focused integration tests cover spoofed ownership, stale JWT/current role facts, and storage rollback; they skip until disposable MariaDB is configured. The route catalogue records all three as source/static implemented with runtime proof pending.

- Added `GET|POST /api/get_notifications` and `POST /api/mark_notification_read` against the reviewed `notifications.user_id`/per-row `is_read` schema. Both derive scope from the active Bearer principal, recheck current database account state, enforce the legacy account-creation cutoff in application UTC, bound list/mark-all work, shape responses, and ignore caller-selected `user_id`.
- Single-row and mark-all changes lock eligible rows and run in one transaction. Focused integration tests prove cross-owner denial, cutoff filtering, stale-account denial, idempotency, bounded mark-all, and rollback after a late repository failure.
- Left `/api/create_notification` and push delivery unimplemented. PHP's ineffective role gate, caller-selected recipients, missing `category`/`title`/`target_user_id`/`push_tokens` storage, and unproven provider/retry/opt-out contract cannot be safely inferred from the reviewed schema.
- Updated the migration ledger, ADR 0004, README, and route catalogue. The workbook now marks two notification read routes built and creation pending; it preserves 148 candidate-retain routes, 159 catalogue routes, and the existing formula structure.

- Security-retired GET/POST `/api/deactivate_staff/{user_id}`. The PHP route accepts a caller-selected target without actor authentication or authorization and permanently deletes the user after deactivation; FastAPI now returns bounded HTTP 410 guidance to the existing state-only, hierarchy-checked `/api/manage_user_account` operation.
- Security-retired GET/POST `/api/user_tokens`. The PHP route trusts a reusable key and caller-selected user ID, logs and returns push-token material, and writes through a `push_tokens` table absent from the reviewed SQL snapshot. A replacement remains a Goal 9 design gate, not implemented functionality.
- Regenerated the 548-record inventory to 148 candidate-retain, 186 candidate-retire, 34 contain-or-remove, 6 internal-only, 54 needs-runtime-proof, 1 platform-replace, 2 replaced-by-consolidated-route, 14 retired-security-risk, and 103 retire-noncontroller-artifact records. The workbook now has 148 direct conversions and 10 consolidated replacements while retaining 159 catalogue routes.

- Added POST `/api/import_alumni` with bearer-first authentication, current active-database `MANAGE_ACCOUNTS` and downward-target authorization, JSON or exact multipart input, 2 MiB/500-row bounds, strict CSV/XLSX/archive/formula/header/value/duplicate validation, enabled chapter/city/member-group checks, and one all-or-nothing transaction.
- Existing imports preserve roles, coordinator state, password/hash state, access/user codes, active/approved/verified/profile status, and profile visibility while reconciling allowlisted profile/chapter/year data. New imports receive fixed alumni/non-coordinator state, an unknown random Argon2id credential with `has_password=0`, empty access code, member group/category, and a private profile. Responses expose only row, status, and user ID.
- Retired the PHP route's reusable-key-only authority, caller-selected shared password, client coordinator grant, forced existing-account activation/approval/verification, source timestamp write, binary XLS, partial commits, and secret-bearing response fields. No current source or built-frontend caller was found.
- Added GET plus bodyless POST `/api/get_birthdays` with current active-account authorization, shared throttling, exact today/week/month/upcoming and Africa/Lagos/February 29 behavior, PHP-compatible missing-key/explicit-false birth-date privacy, malformed-JSON fail-closed handling, deliberate directory-visibility independence, field-aware avatars, deterministic 200-row output, a 10,000-candidate scan bound, and a dedicated active-frontend-compatible response that omits exact DOB, age, contact, account, and unrelated profile fields.
- Added multipart POST `/api/upload_zones_cities` with current active-account `MANAGE_ZONES` authorization, authentication/throttling before parsing, exact one-file and zone/city-header contracts, 2 MiB/2,000-row limits, bounded XLSX archive validation, formula/control-character/cell/duplicate rejection, deterministic catalogue locks, chapter-1 validation for new zones, zone/city chapter alignment, idempotent summaries, bounded errors, PII-free count logs, and all-or-nothing rollback. Legacy binary `.xls` requires conversion.
- Added JSON/form POST `/api/manage_zone` with current active-account `MANAGE_ZONES` authorization, zone-then-city catalogue locks, normalized duplicate checks, chapter and eligible same-chapter coordinator validation, explicit coordinator clearing, atomic child-city chapter alignment, reference-safe deletion, bounded output, PII-free audit metadata, and rollback proof.
- Added JSON/form POST `/api/manage_city` with the same authorization/lock order, global normalized duplicate checks, enforced zone/chapter alignment, automatic chapter alignment on moves, member/profile free-text reference guards for rename/delete, bounded output, and rollback proof.
- Added GET plus JSON/form POST `/api/get_users_by_zone` with active-Bearer/current-account checks, deterministic ID/exact-name resolution, ID precedence, a fixed legacy name-query bug, 100-row pagination, eligible-member filtering, global/city privacy exclusion, owner visibility, field-aware phone/avatar, and no broad PII/account/credential selection.
- Added GET/POST `/api/get_my_zone` as a current-active self-only lookup with spoofed-body immunity, trimmed case-insensitive city matching, deterministic oldest mapping, HTTP 200 unavailable compatibility, explicit null coordinator handling, shared coordinator eligibility/privacy, and no email.
- Added GET/POST `/api/get_cities` with deterministic city/chapter/source-zone output, preserved orphan zone IDs, peer throttling, no member data, and no reusable-key authority.
- Added GET/POST `/api/get_zones` with nested city identity, current coordinator eligibility gates, global/phone/avatar visibility enforcement, malformed-privacy fail-closed behavior, explicit null coordinators, and no coordinator email/role/department selection.
- Updated welfare coordinator mapping/UI to remove email and use member ID for self-message prevention; anonymous users still receive public zone/city structure and in-app messaging retains its existing sign-in gate.
- Added GET/POST `/api/get_vouchers` with optional class-year filtering, active-voucher-only deterministic selection, peer/year throttling, and a minimum public ID/name/year/chapter projection; the legacy shared key has no effect.
- Added GET/POST `/api/voucher_pending` with active Bearer/current-voucher checks, exact owner scoping, pending-only deterministic results, and a dedicated protected schema.
- Added JSON/form POST `/api/vouch_action` with concealed cross-owner IDs, pending replay protection, verified/unapproved registrant guards, deterministic locks, atomic approval or denial-without-account-mutation, bounded responses, PII-free audit fields, and post-commit notifications to the registrant plus exact reviewed managers on approval.
- Updated registration to request vouchers for the selected class year and label choices by name/class rather than email.
- Added current-database authorization, deterministic actor/target row locks, lower-role hierarchy, verified-email and state guards, bounded response fields, and rollback-safe approval/rejection transactions.
- Added private post-commit account-status email; provider failure cannot roll back the database decision.
- Added PII-free structured approval audit events. No durable audit table was added because the legacy schema has no suitable table and schema changes require separate review.
- Added self-deactivation and downward administrator activation/deactivation with current database facts, deterministic row locks, approved/verified reactivation guards, transactional refresh-token revocation, bounded output, rollback proof, PII-free audit events, and post-commit activity email.
- Added bounded role changes to `/api/manage_user_account` using locked current-database actor/target facts, reviewed aliases, self/hierarchy/state gates, atomic refresh-token revocation, bounded responses, rollback proof, and PII-free audit fields.
- Added self/downward-manager profile visibility read/update with current database facts, a fixed 15-field allowlist, deterministic locks, partial-merge/upsert behavior, malformed-JSON fail-closed handling, rollback proof, bounded output, and PII-free audit logging.
- Updated the migration ledger and the workbook's route, grouped, confirmation, and developer-audit views for both visibility routes.
- Replaced `/api/get_users_by_action` with separate bounded approved-directory and downward account-management modes. Directory output applies global/per-field privacy server-side and selects no credential columns; administrator output uses current database authorization and excludes self, peers, higher/unrecognized roles, and unverified accounts.
- Implemented `/api/update_profile` for JSON, flat form, nested multipart profile fields, and optional avatar uploads. User/profile fields are allowlisted, chapter references are validated, responses omit credentials, and cross-user updates require current downward account-management authority.
- Added 5 MB decoded avatar limits, JPEG/PNG/GIF verification, pixel bounds, metadata-stripping re-encoding, generated filenames, profile-only static exposure, attachment metadata, and database/file rollback cleanup. The workbook's four active views now mark the route built and retain production/runtime proof as pending.
- Security-retired `/api/update_user_account` after proving its caller-selected cross-account authorization flaw, raw request/token logging, seven reviewed-schema mismatches, and absence from current source/built frontend. Both legacy methods now return bounded HTTP 410 guidance to `/api/update_profile`; the regenerated inventory quarantines the two legacy-controller copies too.
- Security-retired standalone `/api/update_user_role` and `/api/manage_user_roles`; current source and built frontend use only the shared account route. Their arbitrary/free-form role contracts are not copied, chapter edits remain in the profile flow, and the legacy `roles` table remains non-authoritative metadata.
- Implemented GET and POST `/api/get_alumni_stats` with one bounded four-integer response, current active-account recheck, shared per-user rate limiting, exact reviewed PHP count semantics, and no legacy application-token authorization. No current source or built-frontend caller was found.
- Implemented GET and POST `/api/get_chapters` with a public enabled-chapter list when `user_id` is absent and an active-Bearer self/downward-manager assignment lookup when it is supplied. Output is bounded, unknown roles fail closed, duplicate assignments resolve deterministically to the oldest category row, and no assignment preserves HTTP 200 with `chapter: null`.
- Implemented POST `/api/get_setup_parameters` with bounded JSON/form input, active-Bearer current-state recheck, per-user rate limiting, explicit setup fields, compacted comma values that retain `0`, deterministic oldest-row selection, and real HTTP 400/404 results. The reusable legacy `X-API-Key` is ignored; no current route caller was found.
- Security-retired `/api/create_role`, `/api/manage_role`, and `/api/get_roles` with tested GET/POST HTTP 410 guidance to the fixed account-role contract. Their six backup/old-controller copies are containment targets, the three root copies remain artifact retirements, and the later current inventory totals are recorded above.
- Implemented public JSON/form/multipart registration with peer/identity throttling, bounded password/phone/date input, Argon2id, enabled chapter/city and current active voucher checks, deterministic member codes, and server-owned role/coordinator/year/account state.
- Registration now commits the user, Ion Auth member-group assignment, alumni category, private-by-default profile, optional pending vouch, finite verification code, and optional normalized avatar metadata atomically. A late database failure removes the new file; post-commit mail failure preserves the account and returns resend guidance.
- Removed the frontend's chapter `1` registration assumption; the selected city now supplies its live `chapterId`, and submission fails locally when no mapping is available.

## Decisions and Reasoning

- JWT role claims never grant approval authority; current `users.user_role` and active state are loaded under lock.
- Managers may decide reviewed member aliases; administrators may also decide managers; super administrators may decide lower reviewed roles. Self, peer/higher, and unknown-role targets fail closed.
- Approval requires verified email. Rejecting an already approved account is redirected conceptually to the pending deactivation/account-management slice.
- The frontend source directly calls `/api/approve_user`, but currently changes roles through `/api/manage_user_account`; do not assume the two PHP role routes are active frontend contracts.
- The authorization role set is fixed to alumni, manager, legacy admin/super admin, and the reviewed approval/content/event/finance/storekeeper administrator categories. Category roles receive only their mapped permissions; unknown roles fail closed.
- Legacy `admin` may assign any reviewed role to a lower target, preserving the active frontend's `admin` to `super admin` mapping. A super administrator may change another recognized role but never self; administrative promotion requires an active, approved, email-verified target.
- `users.user_role` is the authorization source. Ion Auth groups and free-form `roles` rows are separate legacy stores and do not grant FastAPI permissions.
- Aggregate alumni statistics preserve PHP's inclusion rules but require an active Bearer principal. They expose no member rows, identifiers, credentials, or free-form filters; legacy token values in query/body data have no effect.
- Birthday discovery preserves PHP's active-user eligibility, Lagos calendar boundary, February 29 observance, and missing-key-public/explicit-false-private rule, but requires a current active Bearer account. Malformed visibility fails private, global directory hiding remains independent, and private avatars are masked. The active frontend's five card fields are retained; exact DOB, age, contact, account, and unrelated profile fields are intentionally retired. Processing is read-only and bounded separately from the 200-row output limit.
- Chapter discovery is public only without a user target so registration can obtain enabled IDs without a shared application secret. A supplied `user_id` changes the contract to protected current-database self/downward-manager authorization; an invalid supplied Bearer token never falls back to public access.
- Setup-parameter lookup requires a current active Bearer account because its name is caller-selected and can address any future row, not a reviewed fixed public allowlist. The four generic names seen in the SQL snapshot do not justify exposing future configuration through a reusable application key.
- Dynamic `roles` definitions are not an authorization source. The legacy definition routes have no current caller or reviewed data, trust only a reusable application key, and conflict with both the table's mandatory `user_id` and the fixed account-role policy, so a `Live/KEEP` audit label does not justify rebuilding them.
- Legacy staff deactivation cannot be treated as parity work: deleting a caller-selected user after deactivation is incompatible with the reviewed account-state contract. Hidden callers must move to `manage_user_account`; the tombstone must remain.
- Push-token ownership must come from the current authenticated member, never a caller-selected ID or reusable application key. Do not add storage or provider behavior until hidden/mobile callers and the live data model are confirmed; token values must not be logged or returned.
- Notification ownership is current-principal-only. The body can retain legacy `user_id` compatibility noise but it cannot affect reads or mutations. SQL predicates include the authenticated recipient and the legacy account-creation boundary; the current per-row `is_read` schema replaces the absent PHP `notifications_read` table.
- Announcement reads are intentionally public after current frontend/homepage review, but are bounded and presentation-shaped. Announcement mutation authority is current database `MANAGE_CONTENT`, never a JWT role claim or reusable API key. Event-administrator frontend display affordances do not expand the canonical content permission until product policy explicitly approves that change.
- Registration is intentionally public but bounded. It never accepts legacy shared-key or Bearer authority, ignores privilege-bearing extras, requires the reviewed member group, and owns initial authorization/account state on the server. Mail delivery occurs after commit so retry guidance cannot create duplicate partial users.
- Voucher discovery is public only for the minimum registration picker projection. Pending and decision operations trust neither JWT role claims nor caller-selected owner IDs: they reload current active/voucher state and bind access to `vouches.voucher_id`. Approval changes both rows atomically; denial changes only the vouch. All notification work is post-commit.
- City and welfare-zone catalogues remain public because registration and the current welfare route need them before authentication. Public geography never trusts the embedded application key. Coordinator email is not part of the visibility model and is therefore never exposed; assigned coordinator phone/avatar follow eligibility plus the same stored visibility defaults and malformed-state fail-closed behavior as the member directory.
- Zone-member discovery is protected because the legacy response exposes membership and broad personal/account data behind only a reusable key. FastAPI resolves a zone ID or exact name but returns only active/approved/verified members; another member's global hiding or private city removes the row so zone membership itself does not bypass location privacy. Missing keys remain public for compatibility, malformed JSON fails private, the owner may see their own row, and pages are capped at 100.
- Self-zone lookup accepts no target identity and reloads current active account state. Duplicate case-insensitive city mappings select the oldest city row; missing/unknown/orphan mappings keep the legacy HTTP 200 unavailable shape. Coordinator output reuses the reviewed public-zone eligibility/privacy projection and never includes email.
- Geography mutation trusts only the current active database role through `MANAGE_ZONES`; JWT role claims and reusable application keys grant nothing. Zone and city writes share one deterministic lock order. Normalized names conflict globally; zones own their child-city chapter metadata; coordinators must be active, approved, verified, and in the same chapter; and legacy free-text member/profile city references block city rename/delete rather than being silently orphaned.
- Bulk geography import preserves those authorization, lock-order, duplicate, and chapter-alignment rules across the entire file. It prevalidates before mutation, limits parser and archive work, reports actual changes so reruns are idempotent, and rolls back every row on a late failure. Binary `.xls` and raw parser errors are intentionally not copied from PHP; new-zone chapter 1 remains a compatibility rule that must be proven against approved production data.
- Alumni import is an account-management operation, not reusable-key ingestion. Current database facts and downward hierarchy govern every existing target; complete roster/chapter/city/configuration validation precedes SQL. Existing account authority, credentials, state, and visibility remain authoritative. New members have no known password and must use the secure reset flow. The client coordinator flag is counted as ignored evidence but never grants privilege.
- `manage_user_account` preserves PHP's `profile_status` during activation/deactivation and changes only `active`; approval state and verified email gate reactivation. Deactivation revokes every active refresh token transactionally.
- Visibility defaults remain PHP-compatible public when a key is absent, but malformed stored JSON fails closed to private. Directory/list responses must apply these settings before returning member fields.
- `get_users_by_action` defaults to the approved directory rather than PHP's broad verified-user fallback. Unknown actions are rejected. Pending/all-user modes require current `MANAGE_ACCOUNTS` permission and use a separate minimum account projection limited to strictly lower reviewed roles.
- Profile editing never copies PHP's implicit `active=1`; an inactive target remains inactive. Avatar bytes are decoded and re-encoded before storage, and a newly stored file is deleted when its database transaction fails.
- A developer-audit `Live/KEEP` label is reachability evidence, not approval to copy an unsafe contract. `update_user_account` is treated as a security retirement because its supported profile purpose is superseded and its remaining fields have no reviewed schema meaning.

## Known Issues

- Developer audit discrepancies remain unresolved: 175 summary versus 163 detail endpoints, missing `Socials` controller row, and 24-versus-25 core functions.
- Authenticated frontend role journeys, current production role distribution, live SMTP/Redis, approved current-database behavior, deployment, and cutover are unverified.
- Durable audit storage needs an approved schema/operations decision.
- Production media persistence and old-avatar retention/deletion policy remain unverified.
- Current production city/chapter/member-group/voucher data, live registration SMTP delivery, and the complete browser registration-to-verification journey remain unverified.
- The authenticated voucher pending/decision UI journey, current production voucher assignments, exact manager-role distribution, live voucher SMTP, and production concurrency remain unverified.
- Approved production city/zone relationships, coordinator eligibility/privacy values, and public registration/welfare browser journeys remain unverified.
- Hidden callers, approved production zone-member counts/privacy states, duplicate city mappings, and authenticated roster/self-zone browser behavior remain unverified.
- Hidden callers, approved production role/chapter/coordinator/city-reference data, representative concurrent administrator mutations, and the administrator UI journey for `/api/manage_zone` and `/api/manage_city` remain unverified.
- No current source or built-frontend caller for `/api/upload_zones_cities` was found. Approved production chapter-1 and duplicate geography data, edge/proxy body-size enforcement, representative concurrent imports, and the administrator upload UI remain unverified.
- No current source or built-frontend caller for `/api/get_setup_parameters` was found; hidden traffic and the approved production setup-name/value contract remain unverified.
- The current frontend source calls `/api/get_birthdays`, but its authenticated browser journey, hidden callers, approved production birth-date/visibility quality, candidate volume, and midnight/leap-day runtime behavior remain unverified outside the disposable SQL suite.
- No current source or built-frontend caller for `/api/import_alumni` was found. Hidden traffic, approved production chapter/city/membership/account data, representative 500-row scale, edge/proxy body limits, concurrency, and an administrator import/password-setup workflow remain unverified.
- Hidden callers for the three retired dynamic role-definition routes remain unverified; they must receive cutover guidance rather than reactivating the unsafe contracts.
- Hidden callers for `deactivate_staff` and `user_tokens` remain unverified. Staff callers must migrate to `manage_user_account`; push callers require a separately reviewed provider/schema and authenticated self-service contract before any replacement is built.
- No current source or built-frontend caller was found for the notification routes. Hidden/client behavior, the live notification data shape, and creator/recipient/provider/retry/opt-out requirements remain cutover gates. `/api/create_notification` is deliberately absent.
- Announcement integration tests require the disposable MariaDB URL; current runtime checks only prove type/lint and test collection. Hidden callers, public-feed frontend behavior, exact content-admin role product policy, production object/media storage/CDN policy, cross-process cleanup, and authenticated browser workflows remain cutover gates.
- Marketplace requires disposable-MariaDB/browser proof, a manager-versus-current-`MANAGE_STORE` product decision, exact legacy GET compatibility decision, production object-media policy, and hidden-caller confirmation. Projects require the same disposable-MariaDB/browser, object-media, hidden-caller, and cutover proof; their public read is intentionally POST because that is the active frontend contract. Do not reintroduce the absent legacy `market` table to satisfy the old route names.

## Important Constraints

- Run Python only through `python-backend/.venv` and set `PIP_REQUIRE_VIRTUALENV=true` for package execution.
- Never use production data or credentials for tests.
- Preserve the fourteen security-sensitive inventory retirements, ten workbook consolidated replacements, sixteen executable tombstones, root `Api.php` retirement, user workbook notes, and unrelated working-tree changes.

## Commands and Tests

From `python-backend/`, with `ALUMNI_TEST_DATABASE_URL` pointing only to the disposable schema:

```powershell
$env:PIP_REQUIRE_VIRTUALENV = "true"
.\scripts\verify.ps1
```

Latest full result: the complete `.venv` gate ran against the disposable schema with 260 passing tests, Ruff format/check, strict mypy, `pip check`, Alembic drift detection, Bandit, and `pip-audit` with no known vulnerabilities. The sole failure was exact coverage of 89.98% against the 90% gate. New parser regression tests have 12 focused passing tests with Ruff and strict mypy clean; rerun the complete gate once with `ALUMNI_TEST_DATABASE_URL` to record the updated percentage. The updated workbook still requires its post-project Excel COM recalculation/open and visual route-catalogue review. Repository-wide Prettier remains a 477-file baseline failure; npm previously reported 17 dependency advisories (2 low, 2 moderate, 12 high, 1 critical), and Vite retains stale-Browserslist and large-bundle warnings. No lockfile-changing automatic fix was attempted.

## Next Actions

1. Start only the disposable MariaDB test service and run the focused marketplace/project then full verification suite; do not use production data or credentials.
2. Decide whether legacy marketplace GET compatibility and the PHP manager override are required, then adjust the contract only with documented current-policy approval.
3. Select and re-review the next safe retained Goal 7 endpoint after confirming hidden announcement/notification/marketplace/project callers and media policy.
