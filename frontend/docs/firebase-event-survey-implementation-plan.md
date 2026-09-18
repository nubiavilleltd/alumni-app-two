# Firebase Event Survey Implementation Plan (Historical Fallback — Do Not Extend)

## Purpose

This document is retained only to explain the existing temporary Firebase survey implementation and to support a controlled historical-data export. It is **not** the current implementation plan.

The current decision is to move event registration forms, form versions, RSVP submissions, and answers to FastAPI plus MariaDB as the canonical live system. Do not create new Firebase survey features, write new form/answer records to Firestore, or treat Firebase credentials as a prerequisite for FastAPI implementation. Firebase may be used later only for an approved sanitized, read-only historical export, explicit ID mapping, disposable-database import rehearsal, and final reconciliation. The current authoritative delivery plan is [the migration breakdown](../../PYTHON_FASTAPI_MIGRATION_TASK_BREAKDOWN.md) and the frontend cutover contract is [the event-registration handoff](backend-event-registration-handoff.md).

This plan assumes:

- the main app auth stays exactly as it is today
- Firebase is used only for event survey forms and survey responses
- Firestore is **not** exposed directly to the browser
- the browser talks to Cloud Functions
- Cloud Functions verify the existing app bearer token, then read/write Firestore with the Admin SDK

This is designed to be safe enough for a temporary solution and stable enough if it becomes long-term.

## Current Frontend Source Of Truth

The current survey prototype behavior already exists in:

- `src/features/events/pages/CreateEventPage.tsx`
- `src/features/events/components/EventRegistrationFormBuilderModal.tsx`
- `src/features/events/components/RegisterEventModal.tsx`
- `src/features/events/lib/eventRegistrationFormStorage.ts`
- `src/features/events/types/eventRegistrationForm.types.ts`

## Recommended Architecture

### Keep these as they are

- existing login flow
- existing app backend
- existing bearer token handling in the frontend
- existing event APIs

### Add these

1. Firebase project
2. Firestore database
3. Cloud Functions for survey read/write operations
4. Firebase App Check in the frontend
5. server-side verification of the existing bearer token inside Cloud Functions

## Why this is the safest version

If the browser writes directly to Firestore, Firestore has no built-in understanding of the app's current login system.

That means direct browser writes would force us to either:

- trust client-provided `userId`, `role`, or `eventId`, which is risky
- or migrate auth logic into Firebase Auth, which is more work and changes the system shape

Using Cloud Functions avoids that. The browser remains untrusted. Firestore stays private.

## Security Model

### Browser can do

- fetch forms for an event
- submit its own answers
- call admin form-management endpoints if the verified user is an admin

### Browser cannot do

- read Firestore directly
- write Firestore directly
- choose its own trusted `userId`
- choose its own trusted `role`

### Cloud Functions must do

- verify Firebase App Check token
- verify the app's bearer token
- derive the real `userId` and `role`
- validate form definitions
- validate survey answers
- write Firestore using the Admin SDK

## Firestore Structure

Use nested collections under the event so all survey data stays grouped by event.

### 1. `events/{eventId}/surveyForms/{formId}`

Represents one logical form attached to an event.

Suggested fields:

- `eventId`
- `name`
- `sortOrder`
- `isActive`
- `activeVersionId`
- `activeVersionNumber`
- `activeSnapshot`
- `hasSubmissions`
- `createdBy`
- `createdAt`
- `updatedAt`
- `updatedBy`

Example:

```json
{
  "eventId": "123",
  "name": "Food Preferences",
  "sortOrder": 1,
  "isActive": true,
  "activeVersionId": "v3",
  "activeVersionNumber": 3,
  "activeSnapshot": {
    "name": "Food Preferences",
    "questions": [
      {
        "id": "q1",
        "label": "Preferred meal",
        "type": "dropdown",
        "required": true,
        "placeholder": "Select a meal",
        "options": ["Rice", "Pasta", "Salad"],
        "order": 1
      }
    ]
  },
  "hasSubmissions": true,
  "createdBy": "39",
  "createdAt": "serverTimestamp",
  "updatedAt": "serverTimestamp",
  "updatedBy": "39"
}
```

