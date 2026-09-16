import { Icon } from '@iconify/react';
import { useEffect, useMemo, useState } from 'react';
import { SEO } from '@/shared/common/SEO';
import { AppLink } from '@/shared/components/ui/AppLink';
import Button from '@/shared/components/ui/Button';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { formatDateRange, parseDateInput } from '@/shared/utils/dateHelpers';
import { AdminBanner } from '@/features/admin/components/AdminBanner';
import { useAllEvents } from '../hooks/useEvents';
import { EVENT_ROUTES } from '../routes';
import type { Event } from '../types/event.types';
import { MapPin } from 'lucide-react';
import { ADMIN_ROUTES } from '@/features/admin/routes';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';

const monthOptions = [
  { label: 'Month', value: 'all' },
  { label: 'January', value: '0' },
  { label: 'February', value: '1' },
  { label: 'March', value: '2' },
  { label: 'April', value: '3' },
  { label: 'May', value: '4' },
  { label: 'June', value: '5' },
  { label: 'July', value: '6' },
  { label: 'August', value: '7' },
  { label: 'September', value: '8' },
  { label: 'October', value: '9' },
  { label: 'November', value: '10' },
  { label: 'December', value: '11' },
] as const;
const ADMIN_EVENTS_PER_PAGE = 6;

function formatEventSummary(event: Event) {
  return event.description?.trim() || event.content?.trim() || 'No event summary provided yet.';
}

function formatEventSchedule(event: Event) {
  return formatDateRange(event.startDate, event.endDate);
}

function sortEventsForAdmin(events: Event[]) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return [...events].sort((left, right) => {
    const leftDate = parseDateInput(left.startDate);
    const rightDate = parseDateInput(right.startDate);

    if (!leftDate && !rightDate) return left.title.localeCompare(right.title);
    if (!leftDate) return 1;
    if (!rightDate) return -1;

    const leftUpcoming = leftDate >= today;
    const rightUpcoming = rightDate >= today;

    if (leftUpcoming && !rightUpcoming) return -1;
    if (!leftUpcoming && rightUpcoming) return 1;

    if (leftUpcoming && rightUpcoming) return leftDate.getTime() - rightDate.getTime();

    return rightDate.getTime() - leftDate.getTime();
  });
}

function AdminEventsCard({ event }: { event: Event }) {
  return (
    <AppLink
      href={ADMIN_ROUTES.EVENT_DETAIL(event.id)}
      className="group block h-full rounded-[1.75rem] bg-white p-4 text-inherit no-underline shadow-[0_10px_28px_rgba(15,23,42,0.06)] ring-1 ring-black/5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_16px_36px_rgba(15,23,42,0.1)] sm:p-3.5"
    >
      <article className="flex h-full flex-col gap-3 sm:flex-row sm:items-center sm:gap-3.5">
        <div className="h-28 overflow-hidden rounded-[1rem] bg-accent-50 sm:h-[7rem] sm:w-[7rem] sm:flex-shrink-0">
          {event.image ? (
            <img
              src={event.image}
              alt={event.title}
              className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-primary-50 text-primary-200">
              <Icon icon="mdi:calendar-month-outline" className="h-12 w-12" />
            </div>
          )}
        </div>

        <div className="flex min-w-0 flex-1 flex-col items-start justify-start md:justify-center md:self-center">
          <h2 className="max-w-3xl text-[1rem] font-bold leading-tight text-accent-950 transition-colors duration-200 group-hover:text-primary-700 sm:text-[1.05rem]">
            {event.title}
          </h2>

          <p className="mt-1.5 line-clamp-2 text-[0.9rem] leading-relaxed text-accent-500 sm:text-[0.93rem]">
            {formatEventSummary(event)}
          </p>

          <div className="mt-2.5 space-y-1.5 text-sm text-accent-500">
            <div className="flex items-center justify-start gap-2">
              {/* <Icon
                icon="mdi:map-marker-outline"
                className="mt-0.5 h-4 w-4 flex-shrink-0 text-accent-400"
              /> */}

              <MapPin size={15} />
              <span>{event.location?.trim() || 'Location to be announced'}</span>
            </div>

            {formatEventSchedule(event) && (
              <div className="flex items-center justify-center gap-2">
                <Icon
                  icon="mdi:clock-outline"
                  className="mt-0.5 h-4 w-4 flex-shrink-0 text-accent-500"
                />
                <span>{formatEventSchedule(event)}</span>
              </div>
            )}
          </div>
        </div>
      </article>
    </AppLink>
  );
}

