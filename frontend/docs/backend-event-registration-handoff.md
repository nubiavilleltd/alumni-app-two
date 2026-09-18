# Backend Handoff: Event Registration Forms and Additional Information

## Purpose

Event creation, editing, RSVP registration, and attendee loading already use the custom backend. The extra registration requirements/additional questions are the exception: their definitions, validation, submission storage, and attendee-detail reads currently go through Firebase Functions, with local compatibility storage and a text serialization bridge in the frontend.

The goal is to make the custom backend the single source of truth for:

- event registration forms
- ordered questions
- form versions
- RSVP records
- structured answers
- the optional additional-info note
- attendee submission detail and export data

**Current architecture decision (2026-09-18):** FastAPI plus MariaDB is the canonical live system for forms, versions, RSVP records, and answers. Firebase/Firestore/Cloud Functions must not be extended or receive new live form/answer writes. They are retained only as a future approved, sanitized, read-only historical-export source until existing records are migrated or intentionally archived. This document describes the frontend cutover required to retire that temporary dependency.

## Affected APIs

### Existing custom-backend APIs to extend

| API                             | Required change                                                                                                                                                             |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `POST /api/get_events`          | Include `has_registration_questions` and `registration_form_count`, derived from active backend forms. Also exclude past events from announcement eligibility.              |
| `POST /api/create_event`        | Continue creating the core event; optionally accept nested `registration_forms` for the final atomic implementation.                                                        |
| `POST /api/manage_event`        | Continue editing/deleting the core event; optionally accept nested form changes in the final atomic implementation. Editing a past event must remain allowed.               |
| `POST /api/register_event`      | Accept and validate structured `answers` together with the RSVP and `additional_info`, then save everything in one transaction.                                             |
| `POST /api/manage_event_rsvp`   | Preserve cancel/update RSVP behavior and ensure it does not delete historical structured answers unless an explicit product rule requires that.                             |
| `POST /api/get_event_attendees` | Remains the normal attendee list API. It can continue returning its current attendee summary while the submission flag/detail data moves to the new custom endpoints below. |

### New custom-backend APIs

| API                                                  | Replaces or supports                                                                       |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `POST /api/get_event_registration_forms`             | Firebase `getEventSurveyForms`                                                             |
| `POST /api/manage_event_registration_form`           | Firebase `upsertEventSurveyForm` and `archiveEventSurveyForm`, selected by `function_type` |
| `POST /api/reorder_event_registration_forms`         | Firebase `reorderEventSurveyForms`                                                         |
| `POST /api/validate_event_registration`              | Firebase `validateEventSurveyRegistration`                                                 |
| `POST /api/get_event_registration_submissions`       | Firebase `getEventSurveySubmissions`                                                       |
| `POST /api/get_event_registration_submission_detail` | Firebase `getEventSurveySubmissionDetail`                                                  |

### Firebase operations to retire

The following Firebase Functions must no longer be called after frontend cutover:

`getEventSurveyForms`, `validateEventSurveyRegistration`, `upsertEventSurveyForm`, `archiveEventSurveyForm`, `reorderEventSurveyForms`, `submitEventSurveyRegistration`, `getEventSurveySubmissions`, and `getEventSurveySubmissionDetail`.

## Current frontend behavior to preserve

Relevant frontend files:

```text
src/features/events/pages/CreateEventPage.tsx
src/features/events/pages/EditEventPage.tsx
src/features/events/components/EventRegistrationFormBuilderModal.tsx
src/features/events/components/RegisterEventModal.tsx
src/features/events/components/EventRegistrationQuestionField.tsx
src/features/events/pages/AttendeesPage.tsx
```

### Admin creates an event

- An admin creates the normal event first.
- The form builder can attach zero or more request-info sections.
- Each section has a name and one or more ordered questions.
- Questions can be reordered.
- The current frontend saves the event and then upserts each form through Firebase.
- It then synchronizes a metadata flag so the event list knows that questions exist.

### Admin edits an event

- Existing forms load for the event.
- The admin can add, edit, reorder, or remove sections and questions.
- Removed forms are archived rather than physically deleted in the intended model.
- Existing submitted answers must remain readable after any edit.
- Editing a past event must remain allowed. The event date being in the past must not prevent an otherwise valid content/form edit.

### A user registers