### 2. `events/{eventId}/surveyForms/{formId}/versions/{versionId}`

Represents an immutable version of a form.

Suggested fields:

- `versionNumber`
- `name`
- `sortOrder`
- `questions`
- `createdBy`
- `createdAt`
- `status`

Example:

```json
{
  "versionNumber": 3,
  "name": "Food Preferences",
  "sortOrder": 1,
  "questions": [
    {
      "id": "q1",
      "label": "Preferred meal",
      "type": "dropdown",
      "required": true,
      "placeholder": "Select a meal",
      "options": ["Rice", "Pasta", "Salad"],
      "order": 1
    }
  ],
  "createdBy": "39",
  "createdAt": "serverTimestamp",
  "status": "published"
}
```

### 3. `events/{eventId}/surveyRegistrations/{userId}`

Represents one user's survey submission for one event.

Use `userId` as the document ID so uniqueness is automatic per event.

Suggested fields:

- `eventId`
- `userId`
- `userName`
- `userEmail`
- `rsvpStatus`
- `additionalInfo`
- `submittedAt`
- `updatedAt`
- `formVersions`
- `answers`

Example:

```json
{
  "eventId": "123",
  "userId": "55",
  "userName": "Jane Doe",
  "userEmail": "jane@example.com",
  "rsvpStatus": "going",
  "additionalInfo": "I may arrive late.",
  "submittedAt": "serverTimestamp",
  "updatedAt": "serverTimestamp",
  "formVersions": [
    {
      "formId": "formA",
      "formVersionId": "v3",
      "formVersionNumber": 3,
      "formName": "Food Preferences"
    }
  ],
  "answers": [
    {
      "formId": "formA",
      "formVersionId": "v3",
      "questionId": "q1",
      "questionLabel": "Preferred meal",
      "questionType": "dropdown",
      "order": 1,
      "required": true,
      "value": "Rice"
    }
  ]
}
```

## Why this data model is good

- active forms are fast to read because `activeSnapshot` is ready for the frontend
- form version history is immutable
- historical answers remain stable after form edits
- admin review is easy because answers already contain snapshots
- event survey data is isolated from the rest of the app

## Firestore Rules

Make Firestore private. Cloud Functions should be the only access path.

