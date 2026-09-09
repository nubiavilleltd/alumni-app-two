# Backend Handoff: Nigerian State and City Locations

## Purpose

The frontend now uses one shared location catalogue for state/city controls instead of allowing users to type arbitrary values. The backend should provide the same catalogue and return cities based on the selected state.

The temporary frontend fallback is:

```text
src/shared/constants/nigeriaLocations.ts
```

This file was generated from the supplied Nigeria GeoJSON/LGA dataset. It contains 37 state groups and 774 LGA values. Because the source dataset provides LGAs rather than a formal city directory, the frontend currently exposes those LGA names as `cities`.

The backend should use the file as the initial data reference, while keeping the catalogue in backend storage so new cities/LGAs can be added later without a frontend deployment.

## Affected APIs

### New/shared catalogue API

| API                        | Status                                                     | Required change                                                                                                                        |
| -------------------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /api/fetch_locations` | Existing frontend contract; backend implementation pending | Return all active state/city groups when no state is supplied, and only the selected state’s cities when `?state={state}` is supplied. |

### Read APIs that consume the catalogue

These endpoints should continue returning their normal records, but any state/city filters or returned location fields must use the canonical catalogue values:

| API                             | Feature                  |
| ------------------------------- | ------------------------ |
| `POST /api/get_users_by_action` | Alumni directory/profile |
| `POST /api/get_listings`        | Marketplace              |
| `POST /api/get_events`          | Events                   |
| `POST /api/get_projects`        | Projects                 |
| `POST /api/get_vacancies`       | Job vacancies            |
| `POST /product/fetch_addresses` | Store addresses          |

### Write APIs requiring state/city validation

The backend must validate that the submitted city belongs to the submitted state in these APIs:

| API                                                          | Feature                                                     |
| ------------------------------------------------------------ | ----------------------------------------------------------- |
| `POST /api/update_profile`                                   | Alumni profile updates                                      |
| `POST /api/create_listing` and `POST /api/manage_listing`    | Marketplace create/update                                   |
| `POST /api/create_event` and `POST /api/manage_event`        | Event create/update where location is split into state/city |
| `POST /api/create_project` and `POST /api/manage_project`    | Project create/update                                       |
| `POST /api/create_vacancy` and `POST /api/manage_vacancy`    | Job vacancy create/update                                   |
| `POST /product/add_address` and `POST /product/edit_address` | Store addresses                                             |

The location catalogue endpoint is the only new shared lookup API requested in this document. The other APIs are affected because they must consume or validate the canonical values.

## Current frontend consumers

The shared `useLocations` hook is currently used by:

- alumni directory/profile location controls
- marketplace listing and business-management forms/filters
- events filters/forms
- projects filters/forms
- job vacancy filters/forms
- store address selection

The response shape must therefore remain stable across all of these surfaces.

## Canonical data model

The minimum backend model is:

```text
location_states
- id
- name
- normalized_name
- is_active
- sort_order

