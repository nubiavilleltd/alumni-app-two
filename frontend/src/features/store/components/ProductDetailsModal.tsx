import { useEffect, useRef, useCallback } from 'react';
import { ChevronUp, ChevronDown, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useCart } from '../hooks/useCart';
import { useCartStore } from '../stores/useCartStore';
import { useProductModalStore } from '../stores/useProductModalStore';
import { useProductSelection } from '../hooks/useProductSelection';
import { ProductColorSelector } from './ProductColorSelector';
import { ProductSizeSelector } from './ProductSizeSelector';
import { ProductQuantitySelector } from './ProductQuantitySelector';
import { toast } from '@/shared/components/ui/Toast';

interface StripProps {
  images: string[];
  activeIndex: number;
  onSelect: (index: number) => void;
}

function VerticalStrip({ images, activeIndex, onSelect }: StripProps) {
  const trackRef = useRef<HTMLDivElement>(null);
  const itemRefs = useRef<(HTMLButtonElement | null)[]>([]);

  useEffect(() => {
    itemRefs.current[activeIndex]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, [activeIndex]);

  const scrollTrack = (dir: 'up' | 'down') => {
    trackRef.current?.scrollBy({ top: dir === 'up' ? -90 : 90, behavior: 'smooth' });
  };

  if (images.length === 0) return null;

  return (
    <div className="flex flex-col items-center gap-1 w-[80px] shrink-0">
      <button
        onClick={() => scrollTrack('up')}
        className="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Scroll up"
      >
        <ChevronUp size={18} />
      </button>
      <div
        ref={trackRef}
        className="flex flex-col gap-2 overflow-y-auto"
        style={{ scrollbarWidth: 'none', msOverflowStyle: 'none', maxHeight: '320px' }}
      >
        {images.map((img, i) => (
          <button
            key={i}
            ref={(el) => {
              itemRefs.current[i] = el;
            }}
            onClick={() => onSelect(i)}
            className={`w-[72px] h-[72px] shrink-0 rounded-xl overflow-hidden border transition-all ${i === activeIndex
                ? 'border-primary-900 opacity-100'
                : 'border-gray-200 opacity-60 hover:opacity-90'
              }`}
          >
            <img src={img} alt={`View ${i + 1}`} className="w-full h-full object-cover" />
          </button>
        ))}
      </div>
      <button
        onClick={() => scrollTrack('down')}
        className="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Scroll down"
      >
        <ChevronDown size={18} />
      </button>
    </div>
  );
}

function HorizontalStrip({ images, activeIndex, onSelect }: StripProps) {
  const trackRef = useRef<HTMLDivElement>(null);
  const itemRefs = useRef<(HTMLButtonElement | null)[]>([]);

  useEffect(() => {
    const el = itemRefs.current[activeIndex];
    if (el && trackRef.current) {
      const track = trackRef.current;
      track.scrollTo({
        left: el.offsetLeft - track.offsetWidth / 2 + el.offsetWidth / 2,
        behavior: 'smooth',
      });
    }
  }, [activeIndex]);

  const scrollTrack = (dir: 'left' | 'right') => {
    trackRef.current?.scrollBy({ left: dir === 'left' ? -90 : 90, behavior: 'smooth' });
  };

  if (images.length === 0) return null;

  return (
    <div className="flex items-center gap-1">
      <button
        onClick={() => scrollTrack('left')}
        className="w-7 h-7 shrink-0 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Scroll left"
      >
        <ChevronLeft size={18} />
      </button>
      <div
        ref={trackRef}
        className="flex gap-2 overflow-x-auto flex-1"
        style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
      >
        {images.map((img, i) => (
          <button
            key={i}
            ref={(el) => {
              itemRefs.current[i] = el;
            }}
            onClick={() => onSelect(i)}
            className={`w-[64px] h-[64px] shrink-0 rounded-xl overflow-hidden border transition-all ${i === activeIndex
                ? 'border-primary-900 opacity-100'
                : 'border-gray-200 opacity-60 hover:opacity-90'
              }`}
          >
            <img src={img} alt={`View ${i + 1}`} className="w-full h-full object-cover" />
          </button>
        ))}
      </div>
      <button
        onClick={() => scrollTrack('right')}
        className="w-7 h-7 shrink-0 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Scroll right"
      >
        <ChevronRight size={18} />
      </button>
    </div>
  );
}

export function ProductDetailsModal() {
  const { isOpen, close, product, mode, cartItem } = useProductModalStore();
  const { addItem, updateItem } = useCart();
  const storeItems = useCartStore((s) => s.items);

  const DEFAULT_NOTE =
    'A portion of proceeds from every purchase supports alumni programs, events, and community initiatives.';

  const {
    selectedColor,
    setSelectedColor,
    selectedSize,
    setSelectedSize,
    quantity,
    setQuantity,
    imageIndex,
    setImageIndex,
    activeVariant,
    sizes,
    maxStock,
    rawStock,
    canAddToCart,
    allImages,
  } = useProductSelection(product, { cartItem, allCartItems: storeItems });

  useEffect(() => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  const handleColorChange = useCallback(
    (color: string) => {
      setSelectedColor(color);
      if (!product) return;
      setImageIndex(product.generalImages?.length ?? 0);
    },
    [product, setSelectedColor, setImageIndex],
  );

  const handleAddToCart = async () => {
    if (!product) return;

    const variantId = activeVariant?.variantId;
    const itemId = variantId ? `${product.id}-${variantId}` : `${product.id}-novariant`;

    const payload = {
      id: itemId,
      productId: product.id,
      variantId,
      productName: product.name,
      image: activeVariant?.image ?? product.image,
      price: product.price,
      quantity,
      color: selectedColor !== 'Default' ? selectedColor : undefined,
      size: product.hasSizes ? selectedSize : undefined,
    };

    if (mode === 'edit' && cartItem) {
      try {
        await updateItem(cartItem.id, quantity);
        toast.success('Cart updated successfully');
        close();
      } catch (error) {
        toast.error('Failed to update cart item');
      }
    } else {
      try {
        await addItem(payload);
        toast.success('Item added to cart');
        close();
      } catch (error) {
        toast.error('Failed to add item to cart');
      }
    }
  };

  if (!isOpen || !product) return null;

  const mainImage = allImages[imageIndex] ?? allImages[0] ?? product.image;

  return (
    <div
      className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm overflow-y-auto"
      onClick={close}
    >
      <div className="flex min-h-full items-start sm:items-center justify-center p-3 sm:p-4">
        <div
          className="relative w-full max-w-5xl bg-white rounded-2xl shadow-2xl"
          onClick={(e) => e.stopPropagation()}
        >
          <button
            onClick={close}
            className="absolute top-3 right-3 z-20 w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-600 transition-colors"
            aria-label="Close"
          >
            <X size={25}  className='text-primary-500'/>
          </button>

          {/* Desktop: three-column */}
          <div className="hidden sm:flex flex-row items-start p-5 gap-5">
            <VerticalStrip images={allImages} activeIndex={imageIndex} onSelect={setImageIndex} />

            <div className="flex-1 min-w-0 max-w-[380px] h-[320px] flex items-center justify-center rounded-xl">
              <img
                src={mainImage}
                alt={product.name}
                className="w-full h-full object-contain rounded-xl p-2"
              />
            </div>
            <div className="w-[60%] shrink-0 flex flex-col gap-3">
              <span className="inline-flex w-fit bg-primary-100 text-primary-600 text-xs font-semibold px-3 py-1.5 rounded-xl">
                {product.category}
              </span>
              <h2 className="text-xl font-bold text-gray-900 leading-snug">{product.name}</h2>
              {product.description && (
                <p className="text-sm text-gray-600 leading-snug">{product.description}</p>
              )}
              <p className="text-2xl font-bold text-gray-900">₦{product.price.toLocaleString()}</p>
              <p className="text-xs text-gray-400 italic leading-relaxed">{DEFAULT_NOTE}</p>
            </div>
          </div>
          {/* Mobile: product info */}
          {/* ── MOBILE: stacked layout ────────────────────────────────── */}
          <div className="sm:hidden mt-7">
            {/* Main image first */}
            <div className="px-4 pt-4">
              <div className="rounded-xl flex items-center justify-center h-[240px]">
                <img
                  src={mainImage}
                  alt={product.name}
                  className="w-full h-full max-h-[240px] object-contain rounded-xl p-2"
                />
              </div>
            </div>

            {/* Horizontal strip BELOW the image */}
            <div className="px-4 pt-3 pb-1">
              <HorizontalStrip
                images={allImages}
                activeIndex={imageIndex}
                onSelect={setImageIndex}
              />
            </div>

            {/* Product info below strip */}
            <div className="px-4 pt-3 pb-2 flex flex-col gap-2">
              <span className="inline-flex w-fit bg-primary-100 text-primary-600 text-xs font-semibold px-3 py-1.5 rounded-xl">
                {product.category}
              </span>
              <h2 className="text-lg text-gray-900 leading-snug">{product.name}</h2>
              {product.description && (
                <p className="text-sm text-gray-600 leading-snug">{product.description}</p>
              )}
              <p className="text-xl font-bold text-gray-900">₦{product.price.toLocaleString()}</p>
              {/* {product.supportNote && <p className="text-xs text-gray-400 italic leading-relaxed">{product.supportNote}</p>} */}
              <p className="text-xs text-gray-400 italic leading-relaxed">{DEFAULT_NOTE}</p>
            </div>
          </div>

          <hr className="border-gray-100 mx-4 sm:mx-5" />

          <div className="p-4 sm:p-5 flex flex-col gap-4">
            {product.hasColors && (
              <ProductColorSelector
                variants={product.variants}
                selected={selectedColor}
                onChange={handleColorChange}
              />
            )}
            {product.hasSizes && (
              <ProductSizeSelector
                sizes={sizes}
                selected={selectedSize}
                onChange={setSelectedSize}
              />
            )}
            <ProductQuantitySelector
              value={quantity}
              max={maxStock}
              rawStock={rawStock}
              onChange={setQuantity}
            />
            <button
              onClick={() => void handleAddToCart()}
              disabled={!canAddToCart}
              className="max-w-[200px] self-center mt-5 p-2 px-8 rounded-full bg-primary-500 text-white font-semibold text-sm hover:bg-primary-600 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {mode === 'edit' ? 'Update Cart' : 'Add to Cart'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
