"""Goal 8 store/orders edge, error-path, and provider-failure coverage.

These tests complement ``tests/test_store.py``: that module proves the happy
paths, while this module drives the validation, authorization, state-machine,
rollback, and provider-failure branches that must still behave correctly.
Everything runs against the sanitized disposable schema with synthetic rows.
"""

from __future__ import annotations

import json
from collections.abc import Iterator
from pathlib import Path
from typing import Any, cast

import pytest
from sqlalchemy import func, select

from app.models.generated import Products
from app.repositories.store import StoreRepository
from tests.test_store import StoreHarness, _create_sample_png
from tests.test_store import store_harness as _shared_store_harness


@pytest.fixture(name="store_harness")
def _store_harness_fixture(
    rsa_pem_pair: tuple[str, str],
    tmp_path: Path,
) -> Iterator[StoreHarness]:
    """Delegate to the shared Goal 8 harness generator without shadowing its name."""
    yield from _shared_store_harness.__wrapped__(  # type: ignore[attr-defined]
        rsa_pem_pair, tmp_path
    )


def _error_message(response: Any) -> str:
    """Return the application-standard error message from a failed response."""
    payload = response.json()
    assert payload["error"]["code"] == "http_error"
    message = payload["error"]["message"]
    assert isinstance(message, str)
    return message


def _form(**overrides: Any) -> dict[str, Any]:
    data: dict[str, Any] = {
        "product_name": "Edge Product",
        "category": "Apparel",
        "price": "1000.00",
        "description": "Edge case product",
        "quantity": "10",
        "spotlight_index": "0",
    }
    data.update(overrides)
    return data


def _post_product(
    harness: StoreHarness,
    *,
    headers: dict[str, str] | None = None,
    image_count: int = 1,
    uploads: list[tuple[str, bytes, str]] | None = None,
    **overrides: Any,
) -> Any:
    """Post one product payload and return the raw response."""
    if uploads is None:
        uploads = [
            (f"edge-{index}.png", _create_sample_png(), "image/png") for index in range(image_count)
        ]
    return harness.client.post(
        "/product/add_product",
        headers=headers or harness.admin_headers,
        data=_form(**overrides),
        files=[("images", upload) for upload in uploads],
    )


def _create_product(
    harness: StoreHarness,
    *,
    headers: dict[str, str] | None = None,
    image_count: int = 1,
    **overrides: Any,
) -> int:
    response = _post_product(
        harness,
        headers=headers,
        image_count=image_count,
        **overrides,
    )
    assert response.status_code == 201, response.text
    product_id = response.json()["data"]["product_id"]
    assert isinstance(product_id, int)
    return product_id


# ═════════════════════════════════════════════════════════════
# PRODUCT VALIDATION
# ═════════════════════════════════════════════════════════════


@pytest.mark.parametrize(
    ("overrides", "expected_message"),
    [
        ({"product_name": ""}, "Please fill all required fields."),
        ({"category": "  "}, "Please fill all required fields."),
        ({"description": ""}, "Please fill all required fields."),
        ({"price": "not-a-number"}, "Price must be a valid non-negative number."),
        ({"price": "-5.00"}, "Price must be a valid non-negative number."),
        ({"quantity": "abc"}, "Quantity must be a valid non-negative number."),
        ({"quantity": "-3"}, "Quantity must be a valid non-negative number."),
        ({"quantity": "null"}, "Quantity must be a valid non-negative number."),
    ],
)
def test_add_product_rejects_invalid_scalar_fields(
    store_harness: StoreHarness,
    overrides: dict[str, Any],
    expected_message: str,
) -> None:
    response = _post_product(store_harness, **overrides)
    assert response.status_code == 400, response.text
    assert _error_message(response) == expected_message


@pytest.mark.parametrize(
    ("overrides", "expected_message"),
    [
        ({"variants": "not-json"}, "Invalid variants JSON payload."),
        ({"variants": "[]"}, "Please provide at least one variant."),
        (
            {"variants": json.dumps([{"color": "Navy", "quantity": 1}])},
            "Variant #0 is missing a size.",
        ),
        (
            {"variants": json.dumps([{"size": "M", "quantity": 1}])},
            "Variant #0 is missing a color.",
        ),
        (
            {"variants": json.dumps([{"color": "Navy", "size": "M"}])},
            "Invalid quantity for variant #0.",
        ),
        (
            {"variants": json.dumps([{"color": "Navy", "size": "M", "quantity": -1}])},
            "Invalid quantity for variant #0.",
        ),
        (
            {
                "variants": json.dumps(
                    [
                        {"color": "Navy", "size": "M", "quantity": 1},
                        {"color": "Navy", "size": "M", "quantity": 2},
                    ]
                )
            },
            "Duplicate variant combination at #1.",
        ),
    ],
)
def test_add_product_rejects_invalid_variant_payloads(
    store_harness: StoreHarness,
    overrides: dict[str, Any],
    expected_message: str,
) -> None:
    response = _post_product(
        store_harness,
        has_size="1",
        has_color="1",
        quantity="null",
        **overrides,
    )
    assert response.status_code == 400, response.text
    assert _error_message(response) == expected_message


def test_add_product_requires_at_least_one_image(store_harness: StoreHarness) -> None:
    response = _post_product(store_harness, image_count=0)
    assert response.status_code == 400, response.text
    assert _error_message(response) == "Please upload at least one product image."


