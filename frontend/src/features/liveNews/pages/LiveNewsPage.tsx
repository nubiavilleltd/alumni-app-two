// pages/resources/ResourcesPage.tsx
// Route: /resources
// Design: 2×2 grid of category cards with coloured icon badges.
// Each resource link is blue with an external link icon where applicable.


import { useMemo, useState } from 'react';
import { SEO } from '@/shared/common/SEO';
import { Pagination } from '@/shared/components/ui/Pagination';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';

import { FeaturedNewsCard } from '../components/FeaturedNewsCard';
import { NewsCard } from '../components/NewsCard';
import useItemsPerPage from '../hooks/useItemsPerPage';
import { useLiveNews } from '../hooks/useLiveNews';

import { LiveNewsSkeleton } from '../components/LiveNewsSkeleton';

export default function LiveNewsPage() {
    const { data: liveNewsData = [], isLoading } = useLiveNews();

    const [currentPage, setCurrentPage] = useUrlPagination();

    const ITEMS_PER_PAGE = useItemsPerPage();

    const sortedNews = useMemo(
        () =>
            [...liveNewsData].sort(
                (a, b) =>
                    new Date(b.publishedAt).getTime() -
                    new Date(a.publishedAt).getTime(),
            ),
        [liveNewsData],
    );

    const totalPages = Math.max(
        1,
        Math.ceil(sortedNews.length / ITEMS_PER_PAGE),
    );

    const start = (currentPage - 1) * ITEMS_PER_PAGE;

    const pageItems = sortedNews.slice(
        start,
        start + ITEMS_PER_PAGE,
    );


    const featuredNews = pageItems[0];

    const remainingNews = pageItems.slice(1);

    const changePage = (page: number) => {
        setCurrentPage(page);

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };
    return (
        <>
            <SEO
                title="Live News"
                description="Stay informed with the latest headlines, real-time news, and breaking stories from trusted media sources."
            />

            <div className="min-h-screen bg-[#F8F8F7]">
                <div className="container-custom py-8 sm:py-10">
                    {/* ── Header ───────────────────────────────────────────────── */}
                    <div className="mb-8">
                        <h1 className="type-section-title mb-2 text-gray-900">Live News</h1>
                        <p className="type-card-body max-w-2xl text-gray-600">
                            Stay informed with the latest headlines, real-time news, and breaking stories from trusted media sources.
                        </p>
                    </div>

                    {isLoading ? (
                        <LiveNewsSkeleton />
                    ) : featuredNews ? (
                        <>
                            <div className="grid gap-6 lg:grid-cols-2 items-stretch">
                                <FeaturedNewsCard item={featuredNews} />
                                <div className="flex flex-col gap-4">
                                    {remainingNews.slice(0, 3).map((item) => (
                                        <NewsCard key={item.id} item={item} />
                                    ))}
                                </div>
                            </div>

                            <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {remainingNews.slice(3).map((item) => (
                                    <NewsCard key={item.id} item={item} />
                                ))}
                            </div>
                        </>
                    ) : (
                        <p className="text-gray-500">No news available at the moment.</p>
                    )}

                    {totalPages > 1 && (
                        <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
                            <Pagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                onPageChange={changePage}
                            />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}









