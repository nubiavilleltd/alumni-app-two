"""Scheduled cleanup for expired private chat attachments.

The V2 attachment feature stages an owned file for up to 24 hours before a message
links it. Once staged uploads expire they are unreadable through the authenticated
download route, but their database rows and private bytes must still be reclaimed.
This bounded operation is the schedulable unit; a deployment cron, systemd timer,
container scheduler, or future `arq` worker registration can call it with the
application settings. It never performs HTTP work and never returns file paths or
uploader identifiers.
"""

from __future__ import annotations

from app.api.common import with_session
from app.core.config import Settings
from app.db.session import Database
from app.integrations.uploads import ChatAttachmentStorage
from app.services.chat import ChatAttachmentReapResult, ChatService


def reap_expired_chat_attachments(
    settings: Settings, *, limit: int = 200
) -> ChatAttachmentReapResult:
    """Run one bounded, idempotent reaper batch for the configured database."""
    database = Database(settings)
    storage = ChatAttachmentStorage(settings.upload_root)
    try:
        return with_session(
            database,
            lambda session: ChatService(session, storage).reap_expired_staged_attachments(
                limit=limit
            ),
        )
    finally:
        database.dispose()
