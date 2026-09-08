// features/events/components/EventCard.tsx
//
// Single shared card component used by:
//   - EventsPage (grid + calendar)
//   - UpcomingEvents (homepage)
//   - Any other listing surface
//
// Eliminates duplication between EventsPage's local EventCard and
// the homepage UpcomingEvents component's local EventCard.

import { useState } from 'react';
import {
  ArrowRight,
  CalendarDays,
  CheckCircle,
  CircleSlash,
  Clock3,
  MapPin,
  Pencil,
  Share2,
  Trash2,
  Users,
} from 'lucide-react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useEventRegistration, useEventAttendeeCount } from '../hooks/useEventRegistration';
import { useDeleteEvent } from '../hooks/useEvents';

import { EVENT_ROUTES } from '../routes';
import type { Event } from '../types/event.types';
import { DeleteConfirmModal } from './DeleteConfirmModal';
import { handleShare } from '@/shared/utils/share';
import { toast } from '@/shared/components/ui/Toast';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { stripEventAnnouncementMarker } from '../lib/eventAnnouncementVisibility';

const EVENT_CARD_ICON_STROKE = 2.35;

// ─── Skeleton ─────────────────────────────────────────────────────────────────

export function EventCardSkeleton() {
  return (
    <div className="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 animate-pulse flex flex-col">
      <div className="h-52 w-full bg-gray-200" />
      <div className="p-4 flex flex-col gap-3">
        <div className="h-4 bg-gray-200 rounded w-3/4" />
        <div className="h-3 bg-gray-200 rounded w-1/2" />
        <div className="h-3 bg-gray-200 rounded w-full" />
        <div className="h-3 bg-gray-200 rounded w-5/6" />
        <div className="h-3 bg-gray-200 rounded w-16 mt-2" />
      </div>
    </div>
  );
}

// ─── Props ────────────────────────────────────────────────────────────────────

interface EventCardProps {
  event: Event;
  isPast?: boolean;
  /** Called when the user clicks Register — parent controls modal state */
  onRegister?: () => void;
  /** When true shows Edit / Delete admin buttons */
  isAdmin?: boolean;
  /** Called after a successful delete so parent can update its list */
  onDeleted?: (eventId: string) => void;
  /** Compact variant used on homepage — hides attendee count + admin actions */
  compact?: boolean;
}

// ─── Card ─────────────────────────────────────────────────────────────────────

