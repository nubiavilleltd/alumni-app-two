// features/events/pages/EventsPage.tsx
// NEW DESIGN: Side-by-side calendar + scrollable event list panel.
// Clicking a calendar event highlights and scrolls it into view in the list.
// Handles hundreds of events via incremental "load more" within the scroll panel.

import { useState, useMemo, useRef, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import { RegisterEventModal } from '../components/RegisterEventModal';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import { useUpcomingEvents, usePastEvents } from '../hooks/useEvents';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { EVENT_ROUTES } from '../routes';
import type { Event } from '../types/event.types';
import { MonthYearPicker } from '@/shared/components/ui/MonthYearPicker';
import { Pagination } from '@/shared/components/ui/Pagination';
import { ROUTES } from '@/shared/constants/routes';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { formatDateRange, parseDateInput } from '@/shared/utils/dateHelpers';
import { stripEventAnnouncementMarker } from '../lib/eventAnnouncementVisibility';
import {
  ArrowLeft,
  ArrowRight,
  Calendar,
  CalendarDays,
  ChevronRight,
  Clock,
  MapPin,
  Plus,
  SlidersHorizontal,
} from 'lucide-react';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDateKey(date: Date) {
  return (
    date.getFullYear() +
    '-' +
    String(date.getMonth() + 1).padStart(2, '0') +
    '-' +
    String(date.getDate()).padStart(2, '0')
  );
}

function formatEventDate(event: Event) {
  return formatDateRange(event.startDate, event.endDate);
}

function isUpcomingEvent(event: Event) {
  const date = parseDateInput(event.endDate || event.startDate);
  if (!date) return false;

  const [hours, minutes] = (event.endTime || '23:59').split(':').map(Number);
  const endDateTime = new Date(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
    Number.isFinite(hours) ? hours : 23,
    Number.isFinite(minutes) ? minutes : 59,
  );

  return endDateTime >= new Date();
}

// Stable pastel color per event, deterministic from id
const EVENT_COLORS = ['#7c6af7', '#e8b84b', '#6ac8f7', '#f76a9f', '#52c97a', '#f79a6a'];
function eventColor(id: string) {
  let hash = 0;
  for (let i = 0; i < id.length; i++) hash = id.charCodeAt(i) + ((hash << 5) - hash);
  return EVENT_COLORS[Math.abs(hash) % EVENT_COLORS.length];
}

const MONTH_NAMES = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];
const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const LIST_PAGE = 20;
const EVENTS_ICON_STROKE = 2.5;

// ─── Calendar event block ─────────────────────────────────────────────────────

function formatShort(dateStr: string | undefined): string {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
  });
}

function CalendarBlock({
  event,
  isActive,
  onClick,
}: {
  event: Event;
  isActive: boolean;
  onClick: () => void;
}) {
  const color = eventColor(event.id);

  return (
    <button
      type="button"
      onClick={onClick}
      title={event.title}
      className={`
        w-full text-left rounded-md
        px-1.5 py-1
        sm:rounded-xl sm:p-2.5
        transition-all duration-200
        hover:brightness-95
      `}
      style={{
        backgroundColor: color + '30',
        border: `1px solid ${color}20`,
        ...(isActive && { outline: `2px solid ${color}` }),
      }}
    >
      {/* Hidden on mobile, visible on desktop */}
      <div className="hidden sm:inline-flex items-center gap-1 px-2 py-[3px] rounded-md text-[10px] font-medium text-white bg-gray-800 mb-1.5">
        <Calendar size={12} strokeWidth={EVENTS_ICON_STROKE} />
        {formatShort(event.startDate)}
        {event.endDate ? ` - ${formatShort(event.endDate)}` : ''}
      </div>

      {/* Compact title */}
      <p className="text-[10px] sm:text-xs font-semibold text-gray-800 leading-tight truncate">
        {event.title}
      </p>
    </button>
  );
}

