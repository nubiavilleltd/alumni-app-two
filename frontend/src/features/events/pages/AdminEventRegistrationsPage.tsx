/**
 * ============================================================================
 * ADMIN EVENT REGISTRATIONS PAGE
 * ============================================================================
 *
 * Route: /admin/event-registrations
 *
 * Features:
 * - View all events
 * - Click event to see attendees
 * - Filter attendees by status (All, Going, Maybe, Not Going)
 * - Export attendee list (future)
 * - See registration stats
 *
 * ============================================================================
 */

import { Icon } from '@iconify/react';
import { useEffect, useMemo } from 'react';
import { useAllEvents } from '@/features/events/hooks/useEvents';
import { useEventAttendees } from '@/features/events/hooks/useEventAttendees';
import type { Event } from '@/features/events/types/event.types';
import type { AttendeeStatus } from '@/features/events/api/adapters/event-attendees.adapter';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { Pagination } from '@/shared/components/ui/Pagination';
import { AdminBanner } from '@/features/admin/components/AdminBanner';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';

const ADMIN_REGISTRATION_EVENTS_PER_PAGE = 6;
const ADMIN_REGISTRATION_ATTENDEES_PER_PAGE = 10;

// ═══════════════════════════════════════════════════════════════════════════
// EVENT LIST ITEM
// ═══════════════════════════════════════════════════════════════════════════

