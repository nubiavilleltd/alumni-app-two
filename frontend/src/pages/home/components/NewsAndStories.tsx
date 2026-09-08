import { Icon } from '@iconify/react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useLatestAnnouncements } from '@/features/announcements/hooks/useAnnouncements';
import type { NewsItem } from '@/features/announcements/types/announcement.types';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';

const FALLBACK_IMAGE = '/news-1.png';

function formatAnnouncementDate(date: string) {
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function SectionHeading({ showViewAll = true }: { showViewAll: boolean }) {
  return (
    <header className="mb-10 flex items-start justify-between gap-6">
      <div className="min-w-0">
        <div className="relative inline-flex">
          <span
            aria-hidden="true"
            className="absolute -left-2 top-1/2 h-5 w-[2px] -translate-y-1/2 bg-primary-500"
          />
          <span
            aria-hidden="true"
            className="absolute -top-2 right-[-10px] h-[2px] w-4 bg-primary-500"
          />
          <span
            aria-hidden="true"
            className="absolute -top-2 right-[-10px] h-4 w-[2px] bg-primary-500"
          />

          <p className="type-small font-semibold text-[#071116]">Announcements</p>
        </div>

        <h2 className="type-section-title mt-7 max-w-5xl text-[#071116]">
          Important news and updates from the alumni community
        </h2>
      </div>

      {showViewAll && (
        <AppLink
          href={ANNOUNCEMENT_ROUTES.ROOT}
          className="type-button mt-1 hidden shrink-0 items-center gap-1 text-primary-500 transition-colors duration-200 hover:text-primary-600 md:inline-flex"
        >
          See All
          <Icon icon="mdi:chevron-right" aria-hidden="true" className="h-5 w-5" />
        </AppLink>
      )}
    </header>
  );
}

function AnnouncementMeta({ date }: { date: string }) {
  return (
    <p className="type-small inline-flex items-center gap-1.5 text-[#556070]">
      <Icon icon="mdi:clock-time-three-outline" aria-hidden="true" className="h-5 w-5" />
      <span>{formatAnnouncementDate(date)}</span>
    </p>
  );
}

function FeaturedAnnouncementCard({ item }: { item: NewsItem }) {
  return (
    <AppLink
      href={item.href ?? ANNOUNCEMENT_ROUTES.DETAIL(item.slug)}
      className="group flex h-full flex-col overflow-hidden rounded-[1.4rem] bg-white text-[#071116] shadow-[0_16px_38px_rgba(7,17,22,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_22px_48px_rgba(7,17,22,0.12)]"
    >
      <div className="h-[280px] overflow-hidden bg-[#e9edf1] sm:h-[340px] lg:h-[384px]">
        <img
          src={item.image || FALLBACK_IMAGE}
          alt={item.title}
          className="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-[1.03]"
        />
      </div>

      <div className="flex flex-1 flex-col px-4 pb-5 pt-4 sm:px-5">
        <AnnouncementMeta date={item.date} />

        <h3 className="type-card-title-featured mt-4 line-clamp-2 text-[#071116]">{item.title}</h3>

        {item.excerpt ? (
          <p className="type-card-body mt-3 line-clamp-3 text-[#556070]">
            {item.excerpt}{' '}
            <span className="font-semibold text-primary-500 transition-colors duration-200 group-hover:text-primary-600">
              Read more
            </span>
          </p>
        ) : (
          <span className="type-small mt-3 inline-flex text-primary-500 transition-colors duration-200 group-hover:text-primary-600">
            Read more
          </span>
        )}
      </div>
    </AppLink>
  );
}

function AnnouncementListCard({ item }: { item: NewsItem }) {
  return (
    <AppLink
      href={item.href ?? ANNOUNCEMENT_ROUTES.DETAIL(item.slug)}
      className="group grid overflow-hidden rounded-[1.4rem] bg-white p-4 text-[#071116] shadow-[0_16px_38px_rgba(7,17,22,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_22px_48px_rgba(7,17,22,0.12)] sm:grid-cols-[240px_1fr] sm:gap-4"
    >
      <div className="h-[150px] overflow-hidden rounded-xl bg-[#e9edf1] sm:h-[138px]">
        <img
          src={item.image || FALLBACK_IMAGE}
          alt={item.title}
          className="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-[1.03]"
        />
      </div>

      <div className="mt-4 flex min-w-0 flex-col sm:mt-0">
        <h3 className="type-card-title line-clamp-2 text-[#071116]">{item.title}</h3>

        {item.excerpt ? (
          <p className="type-card-body mt-2 line-clamp-3 text-[#556070]">{item.excerpt}</p>
        ) : null}

        <div className="mt-3">
          <AnnouncementMeta date={item.date} />
        </div>
      </div>
    </AppLink>
  );
}

function FeaturedSkeleton() {
  return (
    <div className="flex h-full animate-pulse flex-col overflow-hidden rounded-[1.4rem] bg-white shadow-[0_16px_38px_rgba(7,17,22,0.08)]">
      <div className="h-[280px] bg-[#e5e7eb] sm:h-[340px] lg:h-[384px]" />
      <div className="flex flex-col gap-3 px-5 pb-5 pt-4">
        <span className="h-4 w-36 rounded-full bg-[#e5e7eb]" />
        <span className="h-7 w-5/6 rounded-full bg-[#e5e7eb]" />
        <span className="h-4 w-full rounded-full bg-[#e5e7eb]" />
        <span className="h-4 w-2/3 rounded-full bg-[#e5e7eb]" />
      </div>
    </div>
  );
}

function AnnouncementSkeleton() {
  return (
    <div className="grid animate-pulse overflow-hidden rounded-[1.4rem] bg-white p-4 shadow-[0_16px_38px_rgba(7,17,22,0.08)] sm:grid-cols-[240px_1fr] sm:gap-4">
      <div className="h-[150px] rounded-xl bg-[#e5e7eb] sm:h-[138px]" />

      <div className="mt-4 flex flex-col gap-3 sm:mt-0">
        <span className="h-5 w-5/6 rounded-full bg-[#e5e7eb]" />
        <span className="h-4 w-full rounded-full bg-[#e5e7eb]" />
        <span className="h-4 w-3/4 rounded-full bg-[#e5e7eb]" />
        <span className="mt-3 h-4 w-36 rounded-full bg-[#e5e7eb]" />
      </div>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="rounded-[1.4rem] bg-white p-8 text-[#071116] shadow-[0_16px_38px_rgba(7,17,22,0.08)] lg:col-span-2">
      <p className="type-small inline-flex items-center gap-2 text-[#556070]">
        <Icon icon="mdi:bullhorn-outline" aria-hidden="true" className="h-5 w-5" />
        Updates
      </p>

      <h3 className="type-card-title-featured mt-4 text-[#071116]">No announcements yet</h3>

      <p className="type-card-body mt-3 max-w-2xl text-[#556070]">
        New community updates will appear here as soon as they are published.
      </p>
    </div>
  );
}

export default function HomeAnnouncements() {
  const { data: items = [], isLoading } = useLatestAnnouncements(4);
  const featured = items.find((item) => item.featured) ?? items[0];
  const sidebar = featured ? items.filter((item) => item.id !== featured.id).slice(0, 3) : [];

  const isEmpty = !isLoading && items.length == 0;

  return (
    <section className="bg-[#f8f8f7] container-custom py-12">
      <div className="mx-auto max-w-[82rem]">
        <SectionHeading showViewAll={!isEmpty} />

        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.025fr)]">
          {isLoading ? (
            <>
              <FeaturedSkeleton />

              <div className="flex flex-col gap-8">
                {Array.from({ length: 3 }).map((_, index) => (
                  <AnnouncementSkeleton key={index} />
                ))}
              </div>
            </>
          ) : !featured ? (
            <EmptyState />
          ) : (
            <>
              <FeaturedAnnouncementCard item={featured} />

              <div className="flex flex-col gap-8">
                {sidebar.map((item) => (
                  <AnnouncementListCard key={item.id} item={item} />
                ))}
              </div>
            </>
          )}
        </div>

        <AppLink
          href={ANNOUNCEMENT_ROUTES.ROOT}
          className="type-button mt-8 inline-flex items-center gap-1 text-primary-500 transition-colors duration-200 hover:text-primary-600 md:hidden"
        >
          See All
          <Icon icon="mdi:chevron-right" aria-hidden="true" className="h-5 w-5" />
        </AppLink>
      </div>
    </section>
  );
}
