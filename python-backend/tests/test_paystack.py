"""Paystack gateway boundary tests: signing, transport failures, and payload contracts.

Every request is served by an in-process transport, so no test reaches the real
payment provider and no live credential is ever required.
"""

from __future__ import annotations

import hashlib
import hmac
import json
from collections.abc import Callable

import httpx
import pytest
import respx

from app.integrations.paystack import PaystackClient, PaystackError, PaystackSignatureError

SYNTHETIC_SECRET = "sk_test_synthetic_key"
BASE_URL = "https://api.paystack.test"
Handler = Callable[[httpx.Request], httpx.Response]


def _client(handler: Handler) -> PaystackClient:
    """Build a client whose transport never leaves the process."""
    return PaystackClient(
        secret_key=SYNTHETIC_SECRET,
        base_url=BASE_URL,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )


def _json_response(status_code: int, payload: object) -> httpx.Response:
    return httpx.Response(status_code, json=payload)


def test_signature_error_is_a_typed_paystack_error() -> None:
    error = PaystackSignatureError()
    assert isinstance(error, PaystackError)
    assert error.status_code == 401
    assert error.message == "Invalid webhook signature"


def test_secret_key_presence_is_reported_without_exposing_the_value() -> None:
    assert PaystackClient(secret_key=SYNTHETIC_SECRET).has_secret_key is True
    assert PaystackClient().has_secret_key is False
    # A falsy-but-supplied key must behave like an absent key, never like a blank header.
    assert PaystackClient(secret_key="").has_secret_key is False


@pytest.mark.parametrize(
    ("signature", "expected"),
    [
        ("valid", True),
        ("VALID_UPPERCASE", True),
        ("  valid  ", True),
        ("invalid", False),
        ("", False),
    ],
)
def test_webhook_signature_verification_is_case_insensitive_and_trimmed(
    signature: str,
    expected: bool,
) -> None:
    raw_body = json.dumps({"event": "charge.success", "data": {"reference": "ORD-1"}}).encode()
    digest = hmac.new(SYNTHETIC_SECRET.encode("utf-8"), raw_body, hashlib.sha512).hexdigest()
    supplied = digest if signature == "valid" else signature
    if signature == "VALID_UPPERCASE":
        supplied = digest.upper()
    elif signature == "  valid  ":
        supplied = f"  {digest}  "
    assert (
        PaystackClient(secret_key=SYNTHETIC_SECRET).verify_webhook_signature(raw_body, supplied)
        is expected
    )


def test_webhook_signature_is_rejected_when_the_secret_is_absent() -> None:
    raw_body = b'{"event":"charge.success"}'
    digest = hmac.new(SYNTHETIC_SECRET.encode("utf-8"), raw_body, hashlib.sha512).hexdigest()
    assert PaystackClient().verify_webhook_signature(raw_body, digest) is False


def test_webhook_signature_rejects_a_different_body_with_the_same_signature() -> None:
    raw_body = b'{"event":"charge.success","data":{"reference":"ORD-1"}}'
    digest = hmac.new(SYNTHETIC_SECRET.encode("utf-8"), raw_body, hashlib.sha512).hexdigest()
    client = PaystackClient(secret_key=SYNTHETIC_SECRET)
    assert client.verify_webhook_signature(raw_body, digest) is True
    assert client.verify_webhook_signature(b'{"event":"charge.success"}', digest) is False


def test_initialize_transaction_without_a_secret_key_fails_closed() -> None:
    with pytest.raises(PaystackError) as excinfo:
        PaystackClient().initialize_transaction("member@example.test", 150000, "ORD-1")
    assert excinfo.value.status_code == 500
    assert "not configured" in excinfo.value.message


def test_verify_transaction_without_a_secret_key_fails_closed() -> None:
    with pytest.raises(PaystackError) as excinfo:
        PaystackClient().verify_transaction("ORD-1")
    assert excinfo.value.status_code == 500


def test_initialize_transaction_sends_the_expected_request_and_returns_data() -> None:
    captured: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        captured.append(request)
        return _json_response(
            200,
            {
                "status": True,
                "message": "Authorization URL created",
                "data": {
                    "access_code": "acc_synthetic",
                    "authorization_url": "https://checkout.paystack.test/acc_synthetic",
                    "reference": "ORD-1",
                },
            },
        )

    client = _client(handler)
    result = client.initialize_transaction(
        "member@example.test",
        150000,
        "ORD-1",
        {"order_id": 7},
    )

    assert result["access_code"] == "acc_synthetic"
    request = captured[0]
    assert str(request.url) == f"{BASE_URL}/transaction/initialize"
    assert request.method == "POST"
    assert request.headers["Authorization"] == f"Bearer {SYNTHETIC_SECRET}"
    assert request.headers["Cache-Control"] == "no-cache"
    body = json.loads(request.content)
    assert body == {
        "email": "member@example.test",
        "amount": 150000,
        "currency": "NGN",
        "reference": "ORD-1",
        "metadata": {"order_id": 7},
    }


def test_initialize_transaction_defaults_metadata_to_an_empty_object() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert json.loads(request.content)["metadata"] == {}
        return _json_response(200, {"status": True, "data": {"access_code": "acc_synthetic"}})

    result = _client(handler).initialize_transaction("member@example.test", 100, "ORD-2")
    assert result["access_code"] == "acc_synthetic"


