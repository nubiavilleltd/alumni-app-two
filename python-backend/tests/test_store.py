"""Contract, authorization, integration, and payment tests for Store & Orders (Goal 8)."""

from __future__ import annotations

import hashlib
import hmac
import json
import os
import time
import uuid
from collections.abc import Iterator
from dataclasses import dataclass, field
from decimal import Decimal
from io import BytesIO
from pathlib import Path
from typing import Any

import pytest
from fastapi.testclient import TestClient
from PIL import Image
from sqlalchemy import create_engine, delete, insert

from app.core.config import Settings
from app.core.security import TokenService
from app.integrations.uploads import ProductStorage, prepare_avatar
from app.main import create_app
from app.models.generated import (
    CartItems,
    Carts,
    DeliveryZones,
    OrderItems,
    Orders,
    ProductImages,
    Products,
    ProductVariants,
    UserAddresses,
    Users,
)


def _create_sample_png() -> bytes:
    """Generate a minimal 10x10 PNG in memory."""
    buf = BytesIO()
    img = Image.new("RGB", (10, 10), color="green")
    img.save(buf, format="PNG")
    return buf.getvalue()


class MockPaystackClient:
    """In-memory mock Paystack client for reliable testing."""

    def __init__(self, secret_key: str) -> None:
        self.secret_key = secret_key
        self.initialized_transactions: list[dict[str, Any]] = []
        self.verified_responses: dict[str, dict[str, Any]] = {}

    def initialize_transaction(
        self,
        *,
        email: str,
        amount_kobo: int,
        reference: str,
        callback_url: str | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        self.initialized_transactions.append(
            {
                "email": email,
                "amount_kobo": amount_kobo,
                "reference": reference,
                "callback_url": callback_url,
                "metadata": metadata,
            }
        )
        return {
            "authorization_url": f"https://checkout.paystack.com/{reference}",
            "access_code": f"acc_{reference}",
            "reference": reference,
        }

    def verify_transaction(self, reference: str) -> dict[str, Any]:
        if reference in self.verified_responses:
            return self.verified_responses[reference]
        return {
            "status": True,
            "data": {
                "status": "success",
                "reference": reference,
                "amount": 100000,
                "gateway_response": "Successful",
                "channel": "card",
                "currency": "NGN",
                "paid_at": "2026-09-17T20:00:00.000Z",
            },
        }

    def verify_webhook_signature(self, raw_body: bytes, signature_header: str) -> bool:
        expected = hmac.new(self.secret_key.encode("utf-8"), raw_body, hashlib.sha512).hexdigest()
        return hmac.compare_digest(expected, signature_header)


class RecordingMailer:
    """In-memory mailer to verify order status email dispatches."""

    def __init__(self) -> None:
        self.sent_emails: list[dict[str, Any]] = []

    def send_order_status_email(
        self,
        recipient: str,
        display_name: str = "",
        order_number: str = "",
        status: str = "",
        delivery_type: str = "",
        note: str = "",
        rider_details: str = "",
        *,
        first_name: str = "",
        details: dict[str, Any] | None = None,
    ) -> None:
        self.sent_emails.append(
            {
                "recipient": recipient,
                "display_name": display_name or first_name,
                "order_number": order_number,
                "status": status,
                "delivery_type": delivery_type,
                "note": note,
                "rider_details": rider_details,
                "details": details or {"rider_details": rider_details, "note": note},
            }
        )

    def send_verification_email(self, *args: Any, **kwargs: Any) -> None:
        pass

    def send_password_reset_email(self, *args: Any, **kwargs: Any) -> None:
        pass


# ═════════════════════════════════════════════════════════════
# OPENAPI & SCHEMA TESTS
# ═════════════════════════════════════════════════════════════


def test_store_openapi_route_registration(auth_settings: Settings) -> None:
    """Verify all 24 Goal 8 routes are registered in OpenAPI with correct methods."""
    app = create_app(auth_settings)
    openapi = app.openapi()
    paths = openapi["paths"]

    # 1. Products (5 routes)
    assert "/product/fetch_products" in paths
    assert "get" in paths["/product/fetch_products"]
    assert "post" in paths["/product/fetch_products"]
    assert "/product/pin_product_item" in paths
    assert "post" in paths["/product/pin_product_item"]
    assert "/product/add_product" in paths
    assert "post" in paths["/product/add_product"]
    assert "/product/edit_product" in paths
    assert "post" in paths["/product/edit_product"]
    assert "/product/delete_product" in paths
    assert "post" in paths["/product/delete_product"]
    assert "delete" in paths["/product/delete_product"]

    # 2. Cart (5 routes)
    assert "/product/fetch_cart" in paths
    assert "get" in paths["/product/fetch_cart"]
    assert "post" in paths["/product/fetch_cart"]
    assert "/product/add_to_cart" in paths
    assert "post" in paths["/product/add_to_cart"]
    assert "/product/update_cart" in paths
    assert "post" in paths["/product/update_cart"]
    assert "/product/remove_from_cart" in paths
    assert "post" in paths["/product/remove_from_cart"]
    assert "/product/clear_cart" in paths
    assert "post" in paths["/product/clear_cart"]

    # 3. Addresses & Delivery Zones (6 routes)
    assert "/product/add_address" in paths
    assert "post" in paths["/product/add_address"]
    assert "/product/fetch_addresses" in paths
    assert "get" in paths["/product/fetch_addresses"]
    assert "post" in paths["/product/fetch_addresses"]
    assert "/product/edit_address" in paths
    assert "post" in paths["/product/edit_address"]
    assert "/product/delete_address" in paths
    assert "post" in paths["/product/delete_address"]
    assert "delete" in paths["/product/delete_address"]
    assert "/product/set_default_address" in paths
    assert "post" in paths["/product/set_default_address"]
    assert "/product/fetch_delivery_zones" in paths
    assert "get" in paths["/product/fetch_delivery_zones"]
    assert "post" in paths["/product/fetch_delivery_zones"]

    # 4. Checkout, Payments, Webhook & Orders (8 routes)
    assert "/product/initiate_checkout" in paths
    assert "post" in paths["/product/initiate_checkout"]
    assert "/product/verify_payment" in paths
    assert "post" in paths["/product/verify_payment"]
    assert "/product/paystack_webhook" in paths
    assert "post" in paths["/product/paystack_webhook"]
    assert "/api/paystack/webhook" in paths
    assert "post" in paths["/api/paystack/webhook"]
    assert "/product/fetch_orders" in paths
    assert "get" in paths["/product/fetch_orders"]
    assert "post" in paths["/product/fetch_orders"]
    assert "/product/view_order_details" in paths
    assert "post" in paths["/product/view_order_details"]
    assert "/product/order_management" in paths
    assert "get" in paths["/product/order_management"]
    assert "post" in paths["/product/order_management"]
    assert "/product/manage_order_details" in paths
    assert "post" in paths["/product/manage_order_details"]
    assert "/product/update_order_status" in paths
    assert "post" in paths["/product/update_order_status"]


def test_store_mutations_require_authentication(auth_settings: Settings) -> None:
    """Unauthenticated calls to protected endpoints must fail with 401."""
    app = create_app(auth_settings)
    with TestClient(app) as client:
        # Product Admin
        assert client.post("/product/pin_product_item", json={}).status_code == 401
        assert client.post("/product/add_product", data={}).status_code == 401
        assert client.post("/product/edit_product", data={}).status_code == 401
        assert client.post("/product/delete_product", json={}).status_code == 401

        # Cart
        assert client.get("/product/fetch_cart").status_code == 401
        assert client.post("/product/add_to_cart", json={}).status_code == 401
        assert client.post("/product/update_cart", json={}).status_code == 401
        assert client.post("/product/remove_from_cart", json={}).status_code == 401
        assert client.post("/product/clear_cart").status_code == 401

        # Addresses
        assert client.post("/product/add_address", json={}).status_code == 401
        assert client.get("/product/fetch_addresses").status_code == 401
        assert client.post("/product/edit_address", json={}).status_code == 401
        assert client.post("/product/delete_address", json={}).status_code == 401
        assert client.post("/product/set_default_address", json={}).status_code == 401

        # Checkout & Orders
        assert client.post("/product/initiate_checkout", json={}).status_code == 401
        assert client.post("/product/verify_payment", json={}).status_code == 401
        assert client.get("/product/fetch_orders").status_code == 401
        assert client.post("/product/view_order_details", json={}).status_code == 401
        assert client.get("/product/order_management").status_code == 401
        assert client.post("/product/manage_order_details", json={}).status_code == 401
        assert client.post("/product/update_order_status", json={}).status_code == 401


def test_product_storage_lifecycle(tmp_path: Path) -> None:
    """Validate ProductStorage saves, resolves safe paths, and deletes files physically."""
    png_bytes = _create_sample_png()
    prepared = prepare_avatar("store_product.png", png_bytes)

    storage = ProductStorage(tmp_path)
    stored = storage.save(user_id=1, avatar=prepared)
    assert stored.relative_path.startswith("uploads/products/")
    assert stored.filename.endswith(".png")

    disk_path = tmp_path / "products" / stored.filename
    assert disk_path.is_file()

    storage.delete(stored)
    assert not disk_path.exists()


# ═════════════════════════════════════════════════════════════
# DATABASE INTEGRATION HARNESS
# ═════════════════════════════════════════════════════════════


def _create_test_user(
    engine: Any,
    *,
    user_role: str = "admin",
    active: int = 1,
) -> tuple[int, str]:
    """Insert a synthetic user for store tests."""
    unique = uuid.uuid4().hex[:8]
    email = f"store-{user_role}-{unique}@example.com"
    with engine.begin() as conn:
        result = conn.execute(
            insert(Users).values(
                chapter_id=1,
                ip_address="127.0.0.1",
                username=email,
                email=email,
                password="test_password_hash",  # noqa: S106
                has_password=1,
                onboarding_completion=1,
                nick_name=f"StoreTester{unique}",
                state="Lagos",
                country="Nigeria",
                created_on=int(time.time()),
                userAccessCode=f"STR-{unique}",
                profile_status="active",
                voucher="",
                resetKey="",
                first_name="Store",
                last_name="Tester",
                fullname=f"Store Tester {user_role.capitalize()}",
                phone="+2348011112222",
                city="Lagos",
                active=active,
                user_role=user_role,
                is_approved=1,
                email_verified=1,
            )
        )
        user_id = int(result.inserted_primary_key[0])
    return user_id, email


@dataclass
class StoreHarness:
    """Complete test harness with test client, multiple actors, and database engine."""

    client: TestClient
    admin_headers: dict[str, str]
    admin_user_id: int
    storekeeper_headers: dict[str, str]
    storekeeper_user_id: int
    customer_headers: dict[str, str]
    customer_user_id: int
    customer_email: str
    customer2_headers: dict[str, str]
    customer2_user_id: int
    settings: Settings
    paystack_test_key: str
    upload_root: Path
    engine: Any
    mock_paystack: MockPaystackClient
    mailer: RecordingMailer
    created_user_ids: list[int] = field(default_factory=list)


@pytest.fixture
def store_harness(rsa_pem_pair: tuple[str, str], tmp_path: Path) -> Iterator[StoreHarness]:
    """Provide an isolated test client with admin and customer users against MariaDB."""
    database_url = os.getenv("ALUMNI_TEST_DATABASE_URL")
    if not database_url:
        pytest.skip("ALUMNI_TEST_DATABASE_URL is not configured")

    private_pem, public_pem = rsa_pem_pair
    upload_root = tmp_path / "uploads"
    upload_root.mkdir(parents=True, exist_ok=True)

    paystack_test_key = uuid.uuid4().hex
    settings = Settings(
        environment="test",
        database_url=database_url,
        jwt_signing_key=private_pem,
        jwt_verification_key=public_pem,
        public_base_url="https://alumni.example.test/",
        frontend_base_url="https://frontend.example.test/",
        upload_root=upload_root,
        paystack_secret_key=paystack_test_key,
    )
    engine = create_engine(database_url, pool_pre_ping=True)

    # Create test actors
    admin_id, admin_email = _create_test_user(engine, user_role="admin")
    storekeeper_id, storekeeper_email = _create_test_user(engine, user_role="storekeeper admin")
    customer_id, customer_email = _create_test_user(engine, user_role="alumni")
    customer2_id, customer2_email = _create_test_user(engine, user_role="alumni")

    token_service = TokenService(settings)
    admin_token = token_service.issue(
        {
            "id": admin_id,
            "email": admin_email,
            "user_role": "admin",
            "fullname": "Store Admin",
        }
    ).access_token
    storekeeper_token = token_service.issue(
        {
            "id": storekeeper_id,
            "email": storekeeper_email,
            "user_role": "storekeeper admin",
            "fullname": "Storekeeper Admin",
        }
    ).access_token
    customer_token = token_service.issue(
        {
            "id": customer_id,
            "email": customer_email,
            "user_role": "alumni",
            "fullname": "Store Customer",
        }
    ).access_token
    customer2_token = token_service.issue(
        {
            "id": customer2_id,
            "email": customer2_email,
            "user_role": "alumni",
            "fullname": "Second Customer",
        }
    ).access_token

    mock_paystack = MockPaystackClient(secret_key=paystack_test_key)
    recording_mailer = RecordingMailer()

    app = create_app(settings)
    app.state.paystack_client = mock_paystack
    app.state.mailer = recording_mailer

    # Seed delivery zones for testing
    with engine.begin() as conn:
        conn.execute(
            insert(DeliveryZones).values(
                [
                    {
                        "state": "Lagos",
                        "area": "Mainland",
                        "fee": Decimal("3000.00"),
                    },
                    {
                        "state": "Lagos",
                        "area": "Island",
                        "fee": Decimal("3500.00"),
                    },
                    {
                        "state": "Lagos",
                        "area": None,
                        "fee": Decimal("2500.00"),
                    },
                    {
                        "state": "Abuja",
                        "area": None,
                        "fee": Decimal("5000.00"),
                    },
                ]
            )
        )

    with TestClient(app) as client:
        harness = StoreHarness(
            client=client,
            admin_headers={"Authorization": f"Bearer {admin_token}"},
            admin_user_id=admin_id,
            storekeeper_headers={"Authorization": f"Bearer {storekeeper_token}"},
            storekeeper_user_id=storekeeper_id,
            customer_headers={"Authorization": f"Bearer {customer_token}"},
            customer_user_id=customer_id,
            customer_email=customer_email,
            customer2_headers={"Authorization": f"Bearer {customer2_token}"},
            customer2_user_id=customer2_id,
            settings=settings,
            paystack_test_key=paystack_test_key,
            upload_root=upload_root,
            engine=engine,
            mock_paystack=mock_paystack,
            mailer=recording_mailer,
            created_user_ids=[admin_id, storekeeper_id, customer_id, customer2_id],
        )
        try:
            yield harness
        finally:
            # Clean up all created database rows
            with engine.begin() as conn:
                conn.execute(delete(OrderItems))
                conn.execute(delete(Orders))
                conn.execute(delete(CartItems))
                conn.execute(delete(Carts))
                conn.execute(delete(UserAddresses))
                conn.execute(delete(ProductImages))
                conn.execute(delete(ProductVariants))
                conn.execute(delete(Products))
                conn.execute(
                    delete(DeliveryZones).where(DeliveryZones.state.in_(["Lagos", "Abuja"]))
                )
                conn.execute(delete(Users).where(Users.id.in_(harness.created_user_ids)))


# ═════════════════════════════════════════════════════════════
# PRODUCTS & INVENTORY TESTS
# ═════════════════════════════════════════════════════════════


def test_product_catalog_crud_and_pinning(store_harness: StoreHarness) -> None:
    """Test full product lifecycle: create, list, pin limit, edit, and delete."""
    client = store_harness.client
    admin_hdr = store_harness.admin_headers
    cust_hdr = store_harness.customer_headers

    # 1. Admin adds a product with variants and image
    png_data = _create_sample_png()
    variants = [
        {"color": "Navy", "size": "M", "quantity": 10},
        {"color": "Navy", "size": "L", "quantity": 15},
    ]
    resp = client.post(
        "/product/add_product",
        headers=admin_hdr,
        data={
            "product_name": "Alumni Premium Hoodie",
            "category": "Apparel",
            "price": "15000.00",
            "description": "High quality embroidered hoodie",
            "has_size": "1",
            "has_color": "1",
            "quantity": "null",
            "variants": json.dumps(variants),
            "spotlight_index": "0",
        },
        files=[("images", ("hoodie.png", png_data, "image/png"))],
    )
    assert resp.status_code == 201, resp.text
    prod_id = resp.json()["data"]["product_id"]
    assert prod_id > 0

    # 2. Customer lists products -> verifies product exists with variants and images
    list_resp = client.get("/product/fetch_products")
    assert list_resp.status_code == 200
    catalog = list_resp.json()
    assert catalog["status"] is True
    assert catalog["meta"]["total_products"] >= 1
    found = next((p for p in catalog["data"] if p["id"] == prod_id), None)
    assert found is not None
    assert found["product_name"] == "Alumni Premium Hoodie"
    assert len(found["images"]) == 1
    assert found["images"][0]["is_spotlight"] is True
    assert len(found["variants"]) == 2

    # 3. Pinning: Admin pins product
    pin_resp = client.post(
        "/product/pin_product_item",
        headers=admin_hdr,
        json={"product_id": prod_id, "pin_item": True},
    )
    assert pin_resp.status_code == 200
    assert pin_resp.json()["data"]["pin_item"] == 1

    # Verify meta count reflects pinned product
    list_resp2 = client.get("/product/fetch_products")
    assert list_resp2.json()["meta"]["total_pinned"] == 1

    # 4. Customer attempting to pin product gets 403 Forbidden
    cust_pin = client.post(
        "/product/pin_product_item",
        headers=cust_hdr,
        json={"product_id": prod_id, "pin_item": False},
    )
    assert cust_pin.status_code == 403

    # 5. Pin limit test: Create 4 more products and verify maximum 4 pinned items allowed
    extra_prod_ids: list[int] = []
    for i in range(4):
        create_res = client.post(
            "/product/add_product",
            headers=admin_hdr,
            data={
                "product_name": f"Pin Test Item {i}",
                "category": "Accessories",
                "price": "2000.00",
                "description": "Test pin limit",
                "has_size": "0",
                "has_color": "0",
                "quantity": "10",
                "spotlight_index": "0",
            },
            files=[("images", (f"pin_{i}.png", png_data, "image/png"))],
        )
        assert create_res.status_code == 201
        extra_prod_ids.append(create_res.json()["data"]["product_id"])

    # Pin items 0, 1, 2 (now 4 items pinned total including first product)
    for p_id in extra_prod_ids[:3]:
        p_res = client.post(
            "/product/pin_product_item",
            headers=admin_hdr,
            json={"product_id": p_id, "pin_item": True},
        )
        assert p_res.status_code == 200

    # Attempting to pin the 5th item fails with 400
    p_fail = client.post(
        "/product/pin_product_item",
        headers=admin_hdr,
        json={"product_id": extra_prod_ids[3], "pin_item": True},
    )
    assert p_fail.status_code == 400
    fail_data = p_fail.json()
    err_msg = fail_data.get("error", {}).get("message") or fail_data.get("message") or ""
    assert "maximum of 4" in err_msg.lower()

    # 6. Admin edits product
    edit_resp = client.post(
        "/product/edit_product",
        headers=admin_hdr,
        data={
            "product_id": str(prod_id),
            "product_name": "Alumni Premium Hoodie (Updated)",
            "category": "Apparel",
            "price": "16500.00",
            "description": "Updated description",
            "has_size": "1",
            "has_color": "1",
            "variants": json.dumps([{"color": "Navy", "size": "XL", "quantity": 25}]),
        },
    )
    assert edit_resp.status_code == 200

    # Check updated product in list
    check_list = client.get("/product/fetch_products")
    updated_p = next(p for p in check_list.json()["data"] if p["id"] == prod_id)
    assert updated_p["product_name"] == "Alumni Premium Hoodie (Updated)"
    assert float(updated_p["price"]) == 16500.00

    # 7. Admin deletes product
    del_resp = client.post(
        "/product/delete_product",
        headers=admin_hdr,
        json={"product_id": prod_id},
    )
    assert del_resp.status_code == 200

    # Verify product is no longer active in list
    after_del = client.get("/product/fetch_products")
    assert not any(p["id"] == prod_id for p in after_del.json()["data"])


# ═════════════════════════════════════════════════════════════
# CART & USER ISOLATION TESTS
# ═════════════════════════════════════════════════════════════


def test_cart_operations_and_user_isolation(store_harness: StoreHarness) -> None:
    """Test cart lifecycle and strict isolation between different customers."""
    client = store_harness.client
    admin_hdr = store_harness.admin_headers
    cust1_hdr = store_harness.customer_headers
    cust2_hdr = store_harness.customer2_headers

    # Create a test product
    p_resp = client.post(
        "/product/add_product",
        headers=admin_hdr,
        data={
            "product_name": "Alumni Mug",
            "category": "Souvenirs",
            "price": "4500.00",
            "description": "Ceramic coffee mug",
            "has_size": "0",
            "has_color": "0",
            "quantity": "50",
            "spotlight_index": "0",
        },
        files=[("images", ("mug.png", _create_sample_png(), "image/png"))],
    )
    assert p_resp.status_code == 201
    prod_id = p_resp.json()["data"]["product_id"]

    # 1. Customer 1 fetches initially empty cart
    c1_cart = client.get("/product/fetch_cart", headers=cust1_hdr)
    assert c1_cart.status_code == 200
    cart_data = c1_cart.json()["data"]
    assert float(cart_data["subtotal"]) == 0.0
    assert len(cart_data["items"]) == 0

    # 2. Customer 1 adds item to cart
    add_resp = client.post(
        "/product/add_to_cart",
        headers=cust1_hdr,
        json={"product_id": prod_id, "quantity": 2},
    )
    assert add_resp.status_code == 200
    updated_cart = add_resp.json()["data"]
    assert len(updated_cart["items"]) == 1
    assert int(updated_cart["items"][0]["quantity"]) == 2
    assert updated_cart["items"][0]["product_id"] == prod_id
    cart_item_id = int(updated_cart["items"][0]["cart_item_id"])

    # 3. Customer 2 fetches cart -> must be completely empty (User Isolation!)
    c2_cart = client.get("/product/fetch_cart", headers=cust2_hdr)
    assert c2_cart.status_code == 200
    assert len(c2_cart.json()["data"]["items"]) == 0

    # 4. Customer 2 attempts to modify Customer 1's cart item -> 404/403
    bad_update = client.post(
        "/product/update_cart",
        headers=cust2_hdr,
        json={"cart_item_id": cart_item_id, "quantity": 10},
    )
    assert bad_update.status_code in (403, 404)

    # 5. Customer 1 updates quantity to 4
    u_resp = client.post(
        "/product/update_cart",
        headers=cust1_hdr,
        json={"cart_item_id": cart_item_id, "quantity": 4},
    )
    assert u_resp.status_code == 200
    assert int(u_resp.json()["data"]["items"][0]["quantity"]) == 4
    assert float(u_resp.json()["data"]["subtotal"]) == 18000.0  # 4 * 4500

    # 6. Customer 1 removes item from cart
    rem_resp = client.post(
        "/product/remove_from_cart",
        headers=cust1_hdr,
        json={"cart_item_id": cart_item_id},
    )
    assert rem_resp.status_code == 200
    assert len(rem_resp.json()["data"]["items"]) == 0

    # 7. Add again and test clear_cart
    client.post(
        "/product/add_to_cart",
        headers=cust1_hdr,
        json={"product_id": prod_id, "quantity": 1},
    )
    clear_resp = client.post("/product/clear_cart", headers=cust1_hdr)
    assert clear_resp.status_code == 200
    assert len(clear_resp.json()["data"]["items"]) == 0


# ═════════════════════════════════════════════════════════════
# ADDRESSES & DELIVERY ZONES TESTS
# ═════════════════════════════════════════════════════════════


def test_addresses_and_delivery_zones(store_harness: StoreHarness) -> None:
    """Test address management, default toggling, and delivery zone queries."""
    client = store_harness.client
    cust_hdr = store_harness.customer_headers

    # 1. Fetch delivery zones (public)
    dz_resp = client.get("/product/fetch_delivery_zones")
    assert dz_resp.status_code == 200
    zones = dz_resp.json()["data"]
    lagos = next((z for z in zones if z["state"] == "Lagos"), None)
    assert lagos is not None
    assert len(lagos["areas"]) >= 2

    # 2. Add first address (automatically becomes default)
    addr1_resp = client.post(
        "/product/add_address",
        headers=cust_hdr,
        json={
            "first_name": "Alumni",
            "last_name": "Member",
            "phone": "08012345678",
            "address": "12 Marina Street",
            "state": "Lagos",
            "area": "Island",
        },
    )
    assert addr1_resp.status_code == 201
    addr1_id = addr1_resp.json()["address_id"]
    addr1_data = addr1_resp.json()["data"][0]
    assert addr1_data["is_default"] is True

    # 3. Add second address marked as default -> automatically un-defaults first address
    addr2_resp = client.post(
        "/product/add_address",
        headers=cust_hdr,
        json={
            "first_name": "Alumni",
            "last_name": "Work",
            "phone": "08087654321",
            "address": "45 Ikeja Way",
            "state": "Lagos",
            "area": "Mainland",
            "is_default": "1",
        },
    )
    assert addr2_resp.status_code == 201
    addr2_id = addr2_resp.json()["address_id"]

    # Fetch addresses and verify statuses
    addrs_resp = client.get("/product/fetch_addresses", headers=cust_hdr)
    addrs = addrs_resp.json()["data"]
    assert len(addrs) == 2
    a1 = next(a for a in addrs if a["id"] == addr1_id)
    a2 = next(a for a in addrs if a["id"] == addr2_id)
    assert a1["is_default"] is False
    assert a2["is_default"] is True

    # 4. Edit second address
    edit_addr = client.post(
        "/product/edit_address",
        headers=cust_hdr,
        json={
            "id": addr2_id,
            "first_name": "Alumni",
            "last_name": "Work (Updated)",
            "phone": "08087654321",
            "address": "45 Ikeja Way, 2nd Floor",
            "state": "Lagos",
            "area": "Mainland",
        },
    )
    assert edit_addr.status_code == 201

    # 5. Set first address back as default
    set_def = client.post(
        "/product/set_default_address",
        headers=cust_hdr,
        json={"address_id": addr1_id},
    )
    assert set_def.status_code == 200

    # Verify first is now default
    addrs2 = client.get("/product/fetch_addresses", headers=cust_hdr).json()["data"]
    assert next(a for a in addrs2 if a["id"] == addr1_id)["is_default"] is True

    # 6. Delete second address
    del_addr = client.post(
        "/product/delete_address",
        headers=cust_hdr,
        json={"address_id": addr2_id},
    )
    assert del_addr.status_code == 200
    remaining = client.get("/product/fetch_addresses", headers=cust_hdr).json()["data"]
    assert len(remaining) == 1
    assert remaining[0]["id"] == addr1_id


# ═════════════════════════════════════════════════════════════
# CHECKOUT, PAYMENT VERIFICATION & STOCK DEDUCTION TESTS
# ═════════════════════════════════════════════════════════════


def test_checkout_and_payment_verification(store_harness: StoreHarness) -> None:
    """Test full checkout flow: initiate, verify payment, stock deduction, idempotency."""
    client = store_harness.client
    admin_hdr = store_harness.admin_headers
    cust_hdr = store_harness.customer_headers
    mock_paystack = store_harness.mock_paystack

    # 1. Admin creates product with known stock (quantity=10)
    p_resp = client.post(
        "/product/add_product",
        headers=admin_hdr,
        data={
            "product_name": "Alumni Lapel Pin",
            "category": "Accessories",
            "price": "5000.00",
            "description": "Gold plated pin",
            "has_size": "0",
            "has_color": "0",
            "quantity": "10",
            "spotlight_index": "0",
        },
        files=[("images", ("pin.png", _create_sample_png(), "image/png"))],
    )
    assert p_resp.status_code == 201
    prod_id = p_resp.json()["data"]["product_id"]

    # 2. Customer adds address in Lagos / Mainland (shipping fee = 3000)
    addr_resp = client.post(
        "/product/add_address",
        headers=cust_hdr,
        json={
            "first_name": "John",
            "last_name": "Doe",
            "phone": "08012345678",
            "address": "10 Yaba Road",
            "state": "Lagos",
            "area": "Mainland",
            "is_default": "1",
        },
    )
    addr_id = addr_resp.json()["address_id"]

    # 3. Customer adds 2 pins to cart (subtotal = 2 * 5000 = 10,000)
    client.post(
        "/product/add_to_cart",
        headers=cust_hdr,
        json={"product_id": prod_id, "quantity": 2},
    )

    # 4. Customer initiates checkout
    # Expected: Subtotal 10,000 + Shipping 3,000 = Total 13,000 Naira = 1,300,000 kobo
    chk_resp = client.post(
        "/product/initiate_checkout",
        headers=cust_hdr,
        json={
            "delivery_type": "delivery",
            "address_id": addr_id,
            "callback_url": "https://frontend.example.test/store/confirmation",
        },
    )
    assert chk_resp.status_code == 200, chk_resp.text
    chk_data = chk_resp.json()["data"]
    assert chk_data["status"] is True
    reference = chk_data["reference"]
    order_number = chk_data["order_number"]
    assert float(chk_data["amount"]) == 13000.0
    assert float(chk_data["delivery_fee"]) == 3000.0

    # Verify mock Paystack received exact kobo amount
    assert len(mock_paystack.initialized_transactions) == 1
    init_tx = mock_paystack.initialized_transactions[0]
    assert init_tx["amount_kobo"] == 1300000
    assert init_tx["reference"] == reference

    # 5. Customer verifies payment
    # Configure mock Paystack to return matching amount in kobo
    mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": 1300000,
            "gateway_response": "Approved",
            "channel": "card",
            "currency": "NGN",
            "paid_at": "2026-09-17T21:00:00.000Z",
        },
    }

    v_resp = client.post(
        "/product/verify_payment",
        headers=cust_hdr,
        json={"reference": reference},
    )
    assert v_resp.status_code == 200, v_resp.text
    v_data = v_resp.json()["data"]
    assert v_data["status"] is True
    assert v_data["order_number"] == order_number
    assert v_data["payment_status"] == "paid"

    # 6. Verify stock deduction: 10 - 2 = 8
    p_check = client.get("/product/fetch_products")
    pin_prod = next(p for p in p_check.json()["data"] if p["id"] == prod_id)
    assert pin_prod["total_stock"] == 8

    # 7. Verify cart is marked checked_out and active cart is now empty
    cart_after = client.get("/product/fetch_cart", headers=cust_hdr)
    assert len(cart_after.json()["data"]["items"]) == 0

    # 8. Idempotency test: Call verify_payment again with same reference
    # Should succeed immediately without re-decrementing stock!
    v_retry = client.post(
        "/product/verify_payment",
        headers=cust_hdr,
        json={"reference": reference},
    )
    assert v_retry.status_code == 200
    assert v_retry.json()["data"]["status"] is True

    # Stock must remain 8
    p_check2 = client.get("/product/fetch_products")
    pin_prod2 = next(p for p in p_check2.json()["data"] if p["id"] == prod_id)
    assert pin_prod2["total_stock"] == 8


