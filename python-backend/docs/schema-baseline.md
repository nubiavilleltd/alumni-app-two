# Sanitized schema baseline

Verified: 2026-09-08 (Africa/Lagos)

## Evidence boundary

This baseline was produced from `Backend/alumni_portal_v2.sql` and imported into a disposable, localhost-only MariaDB instance. All `INSERT` statements were removed before import. It proves compatibility with the supplied export; it does not prove the current office or production database version, credentials, schema drift, data quality, or runtime permissions.

## Verified local target

| Property | Result |
| --- | --- |
| Server | MariaDB 12.3.3 |
| Bind | `127.0.0.1:3307` only |
| Test database | `alumni_portal_test` |
| Imported application tables | 68 |
| Imported application columns | 778 |
| Imported row count | 0 in every application table |
| Storage engines | InnoDB only |
| Indexes, including primary indexes | 203 |
| Foreign-key constraints | 55 |
| Triggers | 0 |
| Stored routines | 0 |
| Scheduled events | 0 |
| Table collations | 63 `utf8mb4_unicode_ci`, 3 `utf8mb3_general_ci`, 2 `utf8mb4_general_ci` |
| Server timezone | `SYSTEM` / `Africa/Lagos` |
| SQL mode | `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` |

## Mapping verification

The supplied sanitized clone has 69 reflected application tables. After applying the additive compatibility migrations (`9d9d1f2a7c31`, `0e4c31d8f2a7`, `b2c3d4e5f6a7`, `c4d5e6f7a8b9`, `d5e6f7a8b9c0`), `app/models/generated.py` contains 70 mappings: the reflected 69 plus `event_registration_form_versions`. The migrations also add form/question source identifiers, checkbox selection limits, answer display snapshots, chat-attachment ownership/expiry, and the bounded-inbox and staged-purge indexes. Integration tests compare every mapped table and column to `information_schema`, including:

- table and column names;
- column SQL types and lengths;
- nullability;
- primary keys;
- server defaults and computed expressions;
- column comments;
- foreign-key column/target mappings; and
- secondary index columns and uniqueness.

The test suite also checks that every application table is empty and that a representative write to `setup_parameters` can be rolled back without leaving a row.

## Alembic baseline

Revision `7250164972b6` is an intentionally empty baseline for the already-existing schema. Revision `9d9d1f2a7c31` is the additive event-registration compatibility migration described above. Revisions `0e4c31d8f2a7` (private chat-attachment ownership and staged-upload expiry), `c4d5e6f7a8b9` (bounded chat inbox index `idx_thread_participants_inbox` on `member_id`, `left_at`, `is_pinned`), and `d5e6f7a8b9c0` (staged-attachment purge index `idx_msg_attachment_staged_purge` on `message_id`, `expires_at` for the scheduled reaper sweep) extend the same chain for Goal 6; `b2c3d4e5f6a7` adds the `processing` order status for Goal 8 and precedes `c4d5e6f7a8b9`. For a freshly imported sanitized schema, run `alembic upgrade head` rather than stamping head, then require `alembic check` to report `No new upgrade operations detected.` The Alembic environment has no configured URL fallback and refuses production migrations unless both `ALUMNI_ENVIRONMENT=production` and the separate `ALUMNI_MIGRATION_ALLOW_PRODUCTION=true` gate are deliberately supplied.

## Live schema drift evidence (2026-09-18)

A supplied live dump (`alumni_portal_v2.sql`, phpMyAdmin, server `10.5.26-MariaDB`,
database `alumni_portal_v2`) was sanitized to schema-only (`scripts/sanitize_sql_dump.py`,
zero rows) and compared against the models with `scripts/schema_drift_report.py`.
Findings, against the 69-table live schema:

- **One genuinely new live table:** `news_feeds_setup` (`id`, `feed_name`, `feed_url`,
  `sort_order`, `active`, `created_at`, `updated_at`; unique `uq_news_feed_name`, index
  `idx_news_feed_active`). It is the live RSS/Atom feed configuration (`feed_name` is
  "Shown as articles[].source") and has 33 live rows. It was unmapped and is now added
  to `app/models/generated.py`; the `news/feeds` service still hardcodes feeds, so
  wiring it to this table is a Goal 7 follow-up.
- **No other structural drift.** Every other live table, column name, nullability,
  default, index, and foreign key matches the models. The only remaining differences
  are our own additive migrations (expected to apply at cutover): the
  `event_registration_form_versions` table; `event_registration_forms.source_form_id`,
  `event_registration_form_questions.max_selections`/`source_question_id`,
  `event_registration_answers.*_snapshot`; `messages_attachments.uploaded_by_member_id`/
  `expires_at`; `orders.status` gaining `processing` (`b2c3d4e5f6a7`); and the four
  additive indexes.

`python -m alembic upgrade head` applied cleanly to the live schema-only clone and
`alembic check` reported "No new upgrade operations detected." The complete suite is
green against this refreshed baseline (481 tests, 90.23% coverage). The development
baseline `.local-state/sanitized-schema.sql` was regenerated from the live dump
(schema-only, no production rows).

Live schema characteristics (from the schema-only import): 69 tables, 785 columns,
InnoDB only, 3 table collations, 221 indexes (including primary), 55 foreign keys,
0 triggers, 0 stored routines, 0 scheduled events.

