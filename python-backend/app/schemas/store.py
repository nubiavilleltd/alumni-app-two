"""Pydantic schemas for Store, Products, Cart, Addresses, Delivery Zones, and Orders."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field

# ═══════════════════════════════════════════════════════════════════════════════
# PRODUCT SCHEMAS
# ═══════════════════════════════════════════════════════════════════════════════


class ProductImageItem(BaseModel):
    """Product image with spotlight flag and normalized URL."""

    model_config = ConfigDict(extra="ignore")

    id: int
    image_path: str
    image_url: str
    is_spotlight: bool


class ProductVariantItem(BaseModel):
    """Product SKU variant for size/color combinations."""

    model_config = ConfigDict(extra="ignore")

    id: int
    color: str | None = None
    size: str | None = None
    quantity: int
    image_id: int | None = None


class ProductItem(BaseModel):
    """Full product representation returned by catalogue APIs."""

    model_config = ConfigDict(extra="ignore")

    id: int
    user_id: int
    product_name: str
    category: str
    price: str
    description: str
    has_size: bool
    has_color: bool
    status: str
    pin_item: bool
    quantity: int | None = None
    total_stock: int
    created_at: str | None = None
    updated_at: str | None = None
    images: list[ProductImageItem] = Field(default_factory=list)
    variants: list[ProductVariantItem] = Field(default_factory=list)


class ProductListMeta(BaseModel):
    """Catalogue metadata for product listings."""

    model_config = ConfigDict(extra="ignore")

    total_products: int
    total_pinned: int
    max_pinned: int = 4


class ProductListResponse(BaseModel):
    """Standard response for fetching active store products."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    meta: ProductListMeta
    data: list[ProductItem]


class PinProductPayload(BaseModel):
    """Payload to pin or unpin a product in the catalogue."""

    model_config = ConfigDict(extra="ignore")

    product_id: int
    pin_item: bool


class PinProductResponseData(BaseModel):
    """Result data after pinning/unpinning a product."""

    model_config = ConfigDict(extra="ignore")

    product_id: int
    pin_item: bool
    total_pinned: int
    max_pinned: int = 4


class PinProductResponse(BaseModel):
    """Response returned after pin operation."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: PinProductResponseData


# ═══════════════════════════════════════════════════════════════════════════════
# CART SCHEMAS
# ═══════════════════════════════════════════════════════════════════════════════


class CartVariantInfo(BaseModel):
    """Variant metadata embedded in a cart item."""

    model_config = ConfigDict(extra="ignore")

    id: int
    color: str | None = None
    size: str | None = None


class CartImageInfo(BaseModel):
    """Image metadata embedded in a cart item."""

    model_config = ConfigDict(extra="ignore")

    id: int
    image_url: str


class CartItemDetail(BaseModel):
    """Cart item line entry."""

    model_config = ConfigDict(extra="ignore")

    cart_item_id: int
    product_id: int
    unit_price: str
    quantity: int
    product_name: str
    subtotal: str
    variant: CartVariantInfo | None = None
    image: CartImageInfo | None = None


class CartData(BaseModel):
    """Customer active shopping cart contents."""

    model_config = ConfigDict(extra="ignore")

    cart_id: int | None = None
    total_items: int = 0
    subtotal: str = "0.00"
    items: list[CartItemDetail] = Field(default_factory=list)


class CartResponse(BaseModel):
    """Response envelope for cart queries and mutations."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: CartData


class AddToCartPayload(BaseModel):
    """Payload for adding an item to the active cart."""

    model_config = ConfigDict(extra="ignore")

    product_id: int
    variant_id: int | None = None
    quantity: int = 1


class UpdateCartPayload(BaseModel):
    """Payload for updating cart item quantity."""

    model_config = ConfigDict(extra="ignore")

    cart_item_id: int
    quantity: int


class RemoveFromCartPayload(BaseModel):
    """Payload for removing a single line item from cart."""

    model_config = ConfigDict(extra="ignore")

    cart_item_id: int


# ═══════════════════════════════════════════════════════════════════════════════
# ADDRESS & DELIVERY ZONE SCHEMAS
# ═══════════════════════════════════════════════════════════════════════════════


class AddressItem(BaseModel):
    """Saved delivery address."""

    model_config = ConfigDict(extra="ignore")

    id: int
    user_id: int
    first_name: str
    last_name: str
    phone: str
    additional_phone: str | None = None
    address: str
    landmark: str | None = None
    state: str
    area: str
    is_default: bool
    created_at: str | None = None
    updated_at: str | None = None