def test_add_product_rejects_more_than_ten_images(store_harness: StoreHarness) -> None:
    response = _post_product(store_harness, image_count=11)
    assert response.status_code == 400, response.text
    assert _error_message(response) == "Product may contain at most 10 images"


def test_add_product_rejects_a_non_image_upload(store_harness: StoreHarness) -> None:
    response = _post_product(
        store_harness,
        uploads=[("not-an-image.png", b"definitely not an image", "image/png")],
    )
    assert response.status_code == 400, response.text
    assert _error_message(response) == "Avatar file is not a valid image"


def test_add_product_rejects_an_out_of_range_spotlight_index(
    store_harness: StoreHarness,
) -> None:
    response = _post_product(store_harness, spotlight_index="4")
    assert response.status_code == 400, response.text
    assert _error_message(response) == "Invalid spotlight index."


def test_add_product_rejects_a_variant_image_index_out_of_range(
    store_harness: StoreHarness,
) -> None:
    response = _post_product(
        store_harness,
        has_size="1",
        quantity="null",
        variants=json.dumps([{"size": "M", "quantity": 3, "image_index": 5}]),
    )
    assert response.status_code == 400, response.text
    assert _error_message(response) == "Invalid image index for variant #0."


def test_add_product_accepts_json_body_and_binds_variants_to_images(
    store_harness: StoreHarness,
) -> None:
    """The JSON path must stage no uploads and still bind variant images."""
    response = _post_product(
        store_harness,
        has_size="1",
        quantity="null",
        variants=json.dumps([{"size": "M", "quantity": 3, "image_index": 0}]),
    )
    assert response.status_code == 201, response.text
    product_id = response.json()["data"]["product_id"]

    catalog = store_harness.client.get("/product/fetch_products")
    product = next(item for item in catalog.json()["data"] if item["id"] == product_id)
    assert product["variants"][0]["quantity"] == 3
    assert product["variants"][0]["image_id"] == product["images"][0]["id"]


def test_add_product_rolls_back_and_deletes_staged_files_on_a_late_failure(
    store_harness: StoreHarness,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """A late persistence failure must not leave rows or orphaned image files."""

    def _boom(
        _self: StoreRepository, _product_id: int, _payloads: list[dict[str, Any]]
    ) -> list[int]:
        raise RuntimeError("synthetic variant persistence failure")

    monkeypatch.setattr(StoreRepository, "add_product_variants", _boom)

    before_files = sorted((store_harness.upload_root / "products").glob("**/*"))
    with pytest.raises(RuntimeError, match="synthetic variant persistence failure"):
        _post_product(
            store_harness,
            product_name="Rollback Product",
            has_size="1",
            quantity="null",
            variants=json.dumps([{"size": "M", "quantity": 2}]),
        )

    with store_harness.engine.connect() as conn:
        persisted = conn.execute(
            select(func.count())
            .select_from(Products)
            .where(Products.product_name == "Rollback Product")
        ).scalar_one()
    assert persisted == 0
    assert sorted((store_harness.upload_root / "products").glob("**/*")) == before_files


# ═════════════════════════════════════════════════════════════
# PRODUCT EDIT / DELETE / PIN
# ═════════════════════════════════════════════════════════════


def test_edit_product_rejects_invalid_targets_and_fields(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers

    missing_id = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id="0"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert missing_id.status_code == 400
    assert _error_message(missing_id) == "product_id is required."

    unknown = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id="999999"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Product not found."

    product_id = _create_product(store_harness)

    blank_name = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(product_id), product_name=""),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert blank_name.status_code == 400
    assert _error_message(blank_name) == "Please fill all required fields."

    bad_price = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(product_id), price="free"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert bad_price.status_code == 400
    assert _error_message(bad_price) == "Price must be a valid non-negative number."

    bad_quantity = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(product_id), quantity="many"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert bad_quantity.status_code == 400
    assert _error_message(bad_quantity) == "Quantity must be a valid non-negative number."


def test_edit_product_rejects_a_foreign_or_malformed_delete_image_list(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers
    first = _create_product(store_harness, product_name="First Product")
    second = _create_product(store_harness, product_name="Second Product")

    second_images = client.get("/product/fetch_products").json()["data"]
    foreign_image_id = next(item for item in second_images if item["id"] == second)["images"][0][
        "id"
    ]

    foreign = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(first), delete_image_ids=json.dumps([foreign_image_id])),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert foreign.status_code == 400
    assert (
        _error_message(foreign) == f"Image id {foreign_image_id} does not belong to this product."
    )

    malformed = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(first), delete_image_ids="{not-json}"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert malformed.status_code == 400
    assert _error_message(malformed) == "delete_image_ids must be a JSON array of ids."


def test_edit_product_replaces_and_removes_images_with_file_cleanup(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers
    product_id = _create_product(store_harness, image_count=2)

    images = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["images"]
    removed_id = images[0]["id"]
    removed_file = (
        store_harness.upload_root / "products" / images[0]["image_url"].rsplit("/", 1)[-1]
    )
    assert removed_file.is_file()

    response = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(
            product_id=str(product_id),
            delete_image_ids=json.dumps([removed_id]),
            new_images="1",
        ),
        files=[("images", ("replacement.png", _create_sample_png(), "image/png"))],
    )
    assert response.status_code == 200, response.text
    assert response.json()["data"]["product_id"] == product_id
    assert not removed_file.exists()

    updated = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )
    assert len(updated["images"]) == 2
    assert all(image["id"] != removed_id for image in updated["images"])


