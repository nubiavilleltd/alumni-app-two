import { useEffect, useMemo, useState } from 'react';
import { ArrowLeft, Clock3, FileSearch } from 'lucide-react';
import { useParams } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useBlogPostDetail } from '../hooks/useBlogs';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';

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

function createSectionId(heading: string, index: number) {
  const slug = heading
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return `blog-section-${slug || index + 1}`;
}

export function BlogDetailPage() {
  const { slug = '' } = useParams();
  const { data: post, isLoading } = useBlogPostDetail(slug);
  const galleryImages = useMemo(() => {
    if (!post) return [];

    const images = [
      ...(post.coverImageUrl
        ? [
            {
              id: 'cover',
              imageUrl: post.coverImageUrl,
              altText: post.title,
            },
          ]
        : []),
      ...post.galleryImages.map((image) => ({
        id: image.id,
        imageUrl: image.imageUrl,
        altText: image.altText || post.title,
      })),
    ];
    const seenUrls = new Set<string>();

    return images.filter((image) => {
      if (!image.imageUrl || seenUrls.has(image.imageUrl)) return false;
      seenUrls.add(image.imageUrl);
      return true;
    });
  }, [post]);
  const sectionLinks = useMemo(
    () =>
      (post?.sections ?? []).map((section, index) => ({
        ...section,
        sectionId: createSectionId(section.heading, index),
      })),
    [post?.sections],
  );
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [activeSectionId, setActiveSectionId] = useState('');
  const activeImage = galleryImages[activeImageIndex] ?? galleryImages[0];

  useEffect(() => {
    setActiveImageIndex(0);
  }, [post?.id]);

  useEffect(() => {
    setActiveSectionId(sectionLinks[0]?.sectionId ?? '');
  }, [sectionLinks]);

  useEffect(() => {
    if (sectionLinks.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const visibleEntry = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visibleEntry?.target.id) {
          setActiveSectionId(visibleEntry.target.id);
        }
      },
      {
        root: null,
        rootMargin: '-25% 0px -55% 0px',
        threshold: [0.1, 0.25, 0.5, 0.75],
      },
    );

    sectionLinks.forEach((section) => {
      const element = document.getElementById(section.sectionId);
      if (element) observer.observe(element);
    });

    return () => observer.disconnect();
  }, [sectionLinks]);

  const scrollToSection = (sectionId: string) => {
    document.getElementById(sectionId)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    });
  };

  return (
    <>
      <SEO
        title={post?.title || 'Blog'}
        description={post?.excerpt || 'Read the latest blog post from the alumni community.'}
        image={post?.coverImageUrl || undefined}
      />

      <main className="min-h-screen bg-[#F8F8F7] text-[#071116]">
        <section className="container-custom pb-16 pt-4 sm:pb-14 sm:pt-5">
          <AppLink
            href={ANNOUNCEMENT_ROUTES.BLOG}
            className="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700"
          >
            <ArrowLeft className="h-4 w-4" strokeWidth={2.4} />
            Back to blog
          </AppLink>

          {isLoading ? (
            <div className="mt-6 animate-pulse">
              <div className="aspect-[16/8] rounded-t-[2rem] bg-accent-100" />
              <div className="space-y-4 py-6 md:py-8">
                <div className="h-4 w-40 rounded bg-accent-100" />
                <div className="h-10 w-3/4 rounded bg-accent-100" />
                <div className="h-4 w-full rounded bg-accent-100" />
                <div className="h-4 w-5/6 rounded bg-accent-100" />
              </div>
            </div>
          ) : !post ? (
            <div className="mt-6 rounded-[2rem] bg-white p-10 text-center shadow-sm ring-1 ring-accent-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-50">
                <FileSearch className="h-7 w-7 text-accent-700" strokeWidth={2.2} />
              </div>
              <h1 className="mt-4 text-2xl font-bold text-accent-900">Blog post not found</h1>
              <p className="mt-2 text-sm text-accent-500">
                It may have been removed or the link may no longer be valid.
              </p>
            </div>
          ) : (
            <article className="mt-6">
              <div className="overflow-hidden rounded-[1.75rem] bg-accent-100">
                <img
                  src={activeImage?.imageUrl || FALLBACK_IMAGE}
                  alt={activeImage?.altText || ''}
                  className="block aspect-[16/7] w-full object-cover"
                />
              </div>

              {galleryImages.length > 1 ? (
                <div className="scrollbar-hide mt-3 flex gap-3 overflow-x-auto pb-2">
                  {galleryImages.map((image, index) => {
                    const isActive = index === activeImageIndex;

                    return (
                      <button
                        key={`${image.id}-${image.imageUrl}`}
                        type="button"
                        onClick={() => setActiveImageIndex(index)}
                        className={[
                          'h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 transition sm:h-20 sm:w-20',
                          isActive
                            ? 'border-primary-500 shadow-[0_0.65rem_1.4rem_rgb(var(--color-primary-500)/0.18)]'
                            : 'border-transparent opacity-80 hover:opacity-100',
                        ].join(' ')}
                        aria-label={`Show image ${index + 1}`}
                      >
                        <img
                          src={image.imageUrl}
                          alt=""
                          className="h-full w-full object-cover"
                          loading="lazy"
                        />
                      </button>
                    );
                  })}
                </div>
              ) : null}

              <div className="py-8 md:py-10">
                <div className="flex flex-wrap items-center gap-3">
                  <span className="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-primary-700">
                    {post.categoryName || 'Blog'}
                  </span>
                  <span className="inline-flex items-center gap-2 text-sm text-accent-500">
                    <Clock3 className="h-4 w-4" strokeWidth={2.2} />
                    {formatBlogDate(post.publishedAt || post.createdAt)}
                  </span>
                  <span className="text-sm font-semibold text-accent-500">
                    {formatReadTime(post.readTimeMinutes)}
                  </span>
                </div>

                <h1 className="type-section-title mt-4 text-accent-950">{post.title}</h1>

                {post.excerpt ? (
                  <p className="mt-4 max-w-3xl text-lg leading-8 text-accent-600">{post.excerpt}</p>
                ) : null}

                <div className="mt-12 grid gap-10 lg:grid-cols-[minmax(15rem,22rem)_minmax(0,1fr)]">
                  {sectionLinks.length > 0 ? (
                    <aside className="lg:sticky lg:top-28 lg:self-start">
                      <nav
                        className="relative border-l-4 border-accent-100 pl-5"
                        aria-label="Blog sections"
                      >
                        <div className="space-y-2">
                          {sectionLinks.map((section) => {
                            const isActive = activeSectionId === section.sectionId;

                            return (
                              <button
                                key={section.sectionId}
                                type="button"
                                onClick={() => scrollToSection(section.sectionId)}
                                className={[
                                  'relative block w-full rounded-lg px-3 py-2 text-left text-sm font-bold leading-snug transition-colors',
                                  isActive
                                    ? 'text-accent-950'
                                    : 'text-accent-500 hover:bg-primary-50 hover:text-primary-700',
                                ].join(' ')}
                              >
                                {isActive ? (
                                  <span className="absolute -left-[1.75rem] top-2 h-8 w-1.5 rounded-full bg-primary-500" />
                                ) : null}
                                {section.heading}
                              </button>
                            );
                          })}
                        </div>
                      </nav>
                    </aside>
                  ) : null}

                  <div className="min-w-0 space-y-12 text-base leading-8 text-accent-700">
                    {sectionLinks.length > 0 ? (
                      sectionLinks.map((section) => (
                        <section
                          key={section.sectionId}
                          id={section.sectionId}
                          className="scroll-mt-28"
                        >
                          <h2 className="text-2xl font-bold leading-tight text-accent-950 sm:text-3xl">
                            {section.heading}
                          </h2>
                          <p className="mt-4 whitespace-pre-line text-lg leading-8">
                            {section.body}
                          </p>
                        </section>
                      ))
                    ) : (
                      <p>This blog post does not have a full body yet.</p>
                    )}
                  </div>
                </div>
              </div>
            </article>
          )}
        </section>
      </main>
    </>
  );
}
