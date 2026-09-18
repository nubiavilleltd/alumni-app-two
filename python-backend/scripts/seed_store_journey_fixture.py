"""Seed a disposable database with the synthetic store browser-journey fixture.

Creates one approved/verified/active member with a known password plus a single
active product so the store catalogue renders. It refuses any non-localhost database
whose name does not contain "test", removes only its own previously seeded rows, and
prints a small JSON descriptor. Used by ``frontend/e2e/store-journey.mjs``.
"""

from __future__ import annotations

import argparse
import json
import secrets
import time
import uuid
from collections.abc import Sequence

import bcrypt
from sqlalchemy import create_engine, delete, select
from sqlalchemy.engine import make_url
from sqlalchemy.orm import Session

from app.models.generated import Products, Users

EMAIL_DOMAIN = "example.com"
PRODUCT_NAME = "Synthetic Store Journey Product"


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
    prior_user_ids = list(
        session.scalars(select(Users.id).where(Users.email.like("storejourney-%")))
    )
    if prior_user_ids:
        session.execute(delete(Products).where(Products.user_id.in_(prior_user_ids)))
        session.execute(delete(Users).where(Users.id.in_(prior_user_ids)))
    session.execute(delete(Products).where(Products.product_name == PRODUCT_NAME))


def seed(database_url: str) -> dict[str, object]:
    _assert_disposable(database_url)
    engine = create_engine(database_url, pool_pre_ping=True)
    password = f"Journey-{secrets.token_hex(6)}!"
    password_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt(rounds=8)).decode("ascii")
    legacy_hash = password_hash.replace("$2b$", "$2y$", 1)
    email = f"storejourney-{uuid.uuid4().hex[:8]}@{EMAIL_DOMAIN}"
    try:
        with Session(engine) as session, session.begin():
            _purge(session)
            unique = uuid.uuid4().hex[:10]
            user = Users(
                chapter_id=1,
                ip_address="127.0.0.1",
                username=email,
                email=email,
                password=legacy_hash,
                has_password=1,
                onboarding_completion=1,
                nick_name="Journey",
                state="Lagos",
                country="Nigeria",
                created_on=int(time.time()),
                userAccessCode=f"STJ-{unique}",
                profile_status="active",
                voucher="",
                resetKey="",
                first_name="Store",
                last_name="Journey",
                fullname="Store Journey",
                phone="08012345678",
                avatar="uploads/profiles/synthetic.png",
                city="Lagos",
                active=1,
                user_role="alumni",
                is_approved=1,
                email_verified=1,
                graduation_year=2015,
            )
            session.add(user)
            session.flush()
            user_id = int(user.id)
            session.add(
                Products(
                    user_id=user_id,
                    product_name=PRODUCT_NAME,
                    category="Accessories",
                    price="2500.00",
                    description="A synthetic product for the store browser journey.",
                    has_size=0,
                    has_color=0,
                    quantity=10,
                    status="active",
                    pin_item=0,
                )
            )
        return {
            "email": email,
            "password": password,
            "user_id": user_id,
            "product_name": PRODUCT_NAME,
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
