# Chat attachment reaper runbook

The V2 chat feature stages an owned private file for up to 24 hours before a message
links it. Expired, unsent staged uploads are unreadable through the authenticated
download route, but their `messages_attachments` rows and private bytes must still be
reclaimed. The reaper is an internal, bounded, idempotent operation — it is not an
HTTP route.

## Entry points

- Library: `app.tasks.chat_attachment_reaper.reap_expired_chat_attachments(settings, limit=1..500)`.
- Operator CLI: `scripts/reap_chat_attachments.py` (prints aggregate JSON only).

Both delete the authoritative database rows inside one transaction under `FOR UPDATE`,
then remove the private files after commit. A concurrent send and the sweep cannot
delete a just-linked upload. Output never contains storage paths, file names, or
uploader IDs.

## Access path

Migration `d5e6f7a8b9c0` adds `idx_msg_attachment_staged_purge (message_id, expires_at)`.
The owner-scoped `idx_msg_attachment_staged_expiry` starts with `uploaded_by_member_id`,
so it cannot serve a global sweep. `scripts/chat_retrieval_probe.py` measures the sweep:
pre-index `type=ALL; Using filesort`, post-index `type=range key=idx_msg_attachment_staged_purge`.

## Scheduling

Run from `python-backend/` with the project virtual environment. Prefer a small batch
(e.g. every 15 minutes, `limit 200`); the operation drains its backlog batch by batch.

systemd timer (Linux):

```ini
# /etc/systemd/system/chat-reaper.service
[Service]
Type=oneshot
WorkingDirectory=/srv/alumni/python-backend
Environment=PIP_REQUIRE_VIRTUALENV=true
EnvironmentFile=/etc/alumni/backend.env
ExecStart=/srv/alumni/python-backend/.venv/bin/python -m scripts.reap_chat_attachments \
  --database-url ${ALUMNI_DATABASE_URL} --limit 200 --upload-root ${ALUMNI_UPLOAD_ROOT} \
  --allow-non-localhost
```

```ini
# /etc/systemd/system/chat-reaper.timer
[Timer]
OnCalendar=*:0/15
Persistent=true
[Install]
WantedBy=timers.target
```

cron (Linux):

```cron
*/15 * * * * cd /srv/alumni/python-backend && PIP_REQUIRE_VIRTUALENV=true .venv/bin/python -m scripts.reap_chat_attachments --database-url "$ALUMNI_DATABASE_URL" --limit 200 --upload-root "$ALUMNI_UPLOAD_ROOT" --allow-non-localhost
```

Windows Task Scheduler: create a task that runs
`.\.venv\Scripts\python.exe -m scripts.reap_chat_attachments --database-url <url> --limit 200 --allow-non-localhost`
from the `python-backend` directory every 15 minutes.

The CLI refuses a non-localhost target unless `--allow-non-localhost` is supplied, so an
accidental manual run cannot reach a shared office database. The deploy host is the
only place that flag should be used, under Goal 12.

## Monitoring and failure modes

- A non-zero `file_failures` means a stored path was malformed; investigate the row
  source rather than retrying blindly. Rows are still removed (expired = unusable).
- A crash after the row commit but before file removal can leave one orphaned private
  file. This is safe (never retrievable) and can be collected by a storage-vs-database
  audit; the reaper cannot create a retrievable attachment.
- Re-running after a successful batch is a no-op. Alert if `candidates` stays at the
  batch limit for several consecutive runs (backlog larger than the batch cadence).

## Verify locally

```powershell
$env:PIP_REQUIRE_VIRTUALENV = "true"
python -m scripts.reap_chat_attachments --database-url $env:ALUMNI_TEST_DATABASE_URL --limit 50
```
