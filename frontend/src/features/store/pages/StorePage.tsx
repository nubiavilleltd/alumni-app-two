import { useMemo } from 'react';

import { SEO } from '@/shared/common/SEO';
import { Pagination } from '@/shared/components/ui/Pagination';

import { StoreProductCard } from '../components/StoreProductCard';
import { StoreFilters } from '../components/StoreFilters';

import { useProducts } from '../hooks/useProducts';
import useItemsPerPage from '../hooks/useItemsPerPage';

import EmptyState from '@/shared/components/ui/EmptyState';

import { StoreCartButton } from '../components/StoreCartButton';
import { StoreSkeleton } from '../components/StoreSkeleton';

import { useCartCount } from '../hooks/useCartCount';
import { useProductModalStore } from '../stores/useProductModalStore';
import { ProductDetailsModal } from '../components/ProductDetailsModal';
import { useNavigate } from 'react-router-dom';
import { STORE_ROUTES } from '../routes';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';

export function StorePage() {
  const { products, isLoading } = useProducts();

  const { filters, setFilter, clearFilters } = usePersistedFilters('store-filters', {
    search: '',
    category: '',
    priceRange: '',
    availability: '',
    variantFilter: '',
  });
  const { search, category, priceRange, availability, variantFilter } = filters;
  const [page, setPage] = useUrlPagination();

  const ITEMS_PER_PAGE = useItemsPerPage();

  const cartCount = useCartCount();

  // const openProduct = useProductModalStore(
  //     (s) => s.openForAdd,
  // );
  const navigate = useNavigate();

  const categories = useMemo(() => [...new Set(products.map((p) => p.category))], [products]);

  const filtered = useMemo(() => {
    return products.filter((product) => {
      const normalizedSearch = search.toLowerCase();
      const searchMatch =
        [product.name, product.category, product.description]
          .filter(Boolean)
          .join(' ')
          .toLowerCase()
          .includes(normalizedSearch) || product.price?.toString().includes(normalizedSearch);

      const categoryMatch = !category || product.category === category;
      const priceMatch =
        !priceRange ||
        (priceRange === 'under-5000'
          ? product.price < 5000
          : priceRange === '5000-15000'
            ? product.price >= 5000 && product.price < 15000
            : priceRange === '15000-30000'
              ? product.price >= 15000 && product.price < 30000
              : product.price >= 30000);
      const availabilityMatch =
        !availability ||
        (availability === 'in-stock' ? product.totalStock > 0 : product.totalStock <= 0);
      const variantMatch =
        !variantFilter || (variantFilter === 'sizes' ? product.hasSizes : product.hasColors);

      return searchMatch && categoryMatch && priceMatch && availabilityMatch && variantMatch;
    });
  }, [products, search, category, priceRange, availability, variantFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));

  const visible = filtered.slice((page - 1) * ITEMS_PER_PAGE, page * ITEMS_PER_PAGE);

  const hasActiveFilters = Boolean(
    search.trim() || category || priceRange || availability || variantFilter,
  );
  const handleFilterChange = (key: keyof typeof filters) => (value: string) => {
    setFilter(key, value);
  };
  const clearAllFilters = () => {
    clearFilters();
  };

  return (
    <>
      <SEO title="Alumni Store" />

      <section className="min-h-screen bg-[#F8F8F7] py-8">
        <div className="container-custom mx-auto">
          {/* HEADER */}
          <div className="flex justify-between items-start mb-8">
            <div>
              <h1 className="type-section-title">Alumni Store</h1>

              <p className="text-gray-600 max-w-xl">
                Celebrate your connection to the alumni community with exclusive merchandise.
              </p>
            </div>

            <StoreCartButton
              count={cartCount}
              onClick={() => navigate(`${STORE_ROUTES.ROOT}/cart`)}
            />
          </div>

          {/* FILTERS */}
          <StoreFilters
            search={search}
            category={category}
            categories={categories}
            priceRange={priceRange}
            availability={availability}
            variantFilter={variantFilter}
            onSearch={handleFilterChange('search')}
            onCategoryChange={handleFilterChange('category')}
            onPriceRangeChange={handleFilterChange('priceRange')}
            onAvailabilityChange={handleFilterChange('availability')}
            onVariantChange={handleFilterChange('variantFilter')}
            onClearFilters={clearAllFilters}
            showClearFilters={hasActiveFilters}
            className="mb-8"
          />

          {hasActiveFilters && (
            <div className="mb-8 text-sm text-[#69727d]">
              <span>
                Showing {filtered.length} {filtered.length === 1 ? 'product' : 'products'} matching
                your filters
              </span>
            </div>
          )}

          {/* GRID */}
          {isLoading ? (
            <StoreSkeleton />
          ) : visible.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {visible.map((product) => (
                <StoreProductCard key={product.id} product={product} />
              ))}
            </div>
          ) : (
            <EmptyState
              title="No products found"
              description="Try adjusting your search or category filter."
            />
          )}

          {/* PAGINATION */}
          {totalPages > 1 && (
            <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
              <Pagination currentPage={page} totalPages={totalPages} onPageChange={setPage} />
            </div>
          )}
        </div>
      </section>

      {/* GLOBAL MODAL (important: mounted once) */}
      <ProductDetailsModal />
    </>
  );
}