function CalendarComponent({
  events,
  currentDate,
  activeEventId,
  onDateChange,
  onEventClick,
}: {
  events: Event[];
  currentDate: Date;
  activeEventId: string | null;
  onDateChange: (d: Date) => void;
  onEventClick: (e: Event) => void;
}) {
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();

  const isMobile = typeof window !== 'undefined' && window.innerWidth < 640;

  const eventsByDate = useMemo(() => {
    const map = new Map<string, Event[]>();
    events.forEach((ev) => {
      const parsedDate = parseDateInput(ev.startDate);
      if (!parsedDate) return;

      const key = formatDateKey(parsedDate);
      map.set(key, [...(map.get(key) || []), ev]);
    });
    return map;
  }, [events]);

  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const rawFirst = new Date(year, month, 1).getDay();
  const firstDayOfWeek = (rawFirst + 6) % 7; // Mon = 0
  const prevMonthDays = new Date(year, month, 0).getDate();
  const today = formatDateKey(new Date());

  const days: { day: number; isCurrentMonth: boolean; date: Date }[] = [];
  for (let i = firstDayOfWeek - 1; i >= 0; i--)
    days.push({
      day: prevMonthDays - i,
      isCurrentMonth: false,
      date: new Date(year, month - 1, prevMonthDays - i),
    });
  for (let d = 1; d <= daysInMonth; d++)
    days.push({ day: d, isCurrentMonth: true, date: new Date(year, month, d) });
  for (let d = 1; days.length < 42; d++)
    days.push({ day: d, isCurrentMonth: false, date: new Date(year, month + 1, d) });

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-5">
      <div className="grid grid-cols-7 mb-2 gap-2">
        {DAY_NAMES.map((d) => (
          <div
            key={d}
            className="text-center text-[10px] sm:text-xs font-semibold text-gray-900 py-1 ring-1 ring-gray-100 rounded-lg"
          >
            {d}
          </div>
        ))}
      </div>
      <div className="grid grid-cols-7 gap-1 sm:gap-2">
        {days.map((calDay, idx) => {
          const key = formatDateKey(calDay.date);
          const dayEvents = eventsByDate.get(key) || [];
          const isToday = key === today;
          return (
            <div
              key={idx}
              className={`min-h-[60px] sm:min-h-[80px] p-1 rounded-lg flex flex-col items-start gap-1 ${calDay.isCurrentMonth ? 'bg-white' : 'bg-gray-50/60'} ${isToday ? 'ring-1 ring-primary-400' : 'ring-1 ring-gray-100'}`}
            >
              <span
                className={`text-[10px] sm:text-xs font-semibold ${
                  calDay.isCurrentMonth ? 'text-gray-700' : 'text-gray-300'
                } ${isToday ? 'text-primary-600' : ''}`}
              >
                {calDay.day}
              </span>

              {dayEvents.slice(0, isMobile ? 1 : 2).map((ev) => (
                <CalendarBlock
                  key={ev.id}
                  event={ev}
                  isActive={activeEventId === ev.id}
                  onClick={() => onEventClick(ev)}
                />
              ))}

              {dayEvents.length > (isMobile ? 1 : 2) && (
                <button
                  type="button"
                  onClick={() => onEventClick(dayEvents[0])}
                  className="text-[9px] text-primary-500 font-medium"
                >
                  +{dayEvents.length - (isMobile ? 1 : 2)}
                </button>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ─── Event list item ──────────────────────────────────────────────────────────

function EventListItem({
  event,
  isActive,
  onClick,
  itemRef,
}: {
  event: Event;
  isActive: boolean;
  onClick: () => void;
  itemRef: (el: HTMLDivElement | null) => void;
}) {
  const color = eventColor(event.id);
  const cleanDescription = stripEventAnnouncementMarker(event.description ?? '');
  return (
    <div
      ref={itemRef}
      onClick={onClick}
      className={`bg-white flex items-center gap-3 p-2 rounded-xl cursor-pointer transition-all border scroll-mt-2 ${
        isActive
          ? 'border-primary-300 bg-blue-50/60 shadow-sm'
          : 'border-transparent hover:bg-gray-50'
      }`}
    >
      {/* Thumbnail */}
      <div className="w-14 h-14 sm:w-[72px] sm:h-[72px] rounded-xl overflow-hidden flex-shrink-0 bg-gray-100">
        {event.image ? (
          <img
            src={event.image}
            alt={event.title}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <CalendarDays size={24} strokeWidth={EVENTS_ICON_STROKE} className="text-gray-300" />
          </div>
        )}
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-1">
          <h3 className="font-bold text-gray-900 text-sm leading-snug line-clamp-2 flex-1">
            {event.title}
          </h3>
          <div
            className="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-0.5"
            style={{ backgroundColor: color }}
          />
        </div>
        <p className="text-gray-500 text-xs mt-0.5 line-clamp-2 leading-relaxed">
          {cleanDescription}
        </p>
        {event.location && (
          <p className="text-gray-600 text-[11px] mt-1 flex items-center gap-1 truncate">
            <MapPin size={13} strokeWidth={EVENTS_ICON_STROKE} />
            <span className="truncate">{event.location}</span>
          </p>
        )}
        {formatEventDate(event) && (
          <p className="text-gray-600 text-[11px] mt-0.5 flex items-center gap-1">
            <Clock size={12} strokeWidth={EVENTS_ICON_STROKE} />
            {formatEventDate(event)}
          </p>
        )}
      </div>

      <ChevronRight
        size={16}
        strokeWidth={EVENTS_ICON_STROKE}
        className="text-primary-500 flex-shrink-0"
      />
    </div>
  );
}

function EventListSkeleton() {
  return (
    <div className="flex items-center gap-3 p-3 animate-pulse">
      <div className="w-[72px] h-[72px] rounded-xl bg-gray-200 flex-shrink-0" />
      <div className="flex-1 space-y-2">
        <div className="h-4 bg-gray-200 rounded w-3/4" />
        <div className="h-3 bg-gray-200 rounded w-full" />
        <div className="h-3 bg-gray-200 rounded w-1/2" />
      </div>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function EventsPage() {
  const navigate = useNavigate();
  const currentUser = useIdentityStore((state) => state.user);

  const [calendarDate, setCalendarDate] = useState(new Date());
  const [activeEventId, setActiveEventId] = useState<string | null>(null);
  const [registerEvent, setRegisterEvent] = useState<Event | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [locationFilter, setLocationFilter] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [formatFilter, setFormatFilter] = useState('');
  const [timingFilter, setTimingFilter] = useState('');
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);

  const { data: upcoming = [], isLoading: upcomingLoading } = useUpcomingEvents();
  const { data: past = [], isLoading: pastLoading } = usePastEvents();
  const isLoading = upcomingLoading || pastLoading;

  // All events sorted by date ascending
  const allEvents = useMemo(
    () =>
      [...upcoming, ...past].sort(
        (a, b) =>
          (parseDateInput(a.startDate)?.getTime() ?? Number.POSITIVE_INFINITY) -
          (parseDateInput(b.startDate)?.getTime() ?? Number.POSITIVE_INFINITY),
      ),
    [upcoming, past],
  );

  // Right panel filtered events
  const filteredEvents = useMemo(() => {
    const q = searchTerm.trim().toLowerCase();
    return allEvents.filter((e) => {
      const eventDate = parseDateInput(e.startDate);
      const eventYear = eventDate?.getFullYear().toString() ?? '';
      const isUpcoming = isUpcomingEvent(e);
      const normalizedLocation = e.location?.trim().toLowerCase() ?? '';
      const normalizedCategory = e.category?.trim().toLowerCase() ?? '';
      const matchesSearch =
        !q ||
        [e.title, e.description, e.location, e.category, ...(e.tags ?? [])]
          .join(' ')
          .toLowerCase()
          .includes(q);

      return (
        matchesSearch &&
        (!categoryFilter || normalizedCategory === categoryFilter.toLowerCase()) &&
        (!locationFilter || normalizedLocation === locationFilter.toLowerCase()) &&
        (!yearFilter || eventYear === yearFilter) &&
        (!formatFilter || (formatFilter === 'virtual' ? e.isVirtual : !e.isVirtual)) &&
        (!timingFilter || (timingFilter === 'upcoming' ? isUpcoming : !isUpcoming))
      );
    });
  }, [
    allEvents,
    categoryFilter,
    formatFilter,
    locationFilter,
    searchTerm,
    timingFilter,
    yearFilter,
  ]);

  // Events for the currently shown calendar month, respecting active filters.
  const calendarMonthEvents = useMemo(
    () =>
      filteredEvents.filter((e) => {
        const d = parseDateInput(e.startDate);
        if (!d) return false;

        return (
          d.getFullYear() === calendarDate.getFullYear() && d.getMonth() === calendarDate.getMonth()
        );
      }),
    [filteredEvents, calendarDate],
  );

  const categoryOptions = useMemo(
    () =>
      Array.from(new Set(allEvents.map((event) => event.category?.trim()).filter(Boolean)))
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({ label: value, value })),
    [allEvents],
  );
  const locationOptions = useMemo(
    () =>
      Array.from(new Set(allEvents.map((event) => event.location?.trim()).filter(Boolean)))
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({ label: value, value })),
    [allEvents],
  );
  const yearOptions = useMemo(
    () =>
      Array.from(
        new Set(
          allEvents
            .map((event) => parseDateInput(event.startDate)?.getFullYear().toString())
            .filter((value): value is string => Boolean(value)),
        ),
      )
        .sort((a, b) => Number(b) - Number(a))
        .map((value) => ({ label: value, value })),
    [allEvents],
  );
  const activeAdvancedFilterCount = [
    categoryFilter,
    locationFilter,
    yearFilter,
    formatFilter,
    timingFilter,
  ].filter(Boolean).length;
  const hasActiveFilters = Boolean(searchTerm.trim() || activeAdvancedFilterCount);

  const totalPages = Math.max(1, Math.ceil(filteredEvents.length / LIST_PAGE));
  const visibleEvents = filteredEvents.slice(
    (currentPage - 1) * LIST_PAGE,
    currentPage * LIST_PAGE,
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  // Ref map for scrolling individual list items
  const itemRefs = useRef<Map<string, HTMLDivElement>>(new Map());

  const handleCalendarClick = useCallback(
    (event: Event) => {
      setActiveEventId(event.id);

      // Make sure it's in the visible portion, then scroll
      const idx = filteredEvents.findIndex((e) => e.id === event.id);
      if (idx >= 0) {
        setCurrentPage(Math.floor(idx / LIST_PAGE) + 1);
      }

      // requestAnimationFrame(() => {
      //   const el = itemRefs.current.get(event.id);
      //   el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      // });

      requestAnimationFrame(() => {
        const el = itemRefs.current.get(event.id);
        if (el) {
          const container = el.closest('.overflow-y-auto') as HTMLElement | null;
          if (container) {
            container.scrollTo({
              top: el.offsetTop - container.offsetTop,
              behavior: 'smooth',
            });
          }
        }
      });
    },
    [filteredEvents],
  );

  const handleListClick = (event: Event) => {
    setActiveEventId(event.id);
    navigate(EVENT_ROUTES.DETAIL(event.id));
  };

  const handleDateChange = (d: Date) => {
    setCalendarDate(d);
    setActiveEventId(null);
  };

  const handleFilterChange = (setter: (value: string) => void) => (value: string) => {
    setter(value);
    setCurrentPage(1);
    setActiveEventId(null);
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    const setters: Record<string, (nextValue: string) => void> = {
      category: setCategoryFilter,
      location: setLocationFilter,
      year: setYearFilter,
      format: setFormatFilter,
      timing: setTimingFilter,
    };
    setters[key]?.(value);
    setCurrentPage(1);
    setActiveEventId(null);
  };

  const clearAllFilters = () => {
    setSearchTerm('');
    setCategoryFilter('');
    setLocationFilter('');
    setYearFilter('');
    setFormatFilter('');
    setTimingFilter('');
    setCurrentPage(1);
    setActiveEventId(null);
  };

  return (
    <>
      <SEO title="Events" description="Alumni events — connect, celebrate and give back." />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-5 sm:py-7">
          <div className="mb-12 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <h1 className="type-section-title">Events</h1>

            <div className="flex w-full flex-wrap items-center justify-center gap-3 sm:w-auto sm:flex-shrink-0 sm:justify-end">
              <Link
                to={ROUTES.PROJECTS.ROOT}
                className="min-w-0 flex-1 rounded-full border-2 border-primary-500 px-3 py-2.5 text-center text-xs font-bold whitespace-nowrap text-primary-500 transition-colors hover:bg-primary-500 hover:text-white sm:flex-none sm:px-5 sm:text-sm"
              >
                Go to Our Projects
              </Link>
              <Link
                to={ROUTES.NEWS}
                className="min-w-0 flex-1 rounded-full border-2 border-primary-500 px-3 py-2.5 text-center text-xs font-bold whitespace-nowrap text-primary-500 transition-colors hover:bg-primary-500 hover:text-white sm:flex-none sm:px-5 sm:text-sm"
              >
                Go to Announcement
              </Link>
            </div>
          </div>

          {/* ── Top bar ──────────────────────────────────────────────── */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            {/* Month nav */}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() =>
                  handleDateChange(
                    new Date(calendarDate.getFullYear(), calendarDate.getMonth() - 1, 1),
                  )
                }
                className="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 transition-colors"
              >
                <ArrowLeft
                  size={20}
                  strokeWidth={EVENTS_ICON_STROKE}
                  className="text-primary-500"
                />
              </button>
              <button
                type="button"
                onClick={() =>
                  handleDateChange(
                    new Date(calendarDate.getFullYear(), calendarDate.getMonth() + 1, 1),
                  )
                }
                className="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 transition-colors"
              >
                <ArrowRight
                  size={20}
                  strokeWidth={EVENTS_ICON_STROKE}
                  className="text-primary-500"
                />
              </button>

              <MonthYearPicker value={calendarDate} onChange={handleDateChange} />
            </div>

            {/* Search + create */}
            <div className="flex items-center gap-2">
              <SearchInput
                className="flex-1 sm:w-60"
                value={searchTerm}
                onValueChange={(v) => {
                  setSearchTerm(v);
                  setCurrentPage(1);
                  setActiveEventId(null);
                }}
                placeholder="Search events"
              />
              <button
                type="button"
                onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
                aria-expanded={showAdvancedFilters}
                className="flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:bg-gray-50"
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
          </div>

          {showAdvancedFilters && (
            <AdvancedFiltersPanel
              title="Refine event results"
              description="Narrow events by timing, format, category, location, or year."
              gridClassName="grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
              fields={[
                {
                  key: 'timing',
                  kind: 'select',
                  label: 'Timing',
                  value: timingFilter,
                  placeholder: 'All events',
                  options: [
                    { label: 'Upcoming events', value: 'upcoming' },
                    { label: 'Past events', value: 'past' },
                  ],
                },
                {
                  key: 'format',
                  kind: 'select',
                  label: 'Format',
                  value: formatFilter,
                  placeholder: 'All formats',
                  options: [
                    { label: 'Virtual', value: 'virtual' },
                    { label: 'In person', value: 'in_person' },
                  ],
                },
                {
                  key: 'category',
                  kind: 'select',
                  label: 'Category',
                  value: categoryFilter,
                  placeholder: 'All categories',
                  options: categoryOptions,
                },
                {
                  key: 'location',
                  kind: 'select',
                  label: 'Location',
                  value: locationFilter,
                  placeholder: 'All locations',
                  options: locationOptions,
                },
                {
                  key: 'year',
                  kind: 'select',
                  label: 'Event year',
                  value: yearFilter,
                  placeholder: 'Any year',
                  options: yearOptions,
                },
              ]}
              onFieldChange={handleAdvancedFilterChange}
              onReset={clearAllFilters}
              hasActiveFilters={activeAdvancedFilterCount > 0}
            />
          )}

          {hasActiveFilters && (
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
              <span>
                Showing {filteredEvents.length} {filteredEvents.length === 1 ? 'event' : 'events'}{' '}
                matching your filters
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

          {/* ── Two-column layout ─────────────────────────────────────── */}
          <div className="grid grid-cols-1 lg:grid-cols-[1fr_380px] xl:grid-cols-[1fr_430px] gap-4 lg:items-start">
            {/* Calendar */}
            {isLoading ? (
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 animate-pulse h-[480px]" />
            ) : (
              <CalendarComponent
                events={calendarMonthEvents}
                currentDate={calendarDate}
                activeEventId={activeEventId}
                onDateChange={handleDateChange}
                onEventClick={handleCalendarClick}
              />
            )}

            {/* Scrollable event list */}
            <div
              className="rounded-2xl shadow-sm border border-gray-100 flex flex-col"
              style={{ maxHeight: 'calc(100vh - 130px)' }}
            >
              <div className="flex-1 overflow-y-auto p-2 space-y-0.5">
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, i) => <EventListSkeleton key={i} />)
                ) : visibleEvents.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                    <CalendarDays
                      size={40}
                      strokeWidth={EVENTS_ICON_STROKE}
                      className="mb-3 opacity-40"
                    />
                    <p className="text-sm">No events found</p>
                  </div>
                ) : (
                  <>
                    <div className="flex flex-col gap-3">
                      {' '}
                      {visibleEvents.map((event) => (
                        <EventListItem
                          key={event.id}
                          event={event}
                          isActive={activeEventId === event.id}
                          onClick={() => handleListClick(event)}
                          itemRef={(el) => {
                            if (el) itemRefs.current.set(event.id, el);
                            else itemRefs.current.delete(event.id);
                          }}
                        />
                      ))}
                    </div>
                  </>
                )}
              </div>

              {/* Footer count */}
              {!isLoading && filteredEvents.length > 0 && (
                <div className="border-t border-gray-50 px-4 py-3">
                  <div className="text-center text-[11px] text-gray-400">
                    Showing {(currentPage - 1) * LIST_PAGE + 1}-
                    {Math.min(currentPage * LIST_PAGE, filteredEvents.length)} of{' '}
                    {filteredEvents.length} events
                  </div>
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={(page) => setCurrentPage(page)}
                  />
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <RegisterEventModal event={registerEvent} onClose={() => setRegisterEvent(null)} />
    </>
  );
}