function EventListItem({
  event,
  isSelected,
  onClick,
}: {
  event: Event;
  isSelected: boolean;
  onClick: () => void;
}) {
  const isPast = new Date(event.startDate) < new Date();
  const eventDate = formatDateRange(event.startDate, event.endDate, {
    locale: 'en-GB',
    formatOptions: {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    },
  });

  return (
    <button
      onClick={onClick}
      className={`w-full text-left rounded-xl border p-4 transition-all ${
        isSelected
          ? 'border-primary-500 bg-primary-50 shadow-md'
          : 'border-gray-200 bg-white hover:border-primary-300 hover:bg-primary-50/50'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <p className={`font-semibold ${isSelected ? 'text-primary-900' : 'text-gray-900'}`}>
            {event.title}
          </p>
          <div className="mt-1 flex items-center gap-3 text-xs text-gray-500">
            {eventDate && (
              <span className="flex items-center gap-1">
                <Icon icon="mdi:calendar-outline" className="w-3.5 h-3.5" />
                {eventDate}
              </span>
            )}
            {event.location && (
              <span className="flex items-center gap-1">
                <Icon icon="mdi:map-marker-outline" className="w-3.5 h-3.5" />
                {event.location}
              </span>
            )}
            {isPast && (
              <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold">
                PAST
              </span>
            )}
          </div>
        </div>

        {event.attendeeCount !== undefined && (
          <div className="flex-shrink-0 text-right">
            <p className="text-sm font-bold text-primary-700">{event.attendeeCount}</p>
            <p className="text-[10px] text-gray-500">Registered</p>
          </div>
        )}
      </div>
    </button>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// ATTENDEE ROW
// ═══════════════════════════════════════════════════════════════════════════

function AttendeeRow({ attendee }: { attendee: any }) {
  const statusConfig = {
    going: { label: 'Going', color: 'text-green-700 bg-green-100' },
    maybe: { label: 'Maybe', color: 'text-amber-700 bg-amber-100' },
    not_going: { label: 'Not Going', color: 'text-red-700 bg-red-100' },
    '': { label: 'Unknown', color: 'text-gray-700 bg-gray-100' },
  };

  const config = statusConfig[attendee.status as AttendeeStatus] || statusConfig[''];

  // Get initials
  const initials = attendee.fullName
    .split(' ')
    .slice(0, 2)
    .map((n: string) => n[0])
    .join('')
    .toUpperCase();

  return (
    <div className="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-white px-4 py-3 hover:border-primary-200 hover:shadow-sm transition-all">
      <div className="flex items-center gap-3 min-w-0 flex-1">
        {/* Avatar */}
        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary-100 to-accent-100 flex items-center justify-center flex-shrink-0">
          <span className="text-sm font-bold text-primary-700">{initials}</span>
        </div>

        {/* Details */}
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-gray-900 truncate">{attendee.fullName}</p>
          <div className="flex items-center gap-3 mt-0.5 text-xs text-gray-500">
            <span className="flex items-center gap-1">
              <Icon icon="mdi:email-outline" className="w-3.5 h-3.5" />
              {attendee.email}
            </span>
            {attendee.phone && (
              <span className="flex items-center gap-1">
                <Icon icon="mdi:phone-outline" className="w-3.5 h-3.5" />
                {attendee.phone}
              </span>
            )}
            {attendee.graduationYear && <span>Class of {attendee.graduationYear}</span>}
          </div>
        </div>
      </div>

      {/* Status Badge */}
      <div className="flex items-center gap-3 flex-shrink-0">
        {attendee.guestCount && attendee.guestCount > 0 && (
          <span className="text-xs text-gray-600">
            +{attendee.guestCount} guest{attendee.guestCount > 1 ? 's' : ''}
          </span>
        )}
        <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold ${config.color}`}>
          {config.label.toUpperCase()}
        </span>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// SKELETON
// ═══════════════════════════════════════════════════════════════════════════

function AttendeeRowSkeleton() {
  return (
    <div className="flex items-center gap-4 rounded-xl border border-gray-100 bg-white px-4 py-3 animate-pulse">
      <div className="w-10 h-10 rounded-full bg-gray-200" />
      <div className="flex-1 space-y-2">
        <div className="h-4 w-48 bg-gray-200 rounded" />
        <div className="h-3 w-64 bg-gray-100 rounded" />
      </div>
      <div className="h-6 w-16 bg-gray-200 rounded-full" />
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════

export function AdminEventRegistrationsPage() {
  const { filters, setFilter } = usePersistedFilters(
    'admin-event-registrations-filters',
    {
      selectedEventId: '',
      statusFilter: 'all' as 'all' | AttendeeStatus,
      searchQuery: '',
    },
    { paginationParams: ['attendeesPage'] },
  );
  const { selectedEventId, statusFilter, searchQuery } = filters;
  const [eventsPage, setEventsPage] = useUrlPagination('eventsPage');
  const [attendeesPage, setAttendeesPage] = useUrlPagination('attendeesPage');

  // Fetch all events
  const { data: events = [], isLoading: eventsLoading } = useAllEvents();

  // Fetch attendees for selected event
  const { data: attendeeData, isLoading: attendeesLoading } = useEventAttendees(
    selectedEventId,
    statusFilter === 'all' ? undefined : statusFilter,
  );

  // Get first event by default
  const firstEventId = events[0]?.id;
  useEffect(() => {
    if (!selectedEventId && firstEventId && !eventsLoading) {
      setFilter('selectedEventId', firstEventId);
    }
  }, [eventsLoading, firstEventId, selectedEventId, setFilter]);

  const selectedEvent = events.find((e) => e.id === selectedEventId);

  // Filter attendees by search
  const filteredAttendees =
    attendeeData?.attendees.filter((a) => {
      if (!searchQuery.trim()) return true;
      const query = searchQuery.toLowerCase();
      return a.fullName.toLowerCase().includes(query) || a.email.toLowerCase().includes(query);
    }) || [];
  const totalEventPages = Math.max(
    1,
    Math.ceil(events.length / ADMIN_REGISTRATION_EVENTS_PER_PAGE),
  );
  const visibleEvents = useMemo(
    () =>
      events.slice(
        (eventsPage - 1) * ADMIN_REGISTRATION_EVENTS_PER_PAGE,
        eventsPage * ADMIN_REGISTRATION_EVENTS_PER_PAGE,
      ),
    [events, eventsPage],
  );
  const totalAttendeePages = Math.max(
    1,
    Math.ceil(filteredAttendees.length / ADMIN_REGISTRATION_ATTENDEES_PER_PAGE),
  );
  const visibleAttendees = filteredAttendees.slice(
    (attendeesPage - 1) * ADMIN_REGISTRATION_ATTENDEES_PER_PAGE,
    attendeesPage * ADMIN_REGISTRATION_ATTENDEES_PER_PAGE,
  );

  useEffect(() => {
    if (!eventsLoading && eventsPage > totalEventPages) {
      setEventsPage(totalEventPages);
    }
  }, [eventsLoading, eventsPage, totalEventPages]);

  useEffect(() => {
    if (!attendeesLoading && attendeesPage > totalAttendeePages) {
      setAttendeesPage(totalAttendeePages);
    }
  }, [attendeesLoading, attendeesPage, totalAttendeePages]);

  // Stats
  const stats = attendeeData
    ? [
        {
          label: 'Total Registered',
          value: attendeeData.totalRegistrations,
          color: 'text-primary-700',
        },
        { label: 'Going', value: attendeeData.goingCount, color: 'text-green-700' },
        { label: 'Maybe', value: attendeeData.maybeCount, color: 'text-amber-700' },
        { label: 'Not Going', value: attendeeData.notGoingCount, color: 'text-red-700' },
      ]
    : [];

  return (
    <>
      <SEO title="Event Registrations" description="View and manage event registrations" />
      <AdminBanner activeTab="events" title="Event Registrations" />

      <section className="min-h-screen bg-[#F8F8F7] py-8 sm:py-10">
        <div className="container-custom max-w-7xl">
          {/* Main Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6">
            {/* Left: Events List */}
            <div>
              <div className="bg-white rounded-xl border border-gray-200 p-4">
                <h2 className="font-semibold text-gray-900 mb-4">Events</h2>

                {eventsLoading ? (
                  <div className="space-y-2">
                    {[1, 2, 3].map((i) => (
                      <div key={i} className="h-20 rounded-xl bg-gray-100 animate-pulse" />
                    ))}
                  </div>
                ) : events.length === 0 ? (
                  <div className="text-center py-8">
                    <Icon
                      icon="mdi:calendar-month-outline"
                      className="w-12 h-12 mx-auto text-gray-300 mb-2"
                    />
                    <p className="text-sm text-gray-500">No events found</p>
                  </div>
                ) : (
                  <div className="space-y-2 max-h-[600px] overflow-y-auto">
                    {visibleEvents.map((event) => (
                      <EventListItem
                        key={event.id}
                        event={event}
                        isSelected={selectedEventId === event.id}
                        onClick={() => setFilter('selectedEventId', event.id)}
                      />
                    ))}
                  </div>
                )}

                {!eventsLoading && events.length > 0 ? (
                  <Pagination
                    currentPage={eventsPage}
                    totalPages={totalEventPages}
                    onPageChange={(page) => setEventsPage(page)}
                  />
                ) : null}
              </div>
            </div>

            {/* Right: Attendees */}
            <div>
              {!selectedEventId ? (
                <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
                  <Icon
                    icon="mdi:account-group-outline"
                    className="w-16 h-16 mx-auto text-gray-300 mb-3"
                  />
                  <p className="text-gray-500">Select an event to view attendees</p>
                </div>
              ) : (
                <>
                  {/* Event Header */}
                  <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <h2 className="text-xl font-bold text-gray-900">{selectedEvent?.title}</h2>
                    <div className="flex items-center gap-4 mt-2 text-sm text-gray-600">
                      {selectedEvent && (
                        <span className="flex items-center gap-1">
                          <Icon icon="mdi:calendar-outline" className="w-4 h-4" />
                          {formatDateRange(selectedEvent.startDate, selectedEvent.endDate, {
                            locale: 'en-GB',
                            formatOptions: {
                              day: 'numeric',
                              month: 'long',
                              year: 'numeric',
                            },
                          })}
                        </span>
                      )}
                      {selectedEvent?.location && (
                        <span className="flex items-center gap-1">
                          <Icon icon="mdi:map-marker-outline" className="w-4 h-4" />
                          {selectedEvent.location}
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Stats Cards */}
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    {stats.map((stat) => (
                      <div
                        key={stat.label}
                        className="bg-white rounded-xl border border-gray-200 p-4"
                      >
                        <p className="text-xs text-gray-500">{stat.label}</p>
                        <p className={`text-2xl font-bold mt-1 ${stat.color}`}>{stat.value}</p>
                      </div>
                    ))}
                  </div>

                  {/* Filters */}
                  <div className="mb-8">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                      {/* Search */}
                      <div className="flex-1">
                        <label htmlFor="admin-event-registrations-search" className="sr-only">
                          Search attendees by name or email
                        </label>
                        <SearchInput
                          id="admin-event-registrations-search"
                          placeholder="Search by name or email..."
                          value={searchQuery}
                          onValueChange={(value) => setFilter('searchQuery', value)}
                          className="w-full"
                          inputClassName="!h-[56px] !border-0 !shadow-none focus:!ring-0"
                        />
                      </div>

                      {/* Status Filter */}
                      <div className="flex flex-wrap gap-3">
                        <button
                          onClick={() => {
                            setFilter('statusFilter', 'all');
                          }}
                          className={`min-h-[48px] rounded-full border px-5 py-2.5 text-sm font-semibold transition-colors ${
                            statusFilter === 'all'
                              ? 'border-primary-500 bg-primary-500 text-white'
                              : 'border-primary-100 bg-white text-gray-600 hover:border-primary-300'
                          }`}
                        >
                          All
                        </button>
                        <button
                          onClick={() => {
                            setFilter('statusFilter', 'going');
                          }}
                          className={`min-h-[48px] rounded-full border px-5 py-2.5 text-sm font-semibold transition-colors ${
                            statusFilter === 'going'
                              ? 'border-green-500 bg-green-500 text-white'
                              : 'border-primary-100 bg-white text-gray-600 hover:border-primary-300'
                          }`}
                        >
                          Going
                        </button>
                        <button
                          onClick={() => {
                            setFilter('statusFilter', 'maybe');
                          }}
                          className={`min-h-[48px] rounded-full border px-5 py-2.5 text-sm font-semibold transition-colors ${
                            statusFilter === 'maybe'
                              ? 'border-amber-500 bg-amber-500 text-white'
                              : 'border-primary-100 bg-white text-gray-600 hover:border-primary-300'
                          }`}
                        >
                          Maybe
                        </button>

                        <button
                          onClick={() => {
                            setFilter('statusFilter', 'not_going');
                          }}
                          className={`min-h-[48px] rounded-full border px-5 py-2.5 text-sm font-semibold transition-colors ${
                            statusFilter === 'not_going'
                              ? 'border-red-500 bg-red-500 text-white'
                              : 'border-primary-100 bg-white text-gray-600 hover:border-primary-300'
                          }`}
                        >
                          Not Going
                        </button>
                      </div>
                    </div>
                  </div>

                  {/* Attendees List */}
                  <div className="bg-white rounded-xl border border-gray-200 p-4">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="font-semibold text-gray-900">
                        Attendees ({filteredAttendees.length})
                      </h3>
                      {/* Future: Export button */}
                    </div>

                    <div className="space-y-2">
                      {attendeesLoading ? (
                        Array.from({ length: 5 }).map((_, i) => <AttendeeRowSkeleton key={i} />)
                      ) : filteredAttendees.length === 0 ? (
                        <div className="text-center py-12">
                          <Icon
                            icon="mdi:account-search-outline"
                            className="w-16 h-16 mx-auto text-gray-300 mb-3"
                          />
                          <p className="text-gray-500 text-sm">
                            {searchQuery
                              ? 'No attendees found matching your search'
                              : statusFilter === 'all'
                                ? 'No registrations yet'
                                : `No "${
                                    statusFilter === 'not_going'
                                      ? 'Not Going'
                                      : statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1)
                                  }" registrations`}
                          </p>
                        </div>
                      ) : (
                        visibleAttendees.map((attendee) => (
                          <AttendeeRow key={attendee.userId} attendee={attendee} />
                        ))
                      )}
                    </div>
                    {!attendeesLoading && filteredAttendees.length > 0 ? (
                      <Pagination
                        currentPage={attendeesPage}
                        totalPages={totalAttendeePages}
                        onPageChange={(page) => setAttendeesPage(page)}
                      />
                    ) : null}
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
