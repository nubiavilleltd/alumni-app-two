# Backend Handoff: Protected Email and Phone Details

## Purpose

The frontend now masks selected contact details and only reveals them after an explicit user action. At the moment, the reveal request is simulated locally: the component waits briefly and returns the contact value that was already included in the list/detail response.

The backend must complete this feature in two parts:

1. Remove raw email and phone values from the in-scope list/detail responses.
2. Add one authenticated endpoint that returns one requested contact field on demand.

This handoff is intentionally limited to the surfaces that already use the protected-contact UI or are being prepared for it. It does not ask for a blanket removal of contact fields from every endpoint in the application.

## Affected APIs

| API                                                                | Status                                | Required change                                                                                                                                                                                                                              |
| ------------------------------------------------------------------ | ------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `POST /api/get_listings`                                           | Existing                              | Remove raw listing/business `phone`, `email`, and `whatsapp` values, including nested seller/owner aliases; return `has_phone`, `has_email`, and `has_whatsapp`. This endpoint serves both marketplace lists and the business detail lookup. |
| `POST /api/get_users_by_action`                                    | Existing                              | Remove raw contact values from alumni directory/profile responses; return contact availability flags while keeping all non-contact profile data.                                                                                             |
| `GET /api/alumni/{slug}`                                           | Existing/available detail route       | Apply the same alumni response serializer if this route is used for profile detail. It must not become a bypass around the protected list/profile contract.                                                                                  |
| `POST /api/get_zones`                                              | Existing                              | Remove nested coordinator `phone` and `email`; return `has_phone` and `has_email` inside the coordinator object.                                                                                                                             |
| Site/contact configuration API                                     | Not currently present in the frontend | If organization contact configuration moves from `getSiteConfig()` to a backend endpoint, return only contact availability there; do not embed the organization phone/email in public config.                                                |
| `GET /api/protected-contact/{resource_type}/{resource_id}/{field}` | New                                   | Return exactly one authorized contact value after an authenticated on-demand request.                                                                                                                                                        |

These existing write APIs do not need their owner’s submitted contact values removed: `POST /api/create_listing`, `POST /api/manage_listing`, `POST /api/update_profile`, and `POST /api/contact_us`. They must continue to validate and store submitted data normally, but must not echo those values in their response unless a separate protected-read contract is intended.

## Current frontend behavior

The shared component is:

```text
src/shared/components/ui/ProtectedContactValue.tsx
```

It supports two interactions:

- Clicking the masked value/icon opens the phone dialer, email client, or WhatsApp.
- Clicking the copy icon copies the revealed value to the clipboard.

The list cards use `ProtectedContactAvailability`, which displays only channel icons and never displays the value.

The temporary adapter is:

```text
src/shared/api/protectedContactApi.ts
```

Once the backend endpoint is available, this adapter should make the request instead of returning `fallbackValue`.

## In-scope resources

| Frontend surface                       | Current resource type  | Resource ID                    | Fields to protect                            | Current UI                                    |
| -------------------------------------- | ---------------------- | ------------------------------ | -------------------------------------------- | --------------------------------------------- |
| Marketplace listing card               | `marketplace-business` | `businessId` / listing `id`    | `phone`, `email`, `whatsapp`                 | Availability icons only                       |
| Marketplace business detail            | `marketplace-business` | `businessId` / listing `id`    | `phone`, `email`, `whatsapp`                 | Masked value, open action, copy action        |
| Alumni profile                         | `alumni-profile`       | `alumnus.memberId` / user `id` | `email`, legacy `phone`, `alternative_phone` | Masked profile rows, open action, copy action |
| Welfare zone coordinator               | `welfare-zone`         | `zone_id`                      | Coordinator `phone`, `email`                 | Availability icons only on the zone card      |
| Contact Us / Welfare Committee contact | `organization-contact` | `site`                         | Organization `phone`, `email`                | Masked value, open action, copy action        |

### Alumni field naming clarification

The current alumni adapter maps the backend field `phone` to the frontend field `whatsappPhone`, and the profile UI labels it “WhatsApp”. Do not silently reinterpret this existing field as a different number.

For the first implementation, either:

- keep the existing database/API meaning of `phone` and expose it on demand as `whatsapp`, or
- introduce an explicit `whatsapp` response/source field and update the frontend adapter at the same time.

The current frontend’s internal `alternativePhone` name should map to the backend’s preferred snake-case field `alternative_phone`.

## Public list/detail response rules

For the resources above, the normal response must contain all non-contact fields as it does today, but must not contain the raw values under any of these locations:

- top-level `email`
- top-level `phone`
- top-level `whatsapp`
- top-level `alternative_phone`
- nested `user.email`, `profile.email`, `seller.email`, `owner.email`, or equivalent aliases
- nested coordinator contact values in welfare-zone responses

Do not solve this only by omitting one top-level key. The serializer/query must prevent the same value from leaking through a nested owner, seller, user, or profile object.

Return availability flags instead. The flags reveal only that a value exists, not the value itself.

### Marketplace listing example

