"""Database repository for Store, Products, Cart, Addresses, Delivery Zones, and Orders."""

from __future__ import annotations

from datetime import UTC, datetime
from decimal import Decimal
from typing import Any, cast
from urllib.parse import urljoin

from sqlalchemy import (
    Table,
    case,
    func,
    select,
    update,
)
from sqlalchemy.engine import CursorResult
from sqlalchemy.orm import Session

from app.models.generated import (
    CartItems,
    Carts,
    CartsStatus,
    DeliveryZones,
    OrderItems,
    Orders,
    OrdersDeliveryType,
    OrdersStatus,
    ProductImages,
    Products,
    ProductsStatus,
    ProductVariants,
    UserAddresses,
    Users,
)

PRODUCTS_TABLE = cast(Table, Products.__table__)
PRODUCT_IMAGES_TABLE = cast(Table, ProductImages.__table__)
PRODUCT_VARIANTS_TABLE = cast(Table, ProductVariants.__table__)
CARTS_TABLE = cast(Table, Carts.__table__)
CART_ITEMS_TABLE = cast(Table, CartItems.__table__)
USER_ADDRESSES_TABLE = cast(Table, UserAddresses.__table__)
DELIVERY_ZONES_TABLE = cast(Table, DeliveryZones.__table__)
ORDERS_TABLE = cast(Table, Orders.__table__)
ORDER_ITEMS_TABLE = cast(Table, OrderItems.__table__)


class InsufficientStockError(Exception):
    """A locked order item can no longer be fulfilled; the caller must roll back."""