export function EventCard({
  event,
  isPast = false,
  onRegister,
  isAdmin = false,
  onDeleted,
  compact = false,
}: EventCardProps) {
  const currentUser = useIdentityStore((state) => state.user);
  const isLoggedIn = !!currentUser;

  const { isRegistered } = useEventRegistration(event.id);
  const { attendeeCount, capacity, isFull, spotsLeft } = useEventAttendeeCount(event);

  const deleteEvent = useDeleteEvent();
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  const isCancelled = event.status === 'cancelled';

  const formattedDate = formatDateRange(event.startDate, event.endDate, {
    locale: 'en-GB',
    formatOptions: {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    },
  });

  const eventUrl =
    typeof window !== 'undefined'
      ? `${window.location.origin}${EVENT_ROUTES.DETAIL(event.id)}`
      : EVENT_ROUTES.DETAIL(event.id);
  const cleanDescription = stripEventAnnouncementMarker(event.description ?? '');

  const onShare = async () => {
    const result = await handleShare({
      title: event.title,
      text: cleanDescription.slice(0, 100),
      url: eventUrl,
    });

    if (result.success && result.copied) {
      toast.success('Link copied to clipboard');
    } else if (!result.success) {
      toast.error('Failed to share event');
    }
  };

  const handleDelete = () => {
    deleteEvent.mutate(event.id, {
      onSuccess: () => {
        setShowDeleteModal(false);
        onDeleted?.(event.id);
      },
    });
  };

  const renderAction = () => {
    // COMPACT MODE (Homepage) → always show Register → Details
    if (compact) {
      // Always show View Details on homepage compact cards
      return (
        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="inline-flex items-center gap-1 text-primary-500 hover:text-primary-600 text-xs font-semibold transition-colors"
        >
          View Details <ArrowRight className="h-3 w-3" strokeWidth={EVENT_CARD_ICON_STROKE} />
        </AppLink>
      );
    }

    // PAST EVENTS → View Details
    if (isPast) {
      return (
        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="inline-block border border-gray-300 text-gray-500 hover:border-primary-400 hover:text-primary-500 text-xs font-semibold px-5 py-1.5 rounded-md transition-colors"
        >
          View Details
        </AppLink>
      );
    }

    // CANCELLED EVENTS → View Details
    if (isCancelled) {
      return (
        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="inline-flex items-center gap-1 text-red-500 hover:text-red-600 text-xs font-semibold transition-colors"
        >
          <CircleSlash className="h-3.5 w-3.5" strokeWidth={EVENT_CARD_ICON_STROKE} />
          View Details
        </AppLink>
      );
    }

    // NOT LOGGED IN → View Details
    if (!isLoggedIn) {
      return (
        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="inline-flex items-center gap-1 text-primary-500 hover:text-primary-600 text-xs font-semibold transition-colors"
        >
          View Details <ArrowRight className="h-3 w-3" strokeWidth={EVENT_CARD_ICON_STROKE} />
        </AppLink>
      );
    }

    // REGISTERED OR FULL → View Details
    if (isRegistered || isFull) {
      return (
        <AppLink
          href={EVENT_ROUTES.DETAIL(event.id)}
          className="inline-flex items-center gap-1 text-gray-500 hover:text-primary-500 text-xs font-semibold transition-colors"
        >
          {isRegistered ? (
            <CheckCircle className="h-3.5 w-3.5" strokeWidth={EVENT_CARD_ICON_STROKE} />
          ) : null}
          {isFull && <span className="text-gray-400">Event Full</span>}
          View Details
        </AppLink>
      );
    }

    // DEFAULT → available for registration
    return (
      <button
        type="button"
        onClick={onRegister}
        className="inline-flex items-center gap-1 text-primary-500 hover:text-primary-600 text-xs font-semibold transition-colors"
      >
        Register <ArrowRight className="h-3 w-3" strokeWidth={EVENT_CARD_ICON_STROKE} />
      </button>
    );
  };

  return (
    <>
      <div className="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col">
        {/* Banner image */}
        <div className="h-52 w-full overflow-hidden bg-gray-100 relative">
          {event.image ? (
            <img
              src={event.image}
              alt={event.title}
              className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center bg-primary-50">
              <CalendarDays
                className="h-12 w-12 text-primary-200"
                strokeWidth={EVENT_CARD_ICON_STROKE}
              />
            </div>
          )}

          {/* Share Button */}
          <button
            type="button"
            onClick={onShare}
            //   className="absolute top-3 right-3 z-10 bg-white/90 hover:bg-white text-gray-700 p-2 rounded-full shadow-sm transition"
            className="absolute top-3 right-3 z-10 
           bg-black/50 backdrop-blur-sm 
           hover:bg-black/70 
           text-white 
           p-2 rounded-full 
           shadow-md transition"
          >
            <Share2 className="h-4 w-4" strokeWidth={EVENT_CARD_ICON_STROKE} />
          </button>

          {/* Badges */}
          {!isPast && isRegistered && (
            <div className="absolute top-3 left-3 bg-green-500 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full flex items-center gap-1">
              <CheckCircle className="h-3 w-3" strokeWidth={EVENT_CARD_ICON_STROKE} />
              Registered
            </div>
          )}
          {!isPast && isFull && !isRegistered && (
            <div className="absolute top-3 left-3 bg-red-500 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full">
              Event Full
            </div>
          )}
          {isCancelled && (
            <div className="absolute top-3 left-3 bg-gray-600 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full">
              Cancelled
            </div>
          )}
        </div>

        {/* Body */}
        <div className="p-4 flex flex-col gap-2 flex-1">
          <h3 className="text-primary-500 font-bold text-sm leading-snug">{event.title}</h3>

          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-gray-400 text-[11px]">
            {event.location && (
              <span className="flex items-center gap-1">
                <MapPin className="h-3 w-3 flex-shrink-0" strokeWidth={EVENT_CARD_ICON_STROKE} />
                {event.location}
              </span>
            )}
            {formattedDate && (
              <span className="flex items-center gap-1">
                <CalendarDays
                  className="h-3 w-3 flex-shrink-0"
                  strokeWidth={EVENT_CARD_ICON_STROKE}
                />
                {formattedDate}
              </span>
            )}
            {event.startTime && (
              <span className="flex items-center gap-1">
                <Clock3 className="h-3 w-3 flex-shrink-0" strokeWidth={EVENT_CARD_ICON_STROKE} />
                {event.startTime}
              </span>
            )}
          </div>

          <p className="text-gray-500 text-[11px] leading-relaxed line-clamp-3">
            {cleanDescription}
          </p>

          {/* Attendee count — hidden in compact mode */}
          {!compact && !isPast && !isCancelled && (
            <div className="flex items-center gap-1 text-gray-600 text-[11px] mt-1">
              <Users className="h-3.5 w-3.5 flex-shrink-0" strokeWidth={EVENT_CARD_ICON_STROKE} />
              <span>
                {capacity ? (
                  <>
                    <span className="font-semibold">{attendeeCount}</span>/{capacity} attending
                    {spotsLeft !== undefined && spotsLeft > 0 && spotsLeft <= 10 && (
                      <span className="text-orange-500 ml-1">
                        ({spotsLeft} spot{`${spotsLeft == 0 || spotsLeft > 1 ? 's' : ''}`} left)
                      </span>
                    )}
                  </>
                ) : (
                  <>
                    <span className="font-semibold">{attendeeCount}</span> attending
                  </>
                )}
              </span>
            </div>
          )}

          {/* Footer row */}
          <div className="mt-auto pt-2 flex items-center justify-between">
            <div className="flex items-center gap-2">{renderAction()}</div>

            {/* Admin actions — hidden in compact mode */}
            {!compact && isAdmin && (
              <div className="flex items-center gap-2">
                <AppLink
                  href={EVENT_ROUTES.EDIT(event.id)}
                  className="flex items-center gap-1 text-xs text-primary-500 hover:text-primary-600 transition-colors"
                >
                  <Pencil className="h-3.5 w-3.5" strokeWidth={EVENT_CARD_ICON_STROKE} />
                  Edit
                </AppLink>
                <button
                  type="button"
                  onClick={() => setShowDeleteModal(true)}
                  className="flex items-center gap-1 text-xs text-red-500 hover:text-red-600 transition-colors"
                >
                  <Trash2 className="h-3.5 w-3.5" strokeWidth={EVENT_CARD_ICON_STROKE} />
                  Delete
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {showDeleteModal && (
        <DeleteConfirmModal
          title={event.title}
          isDeleting={deleteEvent.isPending}
          onConfirm={handleDelete}
          onCancel={() => setShowDeleteModal(false)}
        />
      )}
    </>
  );
}
