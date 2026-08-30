import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { useAdminProducts, useDeleteProduct, usePinProduct } from '../hooks/useAdminStore';
import { ADMIN_ORDER_ROUTES, ADMIN_STORE_ROUTES } from '../routes';
import { AdminStoreProductCard } from '../components/AdminStoreProductCard';
import { AdminBanner } from '../components/AdminBanner';
import { StoreFilters } from '@/features/store/components/StoreFilters';
import { toast } from '@/shared/components/ui/Toast';
import { Pagination } from '@/shared/components/ui/Pagination';
import useItemsPerPage from '@/features/store/hooks/useItemsPerPage';
import EmptyState from '@/shared/components/ui/EmptyState';

// ─── Confirmation dialog ──────────────────────────────────────────────────────

interface ConfirmDeleteDialogProps {
  productName: string;
  onConfirm: () => void;
  onCancel: () => void;
}

function ConfirmDeleteDialog({ productName, onConfirm, onCancel }: ConfirmDeleteDialogProps) {
  return (
    <div
      className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center px-4"
      onClick={onCancel}
    >
      <div
        className="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-lg font-bold text-gray-900 mb-2">Delete product?</h3>
        <p className="text-sm text-gray-500 mb-6">
          <span className="font-semibold text-gray-700">"{productName}"</span> will be permanently
          removed from the store. This cannot be undone.
        </p>
        <div className="flex gap-3">
          <button
            onClick={onConfirm}
            className="flex-1 py-2.5 rounded-full bg-red-500 text-white font-semibold text-sm hover:bg-red-600 transition-colors"
          >
            Delete
          </button>
          <button
            onClick={onCancel}
            className="flex-1 py-2.5 rounded-full border-2 border-gray-200 text-gray-700 font-semibold text-sm hover:border-gray-300 transition-colors"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function StoreSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="bg-white rounded-[20px] overflow-hidden animate-pulse border border-gray-100">
          <div className="h-[200px] bg-gray-100" />
          <div className="p-4 flex justify-between gap-4">
            <div className="flex-1 space-y-2">
              <div className="h-4 bg-gray-200 rounded" />
              <div className="h-4 w-3/4 bg-gray-200 rounded" />
            </div>
            <div className="h-6 w-20 bg-gray-200 rounded" />
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function AdminStorePage() {
  const navigate = useNavigate();

  const { data: response, isLoading, isError } = useAdminProducts();
  const products = response?.data ?? [];
  const meta = response?.meta;
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');

  const categories = useMemo(
    () => [...new Set(products.map((p) => p.category))],
    [products],
  );



  const [page, setPage] = useState(1);

  const ITEMS_PER_PAGE = useItemsPerPage();

  const filtered = useMemo(() => {
    return products.filter((product) => {


      const searchMatch = product.product_name
        .toLowerCase()
        .includes(search.toLowerCase()) || product.category.toLowerCase()
          .includes(search.toLowerCase()) || product.price?.toString().toLowerCase()
            .includes(search.toLowerCase());

      const categoryMatch = !category || product.category === category;

      return searchMatch && categoryMatch;
    });
  }, [products, search, category]);

  const sorted = useMemo(() => {
    return [...filtered].sort((a, b) => {
      if (a.pin_item === b.pin_item) return 0;
      return a.pin_item ? -1 : 1;
    });
  }, [filtered]);

  const deleteProduct = useDeleteProduct();
  const pinProduct = usePinProduct();

  const [pendingDeleteId, setPendingDeleteId] = useState<string | null>(null);
  // const [pendingPinId, setPendingPinId] = useState<string | null>(null);

  const pendingProduct = products.find((p) => p.id === pendingDeleteId);
  // const productToPin = products.find((p) => p.id === pendingPinId);

  const totalPages = Math.ceil(
    sorted.length / ITEMS_PER_PAGE,
  );

  const visible = sorted.slice(
    (page - 1) * ITEMS_PER_PAGE,
    page * ITEMS_PER_PAGE,
  );


  const handleConfirmDelete = () => {
    if (!pendingDeleteId) return;
    deleteProduct.mutate(pendingDeleteId, {
      onSettled: () => setPendingDeleteId(null),
    });
  };
  const handlePinProduct = async (productId: string, currentPinStatus: boolean) => {
    if (!productId) return;
    try {
      const atCap = !!meta && meta.total_pinned >= meta.max_pinned;
      if (!currentPinStatus && atCap) {
        toast.error(`You can only pin up to ${meta!.max_pinned} products. Unpin one first.`);
        return;
      }
      await pinProduct.mutateAsync({ productId, pinItem: !currentPinStatus });
      toast.success(`Product ${currentPinStatus ? "unpinned" : "pinned"}`);
    } catch (error) {
      toast.error("Failed to pin item")
    }

  };

  return (
    <>
      <SEO title="Admin — Store" />
      <AdminBanner activeTab="store" title="Store" />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-8 sm:py-10">

          {/* Header */}
          <div className="flex flex-col sm:flex-row items-center justify-between mb-8">

            <div className='flex-1'>
              <StoreFilters
                search={search}
                category={category}
                categories={categories}
                onSearch={setSearch}
                onCategoryChange={setCategory}
              /></div>

            <div className="flex items-center gap-3">
              {meta && (
                <span className="text-sm font-semibold text-gray-500 border border-gray-200 rounded-full px-4 py-2 whitespace-nowrap">
                  {meta.total_pinned}/{meta.max_pinned} Pinned
                </span>
              )}
              <Link
                to={ADMIN_ORDER_ROUTES.ROOT}
                className="hidden sm:flex items-center gap-2 text-sm font-semibold text-primary-500 border border-primary-500 rounded-full px-4 py-2 hover:bg-primary-50 transition-colors"
              >
                Order Management
              </Link>
              <button
                onClick={() => navigate(ADMIN_STORE_ROUTES.PRODUCT_CREATE)}
                className="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm rounded-full px-5 py-2.5 transition-colors"
              >
                Add an Item
                <Plus size={16} strokeWidth={2.5} />
              </button>
            </div>

          </div>

          {/* Loading */}
          {isLoading && <StoreSkeleton />}

          {/* Error */}
          {isError && (
            <div className="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">
              Failed to load products. Please refresh and try again.
            </div>
          )}

          {/* Empty */}
          {!isLoading && !isError && products.length === 0 && (
            <div className="text-center py-20">
              <p className="text-gray-400 font-medium mb-4">No products in the store yet.</p>
              <button
                onClick={() => navigate(ADMIN_STORE_ROUTES.PRODUCT_CREATE)}
                className="inline-flex items-center gap-2 border border-primary-300 text-primary-500 font-semibold text-sm rounded-full px-5 py-2 hover:bg-primary-50 transition-colors"
              >
                <Plus size={14} />
                Add your first product
              </button>
            </div>
          )}

          {/* Grid */}
          {/* {!isLoading && !isError && filtered.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {filtered.map((product) => (
                <AdminStoreProductCard
                  key={product.id}
                  product={product}
                  onEdit={() => navigate(ADMIN_STORE_ROUTES.PRODUCT_EDIT(product.id))}
                  onDelete={() => setPendingDeleteId(product.id)}
                  onPin={() => handlePinProduct(product.id, product.pin_item)}
                  isDeleting={deleteProduct.isPending && pendingDeleteId === product.id}
                />
              ))}
            </div>
          )} */}




          {isLoading ? (
            <StoreSkeleton />
          ) : visible.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {visible.map((product) => (
                <AdminStoreProductCard
                  key={product.id}
                  product={product}
                  onEdit={() => navigate(ADMIN_STORE_ROUTES.PRODUCT_EDIT(product.id))}
                  onDelete={() => setPendingDeleteId(product.id)}
                  onPin={() => handlePinProduct(product.id, product.pin_item)}
                  isDeleting={deleteProduct.isPending && pendingDeleteId === product.id}
                  pinDisabled={!product.pin_item && !!meta && meta.total_pinned >= meta.max_pinned}
                />
              ))}
            </div>
          ) : (
            <EmptyState
              title="No products found"
              description="Try adjusting your search or category filter."
            />
          )}

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
      </div>

      {/* Delete confirmation */}
      {pendingDeleteId && pendingProduct && (
        <ConfirmDeleteDialog
          productName={pendingProduct.product_name}
          onConfirm={handleConfirmDelete}
          onCancel={() => setPendingDeleteId(null)}
        />
      )}
    </>
  );
}