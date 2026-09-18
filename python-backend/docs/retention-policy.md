# Data retention and deletion policy

Status: standard-practice baseline (Goal 10). Verified 2026-09-18.

This documents the default retention behaviour implemented by the FastAPI service. It
is a baseline, not a replacement for a legal/privacy review against the deployment's
jurisdiction.

## Authentication material

| Item | Retention |
| --- | --- |
| Access tokens (JWT) | 15 minutes; stateless, not stored server-side |
| Refresh tokens | 30 days; rotated on use, revoked on logout/reuse/role-change/deactivation |
| Password-reset tokens | 30 minutes, hashed, one-time |
| Email-verification codes | 24 hours, one-time, replaced on resend |
| Access-code check | constant-time, no new storage |

## Uploads and staging

| Item | Retention |
| --- | --- |
| Staged chat attachments | 24 hours; expired rows/files removed by the bounded reaper |
| Profile/event/product media | Retained while referenced; replaced files are deleted on mutation |

## Notifications and push

| Item | Retention |
| --- | --- |
| In-app notifications | Per-user rows retained; marked read in place (no auto-delete) |
| Push subscriptions | Removed when the provider is unlinked or the account is deactivated |

## Members and audit

| Item | Retention |
| --- | --- |
| Member accounts | Deactivation changes state, never deletes the row; a full delete requires a separately reviewed data-deletion process |
| Administrative audit log | Append-only, retained indefinitely (immutable history) |

## Logs and metrics

| Item | Retention |
| --- | --- |
| Structured application logs | Redacted of secrets/PII; log rotation and retention are set at deployment |
| Metrics | Aggregated counters/histograms only; no PII, no raw payloads |

## Deletion

- Account deactivation revokes all refresh tokens transactionally.
- Unlinking the last social provider is refused when no password is set (prevents lockout, not a deletion).
- There is no self-service account deletion; a deletion runbook must be approved before any production data is removed.

## Open decisions

- Log retention window (deployment-managed).
- Whether to auto-delete old read notifications or audit rows after a fixed period.
- A reviewed self-service data-deletion flow (GDPR/NDPR right-to-erasure) if required.
