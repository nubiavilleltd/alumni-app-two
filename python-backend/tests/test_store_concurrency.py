"""Goal 8 payment/stock concurrency and oversell-prevention proof.

Two paid orders for the same last unit must not both finalize. The repository's
conditional stock deduction (`WHERE quantity >= :qty`) makes the second finalization
fail with `INSUFFICIENT_STOCK`, rolling back and leaving the order pending for
reconciliation. These tests fail on the previous read-modify-write implementation,
which clamped stock to zero and silently oversold.
"""

from __future__ import annotations

import hashlib
import hmac
import json
from collections.abc import Iterator
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import pytest
from sqlalchemy import select

from app.models.generated import Orders, Products
from tests.test_store import StoreHarness, _create_sample_png
from tests.test_store import store_harness as _shared_store_harness


@pytest.fixture(name="store_harness")
def _store_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[StoreHarness]:
    yield from _shared_store_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


def _product_with_stock(harness: StoreHarness, name: str, price: str, quantity: int) -> int:
    response = harness.client.post(
        "/product/add_product",
        headers=harness.admin_headers,
        data={
            "product_name": name,
            "category": "Accessories",
            "price": price,
            "description": "Synthetic",
            "has_size": "0",
            "has_color": "0",
            "quantity": str(quantity),
            "spotlight_index": "0",
        },
        files=[("images", ("synthetic.png", _create_sample_png(), "image/png"))],
    )
    assert response.status_code == 201
    return int(response.json()["data"]["product_id"])


def _checkout(
    harness: StoreHarness, headers: dict[str, str], product_id: int, quantity: int
) -> str:
    added = harness.client.post(
        "/product/add_to_cart",
        headers=headers,
        json={"product_id": product_id, "quantity": quantity},
    )
    assert added.status_code in (200, 201)
    checkout = harness.client.post(
        "/product/initiate_checkout", headers=headers, json={"delivery_type": "pickup"}
    )
    assert checkout.status_code == 200
    return str(checkout.json()["data"]["reference"])


def _webhook(harness: StoreHarness, reference: str, amount_kobo: int) -> bytes:
    body = json.dumps(
        {
            "event": "charge.success",
            "data": {
                "reference": reference,
                "status": "success",
                "amount": amount_kobo,
                "gateway_response": "Successful",
                "channel": "card",
                "currency": "NGN",
            },
        }
    ).encode("utf-8")
    signature = hmac.new(
        harness.paystack_test_key.encode("utf-8"), body, hashlib.sha512
    ).hexdigest()
    response = harness.client.post(
        "/product/paystack_webhook",
        content=body,
        headers={"X-Paystack-Signature": signature, "Content-Type": "application/json"},
    )
    assert response.status_code == 200
    return body


def _order_statuses(harness: StoreHarness, references: list[str]) -> dict[str, str]:
    with harness.engine.connect() as connection:
        rows = connection.execute(
            select(Orders.paystack_reference, Orders.status).where(
                Orders.paystack_reference.in_(references)
            )
        ).all()
        return {ref: status.value for ref, status in rows}


def _stock(harness: StoreHarness, product_id: int) -> int:
    with harness.engine.connect() as connection:
        value = connection.scalar(select(Products.quantity).where(Products.id == product_id))
        return int(value or 0)


def test_two_paid_orders_cannot_oversell_a_single_unit(store_harness: StoreHarness) -> None:
    """Exactly one of two competing paid orders finalizes; stock never goes negative."""
    product_id = _product_with_stock(store_harness, "Single Unit Cap", "1000.00", 1)
    first_ref = _checkout(store_harness, store_harness.customer_headers, product_id, 1)
    second_ref = _checkout(store_harness, store_harness.customer2_headers, product_id, 1)

    _webhook(store_harness, first_ref, 100_000)
    _webhook(store_harness, second_ref, 100_000)

    statuses = _order_statuses(store_harness, [first_ref, second_ref])
    assert list(statuses.values()).count("paid") == 1
    assert list(statuses.values()).count("pending") == 1
    assert _stock(store_harness, product_id) == 0


def test_concurrent_webhook_finalization_serializes_on_stock(store_harness: StoreHarness) -> None:
    """Two simultaneous charge.success webhooks converge on one paid order."""
    product_id = _product_with_stock(store_harness, "Concurrent Cap", "2000.00", 1)
    first_ref = _checkout(store_harness, store_harness.customer_headers, product_id, 1)
    second_ref = _checkout(store_harness, store_harness.customer2_headers, product_id, 1)
    references = [first_ref, second_ref]

    with ThreadPoolExecutor(max_workers=2) as executor:
        list(executor.map(lambda ref: _webhook(store_harness, ref, 200_000), references))

    statuses = _order_statuses(store_harness, references)
    assert list(statuses.values()).count("paid") == 1
    assert list(statuses.values()).count("pending") == 1
    assert _stock(store_harness, product_id) == 0


def test_replayed_webhook_does_not_double_deduct(store_harness: StoreHarness) -> None:
    """Repeating a single successful webhook is idempotent on the same order."""
    product_id = _product_with_stock(store_harness, "Replay Cap", "1500.00", 5)
    reference = _checkout(store_harness, store_harness.customer_headers, product_id, 2)
    _webhook(store_harness, reference, 300_000)
    _webhook(store_harness, reference, 300_000)

    assert _stock(store_harness, product_id) == 3
    assert _order_statuses(store_harness, [reference])[reference] == "paid"
