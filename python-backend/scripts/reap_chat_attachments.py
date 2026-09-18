"""Reap expired private chat attachments from one database.

This is the manual/operator entry point for the scheduled
`app.tasks.chat_attachment_reaper` operation. It prints only aggregate counts and
refuses any non-localhost target unless `--allow-non-localhost` is supplied
explicitly, so an accidental invocation cannot reach a shared office database.
"""

from __future__ import annotations

import argparse
import json
from collections.abc import Sequence
from pathlib import Path

from sqlalchemy.engine import make_url

from app.core.config import Settings
from app.tasks.chat_attachment_reaper import reap_expired_chat_attachments


class ReaperRefusedError(Exception):
    """The requested target is not an approved database."""


def _assert_localhost(database_url: str, allow_non_localhost: bool) -> None:
    host = (make_url(database_url).host or "").casefold()
    if host in {"127.0.0.1", "localhost", "::1"}:
        return
    if not allow_non_localhost:
        raise ReaperRefusedError(f"refusing non-localhost database host {host!r}")


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Parse the target, batch bound, and explicit remote permission."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--database-url", required=True, help="target SQLAlchemy URL")
    parser.add_argument(
        "--limit",
        type=int,
        default=200,
        help="maximum staged attachments to reap in one batch (1-500)",
    )
    parser.add_argument(
        "--upload-root",
        type=Path,
        default=Path("var/uploads"),
        help="private upload root holding the chat/ directory",
    )
    parser.add_argument(
        "--allow-non-localhost",
        action="store_true",
        help="required before the reaper will touch a non-localhost database",
    )
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    """Run one bounded batch and print an aggregate, non-identifying report."""
    arguments = parse_args(argv)
    if not 1 <= arguments.limit <= 500:
        print(json.dumps({"status": "refused", "reason": "limit must be between 1 and 500"}))
        return 2
    try:
        _assert_localhost(arguments.database_url, arguments.allow_non_localhost)
    except ReaperRefusedError as exc:
        print(json.dumps({"status": "refused", "reason": str(exc)}, sort_keys=True))
        return 2
    settings = Settings(
        environment="test",
        database_url=arguments.database_url,
        upload_root=arguments.upload_root,
    )
    report = reap_expired_chat_attachments(settings, limit=arguments.limit)
    print(
        json.dumps(
            {
                "status": "ok",
                "database": make_url(arguments.database_url).database,
                "candidates": report.candidates,
                "deleted_rows": report.deleted_rows,
                "deleted_files": report.deleted_files,
                "file_failures": report.file_failures,
                "limit": report.limit,
            },
            indent=2,
            sort_keys=True,
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
