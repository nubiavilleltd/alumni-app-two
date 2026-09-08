// features/events/pages/MyEventsPage.tsx
// NEW DESIGN: Card grid (3 cols desktop, 2 tablet, 1 mobile).
// Clicking a card navigates to event detail. Unregister still available.
// Past events section below upcoming.

import { Icon } from '@iconify/react';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLink } from '@/shared/components/ui/AppLink';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import { useMyEvents } from '../hooks/useEventRegistration';
import { useCancelRegistration } from '../hooks/useEvents';
import { toast } from '@/shared/components/ui/Toast';
import { EVENT_ROUTES } from '../routes';
import type { Event } from '../types/event.types';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { Calendar, MapPin, SlidersHorizontal } from 'lucide-react';
const MY_EVENTS_PER_PAGE = 6;

type MyEventFilterState = {
  search: string;
  format: string;
  category: string;
  location: string;
  status: string;
};

const EVENT_FORMAT_OPTIONS = [
  { label: 'Virtual events', value: 'virtual' },
  { label: 'In-person events', value: 'in-person' },
];

const EVENT_STATUS_LABELS: Record<string, string> = {
  published: 'Published',
  completed: 'Completed',
  cancelled: 'Cancelled',
  draft: 'Draft',
};

function getEventStatusLabel(status?: string) {
  if (!status) return 'Published';
  return EVENT_STATUS_LABELS[status] ?? status.replace(/_/g, ' ');
}

function matchesMyEventFilters(event: Event, filters: MyEventFilterState) {
  const query = filters.search.trim().toLowerCase();
  const searchableFields = [
    event.title,
    event.description,
    event.location,
    event.category,
    ...(event.tags ?? []),
  ];

  return (
    (!query || searchableFields.some((field) => field.toLowerCase().includes(query))) &&
    (!filters.format || (filters.format === 'virtual' ? event.isVirtual : !event.isVirtual)) &&
    (!filters.category || event.category === filters.category) &&
    (!filters.location || event.location === filters.location) &&
    (!filters.status || (event.status ?? 'published') === filters.status)
  );
}

// ─── Unregister modal ────────────────────────────────────────────────────────