# ═════════════════════════════════════════════════════════════
# PAYSTACK WEBHOOK TESTS
# ═════════════════════════════════════════════════════════════


def test_paystack_webhook_processing(store_harness: StoreHarness) -> None:
    """Test Paystack webhook signature validation and idempotent background finalization."""
    client = store_harness.client
    admin_hdr = store_harness.admin_headers
    cust_hdr = store_harness.customer_headers

    # 1. Create product and place pending order via checkout
    p_resp = client.post(
        "/product/add_product",
        headers=admin_hdr,
        data={
            "product_name": "Alumni Cap",
            "category": "Accessories",
            "price": "3000.00",
            "description": "Baseball cap",
            "has_size": "0",
            "has_color": "0",
            "quantity": "20",
            "spotlight_index": "0",
        },
        files=[("images", ("cap.png", _create_sample_png(), "image/png"))],
    )
    assert p_resp.status_code == 201
    prod_id = p_resp.json()["data"]["product_id"]

    client.post(
        "/product/add_to_cart",
        headers=cust_hdr,
        json={"product_id": prod_id, "quantity": 3},
    )

    chk_resp = client.post(
        "/product/initiate_checkout",
        headers=cust_hdr,
        json={"delivery_type": "pickup"},
    )
    assert chk_resp.status_code == 200
    reference = chk_resp.json()["data"]["reference"]
    # 2. Build charge.success webhook payload (amount = 3 * 3000 = 9000 Naira = 900,000 kobo)
    webhook_body = json.dumps(
        {
            "event": "charge.success",
            "data": {
                "reference": reference,
                "status": "success",
                "amount": 900000,
                "gateway_response": "Successful",
                "channel": "card",
                "currency": "NGN",
                "paid_at": "2026-09-17T22:00:00.000Z",
            },
        }
    ).encode("utf-8")

    # 3. Webhook with invalid signature returns 401
    bad_sig = client.post(
        "/product/paystack_webhook",
        content=webhook_body,
        headers={"X-Paystack-Signature": "invalid_signature", "Content-Type": "application/json"},
    )
    assert bad_sig.status_code == 401

    # 4. Webhook with valid HMAC-SHA512 signature succeeds
    valid_signature = hmac.new(
        store_harness.paystack_test_key.encode("utf-8"), webhook_body, hashlib.sha512
    ).hexdigest()

    good_sig = client.post(
        "/product/paystack_webhook",
        content=webhook_body,
        headers={"X-Paystack-Signature": valid_signature, "Content-Type": "application/json"},
    )
    assert good_sig.status_code == 200
    assert good_sig.text == "OK"

    # Verify stock decremented: 20 - 3 = 17
    p_check = client.get("/product/fetch_products")
    cap_prod = next(p for p in p_check.json()["data"] if p["id"] == prod_id)
    assert cap_prod["total_stock"] == 17

    # 5. Replay webhook -> returns 200 and does NOT double decrement stock
    replay = client.post(
        "/product/paystack_webhook",
        content=webhook_body,
        headers={"X-Paystack-Signature": valid_signature, "Content-Type": "application/json"},
    )
    assert replay.status_code == 200
    p_check2 = client.get("/product/fetch_products")
    cap_prod2 = next(p for p in p_check2.json()["data"] if p["id"] == prod_id)
    assert cap_prod2["total_stock"] == 17