class AddressListResponse(BaseModel):
    """Response envelope for user address listings."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: list[AddressItem]


class AddAddressPayload(BaseModel):
    """Payload for adding a saved delivery address."""

    model_config = ConfigDict(extra="ignore")

    first_name: str
    last_name: str
    phone: str
    additional_phone: str | None = None
    address: str
    landmark: str | None = None
    state: str
    area: str


class EditAddressPayload(BaseModel):
    """Payload for updating an existing delivery address."""

    model_config = ConfigDict(extra="ignore")

    id: int | str
    first_name: str
    last_name: str
    phone: str
    additional_phone: str | None = None
    address: str
    landmark: str | None = None
    state: str
    area: str


class DeleteAddressPayload(BaseModel):
    """Payload for deleting an existing delivery address."""

    model_config = ConfigDict(extra="ignore")

    address_id: int | str


class SetDefaultAddressPayload(BaseModel):
    """Payload for setting an address as default."""

    model_config = ConfigDict(extra="ignore")

    address_id: int | str


class DeliveryZoneArea(BaseModel):
    """Area and shipping fee within a state."""

    model_config = ConfigDict(extra="ignore")

    area: str | None = None
    fee: str


class DeliveryZoneStateGroup(BaseModel):
    """State grouping containing areas and shipping fees."""

    model_config = ConfigDict(extra="ignore")

    state: str
    areas: list[DeliveryZoneArea] = Field(default_factory=list)


class DeliveryZoneResponse(BaseModel):
    """Delivery zone listing response."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: list[DeliveryZoneStateGroup]


# ═══════════════════════════════════════════════════════════════════════════════
# CHECKOUT & ORDER SCHEMAS
# ═══════════════════════════════════════════════════════════════════════════════


class InitiateCheckoutPayload(BaseModel):
    """Payload to start checkout and initialize Paystack."""

    model_config = ConfigDict(extra="ignore")

    delivery_type: str
    address_id: int | None = None
    first_name: str | None = None
    last_name: str | None = None
    phone: str | None = None
    additional_phone: str | None = None
    address: str | None = None
    landmark: str | None = None
    state: str | None = None
    area: str | None = None


class InitiateCheckoutData(BaseModel):
    """Checkout initialization output with Paystack credentials."""

    model_config = ConfigDict(extra="ignore")

    reference: str
    access_code: str
    subtotal: str
    shipping_fee: str
    amount: str
    order_id: int
    order_number: str | None = None
    delivery_fee: str | None = None
    status: bool = True


class InitiateCheckoutResponse(BaseModel):
    """Response envelope for checkout initiation."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: InitiateCheckoutData


class VerifyPaymentPayload(BaseModel):
    """Payload to verify an order payment."""

    model_config = ConfigDict(extra="ignore")

    reference: str


class VerifyPaymentData(BaseModel):
    """Payment verification outcome."""

    model_config = ConfigDict(extra="ignore")

    order_id: int
    order_number: str | None = None
    reference: str | None = None
    status: bool | str = True
    payment_status: str | None = None
    subtotal: str | None = None
    shipping_fee: str | None = None
    amount: str
    delivery_type: str | None = None


class VerifyPaymentResponse(BaseModel):
    """Response envelope for payment verification."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: VerifyPaymentData


class OrderItemDetail(BaseModel):
    """Snapshot line item in an order."""

    model_config = ConfigDict(extra="ignore")

    id: int
    product_id: int | None = None
    variant_id: int | None = None
    product_name: str
    color: str | None = None
    size: str | None = None
    unit_price: str
    quantity: int
    line_total: str
    image_url: str | None = None


class OrderDetail(BaseModel):
    """Order detail model with items and status."""

    model_config = ConfigDict(extra="ignore")

    id: int
    user_id: int
    order_number: str
    paystack_reference: str
    subtotal: str
    shipping_fee: str
    amount: str
    delivery_type: str
    status: str
    display_status: str | None = None
    created_at: str | None = None
    updated_at: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    phone: str | None = None
    additional_phone: str | None = None
    address: str | None = None
    landmark: str | None = None
    state: str | None = None
    area: str | None = None
    shipped_note: str | None = None
    rider_details: str | None = None
    delivered_note: str | None = None
    paid_at: str | None = None
    completion_date: str | None = None
    cancelled_at: str | None = None
    items: list[OrderItemDetail] = Field(default_factory=list)

    # Admin customer fields
    customer_first_name: str | None = None
    customer_last_name: str | None = None
    customer_email: str | None = None
    customer_phone: str | None = None


class OrdersResponse(BaseModel):
    """Response envelope for order listings."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: list[OrderDetail]


class OrderDetailResponse(BaseModel):
    """Response envelope for single order detail."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: OrderDetail


class ViewOrderDetailPayload(BaseModel):
    """Payload to look up an order by order number."""

    model_config = ConfigDict(extra="ignore")

    order_number: str


class UpdateOrderStatusPayload(BaseModel):
    """Payload to update order status by store admin."""

    model_config = ConfigDict(extra="ignore")

    order_id: int | str
    status: str
    note: str | None = None
    rider_details: str | None = None


class UpdateOrderStatusData(BaseModel):
    """Result data after updating order status."""

    model_config = ConfigDict(extra="ignore")

    order_id: int
    order_number: str
    status: str
    display_status: str
    note: str = ""
    rider_details: str | None = None


class UpdateOrderStatusResponse(BaseModel):
    """Response envelope for order status update."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: UpdateOrderStatusData


class SimpleActionResponse(BaseModel):
    """Standard generic success response."""

    model_config = ConfigDict(extra="ignore")

    status: bool = True
    message: str
    data: dict[str, Any] | None = None