function AdminEventsSkeleton() {
  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:max-w-[52rem]">
          <div className="h-[42px] flex-1 rounded-full bg-white/90 shadow-[0_10px_28px_rgba(15,23,42,0.06)]" />
          <div className="h-[42px] w-full rounded-full bg-white/90 shadow-[0_10px_28px_rgba(15,23,42,0.06)] sm:w-40" />
        </div>
        <div className="h-14 w-full rounded-full bg-white/90 shadow-[0_10px_28px_rgba(15,23,42,0.06)] sm:w-52" />
      </div>

      <div className="grid gap-5 xl:grid-cols-2">
        {Array.from({ length: 4 }).map((_, index) => (
          <div
            key={index}
            className="rounded-[1.5rem] bg-white p-3.5 shadow-[0_10px_28px_rgba(15,23,42,0.06)] ring-1 ring-black/5"
          >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="h-28 rounded-[1rem] bg-accent-100 sm:h-[7rem] sm:w-[7rem]" />
              <div className="flex-1 space-y-3">
                <div className="h-7 w-3/4 rounded bg-accent-100" />
                <div className="space-y-2">
                  <div className="h-4 w-full rounded bg-accent-50" />
                  <div className="h-4 w-5/6 rounded bg-accent-50" />
                </div>
                <div className="space-y-2">
                  <div className="h-4 w-4/5 rounded bg-accent-50" />
                  <div className="h-4 w-3/5 rounded bg-accent-50" />
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export default function AdminEventsPage() {
  const { filters, setFilter } = usePersistedFilters('admin-events-filters', {
    searchTerm: '',
    selectedMonth: 'all',
  });
  const { searchTerm, selectedMonth } = filters;
  const [currentPage, setCurrentPage] = useUrlPagination();

  const { data: events = [], isLoading, isError, refetch } = useAllEvents();

  const filteredEvents = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();

    return sortEventsForAdmin(events).filter((event) => {
      const eventDate = parseDateInput(event.startDate);
      const matchesMonth =
        selectedMonth === 'all' ||
        (eventDate ? eventDate.getMonth() === Number(selectedMonth) : false);

      if (!matchesMonth) return false;

      if (!normalizedSearch) return true;

      const haystack = [event.title, event.description, event.content, event.location]
        .join(' ')
        .toLowerCase();

      return haystack.includes(normalizedSearch);
    });
  }, [events, searchTerm, selectedMonth]);

  const totalPages = Math.max(1, Math.ceil(filteredEvents.length / ADMIN_EVENTS_PER_PAGE));
  const visibleEvents = filteredEvents.slice(
    (currentPage - 1) * ADMIN_EVENTS_PER_PAGE,
    currentPage * ADMIN_EVENTS_PER_PAGE,
  );

  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const changePage = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };


  return (
    <>
      <SEO title="Admin Events" description="Manage events from the admin dashboard." />
      <AdminBanner activeTab="events" title="Events" />

      <main className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-8 sm:py-10">
          <h1 className="sr-only">Admin Events</h1>

          {isLoading ? (
            <AdminEventsSkeleton />
          ) : isError ? (
            <div className="rounded-[1.5rem] bg-white p-8 text-center shadow-[0_10px_28px_rgba(15,23,42,0.06)] ring-1 ring-black/5">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-500">
                <Icon icon="mdi:alert-circle-outline" className="h-7 w-7" />
              </div>
              <h2 className="mt-4 text-xl font-bold text-accent-950">Could not load events</h2>
              <p className="mt-2 text-sm text-accent-500">
                Try again to reload the events management view.
              </p>
              <Button className="mt-6" onClick={() => void refetch()}>
                Try again
              </Button>
            </div>
          ) : (
            <>
              <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:max-w-[52rem]">
                  <div className="min-w-0 flex-1">
                    <label htmlFor="admin-events-search" className="sr-only">
                      Search events
                    </label>
                    <SearchInput
                      id="admin-events-search"
                      value={searchTerm}
                      onValueChange={(value) => setFilter('searchTerm', value)}
                      placeholder="Search events"
                      className="w-full"
                      inputClassName="!h-[56px] !border-0 !pl-11 !pr-4 text-[0.95rem] font-medium text-accent-600 placeholder:text-accent-400 !shadow-none focus:!ring-0"
                      iconClassName="!h-[18px] !w-[18px] !text-accent-400"
                    />
                  </div>

                  <div className="sm:w-40 sm:flex-shrink-0">
                    <span className="sr-only">Filter events by month</span>
                    <SelectInput
                      value={selectedMonth}
                      onChange={(event) => setFilter('selectedMonth', event.target.value)}
                      options={monthOptions}
                      sortOptionsAlphabetically={false}
                      className="w-full gap-0"
                      controlClassName="h-[56px] rounded-full border-0 bg-white px-4 py-0 pr-10 text-[0.95rem] font-semibold text-accent-600 shadow-none focus:ring-0 [&>span]:text-[0.95rem] [&>span]:font-semibold [&>span.text-gray-400]:text-accent-400 [&>span.text-gray-700]:text-accent-600"
                    />
                  </div>
                </div>

                <AppLink
                  href={EVENT_ROUTES.CREATE}
                  className="inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-4 py-2 text-base font-semibold text-white no-underline shadow-[0_10px_26px_rgba(37,99,235,0.18)] transition hover:bg-primary-600"
                >
                  Create Event
                  <Icon icon="mdi:plus" className="h-4 w-4" />
                </AppLink>
              </div>

              {filteredEvents.length > 0 ? (
                <>
                  <div className="mt-6 grid gap-5 xl:grid-cols-2">
                    {visibleEvents.map((event) => (
                      <AdminEventsCard key={event.id} event={event} />
                    ))}
                  </div>
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={changePage}
                  />
                </>
              ) : (
                <div className="mt-6 rounded-[1.5rem] bg-white p-8 text-center shadow-[0_10px_28px_rgba(15,23,42,0.06)] ring-1 ring-black/5">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-50 text-accent-500">
                    <Icon icon="mdi:calendar-search-outline" className="h-7 w-7" />
                  </div>
                  <h2 className="mt-4 text-xl font-bold text-accent-950">No events found</h2>
                  <p className="mt-2 text-sm text-accent-500">
                    Adjust your search or month filter to find a matching event.
                  </p>
                </div>
              )}
            </>
          )}
        </div>
      </main>
    </>
  );
}
