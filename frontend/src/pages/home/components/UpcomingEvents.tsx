import { Icon } from '@iconify/react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useUpcomingEvents } from '@/features/events/hooks/useEvents';
import type { Event } from '@/features/events/types/event.types';
import EmptyState from '@/shared/components/ui/EmptyState';
import { EVENT_ROUTES } from '@/features/events/routes';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { HomeSectionHeader } from './HomeSectionHeader';
import { stripEventAnnouncementMarker } from '@/features/events/lib/eventAnnouncementVisibility';

function HomeEventCard({ event }: { event: Event }) {
  const dateRange = formatDateRange(event.startDate, event.endDate);
  const cleanDescription = stripEventAnnouncementMarker(event.description ?? '');

  return (
    <article className="home-event-card">
      <div className="home-event-card__image-wrap">
        {event.image ? (
          <img src={event.image} alt="" className="home-event-card__image" />
        ) : (
          <div className="home-event-card__placeholder">
            <Icon icon="mdi:calendar-month-outline" aria-hidden="true" />
          </div>
        )}
      </div>

      <div className="home-event-card__body">
        <h3>{event.title}</h3>
        <p>{cleanDescription}</p>

        <div className="home-event-card__meta">
          {event.location && (
            <span>
              <Icon icon="mdi:map-marker-outline" aria-hidden="true" />
              {event.location}
            </span>
          )}
          {dateRange && (
            <span>
              <Icon icon="mdi:clock-outline" aria-hidden="true" />
              {dateRange}
            </span>
          )}
        </div>

        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="home-card-link home-card-link--blue"
        >
          View Details
          <Icon icon="mdi:chevron-right" aria-hidden="true" />
        </AppLink>
      </div>
    </article>
  );
}

function HomeEventSkeleton() {
  return (
    <div className="home-event-card home-event-card--skeleton">
      <div className="home-event-card__image-wrap" />
      <div className="home-event-card__body">
        <span />
        <span />
        <span />
      </div>
    </div>
  );
}

export default function UpcomingEvents() {
  const { data: events = [], isLoading } = useUpcomingEvents();

  const isEmpty = !isLoading && events.length === 0;

  return (
    <section className="home-feature-section">
      <div className="container-custom">
        <HomeSectionHeader
          eyebrow="Upcoming Events"
          title="Stay updated on upcoming alumni gatherings"
          href={EVENT_ROUTES.ROOT}
          showViewAll={!isEmpty}
        />

        {isEmpty ? (
          <EmptyState
            icon="mdi:calendar-blank-outline"
            title="No upcoming events right now"
            description="Check back soon for new events."
          />
        ) : (
          <div className="home-events-grid">
            {isLoading
              ? Array.from({ length: 3 }).map((_, i) => <HomeEventSkeleton key={i} />)
              : events.slice(0, 3).map((event) => <HomeEventCard key={event.id} event={event} />)}
          </div>
        )}
      </div>
    </section>
  );
}