# ═════════════════════════════════════════════════════════════
# ORDERS & STATUS WORKFLOW TESTS
# ═════════════════════════════════════════════════════════════


def test_order_management_and_status_transitions(store_harness: StoreHarness) -> None:
    """Test customer order history, storekeeper management, and status state machine."""
    client = store_harness.client
    admin_hdr = store_harness.admin_headers
    storekeeper_hdr = store_harness.storekeeper_headers
    cust1_hdr = store_harness.customer_headers
    cust2_hdr = store_harness.customer2_headers
    mock_paystack = store_harness.mock_paystack
    mailer = store_harness.mailer

    # 1. Create and pay for an order as Customer 1
    p_resp = client.post(
        "/product/add_product",
        headers=admin_hdr,
        data={
            "product_name": "Alumni Polo Shirt",
            "category": "Apparel",
            "price": "8000.00",
            "description": "Classic pique polo",
            "has_size": "0",
            "has_color": "0",
            "quantity": "30",
            "spotlight_index": "0",
        },
        files=[("images", ("polo.png", _create_sample_png(), "image/png"))],
    )
    assert p_resp.status_code == 201
    prod_id = p_resp.json()["data"]["product_id"]

    client.post(
        "/product/add_to_cart",
        headers=cust1_hdr,
        json={"product_id": prod_id, "quantity": 1},
    )
    chk_resp = client.post(
        "/product/initiate_checkout",
        headers=cust1_hdr,
        json={"delivery_type": "pickup"},
    )
    reference = chk_resp.json()["data"]["reference"]
    order_number = chk_resp.json()["data"]["order_number"]

    mock_paystack.verified_responses[reference] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": reference,
            "amount": 800000,
            "gateway_response": "Successful",
            "channel": "card",
            "currency": "NGN",
            "paid_at": "2026-09-17T22:30:00.000Z",
        },
    }
    v_res = client.post(
        "/product/verify_payment",
        headers=cust1_hdr,
        json={"reference": reference},
    )
    assert v_res.status_code == 200

    # 2. Customer 1 fetches orders -> order is listed
    cust_orders = client.get("/product/fetch_orders", headers=cust1_hdr)
    assert cust_orders.status_code == 200
    orders_list = cust_orders.json()["data"]
    assert len(orders_list) >= 1
    assert any(o["order_number"] == order_number for o in orders_list)

    # 3. Customer 1 views order details
    detail_res = client.post(
        "/product/view_order_details",
        headers=cust1_hdr,
        json={"order_number": order_number},
    )
    assert detail_res.status_code == 200
    order_detail = detail_res.json()["data"]
    assert order_detail["order_number"] == order_number
    assert order_detail["status"] in ("paid", "New Order")
    order_id = int(order_detail["id"])

    # 4. Customer 2 cannot view Customer 1's order details -> 404
    c2_denied = client.post(
        "/product/view_order_details",
        headers=cust2_hdr,
        json={"order_number": order_number},
    )
    assert c2_denied.status_code == 404

    # 5. Customer 1 cannot access store order management -> 403 Forbidden
    cust_admin = client.get("/product/order_management", headers=cust1_hdr)
    assert cust_admin.status_code == 403

    # 6. Storekeeper admin accesses order management -> sees order
    sk_mgmt = client.get("/product/order_management", headers=storekeeper_hdr)
    assert sk_mgmt.status_code == 200
    sk_orders = sk_mgmt.json()["data"]
    assert any(o["order_number"] == order_number for o in sk_orders)

    # 7. Storekeeper views manage_order_details
    sk_detail = client.post(
        "/product/manage_order_details",
        headers=storekeeper_hdr,
        json={"order_number": order_number},
    )
    assert sk_detail.status_code == 200
    assert sk_detail.json()["data"]["order_number"] == order_number

    # 8. Storekeeper updates order status:
    # Invalid transition: paid -> delivered directly without shipping -> 400
    invalid_deliv = client.post(
        "/product/update_order_status",
        headers=storekeeper_hdr,
        json={"order_id": order_id, "status": "delivered", "note": "Premature"},
    )
    assert invalid_deliv.status_code == 400

    # Transition: paid -> shipped (with rider details)
    up_ship = client.post(
        "/product/update_order_status",
        headers=storekeeper_hdr,
        json={
            "order_id": order_id,
            "status": "shipped",
            "rider_details": "Rider: Tunde, Phone: 08099887766",
            "note": "Package dispatched",
        },
    )
    assert up_ship.status_code == 200
    assert up_ship.json()["data"]["status"] == "shipped"

    # Verify email was dispatched for pickup
    assert len(mailer.sent_emails) >= 1
    latest_email = mailer.sent_emails[-1]
    assert latest_email["order_number"] == order_number
    assert latest_email["status"] == "pickup"
    assert latest_email["delivery_type"] == "self_pickup"
    assert latest_email["note"] == "Package dispatched"

    # Transition: shipped -> delivered
    up_deliv = client.post(
        "/product/update_order_status",
        headers=storekeeper_hdr,
        json={"order_id": order_id, "status": "delivered", "note": "Received by customer"},
    )
    assert up_deliv.status_code == 200
    assert up_deliv.json()["data"]["status"] == "delivered"
    assert len(mailer.sent_emails) >= 2
    deliv_email = mailer.sent_emails[-1]
    assert deliv_email["order_number"] == order_number
    assert deliv_email["status"] == "completed"
    assert deliv_email["note"] == "Received by customer"

    # 9. Test door delivery order with rider_details guard and out_for_delivery email:
    addr_res = client.post(
        "/product/add_address",
        headers=cust1_hdr,
        json={
            "first_name": "Customer",
            "last_name": "One",
            "phone": "08012345678",
            "address": "45 Victoria Island",
            "state": "Lagos",
            "area": "Island",
            "is_default": "1",
        },
    )
    assert addr_res.status_code == 201
    door_addr_id = addr_res.json()["address_id"]

    client.post(
        "/product/add_to_cart",
        headers=cust1_hdr,
        json={"product_id": prod_id, "quantity": 1},
    )
    chk_door = client.post(
        "/product/initiate_checkout",
        headers=cust1_hdr,
        json={"delivery_type": "delivery", "address_id": door_addr_id},
    )
    door_ref = chk_door.json()["data"]["reference"]
    door_order_num = chk_door.json()["data"]["order_number"]

    amount_kobo = int(Decimal(str(chk_door.json()["data"]["amount"])) * 100)
    mock_paystack.verified_responses[door_ref] = {
        "status": True,
        "data": {
            "status": "success",
            "reference": door_ref,
            "amount": amount_kobo,
            "gateway_response": "Successful",
            "channel": "card",
            "currency": "NGN",
            "paid_at": "2026-09-17T22:35:00.000Z",
        },
    }
    v_res2 = client.post("/product/verify_payment", headers=cust1_hdr, json={"reference": door_ref})
    assert v_res2.status_code == 200

    door_detail = client.post(
        "/product/manage_order_details",
        headers=storekeeper_hdr,
        json={"order_number": door_order_num},
    ).json()["data"]
    door_order_id = int(door_detail["id"])

    # Attempt shipped without rider_details on door_delivery -> 400
    no_rider = client.post(
        "/product/update_order_status",
        headers=storekeeper_hdr,
        json={"order_id": door_order_id, "status": "shipped"},
    )
    assert no_rider.status_code == 400
    err_body = no_rider.json()
    assert err_body["error"]["code"] == "http_error"
    assert "Rider details are required" in err_body["error"]["message"]

    # Shipped with rider_details -> 200 and out_for_delivery email
    ship_door = client.post(
        "/product/update_order_status",
        headers=storekeeper_hdr,
        json={
            "order_id": door_order_id,
            "status": "shipped",
            "rider_details": "Rider: Tunde, Phone: 08099887766",
            "note": "On the bike",
        },
    )
    assert ship_door.status_code == 200
    door_email = mailer.sent_emails[-1]
    assert door_email["status"] == "out_for_delivery"
    assert "Tunde" in door_email["rider_details"]