def test_edit_product_rejects_conflicting_or_stale_spotlight_selectors(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers
    product_id = _create_product(store_harness, image_count=2)
    images = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["images"]

    both = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(
            product_id=str(product_id),
            spotlight_image_id=str(images[0]["id"]),
            spotlight_index="1",
        ),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert both.status_code == 400
    assert (
        _error_message(both) == "Set only one of spotlight_image_id or spotlight_index, not both."
    )

    stale = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(
            product_id=str(product_id),
            delete_image_ids=json.dumps([images[0]["id"]]),
            spotlight_image_id=str(images[0]["id"]),
            spotlight_index="",
        ),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert stale.status_code == 400
    assert _error_message(stale) == "spotlight_image_id must be a kept existing image."

    out_of_range = client.post(
        "/product/edit_product",
        headers=headers,
        data=_form(product_id=str(product_id), spotlight_index="9"),
        files=[("images", ("edge.png", _create_sample_png(), "image/png"))],
    )
    assert out_of_range.status_code == 400
    assert (
        _error_message(out_of_range)
        == "spotlight_index is out of range for the newly uploaded images."
    )


def test_delete_product_rejects_unknown_targets_and_cleans_up_images(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers

    missing_id = client.post("/product/delete_product", headers=headers, json={"product_id": 0})
    assert missing_id.status_code == 400
    assert _error_message(missing_id) == "product_id is required."

    unknown = client.post("/product/delete_product", headers=headers, json={"product_id": 999999})
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Product not found."

    product_id = _create_product(store_harness)
    stored = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["images"][0]["image_url"]
    stored_file = store_harness.upload_root / "products" / stored.rsplit("/", 1)[-1]
    assert stored_file.is_file()

    deleted = client.request(
        "DELETE",
        "/product/delete_product",
        headers=headers,
        json={"product_id": product_id},
    )
    assert deleted.status_code == 200, deleted.text
    assert not stored_file.exists()
    assert all(
        item["id"] != product_id for item in client.get("/product/fetch_products").json()["data"]
    )

    again = client.post("/product/delete_product", headers=headers, json={"product_id": product_id})
    assert again.status_code == 404


def test_pin_product_item_enforces_limit_and_reports_missing_inputs(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    headers = store_harness.admin_headers

    missing_flag = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": 1},
    )
    assert missing_flag.status_code == 400
    assert _error_message(missing_flag) == "pin_item is required."

    invalid_id = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": "abc", "pin_item": True},
    )
    assert invalid_id.status_code == 400
    assert _error_message(invalid_id) == "Invalid product."

    unknown = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": 999999, "pin_item": True},
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Product not found."

    pinned_ids = [
        _create_product(store_harness, product_name=f"Pinned Product {index}") for index in range(4)
    ]
    for product_id in pinned_ids:
        pinned = client.post(
            "/product/pin_product_item",
            headers=headers,
            json={"product_id": product_id, "pin_item": True},
        )
        assert pinned.status_code == 200, pinned.text

    overflow_id = _create_product(store_harness, product_name="Pinned Overflow")
    overflow = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": overflow_id, "pin_item": True},
    )
    assert overflow.status_code == 400
    assert _error_message(overflow) == (
        "You can only pin a maximum of 4 products. Please unpin one before pinning another."
    )

    # Unpinning one product frees a slot for the previously rejected product.
    unpinned = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": pinned_ids[0], "pin_item": False},
    )
    assert unpinned.status_code == 200, unpinned.text
    retried = client.post(
        "/product/pin_product_item",
        headers=headers,
        json={"product_id": overflow_id, "pin_item": True},
    )
    assert retried.status_code == 200, retried.text


# ═════════════════════════════════════════════════════════════
# CART EDGES
# ═════════════════════════════════════════════════════════════


def test_add_to_cart_rejects_invalid_product_and_quantity(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers

    invalid = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": 0, "quantity": 1},
    )
    assert invalid.status_code == 400
    assert _error_message(invalid) == "Invalid request."

    zero_quantity = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": "abc", "quantity": 0},
    )
    assert zero_quantity.status_code == 400
    assert _error_message(zero_quantity) == "Invalid request."

    unknown = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": 999999, "quantity": 1},
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Product not found."

    # A non-numeric quantity falls back to the legacy default of one unit.
    product_id = _create_product(store_harness, quantity="5")
    defaulted = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": "not-a-number"},
    )
    assert defaulted.status_code == 200, defaulted.text
    assert defaulted.json()["data"]["items"][0]["quantity"] == 1


