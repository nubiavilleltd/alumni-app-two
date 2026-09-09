// import { OrderFilters } from '@/features/store/components/OrderFilters';
// import OrderListCard from '@/features/store/components/OrderListCard';
// import useItemsPerPage from '@/features/store/hooks/useItemsPerPage';
// import { SEO } from '@/shared/common/SEO';
// import { Pagination } from '@/shared/components/ui/Pagination';
// import ContainerBackground from '@/shared/containers/ContainerBackground';
// import React, { useState } from 'react'
// import { Link } from 'react-router-dom';
// import { useAdminOrders } from '../hooks/useAdminOrders';
// import { ADMIN_ORDER_ROUTES } from '../routes'; // adjust to wherever this actually lives
// import { Download } from 'lucide-react';
// import { ExportButton } from '@/shared/components/ui/ExportButton';

// export default function AdminOrderManagementPage() {

//     const [search, setSearch] = useState('');
//     const [activeTab, setActiveTab] = useState('all');
//     const [dateFrom, setDateFrom] = useState('');
//     const [dateTo, setDateTo] = useState('');
//     const [page, setPage] = useState(1);

//     // Fetch all orders (filtering happens inside the hook via useOrdersBase)
//     const { orders, counts, isLoading, isError, error } = useAdminOrders({
//         search,
//         status: activeTab,
//         dateFrom,
//         dateTo,
//     });

//     // Build tabs with counts
//     const tabs = [
//         { label: 'All', value: 'all', count: counts.total },
//         { label: 'New Orders', value: 'new_order', count: counts.newOrders },
//         { label: 'Out for Delivery / Ready for Pickup', value: 'out_for_delivery', count: counts.outForDelivery },
//         { label: 'Completed', value: 'completed', count: counts.completed },
//     ];

//     const ITEMS_PER_PAGE = useItemsPerPage();

//     const totalPages = Math.ceil(orders.length / ITEMS_PER_PAGE);

//     const visible = orders.slice(
//         (page - 1) * ITEMS_PER_PAGE,
//         page * ITEMS_PER_PAGE,
//     );

//     if (isLoading) {
//         return <ContainerBackground>
//             <p className="">Loading orders...</p>
//         </ContainerBackground>;
//     }

//     if (isError) {
//         return (
//             <div className="container mx-auto px-4 py-6">
//                 <div className="text-red-500">Error loading orders: {error?.message}</div>
//             </div>
//         );
//     }

//     const handleExport = () => {
//         // TODO: wire up actual export logic (CSV/Excel generation or API call)
//         console.log('Exporting orders...', { search, activeTab, dateFrom, dateTo });
//     };

//     return (
//         <>
//             <SEO title="Order Management" />

//             <ContainerBackground>
//                 {/* HEADER */}
//                 {/* <div className="flex justify-between items-start mb-8">
//                     <div>
//                         <h1 className="type-section-title">
//                             Order Management
//                         </h1>

//                         <p className="text-gray-600 max-w-xl">
//                             Track, process, and manage merchandise orders.
//                         </p>
//                     </div>

//                 </div> */}

//                 {/* HEADER */}
//                 <div className="flex justify-between items-start mb-8">
//                     <div>
//                         <h1 className="type-section-title">
//                             Order Management
//                         </h1>

//                         <p className="text-gray-600 max-w-xl">
//                             Track, process, and manage merchandise orders.
//                         </p>
//                     </div>

//                     {/* <button
//                         type="button"
//                         onClick={handleExport}
//                         className="flex items-center gap-2 rounded-full border-2 border-primary-500 px-4 py-2 text-sm font-semibold text-primary-500 transition-colors hover:bg-primary-500 hover:text-white whitespace-nowrap"
//                     >
//                         <Download className="w-4 h-4" strokeWidth={2.4} />
//                         Export
//                     </button> */}

//                     <ExportButton onExport={handleExport} />
//                 </div>

//                 <OrderFilters
//                     variant="admin"
//                     search={search}
//                     onSearch={setSearch}
//                     activeTab={activeTab}
//                     onTabChange={setActiveTab}
//                     tabs={tabs}
//                     dateRange={{
//                         from: dateFrom,
//                         to: dateTo,
//                         onFromChange: setDateFrom,
//                         onToChange: setDateTo,
//                     }}
//                 />

//                 <div className="mt-6 space-y-4">
//                     {visible.length === 0 ? (
//                         <p className="text-center text-gray-500 py-8">
//                             {search.trim() ? 'No orders match your search' : 'No orders found'}
//                         </p>
//                     ) : (
//                         visible.map((order) => (
//                             <OrderListCard order={order} key={order.orderNumber} variant='admin' action={
//                                 <Link to={ADMIN_ORDER_ROUTES.DETAIL(order.orderNumber)} className="border-2 whitespace-nowrap p-0.5 px-3 font-semibold rounded-2xl border-primary-500 text-primary-500">
//                                     View Details
//                                 </Link>
//                             } />
//                         ))
//                     )}
//                 </div>

