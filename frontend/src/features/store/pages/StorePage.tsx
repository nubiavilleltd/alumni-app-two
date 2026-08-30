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

export function StorePage() {
    const { products, isLoading } = useProducts();

    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
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
            const searchMatch = product.name
                .toLowerCase()
                .includes(search.toLowerCase()) || product.category.toLowerCase()
                .includes(search.toLowerCase()) || product.price?.toString().toLowerCase()
                .includes(search.toLowerCase());

            const categoryMatch =
                !category || product.category === category;

            return searchMatch && categoryMatch;
        });
    }, [products, search, category]);

    const totalPages = Math.ceil(
        filtered.length / ITEMS_PER_PAGE,
    );

    const visible = filtered.slice(
        (page - 1) * ITEMS_PER_PAGE,
        page * ITEMS_PER_PAGE,
    );

    return (
        <>
            <SEO title="Alumnae Store" />

            <section className="min-h-screen bg-[#F8F8F7] py-8">
                <div className="container-custom mx-auto">

                    {/* HEADER */}
                    <div className="flex justify-between items-start mb-8">
                        <div>
                            <h1 className="type-section-title">
                                Alumnae Store
                            </h1>

                            <p className="text-gray-600 max-w-xl">
                                Celebrate your connection to the alumnae
                                community with exclusive merchandise.
                            </p>
                        </div>

                        <StoreCartButton count={cartCount} onClick={() => navigate(`${STORE_ROUTES.ROOT}/cart`)} />
                    </div>

                    {/* FILTERS */}
                    <StoreFilters
                        search={search}
                        category={category}
                        categories={categories}
                        onSearch={setSearch}
                        onCategoryChange={setCategory}
                        className='mb-8'
                    />

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