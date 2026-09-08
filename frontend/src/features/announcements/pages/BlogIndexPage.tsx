import { Clock3, Megaphone, Plus, SlidersHorizontal } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { SEO } from '@/shared/common/SEO';
import { ButtonLink } from '@/shared/components/ui/Button';
import { AppLink } from '@/shared/components/ui/AppLink';
import { Pagination } from '@/shared/components/ui/Pagination';
import { ROUTES } from '@/shared/constants/routes';
import { EVENT_ROUTES } from '@/features/events/routes';
import { AnnouncementEditorModal } from '@/features/announcements/components/AnnouncementEditorModal';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import {
  usePublicAnnouncements,
  useBirthdayAnnouncements,
} from '@/features/announcements/hooks/useAnnouncements';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';
import type { AnnouncementType, NewsItem } from '@/features/announcements/types/announcement.types';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { BirthdaySection } from '../components/BirthdaySection';

const FALLBACK_IMAGE = '/news-1.png';
const pageShellClassName = 'container-custom pb-16 pt-4 sm:pb-14 sm:pt-5';
const cardClassName =
  'flex min-w-0 overflow-hidden rounded-2xl border border-[#eef2f5] bg-white p-3.5 shadow-[0_1px_2px_rgba(7,17,22,0.04)] no-underline transition-colors duration-200 hover:border-primary-100 max-sm:flex-col max-sm:p-0';
const cardImageWrapClassName =
  'w-[min(38%,14rem)] basis-[min(38%,14rem)] flex-shrink-0 overflow-hidden rounded-xl bg-[#e9edf1] max-sm:w-full max-sm:basis-auto max-sm:aspect-[16/9] max-sm:rounded-none';
const cardBodyClassName =
  'flex min-w-0 flex-1 flex-col justify-start px-4 py-[0.2rem] pr-[0.1rem] max-sm:p-[1.15rem]';
const metaClassName =
  'm-0 flex items-center gap-[0.55rem] text-sm font-semibold leading-[1.2] text-[#59626c]';
const actionClassName =
  'min-h-10 rounded-full border-2 border-primary-500 bg-transparent px-5 py-2.5 text-sm font-bold text-primary-500 transition-colors hover:bg-primary-500 hover:text-white whitespace-nowrap';
const boardClassName =
  'hidden gap-5 min-[1181px]:grid min-[1181px]:grid-cols-2 min-[1181px]:items-start';
const sideListClassName = 'grid gap-3.5 min-[1181px]:grid-rows-3 [&>*]:h-full';
const continuationGridClassName = 'mt-5 hidden gap-5 min-[1181px]:grid min-[1181px]:grid-cols-2';
const stackedListClassName = 'grid gap-5 min-[1181px]:hidden';

type AnnouncementFilter = 'all' | AnnouncementType | 'project';

const typeFilters: Array<{ label: string; value: AnnouncementFilter }> = [
  { label: 'All updates', value: 'all' },
  { label: 'Info', value: 'info' },
  { label: 'Events', value: 'event' },
  { label: 'Projects', value: 'project' },
];

const DESKTOP_SIDE_CARD_GAP_PX = 14;
const DESKTOP_COMPACT_STACK_HEIGHT_SCALE = 0.77;
const ANNOUNCEMENTS_PER_PAGE = 8;

