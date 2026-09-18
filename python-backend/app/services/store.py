"""Service layer for Store, Products, Cart, Addresses, Delivery Zones, and Orders."""

from __future__ import annotations

import json
import secrets
import time
from datetime import UTC, datetime
from decimal import Decimal
from typing import Any

import structlog
from sqlalchemy.orm import Session

from app.authorization.policy import AuthorizationFacts, Permission, has_permission
from app.core.config import Settings
from app.integrations.mail import Mailer
from app.integrations.paystack import PaystackClient, PaystackSignatureError
from app.integrations.uploads import PreparedAvatar, ProductStorage, StoredAvatar
from app.models.generated import (
    OrdersStatus,
    ProductsStatus,
    ProductVariants,
    Users,
)
from app.repositories.store import InsufficientStockError, StoreRepository

logger = structlog.get_logger(__name__)


class StoreError(Exception):
    """An operation in the store/order family failed."""

    def __init__(self, code: str, message: str, http_status: int = 400) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


class StoreService:
    """Business logic for product catalog, shopping cart, delivery, and orders."""

    def __init__(
        self,
        session: Session,
        settings: Settings,
        paystack_client: PaystackClient | None = None,
        mail_service: Mailer | None = None,
    ) -> None:
        self._session = session
        self._settings = settings
        self._repo = StoreRepository(session)
        self._storage = ProductStorage(settings.upload_root)
        self._paystack = paystack_client or PaystackClient(
            secret_key=settings.paystack_secret_key_value(),
        )
        self._mail = mail_service

    def _base_url(self) -> str:
        if self._settings.public_base_url:
            return str(self._settings.public_base_url).rstrip("/")
        return ""

    def _require_actor_permission(self, actor_user_id: int, permission: Permission) -> None:
        actor = self._repo.lock_actor(actor_user_id)
        if not actor or not actor.get("active"):
            raise StoreError("FORBIDDEN", "You are not authorized to perform this action.", 403)
        facts = AuthorizationFacts(
            user_id=actor["id"],
            user_role=actor.get("user_role"),
            is_coordinator=bool(actor.get("is_coordinator")),
        )
        if not has_permission(facts, permission):
            raise StoreError("FORBIDDEN", "You are not authorized to perform this action.", 403)

    # ═════════════════════════════════════════════════════════════
    # PRODUCTS
    # ═════════════════════════════════════════════════════════════

    def fetch_products(self) -> tuple[list[dict[str, Any]], dict[str, int]]:
        """Fetch active products, images, and variants with meta statistics."""
        products, total_pinned = self._repo.list_active_products(base_url=self._base_url())
        meta = {
            "total_products": len(products),
            "total_pinned": total_pinned,
            "max_pinned": 4,
        }
        return products, meta

    def pin_product_item(
        self, actor_user_id: int, product_id: int, pin_item: bool
    ) -> dict[str, Any]:
        """Pin or unpin a product, enforcing maximum 4 pinned products."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        if product_id <= 0:
            raise StoreError("INVALID_PRODUCT", "Invalid product.", 400)

        product = self._repo.get_product_by_id(product_id, lock=True)
        if not product or product.status != ProductsStatus.ACTIVE:
            raise StoreError("NOT_FOUND", "Product not found.", 404)

        if pin_item and not product.pin_item:
            pinned_count = self._repo.count_active_pinned()
            if pinned_count >= 4:
                raise StoreError(
                    "PIN_LIMIT_EXCEEDED",
                    "You can only pin a maximum of 4 products. "
                    "Please unpin one before pinning another.",
                    400,
                )

        self._repo.update_product_pin(product_id, pin_item)
        self._session.commit()

        current_pinned = self._repo.count_active_pinned()
        return {
            "product_id": product_id,
            "pin_item": pin_item,
            "total_pinned": current_pinned,
            "max_pinned": 4,
        }

    def add_product(
        self,
        actor_user_id: int,
        product_name: str,
        category: str,
        price_val: str | float | Decimal,
        description: str,
        has_size: bool,
        has_color: bool,
        quantity: int | None,
        variants_json: str | list[dict[str, Any]] | None,
        images: list[PreparedAvatar],
        spotlight_index: int,
    ) -> int:
        """Create a new product with image uploads and variants."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        name = product_name.strip()
        cat = category.strip()
        desc = description.strip()

        if not name or not cat or not desc:
            raise StoreError("VALIDATION_ERROR", "Please fill all required fields.", 400)

        try:
            price = Decimal(str(price_val))
            if price < 0:
                raise ValueError
        except (ValueError, ArithmeticError):
            raise StoreError(
                "VALIDATION_ERROR", "Price must be a valid non-negative number.", 400
            ) from None

        if not has_size and not has_color:
            if quantity is None or quantity < 0:
                raise StoreError(
                    "VALIDATION_ERROR", "Quantity must be a valid non-negative number.", 400
                )
            final_quantity = quantity
        else:
            final_quantity = None

        # Validate variants
        parsed_variants: list[dict[str, Any]] = []
        if has_size or has_color:
            if isinstance(variants_json, str):
                try:
                    parsed_variants = json.loads(variants_json)
                except json.JSONDecodeError:
                    raise StoreError(
                        "VALIDATION_ERROR", "Invalid variants JSON payload.", 400
                    ) from None
            elif isinstance(variants_json, list):
                parsed_variants = variants_json

            if not parsed_variants:
                raise StoreError("VALIDATION_ERROR", "Please provide at least one variant.", 400)

            seen_combos: set[str] = set()
            for i, var in enumerate(parsed_variants):
                v_qty = var.get("quantity")
                if v_qty is None or not isinstance(v_qty, (int, float)) or int(v_qty) < 0:
                    raise StoreError("VALIDATION_ERROR", f"Invalid quantity for variant #{i}.", 400)

                color = (var.get("color") or "").strip() or None
                size = (var.get("size") or "").strip() or None

                if has_color and not color:
                    raise StoreError("VALIDATION_ERROR", f"Variant #{i} is missing a color.", 400)
                if has_size and not size:
                    raise StoreError("VALIDATION_ERROR", f"Variant #{i} is missing a size.", 400)

                combo = f"{color or ''}|{size or ''}"
                if combo in seen_combos:
                    raise StoreError(
                        "VALIDATION_ERROR", f"Duplicate variant combination at #{i}.", 400
                    )
                seen_combos.add(combo)

        # Validate images
        if not images:
            raise StoreError("VALIDATION_ERROR", "Please upload at least one product image.", 400)

        image_count = len(images)
        if spotlight_index < 0 or spotlight_index >= image_count:
            raise StoreError("VALIDATION_ERROR", "Invalid spotlight index.", 400)

        for i, var in enumerate(parsed_variants):
            if "image_index" in var and var["image_index"] is not None and var["image_index"] != "":
                idx = int(var["image_index"])
                if idx < 0 or idx >= image_count:
                    raise StoreError(
                        "VALIDATION_ERROR", f"Invalid image index for variant #{i}.", 400
                    )

        # Stage image uploads
        staged_images: list[StoredAvatar] = []
        try:
            for avatar in images:
                stored = self._storage.save(actor_user_id, avatar)
                staged_images.append(stored)

            # Insert product
            product_id = self._repo.create_product(
                user_id=actor_user_id,
                product_name=name,
                category=cat,
                price=price,
                description=desc,
                has_size=has_size,
                has_color=has_color,
                quantity=final_quantity,
            )

            # Insert product images
            img_payloads = [
                {
                    "image_path": st.relative_path,
                    "is_spotlight": 1 if idx == spotlight_index else 0,
                }
                for idx, st in enumerate(staged_images)
            ]
            image_ids = self._repo.add_product_images(product_id, img_payloads)

            # Insert variants
            if parsed_variants:
                var_payloads: list[dict[str, Any]] = []
                for var in parsed_variants:
                    img_id = None
                    if (
                        "image_index" in var
                        and var["image_index"] is not None
                        and var["image_index"] != ""
                    ):
                        idx = int(var["image_index"])
                        if 0 <= idx < len(image_ids):
                            img_id = image_ids[idx]

                    var_payloads.append(
                        {
                            "color": (var.get("color") or "").strip() or None,
                            "size": (var.get("size") or "").strip() or None,
                            "quantity": int(var.get("quantity", 0)),
                            "image_id": img_id,
                        }
                    )
                self._repo.add_product_variants(product_id, var_payloads)

            self._session.commit()
            return product_id

        except Exception:
            self._session.rollback()
            for st in staged_images:
                self._storage.delete(st)
            raise

    def edit_product(
        self,
        actor_user_id: int,
        product_id: int,
        product_name: str,
        category: str,
        price_val: str | float | Decimal,
        description: str,
        has_size: bool,
        has_color: bool,
        quantity: int | None,
        variants_json: str | list[dict[str, Any]] | None,
        delete_image_ids: list[int],
        new_images: list[PreparedAvatar],
        spotlight_image_id: int | None,
        spotlight_index: int | None,
    ) -> int:
        """Update an existing product, images, and variants."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        if product_id <= 0:
            raise StoreError("VALIDATION_ERROR", "product_id is required.", 400)

        product = self._repo.get_product_by_id(product_id, lock=True)
        if not product:
            raise StoreError("NOT_FOUND", "Product not found.", 404)

        name = product_name.strip()
        cat = category.strip()
        desc = description.strip()

        if not name or not cat or not desc:
            raise StoreError("VALIDATION_ERROR", "Please fill all required fields.", 400)

        try:
            price = Decimal(str(price_val))
            if price < 0:
                raise ValueError
        except (ValueError, ArithmeticError):
            raise StoreError(
                "VALIDATION_ERROR", "Price must be a valid non-negative number.", 400
            ) from None

        if not has_size and not has_color:
            if quantity is None or quantity < 0:
                raise StoreError(
                    "VALIDATION_ERROR", "Quantity must be a valid non-negative number.", 400
                )
            final_quantity = quantity
        else:
            final_quantity = None

        existing_images = self._repo.get_product_images(product_id)
        existing_ids = {img["id"] for img in existing_images}

        # Check delete_image_ids belong to this product
        for del_id in delete_image_ids:
            if del_id not in existing_ids:
                raise StoreError(
                    "VALIDATION_ERROR", f"Image id {del_id} does not belong to this product.", 400
                )

        remaining_existing_ids = [
            img_id for img_id in existing_ids if img_id not in delete_image_ids
        ]
        new_image_count = len(new_images)

        if not remaining_existing_ids and new_image_count == 0:
            raise StoreError(
                "VALIDATION_ERROR",
                "Product must have at least one image. Add a new image or keep an existing one.",
                400,
            )

        # Validate spotlight arguments
        if spotlight_image_id is not None and spotlight_index is not None:
            raise StoreError(
                "VALIDATION_ERROR",
                "Set only one of spotlight_image_id or spotlight_index, not both.",
                400,
            )

        if spotlight_image_id is not None and spotlight_image_id not in remaining_existing_ids:
            raise StoreError(
                "VALIDATION_ERROR", "spotlight_image_id must be a kept existing image.", 400
            )

        if spotlight_index is not None and (
            spotlight_index < 0 or spotlight_index >= new_image_count
        ):
            raise StoreError(
                "VALIDATION_ERROR",
                "spotlight_index is out of range for the newly uploaded images.",
                400,
            )

        # Validate variants
        parsed_variants: list[dict[str, Any]] = []
        if has_size or has_color:
            if isinstance(variants_json, str):
                try:
                    parsed_variants = json.loads(variants_json)
                except json.JSONDecodeError:
                    raise StoreError(
                        "VALIDATION_ERROR", "Invalid variants JSON payload.", 400
                    ) from None
            elif isinstance(variants_json, list):
                parsed_variants = variants_json

            if not parsed_variants:
                raise StoreError("VALIDATION_ERROR", "Please provide at least one variant.", 400)

            seen_combos: set[str] = set()
            for i, var in enumerate(parsed_variants):
                v_qty = var.get("quantity")
                if v_qty is None or not isinstance(v_qty, (int, float)) or int(v_qty) < 0:
                    raise StoreError("VALIDATION_ERROR", f"Invalid quantity for variant #{i}.", 400)

                color = (var.get("color") or "").strip() or None
                size = (var.get("size") or "").strip() or None

                if has_color and not color:
                    raise StoreError("VALIDATION_ERROR", f"Variant #{i} is missing a color.", 400)
                if has_size and not size:
                    raise StoreError("VALIDATION_ERROR", f"Variant #{i} is missing a size.", 400)

                combo = f"{color or ''}|{size or ''}"
                if combo in seen_combos:
                    raise StoreError(
                        "VALIDATION_ERROR", f"Duplicate variant combination at #{i}.", 400
                    )
                seen_combos.add(combo)

                has_img_id = (
                    "image_id" in var and var["image_id"] is not None and var["image_id"] != ""
                )
                has_img_idx = (
                    "image_index" in var
                    and var["image_index"] is not None
                    and var["image_index"] != ""
                )

                if has_img_id and has_img_idx:
                    raise StoreError(
                        "VALIDATION_ERROR",
                        f"Variant #{i} cannot set both image_id and image_index.",
                        400,
                    )

                if has_img_id:
                    ref_id = int(var["image_id"])
                    if ref_id not in remaining_existing_ids:
                        raise StoreError(
                            "VALIDATION_ERROR",
                            f"Variant #{i} references an image_id that is not a kept "
                            "existing image.",
                            400,
                        )

                if has_img_idx:
                    idx = int(var["image_index"])
                    if idx < 0 or idx >= new_image_count:
                        raise StoreError(
                            "VALIDATION_ERROR",
                            f"Variant #{i} references an invalid new image_index.",
                            400,
                        )

        # Stage new uploads
        staged_new_images: list[StoredAvatar] = []
        files_pending_deletion: list[str] = [
            img["image_path"] for img in existing_images if img["id"] in delete_image_ids
        ]

        try:
            for avatar in new_images:
                stored = self._storage.save(actor_user_id, avatar)
                staged_new_images.append(stored)

            # Update product row
            self._repo.update_product_row(
                product_id=product_id,
                product_name=name,
                category=cat,
                price=price,
                description=desc,
                has_size=has_size,
                has_color=has_color,
                quantity=final_quantity,
            )

            # Delete requested existing images
            if delete_image_ids:
                self._repo.delete_product_images(delete_image_ids)

            # Insert newly uploaded images
            new_image_ids: list[int] = []
            if staged_new_images:
                img_payloads = [
                    {"image_path": st.relative_path, "is_spotlight": 0} for st in staged_new_images
                ]
                new_image_ids = self._repo.add_product_images(product_id, img_payloads)

            # Resolve spotlight
            final_spotlight_id: int | None = None
            if spotlight_image_id is not None:
                final_spotlight_id = spotlight_image_id
            elif spotlight_index is not None:
                if 0 <= spotlight_index < len(new_image_ids):
                    final_spotlight_id = new_image_ids[spotlight_index]
            else:
                # Keep previous spotlight if kept
                prev_spotlight = next((img for img in existing_images if img["is_spotlight"]), None)
                if prev_spotlight and prev_spotlight["id"] in remaining_existing_ids:
                    final_spotlight_id = prev_spotlight["id"]
                elif remaining_existing_ids:
                    final_spotlight_id = remaining_existing_ids[0]
                elif new_image_ids:
                    final_spotlight_id = new_image_ids[0]

            if final_spotlight_id is not None:
                self._repo.set_spotlight_image(product_id, final_spotlight_id)

            # Replace variants
            self._repo.delete_product_variants(product_id)
            if parsed_variants:
                var_payloads: list[dict[str, Any]] = []
                for var in parsed_variants:
                    variant_image_ref: int | None = None
                    if "image_id" in var and var["image_id"] is not None and var["image_id"] != "":
                        variant_image_ref = int(var["image_id"])
                    elif (
                        "image_index" in var
                        and var["image_index"] is not None
                        and var["image_index"] != ""
                    ):
                        idx = int(var["image_index"])
                        if 0 <= idx < len(new_image_ids):
                            variant_image_ref = new_image_ids[idx]

                    var_payloads.append(
                        {
                            "color": (var.get("color") or "").strip() or None,
                            "size": (var.get("size") or "").strip() or None,
                            "quantity": int(var.get("quantity", 0)),
                            "image_id": variant_image_ref,
                        }
                    )
                self._repo.add_product_variants(product_id, var_payloads)

            self._session.commit()

            # Physical cleanup of deleted images
            for path in files_pending_deletion:
                self._storage.delete(path)

            return product_id

        except Exception:
            self._session.rollback()
            for st in staged_new_images:
                self._storage.delete(st)
            raise

    def delete_product(self, actor_user_id: int, product_id: int) -> None:
        """Delete product and its variants/images, cleaning up image files."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        if product_id <= 0:
            raise StoreError("VALIDATION_ERROR", "product_id is required.", 400)

        product = self._repo.get_product_by_id(product_id, lock=True)
        if not product:
            raise StoreError("NOT_FOUND", "Product not found.", 404)

        image_paths = self._repo.delete_product_cascade(product_id)
        self._session.commit()

        for path in image_paths:
            self._storage.delete(path)

    # ═════════════════════════════════════════════════════════════
    # CART
    # ═════════════════════════════════════════════════════════════

    def fetch_cart(self, user_id: int) -> dict[str, Any]:
        """Fetch active shopping cart for user."""
        return self._repo.get_cart_data(user_id, base_url=self._base_url())

    def add_to_cart(
        self, user_id: int, product_id: int, variant_id: int | None, quantity: int
    ) -> dict[str, Any]:
        """Add product or SKU variant to user active cart."""
        if product_id <= 0 or quantity <= 0:
            raise StoreError("VALIDATION_ERROR", "Invalid request.", 400)

        product = self._repo.get_product_by_id(product_id)
        if not product or product.status != ProductsStatus.ACTIVE:
            raise StoreError("NOT_FOUND", "Product not found.", 404)

        unit_price = Decimal(str(product.price))

        if product.has_color or product.has_size:
            if not variant_id:
                raise StoreError("VALIDATION_ERROR", "Please select a product variant.", 400)
            var = self._session.get(ProductVariants, variant_id)
            if not var or var.product_id != product_id:
                raise StoreError("NOT_FOUND", "Variant not found.", 404)
            if var.quantity < quantity:
                raise StoreError("INSUFFICIENT_STOCK", "Insufficient stock.", 400)
            stock_limit = var.quantity
        else:
            p_qty = product.quantity or 0
            if p_qty < quantity:
                raise StoreError("INSUFFICIENT_STOCK", "Insufficient stock.", 400)
            stock_limit = p_qty

        cart_id = self._repo.get_or_create_active_cart(user_id, lock=True)

        existing_item = self._repo.get_cart_item(cart_id, product_id, variant_id)
        if existing_item:
            new_qty = existing_item.quantity + quantity
            if new_qty > stock_limit:
                raise StoreError("INSUFFICIENT_STOCK", "Cannot add more than available stock.", 400)

        self._repo.upsert_cart_item(cart_id, product_id, variant_id, unit_price, quantity)
        self._session.commit()

        return self._repo.get_cart_data(user_id, base_url=self._base_url())

    def update_cart(self, user_id: int, cart_item_id: int, quantity: int) -> dict[str, Any]:
        """Update cart item quantity, removing if quantity is zero."""
        if cart_item_id <= 0:
            raise StoreError("VALIDATION_ERROR", "Invalid cart item.", 400)

        if quantity == 0:
            return self.remove_from_cart(user_id, cart_item_id)

        if quantity < 0:
            raise StoreError("VALIDATION_ERROR", "Quantity must be 0 or greater.", 400)

        item_ctx = self._repo.get_cart_item_by_id(cart_item_id)
        if not item_ctx:
            raise StoreError("NOT_FOUND", "Cart item not found.", 404)

        cart_item, owner_id = item_ctx
        if owner_id != user_id:
            raise StoreError("UNAUTHORIZED", "Unauthorized.", 403)

        product = self._repo.get_product_by_id(cart_item.product_id)
        if not product or product.status != ProductsStatus.ACTIVE:
            raise StoreError("NOT_FOUND", "Product not available.", 404)

        if cart_item.variant_id:
            var = self._session.get(ProductVariants, cart_item.variant_id)
            if not var or var.quantity < quantity:
                raise StoreError("INSUFFICIENT_STOCK", "Cannot exceed available stock.", 400)
        else:
            if (product.quantity or 0) < quantity:
                raise StoreError("INSUFFICIENT_STOCK", "Cannot exceed available stock.", 400)

        self._repo.update_cart_item_quantity(
            cart_item_id, quantity, Decimal(str(cart_item.unit_price))
        )
        self._session.commit()

        return self._repo.get_cart_data(user_id, base_url=self._base_url())

    def remove_from_cart(self, user_id: int, cart_item_id: int) -> dict[str, Any]:
        """Remove an item from active cart."""
        if cart_item_id <= 0:
            raise StoreError("VALIDATION_ERROR", "Invalid cart item.", 400)

        item_ctx = self._repo.get_cart_item_by_id(cart_item_id)
        if not item_ctx:
            raise StoreError("NOT_FOUND", "Cart item not found.", 404)

        _, owner_id = item_ctx
        if owner_id != user_id:
            raise StoreError("UNAUTHORIZED", "Unauthorized.", 403)

        self._repo.delete_cart_item(cart_item_id)
        self._session.commit()

        return self._repo.get_cart_data(user_id, base_url=self._base_url())

    def clear_cart(self, user_id: int) -> dict[str, Any]:
        """Empty all items and delete the active cart."""
        self._repo.clear_active_cart(user_id)
        self._session.commit()
        return {
            "cart_id": None,
            "total_items": 0,
            "subtotal": "0.00",
            "items": [],
        }

    # ═════════════════════════════════════════════════════════════
    # ADDRESSES & DELIVERY ZONES
    # ═════════════════════════════════════════════════════════════

    def add_address(self, user_id: int, data: dict[str, Any]) -> tuple[int, list[dict[str, Any]]]:
        """Add new delivery address for user."""
        fn = (data.get("first_name") or "").strip()
        ln = (data.get("last_name") or "").strip()
        ph = (data.get("phone") or "").strip()
        addr = (data.get("address") or "").strip()
        st = (data.get("state") or "").strip()
        area = (data.get("area") or "").strip()

        if not fn or not ln or not ph or not addr or not st or not area:
            raise StoreError("VALIDATION_ERROR", "Please fill all required address fields.", 400)

        addr_id = self._repo.create_address(
            user_id,
            {
                "first_name": fn,
                "last_name": ln,
                "phone": ph,
                "additional_phone": (data.get("additional_phone") or "").strip() or None,
                "address": addr,
                "landmark": (data.get("landmark") or "").strip() or None,
                "state": st,
                "area": area,
            },
        )
        self._session.commit()
        addresses = self._repo.list_user_addresses(user_id)
        return addr_id, addresses

    def fetch_addresses(self, user_id: int) -> list[dict[str, Any]]:
        """List all addresses for user."""
        return self._repo.list_user_addresses(user_id)

    def edit_address(
        self, user_id: int, address_id: int, data: dict[str, Any]
    ) -> list[dict[str, Any]]:
        """Edit an existing address for user."""
        fn = (data.get("first_name") or "").strip()
        ln = (data.get("last_name") or "").strip()
        ph = (data.get("phone") or "").strip()
        addr = (data.get("address") or "").strip()
        st = (data.get("state") or "").strip()
        area = (data.get("area") or "").strip()

        if not fn or not ln or not ph or not addr or not st or not area:
            raise StoreError("VALIDATION_ERROR", "Please fill all required address fields.", 400)

        ok = self._repo.update_address(
            user_id,
            address_id,
            {
                "first_name": fn,
                "last_name": ln,
                "phone": ph,
                "additional_phone": (data.get("additional_phone") or "").strip() or None,
                "address": addr,
                "landmark": (data.get("landmark") or "").strip() or None,
                "state": st,
                "area": area,
            },
        )
        if not ok:
            raise StoreError("NOT_FOUND", "Address not found.", 404)

        self._session.commit()
        return self._repo.list_user_addresses(user_id)

    def delete_address(self, user_id: int, address_id: int) -> None:
        """Delete user address."""
        if address_id <= 0:
            raise StoreError("VALIDATION_ERROR", "address_id is required.", 400)
        ok = self._repo.delete_address(user_id, address_id)
        if not ok:
            raise StoreError("NOT_FOUND", "Address not found.", 404)
        self._session.commit()

    def set_default_address(self, user_id: int, address_id: int) -> None:
        """Set address as default for user."""
        if address_id <= 0:
            raise StoreError("VALIDATION_ERROR", "address_id is required.", 400)
        ok = self._repo.set_default_address(user_id, address_id)
        if not ok:
            raise StoreError("NOT_FOUND", "Address not found.", 404)
        self._session.commit()

    def fetch_delivery_zones(self) -> list[dict[str, Any]]:
        """Fetch all delivery zones grouped by state."""
        return self._repo.list_delivery_zones_grouped()

    # ═════════════════════════════════════════════════════════════
    # CHECKOUT & PAYSTACK ORDERS
    # ═════════════════════════════════════════════════════════════

    def initiate_checkout(
        self,
        user_id: int,
        user_email: str,
        payload: dict[str, Any],
    ) -> dict[str, Any]:
        """Initiate checkout, snapshot active cart, and create pending order with Paystack."""
        raw_del = (payload.get("delivery_type") or "").strip()
        delivery_type = {
            "delivery": "door_delivery",
            "door_delivery": "door_delivery",
            "pickup": "self_pickup",
            "self_pickup": "self_pickup",
        }.get(raw_del, raw_del)
        if delivery_type not in ("door_delivery", "self_pickup"):
            raise StoreError(
                "VALIDATION_ERROR", 'delivery_type must be "door_delivery" or "self_pickup".', 400
            )

        # Cancel any previous pending orders
        self._repo.cancel_pending_orders_for_user(user_id)

        address_info: dict[str, Any] = {}
        shipping_fee = Decimal("0.00")

        if delivery_type == "door_delivery":
            address_id = payload.get("address_id")
            if address_id:
                saved = self._repo.get_user_address(user_id, int(address_id))
                if not saved:
                    raise StoreError("NOT_FOUND", "Saved address not found.", 404)
                address_info = {
                    "first_name": saved.first_name,
                    "last_name": saved.last_name,
                    "phone": saved.phone,
                    "additional_phone": saved.additional_phone,
                    "address": saved.address,
                    "landmark": saved.landmark,
                    "state": saved.state,
                    "area": saved.area,
                }
            else:
                fn = (payload.get("first_name") or "").strip()
                ln = (payload.get("last_name") or "").strip()
                ph = (payload.get("phone") or "").strip()
                addr = (payload.get("address") or "").strip()
                st = (payload.get("state") or "").strip()
                area = (payload.get("area") or "").strip()

                if not fn or not ln or not ph or not addr or not st or not area:
                    raise StoreError(
                        "VALIDATION_ERROR", "Please provide all required delivery details.", 400
                    )

                address_info = {
                    "first_name": fn,
                    "last_name": ln,
                    "phone": ph,
                    "additional_phone": (payload.get("additional_phone") or "").strip() or None,
                    "address": addr,
                    "landmark": (payload.get("landmark") or "").strip() or None,
                    "state": st,
                    "area": area,
                }

            fee = self._repo.find_delivery_fee(address_info["state"], address_info.get("area"))
            if fee is None:
                raise StoreError(
                    "DELIVERY_UNAVAILABLE",
                    f"Delivery is not available to {address_info.get('area')}, "
                    f"{address_info['state']} yet. "
                    "Please choose a different area or select Self Pickup.",
                    400,
                )
            shipping_fee = fee

        # Load active cart
        cart_data = self._repo.get_cart_data(user_id)
        if not cart_data["cart_id"] or not cart_data["items"]:
            raise StoreError("CART_EMPTY", "Your cart is empty.", 400)

        cart_id = cart_data["cart_id"]

        # Validate stock for all items
        subtotal = Decimal("0.00")
        for item in cart_data["items"]:
            product = self._repo.get_product_by_id(item["product_id"])
            if not product or product.status != ProductsStatus.ACTIVE:
                raise StoreError(
                    "UNAVAILABLE",
                    f"'{item['product_name']}' is no longer available. "
                    "Please remove it from your cart.",
                    400,
                )

            if item["variant"]:
                var_id = item["variant"]["id"]
                var = self._session.get(ProductVariants, var_id)
                if not var or var.quantity < item["quantity"]:
                    label = (
                        f"{item['variant'].get('color') or ''} / "
                        f"{item['variant'].get('size') or ''}"
                    ).strip(" /")
                    rem = var.quantity if var else 0
                    raise StoreError(
                        "INSUFFICIENT_STOCK",
                        f"Insufficient stock for '{item['product_name']}' ({label}). "
                        f"Only {rem} left.",
                        400,
                    )
            else:
                rem = product.quantity or 0
                if rem < item["quantity"]:
                    raise StoreError(
                        "INSUFFICIENT_STOCK",
                        f"Insufficient stock for '{item['product_name']}'. Only {rem} left.",
                        400,
                    )

            subtotal += Decimal(item["subtotal"])

        total_amount = subtotal + shipping_fee
        amount_kobo = round(total_amount * 100)

        # Generate unique reference and order number
        reference = f"ORD-{secrets.token_hex(8).upper()}-{int(time.time())}"
        order_number = f"ORD-{secrets.token_hex(8).upper()}"

        # Initialize Paystack
        paystack_meta = {
            "user_id": user_id,
            "cart_id": cart_id,
            "delivery_type": delivery_type,
            "shipping_fee": float(shipping_fee),
        }
        paystack_res = self._paystack.initialize_transaction(
            email=user_email,
            amount_kobo=amount_kobo,
            reference=reference,
            metadata=paystack_meta,
        )

        access_code = paystack_res["access_code"]

        # Insert pending order and snapshot items
        order_id = self._repo.create_pending_order(
            user_id=user_id,
            cart_id=cart_id,
            order_number=order_number,
            paystack_reference=reference,
            access_code=access_code,
            subtotal=subtotal,
            shipping_fee=shipping_fee,
            total_amount=total_amount,
            delivery_type=delivery_type,
            address_info=address_info,
        )
        self._repo.snapshot_order_items(order_id, cart_id)
        self._session.commit()

        return {
            "status": True,
            "reference": reference,
            "order_number": order_number,
            "access_code": access_code,
            "subtotal": f"{subtotal:.2f}",
            "shipping_fee": f"{shipping_fee:.2f}",
            "delivery_fee": f"{shipping_fee:.2f}",
            "amount": f"{total_amount:.2f}",
            "order_id": order_id,
        }

    def verify_payment(self, user_id: int, reference: str) -> dict[str, Any]:
        """Verify payment with Paystack and finalize order idempotently."""
        ref = reference.strip()
        if not ref:
            raise StoreError("VALIDATION_ERROR", "Payment reference is required.", 400)

        order = self._repo.get_order_by_reference(ref, lock=True)
        if not order:
            raise StoreError("NOT_FOUND", "Order not found.", 404)

        if order.user_id != user_id:
            raise StoreError("FORBIDDEN", "Unauthorized.", 403)

        del_type_str = (
            order.delivery_type.value
            if hasattr(order.delivery_type, "value")
            else str(order.delivery_type)
        )

        # Idempotent return if already paid
        if order.status in (
            OrdersStatus.PAID,
            OrdersStatus.PROCESSING,
            OrdersStatus.SHIPPED,
            OrdersStatus.DELIVERED,
        ):
            return {
                "status": True,
                "order_id": order.id,
                "order_number": order.order_number,
                "reference": ref,
                "subtotal": f"{Decimal(str(order.subtotal)):.2f}",
                "shipping_fee": f"{Decimal(str(order.shipping_fee)):.2f}",
                "amount": f"{Decimal(str(order.amount)):.2f}",
                "payment_status": "paid",
                "delivery_type": del_type_str,
            }

        if order.status == OrdersStatus.FAILED:
            raise StoreError(
                "PAYMENT_FAILED", "This payment was unsuccessful. Please try again.", 400
            )

        # Verify with Paystack
        tx_data = self._paystack.verify_transaction(ref)
        payload_data = (
            tx_data["data"] if "data" in tx_data and isinstance(tx_data["data"], dict) else tx_data
        )

        tx_status = payload_data.get("status")
        tx_amount_kobo = payload_data.get("amount") or 0
        expected_kobo = round(Decimal(str(order.amount)) * 100)

        if tx_status != "success":
            self._repo.mark_order_failed(order, json.dumps(tx_data))
            self._session.commit()
            raise StoreError("PAYMENT_FAILED", "Payment was not successful. Please try again.", 400)

        if tx_amount_kobo != expected_kobo:
            logger.error(
                "Paystack amount mismatch",
                reference=ref,
                expected_kobo=expected_kobo,
                paid_kobo=tx_amount_kobo,
            )
            self._repo.mark_order_failed(order, json.dumps(tx_data))
            self._session.commit()
            raise StoreError(
                "AMOUNT_MISMATCH",
                f"Payment amount mismatch. Please contact support with reference: {ref}",
                400,
            )

        # Atomically finalize order
        try:
            self._repo.finalize_order_atomic(order, json.dumps(tx_data))
        except InsufficientStockError:
            self._session.rollback()
            raise StoreError(
                "INSUFFICIENT_STOCK",
                "An item in this order is no longer in stock. "
                "Your payment was not completed; please contact support.",
                409,
            ) from None
        self._session.commit()

        return {
            "status": True,
            "order_id": order.id,
            "order_number": order.order_number,
            "reference": ref,
            "subtotal": f"{Decimal(str(order.subtotal)):.2f}",
            "shipping_fee": f"{Decimal(str(order.shipping_fee)):.2f}",
            "amount": f"{Decimal(str(order.amount)):.2f}",
            "payment_status": "paid",
            "delivery_type": del_type_str,
        }

    def process_paystack_webhook(self, raw_body: bytes, signature: str) -> bool:
        """Process Paystack charge.success webhook with signature verification."""
        if not self._paystack.verify_webhook_signature(raw_body, signature):
            raise PaystackSignatureError("Invalid signature")

        try:
            event = json.loads(raw_body)
        except json.JSONDecodeError:
            return True

        if event.get("event") != "charge.success":
            return True

        tx_data = event.get("data") or {}
        ref = tx_data.get("reference")
        if not ref:
            return True

        order = self._repo.get_order_by_reference(ref, lock=True)
        if not order or order.status != OrdersStatus.PENDING:
            return True

        tx_status = tx_data.get("status")
        tx_amount_kobo = tx_data.get("amount") or 0
        expected_kobo = round(Decimal(str(order.amount)) * 100)

        if tx_status == "success" and tx_amount_kobo == expected_kobo:
            try:
                self._repo.finalize_order_atomic(order, json.dumps(tx_data))
            except InsufficientStockError:
                self._session.rollback()
                logger.error("paystack_webhook_stock_shortfall", reference=ref)
                return True
        else:
            self._repo.mark_order_failed(order, json.dumps(tx_data))

        self._session.commit()
        return True

    def fetch_user_orders(self, user_id: int) -> list[dict[str, Any]]:
        """Fetch customer's non-pending orders."""
        return self._repo.list_user_orders(user_id, base_url=self._base_url())

    def view_order_details(self, user_id: int, order_number: str) -> dict[str, Any]:
        """View single order details for customer."""
        num = order_number.strip()
        if not num:
            raise StoreError("VALIDATION_ERROR", "Invalid order.", 400)

        order = self._repo.get_order_by_number(num, user_id=user_id)
        if not order or order.status in (
            OrdersStatus.PENDING,
            OrdersStatus.CANCELLED,
            OrdersStatus.FAILED,
        ):
            raise StoreError("NOT_FOUND", "Order not found.", 404)

        items = self._repo.get_order_items_detail(order.id, base_url=self._base_url())
        status_str = order.status.value if hasattr(order.status, "value") else str(order.status)
        del_type_str = (
            order.delivery_type.value
            if hasattr(order.delivery_type, "value")
            else str(order.delivery_type)
        )

        display_status = status_str
        if status_str == "paid":
            display_status = "New Order"
        elif status_str == "shipped":
            display_status = (
                "Out for Delivery" if del_type_str == "door_delivery" else "Ready for Pickup"
            )
        elif status_str == "delivered":
            display_status = "Completed"

        return {
            "id": order.id,
            "user_id": order.user_id,
            "order_number": order.order_number,
            "paystack_reference": order.paystack_reference,
            "subtotal": f"{Decimal(str(order.subtotal)):.2f}",
            "shipping_fee": f"{Decimal(str(order.shipping_fee)):.2f}",
            "amount": f"{Decimal(str(order.amount)):.2f}",
            "delivery_type": del_type_str,
            "status": display_status,
            "first_name": order.first_name,
            "last_name": order.last_name,
            "phone": order.phone,
            "additional_phone": order.additional_phone,
            "address": order.address,
            "landmark": order.landmark,
            "state": order.state,
            "area": order.area,
            "shipped_note": order.shipped_note,
            "rider_details": order.rider_details,
            "delivered_note": order.delivered_note,
            "paid_at": order.paid_at.strftime("%Y-%m-%d %H:%M:%S") if order.paid_at else None,
            "created_at": order.created_at.strftime("%Y-%m-%d %H:%M:%S")
            if order.created_at
            else None,
            "updated_at": order.updated_at.strftime("%Y-%m-%d %H:%M:%S")
            if order.updated_at
            else None,
            "items": items,
        }

    def order_management(self, actor_user_id: int) -> list[dict[str, Any]]:
        """Fetch all orders for store admin."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)
        return self._repo.list_admin_orders(base_url=self._base_url())

    def manage_order_details(self, actor_user_id: int, order_number: str) -> dict[str, Any]:
        """View single order details for admin across any user."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        num = order_number.strip()
        if not num:
            raise StoreError("VALIDATION_ERROR", "Invalid order.", 400)

        order = self._repo.get_order_by_number(num)
        if not order or order.status in (
            OrdersStatus.PENDING,
            OrdersStatus.CANCELLED,
            OrdersStatus.FAILED,
        ):
            raise StoreError("NOT_FOUND", "Order not found.", 404)

        items = self._repo.get_order_items_detail(order.id, base_url=self._base_url())
        status_str = order.status.value if hasattr(order.status, "value") else str(order.status)
        del_type_str = (
            order.delivery_type.value
            if hasattr(order.delivery_type, "value")
            else str(order.delivery_type)
        )

        display_status = status_str
        if status_str == "paid":
            display_status = "New Order"
        elif status_str == "shipped":
            display_status = (
                "Out for Delivery" if del_type_str == "door_delivery" else "Ready for Pickup"
            )
        elif status_str == "delivered":
            display_status = "Completed"

        # Lookup customer
        customer = self._session.get(Users, order.user_id)

        return {
            "id": order.id,
            "user_id": order.user_id,
            "order_number": order.order_number,
            "paystack_reference": order.paystack_reference,
            "subtotal": f"{Decimal(str(order.subtotal)):.2f}",
            "shipping_fee": f"{Decimal(str(order.shipping_fee)):.2f}",
            "amount": f"{Decimal(str(order.amount)):.2f}",
            "delivery_type": del_type_str,
            "status": display_status,
            "first_name": order.first_name,
            "last_name": order.last_name,
            "phone": order.phone,
            "additional_phone": order.additional_phone,
            "address": order.address,
            "landmark": order.landmark,
            "state": order.state,
            "area": order.area,
            "shipped_note": order.shipped_note,
            "rider_details": order.rider_details,
            "delivered_note": order.delivered_note,
            "paid_at": order.paid_at.strftime("%Y-%m-%d %H:%M:%S") if order.paid_at else None,
            "created_at": order.created_at.strftime("%Y-%m-%d %H:%M:%S")
            if order.created_at
            else None,
            "updated_at": order.updated_at.strftime("%Y-%m-%d %H:%M:%S")
            if order.updated_at
            else None,
            "customer_first_name": customer.first_name if customer else None,
            "customer_last_name": customer.last_name if customer else None,
            "customer_email": customer.email if customer else None,
            "customer_phone": customer.phone if customer else None,
            "items": items,
        }

    def update_order_status(
        self,
        actor_user_id: int,
        order_id: int,
        status: str,
        note: str = "",
        rider_details: str = "",
    ) -> dict[str, Any]:
        """Update order status with state machine guards."""
        self._require_actor_permission(actor_user_id, Permission.MANAGE_STORE)

        if order_id <= 0:
            raise StoreError("VALIDATION_ERROR", "Invalid order.", 400)

        target_status = status.lower().strip()
        if target_status not in ("shipped", "delivered"):
            raise StoreError("VALIDATION_ERROR", "Invalid status.", 400)

        order = self._repo.get_order_by_id(order_id, lock=True)
        if not order:
            raise StoreError("NOT_FOUND", "Order not found.", 404)

        curr_status = order.status.value if hasattr(order.status, "value") else str(order.status)
        del_type = (
            order.delivery_type.value
            if hasattr(order.delivery_type, "value")
            else str(order.delivery_type)
        )

        if curr_status == "pending":
            raise StoreError("INVALID_STATE", "This order has not been paid for.", 400)
        if curr_status == "cancelled":
            raise StoreError("INVALID_STATE", "Cancelled orders cannot be updated.", 400)
        if curr_status == "delivered":
            raise StoreError("INVALID_STATE", "This order has already been completed.", 400)
        if curr_status == target_status:
            raise StoreError("INVALID_STATE", f"Order is already marked as {target_status}.", 400)
        if curr_status == "paid" and target_status == "delivered":
            raise StoreError(
                "INVALID_STATE", "Order must be shipped before it can be completed.", 400
            )

        clean_rider = rider_details.strip()
        if (
            curr_status == "paid"
            and target_status == "shipped"
            and del_type == "door_delivery"
            and not clean_rider
        ):
            raise StoreError(
                "VALIDATION_ERROR", "Rider details are required for door delivery.", 400
            )

        display_status = ""
        if target_status == "shipped":
            display_status = (
                "Out for Delivery" if del_type == "door_delivery" else "Ready for Pickup"
            )
        elif target_status == "delivered":
            display_status = "Completed"

        clean_note = note.strip()
        now = datetime.now(UTC).replace(tzinfo=None)

        order.status = (
            OrdersStatus.SHIPPED if target_status == "shipped" else OrdersStatus.DELIVERED
        )
        order.updated_at = now

        if target_status == "shipped":
            if clean_note:
                order.shipped_note = clean_note
            if del_type == "door_delivery" and clean_rider:
                order.rider_details = clean_rider
        elif target_status == "delivered":
            if clean_note:
                order.delivered_note = clean_note
            order.completion_date = now

        self._session.commit()

        # Send status email to customer
        customer = self._session.get(Users, order.user_id)
        if customer and customer.email and self._mail:
            try:
                fullname = f"{customer.first_name} {customer.last_name}".strip()
                email_status = ""
                if target_status == "shipped":
                    email_status = "out_for_delivery" if del_type == "door_delivery" else "pickup"
                elif target_status == "delivered":
                    email_status = "completed"

                self._mail.send_order_status_email(
                    customer.email,
                    fullname,
                    order.order_number,
                    email_status,
                    del_type,
                    clean_note,
                    clean_rider
                    if (target_status == "shipped" and del_type == "door_delivery")
                    else (order.rider_details or ""),
                )
            except Exception:
                logger.error("Failed to send order status email", exc_info=True)

        return {
            "order_id": order.id,
            "order_number": order.order_number,
            "status": target_status,
            "display_status": display_status,
            "note": clean_note,
            "rider_details": clean_rider
            if (target_status == "shipped" and del_type == "door_delivery")
            else order.rider_details,
        }