def test_add_to_cart_enforces_variant_and_stock_rules(store_harness: StoreHarness) -> None:
    client = store_harness.client
    admin = store_harness.admin_headers
    customer = store_harness.customer_headers

    variant_product = _create_product(
        store_harness,
        product_name="Variant Guard Product",
        has_size="1",
        quantity="null",
        variants=json.dumps([{"size": "M", "quantity": 2}]),
    )
    plain_product = _create_product(store_harness, product_name="Plain Guard Product", quantity="2")

    no_variant = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": variant_product, "quantity": 1},
    )
    assert no_variant.status_code == 400
    assert _error_message(no_variant) == "Please select a product variant."

    wrong_variant = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": variant_product, "variant_id": 999999, "quantity": 1},
    )
    assert wrong_variant.status_code == 404
    assert _error_message(wrong_variant) == "Variant not found."

    variant_id = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == variant_product
    )["variants"][0]["id"]

    over_variant = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": variant_product, "variant_id": variant_id, "quantity": 3},
    )
    assert over_variant.status_code == 400
    assert _error_message(over_variant) == "Insufficient stock."

    over_plain = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": plain_product, "quantity": 9},
    )
    assert over_plain.status_code == 400
    assert _error_message(over_plain) == "Insufficient stock."

    first = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": variant_product, "variant_id": variant_id, "quantity": 1},
    )
    assert first.status_code == 200, first.text

    # A repeated add that would exceed stock is rejected instead of silently clamped.
    accumulated = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": variant_product, "variant_id": variant_id, "quantity": 2},
    )
    assert accumulated.status_code == 400
    assert _error_message(accumulated) == "Cannot add more than available stock."

    # The rejected add must not have mutated the stored quantity.
    cart = client.get("/product/fetch_cart", headers=customer).json()["data"]
    assert cart["items"][0]["quantity"] == 1
    assert cart["total_items"] == 1

    # Administrator tooling must not bypass the customer cart guard.
    admin_cart = client.get("/product/fetch_cart", headers=admin).json()["data"]
    assert admin_cart["items"] == []


def test_cart_update_and_remove_guard_invalid_and_foreign_items(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    other = store_harness.customer2_headers

    product_id = _create_product(store_harness, quantity="5")
    added = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 2},
    )
    assert added.status_code == 200, added.text
    cart_item_id = added.json()["data"]["items"][0]["cart_item_id"]

    invalid = client.post("/product/update_cart", headers=customer, json={"cart_item_id": 0})
    assert invalid.status_code == 400
    assert _error_message(invalid) == "Invalid cart item."

    missing_quantity = client.post(
        "/product/update_cart",
        headers=customer,
        json={"cart_item_id": cart_item_id},
    )
    assert missing_quantity.status_code == 400
    assert _error_message(missing_quantity) == "Quantity must be 0 or greater."

    unknown = client.post(
        "/product/update_cart",
        headers=customer,
        json={"cart_item_id": 999999, "quantity": 1},
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Cart item not found."

    foreign = client.post(
        "/product/update_cart",
        headers=other,
        json={"cart_item_id": cart_item_id, "quantity": 3},
    )
    assert foreign.status_code == 403
    assert _error_message(foreign) == "Unauthorized."

    over_stock = client.post(
        "/product/update_cart",
        headers=customer,
        json={"cart_item_id": cart_item_id, "quantity": 50},
    )
    assert over_stock.status_code == 400
    assert _error_message(over_stock) == "Cannot exceed available stock."

    # quantity=0 is the documented removal path.
    removed = client.post(
        "/product/update_cart",
        headers=customer,
        json={"cart_item_id": cart_item_id, "quantity": 0},
    )
    assert removed.status_code == 200, removed.text
    assert removed.json()["data"]["items"] == []

    invalid_remove = client.post(
        "/product/remove_from_cart",
        headers=customer,
        json={"cart_item_id": 0},
    )
    assert invalid_remove.status_code == 400
    assert _error_message(invalid_remove) == "Invalid cart item."

    unknown_remove = client.post(
        "/product/remove_from_cart",
        headers=customer,
        json={"cart_item_id": cart_item_id},
    )
    assert unknown_remove.status_code == 404
    assert _error_message(unknown_remove) == "Cart item not found."

    cleared = client.post("/product/clear_cart", headers=customer)
    assert cleared.status_code == 200, cleared.text
    assert cleared.json()["data"] == {
        "cart_id": None,
        "total_items": 0,
        "subtotal": "0.00",
        "items": [],
    }


def test_cart_removal_of_a_foreign_item_is_denied(store_harness: StoreHarness) -> None:
    client = store_harness.client
    other = store_harness.customer2_headers
    product_id = _create_product(store_harness, quantity="5")
    added = client.post(
        "/product/add_to_cart",
        headers=store_harness.customer_headers,
        json={"product_id": product_id, "quantity": 1},
    )
    assert added.status_code == 200, added.text
    cart_item_id = added.json()["data"]["items"][0]["cart_item_id"]

    denied = client.post(
        "/product/remove_from_cart",
        headers=other,
        json={"cart_item_id": cart_item_id},
    )
    assert denied.status_code == 403
    assert _error_message(denied) == "Unauthorized."


# ═════════════════════════════════════════════════════════════
# ADDRESSES
# ═════════════════════════════════════════════════════════════


def test_address_validation_ownership_and_default_switching(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    other = store_harness.customer2_headers

    incomplete = client.post(
        "/product/add_address",
        headers=customer,
        json={"first_name": "Only", "last_name": "Partial"},
    )
    assert incomplete.status_code == 400
    assert _error_message(incomplete) == "Please fill all required address fields."

    first = client.post(
        "/product/add_address",
        headers=customer,
        json={
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223333",
            "address": "1 Alumni Way",
            "state": "Lagos",
            "area": "Mainland",
            "landmark": "Blue gate",
        },
    )
    assert first.status_code == 201, first.text
    first_id = first.json()["address_id"]

    second = client.post(
        "/product/add_address",
        headers=customer,
        json={
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223334",
            "additional_phone": "+2348022223335",
            "address": "2 Alumni Way",
            "state": "Lagos",
            "area": "Island",
        },
    )
    assert second.status_code == 201, second.text
    second_id = second.json()["address_id"]
    assert len(second.json()["data"]) >= 2

    blank_edit = client.post(
        "/product/edit_address",
        headers=customer,
        json={"id": first_id, "first_name": ""},
    )
    assert blank_edit.status_code == 400
    assert _error_message(blank_edit) == "Please fill all required address fields."

    missing_edit = client.post(
        "/product/edit_address",
        headers=customer,
        json={
            "id": 999999,
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223333",
            "address": "1 Alumni Way",
            "state": "Lagos",
            "area": "Mainland",
        },
    )
    assert missing_edit.status_code == 404
    assert _error_message(missing_edit) == "Address not found."

    edited = client.post(
        "/product/edit_address",
        headers=customer,
        json={
            "id": first_id,
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223339",
            "address": "1 Alumni Way",
            "state": "Lagos",
            "area": "Mainland",
        },
    )
    assert edited.status_code == 201, edited.text
    assert any(entry["phone"] == "+2348022223339" for entry in edited.json()["data"])

    # Another member's address id is invisible, not merely read-only.
    foreign = client.post(
        "/product/edit_address",
        headers=other,
        json={
            "id": first_id,
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223333",
            "address": "1 Alumni Way",
            "state": "Lagos",
            "area": "Mainland",
        },
    )
    assert foreign.status_code == 404
    assert _error_message(foreign) == "Address not found."

    assert (
        client.post(
            "/product/set_default_address", headers=customer, json={"address_id": 0}
        ).status_code
        == 400
    )
    assert (
        client.post(
            "/product/set_default_address", headers=customer, json={"address_id": first_id}
        ).status_code
        == 200
    )
    unknown_default = client.post(
        "/product/set_default_address",
        headers=customer,
        json={"address_id": 999999},
    )
    assert unknown_default.status_code == 404
    assert _error_message(unknown_default) == "Address not found."

    deleted = client.post(
        "/product/delete_address",
        headers=customer,
        json={"address_id": second_id},
    )
    assert deleted.status_code == 200, deleted.text
    assert (
        client.post(
            "/product/delete_address",
            headers=customer,
            json={"address_id": second_id},
        ).status_code
        == 404
    )
    invalid_delete = client.post(
        "/product/delete_address",
        headers=customer,
        json={"address_id": "abc"},
    )
    assert invalid_delete.status_code == 400
    assert _error_message(invalid_delete) == "address_id is required."


def test_delivery_zones_are_public_and_grouped_by_state(store_harness: StoreHarness) -> None:
    response = store_harness.client.get("/product/fetch_delivery_zones")
    assert response.status_code == 200, response.text
    payload = response.json()
    assert payload["status"] is True
    lagos = next(zone for zone in payload["data"] if zone["state"] == "Lagos")
    areas = {area["area"] for area in lagos["areas"]}
    assert {"Mainland", "Island"} <= areas

    posted = store_harness.client.post("/product/fetch_delivery_zones")
    assert posted.status_code == 200
    assert posted.json()["data"] == payload["data"]


# ═════════════════════════════════════════════════════════════
# CHECKOUT AND PAYMENT VERIFICATION
# ═════════════════════════════════════════════════════════════


def test_initiate_checkout_rejects_invalid_delivery_inputs(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers

    bad_type = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "teleport"},
    )
    assert bad_type.status_code == 400
    assert _error_message(bad_type) == ('delivery_type must be "door_delivery" or "self_pickup".')

    product_id = _create_product(store_harness, quantity="5")
    added = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 1},
    )
    assert added.status_code == 200, added.text

    missing_details = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "door_delivery", "first_name": "Ada"},
    )
    assert missing_details.status_code == 400
    assert _error_message(missing_details) == "Please provide all required delivery details."

    unknown_address = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "door_delivery", "address_id": 999999},
    )
    assert unknown_address.status_code == 404
    assert _error_message(unknown_address) == "Saved address not found."

    unavailable = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={
            "delivery_type": "delivery",
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223333",
            "address": "3 Alumni Way",
            "state": "Kano",
            "area": "Unknown Area",
        },
    )
    assert unavailable.status_code == 400
    assert "Delivery is not available to Unknown Area, Kano yet." in _error_message(unavailable)