location_cities
- id
- state_id
- name
- normalized_name
- is_active
- sort_order
```

If the backend prefers one JSON/config table, it must still expose the same logical relationship: one state has many cities.

Use canonical display names from the supplied file. Do not create duplicate states because of casing, whitespace, or known spelling aliases. The frontend currently normalizes `Nassarawa` to `Nasarawa`; the backend should store one canonical spelling and optionally support aliases during lookup.

### Important terminology note

The current `cities` array contains LGA names. This is deliberate for the first product version and matches the existing frontend controls. If the product later needs actual cities/towns rather than LGAs, add a clear backend data-version decision and migrate the values consistently; do not mix both types without a field-level definition.

## Endpoint

The existing frontend endpoint is:

```http
GET /api/fetch_locations
```

The endpoint should support both initial catalogue loading and dependent city loading.

### Load all states and their cities

```http
GET /api/fetch_locations
```

Response:

```json
{
  "status": 200,
  "message": "Locations retrieved successfully",
  "locations": [
    {
      "state": "Abia",
      "cities": ["Aba North", "Aba South", "Arochukwu"]
    },
    {
      "state": "Lagos",
      "cities": ["Ikeja", "Lagos Island", "Lagos Mainland"]
    }
  ]
}
```

This form is compatible with the frontend’s current normalizer and allows the initial state dropdown to be populated in one request.

### Load cities for one selected state

```http
GET /api/fetch_locations?state=Lagos
```

Response:

```json
{
  "status": 200,
  "message": "Locations retrieved successfully",
  "locations": [
    {
      "state": "Lagos",
      "cities": ["Ikeja", "Lagos Island", "Lagos Mainland"]
    }
  ]
}
```

The selected state must control the returned city list. Never return cities from unrelated states when `state` is supplied.

An implementation may add a dedicated endpoint such as `GET /api/fetch_locations/{state}/cities`, but the query-string form should remain supported because it matches the current frontend endpoint and makes rollout easier.

## Request and response rules

- State matching should be case-insensitive and whitespace-tolerant, but responses must return the canonical display name.
- URL-decode the state parameter before matching it.
- Return cities in a deterministic order, preferably the source file’s alphabetical order.
- Remove duplicate state or city names after normalization.
- Return only active states/cities.
- If no state is provided, return every active state group.
- If a state is unknown, return `404` with a clear message or the application’s documented empty-result convention. Do not silently return all states.
- If a valid state has no active cities, return the state with `cities: []`.
- The endpoint should not return addresses; address remains a separate free-text field where a form needs it.
- Include stable IDs later if useful, but do not remove the current `state` and `cities` strings. The current frontend consumes strings.

Recommended headers:

```http
X-API-Key: <application_api_key>
Authorization: Bearer <access_token>
```

The catalogue itself is not highly sensitive. The API key is required by the existing client convention; bearer authentication can remain optional if the backend needs the endpoint for logged-out public forms.

## Dependent-filter behavior expected by the frontend

The desired behavior is:

1. Load states.
2. User selects a state.
3. Clear any previously selected city that does not belong to that state.
4. Load or derive the cities for the selected state.
5. Enable the city dropdown and show only those cities.
6. If the state is cleared, clear the city and return the city control to its unfiltered state.

The backend is responsible for authoritative lookup. The frontend may cache the all-locations response for fast dropdown rendering, but submitted state/city values must still be validated against the backend catalogue.

## Validation on write endpoints

Every endpoint that accepts a state/city pair should validate:

- the state exists and is active
- the city exists and is active
- the city belongs to the submitted state

This applies to marketplace businesses, alumni profile updates, events, projects, job vacancies, and store addresses where the fields are present.

Do not trust a city supplied by the browser merely because it appeared in a previous response. Re-check the relationship server-side to prevent inconsistent records and typos.

Suggested validation error:

```json
{
  "status": 422,
  "message": "Selected city does not belong to the selected state.",
  "errors": {
    "city": "Choose a city from the selected state."
  }
}
```

## Initial data import and ownership

1. Import the state/city pairs from `src/shared/constants/nigeriaLocations.ts`.
2. Deduplicate and normalize spelling before inserting.
3. Keep a seed/import script so the dataset can be refreshed without manual copy/paste.
4. Make additions through backend-managed data or an admin workflow.
5. Preserve inactive records rather than deleting them if they are already referenced by existing marketplace, profile, event, project, job, or address records.

The frontend fallback should remain temporarily during rollout. Once the endpoint is reliable in every environment, the fallback can remain as an offline/error safety net or be removed by a separate release decision.

## Acceptance checklist

- [ ] All Nigerian state groups from the supplied file are available.
- [ ] A selected state returns only its own cities.
- [ ] Changing state clears an incompatible city in the UI.
- [ ] State/city write validation rejects mismatched pairs.
- [ ] Canonical spelling is consistent, including the Nasarawa alias case.
- [ ] The empty-state response is valid JSON with `locations: []` or the documented state-with-empty-cities behavior.
- [ ] Responses are deterministic and contain no duplicate values.
- [ ] Existing frontend consumers can continue using `state` and `cities` strings without a breaking response change.