//                 {/* PAGINATION */}
//                 {totalPages > 1 && (
//                     <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
//                         <Pagination
//                             currentPage={page}
//                             totalPages={totalPages}
//                             onPageChange={setPage}
//                         />
//                     </div>
//                 )}
//             </ContainerBackground>

//         </>
//     );
// }














import { OrderFilters } from '@/features/store/components/OrderFilters';
import OrderListCard from '@/features/store/components/OrderListCard';
import useItemsPerPage from '@/features/store/hooks/useItemsPerPage';
import { SEO } from '@/shared/common/SEO';
import { Pagination } from '@/shared/components/ui/Pagination';
import { ExportButton } from '@/shared/components/ui/ExportButton';
import ContainerBackground from '@/shared/containers/ContainerBackground';
import { Link } from 'react-router-dom';
import { useAdminOrders } from '../hooks/useAdminOrders';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { ADMIN_ORDER_ROUTES } from '../routes';
import { downloadCsvFile } from '@/shared/utils/csvExport';
import { buildOrderExportRows, ORDER_EXPORT_HEADERS } from '@/features/store/utils/orderExport';
import { toast } from '@/shared/components/ui/Toast';

export default function AdminOrderManagementPage() {

    const { filters, setFilter } = usePersistedFilters('admin-orders-filters', {
        search: '',
        activeTab: 'all',
        dateFrom: '',
        dateTo: '',
    });
    const { search, activeTab, dateFrom, dateTo } = filters;
    const [page, setPage] = useUrlPagination();

    const { orders, counts, isLoading, isError, error } = useAdminOrders({
        search,
        status: activeTab,
        dateFrom,
        dateTo,
    });

    const tabs = [
        { label: 'All', value: 'all', count: counts.total },
        { label: 'New Orders', value: 'new_order', count: counts.newOrders },
        { label: 'Out for Delivery / Ready for Pickup', value: 'out_for_delivery', count: counts.outForDelivery },
        { label: 'Completed', value: 'completed', count: counts.completed },
    ];

    const ITEMS_PER_PAGE = useItemsPerPage();

    const totalPages = Math.ceil(orders.length / ITEMS_PER_PAGE);

    const visible = orders.slice(
        (page - 1) * ITEMS_PER_PAGE,
        page * ITEMS_PER_PAGE,
    );

    function handleExportOrders() {
        if (orders.length === 0) {
            toast.error('No orders to export');
            return;
        }

        try {
            const rows = buildOrderExportRows(orders);
            const filename = `orders-export-${new Date().toISOString().slice(0, 10)}.csv`;
            downloadCsvFile(filename, ORDER_EXPORT_HEADERS, rows);
        } catch (err) {
            console.error('[AdminOrderManagementPage] failed to export orders', err);
            toast.error('Unable to export orders right now. Please try again.');
        }
    }

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
            <SEO title="Order Management" />

            <ContainerBackground>
                {/* HEADER */}
                <div className="flex justify-between items-start mb-8">
                    <div>
                        <h1 className="type-section-title">
                            Order Management
                        </h1>

                        <p className="text-gray-600 max-w-xl">
                            Track, process, and manage merchandise orders.
                        </p>
                    </div>

                    <ExportButton
                        onExport={handleExportOrders}
                        disabled={orders.length === 0}
                        label="Export"
                    />
                </div>

                <OrderFilters
                    variant="admin"
                    search={search}
                    onSearch={(value) => setFilter('search', value)}
                    activeTab={activeTab}
                    onTabChange={(value) => setFilter('activeTab', value)}
                    tabs={tabs}
                    dateRange={{
                        from: dateFrom,
                        to: dateTo,
                        onFromChange: (value) => setFilter('dateFrom', value),
                        onToChange: (value) => setFilter('dateTo', value),
                    }}
                />

                <div className="mt-6 space-y-4">
                    {visible.length === 0 ? (
                        <p className="text-center text-gray-500 py-8">
                            {search.trim() ? 'No orders match your search' : 'No orders found'}
                        </p>
                    ) : (
                        visible.map((order) => (
                            <OrderListCard order={order} key={order.orderNumber} variant='admin' action={
                                <Link to={ADMIN_ORDER_ROUTES.DETAIL(order.orderNumber)} className="border-2 whitespace-nowrap p-0.5 px-3 font-semibold rounded-2xl border-primary-500 text-primary-500">
                                    View Details
                                </Link>
                            } />
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

        </>
    );
}
