"""FastAPI router for Store, Products, Cart, Addresses, Delivery Zones, and Orders."""

from __future__ import annotations

import json
from collections.abc import Callable
from typing import Annotated, Any

from fastapi import APIRouter, Depends, Header, HTTPException, Request, Response, status
from fastapi.responses import JSONResponse, PlainTextResponse
from sqlalchemy.orm import Session
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import UploadFile

from app.api.common import body_mapping, with_session
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.db.session import Database
from app.integrations.mail import Mailer
from app.integrations.paystack import PaystackClient, PaystackError, PaystackSignatureError
from app.integrations.uploads import AvatarUploadError, PreparedAvatar, prepare_avatar
from app.schemas.store import (
    CartResponse,
    DeliveryZoneResponse,
    InitiateCheckoutResponse,
    OrderDetailResponse,
    OrdersResponse,
    PinProductResponse,
    ProductListResponse,
    UpdateOrderStatusResponse,
    VerifyPaymentResponse,
)
from app.services.store import StoreError, StoreService

product_router = APIRouter(prefix="/product", tags=["Store & Orders"])


def _to_bool(value: Any) -> bool:
    """Normalize boolean flag from json or string form-data."""
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value != 0
    if isinstance(value, str):
        return value.strip().lower() in {"1", "true", "yes", "on"}
    return False


async def _run_store[T](request: Request, action: Callable[[StoreService], T]) -> T:
    """Run a store service operation within a worker thread using application database session."""
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    mailer: Mailer | None = getattr(request.app.state, "mailer", None)
    paystack_client: PaystackClient | None = getattr(request.app.state, "paystack_client", None)

    def execute(session: Session) -> T:
        service = StoreService(
            session=session,
            settings=settings,
            paystack_client=paystack_client,
            mail_service=mailer,
        )
        return action(service)

    return await run_in_threadpool(with_session, database, execute)


async def _product_input(request: Request) -> tuple[dict[str, Any], list[UploadFile]]:
    """Parse product input accepting both application/json and multipart/form-data."""
    content_type = request.headers.get("content-type", "").lower()
    if "application/json" in content_type:
        body = await body_mapping(request)
        return body, []
    form = await request.form()
    payload: dict[str, Any] = {}
    images: list[UploadFile] = []
    for key, value in form.multi_items():
        if key in {"images", "images[]"} and isinstance(value, UploadFile):
            images.append(value)
        elif isinstance(value, str):
            payload[key] = value
    return payload, images


async def _prepared_images(images: list[UploadFile]) -> list[PreparedAvatar]:
    """Validate and sanitize uploaded product images."""
    if len(images) > 10:
        raise AvatarUploadError("Product may contain at most 10 images")
    prepared: list[PreparedAvatar] = []
    try:
        for image in images:
            content = await image.read(5 * 1024 * 1024 + 1)
            if content:
                prep = prepare_avatar(image.filename, content)
                prepared.append(prep)
        return prepared
    finally:
        for image in images:
            await image.close()


# ═══════════════════════════════════════════════════════════════════════════════
# PRODUCTS
# ═══════════════════════════════════════════════════════════════════════════════


@product_router.get(
    "/fetch_products",
    response_model=ProductListResponse,
    summary="Fetch all active store products with variants and images",
)
@product_router.post(
    "/fetch_products",
    response_model=ProductListResponse,
    summary="Fetch all active store products with variants and images (POST alias)",
)
async def fetch_products(request: Request) -> Any:
    products, meta = await _run_store(request, lambda svc: svc.fetch_products())
    return {
        "status": True,
        "message": "Products retrieved successfully.",
        "meta": meta,
        "data": products,
    }