```txt
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

This is the safest rule set for this architecture.

## Cloud Functions To Build

Use HTTPS functions, not direct Firestore access from the frontend.

### Shared helper requirements for every function

Every function should:

1. verify Firebase App Check
2. verify the existing app bearer token
3. fetch the real user profile
4. derive `userId` and `role` from trusted server-side data

### Function 1. `getEventSurveyForms`

Purpose:

- fetch all active forms for an event

Who can call it:

- any authenticated user

Request:

```json
{
  "eventId": "123"
}
```

Response:

```json
{
  "eventId": "123",
  "forms": [
    {
      "id": "formA",
      "name": "Food Preferences",
      "sortOrder": 1,
      "version": 3,
      "questions": [
        {
          "id": "q1",
          "label": "Preferred meal",
          "type": "dropdown",
          "required": true,
          "placeholder": "Select a meal",
          "options": ["Rice", "Pasta", "Salad"],
          "order": 1
        }
      ]
    }
  ]
}
```

### Function 2. `upsertEventSurveyForm`

Purpose:

- create a new form
- update an existing form
- always publish a new version

Who can call it:

- admin only

Request:

```json
{
  "eventId": "123",
  "formId": "formA",
  "name": "Food Preferences",
  "sortOrder": 1,
  "questions": [
    {
      "id": "q1",
      "label": "Preferred meal",
      "type": "dropdown",
      "required": true,
      "placeholder": "Select a meal",
      "options": ["Rice", "Pasta", "Salad"],
      "order": 1
    }
  ]
}
```

Required behavior:

- validate the form
- if `formId` does not exist, create it
- if `formId` exists, create a new version
- update the parent form document's `activeVersionId`, `activeVersionNumber`, and `activeSnapshot`

### Function 3. `archiveEventSurveyForm`

Purpose:

- deactivate a form

Who can call it:

- admin only

Request:

```json
{
  "eventId": "123",
  "formId": "formA"
}
```

Required behavior:

- soft archive only
- set `isActive` to `false`
- do not hard-delete old versions

### Function 4. `reorderEventSurveyForms`

Purpose:

- update form ordering between forms

Who can call it:

- admin only

Request:

```json
{
  "eventId": "123",
  "forms": [
    { "formId": "formA", "sortOrder": 1 },
    { "formId": "formB", "sortOrder": 2 }
  ]
}
```

Required behavior:

- update only `sortOrder`
- keep versions unchanged

### Function 5. `submitEventSurveyRegistration`

Purpose:

- save one user's answers for one event

Who can call it:

- authenticated user

Request:

```json
{
  "eventId": "123",
  "rsvpStatus": "going",
  "additionalInfo": "I may arrive late.",
  "answers": [
    {
      "formId": "formA",
      "questionId": "q1",
      "value": "Rice"
    }
  ]
}
```

Required behavior:

- load current active forms for the event
- validate answers against the active forms
- capture form version info and question snapshots
- write one registration document at `events/{eventId}/surveyRegistrations/{userId}`
- use a transaction

### Function 6. `getEventSurveySubmissions`

Purpose:

- fetch survey submission list for admins

Who can call it:

- admin only

Request:

```json
{
  "eventId": "123"
}
```

Response should include:

- `userId`
- `userName`
- `userEmail`
- `rsvpStatus`
- `submittedAt`
- `updatedAt`

### Function 7. `getEventSurveySubmissionDetail`

Purpose:

- fetch one user's full survey submission for admin review

Who can call it:

- admin only

Request:

```json
{
  "eventId": "123",
  "userId": "55"
}
```

Response should include:

- registration summary
- form version metadata
- answers in saved order

## Shared Validation Rules

### Form validation

- form name is required
- form must contain at least one question
- question label is required
- question `type` must be one of:
  - `short_answer`
  - `long_answer`
  - `multiple_choice`
  - `checkbox`
  - `dropdown`
- choice-based types must have at least 2 options
- `order` must be explicit

### Answer validation

- required questions must have a value
- `multiple_choice` must match one allowed option
- `dropdown` must match one allowed option
- `checkbox` must contain only allowed options
- `short_answer` and `long_answer` can be strings

## Function Helper Modules To Create

These helpers will keep the Cloud Functions code clean.

### `verifyAppCheck`

Purpose:

- verify App Check token on every request

### `verifyExistingBearerToken`

Purpose:

- verify the app's current bearer token

Recommended behavior:

- read the `Authorization: Bearer ...` header
- call the existing backend user/profile endpoint or a dedicated token-verification endpoint
- reject if invalid
- return trusted user data

### `requireAdmin`

Purpose:

- reject non-admin users for admin-only functions

### `validateSurveyForm`

Purpose:

- validate form structure before save

### `validateSurveyAnswers`

Purpose:

- validate user answers against active forms

### `buildAnswerSnapshots`

Purpose:

- convert raw answers into stored answer rows with:
  - `formVersionId`
  - `questionLabel`
  - `questionType`
  - `order`
  - `required`

### `writeSurveyRegistrationTransaction`

Purpose:

- transactionally save or update a user's submission

## Suggested Cloud Functions Folder Shape

```txt
functions/
  src/
    index.ts
    config/
      firebase.ts
    middleware/
      verifyAppCheck.ts
      verifyExistingBearerToken.ts
      requireAdmin.ts
    survey/
      getEventSurveyForms.ts
      upsertEventSurveyForm.ts
      archiveEventSurveyForm.ts
      reorderEventSurveyForms.ts
      submitEventSurveyRegistration.ts
      getEventSurveySubmissions.ts
      getEventSurveySubmissionDetail.ts
    survey/
      validators/
        validateSurveyForm.ts
        validateSurveyAnswers.ts
      utils/
        buildAnswerSnapshots.ts
        writeSurveyRegistrationTransaction.ts