function formatAnnouncementDate(date: string) {
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(date);
  const parsed = new Date(isDateOnly ? `${date}T00:00:00` : date);
  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function getAnnouncementSummary(item: NewsItem) {
  return item.excerpt?.trim() || item.content?.trim() || 'Read the latest community update.';
}

function AnnouncementCard({ item, compact = false }: { item: NewsItem; compact?: boolean }) {
  const rootClassName = compact
    ? 'flex h-full min-h-0 min-w-0 overflow-hidden rounded-2xl border border-[#eef2f5] bg-white p-2.5 shadow-[0_1px_2px_rgba(7,17,22,0.04)] no-underline transition-colors duration-200 hover:border-primary-100'
    : cardClassName;
  const imageWrapClassName = compact
    ? 'w-[min(34%,9rem)] basis-[min(34%,9rem)] flex-shrink-0 overflow-hidden rounded-[0.9rem] bg-[#e9edf1]'
    : cardImageWrapClassName;
  const bodyClassName = compact
    ? 'flex min-h-0 min-w-0 flex-1 flex-col justify-start px-2.5 py-0 pr-0'
    : cardBodyClassName;
  const titleClassName = compact
    ? 'm-0 line-clamp-2 text-[0.92rem] font-bold leading-[1.12] text-[#071116]'
    : 'm-0 text-[clamp(0.95rem,1.15vw,1.1rem)] font-bold leading-[1.18] text-[#071116]';
  const summaryClassName = compact
    ? 'mt-1 overflow-hidden text-[0.76rem] font-medium leading-[1.2] text-[#59626c] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:1]'
    : 'mt-[0.45rem] overflow-hidden text-sm font-medium leading-[1.35] text-[#59626c] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]';
  const metaRowClassName = compact ? `${metaClassName} mt-1.5 text-[0.76rem]` : metaClassName;

  return (
    <AppLink href={item.href ?? ANNOUNCEMENT_ROUTES.DETAIL(item.slug)} className={rootClassName}>
      <div className={imageWrapClassName}>
        <img src={item.image || FALLBACK_IMAGE} alt="" className="h-full w-full object-cover" />
      </div>

      <div className={bodyClassName}>
        <h3 className={titleClassName}>{item.title}</h3>
        <p className={summaryClassName}>{getAnnouncementSummary(item)}</p>
        <p className={metaRowClassName}>
          <Clock3 className="h-4 w-4 flex-shrink-0" />
          {formatAnnouncementDate(item.startsAt || item.date)}
        </p>
      </div>
    </AppLink>
  );
}

function AnnouncementCardSkeleton({ compact = false }: { compact?: boolean }) {
  const rootClassName = compact
    ? 'flex h-full min-w-0 overflow-hidden rounded-2xl border border-[#eef2f5] bg-white p-3 shadow-[0_1px_2px_rgba(7,17,22,0.04)]'
    : cardClassName;
  const imageWrapClassName = compact
    ? 'w-[min(35%,10.5rem)] basis-[min(35%,10.5rem)] flex-shrink-0 overflow-hidden rounded-[1rem] bg-accent-100'
    : `${cardImageWrapClassName} bg-accent-100`;
  const bodyClassName = compact
    ? 'flex min-w-0 flex-1 flex-col justify-center px-3 py-0 pr-0'
    : cardBodyClassName;

  return (
    <div className={`${rootClassName} animate-pulse`}>
      <div className={imageWrapClassName} />
      <div className={bodyClassName}>
        <div className={`rounded bg-accent-100 ${compact ? 'h-4 w-4/5' : 'h-4 w-2/3'}`} />
        <div
          className={`rounded bg-accent-100 ${compact ? 'mt-2 h-3 w-full' : 'mt-3 h-3 w-full'}`}
        />
        <div className={`rounded bg-accent-100 ${compact ? 'mt-2 h-3 w-1/2' : 'mt-2 h-3 w-5/6'}`} />
        {!compact && <div className="mt-4 h-3 w-1/3 rounded bg-accent-100" />}
      </div>
    </div>
  );
}

export default function BlogIndexPage() {
  const user = useIdentityStore((state) => state.user);
  const [selectedType, setSelectedType] = useState<AnnouncementFilter>('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [dateRangeFilter, setDateRangeFilter] = useState('');
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const featuredCardRef = useRef<HTMLElement | null>(null);
  const sideListRef = useRef<HTMLDivElement | null>(null);
  const [sideListHeight, setSideListHeight] = useState<number | null>(null);

  const { data: announcements = [], isLoading } = usePublicAnnouncements();

  const { data: birthdays, isLoading: isLoadingBirthdays } = useBirthdayAnnouncements();

  const yearOptions = useMemo(
    () =>
      Array.from(
        new Set(
          announcements.map((item) => {
            const date = new Date(item.startsAt || item.date);
            return String(item.year ?? (Number.isNaN(date.getTime()) ? '' : date.getFullYear()));
          }),
        ),
      )
        .filter(Boolean)
        .sort((a, b) => Number(b) - Number(a))
        .map((value) => ({ label: value, value })),
    [announcements],
  );

  const sortedAnnouncements = useMemo(() => {
    const q = searchTerm.trim().toLowerCase();
    const now = new Date();
    const currentYear = now.getFullYear();
    const rangeInDays = dateRangeFilter === '30' ? 30 : dateRangeFilter === '90' ? 90 : null;
    const rangeStart = rangeInDays
      ? new Date(now.getTime() - rangeInDays * 24 * 60 * 60 * 1000)
      : null;

    return announcements
      .filter((item) => {
        const date = new Date(item.startsAt || item.date);
        const itemYear = String(
          item.year ?? (Number.isNaN(date.getTime()) ? '' : date.getFullYear()),
        );
        const matchesSearch =
          !q ||
          [item.title, item.excerpt, item.content, item.tag]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(q);
        const matchesType =
          selectedType === 'all' ||
          (selectedType === 'project' ? item.source === 'project' : item.type === selectedType);
        const matchesYear = !yearFilter || itemYear === yearFilter;
        const matchesDateRange =
          !dateRangeFilter ||
          (!Number.isNaN(date.getTime()) &&
            (dateRangeFilter === 'year'
              ? date.getFullYear() === currentYear
              : Boolean(rangeStart && date >= rangeStart)));

        return matchesSearch && matchesType && matchesYear && matchesDateRange;
      })
      .sort(
        (a, b) =>
          new Date(b.startsAt || b.date).getTime() - new Date(a.startsAt || a.date).getTime(),
      );
  }, [announcements, dateRangeFilter, searchTerm, selectedType, yearFilter]);

  const activeAdvancedFilterCount = [searchTerm.trim(), yearFilter, dateRangeFilter].filter(
    Boolean,
  ).length;
  const totalPages = Math.max(1, Math.ceil(sortedAnnouncements.length / ANNOUNCEMENTS_PER_PAGE));
  const pageAnnouncements = sortedAnnouncements.slice(
    (currentPage - 1) * ANNOUNCEMENTS_PER_PAGE,
    currentPage * ANNOUNCEMENTS_PER_PAGE,
  );

  const [featured, ...latest] = pageAnnouncements;
  const isAdmin = user?.role === 'admin';
  // Height of each compact card in the continuation grid below — derived from
  // the measured side list so the grid rows stay consistent.
  const compactCardHeight =
    sideListHeight !== null
      ? Math.max((sideListHeight - DESKTOP_SIDE_CARD_GAP_PX * 2) / 3, 0)
      : null;

  // Measure the right-side list's natural height so we can cap the featured
  // card to the same height (reducing it, not growing the side cards).
  useEffect(() => {
    const element = sideListRef.current;

    if (!element || typeof window === 'undefined') {
      setSideListHeight(null);
      return;
    }

    const updateHeight = () => {
      if (window.innerWidth < 1181) {
        setSideListHeight(null);
        return;
      }

      setSideListHeight(element.getBoundingClientRect().height);
    };

    updateHeight();

    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', updateHeight);
      return () => window.removeEventListener('resize', updateHeight);
    }

    const observer = new ResizeObserver(updateHeight);
    observer.observe(element);
    window.addEventListener('resize', updateHeight);

    return () => {
      observer.disconnect();
      window.removeEventListener('resize', updateHeight);
    };
  }, [
    latest
      .slice(0, 3)
      .map((i) => i.slug)
      .join(','),
  ]);

  useEffect(() => {
    setCurrentPage(1);
  }, [dateRangeFilter, searchTerm, selectedType, yearFilter]);

  const clearAllFilters = () => {
    setSelectedType('all');
    setSearchTerm('');
    setYearFilter('');
    setDateRangeFilter('');
    setCurrentPage(1);
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    if (key === 'search') setSearchTerm(value);
    if (key === 'year') setYearFilter(value);
    if (key === 'dateRange') setDateRangeFilter(value);
    setCurrentPage(1);
  };

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  return (
    <>
      <SEO
        title="Announcements"
        description="Read the latest Alumni Portal updates, event notices, welfare reminders, and project news."
      />

      <main className="min-h-full bg-[#F8F8F7] text-[#071116]">
        <section className={pageShellClassName} aria-labelledby="announcements-title">
          <header className="mb-6">
            {/* Header */}
            <div className="mb-12 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
              <div className="max-w-2xl">
                <h1 id="announcements-title" className="type-section-title m-0 text-gray-900">
                  Announcements
                </h1>
                <p className="type-card-body mt-1 text-gray-500">
                  Read the latest community updates, event notices, and project news
                </p>
              </div>

              <div className="flex w-full flex-wrap items-center justify-center gap-3 sm:w-auto sm:flex-shrink-0 sm:justify-end">
                <ButtonLink
                  href={ROUTES.PROJECTS.ROOT}
                  variant="outline"
                  className={`${actionClassName} flex-1 text-center sm:flex-none`}
                >
                  Go to our Projects
                </ButtonLink>
                <ButtonLink
                  href={EVENT_ROUTES.ROOT}
                  variant="outline"
                  className={`${actionClassName} flex-1 text-center sm:flex-none`}
                >
                  Go to Events
                </ButtonLink>
              </div>
            </div>

            {/* Controls */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex flex-wrap gap-2">
                {typeFilters.map((filter) => (
                  <button
                    key={filter.value}
                    type="button"
                    onClick={() => setSelectedType(filter.value)}
                    className={`rounded-full px-4 py-2 text-sm font-semibold transition-colors ${
                      selectedType === filter.value
                        ? 'bg-primary-500 text-white'
                        : 'bg-white text-accent-700 shadow-sm ring-1 ring-accent-100 hover:bg-primary-50'
                    }`}
                  >
                    {filter.label}
                  </button>
                ))}
              </div>

              <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <button
                  type="button"
                  onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
                  aria-expanded={showAdvancedFilters}
                  className="flex w-full items-center justify-center gap-1.5 rounded-full border border-[#e1e6ec] bg-white px-4 py-2 text-sm font-semibold text-[#58606b] shadow-sm transition-colors hover:bg-[#f5f8fa] sm:w-auto"
                >
                  <SlidersHorizontal className="h-4 w-4" />
                  Advanced filters
                  {activeAdvancedFilterCount > 0 && (
                    <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] text-white">
                      {activeAdvancedFilterCount}
                    </span>
                  )}
                </button>

                {isAdmin && (
                  <button
                    type="button"
                    onClick={() => setIsEditorOpen(true)}
                    className="flex w-full flex-shrink-0 items-center justify-center gap-1.5 rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600 sm:w-auto"
                  >
                    <span>Create Announcement</span>
                    <Plus className="h-4 w-4" />
                  </button>
                )}
              </div>
            </div>

            {showAdvancedFilters && (
              <AdvancedFiltersPanel
                title="Refine announcements"
                description="Search updates and narrow them by publication year or recency."
                fields={[
                  {
                    key: 'search',
                    kind: 'search',
                    value: searchTerm,
                    placeholder: 'Search announcements',
                  },
                  {
                    key: 'year',
                    kind: 'select',
                    label: 'Publication year',
                    value: yearFilter,
                    placeholder: 'Any year',
                    options: yearOptions,
                  },
                  {
                    key: 'dateRange',
                    kind: 'select',
                    label: 'Published',
                    value: dateRangeFilter,
                    placeholder: 'Any time',
                    options: [
                      { label: 'Last 30 days', value: '30' },
                      { label: 'Last 90 days', value: '90' },
                      { label: 'This year', value: 'year' },
                    ],
                  },
                ]}
                onFieldChange={handleAdvancedFilterChange}
                onReset={clearAllFilters}
                hasActiveFilters={activeAdvancedFilterCount > 0}
              />
            )}

            {(selectedType !== 'all' || activeAdvancedFilterCount > 0) && (
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                <span>
                  Showing {sortedAnnouncements.length}{' '}
                  {sortedAnnouncements.length === 1 ? 'announcement' : 'announcements'} matching
                  your filters
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
          </header>

          {/* {!isLoadingBirthdays && birthdays && birthdays.length > 0 && (
          )} */}
          <BirthdaySection people={birthdays ?? []} isLoading={isLoadingBirthdays} />

          {isLoading ? (
            <>
              <div className={boardClassName}>
                <div className="flex flex-col overflow-hidden rounded-2xl border border-[#eef2f5] bg-white shadow-[0_1px_2px_rgba(7,17,22,0.04)]">
                  <div className="aspect-[16/9] bg-accent-100" />
                  <div className="space-y-4 p-[1.15rem_1.25rem_1.35rem]">
                    <div className="h-4 w-40 rounded bg-accent-100" />
                    <div className="h-8 w-4/5 rounded bg-accent-100" />
                    <div className="h-4 w-full rounded bg-accent-100" />
                    <div className="h-4 w-5/6 rounded bg-accent-100" />
                    <div className="h-4 w-1/3 rounded bg-accent-100" />
                  </div>
                </div>
                <div className={sideListClassName}>
                  {Array.from({ length: 3 }).map((_, index) => (
                    <AnnouncementCardSkeleton key={index} compact />
                  ))}
                </div>
              </div>

              <div className={stackedListClassName}>
                {Array.from({ length: 4 }).map((_, index) => (
                  <AnnouncementCardSkeleton key={index} />
                ))}
              </div>
            </>
          ) : featured ? (
            <>
              <div className={boardClassName}>
                <article
                  ref={featuredCardRef}
                  style={sideListHeight ? { maxHeight: `${sideListHeight}px` } : undefined}
                  className="flex flex-col overflow-hidden rounded-2xl border border-[#eef2f5] bg-white shadow-[0_1px_2px_rgba(7,17,22,0.04)]"
                >
                  {/* Image fills remaining space after the text block shrinks the card */}
                  <div className="min-h-[6rem] flex-1 overflow-hidden bg-[#e9edf1]">
                    <img
                      src={featured.image || FALLBACK_IMAGE}
                      alt=""
                      className="h-full w-full object-cover"
                    />
                  </div>

                  <div className="flex flex-shrink-0 flex-col p-[1.15rem_1.25rem_1.35rem]">
                    <p className={metaClassName}>
                      <Clock3 className="h-4 w-4 flex-shrink-0" />
                      {formatAnnouncementDate(featured.startsAt || featured.date)}
                    </p>
                    <h2 className="mt-4 text-[clamp(1.35rem,2vw,1.75rem)] font-bold leading-[1.24] text-[#071116]">
                      {featured.title}
                    </h2>
                    <p className="mt-[0.65rem] text-base font-medium leading-[1.32] text-[#59626c]">
                      {getAnnouncementSummary(featured)}{' '}
                      <span className="whitespace-nowrap font-extrabold text-primary-500">
                        Read more
                      </span>
                    </p>
                    <div className="mt-5">
                      <ButtonLink
                        href={featured.href ?? ANNOUNCEMENT_ROUTES.DETAIL(featured.slug)}
                        variant="primary"
                      >
                        Open update
                      </ButtonLink>
                    </div>
                  </div>
                </article>

                {/* Side list renders at its natural height; we measure it to cap the featured card */}
                <div ref={sideListRef} className={sideListClassName}>
                  {latest.slice(0, 3).map((item) => (
                    <AnnouncementCard key={item.slug} item={item} compact />
                  ))}
                </div>
              </div>

              <div className={stackedListClassName}>
                {pageAnnouncements.map((item) => (
                  <AnnouncementCard key={item.slug} item={item} />
                ))}
              </div>
            </>
          ) : (
            <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm ring-1 ring-accent-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50">
                <Megaphone className="h-7 w-7 text-primary-500" />
              </div>
              <h2 className="mt-4 text-2xl font-bold text-accent-900">No announcements yet</h2>
              <p className="mt-2 text-sm text-accent-500">
                Fresh updates will appear here as soon as the admin team publishes them.
              </p>
            </div>
          )}

          {(isLoading || latest.length > 3) && (
            <div
              className={continuationGridClassName}
              style={compactCardHeight ? { gridAutoRows: `${compactCardHeight}px` } : undefined}
            >
              {isLoading
                ? Array.from({ length: 4 }).map((_, index) => (
                    <AnnouncementCardSkeleton key={index} compact />
                  ))
                : latest
                    .slice(3)
                    .map((item) => <AnnouncementCard key={item.slug} item={item} compact />)}
            </div>
          )}

          {!isLoading && sortedAnnouncements.length > 0 ? (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => {
                setCurrentPage(page);
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
            />
          ) : null}
        </section>
      </main>

      <AnnouncementEditorModal isOpen={isEditorOpen} onClose={() => setIsEditorOpen(false)} />
    </>
  );
}
