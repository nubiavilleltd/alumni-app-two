import { SEO } from '@/shared/common/SEO'
import ContainerBackground from '@/shared/containers/ContainerBackground'
import React from 'react'
import useItemsPerPage from '../hooks/useItemsPerPage';
import { useOrders } from '../hooks/useOrders';
import { Pagination } from '@/shared/components/ui/Pagination';
import { OrderFilters } from '../components/OrderFilters';
import { Link } from 'react-router-dom';
import { useProducts } from '../hooks/useProducts';
import { useProductModalStore } from '../stores/useProductModalStore';
import { FlattenedOrderItem } from '../types/order.types';
import OrderItemListCard from '../components/OrderItemListCard';
import { ProductDetailsModal } from '../components/ProductDetailsModal';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';

export default function OrderHistoryPage() {
    const { filters, setFilter } = usePersistedFilters('order-history-filters', {
        search: '',
        activeTab: 'all',
    });
    const { search, activeTab } = filters;
    const [page, setPage] = useUrlPagination();

    const { flattenedItems, counts, isLoading, isError, error } = useOrders({
        search,
        status: activeTab,
    });

    const { products } = useProducts();
    const openForAdd = useProductModalStore((s) => s.openForAdd);

    const tabs = [
        { label: 'All', value: 'all', count: counts.total },
        { label: 'Processing', value: 'processing', count: counts.processing },
        { label: 'Out for Delivery / Ready for Pickup', value: 'out_for_delivery', count: counts.outForDelivery },
        { label: 'Completed', value: 'completed', count: counts.completed },
    ];

    const ITEMS_PER_PAGE = useItemsPerPage();

    const totalPages = Math.ceil(flattenedItems.length / ITEMS_PER_PAGE);

    const visible = flattenedItems.slice(
        (page - 1) * ITEMS_PER_PAGE,
        page * ITEMS_PER_PAGE,
    );

    const handleAddToCart = (flatItem: FlattenedOrderItem) => {
        const product = products.find((p) => p.id === flatItem.item.productId);
        if (!product) return; // product may have been removed since order was placed
        openForAdd(product);
    };

    if (isLoading) {
        return <ContainerBackground>
            <p className="">Loading orders...</p>
        </ContainerBackground>;
    }

    if (isError) {
        return (
            <div className="container mx-auto px-4 py-6">
                <div className="text-red-500">Error loading orders: {error?.message}</div>
            </div>
        );
    }

    return (
        <>
            <SEO title="My Order History" />

            <ContainerBackground>
                <div className="flex justify-between items-start mb-8">
                    <div>
                        <h1 className="type-section-title">
                            My Order History
                        </h1>
                    </div>
                </div>

                <OrderFilters
                    variant="user"
                    search={search}
                    onSearch={(value) => setFilter('search', value)}
                    activeTab={activeTab}
                    onTabChange={(value) => setFilter('activeTab', value)}
                    tabs={tabs}
                />

                <div className="mt-6 space-y-4">
                    {visible.length === 0 ? (
                        <p className="text-center text-gray-500 py-8">
                            {search.trim() ? 'No orders match your search' : 'No orders found'}
                        </p>
                    ) : (
                        visible.map((flatItem) => (
                            <OrderItemListCard
                                key={flatItem.id}
                                flatItem={flatItem}
                                onAddToCart={handleAddToCart}
                                action={
                                    <Link to={`/orders/${flatItem.orderNumber}`} className="border-2 p-0.5 px-3 font-semibold rounded-2xl border-primary-500 text-primary-500">
                                        View Details
                                    </Link>
                                }
                            />
                        ))
                    )}
                </div>

                {totalPages > 1 && (
                    <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
                        <Pagination
                            currentPage={page}
                            totalPages={totalPages}
                            onPageChange={setPage}
                        />
                    </div>
                )}

            </ContainerBackground>
            <ProductDetailsModal />
        </>
    );
}