- The registration modal loads all active forms for the event.
- Forms render in `sort_order`; questions render in `order`.
- The user submits one RSVP with all answers and an optional free-text note.
- Current RSVP statuses are `going`, `maybe`, and `not_going`.
- Required answers, option membership, and checkbox limits are currently checked in the browser. The backend must repeat all checks.

### Admin views attendees

- The existing attendee endpoint returns the normal attendee list.
- A separate Firebase list call identifies attendees with saved survey responses.
- A detail call loads the exact form/question snapshots for one attendee.
- The page displays requested information and includes dynamic question columns in CSV export.

### Firebase-to-backend replacement map

The current Firebase client exposes these operations. The custom backend should provide the following replacements:

| Current Firebase operation        | Custom backend replacement                                               |
| --------------------------------- | ------------------------------------------------------------------------ |
| `getEventSurveyForms`             | `POST /api/get_event_registration_forms`                                 |
| `validateEventSurveyRegistration` | `POST /api/validate_event_registration`                                  |
| `upsertEventSurveyForm`           | `POST /api/manage_event_registration_form` with `function_type: upsert`  |
| `archiveEventSurveyForm`          | `POST /api/manage_event_registration_form` with `function_type: archive` |
| `reorderEventSurveyForms`         | `POST /api/reorder_event_registration_forms`                             |
| `submitEventSurveyRegistration`   | The structured `answers` part of `POST /api/register_event`              |
| `getEventSurveySubmissions`       | `POST /api/get_event_registration_submissions`                           |
| `getEventSurveySubmissionDetail`  | `POST /api/get_event_registration_submission_detail`                     |

After cutover, these operations should no longer depend on Firebase Functions, Firestore, or Firebase-specific headers.

## Supported question model

Use these exact type values:

```text
short_answer
long_answer
multiple_choice
checkbox
dropdown
```

Canonical API representation:

```json
{
  "id": "question-1710000000000-abc123",
  "label": "Preferred meal",
  "type": "dropdown",
  "required": true,
  "placeholder": "Select a meal",
  "options": ["Rice", "Pasta", "Salad"],
  "max_selections": null,
  "sort_order": 1
}
```

Rules:

- `label` is required and should be trimmed.
- `short_answer` and `long_answer` use `options: []` and `max_selections: null`.
- `multiple_choice`, `checkbox`, and `dropdown` need at least two non-empty options.
- `multiple_choice` and `dropdown` accept one string answer.
- `checkbox` accepts an array of strings and may have a positive `max_selections` limit.
- Question order must be stored explicitly; never depend on database insertion order.

The current Firebase-shaped frontend objects use camelCase names such as `eventId`, `formId`, `sortOrder`, and `maxSelections`. The custom backend contract should use the project’s normal snake_case convention. During the frontend cutover, either update the adapter or temporarily accept both spellings at the request boundary, while returning one canonical snake_case response.

## Recommended database structure

### `event_registration_forms`

One logical request-info section attached to an event:

```text
id
event_id
name
sort_order
is_active
active_version_id
has_submissions
created_by
created_at
updated_by
updated_at
```

### `event_registration_form_versions`

An immutable snapshot of a form definition:

```text
id
form_id
version_number
name_snapshot
sort_order_snapshot
status                 -- draft | published | archived
created_by
created_at
```

### `event_registration_form_questions`

Questions belong to a version, not directly to the mutable form record:

```text
id
form_version_id
label
question_type
required
placeholder
options_json
max_selections
sort_order
```

### `event_registrations`

One RSVP per user/event:

```text
id
event_id
user_id
rsvp_status
additional_info
submitted_at
updated_at
```

Add a unique constraint on `(event_id, user_id)`. A repeat registration should update the same registration rather than create duplicate rows.

### `event_registration_answers`

One answer per registration/question snapshot:

```text
id
registration_id
event_id
form_id
form_version_id
question_id
question_label_snapshot
question_type_snapshot
question_order_snapshot
required_snapshot
answer_text
answer_json
created_at
updated_at
```

Use `answer_text` for short answer, long answer, multiple choice, and dropdown. Use `answer_json` for checkbox arrays. Keeping the question/form snapshots is mandatory: a later admin edit must not change what an earlier attendee’s answer means.

## Custom backend API

All endpoints should use the normal application headers:

```http
Authorization: Bearer <access_token>
X-API-Key: <application_api_key>
Content-Type: application/json
```