```

## Frontend Changes Needed

The frontend should stop reading and writing survey data from `localStorage`.

### Replace these responsibilities

Current local storage behavior in:

- `src/features/events/lib/eventRegistrationFormStorage.ts`

Should be replaced with API calls to the new Cloud Functions.

### Frontend integration targets

1. `CreateEventPage.tsx`
   - load saved forms from Firebase
   - save create/update form actions to Firebase

2. `EventRegistrationFormBuilderModal.tsx`
   - send create/update requests to Firebase functions

3. `RegisterEventModal.tsx`
   - fetch forms from Firebase
   - submit answers to Firebase

4. `eventRegistrationForm.types.ts`
   - keep types aligned with Firebase payloads

## Step-By-Step Implementation Order

This is the order to start in.

### Step 1. Set up Firebase

- create Firebase project
- enable Firestore
- enable Cloud Functions
- enable App Check for web

### Step 2. Lock Firestore down

- deploy deny-all Firestore rules
- confirm browser cannot read/write Firestore directly

### Step 3. Create Cloud Functions project

- initialize Firebase Functions
- add Admin SDK access
- add TypeScript if not already using it

### Step 4. Implement shared middleware

Build these first:

- `verifyAppCheck`
- `verifyExistingBearerToken`
- `requireAdmin`

Do not build survey functions before these are working.

### Step 5. Implement form read endpoint

Build `getEventSurveyForms` first because the frontend needs it to stop relying on local storage.

### Step 6. Implement admin form management endpoints

Build:

- `upsertEventSurveyForm`
- `archiveEventSurveyForm`
- `reorderEventSurveyForms`

### Step 7. Implement user submission endpoint

Build:

- `submitEventSurveyRegistration`

Use Firestore transactions here.

### Step 8. Implement admin reporting endpoints

Build:

- `getEventSurveySubmissions`
- `getEventSurveySubmissionDetail`

### Step 9. Switch frontend off local storage

- replace form definition storage
- replace user answer storage
- keep current UI behavior the same

### Step 10. Remove old local storage fallback

Once Firebase flow is stable:

- remove local storage persistence
- remove temporary text serialization into `additional_info` if no longer needed

## Immediate Build Checklist

This is the fastest safe starting sequence for right now.

### Today

1. create Firebase project
2. enable Firestore
3. initialize Cloud Functions
4. deploy deny-all Firestore rules
5. scaffold middleware helpers

### Next

1. build `getEventSurveyForms`
2. build `upsertEventSurveyForm`
3. connect `CreateEventPage.tsx` and the form builder

### After that

1. build `submitEventSurveyRegistration`
2. connect `RegisterEventModal.tsx`
3. add admin submission review endpoints

## Risks To Avoid

Do not do these:

- do not let the browser read Firestore directly
- do not let the browser write Firestore directly
- do not trust client-provided `userId`
- do not trust client-provided `role`
- do not hard-delete forms or versions after submissions exist
- do not mutate historical versions in place
- do not use permissive Firestore rules

## Long-Term Recommendation

Even if this starts as a fallback, build it like it may stay.

That means:

- Cloud Functions only
- private Firestore
- explicit versioning
- transactional writes
- strict validation
- admin-only management paths

If this is implemented in that shape, it can remain in production safely for much longer than a quick temporary hack.