def test_initiate_checkout_requires_a_non_empty_cart(store_harness: StoreHarness) -> None:
    response = store_harness.client.post(
        "/product/initiate_checkout",
        headers=store_harness.customer_headers,
        json={"delivery_type": "self_pickup"},
    )
    assert response.status_code == 400
    assert _error_message(response) == "Your cart is empty."


def test_initiate_checkout_reports_provider_failure_and_snapshots_a_successful_order(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers

    product_id = _create_product(store_harness, quantity="5", price="2000.00")
    added = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 2},
    )
    assert added.status_code == 200, added.text

    def _provider_down(**_kwargs: Any) -> dict[str, Any]:
        from app.integrations.paystack import PaystackError

        raise PaystackError("Unable to communicate with Paystack payment gateway")

    original = store_harness.mock_paystack.initialize_transaction
    store_harness.mock_paystack.initialize_transaction = _provider_down  # type: ignore[method-assign]
    try:
        failed = client.post(
            "/product/initiate_checkout",
            headers=customer,
            json={"delivery_type": "self_pickup"},
        )
    finally:
        store_harness.mock_paystack.initialize_transaction = original  # type: ignore[method-assign]

    assert failed.status_code == 502
    assert _error_message(failed) == "Unable to communicate with Paystack payment gateway"
    # A failed initialization must not leave a pending order behind.
    orders = client.get("/product/fetch_orders", headers=customer).json()["data"]
    assert all(order["status"] != "pending" for order in orders)

    retried = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "pickup"},
    )
    assert retried.status_code == 200, retried.text
    data = retried.json()["data"]
    assert data["reference"].startswith("ORD-")
    assert data["subtotal"] == "4000.00"
    assert data["shipping_fee"] == "0.00"
    assert data["delivery_fee"] == "0.00"
    assert data["amount"] == "4000.00"
    assert data["access_code"]
    assert store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"] == 400000