Derive the current user from the bearer token. Do not use a client-provided user ID for authorization decisions. The existing Firebase client sends `X-User-Id`; that header must not be carried forward as a trusted identity mechanism.

### 1. Event list metadata

Existing endpoint:

```http
POST /api/get_events
```

Each event should include:

```json
{
  "has_registration_questions": true,
  "registration_form_count": 2
}
```

Return `false` and `0` when there are no active forms. These values should be derived from active backend forms, not from a tag or text marker.

While this is being implemented, announcements must also derive their event eligibility from the explicit event flag and date. A past event must never appear in announcements, even if an old `show_in_announcements` value or description marker is still present. Editing a past event is allowed; it simply remains ineligible for announcements.

### 2. Get active forms for an event

```http
POST /api/get_event_registration_forms
```

Request:

```json
{
  "event_id": "123"
}
```

Response:

```json
{
  "status": 200,
  "message": "Event registration forms retrieved successfully",
  "event_id": "123",
  "forms": [
    {
      "id": "12",
      "event_id": "123",
      "name": "Food Preferences",
      "sort_order": 1,
      "version": 3,
      "is_active": true,
      "questions": [
        {
          "id": "question-1710000000000-abc123",
          "label": "Preferred meal",
          "type": "dropdown",
          "required": true,
          "placeholder": "Select a meal",
          "options": ["Rice", "Pasta", "Salad"],
          "max_selections": null,
          "sort_order": 1
        }
      ],
      "created_at": "2026-06-18T10:00:00Z",
      "updated_at": "2026-06-18T10:30:00Z"
    }
  ]
}
```

Always return `forms`. Return `forms: []` when the event has no active forms. Sort forms and questions ascending before returning them. Do not return archived forms to the registration modal.

### 3. Create or update one form

Use one management endpoint in the same style as the other custom APIs:

```http
POST /api/manage_event_registration_form
```

Create request example:

```json
{
  "function_type": "upsert",
  "event_id": "123",
  "name": "Food Preferences",
  "sort_order": 1,
  "questions": [
    {
      "id": "question-1710000000000-abc123",
      "label": "Preferred meal",
      "type": "dropdown",
      "required": true,
      "placeholder": "Select a meal",
      "options": ["Rice", "Pasta", "Salad"],
      "max_selections": null,
      "sort_order": 1
    }
  ]
}
```

Update request adds `form_id`:

```json
{
  "function_type": "upsert",
  "event_id": "123",
  "form_id": "12",
  "name": "Food Preferences",
  "sort_order": 1,
  "questions": []
}
```

The endpoint must:

- verify the requester can manage the event
- verify the event exists
- validate the form name and questions
- create a new immutable version when a saved definition changes
- point `active_version_id` to the new version
- retain older versions if submissions exist
- return the resulting form in the same shape as the get endpoint

Do not overwrite a version referenced by a submitted answer.

### 4. Archive a form

The same management endpoint can archive a form:

```json
{
  "function_type": "archive",
  "event_id": "123",
  "form_id": "12"
}
```

Archive by setting `is_active = false`. Keep the form, versions, questions, and answers for historical review. Recalculate the event’s active form count after the operation.

### 5. Reorder forms

```http
POST /api/reorder_event_registration_forms
```

Request:

```json
{
  "event_id": "123",
  "forms": [
    { "form_id": "12", "sort_order": 1 },
    { "form_id": "14", "sort_order": 2 }
  ]
}
```

Verify every submitted form belongs to the event and is active/manageable. Reordering changes display order, not historical answer snapshots.

### 6. Validate registration answers

The frontend currently calls a separate validation function before submitting. Keep a separate endpoint for immediate feedback if useful:

```http
POST /api/validate_event_registration
```

Request:

```json
{
  "event_id": "123",
  "answers": [
    {
      "form_id": "12",
      "form_version": 3,
      "question_id": "question-1710000000000-abc123",
      "value": "Rice"
    }
  ]
}
```

The submit endpoint must run the same validation again; validation must never be trusted just because the browser called this endpoint first.

### 7. Register or update RSVP with structured answers

Extend the existing endpoint:

```http
POST /api/register_event
```

Canonical request:

```json
{
  "event_id": "123",
  "rsvp_status": "going",
  "additional_info": "I will arrive with one guest.",
  "answers": [
    {
      "form_id": "12",
      "form_version": 3,
      "question_id": "question-1710000000000-abc123",
      "value": "Rice"
    },
    {
      "form_id": "14",
      "form_version": 1,
      "question_id": "question-1710000000001-def456",
      "value": ["Medium", "Blue"]
    }
  ]
}
```