# ═════════════════════════════════════════════════════════════
# PERMISSION ROLES TESTS
# ═════════════════════════════════════════════════════════════


def test_store_admin_roles_authorization(store_harness: StoreHarness) -> None:
    """Verify all authorized store roles (super admin, admin, finance admin, storekeeper admin)."""
    engine = store_harness.engine
    settings = store_harness.settings
    token_svc = TokenService(settings)
    client = store_harness.client

    # Test each authorized role
    allowed_roles = ["super admin", "finance admin"]
    for role in allowed_roles:
        u_id, u_email = _create_test_user(engine, user_role=role)
        store_harness.created_user_ids.append(u_id)
        u_tok = token_svc.issue(
            {
                "id": u_id,
                "email": u_email,
                "user_role": role,
                "fullname": f"Test {role}",
            }
        ).access_token
        hdr = {"Authorization": f"Bearer {u_tok}"}

        # Should be able to view order management
        resp = client.get("/product/order_management", headers=hdr)
        assert resp.status_code == 200, f"Role {role} failed with {resp.text}"

    # Disallowed role: "alumni" / "member"
    member_id, member_email = _create_test_user(engine, user_role="alumni")
    store_harness.created_user_ids.append(member_id)
    member_tok = token_svc.issue(
        {
            "id": member_id,
            "email": member_email,
            "user_role": "alumni",
            "fullname": "Standard Member",
        }
    ).access_token
    member_hdr = {"Authorization": f"Bearer {member_tok}"}

    resp_denied = client.get("/product/order_management", headers=member_hdr)
    assert resp_denied.status_code == 403