def test_verify_payment_guards_reference_ownership_and_success_state(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    other = store_harness.customer2_headers

    blank = client.post("/product/verify_payment", headers=customer, json={"reference": "   "})
    assert blank.status_code == 400
    assert _error_message(blank) == "Payment reference is required."

    unknown = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": "ORD-DOES-NOT-EXIST"},
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Order not found."

    product_id = _create_product(store_harness, quantity="5", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 1},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    assert checkout.status_code == 200, checkout.text
    reference = checkout.json()["data"]["reference"]

    foreign = client.post(
        "/product/verify_payment",
        headers=other,
        json={"reference": reference},
    )
    assert foreign.status_code == 403
    assert _error_message(foreign) == "Unauthorized."

    store_harness.mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "abandoned",
            "reference": reference,
            "amount": 150000,
            "currency": "NGN",
        },
    }
    not_successful = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert not_successful.status_code == 400
    assert _error_message(not_successful) == "Payment was not successful. Please try again."

    # A failed order cannot be retried through verification.
    retry_failed = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert retry_failed.status_code == 400
    assert _error_message(retry_failed) == "This payment was unsuccessful. Please try again."


def test_verify_payment_rejects_an_amount_mismatch(store_harness: StoreHarness) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    product_id = _create_product(store_harness, quantity="5", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 1},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    reference = checkout.json()["data"]["reference"]
    expected_kobo = store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]

    store_harness.mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": expected_kobo - 100,
            "currency": "NGN",
        },
    }
    mismatch = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert mismatch.status_code == 400
    assert (
        _error_message(mismatch)
        == f"Payment amount mismatch. Please contact support with reference: {reference}"
    )


def test_verify_payment_is_idempotent_after_success(store_harness: StoreHarness) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    product_id = _create_product(store_harness, quantity="5", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 2},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    reference = checkout.json()["data"]["reference"]
    expected_kobo = store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]
    store_harness.mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": expected_kobo,
            "currency": "NGN",
        },
    }

    first = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert first.status_code == 200, first.text
    assert first.json()["data"]["payment_status"] == "paid"

    stock_after = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["quantity"]

    replay = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert replay.status_code == 200, replay.text
    assert replay.json()["data"]["payment_status"] == "paid"
    assert replay.json()["data"]["order_id"] == first.json()["data"]["order_id"]

    # A replay must not decrement inventory a second time.
    stock_replayed = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["quantity"]
    assert stock_replayed == stock_after

    # The paid order becomes visible in customer order history.
    history = client.get("/product/fetch_orders", headers=customer).json()["data"]
    assert any(
        order["order_number"] == checkout.json()["data"]["order_number"] for order in history
    )


# ═════════════════════════════════════════════════════════════
# WEBHOOK
# ═════════════════════════════════════════════════════════════


def _signed_webhook(harness: StoreHarness, payload: dict[str, Any]) -> Any:
    import hashlib
    import hmac

    body = json.dumps(payload).encode()
    signature = hmac.new(
        harness.paystack_test_key.encode("utf-8"),
        body,
        hashlib.sha512,
    ).hexdigest()
    return harness.client.post(
        "/product/paystack_webhook",
        content=body,
        headers={"X-Paystack-Signature": signature, "Content-Type": "application/json"},
    )


def test_webhook_rejects_an_invalid_or_missing_signature(store_harness: StoreHarness) -> None:
    client = store_harness.client
    unsigned = client.post(
        "/product/paystack_webhook",
        content=json.dumps({"event": "charge.success"}).encode(),
        headers={"Content-Type": "application/json"},
    )
    assert unsigned.status_code == 401
    assert unsigned.text == "Invalid signature"

    wrong = client.post(
        "/product/paystack_webhook",
        content=json.dumps({"event": "charge.success"}).encode(),
        headers={"X-Paystack-Signature": "deadbeef", "Content-Type": "application/json"},
    )
    assert wrong.status_code == 401
    assert wrong.text == "Invalid signature"


def test_webhook_acknowledges_unactionable_events(store_harness: StoreHarness) -> None:
    for payload in (
        {"event": "charge.success"},  # no data/reference
        {"event": "transfer.success", "data": {"reference": "ORD-IRRELEVANT"}},
        {"event": "charge.success", "data": {"reference": "ORD-UNKNOWN-REFERENCE"}},
    ):
        response = _signed_webhook(store_harness, payload)
        assert response.status_code == 200, response.text
        assert response.text == "OK"


def test_webhook_marks_a_mismatched_charge_as_failed(store_harness: StoreHarness) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    product_id = _create_product(store_harness, quantity="5", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 1},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    reference = checkout.json()["data"]["reference"]
    expected_kobo = store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]

    response = _signed_webhook(
        store_harness,
        {
            "event": "charge.success",
            "data": {"reference": reference, "status": "success", "amount": expected_kobo + 1},
        },
    )
    assert response.status_code == 200

    # The order is failed, so verification reports the terminal failure state.
    verify = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert verify.status_code == 400
    assert _error_message(verify) == "This payment was unsuccessful. Please try again."