For compatibility with the existing RSVP payload, the backend may temporarily accept `status` as an alias for `rsvp_status` and `user_id` as an ignored/validated legacy field. The authenticated user must remain authoritative.

The operation must be one database transaction:

1. Load the event and active form versions.
2. Confirm the event is registrable under the existing event policy.
3. Validate every answer against the matching event form/question.
4. Insert or update the unique `(event_id, user_id)` registration.
5. Replace/update that registration’s current answers atomically.
6. Save the free-text `additional_info`.
7. Commit and return the saved registration.

If any answer is invalid, the RSVP must not be saved. This removes the current failure mode where the custom RSVP succeeds and the Firebase survey submission fails afterward.

### Validation requirements

- `event_id` must refer to an existing event.
- `rsvp_status` must be `going`, `maybe`, or `not_going`.
- `additional_info` is optional, trimmed, and bounded by a backend length limit.
- Every submitted `form_id` must belong to the event and be active at the time of submission.
- Every `question_id` must belong to the submitted form/version.
- Required string answers must be non-empty after trimming.
- `multiple_choice` and `dropdown` values must be one of the stored options.
- Checkbox values must be an array, contain only stored options, contain no duplicates, and respect `max_selections`.
- Reject unknown fields/questions rather than silently storing answers that cannot be reviewed later.

### Additional-info compatibility

The current frontend serializes structured answers into the existing `additional_info` text field for compatibility, then also sends the structured answers to Firebase. During migration, the backend may receive that serialized text. It should store the structured `answers` as the canonical data and treat `additional_info` as the user’s free-text note.

After the frontend switches to this endpoint, remove the answer-to-text serialization bridge. Do not require the backend to parse question labels from `additional_info`.

### Response example

```json
{
  "status": 200,
  "message": "Event registration saved successfully",
  "registration": {
    "id": "reg-9001",
    "event_id": "123",
    "user_id": "39",
    "rsvp_status": "going",
    "additional_info": "I will arrive with one guest.",
    "form_versions": [
      {
        "form_id": "12",
        "form_version_id": "form-version-1203",
        "form_version_number": 3,
        "form_name": "Food Preferences"
      }
    ],
    "answers": [
      {
        "form_id": "12",
        "form_version_id": "form-version-1203",
        "question_id": "question-1710000000000-abc123",
        "question_label": "Preferred meal",
        "question_type": "dropdown",
        "order": 1,
        "required": true,
        "value": "Rice"
      }
    ],
    "submitted_at": "2026-06-18T11:00:00Z",
    "updated_at": "2026-06-18T11:00:00Z"
  }
}
```

### 8. List submissions for attendees

Replace Firebase `getEventSurveySubmissions` with:

```http
POST /api/get_event_registration_submissions
```

Request:

```json
{
  "event_id": "123"
}
```

Response:

```json
{
  "status": 200,
  "event_id": "123",
  "submissions": [
    {
      "user_id": "39",
      "user_name": "Ada Example",
      "user_email": "ada@example.com",
      "rsvp_status": "going",
      "submitted_at": "2026-06-18T11:00:00Z",
      "updated_at": "2026-06-18T11:00:00Z",
      "has_survey_response": true
    }
  ]
}
```

This is an admin-only endpoint. It is used to match the normal attendee list to records containing structured answers. It should return a lightweight list; do not include all answers here.

### 9. Get one submission detail

Replace Firebase `getEventSurveySubmissionDetail` with:

```http
POST /api/get_event_registration_submission_detail
```

Request:

```json
{
  "event_id": "123",
  "user_id": "39"
}
```

Response:

```json
{
  "status": 200,
  "event_id": "123",
  "registration": {
    "id": "reg-9001",
    "event_id": "123",
    "event_title_snapshot": "Annual Alumni Reunion 2026",
    "user_id": "39",
    "user_name": "Ada Example",
    "user_email": "ada@example.com",
    "rsvp_status": "going",
    "additional_info": "I will arrive with one guest.",
    "submitted_at": "2026-06-18T11:00:00Z",
    "updated_at": "2026-06-18T11:00:00Z"
  },
  "forms": [
    {
      "form_id": "12",
      "form_name": "Food Preferences",
      "form_version_id": "form-version-1203",
      "form_version_number": 3,
      "questions": [
        {
          "id": "question-1710000000000-abc123",
          "label": "Preferred meal",
          "type": "dropdown",
          "required": true,
          "placeholder": "Select a meal",
          "options": ["Rice", "Pasta", "Salad"],
          "max_selections": null,
          "order": 1,
          "value": "Rice"
        }
      ]
    }
  ]
}
```

