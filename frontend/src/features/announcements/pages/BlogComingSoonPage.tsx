import { useState } from 'react';
import { ChevronDown, ChevronRight, Clock3, FileText } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { AppLink } from '@/shared/components/ui/AppLink';
import EmptyState from '@/shared/components/ui/EmptyState';
import { useBlogCategories, useBlogPosts } from '@/features/blogs/hooks/useBlogs';
import type { BlogCategory, BlogPostSummary } from '@/features/blogs/types/blog.types';
import { ANNOUNCEMENT_ROUTES } from '../routes';

const FALLBACK_IMAGE = '/news-1.png';

function formatBlogDate(value?: string | null) {
  if (!value) return 'Not published';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;

  return parsed.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function formatReadTime(minutes?: number | null) {
  const resolvedMinutes = minutes && minutes > 0 ? minutes : 1;
  return `${resolvedMinutes} min${resolvedMinutes === 1 ? '' : 's'} read`;
}

function BlogCard({ post }: { post: BlogPostSummary }) {
  return (
    <AppLink
      href={ANNOUNCEMENT_ROUTES.BLOG_DETAIL(post.slug || post.id)}
      className="group flex min-w-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-[#eef2f5] bg-white no-underline shadow-[0_1px_2px_rgba(7,17,22,0.04)] transition-colors duration-200 hover:border-primary-100"
    >
      <div className="relative aspect-[16/10] overflow-hidden rounded-2xl bg-[#e9edf1]">
        <img
          src={post.coverImageUrl || FALLBACK_IMAGE}
          alt=""
          className="h-full w-full object-cover transition-transform duration-300 ease-out group-hover:scale-[1.03]"
          loading="lazy"
        />
        <span className="absolute left-3 top-3 max-w-[calc(100%-1.5rem)] truncate rounded-full bg-primary-600 bg-opacity-60 px-3 py-1.5 text-xs font-semibold text-white shadow-sm">
          {post.categoryName || 'Uncategorized'}
        </span>
      </div>

      <div className="flex flex-1 flex-col px-4 pb-5 pt-4">
        <div className="mb-2 flex min-w-0 items-center gap-1.5 text-sm font-semibold leading-none text-[#59626c]">
          <Clock3 className="h-4 w-4 shrink-0" />
          <span className="truncate">{formatBlogDate(post.publishedAt || post.createdAt)}</span>
          <span className="shrink-0" aria-hidden="true">
            |
          </span>
          <span className="shrink-0">{formatReadTime(post.readTimeMinutes)}</span>
        </div>
        <h2 className="mb-2 line-clamp-2 text-base font-bold leading-snug text-[#071116]">
          {post.title}
        </h2>
        <p className="line-clamp-3 text-sm font-medium leading-relaxed text-[#59626c]">
          {post.excerpt}
        </p>
      </div>
    </AppLink>
  );
}

function BlogCardSkeleton() {
  return (
    <div className="animate-pulse overflow-hidden rounded-2xl border border-[#eef2f5] bg-white">
      <div className="aspect-[16/10] rounded-2xl bg-gray-200" />
      <div className="space-y-3 px-4 pb-5 pt-4">
        <div className="h-4 w-3/4 rounded bg-gray-200" />
        <div className="h-5 w-full rounded bg-gray-200" />
        <div className="h-4 w-full rounded bg-gray-200" />
        <div className="h-4 w-2/3 rounded bg-gray-200" />
      </div>
    </div>
  );
}

export function BlogComingSoonPage() {
  const [activeCategoryId, setActiveCategoryId] = useState('all');
  const [showAllCategories, setShowAllCategories] = useState(false);
  const { data: categories = [], isLoading: categoriesLoading } = useBlogCategories();
  const {
    data: postsResult,
    isLoading: postsLoading,
    isError,
  } = useBlogPosts({
    status: 'published',
    category: activeCategoryId === 'all' ? undefined : activeCategoryId,
    page: 1,
    limit: 100,
  });
  const categoryOptions: Array<Pick<BlogCategory, 'id' | 'name'>> = [
    { id: 'all', name: 'All' },
    ...categories,
  ];
  const visibleCategories = showAllCategories ? categoryOptions : categoryOptions.slice(0, 6);
  const posts = postsResult?.posts ?? [];
  const isLoading = categoriesLoading || postsLoading;

  return (
    <>
      <SEO
        title="Blog"
        description="Discover inspiring stories, career insights, community updates, and valuable resources from our alumni network."
      />

      <main className="min-h-screen bg-[#F8F8F7] text-[#071116]">
        <section className="container-custom py-8 sm:py-10">
          <header className="mb-8 max-w-2xl">
            <h1 className="mb-2 text-4xl font-extrabold leading-tight text-[#071116]">Blog</h1>
            <p className="max-w-xl text-base font-medium leading-relaxed text-[#4B5563]">
              Discover inspiring stories, career insights, community updates, and valuable resources
              from our alumni network.
            </p>
          </header>

          <div className="mb-8 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div className="scrollbar-hide flex min-w-0 flex-1 gap-2 overflow-x-auto scroll-smooth pb-1">
              {visibleCategories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => setActiveCategoryId(category.id)}
                  className={`min-h-10 shrink-0 rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 ${
                    activeCategoryId === category.id
                      ? 'border-primary-500 bg-primary-500 text-white'
                      : 'border-primary-100 bg-white text-[#4B5563] hover:border-primary-300 hover:text-primary-600'
                  }`}
                >
                  {category.name}
                </button>
              ))}

              {!showAllCategories && categoryOptions.length > visibleCategories.length ? (
                <button
                  type="button"
                  onClick={() => setShowAllCategories(true)}
                  className="inline-flex min-h-10 shrink-0 items-center justify-center rounded-full border border-primary-100 bg-white px-3 py-2 text-primary-500 transition-colors hover:border-primary-300 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                  aria-label="Show all categories"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              ) : null}
            </div>

            <div className="shrink-0 lg:ml-auto">
              <button
                type="button"
                className="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-primary-100 bg-white px-4 py-2 text-sm font-semibold text-[#4B5563] shadow-[0_1px_2px_rgba(7,17,22,0.04)] transition-colors hover:border-primary-300 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
              >
                Latest
                <ChevronDown className="h-4 w-4" />
              </button>
            </div>
          </div>

          {isLoading ? (
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
              {Array.from({ length: 8 }).map((_, index) => (
                <BlogCardSkeleton key={index} />
              ))}
            </div>
          ) : null}

          {isError ? (
            <div className="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">
              We could not load blog posts right now.
            </div>
          ) : null}

          {!isLoading && !isError && posts.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No blog posts yet"
              description="Published stories and updates from the alumni community will appear here."
            />
          ) : null}

          {!isLoading && !isError && posts.length > 0 ? (
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
              {posts.map((post) => (
                <BlogCard key={post.id} post={post} />
              ))}
            </div>
          ) : null}
        </section>
      </main>
    </>
  );
}
