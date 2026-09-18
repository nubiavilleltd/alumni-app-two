"""Seed a disposable database with the synthetic V2 inbox browser-journey fixture.

Creates one approved/verified/active member with a known password plus a peer, a
group thread they share, and one message. It refuses any non-localhost database
whose name does not contain "test", removes only its own previously seeded rows, and
prints a small JSON descriptor (no tokens, no keys, no other PII). It is used by
``frontend/e2e/messages-v2-inbox.mjs`` and never touches production.
"""

from __future__ import annotations

import argparse
import json
import secrets
import time
import uuid
from collections.abc import Sequence
from typing import cast

import bcrypt
from sqlalchemy import Table, create_engine, delete, select
from sqlalchemy.engine import make_url
from sqlalchemy.orm import Session

from app.models.generated import (
    Messages,
    MessagesAttachments,
    MessageThreads,
    ThreadParticipants,
    Users,
)

THREADS_TABLE: Table = cast(Table, MessageThreads.__table__)

EMAIL_DOMAIN = "example.com"
THREAD_TITLE = "Synthetic V2 Inbox Journey"
MESSAGE_BODY = "Hello from the seeded V2 inbox journey"


class SeedRefusedError(Exception):
    """The requested target is not an approved disposable database."""


def _assert_disposable(database_url: str) -> None:
    url = make_url(database_url)
    host = (url.host or "").casefold()
    database = (url.database or "").casefold()
    if host not in {"127.0.0.1", "localhost", "::1"}:
        raise SeedRefusedError(f"refusing non-localhost database host {host!r}")
    if "test" not in database:
        raise SeedRefusedError(f"refusing database name {database!r} without 'test'")


def _purge(session: Session) -> None:
    prior_user_ids = list(session.scalars(select(Users.id).where(Users.email.like("journey-%"))))
    thread_ids = list(
        session.scalars(
            select(MessageThreads.id).where(
                (MessageThreads.title == THREAD_TITLE)
                | (MessageThreads.created_by.in_(prior_user_ids or [-1]))
            )
        )
    )
    if thread_ids:
        session.execute(
            delete(MessagesAttachments).where(MessagesAttachments.thread_id.in_(thread_ids))
        )
        session.execute(delete(Messages).where(Messages.thread_id.in_(thread_ids)))
        session.execute(
            delete(ThreadParticipants).where(ThreadParticipants.thread_id.in_(thread_ids))
        )
        session.execute(delete(MessageThreads).where(MessageThreads.id.in_(thread_ids)))
    if prior_user_ids:
        session.execute(delete(Users).where(Users.id.in_(prior_user_ids)))


def _insert_user(
    session: Session,
    *,
    email: str,
    password_hash: str,
    fullname: str,
    role: str,
    year: int,
) -> int:
    unique = uuid.uuid4().hex[:10]
    user = Users(
        chapter_id=1,
        ip_address="127.0.0.1",
        username=email,
        email=email,
        password=password_hash,
        has_password=1,
        onboarding_completion=1,
        nick_name="Journey",
        state="Lagos",
        country="Nigeria",
        created_on=int(time.time()),
        userAccessCode=f"JRN-{unique}",
        profile_status="active",
        voucher="",
        resetKey="",
        first_name=fullname.split()[0],
        last_name=fullname.split()[-1],
        fullname=fullname,
        phone="+2348000000000",
        avatar="uploads/profiles/synthetic.png",
        city="Lagos",
        active=1,
        user_role=role,
        is_approved=1,
        email_verified=1,
        graduation_year=year,
    )
    session.add(user)
    session.flush()
    return int(user.id)


def seed(database_url: str) -> dict[str, object]:
    _assert_disposable(database_url)
    engine = create_engine(database_url, pool_pre_ping=True)
    password = f"Journey-{secrets.token_hex(6)}!"
    password_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt(rounds=8)).decode("ascii")
    legacy_hash = password_hash.replace("$2b$", "$2y$", 1)
    member_email = f"journey-member-{uuid.uuid4().hex[:8]}@{EMAIL_DOMAIN}"
    peer_email = f"journey-peer-{uuid.uuid4().hex[:8]}@{EMAIL_DOMAIN}"
    try:
        with Session(engine) as session, session.begin():
            _purge(session)
            member_id = _insert_user(
                session,
                email=member_email,
                password_hash=legacy_hash,
                fullname="Journey Member",
                role="alumni",
                year=2015,
            )
            peer_id = _insert_user(
                session,
                email=peer_email,
                password_hash=legacy_hash,
                fullname="Journey Peer",
                role="alumni",
                year=2014,
            )
            now = time.strftime("%Y-%m-%d %H:%M:%S")
            thread = MessageThreads(
                type="group",
                title=THREAD_TITLE,
                created_by=member_id,
                category="community",
                attachment_enabled=1,
                audio_enabled=0,
                created_at=now,
            )
            session.add(thread)
            session.flush()
            thread_id = int(thread.id)
            session.add_all(
                [
                    ThreadParticipants(
                        thread_id=thread_id,
                        member_id=member_id,
                        role="admin",
                        joined_at=now,
                        created_at=now,
                    ),
                    ThreadParticipants(
                        thread_id=thread_id,
                        member_id=peer_id,
                        role="member",
                        joined_at=now,
                        created_at=now,
                    ),
                ]
            )
            message = Messages(
                thread_id=thread_id,
                sender_member_id=peer_id,
                body=MESSAGE_BODY,
                message_type="text",
                created_at=now,
            )
            session.add(message)
            session.flush()
            session.execute(
                THREADS_TABLE.update()
                .where(MessageThreads.id == thread_id)
                .values(
                    last_message_id=message.id,
                    last_message_at=now,
                    last_message_preview=MESSAGE_BODY,
                    last_message_sender_name="Journey Peer",
                    last_message_sender_member_id=peer_id,
                )
            )
        return {
            "email": member_email,
            "password": password,
            "user_id": member_id,
            "peer_id": peer_id,
            "thread_id": thread_id,
            "thread_title": THREAD_TITLE,
            "message_body": MESSAGE_BODY,
        }
    finally:
        engine.dispose()


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--database-url", required=True, help="disposable localhost test URL")
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    arguments = parse_args(argv)
    try:
        fixture = seed(arguments.database_url)
    except SeedRefusedError as exc:
        print(json.dumps({"status": "refused", "reason": str(exc)}, sort_keys=True))
        return 2
    print(json.dumps({"status": "ok", **fixture}, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