function UnregisterModal({
  event,
  isLoading,
  onConfirm,
  onCancel,
}: {
  event: Event | null;
  isLoading: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  if (!event) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <Icon icon="mdi:alert-circle-outline" className="w-5 h-5 text-red-600" />
          </div>
          <div>
            <h3 className="font-bold text-gray-900 text-lg mb-1">Cancel Registration?</h3>
            <p className="text-gray-600 text-sm">
              Are you sure you want to unregister from{' '}
              <span className="font-semibold">{event.title}</span>?
            </p>
          </div>
        </div>
        <div className="flex gap-3 justify-end mt-6">
          <button
            type="button"
            onClick={onCancel}
            disabled={isLoading}
            className="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 disabled:opacity-50"
          >
            Keep Registration
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={isLoading}
            className="px-6 py-2 text-sm font-semibold bg-red-500 hover:bg-red-600 text-white rounded-xl flex items-center gap-2 disabled:opacity-50 transition-colors"
          >
            {isLoading && <Icon icon="mdi:loading" className="w-4 h-4 animate-spin" />}
            Yes, Unregister
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Event card ───────────────────────────────────────────────────────────────

// function MyEventCard({
//   event,
//   isPast,
//   onUnregisterClick,
// }: {
//   event: Event;
//   isPast: boolean;
//   onUnregisterClick: (e: React.MouseEvent) => void;
// }) {
//   const navigate = useNavigate();
//   const isCancelled = event.status === 'cancelled';

//   const dateDisplay = (() => {
//     const d = new Date(event.startDate);
//     return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
//   })();

//   return (
//     <div
//       onClick={() => navigate(EVENT_ROUTES.DETAIL(event.id))}
//       className="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-all cursor-pointer group"
//     >
//       {/* Image */}
//       <div className="aspect-[16/9] overflow-hidden bg-gray-100 relative">
//         {event.image ? (
//           <img
//             src={event.image}
//             alt={event.title}
//             className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
//             loading="lazy"
//           />
//         ) : (
//           <div className="w-full h-full flex items-center justify-center bg-primary-50">
//             <Icon icon="mdi:calendar-month-outline" className="w-10 h-10 text-primary-200" />
//           </div>
//         )}

//         {/* Badges overlay */}
//         <div className="absolute top-2.5 left-2.5 flex gap-1.5 flex-wrap">
//           {isCancelled && (
//             <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-700/90 text-white">
//               Cancelled
//             </span>
//           )}
//           {isPast && !isCancelled && (
//             <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-600/90 text-white flex items-center gap-1">
//               <Icon icon="mdi:check-circle" className="w-3 h-3" /> Attended
//             </span>
//           )}
//         </div>
//       </div>

//       {/* Content */}
//       <div className="p-4">
//         <h3 className="font-bold text-gray-900 text-sm leading-snug mb-1 line-clamp-2">
//           {event.title}
//         </h3>
//         <p className="text-gray-500 text-xs leading-relaxed line-clamp-2 mb-3">
//           {event.description}
//         </p>

//         {event.location && (
//           <p className="text-gray-400 text-xs flex items-center gap-1 mb-1 truncate">
//             <Icon icon="mdi:map-marker-outline" className="w-3.5 h-3.5 flex-shrink-0" />
//             <span className="truncate">{event.location}</span>
//           </p>
//         )}

//         <p className="text-gray-400 text-xs flex items-center gap-1">
//           <Icon icon="mdi:clock-outline" className="w-3.5 h-3.5 flex-shrink-0" />
//           {dateDisplay}
//         </p>

//         {/* Unregister — upcoming + not cancelled only, stops propagation */}
//         {!isPast && !isCancelled && (
//           <button
//             type="button"
//             onClick={onUnregisterClick}
//             className="mt-3 flex items-center gap-1 text-red-500 hover:text-red-600 text-xs font-medium transition-colors"
//           >
//             <Icon icon="mdi:close-circle-outline" className="w-3.5 h-3.5" />
//             Unregister
//           </button>
//         )}
//       </div>
//     </div>
//   );
// }

// ─── Event card ───────────────────────────────────────────────────────────────

function MyEventCard({
  event,
  isPast,
  onUnregisterClick,
}: {
  event: Event;
  isPast: boolean;
  onUnregisterClick: (e: React.MouseEvent) => void;
}) {
  const navigate = useNavigate();
  const isCancelled = event.status === 'cancelled';

  // Date range: show "startDate - endDate" or just startDate
  // const formatEventDate = (iso: string) =>
  //   new Date(iso).toLocaleDateString('en-US', {
  //     month: 'short',
  //     day: 'numeric',
  //     year: 'numeric',
  //   });

  // const startLabel = event.startDate ? formatEventDate(event.startDate) : null;
  // const endLabel   = event.endDate   ? formatEventDate(event.endDate)   : null;
  // const dateRange  = startLabel && endLabel
  //   ? `${startLabel} - ${endLabel}`
  //   : startLabel ?? null;

  const dateRange = formatDateRange(event.startDate, event.endDate);

  return (
    <div
      onClick={() => navigate(EVENT_ROUTES.DETAIL(event.id))}
      className="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-all cursor-pointer group flex flex-col p-3"
    >
      {/* ── Image ── */}
      <div className="overflow-hidden rounded-3xl bg-gray-100 relative">
        {event.image ? (
          <img
            src={event.image}
            alt={event.title}
            className="w-full aspect-[16/9] object-cover group-hover:scale-105 transition-transform duration-300 rounded-3xl"
            loading="lazy"
          />
        ) : (
          <div className="w-full aspect-[16/9] flex items-center justify-center bg-gray-100 rounded-3xl">
            <Icon icon="mdi:calendar-month-outline" className="w-10 h-10 text-gray-300" />
          </div>
        )}

        {/* Status badges */}
        <div className="absolute top-2.5 left-2.5 flex gap-1.5 flex-wrap">
          {isCancelled && (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-700/90 text-white">
              Cancelled
            </span>
          )}
          {isPast && !isCancelled && (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-600/90 text-white flex items-center gap-1">
              {/* <Icon icon="mdi:check-circle" className="w-3 h-3" /> Attended */}
              Attended
            </span>
          )}
        </div>
      </div>

      {/* ── Content ── */}
      <div className="py-2 flex flex-col flex-1">
        {/* Title */}
        <h3 className="font-bold text-gray-900 text-base leading-snug mb-1.5 line-clamp-2">
          {event.title}
        </h3>

        {/* Description */}
        <p className="text-gray-600 text-sm leading-relaxed line-clamp-2 mb-4">
          {event.description}
        </p>

        {/* Meta — pushed to bottom */}
        <div className="mt-auto flex flex-col gap-1.5">
          {event.location && (
            <p className="text-gray-600 text-sm flex items-center gap-1.5 truncate">
              {/* <Icon icon="mdi:map-marker-outline" className="w-4 h-4 flex-shrink-0 text-gray-400" /> */}
              <MapPin size={15} />
              <span className="truncate">{event.location}</span>
            </p>
          )}

          {dateRange && (
            <p className="text-gray-600 text-sm flex items-center gap-1.5">
              {/* <Icon icon="mdi:clock-outline" className="w-4 h-4 flex-shrink-0 text-gray-400" /> */}
              <Calendar size={15} />
              {dateRange}
            </p>
          )}
        </div>

        {/* Unregister — upcoming + not cancelled only */}
        {!isPast && !isCancelled && (
          <button
            type="button"
            onClick={onUnregisterClick}
            className="mt-4 self-start flex items-center gap-1 text-red-500 hover:text-red-600 text-xs font-medium transition-colors"
          >
            <Icon icon="mdi:close-circle-outline" className="w-3.5 h-3.5" />
            Unregister
          </button>
        )}
      </div>
    </div>
  );
}

// ─── Skeleton card ────────────────────────────────────────────────────────────

function MyEventCardSkeleton() {
  return (
    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 animate-pulse flex flex-col">
      <div className="aspect-[16/9] bg-gray-200 rounded-t-2xl" />
      <div className="p-4 flex flex-col gap-2.5">
        <div className="h-5 bg-gray-200 rounded w-3/4" />
        <div className="h-4 bg-gray-200 rounded w-full" />
        <div className="h-4 bg-gray-200 rounded w-5/6" />
        <div className="mt-2 flex flex-col gap-2">
          <div className="h-3.5 bg-gray-200 rounded w-2/3" />
          <div className="h-3.5 bg-gray-200 rounded w-1/2" />
        </div>
      </div>
    </div>
  );
}

// ─── Skeleton card ────────────────────────────────────────────────────────────

// function MyEventCardSkeleton() {
//   return (
//     <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 animate-pulse">
//       <div className="aspect-[16/9] bg-gray-200" />
//       <div className="p-4 space-y-2">
//         <div className="h-4 bg-gray-200 rounded w-3/4" />
//         <div className="h-3 bg-gray-200 rounded w-full" />
//         <div className="h-3 bg-gray-200 rounded w-1/2" />
//       </div>
//     </div>
//   );
// }

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState({ type }: { type: 'upcoming' | 'past' }) {
  return (
    <div className="col-span-full text-center py-12 bg-white rounded-2xl border border-gray-100">
      <Icon
        icon={type === 'upcoming' ? 'mdi:calendar-blank-outline' : 'mdi:calendar-check-outline'}
        className="w-10 h-10 mx-auto mb-3 text-gray-300"
      />
      <p className="text-gray-500 text-sm mb-3">
        {type === 'upcoming'
          ? "You haven't registered for any upcoming events yet."
          : "You haven't attended any past events."}
      </p>
      {type === 'upcoming' && (
        <AppLink
          href={EVENT_ROUTES.ROOT}
          className="inline-flex items-center gap-1 text-primary-500 hover:text-primary-600 text-sm font-semibold"
        >
          Browse Events <Icon icon="mdi:arrow-right" className="w-4 h-4" />
        </AppLink>
      )}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function MyEventsPage() {
  // TODO: myEvents empty until backend implements POST /get_events { user_id }
  const { events: myEvents = [], isLoading } = useMyEvents();

  const cancelMutation = useCancelRegistration();
  const [unregisterEvent, setUnregisterEvent] = useState<Event | null>(null);
  const [upcomingPage, setUpcomingPage] = useState(1);
  const [pastPage, setPastPage] = useState(1);
  const [search, setSearch] = useState('');
  const [formatFilter, setFormatFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [locationFilter, setLocationFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);

  const filterState = useMemo<MyEventFilterState>(
    () => ({
      search,
      format: formatFilter,
      category: categoryFilter,
      location: locationFilter,
      status: statusFilter,
    }),
    [categoryFilter, formatFilter, locationFilter, search, statusFilter],
  );

  const filteredEvents = useMemo(
    () => myEvents.filter((event: Event) => matchesMyEventFilters(event, filterState)),
    [filterState, myEvents],
  );

  const facetOptions = useMemo(() => {
    const categories = Array.from(
      new Set(myEvents.map((event: Event) => event.category.trim()).filter(Boolean)),
    )
      .sort((a, b) => a.localeCompare(b))
      .map((value) => ({ label: value, value }));
    const locations = Array.from(
      new Set(myEvents.map((event: Event) => event.location.trim()).filter(Boolean)),
    )
      .sort((a, b) => a.localeCompare(b))
      .map((value) => ({ label: value, value }));
    const statuses = Array.from(
      new Set(myEvents.map((event: Event) => event.status ?? 'published')),
    )
      .sort((a, b) => getEventStatusLabel(a).localeCompare(getEventStatusLabel(b)))
      .map((value) => ({ label: getEventStatusLabel(value), value }));

    return { categories, locations, statuses };
  }, [myEvents]);

  const activeAdvancedFilterCount = [
    formatFilter,
    categoryFilter,
    locationFilter,
    statusFilter,
  ].filter(Boolean).length;
  const hasActiveFilters = Boolean(search.trim() || activeAdvancedFilterCount);

  const now = new Date();
  const upcomingEvents = filteredEvents.filter((e: Event) => {
    const [h = 23, m = 59] = (e.endTime || '23:59').split(':').map(Number);
    const d = new Date(e.startDate);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate(), h, m) >= now;
  });
  const pastEvents = filteredEvents.filter((e: Event) => {
    const [h = 23, m = 59] = (e.endTime || '23:59').split(':').map(Number);
    const d = new Date(e.startDate);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate(), h, m) < now;
  });
  const upcomingTotalPages = Math.max(1, Math.ceil(upcomingEvents.length / MY_EVENTS_PER_PAGE));
  const pastTotalPages = Math.max(1, Math.ceil(pastEvents.length / MY_EVENTS_PER_PAGE));
  const visibleUpcomingEvents = upcomingEvents.slice(
    (upcomingPage - 1) * MY_EVENTS_PER_PAGE,
    upcomingPage * MY_EVENTS_PER_PAGE,
  );
  const visiblePastEvents = pastEvents.slice(
    (pastPage - 1) * MY_EVENTS_PER_PAGE,
    pastPage * MY_EVENTS_PER_PAGE,
  );

  useEffect(() => {
    if (upcomingPage > upcomingTotalPages) {
      setUpcomingPage(upcomingTotalPages);
    }
  }, [upcomingPage, upcomingTotalPages]);

  useEffect(() => {
    if (pastPage > pastTotalPages) {
      setPastPage(pastTotalPages);
    }
  }, [pastPage, pastTotalPages]);

  useEffect(() => {
    setUpcomingPage(1);
    setPastPage(1);
  }, [categoryFilter, formatFilter, locationFilter, search, statusFilter]);

  const handleUnregister = async () => {
    if (!unregisterEvent) return;
    try {
      await cancelMutation.mutateAsync(unregisterEvent.id);
      toast.success('You have been unregistered from this event.');
    } catch (err: any) {
      toast.fromError(err);
    } finally {
      setUnregisterEvent(null);
    }
  };

  const clearAllFilters = () => {
    setSearch('');
    setFormatFilter('');
    setCategoryFilter('');
    setLocationFilter('');
    setStatusFilter('');
    setUpcomingPage(1);
    setPastPage(1);
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    if (key === 'format') setFormatFilter(value);
    if (key === 'category') setCategoryFilter(value);
    if (key === 'location') setLocationFilter(value);
    if (key === 'status') setStatusFilter(value);
  };

  const breadcrumbItems = [
    { label: 'Home', href: '/' },
    { label: 'Events', href: EVENT_ROUTES.ROOT },
    { label: 'My Registered Events' },
  ];

  return (
    <>
      <SEO title="My Registered Events" description="View and manage your event registrations" />
      {/* <Breadcrumbs items={breadcrumbItems} /> */}

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-7">
          {/* <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-7">
            My Registered Events
          </h1> */}

          <div className="flex items-center justify-between mb-7">
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">My Registered Events</h1>
            <AppLink
              href={EVENT_ROUTES.ROOT}
              className="rounded-full border-2 border-primary-500 px-4 py-2 text-sm font-bold whitespace-nowrap text-primary-500 transition-colors hover:bg-primary-50"
            >
              Go to Events
            </AppLink>
          </div>

          {!isLoading && myEvents.length > 0 && (
            <>
              <div className="mb-5 flex w-full items-center gap-3">
                <div className="min-w-0 flex-1 sm:max-w-xl">
                  <SearchInput
                    value={search}
                    onValueChange={setSearch}
                    placeholder="Search your registered events"
                    inputClassName="!h-10 !py-0"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
                  aria-expanded={showAdvancedFilters}
                  className="flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:bg-gray-50 sm:px-4 sm:text-sm"
                >
                  <SlidersHorizontal className="h-4 w-4" />
                  <span className="hidden sm:inline">Advanced filters</span>
                  {activeAdvancedFilterCount > 0 && (
                    <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] text-white">
                      {activeAdvancedFilterCount}
                    </span>
                  )}
                </button>
              </div>

              {showAdvancedFilters && (
                <AdvancedFiltersPanel
                  title="Refine your events"
                  description="Find registered events by format, category, location, or status."
                  fields={[
                    {
                      key: 'format',
                      kind: 'select',
                      label: 'Format',
                      value: formatFilter,
                      placeholder: 'All formats',
                      options: EVENT_FORMAT_OPTIONS,
                    },
                    {
                      key: 'category',
                      kind: 'select',
                      label: 'Category',
                      value: categoryFilter,
                      placeholder: 'All categories',
                      options: facetOptions.categories,
                    },
                    {
                      key: 'location',
                      kind: 'select',
                      label: 'Location',
                      value: locationFilter,
                      placeholder: 'All locations',
                      options: facetOptions.locations,
                    },
                    {
                      key: 'status',
                      kind: 'select',
                      label: 'Status',
                      value: statusFilter,
                      placeholder: 'All statuses',
                      options: facetOptions.statuses,
                    },
                  ]}
                  onFieldChange={handleAdvancedFilterChange}
                  onReset={clearAllFilters}
                  hasActiveFilters={activeAdvancedFilterCount > 0}
                />
              )}

              {hasActiveFilters && (
                <div className="mb-6 mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                  <span>
                    Showing {filteredEvents.length} of {myEvents.length} registered{' '}
                    {myEvents.length === 1 ? 'event' : 'events'}
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
            </>
          )}

          {/* Upcoming */}
          {(isLoading || upcomingEvents.length > 0) && (
            <section className="mb-10">
              {!isLoading && upcomingEvents.length > 0 && (
                <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                  {/* <Icon icon="mdi:calendar-clock-outline" className="w-4 h-4 text-primary-500" /> */}
                  Upcoming ({upcomingEvents.length})
                </h2>
              )}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                {isLoading ? (
                  Array.from({ length: 3 }).map((_, i) => <MyEventCardSkeleton key={i} />)
                ) : upcomingEvents.length > 0 ? (
                  visibleUpcomingEvents.map((event: Event) => (
                    <MyEventCard
                      key={event.id}
                      event={event}
                      isPast={false}
                      onUnregisterClick={(e) => {
                        e.stopPropagation();
                        setUnregisterEvent(event);
                      }}
                    />
                  ))
                ) : (
                  <EmptyState type="upcoming" />
                )}
              </div>
              {!isLoading && upcomingEvents.length > 0 ? (
                <Pagination
                  currentPage={upcomingPage}
                  totalPages={upcomingTotalPages}
                  onPageChange={(page) => {
                    setUpcomingPage(page);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                  }}
                />
              ) : null}
            </section>
          )}

          {/* Empty upcoming (when not loading) */}
          {!isLoading && upcomingEvents.length === 0 && (
            <section className="mb-10">
              <div className="grid grid-cols-1">
                <EmptyState type="upcoming" />
              </div>
            </section>
          )}

          {/* Past */}
          {(isLoading || pastEvents.length > 0) && (
            <section>
              <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-2">
                {/* <Icon icon="mdi:calendar-check-outline" className="w-4 h-4 text-gray-400" /> */}
                Past Events ({isLoading ? '…' : pastEvents.length})
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                {isLoading ? (
                  Array.from({ length: 2 }).map((_, i) => <MyEventCardSkeleton key={i} />)
                ) : pastEvents.length > 0 ? (
                  visiblePastEvents.map((event: Event) => (
                    <MyEventCard
                      key={event.id}
                      event={event}
                      isPast={true}
                      onUnregisterClick={(e) => e.stopPropagation()}
                    />
                  ))
                ) : (
                  <EmptyState type="past" />
                )}
              </div>
              {!isLoading && pastEvents.length > 0 ? (
                <Pagination
                  currentPage={pastPage}
                  totalPages={pastTotalPages}
                  onPageChange={(page) => {
                    setPastPage(page);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                  }}
                />
              ) : null}
            </section>
          )}
        </div>
      </div>

      <UnregisterModal
        event={unregisterEvent}
        isLoading={cancelMutation.isPending}
        onConfirm={handleUnregister}
        onCancel={() => setUnregisterEvent(null)}
      />
    </>
  );
}