def test_webhook_finalizes_a_valid_charge_exactly_once(store_harness: StoreHarness) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    product_id = _create_product(store_harness, quantity="5", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 2},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    reference = checkout.json()["data"]["reference"]
    expected_kobo = store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]
    payload = {
        "event": "charge.success",
        "data": {"reference": reference, "status": "success", "amount": expected_kobo},
    }

    first = _signed_webhook(store_harness, payload)
    assert first.status_code == 200
    stock_after = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["quantity"]

    replay = _signed_webhook(store_harness, payload)
    assert replay.status_code == 200
    stock_replayed = next(
        item
        for item in client.get("/product/fetch_products").json()["data"]
        if item["id"] == product_id
    )["quantity"]
    assert stock_replayed == stock_after

    # A signed but malformed body is acknowledged without raising.
    import hashlib
    import hmac

    raw = b"{not-json"
    signature = hmac.new(
        store_harness.paystack_test_key.encode("utf-8"),
        raw,
        hashlib.sha512,
    ).hexdigest()
    malformed = client.post(
        "/product/paystack_webhook",
        content=raw,
        headers={"X-Paystack-Signature": signature, "Content-Type": "application/json"},
    )
    assert malformed.status_code == 200
    assert malformed.text == "OK"


# ═════════════════════════════════════════════════════════════
# ORDER MANAGEMENT STATE MACHINE
# ═════════════════════════════════════════════════════════════


def _paid_order(
    harness: StoreHarness,
    *,
    price: str = "1500.00",
    quantity: int = 1,
    delivery: str = "self_pickup",
) -> dict[str, Any]:
    """Create one paid order and return its checkout payload."""
    client = harness.client
    customer = harness.customer_headers
    product_id = _create_product(harness, quantity="20", price=price)
    added = client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": quantity},
    )
    assert added.status_code == 200, added.text
    checkout = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": delivery},
    )
    assert checkout.status_code == 200, checkout.text
    data = checkout.json()["data"]
    reference = data["reference"]
    expected_kobo = harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]
    harness.mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": expected_kobo,
            "currency": "NGN",
        },
    }
    verified = client.post(
        "/product/verify_payment",
        headers=customer,
        json={"reference": reference},
    )
    assert verified.status_code == 200, verified.text
    return cast(dict[str, Any], verified.json()["data"])


def test_order_status_state_machine_rejects_invalid_transitions(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    admin = store_harness.admin_headers
    pickup = _paid_order(store_harness, delivery="self_pickup")
    order_id = pickup["order_id"]

    invalid_id = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": 0, "status": "shipped"},
    )
    assert invalid_id.status_code == 400
    assert _error_message(invalid_id) == "Invalid order."

    invalid_status = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "refunded"},
    )
    assert invalid_status.status_code == 400
    assert _error_message(invalid_status) == "Invalid status."

    unknown_order = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": 999999, "status": "shipped"},
    )
    assert unknown_order.status_code == 404
    assert _error_message(unknown_order) == "Order not found."

    skip_shipping = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "delivered"},
    )
    assert skip_shipping.status_code == 400
    assert _error_message(skip_shipping) == "Order must be shipped before it can be completed."

    shipped = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "shipped", "note": "Collected by rider"},
    )
    assert shipped.status_code == 200, shipped.text
    assert shipped.json()["data"]["display_status"] == "Ready for Pickup"

    repeated = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "shipped"},
    )
    assert repeated.status_code == 400
    assert _error_message(repeated) == "Order is already marked as shipped."

    completed = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "delivered", "note": "Handed over"},
    )
    assert completed.status_code == 200, completed.text
    assert completed.json()["data"]["display_status"] == "Completed"

    after_completion = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "shipped"},
    )
    assert after_completion.status_code == 400
    assert _error_message(after_completion) == "This order has already been completed."

    assert len(store_harness.mailer.sent_emails) >= 2


def test_order_status_requires_rider_details_for_door_delivery(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    admin = store_harness.admin_headers

    address = client.post(
        "/product/add_address",
        headers=store_harness.customer_headers,
        json={
            "first_name": "Ada",
            "last_name": "Obi",
            "phone": "+2348022223333",
            "address": "4 Alumni Way",
            "state": "Lagos",
            "area": "Mainland",
        },
    )
    assert address.status_code == 201, address.text
    address_id = address.json()["address_id"]

    product_id = _create_product(store_harness, quantity="20", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=store_harness.customer_headers,
        json={"product_id": product_id, "quantity": 1},
    )
    checkout = client.post(
        "/product/initiate_checkout",
        headers=store_harness.customer_headers,
        json={"delivery_type": "door_delivery", "address_id": address_id},
    )
    assert checkout.status_code == 200, checkout.text
    checkout_data = checkout.json()["data"]
    reference = checkout_data["reference"]
    expected_kobo = store_harness.mock_paystack.initialized_transactions[-1]["amount_kobo"]
    store_harness.mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": expected_kobo,
            "currency": "NGN",
        },
    }
    verified = client.post(
        "/product/verify_payment",
        headers=store_harness.customer_headers,
        json={"reference": reference},
    )
    assert verified.status_code == 200, verified.text
    order_id = verified.json()["data"]["order_id"]

    missing_rider = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order_id, "status": "shipped"},
    )
    assert missing_rider.status_code == 400
    assert _error_message(missing_rider) == "Rider details are required for door delivery."

    shipped = client.post(
        "/product/update_order_status",
        headers=admin,
        json={
            "order_id": order_id,
            "status": "shipped",
            "rider_details": "Rider Musa — 0803 000 0000",
        },
    )
    assert shipped.status_code == 200, shipped.text
    assert shipped.json()["data"]["display_status"] == "Out for Delivery"
    assert shipped.json()["data"]["rider_details"] == "Rider Musa — 0803 000 0000"