@product_router.post(
    "/pin_product_item",
    response_model=PinProductResponse,
    summary="Pin or unpin a product in the catalogue (Store Admin)",
)
async def pin_product_item(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    product_id_raw = data.get("product_id")
    pin_item_raw = data.get("pin_item")

    if pin_item_raw is None:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"status": False, "message": "pin_item is required."},
        )

    try:
        product_id = int(product_id_raw or 0)
    except (ValueError, TypeError):
        product_id = 0

    pin_item = _to_bool(pin_item_raw)

    try:
        res = await _run_store(
            request,
            lambda svc: svc.pin_product_item(principal.user_id, product_id, pin_item),
        )
        return {
            "status": True,
            "message": (
                "Product pinned successfully." if pin_item else "Product unpinned successfully."
            ),
            "data": res,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/add_product",
    status_code=status.HTTP_201_CREATED,
    summary="Create a new product with images and variants (Store Admin)",
)
async def add_product(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    payload, image_files = await _product_input(request)
    try:
        prepared = await _prepared_images(image_files)
    except AvatarUploadError as exc:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"status": False, "message": str(exc)},
        ) from exc

    product_name = str(payload.get("product_name") or "")
    category = str(payload.get("category") or "")
    price = str(payload.get("price") or "")
    description = str(payload.get("description") or "")
    has_size = _to_bool(payload.get("has_size"))
    has_color = _to_bool(payload.get("has_color"))
    quantity_raw = payload.get("quantity")
    variants_raw = payload.get("variants")
    spotlight_index_raw = payload.get("spotlight_index")

    qty_val: int | None = None
    if quantity_raw is not None and str(quantity_raw).strip() not in ("", "null", "None"):
        try:
            qty_val = int(quantity_raw)
        except ValueError:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "status": False,
                    "message": "Quantity must be a valid non-negative number.",
                },
            ) from None

    try:
        spotlight_index = int(spotlight_index_raw or 0)
    except (ValueError, TypeError):
        spotlight_index = 0

    variants_json = (
        str(variants_raw)
        if isinstance(variants_raw, str)
        else (json.dumps(variants_raw) if variants_raw is not None else None)
    )

    try:
        product_id = await _run_store(
            request,
            lambda svc: svc.add_product(
                actor_user_id=principal.user_id,
                product_name=product_name,
                category=category,
                price_val=price,
                description=description,
                has_size=has_size,
                has_color=has_color,
                quantity=qty_val,
                variants_json=variants_json,
                images=prepared,
                spotlight_index=spotlight_index,
            ),
        )
        return JSONResponse(
            status_code=status.HTTP_201_CREATED,
            content={
                "status": True,
                "message": "Product created successfully.",
                "data": {"product_id": product_id},
            },
        )
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/edit_product",
    summary="Edit an existing product, variants, and images (Store Admin)",
)
async def edit_product(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    payload, image_files = await _product_input(request)
    try:
        prepared = await _prepared_images(image_files)
    except AvatarUploadError as exc:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"status": False, "message": str(exc)},
        ) from exc

    try:
        product_id = int(payload.get("product_id") or 0)
    except (ValueError, TypeError):
        product_id = 0

    product_name = str(payload.get("product_name") or "")
    category = str(payload.get("category") or "")
    price = str(payload.get("price") or "")
    description = str(payload.get("description") or "")
    has_size = _to_bool(payload.get("has_size"))
    has_color = _to_bool(payload.get("has_color"))
    quantity_raw = payload.get("quantity")
    variants_raw = payload.get("variants")
    delete_image_ids_raw = payload.get("delete_image_ids")
    spotlight_image_id_raw = payload.get("spotlight_image_id")
    spotlight_index_raw = payload.get("spotlight_index")

    parsed_delete_ids: list[int] = []
    if delete_image_ids_raw:
        if isinstance(delete_image_ids_raw, list):
            parsed_delete_ids = [int(x) for x in delete_image_ids_raw]
        elif isinstance(delete_image_ids_raw, str) and delete_image_ids_raw.strip():
            try:
                decoded = json.loads(delete_image_ids_raw)
                if isinstance(decoded, list):
                    parsed_delete_ids = [int(x) for x in decoded]
            except (ValueError, TypeError, json.JSONDecodeError):
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail={
                        "status": False,
                        "message": "delete_image_ids must be a JSON array of ids.",
                    },
                ) from None

    qty_val: int | None = None
    if quantity_raw is not None and str(quantity_raw).strip() not in ("", "null", "None"):
        try:
            qty_val = int(quantity_raw)
        except ValueError:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "status": False,
                    "message": "Quantity must be a valid non-negative number.",
                },
            ) from None

    spotlight_image_id: int | None = None
    if spotlight_image_id_raw is not None and str(spotlight_image_id_raw).strip() not in (
        "",
        "null",
        "None",
    ):
        try:
            spotlight_image_id = int(spotlight_image_id_raw)
        except (ValueError, TypeError):
            spotlight_image_id = None

    spotlight_index: int | None = None
    if spotlight_index_raw is not None and str(spotlight_index_raw).strip() not in (
        "",
        "null",
        "None",
    ):
        try:
            spotlight_index = int(spotlight_index_raw)
        except (ValueError, TypeError):
            spotlight_index = None

    variants_json = (
        str(variants_raw)
        if isinstance(variants_raw, str)
        else (json.dumps(variants_raw) if variants_raw is not None else None)
    )

    try:
        updated_id = await _run_store(
            request,
            lambda svc: svc.edit_product(
                actor_user_id=principal.user_id,
                product_id=product_id,
                product_name=product_name,
                category=category,
                price_val=price,
                description=description,
                has_size=has_size,
                has_color=has_color,
                quantity=qty_val,
                variants_json=variants_json,
                delete_image_ids=parsed_delete_ids,
                new_images=prepared,
                spotlight_image_id=spotlight_image_id,
                spotlight_index=spotlight_index,
            ),
        )
        return {
            "status": True,
            "message": "Product updated successfully.",
            "data": {"product_id": updated_id},
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/delete_product",
    summary="Delete a product and its images (Store Admin)",
)
@product_router.delete(
    "/delete_product",
    summary="Delete a product and its images (Store Admin, DELETE alias)",
)
async def delete_product(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    product_id_raw = data.get("product_id")
    try:
        product_id = int(product_id_raw or 0)
    except (ValueError, TypeError):
        product_id = 0

    try:
        await _run_store(request, lambda svc: svc.delete_product(principal.user_id, product_id))
        return {
            "status": True,
            "message": "Product deleted successfully.",
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


# ═══════════════════════════════════════════════════════════════════════════════
# CART
# ═══════════════════════════════════════════════════════════════════════════════


@product_router.get(
    "/fetch_cart",
    response_model=CartResponse,
    summary="Fetch active shopping cart for authenticated user",
)
@product_router.post(
    "/fetch_cart",
    response_model=CartResponse,
    summary="Fetch active shopping cart for authenticated user (POST alias)",
)
async def fetch_cart(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    cart_data = await _run_store(request, lambda svc: svc.fetch_cart(principal.user_id))
    return {
        "status": True,
        "message": "Cart retrieved successfully.",
        "data": cart_data,
    }


@product_router.post(
    "/add_to_cart",
    response_model=CartResponse,
    summary="Add an item or variant to shopping cart",
)
async def add_to_cart(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        product_id = int(data.get("product_id") or 0)
    except (ValueError, TypeError):
        product_id = 0

    variant_id_raw = data.get("variant_id")
    variant_id = int(variant_id_raw) if variant_id_raw not in (None, "", "null") else None

    try:
        quantity = int(data.get("quantity") or 1)
    except (ValueError, TypeError):
        quantity = 1

    try:
        updated_cart = await _run_store(
            request,
            lambda svc: svc.add_to_cart(
                user_id=principal.user_id,
                product_id=product_id,
                variant_id=variant_id,
                quantity=quantity,
            ),
        )
        return {
            "status": True,
            "message": "Item added to cart.",
            "data": updated_cart,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/update_cart",
    response_model=CartResponse,
    summary="Update quantity of item in cart (quantity=0 removes)",
)
async def update_cart(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        cart_item_id = int(data.get("cart_item_id") or 0)
    except (ValueError, TypeError):
        cart_item_id = 0

    try:
        raw_quantity = data.get("quantity")
        quantity = int(raw_quantity) if raw_quantity is not None else -1
    except (ValueError, TypeError):
        quantity = -1

    try:
        updated_cart = await _run_store(
            request,
            lambda svc: svc.update_cart(
                user_id=principal.user_id,
                cart_item_id=cart_item_id,
                quantity=quantity,
            ),
        )
        msg = "Item removed from cart." if quantity == 0 else "Cart updated successfully."
        return {
            "status": True,
            "message": msg,
            "data": updated_cart,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/remove_from_cart",
    response_model=CartResponse,
    summary="Remove a line item from shopping cart",
)
async def remove_from_cart(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        cart_item_id = int(data.get("cart_item_id") or 0)
    except (ValueError, TypeError):
        cart_item_id = 0

    try:
        updated_cart = await _run_store(
            request,
            lambda svc: svc.remove_from_cart(
                user_id=principal.user_id,
                cart_item_id=cart_item_id,
            ),
        )
        return {
            "status": True,
            "message": "Item removed from cart.",
            "data": updated_cart,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/clear_cart",
    response_model=CartResponse,
    summary="Clear all items in shopping cart",
)
async def clear_cart(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    empty_cart = await _run_store(request, lambda svc: svc.clear_cart(principal.user_id))
    return {
        "status": True,
        "message": "Cart cleared successfully.",
        "data": empty_cart,
    }


# ═══════════════════════════════════════════════════════════════════════════════
# ADDRESSES & DELIVERY ZONES
# ═══════════════════════════════════════════════════════════════════════════════


@product_router.post(
    "/add_address",
    status_code=status.HTTP_201_CREATED,
    summary="Add a saved delivery address for user",
)
async def add_address(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        addr_id, addresses = await _run_store(
            request,
            lambda svc: svc.add_address(principal.user_id, data),
        )
        return JSONResponse(
            status_code=status.HTTP_201_CREATED,
            content={
                "status": True,
                "message": "Address saved successfully.",
                "address_id": addr_id,
                "data": addresses,
            },
        )
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.get(
    "/fetch_addresses",
    summary="Fetch all saved delivery addresses for user",
)
@product_router.post(
    "/fetch_addresses",
    summary="Fetch all saved delivery addresses for user (POST alias)",
)
async def fetch_addresses(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    addresses = await _run_store(request, lambda svc: svc.fetch_addresses(principal.user_id))
    return {
        "status": True,
        "message": "Addresses retrieved successfully.",
        "data": addresses,
    }


@product_router.post(
    "/edit_address",
    status_code=status.HTTP_201_CREATED,
    summary="Edit an existing saved delivery address",
)
async def edit_address(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        address_id = int(data.get("id") or 0)
    except (ValueError, TypeError):
        address_id = 0

    try:
        addresses = await _run_store(
            request,
            lambda svc: svc.edit_address(principal.user_id, address_id, data),
        )
        return JSONResponse(
            status_code=status.HTTP_201_CREATED,
            content={
                "status": True,
                "message": "Address edited successfully.",
                "address_id": address_id,
                "data": addresses,
            },
        )
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/delete_address",
    summary="Delete a saved delivery address",
)
@product_router.delete(
    "/delete_address",
    summary="Delete a saved delivery address (DELETE alias)",
)
async def delete_address(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        address_id = int(data.get("address_id") or 0)
    except (ValueError, TypeError):
        address_id = 0

    try:
        await _run_store(request, lambda svc: svc.delete_address(principal.user_id, address_id))
        return {
            "status": True,
            "message": "Address deleted successfully.",
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/set_default_address",
    summary="Set an address as the default",
)
async def set_default_address(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        address_id = int(data.get("address_id") or 0)
    except (ValueError, TypeError):
        address_id = 0

    try:
        await _run_store(
            request,
            lambda svc: svc.set_default_address(principal.user_id, address_id),
        )
        return {
            "status": True,
            "message": "Default address updated.",
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.get(
    "/fetch_delivery_zones",
    response_model=DeliveryZoneResponse,
    summary="Fetch all delivery zones grouped by state",
)
@product_router.post(
    "/fetch_delivery_zones",
    response_model=DeliveryZoneResponse,
    summary="Fetch all delivery zones grouped by state (POST alias)",
)
async def fetch_delivery_zones(request: Request) -> Any:
    zones = await _run_store(request, lambda svc: svc.fetch_delivery_zones())
    return {
        "status": True,
        "message": "Delivery zones retrieved.",
        "data": zones,
    }


# ═══════════════════════════════════════════════════════════════════════════════
# CHECKOUT & ORDERS
# ═══════════════════════════════════════════════════════════════════════════════


@product_router.post(
    "/initiate_checkout",
    response_model=InitiateCheckoutResponse,
    summary="Initiate checkout and start Paystack transaction",
)
async def initiate_checkout(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        res = await _run_store(
            request,
            lambda svc: svc.initiate_checkout(
                user_id=principal.user_id,
                user_email=principal.email,
                payload=data,
            ),
        )
        return {
            "status": True,
            "message": "Checkout initiated.",
            "data": res,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc
    except PaystackError as exc:
        raise HTTPException(
            status_code=exc.status_code,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/verify_payment",
    response_model=VerifyPaymentResponse,
    summary="Verify payment after Paystack inline checkout callback",
)
async def verify_payment(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    reference = str(data.get("reference") or "")

    try:
        res = await _run_store(
            request,
            lambda svc: svc.verify_payment(principal.user_id, reference),
        )
        return {
            "status": True,
            "message": "Payment verified. Order placed successfully.",
            "data": res,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc
    except PaystackError as exc:
        raise HTTPException(
            status_code=exc.status_code,
            detail={"status": False, "message": exc.message},
        ) from exc


async def _handle_paystack_webhook(request: Request, signature: str) -> Response:
    """Verify and process a charge.success webhook, acknowledging any outcome."""
    raw_body = await request.body()
    try:
        await _run_store(
            request,
            lambda svc: svc.process_paystack_webhook(raw_body, signature),
        )
        return PlainTextResponse("OK", status_code=200)
    except PaystackSignatureError:
        return PlainTextResponse("Invalid signature", status_code=401)


@product_router.post(
    "/paystack_webhook",
    summary="Paystack webhook for charge.success events",
)
async def paystack_webhook(
    request: Request,
    x_paystack_signature: Annotated[str, Header(alias="X-Paystack-Signature")] = "",
) -> Response:
    return await _handle_paystack_webhook(request, x_paystack_signature)


@product_router.get(
    "/fetch_orders",
    response_model=OrdersResponse,
    summary="Fetch order history for authenticated customer",
)
@product_router.post(
    "/fetch_orders",
    response_model=OrdersResponse,
    summary="Fetch order history for authenticated customer (POST alias)",
)
async def fetch_orders(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    orders = await _run_store(request, lambda svc: svc.fetch_user_orders(principal.user_id))
    return {
        "status": True,
        "message": "Orders retrieved successfully.",
        "data": orders,
    }


@product_router.post(
    "/view_order_details",
    response_model=OrderDetailResponse,
    summary="View single order details for customer",
)
async def view_order_details(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    order_number = str(data.get("order_number") or "")

    try:
        order = await _run_store(
            request,
            lambda svc: svc.view_order_details(principal.user_id, order_number),
        )
        return {
            "status": True,
            "message": "Order details retrieved successfully.",
            "data": order,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.get(
    "/order_management",
    response_model=OrdersResponse,
    summary="Fetch all orders for store management (Store Admin)",
)
@product_router.post(
    "/order_management",
    response_model=OrdersResponse,
    summary="Fetch all orders for store management (Store Admin, POST alias)",
)
async def order_management(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    try:
        orders = await _run_store(request, lambda svc: svc.order_management(principal.user_id))
        return {
            "status": True,
            "message": "Orders retrieved successfully.",
            "data": orders,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/manage_order_details",
    response_model=OrderDetailResponse,
    summary="View any order details for store management (Store Admin)",
)
async def manage_order_details(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    order_number = str(data.get("order_number") or "")

    try:
        order = await _run_store(
            request,
            lambda svc: svc.manage_order_details(principal.user_id, order_number),
        )
        return {
            "status": True,
            "message": "Order details retrieved successfully.",
            "data": order,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


@product_router.post(
    "/update_order_status",
    response_model=UpdateOrderStatusResponse,
    summary="Update order delivery status (Store Admin)",
)
async def update_order_status(
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> Any:
    data = await body_mapping(request)
    try:
        order_id = int(data.get("order_id") or 0)
    except (ValueError, TypeError):
        order_id = 0

    target_status = str(data.get("status") or "")
    note = str(data.get("note") or "")
    rider_details = str(data.get("rider_details") or "")

    try:
        res = await _run_store(
            request,
            lambda svc: svc.update_order_status(
                actor_user_id=principal.user_id,
                order_id=order_id,
                status=target_status,
                note=note,
                rider_details=rider_details,
            ),
        )
        return {
            "status": True,
            "message": "Order status updated successfully.",
            "data": res,
        }
    except StoreError as exc:
        raise HTTPException(
            status_code=exc.http_status,
            detail={"status": False, "message": exc.message},
        ) from exc


paystack_webhook_alias_router = APIRouter(prefix="/api/paystack", tags=["Store & Orders"])


@paystack_webhook_alias_router.post(
    "/webhook",
    summary="Paystack webhook alias for charge.success events",
)
async def paystack_webhook_alias(
    request: Request,
    x_paystack_signature: Annotated[str, Header(alias="X-Paystack-Signature")] = "",
) -> Response:
    """Serve the same webhook logic at the Paystack-configured ``/api/paystack/webhook`` path."""
    return await _handle_paystack_webhook(request, x_paystack_signature)