def test_initialize_transaction_reports_transport_failures() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectTimeout("synthetic timeout", request=request)

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-3")
    assert excinfo.value.status_code == 502
    assert "Unable to communicate" in excinfo.value.message


def test_initialize_transaction_uses_the_provider_message_on_a_json_error() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(400, {"status": False, "message": "Invalid key"})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-4")
    assert excinfo.value.status_code == 502
    assert excinfo.value.message == "Invalid key"


def test_initialize_transaction_falls_back_when_the_error_body_is_not_json() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return httpx.Response(502, text="<html>gateway down</html>")

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-5")
    assert excinfo.value.message == "Payment initialization failed"


def test_initialize_transaction_rejects_a_malformed_success_body() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, text="not-json")

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-6")
    assert excinfo.value.message == "Malformed response from Paystack"


def test_initialize_transaction_rejects_a_false_status_envelope() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": False, "message": "Duplicate reference"})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-7")
    assert excinfo.value.message == "Duplicate reference"


def test_initialize_transaction_rejects_a_false_status_without_a_message() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": False})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-8")
    assert excinfo.value.message == "Payment initialization returned false status"


def test_initialize_transaction_requires_an_access_code() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": True, "data": {"reference": "ORD-9"}})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-9")
    assert excinfo.value.message == "Paystack response missing access_code"


def test_initialize_transaction_rejects_a_non_object_data_field() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": True, "data": ["not", "an", "object"]})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).initialize_transaction("member@example.test", 100, "ORD-10")
    assert excinfo.value.message == "Paystack response missing access_code"


def test_verify_transaction_quotes_the_reference_in_the_request_path() -> None:
    captured: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        captured.append(request)
        return _json_response(
            200,
            {
                "status": True,
                "data": {
                    "status": "success",
                    "reference": "ORD 1/2",
                    "amount": 150000,
                    "currency": "NGN",
                },
            },
        )

    result = _client(handler).verify_transaction("ORD 1/2")
    assert result["status"] == "success"
    assert result["amount"] == 150000
    assert str(captured[0].url) == f"{BASE_URL}/transaction/verify/ORD%201%2F2"


def test_verify_transaction_reports_transport_failures() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ReadTimeout("synthetic timeout", request=request)

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert "Unable to communicate" in excinfo.value.message


def test_verify_transaction_uses_the_provider_message_on_a_json_error() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(404, {"status": False, "message": "Transaction not found"})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Transaction not found"


def test_verify_transaction_falls_back_when_the_error_body_is_not_json() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return httpx.Response(500, text="upstream error")

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Payment verification failed"


def test_verify_transaction_rejects_a_malformed_success_body() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, text="<html>not json</html>")

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Malformed response from Paystack"


def test_verify_transaction_rejects_a_false_status_envelope() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": False, "message": "Invalid reference"})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Invalid reference"


def test_verify_transaction_rejects_a_false_status_without_a_message() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": False})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Payment verification returned false status"


def test_verify_transaction_requires_a_data_object() -> None:
    def handler(_request: httpx.Request) -> httpx.Response:
        return _json_response(200, {"status": True, "data": "unexpected"})

    with pytest.raises(PaystackError) as excinfo:
        _client(handler).verify_transaction("ORD-1")
    assert excinfo.value.message == "Paystack response missing data object"


def test_client_without_an_injected_transport_still_sends_a_bounded_request() -> None:
    """The implicit-client branch must build the same request shape."""
    with respx.mock(base_url=BASE_URL, assert_all_called=True) as router:
        route = router.post("/transaction/initialize").mock(
            return_value=httpx.Response(200, json={"status": True, "data": {"access_code": "acc"}})
        )
        client = PaystackClient(secret_key=SYNTHETIC_SECRET, base_url=BASE_URL, timeout=5.0)
        result = client.initialize_transaction("member@example.test", 250, "ORD-11")

    assert result["access_code"] == "acc"
    request = route.calls[0].request
    assert request.headers["Authorization"] == f"Bearer {SYNTHETIC_SECRET}"


def test_verify_transaction_without_an_injected_transport_still_sends_a_bounded_request() -> None:
    with respx.mock(base_url=BASE_URL, assert_all_called=True) as router:
        route = router.get("/transaction/verify/ORD-12").mock(
            return_value=httpx.Response(
                200,
                json={"status": True, "data": {"status": "success", "amount": 250}},
            )
        )
        client = PaystackClient(secret_key=SYNTHETIC_SECRET, base_url=BASE_URL, timeout=5.0)
        result = client.verify_transaction("ORD-12")

    assert result["status"] == "success"
    assert route.calls[0].request.method == "GET"


def test_base_url_trailing_slash_is_normalized() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert str(request.url) == f"{BASE_URL}/transaction/verify/ORD-13"
        return _json_response(200, {"status": True, "data": {"status": "success"}})

    client = PaystackClient(
        secret_key=SYNTHETIC_SECRET,
        base_url=f"{BASE_URL}/",
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    assert client.verify_transaction("ORD-13")["status"] == "success"
