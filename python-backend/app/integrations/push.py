"""VAPID Web Push delivery over the reviewed push-subscription schema."""

from __future__ import annotations

import json
from typing import Any

import structlog
from pywebpush import WebPushException, webpush  # type: ignore[import-untyped]

logger = structlog.get_logger(__name__)


class PushDeliveryError(Exception):
    """A web-push delivery failed for a known reason."""


def send_web_push(
    subscription: dict[str, Any],
    payload: dict[str, Any],
    *,
    private_key: str,
    subject: str,
) -> None:
    """Deliver one Web Push payload; missing/invalid keys raise PushDeliveryError."""
    if not private_key:
        raise PushDeliveryError("VAPID private key is not configured")
    try:
        webpush(
            subscription_info={
                "endpoint": str(subscription["endpoint"]),
                "keys": {"p256dh": str(subscription["p256dh"]), "auth": str(subscription["auth"])},
            },
            data=json.dumps(payload),
            vapid_private_key=private_key,
            vapid_claims={"sub": subject},
            timeout=10,
        )
    except WebPushException as exc:
        logger.error("web_push_delivery_failed", error=str(exc))
        raise PushDeliveryError(str(exc)) from exc