def test_order_status_survives_a_mail_provider_failure(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    admin = store_harness.admin_headers
    order = _paid_order(store_harness, delivery="self_pickup")

    def _boom(*_args: Any, **_kwargs: Any) -> None:
        raise RuntimeError("synthetic SMTP outage")

    store_harness.mailer.send_order_status_email = _boom  # type: ignore[method-assign]
    response = client.post(
        "/product/update_order_status",
        headers=admin,
        json={"order_id": order["order_id"], "status": "shipped"},
    )
    assert response.status_code == 200, response.text
    assert response.json()["data"]["status"] == "shipped"


def test_order_status_requires_store_authority(store_harness: StoreHarness) -> None:
    client = store_harness.client
    order = _paid_order(store_harness, delivery="self_pickup")

    denied = client.post(
        "/product/update_order_status",
        headers=store_harness.customer_headers,
        json={"order_id": order["order_id"], "status": "shipped"},
    )
    assert denied.status_code == 403
    assert _error_message(denied) == "You are not authorized to perform this action."

    allowed = client.post(
        "/product/update_order_status",
        headers=store_harness.storekeeper_headers,
        json={"order_id": order["order_id"], "status": "shipped"},
    )
    assert allowed.status_code == 200, allowed.text


# ═════════════════════════════════════════════════════════════
# ORDER VISIBILITY
# ═════════════════════════════════════════════════════════════


def test_view_order_details_enforces_ownership_and_visibility(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers
    order = _paid_order(store_harness, delivery="self_pickup")

    blank = client.post("/product/view_order_details", headers=customer, json={"order_number": ""})
    assert blank.status_code == 400
    assert _error_message(blank) == "Invalid order."

    unknown = client.post(
        "/product/view_order_details",
        headers=customer,
        json={"order_number": "ORD-NOT-A-REAL-ORDER"},
    )
    assert unknown.status_code == 404
    assert _error_message(unknown) == "Order not found."

    foreign = client.post(
        "/product/view_order_details",
        headers=store_harness.customer2_headers,
        json={"order_number": order["order_number"]},
    )
    assert foreign.status_code == 404
    assert _error_message(foreign) == "Order not found."

    own = client.post(
        "/product/view_order_details",
        headers=customer,
        json={"order_number": order["order_number"]},
    )
    assert own.status_code == 200, own.text
    assert own.json()["data"]["status"] == "New Order"
    assert own.json()["data"]["delivery_type"] == "self_pickup"
    assert own.json()["data"]["items"]


def test_manage_order_details_is_store_admin_only(store_harness: StoreHarness) -> None:
    client = store_harness.client
    order = _paid_order(store_harness, delivery="self_pickup")

    denied = client.post(
        "/product/manage_order_details",
        headers=store_harness.customer_headers,
        json={"order_number": order["order_number"]},
    )
    assert denied.status_code == 403

    blank = client.post(
        "/product/manage_order_details",
        headers=store_harness.admin_headers,
        json={"order_number": "  "},
    )
    assert blank.status_code == 400
    assert _error_message(blank) == "Invalid order."

    unknown = client.post(
        "/product/manage_order_details",
        headers=store_harness.admin_headers,
        json={"order_number": "ORD-UNKNOWN"},
    )
    assert unknown.status_code == 404

    managed = client.post(
        "/product/manage_order_details",
        headers=store_harness.admin_headers,
        json={"order_number": order["order_number"]},
    )
    assert managed.status_code == 200, managed.text
    payload = managed.json()["data"]
    assert payload["customer_email"] == store_harness.customer_email
    assert payload["status"] == "New Order"


def test_order_management_requires_store_authority(store_harness: StoreHarness) -> None:
    client = store_harness.client
    _paid_order(store_harness, delivery="self_pickup")

    denied = client.get("/product/order_management", headers=store_harness.customer_headers)
    assert denied.status_code == 403
    assert _error_message(denied) == "You are not authorized to perform this action."

    allowed = client.get("/product/order_management", headers=store_harness.admin_headers)
    assert allowed.status_code == 200, allowed.text
    assert allowed.json()["data"]


def test_fetch_orders_excludes_pending_and_cancelled_orders(
    store_harness: StoreHarness,
) -> None:
    client = store_harness.client
    customer = store_harness.customer_headers

    product_id = _create_product(store_harness, quantity="20", price="1500.00")
    client.post(
        "/product/add_to_cart",
        headers=customer,
        json={"product_id": product_id, "quantity": 1},
    )
    pending = client.post(
        "/product/initiate_checkout",
        headers=customer,
        json={"delivery_type": "self_pickup"},
    )
    assert pending.status_code == 200, pending.text
    pending_number = pending.json()["data"]["order_number"]

    history = client.get("/product/fetch_orders", headers=customer).json()["data"]
    assert all(order["order_number"] != pending_number for order in history)

    # A pending order is also invisible to customer detail lookup and admin detail lookup.
    view = client.post(
        "/product/view_order_details",
        headers=customer,
        json={"order_number": pending_number},
    )
    assert view.status_code == 404
    manage = client.post(
        "/product/manage_order_details",
        headers=store_harness.admin_headers,
        json={"order_number": pending_number},
    )
    assert manage.status_code == 404