class StoreRepository:
    """Explicit repository for product catalog, shopping cart, delivery, and orders."""

    def __init__(self, session: Session) -> None:
        self._session = session

    # ═════════════════════════════════════════════════════════════
    # ACTOR & AUTHORIZATION
    # ═════════════════════════════════════════════════════════════

    def lock_actor(self, user_id: int) -> dict[str, Any] | None:
        """Lock and fetch current actor facts for authorization checks."""
        row = (
            self._session.execute(
                select(Users.id, Users.active, Users.user_role, Users.is_coordinator, Users.email)
                .where(Users.id == user_id)
                .with_for_update()
                .limit(1)
            )
            .mappings()
            .first()
        )
        return dict(row) if row is not None else None

    # ═════════════════════════════════════════════════════════════
    # PRODUCTS & INVENTORY
    # ═════════════════════════════════════════════════════════════

    def list_active_products(self, base_url: str = "") -> tuple[list[dict[str, Any]], int]:
        """Fetch all active products with images and variants, plus count of pinned products."""
        # Query active products ordered by pin_item DESC, id DESC
        prod_rows = (
            self._session.execute(
                select(Products)
                .where(Products.status == ProductsStatus.ACTIVE)
                .order_by(Products.pin_item.desc(), Products.id.desc())
            )
            .scalars()
            .all()
        )

        if not prod_rows:
            return [], 0

        product_ids = [p.id for p in prod_rows]

        # Fetch images for these products
        img_rows = (
            self._session.execute(
                select(ProductImages)
                .where(ProductImages.product_id.in_(product_ids))
                .order_by(ProductImages.is_spotlight.desc(), ProductImages.id.asc())
            )
            .scalars()
            .all()
        )
        images_by_prod: dict[int, list[dict[str, Any]]] = {pid: [] for pid in product_ids}
        for img in img_rows:
            image_url = (
                urljoin(base_url.rstrip("/") + "/", img.image_path) if base_url else img.image_path
            )
            images_by_prod[img.product_id].append(
                {
                    "id": img.id,
                    "image_path": img.image_path,
                    "image_url": image_url,
                    "is_spotlight": bool(img.is_spotlight),
                }
            )

        # Fetch variants for these products
        var_rows = (
            self._session.execute(
                select(ProductVariants)
                .where(ProductVariants.product_id.in_(product_ids))
                .order_by(ProductVariants.color.asc(), ProductVariants.size.asc())
            )
            .scalars()
            .all()
        )
        variants_by_prod: dict[int, list[dict[str, Any]]] = {pid: [] for pid in product_ids}
        for var in var_rows:
            variants_by_prod[var.product_id].append(
                {
                    "id": var.id,
                    "color": var.color,
                    "size": var.size,
                    "quantity": int(var.quantity),
                    "image_id": var.image_id,
                }
            )

        # Count pinned
        pinned_count = sum(1 for p in prod_rows if p.pin_item == 1)

        result: list[dict[str, Any]] = []
        for p in prod_rows:
            images = images_by_prod.get(p.id, [])
            variants = variants_by_prod.get(p.id, [])

            if p.has_size or p.has_color:
                total_stock = sum(v["quantity"] for v in variants)
            else:
                total_stock = int(p.quantity or 0)

            result.append(
                {
                    "id": p.id,
                    "user_id": p.user_id,
                    "product_name": p.product_name,
                    "category": p.category,
                    "price": f"{Decimal(str(p.price)):.2f}",
                    "description": p.description,
                    "has_size": bool(p.has_size),
                    "has_color": bool(p.has_color),
                    "status": p.status.value if hasattr(p.status, "value") else str(p.status),
                    "pin_item": bool(p.pin_item),
                    "quantity": p.quantity,
                    "total_stock": total_stock,
                    "created_at": p.created_at.strftime("%Y-%m-%d %H:%M:%S")
                    if p.created_at
                    else None,
                    "updated_at": p.updated_at.strftime("%Y-%m-%d %H:%M:%S")
                    if p.updated_at
                    else None,
                    "images": images,
                    "variants": variants,
                }
            )

        return result, pinned_count

    def get_product_by_id(self, product_id: int, lock: bool = False) -> Products | None:
        """Fetch product by ID, optionally locking the row."""
        stmt = select(Products).where(Products.id == product_id)
        if lock:
            stmt = stmt.with_for_update()
        return self._session.execute(stmt).scalar_one_or_none()

    def count_active_pinned(self) -> int:
        """Count how many active products are currently pinned."""
        count = self._session.execute(
            select(func.count())
            .select_from(Products)
            .where(Products.pin_item == 1, Products.status == ProductsStatus.ACTIVE)
        ).scalar_one()
        return int(count or 0)

    def update_product_pin(self, product_id: int, pin_item: bool) -> None:
        """Set pin_item flag on a product."""
        self._session.execute(
            update(Products).where(Products.id == product_id).values(pin_item=1 if pin_item else 0)
        )

    def create_product(
        self,
        user_id: int,
        product_name: str,
        category: str,
        price: Decimal,
        description: str,
        has_size: bool,
        has_color: bool,
        quantity: int | None,
    ) -> int:
        """Insert a new product row and return its ID."""
        now = datetime.now(UTC).replace(tzinfo=None)
        prod = Products(
            user_id=user_id,
            product_name=product_name,
            category=category,
            price=price,
            description=description,
            has_size=1 if has_size else 0,
            has_color=1 if has_color else 0,
            quantity=quantity,
            status=ProductsStatus.ACTIVE,
            pin_item=0,
            created_at=now,
            updated_at=now,
        )
        self._session.add(prod)
        self._session.flush()
        return prod.id

    def add_product_images(
        self,
        product_id: int,
        images: list[dict[str, Any]],
    ) -> list[int]:
        """Insert product image records and return list of inserted IDs."""
        now = datetime.now(UTC).replace(tzinfo=None)
        image_ids: list[int] = []
        for img in images:
            img_row = ProductImages(
                product_id=product_id,
                image_path=img["image_path"],
                is_spotlight=1 if img.get("is_spotlight") else 0,
                created_at=now,
            )
            self._session.add(img_row)
            self._session.flush()
            image_ids.append(img_row.id)
        return image_ids

    def add_product_variants(
        self,
        product_id: int,
        variants: list[dict[str, Any]],
    ) -> None:
        """Insert product variant records."""
        now = datetime.now(UTC).replace(tzinfo=None)
        for var in variants:
            var_row = ProductVariants(
                product_id=product_id,
                color=var.get("color"),
                size=var.get("size"),
                quantity=int(var.get("quantity", 0)),
                image_id=var.get("image_id"),
                created_at=now,
                updated_at=now,
            )
            self._session.add(var_row)
        self._session.flush()

    def update_product_row(
        self,
        product_id: int,
        product_name: str,
        category: str,
        price: Decimal,
        description: str,
        has_size: bool,
        has_color: bool,
        quantity: int | None,
    ) -> None:
        """Update fields on a product."""
        now = datetime.now(UTC).replace(tzinfo=None)
        self._session.execute(
            update(Products)
            .where(Products.id == product_id)
            .values(
                product_name=product_name,
                category=category,
                price=price,
                description=description,
                has_size=1 if has_size else 0,
                has_color=1 if has_color else 0,
                quantity=quantity,
                updated_at=now,
            )
        )

    def get_product_images(self, product_id: int) -> list[dict[str, Any]]:
        """Get all images currently attached to a product."""
        rows = (
            self._session.execute(
                select(ProductImages)
                .where(ProductImages.product_id == product_id)
                .order_by(ProductImages.id.asc())
            )
            .scalars()
            .all()
        )
        return [
            {
                "id": r.id,
                "product_id": r.product_id,
                "image_path": r.image_path,
                "is_spotlight": bool(r.is_spotlight),
            }
            for r in rows
        ]

    def delete_product_images(self, image_ids: list[int]) -> None:
        """Delete specific product images by ID."""
        if not image_ids:
            return
        self._session.execute(select(ProductImages).where(ProductImages.id.in_(image_ids)))
        for img_id in image_ids:
            img = self._session.get(ProductImages, img_id)
            if img:
                self._session.delete(img)
        self._session.flush()

    def set_spotlight_image(self, product_id: int, spotlight_id: int) -> None:
        """Clear spotlight on all images for product, set on spotlight_id."""
        self._session.execute(
            update(ProductImages)
            .where(ProductImages.product_id == product_id)
            .values(is_spotlight=0)
        )
        self._session.execute(
            update(ProductImages).where(ProductImages.id == spotlight_id).values(is_spotlight=1)
        )

    def delete_product_variants(self, product_id: int) -> None:
        """Delete all existing variants for a product."""
        vars_to_delete = (
            self._session.execute(
                select(ProductVariants).where(ProductVariants.product_id == product_id)
            )
            .scalars()
            .all()
        )
        for v in vars_to_delete:
            self._session.delete(v)
        self._session.flush()

    def delete_product_cascade(self, product_id: int) -> list[str]:
        """Delete product, variants, and images. Returns image paths to be unlinked."""
        # 1. Collect image paths
        images = (
            self._session.execute(
                select(ProductImages.image_path).where(ProductImages.product_id == product_id)
            )
            .scalars()
            .all()
        )
        image_paths = list(images)

        # 2. Delete variants
        self.delete_product_variants(product_id)

        # 3. Delete images
        imgs_to_del = (
            self._session.execute(
                select(ProductImages).where(ProductImages.product_id == product_id)
            )
            .scalars()
            .all()
        )
        for img in imgs_to_del:
            self._session.delete(img)

        # 4. Delete product
        prod = self._session.get(Products, product_id)
        if prod:
            self._session.delete(prod)
        self._session.flush()

        return image_paths

    # ═════════════════════════════════════════════════════════════
    # SHOPPING CART
    # ═════════════════════════════════════════════════════════════

    def get_or_create_active_cart(self, user_id: int, lock: bool = False) -> int:
        """Get the ID of the user's active cart, creating one if not exists."""
        stmt = select(Carts).where(Carts.user_id == user_id, Carts.status == CartsStatus.ACTIVE)
        if lock:
            stmt = stmt.with_for_update()
        cart = self._session.execute(stmt).scalar_one_or_none()
        if cart is not None:
            return cart.id

        now = datetime.now(UTC).replace(tzinfo=None)
        new_cart = Carts(
            user_id=user_id,
            status=CartsStatus.ACTIVE,
            created_at=now,
            updated_at=now,
        )
        self._session.add(new_cart)
        self._session.flush()
        return new_cart.id

    def get_cart_item(
        self, cart_id: int, product_id: int, variant_id: int | None = None
    ) -> CartItems | None:
        """Find existing item in cart by product and variant."""
        stmt = select(CartItems).where(
            CartItems.cart_id == cart_id,
            CartItems.product_id == product_id,
        )
        if variant_id is not None:
            stmt = stmt.where(CartItems.variant_id == variant_id)
        else:
            stmt = stmt.where(CartItems.variant_id.is_(None))
        return self._session.execute(stmt).scalar_one_or_none()

    def get_cart_item_by_id(self, cart_item_id: int) -> tuple[CartItems, int] | None:
        """Get cart item and its parent cart's user_id."""
        row = self._session.execute(
            select(CartItems, Carts.user_id)
            .join(Carts, Carts.id == CartItems.cart_id)
            .where(CartItems.id == cart_item_id, Carts.status == CartsStatus.ACTIVE)
        ).first()
        if not row:
            return None
        return row[0], int(row[1])

    def upsert_cart_item(
        self,
        cart_id: int,
        product_id: int,
        variant_id: int | None,
        unit_price: Decimal,
        quantity: int,
    ) -> None:
        """Add new item or increase quantity on existing cart item."""
        existing = self.get_cart_item(cart_id, product_id, variant_id)
        now = datetime.now(UTC).replace(tzinfo=None)
        if existing:
            new_qty = existing.quantity + quantity
            existing.quantity = new_qty
            existing.line_total = unit_price * new_qty
            existing.updated_at = now
        else:
            item = CartItems(
                cart_id=cart_id,
                product_id=product_id,
                variant_id=variant_id,
                unit_price=unit_price,
                quantity=quantity,
                line_total=unit_price * quantity,
                created_at=now,
                updated_at=now,
            )
            self._session.add(item)
        self._session.flush()

    def update_cart_item_quantity(
        self, cart_item_id: int, quantity: int, unit_price: Decimal
    ) -> None:
        """Update quantity and line total for a specific cart item."""
        item = self._session.get(CartItems, cart_item_id)
        if item:
            item.quantity = quantity
            item.line_total = unit_price * quantity
            item.updated_at = datetime.now(UTC).replace(tzinfo=None)
            self._session.flush()

    def delete_cart_item(self, cart_item_id: int) -> None:
        """Delete cart item by ID."""
        item = self._session.get(CartItems, cart_item_id)
        if item:
            self._session.delete(item)
            self._session.flush()

    def clear_active_cart(self, user_id: int) -> None:
        """Delete all items and the active cart row itself for user."""
        cart = self._session.execute(
            select(Carts).where(Carts.user_id == user_id, Carts.status == CartsStatus.ACTIVE)
        ).scalar_one_or_none()
        if cart:
            items = (
                self._session.execute(select(CartItems).where(CartItems.cart_id == cart.id))
                .scalars()
                .all()
            )
            for i in items:
                self._session.delete(i)
            self._session.delete(cart)
            self._session.flush()

    def get_cart_data(self, user_id: int, base_url: str = "") -> dict[str, Any]:
        """Assemble active cart structure matching PHP getCart()."""
        cart = self._session.execute(
            select(Carts).where(Carts.user_id == user_id, Carts.status == CartsStatus.ACTIVE)
        ).scalar_one_or_none()
        if not cart:
            return {
                "cart_id": None,
                "total_items": 0,
                "subtotal": "0.00",
                "items": [],
            }

        # Query cart items with products and variants
        items_query = (
            select(CartItems, Products, ProductVariants)
            .join(Products, Products.id == CartItems.product_id)
            .outerjoin(ProductVariants, ProductVariants.id == CartItems.variant_id)
            .where(CartItems.cart_id == cart.id)
            .order_by(CartItems.id.asc())
        )
        item_rows = self._session.execute(items_query).all()

        subtotal = Decimal("0.00")
        total_items = 0
        items_out: list[dict[str, Any]] = []

        for cart_item, product, variant in item_rows:
            line_subtotal = Decimal(str(cart_item.unit_price)) * cart_item.quantity
            subtotal += line_subtotal
            total_items += cart_item.quantity

            # Resolve image: variant image first, otherwise spotlight product image
            image_url: str | None = None
            if variant and variant.image_id:
                var_img = self._session.get(ProductImages, variant.image_id)
                if var_img:
                    image_url = (
                        urljoin(base_url.rstrip("/") + "/", var_img.image_path)
                        if base_url
                        else var_img.image_path
                    )

            if not image_url:
                spotlight_img = self._session.execute(
                    select(ProductImages)
                    .where(ProductImages.product_id == product.id, ProductImages.is_spotlight == 1)
                    .limit(1)
                ).scalar_one_or_none()
                if spotlight_img:
                    image_url = (
                        urljoin(base_url.rstrip("/") + "/", spotlight_img.image_path)
                        if base_url
                        else spotlight_img.image_path
                    )

            variant_dict = None
            if variant:
                variant_dict = {
                    "id": variant.id,
                    "color": variant.color,
                    "size": variant.size,
                }

            image_dict = (
                {
                    "id": variant.image_id if variant and variant.image_id else 0,
                    "image_url": image_url,
                }
                if image_url
                else None
            )

            items_out.append(
                {
                    "cart_item_id": cart_item.id,
                    "product_id": product.id,
                    "unit_price": f"{Decimal(str(cart_item.unit_price)):.2f}",
                    "quantity": cart_item.quantity,
                    "product_name": product.product_name,
                    "subtotal": f"{line_subtotal:.2f}",
                    "variant": variant_dict,
                    "image": image_dict,
                }
            )

        return {
            "cart_id": cart.id,
            "total_items": total_items,
            "subtotal": f"{subtotal:.2f}",
            "items": items_out,
        }

    # ═════════════════════════════════════════════════════════════
    # ADDRESSES & DELIVERY ZONES
    # ═════════════════════════════════════════════════════════════

    def list_user_addresses(self, user_id: int) -> list[dict[str, Any]]:
        """Fetch all addresses for user ordered by ID desc."""
        rows = (
            self._session.execute(
                select(UserAddresses)
                .where(UserAddresses.user_id == user_id)
                .order_by(UserAddresses.id.desc())
            )
            .scalars()
            .all()
        )
        return [
            {
                "id": r.id,
                "user_id": r.user_id,
                "first_name": r.first_name,
                "last_name": r.last_name,
                "phone": r.phone,
                "additional_phone": r.additional_phone,
                "address": r.address,
                "landmark": r.landmark,
                "state": r.state,
                "area": r.area,
                "is_default": bool(r.is_default),
                "created_at": r.created_at.strftime("%Y-%m-%d %H:%M:%S") if r.created_at else None,
                "updated_at": r.updated_at.strftime("%Y-%m-%d %H:%M:%S") if r.updated_at else None,
            }
            for r in rows
        ]

    def get_user_address(self, user_id: int, address_id: int) -> UserAddresses | None:
        """Get single address for user."""
        return self._session.execute(
            select(UserAddresses).where(
                UserAddresses.id == address_id,
                UserAddresses.user_id == user_id,
            )
        ).scalar_one_or_none()

    def create_address(self, user_id: int, data: dict[str, Any]) -> int:
        """Unset is_default on existing addresses, create new address with is_default=1."""
        now = datetime.now(UTC).replace(tzinfo=None)
        # Unset all default flags for user
        self._session.execute(
            update(UserAddresses).where(UserAddresses.user_id == user_id).values(is_default=0)
        )

        addr = UserAddresses(
            user_id=user_id,
            first_name=data["first_name"],
            last_name=data["last_name"],
            phone=data["phone"],
            additional_phone=data.get("additional_phone"),
            address=data["address"],
            landmark=data.get("landmark"),
            state=data["state"],
            area=data["area"],
            is_default=1,
            created_at=now,
            updated_at=now,
        )
        self._session.add(addr)
        self._session.flush()
        return addr.id

    def update_address(self, user_id: int, address_id: int, data: dict[str, Any]) -> bool:
        """Update address fields."""
        addr = self.get_user_address(user_id, address_id)
        if not addr:
            return False
        addr.first_name = data["first_name"]
        addr.last_name = data["last_name"]
        addr.phone = data["phone"]
        addr.additional_phone = data.get("additional_phone")
        addr.address = data["address"]
        addr.landmark = data.get("landmark")
        addr.state = data["state"]
        addr.area = data["area"]
        addr.updated_at = datetime.now(UTC).replace(tzinfo=None)
        self._session.flush()
        return True

    def delete_address(self, user_id: int, address_id: int) -> bool:
        """Delete user address."""
        addr = self.get_user_address(user_id, address_id)
        if not addr:
            return False
        self._session.delete(addr)
        self._session.flush()
        return True

    def set_default_address(self, user_id: int, address_id: int) -> bool:
        """Set address as default and unset all other addresses for user."""
        addr = self.get_user_address(user_id, address_id)
        if not addr:
            return False
        self._session.execute(
            update(UserAddresses).where(UserAddresses.user_id == user_id).values(is_default=0)
        )
        addr.is_default = 1
        addr.updated_at = datetime.now(UTC).replace(tzinfo=None)
        self._session.flush()
        return True

    def list_delivery_zones_grouped(self) -> list[dict[str, Any]]:
        """Fetch all delivery zones and group by state."""
        rows = (
            self._session.execute(
                select(DeliveryZones).order_by(DeliveryZones.state.asc(), DeliveryZones.area.asc())
            )
            .scalars()
            .all()
        )
        grouped: dict[str, list[dict[str, Any]]] = {}
        for r in rows:
            if r.state not in grouped:
                grouped[r.state] = []
            grouped[r.state].append(
                {
                    "area": r.area,
                    "fee": f"{Decimal(str(r.fee)):.2f}",
                }
            )
        return [{"state": st, "areas": areas} for st, areas in grouped.items()]

    def find_delivery_fee(self, state: str, area: str | None = None) -> Decimal | None:
        """Look up the shipping fee for a state and area.

        Falls back to the state-level row when the area row is absent.
        """
        if area:
            zone = self._session.execute(
                select(DeliveryZones).where(
                    DeliveryZones.state == state,
                    DeliveryZones.area == area,
                )
            ).scalar_one_or_none()
            if zone is not None:
                return Decimal(str(zone.fee))

        # Fallback to state-wide fee where area is NULL
        fallback = self._session.execute(
            select(DeliveryZones).where(
                DeliveryZones.state == state,
                DeliveryZones.area.is_(None),
            )
        ).scalar_one_or_none()
        if fallback is not None:
            return Decimal(str(fallback.fee))

        return None

    # ═════════════════════════════════════════════════════════════
    # CHECKOUT & ORDERS
    # ═════════════════════════════════════════════════════════════

    def cancel_pending_orders_for_user(self, user_id: int) -> None:
        """Cancel any previous pending orders for the user before checkout."""
        now = datetime.now(UTC).replace(tzinfo=None)
        self._session.execute(
            update(Orders)
            .where(Orders.user_id == user_id, Orders.status == OrdersStatus.PENDING)
            .values(
                status=OrdersStatus.CANCELLED,
                cancelled_at=now,
                updated_at=now,
            )
        )
        self._session.flush()

    def create_pending_order(
        self,
        user_id: int,
        cart_id: int,
        order_number: str,
        paystack_reference: str,
        access_code: str,
        subtotal: Decimal,
        shipping_fee: Decimal,
        total_amount: Decimal,
        delivery_type: str,
        address_info: dict[str, Any] | None = None,
    ) -> int:
        """Insert order row in pending status."""
        now = datetime.now(UTC).replace(tzinfo=None)
        del_type = (
            OrdersDeliveryType.DOOR_DELIVERY
            if delivery_type == "door_delivery"
            else OrdersDeliveryType.SELF_PICKUP
        )
        addr = address_info or {}
        order = Orders(
            user_id=user_id,
            cart_id=cart_id,
            order_number=order_number,
            paystack_reference=paystack_reference,
            paystack_access_code=access_code,
            subtotal=subtotal,
            shipping_fee=shipping_fee,
            amount=total_amount,
            delivery_type=del_type,
            status=OrdersStatus.PENDING,
            first_name=addr.get("first_name"),
            last_name=addr.get("last_name"),
            phone=addr.get("phone"),
            additional_phone=addr.get("additional_phone"),
            address=addr.get("address"),
            landmark=addr.get("landmark"),
            state=addr.get("state"),
            area=addr.get("area"),
            created_at=now,
            updated_at=now,
        )
        self._session.add(order)
        self._session.flush()
        return order.id

    def snapshot_order_items(self, order_id: int, cart_id: int) -> list[dict[str, Any]]:
        """Snapshot items from active cart into order_items. Returns items for validation."""
        now = datetime.now(UTC).replace(tzinfo=None)
        items = self._session.execute(
            select(CartItems, Products, ProductVariants)
            .join(Products, Products.id == CartItems.product_id)
            .outerjoin(ProductVariants, ProductVariants.id == CartItems.variant_id)
            .where(CartItems.cart_id == cart_id)
        ).all()

        snapshotted: list[dict[str, Any]] = []
        for c_item, prod, var in items:
            order_item = OrderItems(
                order_id=order_id,
                product_id=prod.id,
                variant_id=var.id if var else None,
                product_name=prod.product_name,
                color=var.color if var else None,
                size=var.size if var else None,
                unit_price=c_item.unit_price,
                quantity=c_item.quantity,
                line_total=c_item.line_total,
                created_at=now,
            )
            self._session.add(order_item)
            snapshotted.append(
                {
                    "product_id": prod.id,
                    "variant_id": var.id if var else None,
                    "product_name": prod.product_name,
                    "quantity": c_item.quantity,
                    "unit_price": c_item.unit_price,
                    "line_total": c_item.line_total,
                }
            )
        self._session.flush()
        return snapshotted

    def get_order_by_reference(self, reference: str, lock: bool = False) -> Orders | None:
        """Find order by Paystack reference."""
        stmt = select(Orders).where(Orders.paystack_reference == reference)
        if lock:
            stmt = stmt.with_for_update()
        return self._session.execute(stmt).scalar_one_or_none()

    def get_order_by_number(self, order_number: str, user_id: int | None = None) -> Orders | None:
        """Find order by order_number with optional user_id guard."""
        stmt = select(Orders).where(Orders.order_number == order_number)
        if user_id is not None:
            stmt = stmt.where(Orders.user_id == user_id)
        return self._session.execute(stmt).scalar_one_or_none()

    def get_order_by_id(self, order_id: int, lock: bool = False) -> Orders | None:
        """Find order by ID."""
        stmt = select(Orders).where(Orders.id == order_id)
        if lock:
            stmt = stmt.with_for_update()
        return self._session.execute(stmt).scalar_one_or_none()

    def finalize_order_atomic(
        self,
        order: Orders,
        payment_payload_json: str,
    ) -> None:
        """Atomic order finalization:
        1. Deduct stock with conditional updates so a second concurrent finalization
           for the same unit observes the decremented quantity and fails.
        2. Set order status to paid, save payload & paid_at.
        3. Set cart status to checked_out.

        Any shortfall raises ``InsufficientStock`` so the enclosing transaction rolls
        back; the caller owns marking the order failed and reconciling the payment.
        """
        now = datetime.now(UTC).replace(tzinfo=None)

        order_items = (
            self._session.execute(
                select(OrderItems)
                .where(OrderItems.order_id == order.id)
                .order_by(OrderItems.id.asc())
            )
            .scalars()
            .all()
        )

        for item in order_items:
            if item.variant_id:
                deducted = self._deduct_variant_stock(item.variant_id, item.quantity, now)
            elif item.product_id:
                deducted = self._deduct_product_stock(item.product_id, item.quantity, now)
            else:
                deducted = True
            if not deducted:
                raise InsufficientStockError

        order.status = OrdersStatus.PAID
        order.paid_at = now
        order.updated_at = now
        order.payment_payload = payment_payload_json

        if order.cart_id:
            cart = self._session.get(Carts, order.cart_id)
            if cart:
                cart.status = CartsStatus.CHECKED_OUT
                cart.updated_at = now

        self._session.flush()

    def _deduct_variant_stock(self, variant_id: int, quantity: int, now: datetime) -> bool:
        result = cast(
            CursorResult[Any],
            self._session.execute(
                update(ProductVariants)
                .where(ProductVariants.id == variant_id, ProductVariants.quantity >= quantity)
                .values(quantity=ProductVariants.quantity - quantity, updated_at=now)
            ),
        )
        return result.rowcount == 1

    def _deduct_product_stock(self, product_id: int, quantity: int, now: datetime) -> bool:
        result = cast(
            CursorResult[Any],
            self._session.execute(
                update(Products)
                .where(
                    Products.id == product_id,
                    Products.quantity.is_not(None),
                    Products.quantity >= quantity,
                )
                .values(quantity=Products.quantity - quantity, updated_at=now)
            ),
        )
        return result.rowcount == 1

    def mark_order_failed(self, order: Orders, payment_payload_json: str) -> None:
        """Mark an order as failed and save Paystack response."""
        now = datetime.now(UTC).replace(tzinfo=None)
        order.status = OrdersStatus.FAILED
        order.payment_payload = payment_payload_json
        order.updated_at = now
        self._session.flush()

    def get_order_items_detail(self, order_id: int, base_url: str = "") -> list[dict[str, Any]]:
        """Get order items with spotlight / variant images."""
        rows = (
            self._session.execute(
                select(OrderItems)
                .where(OrderItems.order_id == order_id)
                .order_by(OrderItems.id.asc())
            )
            .scalars()
            .all()
        )
        items: list[dict[str, Any]] = []
        for r in rows:
            image_url = None
            if r.variant_id:
                var = self._session.get(ProductVariants, r.variant_id)
                if var and var.image_id:
                    v_img = self._session.get(ProductImages, var.image_id)
                    if v_img:
                        image_url = (
                            urljoin(base_url.rstrip("/") + "/", v_img.image_path)
                            if base_url
                            else v_img.image_path
                        )

            if not image_url and r.product_id:
                s_img = self._session.execute(
                    select(ProductImages)
                    .where(
                        ProductImages.product_id == r.product_id, ProductImages.is_spotlight == 1
                    )
                    .limit(1)
                ).scalar_one_or_none()
                if s_img:
                    image_url = (
                        urljoin(base_url.rstrip("/") + "/", s_img.image_path)
                        if base_url
                        else s_img.image_path
                    )

            items.append(
                {
                    "id": r.id,
                    "product_id": r.product_id,
                    "variant_id": r.variant_id,
                    "product_name": r.product_name,
                    "color": r.color,
                    "size": r.size,
                    "unit_price": f"{Decimal(str(r.unit_price)):.2f}",
                    "quantity": r.quantity,
                    "line_total": f"{Decimal(str(r.line_total)):.2f}",
                    "image_url": image_url,
                }
            )
        return items

    def list_user_orders(self, user_id: int, base_url: str = "") -> list[dict[str, Any]]:
        """Fetch non-pending orders for customer."""
        orders = (
            self._session.execute(
                select(Orders)
                .where(
                    Orders.user_id == user_id,
                    Orders.status.notin_(
                        [OrdersStatus.PENDING, OrdersStatus.CANCELLED, OrdersStatus.FAILED]
                    ),
                )
                .order_by(Orders.id.desc())
            )
            .scalars()
            .all()
        )

        result: list[dict[str, Any]] = []
        for o in orders:
            items = self.get_order_items_detail(o.id, base_url)
            status_str = o.status.value if hasattr(o.status, "value") else str(o.status)
            del_type_str = (
                o.delivery_type.value if hasattr(o.delivery_type, "value") else str(o.delivery_type)
            )

            # Display-friendly status mapping
            display_status = status_str
            if status_str == "paid":
                display_status = "Processing"
            elif status_str == "shipped":
                display_status = (
                    "Out for Delivery" if del_type_str == "door_delivery" else "Ready for Pickup"
                )
            elif status_str == "delivered":
                display_status = "Completed"

            result.append(
                {
                    "id": o.id,
                    "user_id": o.user_id,
                    "order_number": o.order_number,
                    "paystack_reference": o.paystack_reference,
                    "subtotal": f"{Decimal(str(o.subtotal)):.2f}",
                    "shipping_fee": f"{Decimal(str(o.shipping_fee)):.2f}",
                    "amount": f"{Decimal(str(o.amount)):.2f}",
                    "delivery_type": del_type_str,
                    "status": display_status,
                    "first_name": o.first_name,
                    "last_name": o.last_name,
                    "phone": o.phone,
                    "additional_phone": o.additional_phone,
                    "address": o.address,
                    "landmark": o.landmark,
                    "state": o.state,
                    "area": o.area,
                    "shipped_note": o.shipped_note,
                    "rider_details": o.rider_details,
                    "delivered_note": o.delivered_note,
                    "paid_at": o.paid_at.strftime("%Y-%m-%d %H:%M:%S") if o.paid_at else None,
                    "created_at": o.created_at.strftime("%Y-%m-%d %H:%M:%S")
                    if o.created_at
                    else None,
                    "updated_at": o.updated_at.strftime("%Y-%m-%d %H:%M:%S")
                    if o.updated_at
                    else None,
                    "items": items,
                }
            )
        return result

    def list_admin_orders(self, base_url: str = "") -> list[dict[str, Any]]:
        """Fetch all orders for storekeeper/admin management."""
        status_priority = case(
            (Orders.status == OrdersStatus.PAID, 1),
            (Orders.status == OrdersStatus.SHIPPED, 2),
            (Orders.status == OrdersStatus.DELIVERED, 3),
            (Orders.status == OrdersStatus.CANCELLED, 4),
            else_=5,
        )

        orders_query = (
            select(Orders, Users)
            .outerjoin(Users, Users.id == Orders.user_id)
            .where(
                Orders.status.notin_(
                    [OrdersStatus.PENDING, OrdersStatus.CANCELLED, OrdersStatus.FAILED]
                )
            )
            .order_by(status_priority.asc(), Orders.id.desc())
        )
        rows = self._session.execute(orders_query).all()

        result: list[dict[str, Any]] = []
        for o, user in rows:
            items = self.get_order_items_detail(o.id, base_url)
            status_str = o.status.value if hasattr(o.status, "value") else str(o.status)
            del_type_str = (
                o.delivery_type.value if hasattr(o.delivery_type, "value") else str(o.delivery_type)
            )

            # Display-friendly status mapping for admin
            display_status = status_str
            if status_str == "paid":
                display_status = "New Order"
            elif status_str == "shipped":
                display_status = (
                    "Out for Delivery" if del_type_str == "door_delivery" else "Ready for Pickup"
                )
            elif status_str == "delivered":
                display_status = "Completed"

            result.append(
                {
                    "id": o.id,
                    "user_id": o.user_id,
                    "order_number": o.order_number,
                    "paystack_reference": o.paystack_reference,
                    "subtotal": f"{Decimal(str(o.subtotal)):.2f}",
                    "shipping_fee": f"{Decimal(str(o.shipping_fee)):.2f}",
                    "amount": f"{Decimal(str(o.amount)):.2f}",
                    "delivery_type": del_type_str,
                    "status": display_status,
                    "first_name": o.first_name,
                    "last_name": o.last_name,
                    "phone": o.phone,
                    "additional_phone": o.additional_phone,
                    "address": o.address,
                    "landmark": o.landmark,
                    "state": o.state,
                    "area": o.area,
                    "shipped_note": o.shipped_note,
                    "rider_details": o.rider_details,
                    "delivered_note": o.delivered_note,
                    "paid_at": o.paid_at.strftime("%Y-%m-%d %H:%M:%S") if o.paid_at else None,
                    "created_at": o.created_at.strftime("%Y-%m-%d %H:%M:%S")
                    if o.created_at
                    else None,
                    "updated_at": o.updated_at.strftime("%Y-%m-%d %H:%M:%S")
                    if o.updated_at
                    else None,
                    "customer_first_name": user.first_name if user else None,
                    "customer_last_name": user.last_name if user else None,
                    "customer_email": user.email if user else None,
                    "customer_phone": user.phone if user else None,
                    "items": items,
                }
            )
        return result