```json
{
  "id": "BIZ-2026-001",
  "user_id": "39",
  "title": "Example Business",
  "description": "Business description",
  "category": "services",
  "location": "Ikeja, Lagos",
  "seller_name": "Ada Example",
  "has_phone": true,
  "has_email": true,
  "has_whatsapp": false
}
```

There must be no `phone`, `email`, or `whatsapp` value anywhere in this public listing item.

### Alumni profile example

```json
{
  "id": "39",
  "fullname": "Ada Example",
  "graduation_year": 2012,
  "city": "Ikeja",
  "state": "Lagos",
  "has_email": true,
  "has_phone": true,
  "has_alternative_phone": false
}
```

If the existing `phone` field represents WhatsApp, document that explicitly in the API response contract or return `has_whatsapp` instead of making the frontend infer the meaning.

### Welfare-zone example

```json
{
  "zone_id": 4,
  "zone": "Zone 4",
  "chapter_id": 1,
  "coordinator": {
    "user_id": 39,
    "name": "Ada Example",
    "avatar": "/uploads/avatar.png",
    "has_phone": true,
    "has_email": true
  },
  "cities": ["Ikeja", "Maryland"]
}
```

The coordinator object must not include `phone` or `email` in this response.

### Organization contact example

The public site/contact configuration should expose the location/address normally, but only expose contact availability for the protected fields:

```json
{
  "address": "Lagos, Nigeria",
  "has_phone": true,
  "has_email": true
}
```

The actual organization phone/email must not be embedded in a public content/config response.

## On-demand endpoint

### Endpoint

```http
GET /api/protected-contact/{resource_type}/{resource_id}/{field}
```

Examples:

```http
GET /api/protected-contact/marketplace-business/BIZ-2026-001/phone
GET /api/protected-contact/alumni-profile/39/email
GET /api/protected-contact/welfare-zone/4/email
GET /api/protected-contact/organization-contact/site/phone
```

Canonical path values should use snake case for multi-word fields:

- `email`
- `phone`
- `whatsapp`
- `alternative_phone`

The frontend currently uses `alternativePhone` internally, so its adapter will translate that name to `alternative_phone` in the URL.

### Headers

Use the same authentication headers as the other protected custom-backend endpoints:

```http
Authorization: Bearer <access_token>
X-API-Key: <application_api_key>
```

The backend must derive the current user from the bearer token. Do not trust a client-supplied `user_id` or `X-User-Id` header for authorization.

### Success response

Keep the value at the top level so the frontend adapter can consume the contract without knowing the database shape:

```json
{
  "status": 200,
  "message": "Protected contact retrieved successfully",
  "resource_type": "marketplace-business",
  "resource_id": "BIZ-2026-001",
  "field": "phone",
  "value": "+2348012345678"
}
```

### Error responses

Use the application’s normal `status`/`message` error shape.

- `401` — missing or invalid authentication
- `403` — authenticated user cannot view this resource/contact field
- `404` — resource or requested contact field does not exist
- `429` — request rate limit exceeded

Do not return the value in an error message, debug payload, or alternate response key.

## Authorization and privacy rules

The endpoint must check all of the following before returning a value:

1. The bearer token is valid and the account is active.
2. The resource exists and is visible to the requesting user.
3. The requested field is allowed by the resource’s privacy/visibility rules.
4. The requested field actually has a value.

Recommended defaults:

- Marketplace business contacts: available only to authenticated portal users who can view the listing.
- Alumni contacts: respect the user’s profile visibility and per-field privacy setting. The profile owner and authorized administrators may follow the existing owner/admin policy.
- Welfare coordinator contacts: available only to authenticated users who can view the welfare zone.
- Organization contacts: available to authenticated portal users; administrators can retain normal management access.

The exact role policy can follow the existing permission system, but it must be enforced server-side and must not be inferred from the URL.

## Security and operational requirements

- Rate-limit requests per user and IP. A separate request for each field is intentional, but it must not permit unlimited harvesting.
- Record an audit event containing requester, resource, field, timestamp, and outcome. Never store the raw value in the audit log.
- Do not cache raw values in public HTTP caches, list-query caches, analytics events, or application logs.
- Use HTTPS only.
- Normalize phone values only for the action being performed; preserve the canonical stored value for copy/email actions.
- Return `404` for an absent field rather than returning an empty value that looks successful.
- The list/detail query tests should assert that a raw value is absent from the serialized JSON, including nested objects.

## Frontend cutover

The current frontend passes `fallbackValue` because the endpoint is not implemented yet. The cutover should be:

1. Implement and test the endpoint above.
2. Update `src/shared/api/protectedContactApi.ts` to call it and parse `value`.
3. Update the five in-scope resource adapters to use `has_*` flags for availability.
4. Remove the fallback return from the protected-contact adapter.
5. Verify the list cards still show only icons and that detail/profile actions reveal or copy the value only after the request.
6. Confirm that raw contact values no longer appear in browser network responses for normal list/detail requests.

## Explicitly out of scope for this change

Do not alter these flows as part of this handoff unless a separate privacy decision is made:

- the logged-in user’s own profile/edit form
- authentication/session responses required by the current user session
- the email a user enters into the Contact Us submission form
- event attendee exports and admin-only attendee data
- marketplace create/update request bodies, which still need the user’s own contact details to be submitted
