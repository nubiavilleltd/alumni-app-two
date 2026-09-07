import { useMemo, useState } from 'react';

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
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import { SlidersHorizontal } from 'lucide-react';

export function StorePage() {
    const { products, isLoading } = useProducts();

    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [priceRange, setPriceRange] = useState('');
    const [availability, setAvailability] = useState('');
    const [variantFilter, setVariantFilter] = useState('');
    const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
    const [page, setPage] = useState(1);

    const ITEMS_PER_PAGE = useItemsPerPage();

    const cartCount = useCartCount();

    // const openProduct = useProductModalStore(
    //     (s) => s.openForAdd,
    // );
    const navigate = useNavigate();

    const categories = useMemo(
        () => [...new Set(products.map((p) => p.category))],
        [products],
    );

    const filtered = useMemo(() => {
        return products.filter((product) => {
            const normalizedSearch = search.toLowerCase();
            const searchMatch = [product.name, product.category, product.description]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()
                .includes(normalizedSearch) || product.price?.toString().includes(normalizedSearch);

            const categoryMatch =
                !category || product.category === category;
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
                !variantFilter ||
                (variantFilter === 'sizes' ? product.hasSizes : product.hasColors);

            return searchMatch && categoryMatch && priceMatch && availabilityMatch && variantMatch;
        });
    }, [products, search, category, priceRange, availability, variantFilter]);

    const totalPages = Math.max(1, Math.ceil(
        filtered.length / ITEMS_PER_PAGE,
    ));

    const visible = filtered.slice(
        (page - 1) * ITEMS_PER_PAGE,
        page * ITEMS_PER_PAGE,
    );

    const activeAdvancedFilterCount = [priceRange, availability, variantFilter].filter(Boolean).length;
    const hasActiveFilters = Boolean(search.trim() || category || activeAdvancedFilterCount);
    const handleFilterChange = (setter: (value: string) => void) => (value: string) => {
        setter(value);
        setPage(1);
    };
    const clearAllFilters = () => {
        setSearch('');
        setCategory('');
        setPriceRange('');
        setAvailability('');
        setVariantFilter('');
        setPage(1);
    };

    return (
        <>
            <SEO title="Alumni Store" />

            <section className="min-h-screen bg-[#F8F8F7] py-8">
                <div className="container-custom mx-auto">

                    {/* HEADER */}
                    <div className="flex justify-between items-start mb-8">
                        <div>
                            <h1 className="type-section-title">
                                Alumni Store
                            </h1>

                            <p className="text-gray-600 max-w-xl">
                                Celebrate your connection to the alumni
                                community with exclusive merchandise.
                            </p>
                        </div>

                        <StoreCartButton count={cartCount} onClick={() => navigate(`${STORE_ROUTES.ROOT}/cart`)} />
                    </div>

                    {/* FILTERS */}
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="min-w-0 flex-1">
                            <StoreFilters
                                search={search}
                                category={category}
                                categories={categories}
                                onSearch={handleFilterChange(setSearch)}
                                onCategoryChange={handleFilterChange(setCategory)}
                                className="mb-0"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
                            aria-expanded={showAdvancedFilters}
                            className="flex h-10 w-full shrink-0 items-center justify-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-600 shadow-sm transition-colors hover:bg-gray-50 sm:w-auto"
                        >
                            <SlidersHorizontal className="h-4 w-4" />
                            Advanced filters
                            {activeAdvancedFilterCount > 0 && (
                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] text-white">
                                    {activeAdvancedFilterCount}
                                </span>
                            )}
                        </button>
                    </div>

                    {showAdvancedFilters && (
                        <AdvancedFiltersPanel
                            title="Refine store products"
                            description="Narrow products by price, availability, or available variants."
                            gridClassName="grid-cols-1 gap-4 md:grid-cols-3"
                            fields={[
                                {
                                    key: 'priceRange',
                                    kind: 'select',
                                    label: 'Price range',
                                    value: priceRange,
                                    placeholder: 'Any price',
                                    options: [
                                        { label: 'Under 5,000', value: 'under-5000' },
                                        { label: '5,000–15,000', value: '5000-15000' },
                                        { label: '15,000–30,000', value: '15000-30000' },
                                        { label: '30,000 and above', value: '30000-plus' },
                                    ],
                                },
                                {
                                    key: 'availability',
                                    kind: 'select',
                                    label: 'Availability',
                                    value: availability,
                                    placeholder: 'All products',
                                    options: [
                                        { label: 'In stock', value: 'in-stock' },
                                        { label: 'Out of stock', value: 'out-of-stock' },
                                    ],
                                },
                                {
                                    key: 'variantFilter',
                                    kind: 'select',
                                    label: 'Options',
                                    value: variantFilter,
                                    placeholder: 'Any options',
                                    options: [
                                        { label: 'Available in sizes', value: 'sizes' },
                                        { label: 'Available in colors', value: 'colors' },
                                    ],
                                },
                            ]}
                            onFieldChange={(key, value) => {
                                if (key === 'priceRange') setPriceRange(value);
                                if (key === 'availability') setAvailability(value);
                                if (key === 'variantFilter') setVariantFilter(value);
                                setPage(1);
                            }}
                            onReset={clearAllFilters}
                            hasActiveFilters={activeAdvancedFilterCount > 0}
                        />
                    )}

                    {hasActiveFilters && (
                        <div className="mb-8 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                            <span>
                                Showing {filtered.length} {filtered.length === 1 ? 'product' : 'products'} matching your filters
                            </span>
                            <button
                                type="button"
                                onClick={clearAllFilters}
                                className="font-semibold text-primary-600 transition-colors hover:text-primary-700"
                            >
                                Clear all filters
                            </button>
                        </div>
                    )}

                    {/* GRID */}
                    {isLoading ? (
                        <StoreSkeleton />
                    ) : visible.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
                            {visible.map((product) => (
                                <StoreProductCard
                                    key={product.id}
                                    product={product}
                                />
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
                            <Pagination
                                currentPage={page}
                                totalPages={totalPages}
                                onPageChange={setPage}
                            />
                        </div>
                    )}
                </div>
            </section>

            {/* GLOBAL MODAL (important: mounted once) */}
            <ProductDetailsModal />
        </>
    );
}