The `forms` response must be built from the version referenced by the saved answers, not from the event’s current active form. That is what keeps old submissions understandable after an admin edits the form.

The attendee page uses this same detail response to show requested information and build dynamic CSV columns such as `Food Preferences: Preferred meal` plus `Additional Info`.

## Create/update event integration options

### Short-term compatibility path

Implement the form endpoints above first. The existing frontend create/edit flow can then replace each Firebase call with the custom API while keeping the event create/update request separate.

### Preferred final path

Allow `create_event` and `manage_event` to accept a `registration_forms` collection and save the event plus initial form versions in one transaction. This removes partial-save states when the event succeeds but form creation fails.

Example nested payload for a JSON event request:

```json
{
  "title": "Annual Alumni Reunion 2026",
  "start_date": "2026-06-20",
  "location": "Lagos",
  "registration_forms": [
    {
      "name": "Food Preferences",
      "sort_order": 1,
      "questions": [
        {
          "label": "Preferred meal",
          "type": "dropdown",
          "required": true,
          "placeholder": "Select a meal",
          "options": ["Rice", "Pasta"],
          "max_selections": null,
          "sort_order": 1
        }
      ]
    }
  ]
}
```

If the event includes an uploaded banner and therefore uses `multipart/form-data`, accept `registration_forms` as a JSON string or keep the short-term separate form calls until the backend has a consistent multipart parser.

## Permissions, consistency, and reliability

- Form CRUD, submission list, and submission detail are admin/event-manager operations according to the existing event permission policy.
- Public event users may read active forms only for an event they can view.
- Enforce event visibility and membership on every read endpoint.
- Use a unique `(event_id, user_id)` constraint and an idempotent upsert for registrations.
- Return stable IDs and ISO-8601 timestamps.
- Make form upsert/reorder/archive operations transactional.
- Recalculate `has_registration_questions` and `registration_form_count` from active forms after every form mutation.
- Add pagination to submission lists if the attendee list can become large, while preserving the current simple response for small lists during rollout.
- Never expose draft or archived forms to normal registration users.

## Firebase export migration and frontend cutover

1. Treat the FastAPI/MariaDB implementation as the target live system; do not create or extend Firebase survey features.
2. Obtain an approved sanitized, bounded, read-only Firebase export; never use production Firebase credentials or raw production data for development tests.
3. Validate the export with the repository's read-only validator and provide explicit source-to-target event/user ID maps. Do not infer IDs.
4. Rehearse import and rollback against a disposable MariaDB schema, preserving form/version/question provenance where possible and recording unmapped data.
5. Update frontend event hooks/services to use `apiClient` and the custom endpoints, with `register_event_with_forms` as the single atomic RSVP-and-answer write.
6. Send `form_version` with each answer from the form version already present in the frontend form response.
7. Remove Firebase function calls and the local-storage availability bridge only after reads, writes, admin review, export, browser behavior, and rollback have been verified against the custom backend.
8. Stop serializing structured answers into `additional_info`; keep that field for the optional note only.
9. Remove Firebase survey configuration and Functions only after the agreed rollback window and final reconciliation.

## Acceptance checklist

- [ ] An event with no forms returns `has_registration_questions: false` and `registration_form_count: 0`.
- [ ] An event with multiple forms returns them in the saved order.
- [ ] Questions render in saved order and support all five types.
- [ ] Choice answers are validated against stored options.
- [ ] Checkbox answers respect membership, duplicates, and max selection rules.
- [ ] A registration and its structured answers commit or fail together.
- [ ] Re-registering the same user/event does not create a duplicate registration.
- [ ] Editing a form creates a new version when necessary.
- [ ] Historical submissions still show the old question labels/options after a form edit.
- [ ] Archiving a form removes it from new registration but preserves old answers.
- [ ] Admin attendee list/detail and CSV export work without Firebase.
- [ ] Editing a past event is allowed, while past events remain excluded from announcements.
- [ ] No event registration form or answer data is read from Firebase after cutover.
