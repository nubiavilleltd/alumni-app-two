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

`app/models/generated.py` contains 68 SQLAlchemy table mappings and the enum types reflected from the sanitized clone. Integration tests compare every mapped table and column to `information_schema`, including:

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

Revision `7250164972b6` is an intentionally empty baseline for the already-existing schema. The disposable database was stamped, not upgraded. `alembic check` then reported `No new upgrade operations detected.` The Alembic environment has no configured URL fallback and refuses production migrations unless both `ALUMNI_ENVIRONMENT=production` and the separate `ALUMNI_MIGRATION_ALLOW_PRODUCTION=true` gate are deliberately supplied.

## Remaining production evidence

Before any cutover or live schema change, perform read-only introspection against the approved current database and compare this report field by field. Record server version, schema differences, row-quality findings, permissions, backup/restore proof, and any proposed index separately.

