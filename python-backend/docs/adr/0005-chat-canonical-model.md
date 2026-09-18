# ADR 0005: Canonical Chat Model

## Status

Accepted for FastAPI implementation on 2026-09-17. Legacy-data reconciliation remains pending.

## Context

The reviewed schema contains two independent chat families:

- Legacy: `chat_groups`, `chat_group_members`, `chat_messages`, and `direct_messages`.
- V2: `message_threads`, `thread_participants`, `messages`, and `messages_attachments`.

The active frontend source and compiled bundle call only the V2 `POST /chat_api/v2_*`
paths through `backendMessagesTransport`; no active source or bundle caller was found for
the legacy chat paths. The V2 tables provide the required direct-message key, participant
membership state, read/delivery cursors, idempotency key, replies, staged attachments, and
soft-deletion fields. The disposable sanitized schema has no chat rows, so it cannot prove
which legacy/V2 records coexist in a live environment.

The PHP V2 handlers are reachability evidence, not a safe authorization model. They allow
unrestricted group-member addition, do not consistently bind replies or staged attachments
to the actor and thread, and expose a bulk graduation-year synchronization operation without
an administrator check. FastAPI must not copy those behaviors.

## Decision

FastAPI chat will use V2 tables only. It will preserve the active frontend's existing
`POST /chat_api/v2_*` route names and response adaptation surface while deriving the viewer
only from the current Bearer principal; client `viewerMemberId` stays compatibility noise.
The legacy tables are neither read nor written by FastAPI unless a later approved sanitized
reconciliation proves their live data must be retained.

Each V2 operation will reload active account facts and enforce the following rules:

- An active participant is required before listing, reading, sending, marking delivery/read,
  pinning, leaving, or staging an attachment for a thread. Leaving soft-preserves rows and
  history but ends future access and writes.
- Direct threads use the existing sorted `direct_key` and unique index. Concurrent first
  sends must resolve the same thread and client-generated message IDs must be idempotent.
- A group creator is the initial administrator. Only an active group administrator may add
  an active target member; ordinary members cannot add arbitrary people.
- A reply target must be a non-deleted message in the same thread. A staged attachment must
  belong to the current actor and thread, have no prior message link, and be linked in the
  same transaction as the message.
- Only the sender may soft-delete a message. The deletion clears the message body while
  retaining the row and must not delete shared attachment bytes until storage retention is
  approved.
- Graduation-year synchronization is not an unauthenticated or ordinary-member endpoint.
  The bulk operation remains unavailable until a dedicated current-policy administrator or
  internal-job authorization decision is implemented.

File bytes and provider delivery are out of this first chat slice. Attachment metadata may
be modeled only with the existing reviewed V2 tables; accepting bytes requires the separate
Goal 9 storage/provider policy and a staged-upload ownership proof.

The active frontend does call `v2_upload_attachment`: it sends multipart `file` bytes and
then renders the returned attachment URL directly in image/audio/file UI. Its client-side
allowlist includes image, audio, PDF, Office, and text types, but that is not a server-side
content validation policy. FastAPI must not put chat bytes under an anonymous `StaticFiles`
mount merely to satisfy that URL shape: an unguessable/generated path is not membership
authorization. Before the route is enabled, the frontend and Goal 9 storage design need an
authenticated browser-safe retrieval mechanism (or a separately approved short-lived,
revocable capability design), bounded type/signature/size handling, malware/provider
decision, staged-upload expiry cleanup, and a test that another account cannot retrieve or
link the object.

## Consequences

The first implementation slice is the active frontend's V2 inbox, detail, direct/group send,
message delete, and read paths, with repository/service/API boundaries and disposable-MariaDB
authorization, cross-thread, duplicate-send, and rollback tests. Attachment, delivery, group
membership, pin, leave, and graduation-year operations remain separate bounded slices.

No legacy schema is dropped, copied, or converted by this decision. Before cutover, an
approved sanitized export must establish whether legacy records need a reversible migration,
which records are authoritative, and how duplicate conversations are handled.
