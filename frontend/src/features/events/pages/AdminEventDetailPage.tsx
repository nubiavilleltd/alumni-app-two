// features/events/pages/EventDetailPage.tsx

import { useMemo, useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { AppLink } from '@/shared/components/ui/AppLink';
import { SEO } from '@/shared/common/SEO';
import { RegisterEventModal } from '../components/RegisterEventModal';
import { DeleteConfirmModal } from '../components/DeleteConfirmModal';
import { useEvent, useDeleteEvent, useCancelRegistration } from '../hooks/useEvents';
import { useEventRegistration, useEventAttendeeCount } from '../hooks/useEventRegistration';
import { toast } from '@/shared/components/ui/Toast';
import { EVENT_ROUTES } from '../routes';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { renderMarkdown } from '@/data/content';
import { stripEventAnnouncementMarker } from '../lib/eventAnnouncementVisibility';
import { useEventStatus } from '../hooks/useEventStatus';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { useRequireSignIn } from '@/features/authentication/hooks/useRequireSignIn';
import {
  AlertCircle,
  CalendarDays,
  CalendarX,
  Clock,
  LoaderCircle,
  MapPin,
  Share2,
  Users,
} from 'lucide-react';

// ─── Countdown ────────────────────────────────────────────────────────────────

function useCountdown(targetDate: string, startTime?: string) {
  const [remaining, setRemaining] = useState<{
    d: number;
    h: number;
    m: number;
    s: number;
  } | null>(null);

  useEffect(() => {
    const target = (() => {
      const [y, mo, d] = targetDate.split('-').map(Number);
      const [h = 0, mi = 0] = (startTime ?? '00:00').split(':').map(Number);
      return new Date(y, mo - 1, d, h, mi, 0);
    })();

    const tick = () => {
      const diff = target.getTime() - Date.now();
      if (diff <= 0) {
        setRemaining(null);
        return;
      }
      const s = Math.floor(diff / 1000);
      setRemaining({
        d: Math.floor(s / 86400),
        h: Math.floor((s % 86400) / 3600),
        m: Math.floor((s % 3600) / 60),
        s: s % 60,
      });
    };

    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [targetDate, startTime]);

  return remaining;
}

/**
 * Countdown timer — matches the screenshot exactly:
 * bordered box, large digit, colon separator, label beneath each unit.
 */
function CountdownTimer({ date, startTime }: { date: string; startTime?: string }) {
  const t = useCountdown(date, startTime);
  if (!t) return null;

  const pad = (n: number) => String(n).padStart(2, '0');

  const units = [
    { value: pad(t.d), label: 'Days' },
    { value: pad(t.h), label: 'Hours' },
    { value: pad(t.m), label: 'Minutes' },
    { value: pad(t.s), label: 'Seconds' },
  ];

  return (
    <div className="inline-flex items-center gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
      {units.map((u, i) => (
        <div key={u.label} className="flex items-center">
          {/* Unit cell */}
          <div className="flex flex-col items-center justify-center px-4 sm:px-6 py-3 sm:py-4 min-w-[60px] sm:min-w-[72px]">
            <span className="text-2xl sm:text-3xl font-bold text-gray-900 tabular-nums leading-none">
              {u.value}
            </span>
            <span className="text-[10px] sm:text-xs text-gray-400 mt-1 font-medium tracking-wide">
              {u.label}
            </span>
          </div>
          {/* Colon separator — not after last unit */}
          {i < units.length - 1 && (
            <span className="text-xl sm:text-2xl font-bold text-gray-300 select-none pb-3">:</span>
          )}
        </div>
      ))}
    </div>
  );
}

// ─── Unregister confirmation ──────────────────────────────────────────────────

function UnregisterConfirmModal({
  eventTitle,
  isLoading,
  onConfirm,
  onCancel,
}: {
  eventTitle: string;
  isLoading: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <AlertCircle className="w-5 h-5 text-red-600" />
          </div>
          <div>
            <h3 className="font-bold text-gray-900 text-lg mb-1">Cancel Registration?</h3>
            <p className="text-gray-600 text-sm">
              Are you sure you want to unregister from{' '}
              <span className="font-semibold">{eventTitle}</span>?
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
            {isLoading && <LoaderCircle className="w-4 h-4 animate-spin" />}
            Yes, Unregister
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function EventDetailSkeleton() {
  return (
    <div className="container-custom py-6 animate-pulse">
      {/* Admin bar placeholder */}
      <div className="flex justify-end gap-3 mb-4">
        <div className="h-9 w-36 bg-gray-200 rounded-full" />
        <div className="h-9 w-28 bg-gray-200 rounded-full" />
        <div className="h-9 w-28 bg-gray-200 rounded-full" />
      </div>
      {/* Hero */}
      <div className="w-full h-64 sm:h-80 bg-gray-200 rounded-2xl mb-6" />
      {/* Title */}
      <div className="h-8 bg-gray-200 rounded w-2/3 mb-3" />
      {/* Meta */}
      <div className="h-4 bg-gray-200 rounded w-1/3 mb-2" />
      <div className="h-4 bg-gray-200 rounded w-1/4 mb-6" />
      {/* Content */}
      <div className="space-y-2 mb-8">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="h-4 bg-gray-200 rounded w-full" />
        ))}
        <div className="h-4 bg-gray-200 rounded w-3/4" />
      </div>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function AdminEventDetailPage() {
  const { id = '' } = useParams();
  const requireSignIn = useRequireSignIn();
  const { data: event, isLoading, error } = useEvent(id);
  const { isUpcoming, isOngoing, isPast } = useEventStatus(event);

  const navigate = useNavigate();

  const currentUser = useIdentityStore((state) => state.user);
  const isLoggedIn = !!currentUser;

  const deleteEvent = useDeleteEvent();
  const cancelMutation = useCancelRegistration();

  const { isRegistered } = useEventRegistration(event?.id ?? '');
  const { attendeeCount, capacity, isFull, spotsLeft } = useEventAttendeeCount(event ?? null);

  const [showRegisterModal, setShowRegisterModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showUnregisterModal, setShowUnregisterModal] = useState(false);

  const markdown = useMemo(() => {
    const cleanContent = stripEventAnnouncementMarker(event?.content ?? '');
    if (!cleanContent) return '';
    try {
      return renderMarkdown(cleanContent);
    } catch {
      return cleanContent;
    }
  }, [event?.content]);

  // ── Loading / error states ────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#F8F8F7]">
        <EventDetailSkeleton />
      </div>
    );
  }

  if (error || !event) {
    return (
      <section className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom text-center py-20">
          <CalendarX className="w-16 h-16 text-red-400 mx-auto mb-4" />
          <h1 className="text-2xl font-bold mb-3">Event not found</h1>
          <p className="text-gray-600 mb-6">This event doesn't exist or has been removed.</p>
          <div className="flex gap-4 justify-center">
            <AppLink href={EVENT_ROUTES.ROOT} className="btn btn-primary">
              Back to Events
            </AppLink>
            <button onClick={() => window.location.reload()} className="btn btn-outline">
              Try Again
            </button>
          </div>
        </div>
      </section>
    );
  }

  // ── Derived state ─────────────────────────────────────────────────────────

  const isCancelled = event.status === 'cancelled';

  // ── Handlers ──────────────────────────────────────────────────────────────

  const handleDelete = () => {
    deleteEvent.mutate(event.id, {
      onSuccess: () => navigate(EVENT_ROUTES.ROOT),
      onError: (err: any) => {
        setShowDeleteModal(false);
        toast.fromError(err);
      },
    });
  };

  const handleUnregister = async () => {
    try {
      await cancelMutation.mutateAsync(event.id);
      toast.success('You have been unregistered from this event.');
    } catch (err: any) {
      toast.fromError(err);
    } finally {
      setShowUnregisterModal(false);
    }
  };

  const handleShare = () => {
    const url = window.location.href;
    if (navigator.share) {
      navigator.share({ title: event.title, url }).catch(() => {});
    } else {
      navigator.clipboard
        .writeText(url)
        .then(() => toast.success('Link copied!'))
        .catch(() => {});
    }
  };

  // ── Formatted date ────────────────────────────────────────────────────────

  const dateDisplay = (() => {
    const dateRange = formatDateRange(event.startDate, event.endDate);
    const timePart = [event.startTime, event.endTime].filter(Boolean).join(' - ');
    if (dateRange && timePart) return `${dateRange} · ${timePart}`;
    return dateRange;
  })();

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <>
      <SEO title={event.title} description={stripEventAnnouncementMarker(event.description)} />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-6">
          {/* ════════════════════════════════════════════════════
              ADMIN ACTION BAR
              Sits above the hero image, right-aligned.
              Visible only to admins, always as buttons (no menu).
              ════════════════════════════════════════════════════ */}
          <div className="flex items-center justify-end gap-2 sm:gap-3 mb-4 flex-wrap">
            {/* View Registrations */}
            <AppLink
              href={EVENT_ROUTES.ATTENDEES(event.id)}
              className="inline-flex items-center gap-1.5 border-2 border-primary-500 bg-white hover:bg-primary-600 hover:text-white text-primary-500 text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2 rounded-full transition-colors shadow-sm whitespace-nowrap"
            >
              {/* <Icon icon="mdi:account-group-outline" className="w-4 h-4" /> */}
              View Registrations
            </AppLink>

            {/* Edit Event */}
            <AppLink
              href={EVENT_ROUTES.EDIT(event.id)}
              className="inline-flex items-center gap-1.5 border-2 border-primary-500 bg-white hover:bg-primary-600 hover:text-white text-primary-500 text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2 rounded-full transition-colors shadow-sm whitespace-nowrap"
            >
              {/* <Icon icon="mdi:pencil-outline" className="w-4 h-4" /> */}
              Edit Event
            </AppLink>

            {/* Delete Event */}
            <button
              type="button"
              onClick={() => setShowDeleteModal(true)}
              className="inline-flex items-center gap-1.5 border-2 border-red-500 bg-white hover:bg-red-50 text-red-600 text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2 rounded-full transition-colors shadow-sm whitespace-nowrap"
            >
              {/* <Icon icon="mdi:trash-can-outline" className="w-4 h-4" /> */}
              Delete Event
            </button>
          </div>
          {/* ════════════════════════════════════════════════════
              HERO IMAGE
              ════════════════════════════════════════════════════ */}
          {event.image ? (
            <div className="w-full rounded-2xl overflow-hidden mb-6 bg-gray-100 shadow-sm">
              <img
                src={event.image}
                alt={event.title}
                className="w-full object-cover max-h-[400px]"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                }}
              />
            </div>
          ) : (
            <div className="w-full h-52 rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center mb-6 shadow-sm">
              <CalendarDays className="w-16 h-16 text-primary-300" />
            </div>
          )}

          {/* ════════════════════════════════════════════════════
              CONTENT
              ════════════════════════════════════════════════════ */}
          <div className="max-w-3xl">
            {/* ── Back link ─────────────────────────────────── */}

            {/* ── Status badges ─────────────────────────────── */}
            <div className="flex flex-wrap items-center gap-2 mb-3">
              {isOngoing && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                  {/* <Icon icon="mdi:play-circle-outline" className="w-3.5 h-3.5" /> */}
                  Happening Now
                </span>
              )}
              {isPast && !isCancelled && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                  {/* <Icon icon="mdi:calendar-check-outline" className="w-3.5 h-3.5" /> */}
                  Past Event
                </span>
              )}
              {event.featured && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200">
                  {/* <Icon icon="mdi:star" className="w-3.5 h-3.5" /> */}
                  Featured
                </span>
              )}
              {isRegistered && (isUpcoming || isOngoing) && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                  {/* <Icon icon="mdi:check-circle" className="w-3.5 h-3.5" /> */}
                  You're Registered
                </span>
              )}
              {isRegistered && isPast && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                  {/* <Icon icon="mdi:check-circle" className="w-3.5 h-3.5" /> */}
                  You Attended
                </span>
              )}
              {!isCancelled && isFull && !isRegistered && isUpcoming && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-red-100 text-red-600 border border-red-200">
                  {/* <Icon icon="mdi:alert-circle-outline" className="w-3.5 h-3.5" /> */}
                  Event Full
                </span>
              )}
              {isCancelled && (
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-red-100 text-red-600 border border-red-200">
                  {/* <Icon icon="mdi:cancel" className="w-3.5 h-3.5" /> */}
                  Event Cancelled
                </span>
              )}
            </div>

            {/* ── Title ─────────────────────────────────────── */}
            <h1 className="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight mb-4">
              {event.title}
            </h1>

            {/* ── Location + Date ───────────────────────────── */}
            <div className="flex flex-col gap-1.5 mb-6">
              {event.location && (
                <div className="flex items-center gap-2 text-gray-600 text-sm">
                  {/* <Icon
                    icon="mdi:map-marker-outline"
                    className="w-4 h-4 text-gray-400 flex-shrink-0"
                  /> */}
                  <MapPin size={15} />
                  <span>{event.location}</span>
                </div>
              )}
              {dateDisplay && (
                <div className="flex items-center gap-2 text-gray-600 text-sm">
                  {/* <Icon icon="mdi:clock-outline" className="w-4 h-4 text-gray-400 flex-shrink-0" /> */}
                  <Clock size={15} />
                  <span>{dateDisplay}</span>
                </div>
              )}
              {!isCancelled && attendeeCount > 0 && (
                <div className="flex items-center gap-2 text-gray-600 text-sm">
                  <Users className="w-4 h-4 text-gray-600 flex-shrink-0" />

                  <span>
                    {capacity ? `${attendeeCount}/${capacity}` : attendeeCount}{' '}
                    {isPast ? 'attended' : isOngoing ? 'attending live' : 'attending'}
                    {spotsLeft !== undefined &&
                      spotsLeft > 0 &&
                      spotsLeft <= 1 &&
                      (isUpcoming || isOngoing) && (
                        <span className="text-orange-500 font-semibold ml-1">
                          · {spotsLeft} spot(s) left
                        </span>
                      )}
                  </span>
                </div>
              )}
            </div>

            {/* ── Countdown ─────────────────────────────────── */}
            {isUpcoming && (
              <div className="mb-8">
                <CountdownTimer date={event.startDate} startTime={event.startTime} />
              </div>
            )}

            {/* ── Event content ─────────────────────────────── */}
            {markdown && (
              <div
                className="prose prose-sm sm:prose-base max-w-none prose-headings:text-gray-900 prose-p:text-gray-700 prose-a:text-primary-600 prose-strong:text-gray-900 mb-10 leading-relaxed"
                dangerouslySetInnerHTML={{ __html: markdown }}
              />
            )}

            {/* ════════════════════════════════════════════════
                BOTTOM ACTION ROW
                Register / Cancel Registration (never both)
                + Share icon button beside it.
                Matches the screenshot: pill button on left,
                circular share icon immediately to its right.
                ════════════════════════════════════════════════ */}
            <div className="flex items-center gap-3">
              {/* Register — not logged in, upcoming, not full */}
              {(isUpcoming || isOngoing) && !isCancelled && !isLoggedIn && !isFull && (
                <button
                  type="button"
                  onClick={() =>
                    requireSignIn({ message: 'Please sign in to register for this event.' })
                  }
                  className="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm px-6 py-2.5 rounded-full transition-colors shadow-sm"
                >
                  {/* <Icon icon="mdi:login" className="w-4 h-4" /> */}
                  Sign in to Register
                </button>
              )}

              {/* Register — logged in, upcoming, not registered, not full */}
              {(isUpcoming || isOngoing) &&
                !isCancelled &&
                isLoggedIn &&
                !isRegistered &&
                !isFull && (
                  <button
                    type="button"
                    onClick={() => setShowRegisterModal(true)}
                    className="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm px-6 py-2.5 rounded-full transition-colors shadow-sm"
                  >
                    Register
                  </button>
                )}

              {/* Cancel Registration — logged in, upcoming, registered */}
              {(isUpcoming || isOngoing) && !isCancelled && isLoggedIn && isRegistered && (
                <button
                  type="button"
                  onClick={() => setShowUnregisterModal(true)}
                  className="inline-flex items-center gap-2 border border-red-300 bg-red-500 hover:bg-red-600 text-white font-semibold text-sm px-6 py-2.5 rounded-full transition-colors shadow-sm"
                >
                  Cancel Registration
                </button>
              )}

              {/* Share — always visible */}
              <button
                type="button"
                onClick={handleShare}
                aria-label="Share event"
                className="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-full border border-primary-300 bg-white hover:bg-primary-50 text-primary-500 transition-colors shadow-sm"
              >
                {/* <Icon icon="mdi:share-variant-outline" className="w-4 h-4" /> */}
                <Share2 size={20} />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* ── Modals ──────────────────────────────────────── */}
      <RegisterEventModal
        event={showRegisterModal ? event : null}
        onClose={() => setShowRegisterModal(false)}
      />

      {showDeleteModal && (
        <DeleteConfirmModal
          title={event.title}
          isDeleting={deleteEvent.isPending}
          onConfirm={handleDelete}
          onCancel={() => setShowDeleteModal(false)}
        />
      )}

      {showUnregisterModal && (
        <UnregisterConfirmModal
          eventTitle={event.title}
          isLoading={cancelMutation.isPending}
          onConfirm={handleUnregister}
          onCancel={() => setShowUnregisterModal(false)}
        />
      )}
    </>
  );
}
