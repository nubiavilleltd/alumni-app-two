"""Paystack payment gateway integration for order checkout and verification."""

from __future__ import annotations

import hmac
import json
from hashlib import sha512
from typing import Any
from urllib.parse import quote

import httpx
import structlog

logger = structlog.get_logger(__name__)


class PaystackError(Exception):
    """Base exception for Paystack integration failures."""

    def __init__(self, message: str, status_code: int = 502) -> None:
        super().__init__(message)
        self.message = message
        self.status_code = status_code


class PaystackSignatureError(PaystackError):
    """Raised when webhook HMAC signature does not match."""

    def __init__(self, message: str = "Invalid webhook signature") -> None:
        super().__init__(message, status_code=401)


class PaystackClient:
    """HTTP client for Paystack API transactions and webhook verification."""

    def __init__(
        self,
        secret_key: str | None = None,
        base_url: str = "https://api.paystack.co",
        timeout: float = 30.0,
        http_client: httpx.Client | None = None,
    ) -> None:
        self._secret_key = secret_key or ""
        self._base_url = base_url.rstrip("/")
        self._timeout = timeout
        self._http_client = http_client

    @property
    def has_secret_key(self) -> bool:
        """Check if secret key is present."""
        return bool(self._secret_key)

    def _get_headers(self) -> dict[str, str]:
        return {
            "Authorization": f"Bearer {self._secret_key}",
            "Content-Type": "application/json",
            "Cache-Control": "no-cache",
        }

    def verify_webhook_signature(self, raw_body: bytes, signature: str) -> bool:
        """Verify HMAC-SHA512 signature on incoming Paystack webhook payload."""
        if not self._secret_key or not signature:
            return False
        computed = hmac.new(
            self._secret_key.encode("utf-8"),
            raw_body,
            sha512,
        ).hexdigest()
        return hmac.compare_digest(computed.lower(), signature.strip().lower())

    def initialize_transaction(
        self,
        email: str,
        amount_kobo: int,
        reference: str,
        metadata: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Initialize a Paystack transaction and return data containing access_code."""
        if not self._secret_key:
            raise PaystackError("Paystack secret key is not configured", status_code=500)

        payload: dict[str, Any] = {
            "email": email,
            "amount": amount_kobo,
            "currency": "NGN",
            "reference": reference,
            "metadata": metadata or {},
        }
        url = f"{self._base_url}/transaction/initialize"

        try:
            if self._http_client is not None:
                response = self._http_client.post(
                    url,
                    json=payload,
                    headers=self._get_headers(),
                    timeout=self._timeout,
                )
            else:
                with httpx.Client(timeout=self._timeout) as client:
                    response = client.post(
                        url,
                        json=payload,
                        headers=self._get_headers(),
                    )
        except httpx.HTTPError as exc:
            logger.error("Paystack initialize request failed", error=str(exc))
            raise PaystackError("Unable to communicate with Paystack payment gateway") from exc

        if response.status_code != 200:
            logger.error(
                "Paystack initialize failed",
                status_code=response.status_code,
                response_text=response.text[:200],
            )
            try:
                err_data = response.json()
                msg = err_data.get("message") or "Payment initialization failed"
            except (json.JSONDecodeError, ValueError):
                msg = "Payment initialization failed"
            raise PaystackError(msg, status_code=502)

        try:
            data = response.json()
        except (json.JSONDecodeError, ValueError) as exc:
            raise PaystackError("Malformed response from Paystack") from exc

        if not data.get("status"):
            msg = data.get("message") or "Payment initialization returned false status"
            raise PaystackError(msg, status_code=502)

        result = data.get("data")
        if not isinstance(result, dict) or "access_code" not in result:
            raise PaystackError("Paystack response missing access_code", status_code=502)

        return result

    def verify_transaction(self, reference: str) -> dict[str, Any]:
        """Verify transaction status and details with Paystack by reference."""
        if not self._secret_key:
            raise PaystackError("Paystack secret key is not configured", status_code=500)

        safe_ref = quote(reference, safe="")
        url = f"{self._base_url}/transaction/verify/{safe_ref}"

        try:
            if self._http_client is not None:
                response = self._http_client.get(
                    url,
                    headers=self._get_headers(),
                    timeout=self._timeout,
                )
            else:
                with httpx.Client(timeout=self._timeout) as client:
                    response = client.get(
                        url,
                        headers=self._get_headers(),
                    )
        except httpx.HTTPError as exc:
            logger.error("Paystack verify request failed", error=str(exc))
            raise PaystackError("Unable to communicate with Paystack payment gateway") from exc

        if response.status_code != 200:
            logger.error(
                "Paystack verify failed",
                status_code=response.status_code,
                response_text=response.text[:200],
            )
            try:
                err_data = response.json()
                msg = err_data.get("message") or "Payment verification failed"
            except (json.JSONDecodeError, ValueError):
                msg = "Payment verification failed"
            raise PaystackError(msg, status_code=502)

        try:
            data = response.json()
        except (json.JSONDecodeError, ValueError) as exc:
            raise PaystackError("Malformed response from Paystack") from exc

        if not data.get("status"):
            msg = data.get("message") or "Payment verification returned false status"
            raise PaystackError(msg, status_code=502)

        result = data.get("data")
        if not isinstance(result, dict):
            raise PaystackError("Paystack response missing data object", status_code=502)

        return result
