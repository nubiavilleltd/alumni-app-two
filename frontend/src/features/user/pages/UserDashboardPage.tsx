// features/user/pages/UserDashboardPage.tsx
// NEW DESIGN: Matches screenshot exactly.
// Header → Profile Completeness (full width) →
// Attestation|Suggested Alumni (two-column) →
// My Registered Events (left column, matching width above)
// Background: warm off-white #F8F8F7

import { Icon } from '@iconify/react';
import { useState, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import { SEO } from '@/shared/common/SEO';
import { useMyEvents } from '@/features/events/hooks/useEventRegistration';
import type { Event } from '@/features/events/types/event.types';
import { EVENT_ROUTES } from '@/features/events/routes';
import { ALUMNI_ROUTES } from '@/features/alumni/routes';
import { USER_ROUTES } from '../routes';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { usePendingVouches, useApproveVouch, useRejectVouch } from '../hooks/useVoucher';
import type { PendingVouch } from '../api/voucherApi';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { resolveProfilePhoto } from '@/features/user/utils/profileUtils';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { Calendar, Clock, MapPin } from 'lucide-react';

const voucherActionButtonClassName =
  'flex h-[33px] w-[134px] items-center justify-center gap-1 rounded-[48px] border-2 px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60';
const voucherConfirmButtonClassName = `${voucherActionButtonClassName} border-primary-500 bg-primary-500 text-white hover:bg-primary-600`;
const voucherDenyButtonClassName = `${voucherActionButtonClassName} border-[#C80000] bg-transparent text-[#C80000] hover:bg-red-50`;

// ─── Profile completeness checklist ──────────────────────────────────────────

interface CheckItem {
  key: string;
  label: string;
  done: boolean;
}

function buildCheckItems(user: any): CheckItem[] {
  return [
    { key: 'photo', label: 'Profile Photo', done: !!user?.photo },
    { key: 'email', label: 'Email Address', done: !!user?.email },
    { key: 'phone', label: 'Phone Number', done: !!user?.whatsappPhone },
    { key: 'bio', label: 'About Me', done: !!user?.bio },
    { key: 'maiden', label: 'Former Name', done: !!user?.nameInSchool },
    { key: 'nickname', label: 'Preferred Nickname', done: !!user?.nickName },
    { key: 'dob', label: 'Date of Birth', done: !!user?.birthDate },
    { key: 'employment', label: 'Employment status', done: !!user?.employmentStatus },
    { key: 'occupation', label: 'Occupation', done: !!user?.occupations?.length },
    { key: 'address', label: 'Address', done: !!user?.residentialAddress },
    {
      key: 'socials',
      label: 'Socials',
      done: !!(user?.instagram || user?.twitter || user?.linkedin || user?.facebook),
    },
  ];
}

function computePct(items: CheckItem[]) {
  return Math.round((items.filter((i) => i.done).length / items.length) * 100);
}

// ─── Profile Completeness Card ────────────────────────────────────────────────

function ProfileCompletenessCard({ user }: { user: any }) {
  const navigate = useNavigate();
  const items = buildCheckItems(user);
  const pct = computePct(items);
  const left = items.filter((i) => !i.done).length;

  // 4 columns
  const cols: CheckItem[][] = [
    items.slice(0, 3),
    items.slice(3, 6),
    items.slice(6, 9),
    items.slice(9),
  ];

  return (
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
      <h2 className="font-bold text-gray-900 text-base mb-0.5">Profile Completeness</h2>
      <p className="text-sm text-gray-500 mb-4">
        You're almost there! Complete your profile to help other members find you and connect with
        you.
      </p>

      {/* Progress */}
      <div className="flex items-center justify-between text-xs mb-1.5">
        <span className="text-gray-700 font-medium">{pct}% Complete</span>
        <span className="text-gray-400">
          {left} Item{left !== 1 ? 's' : ''} Left
        </span>
      </div>
      <div className="h-2 w-full rounded-full bg-gray-100 mb-5 overflow-hidden">
        <div
          className="h-full rounded-full bg-primary-500 transition-all duration-500"
          style={{ width: `${pct}%` }}
        />
      </div>

      {/* Checklist: 2 cols mobile, 4 cols desktop */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2 mb-6">
        {cols.map((col, ci) => (
          <div key={ci} className="space-y-2">
            {col.map((item) => (
              <div key={item.key} className="flex items-center gap-1.5 text-sm">
                {item.done ? (
                  <Icon icon="mdi:check" className="w-3.5 h-3.5 text-primary-500 flex-shrink-0" />
                ) : (
                  <Icon icon="mdi:close" className="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                )}
                <span className={item.done ? 'text-primary-600 font-medium' : 'text-gray-600'}>
                  {item.label}
                </span>
              </div>
            ))}
          </div>
        ))}
      </div>

      <div className="flex justify-center">
        <button
          type="button"
          onClick={() => navigate(USER_ROUTES.EDIT_PROFILE)}
          className="bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold px-10 py-2.5 rounded-full transition-colors"
        >
          Complete Your Profile
        </button>
      </div>
    </div>
  );
}

// ─── Attestation row ──────────────────────────────────────────────────────────

function AttestationRow({
  vouch,
  onApprove,
  onReject,
}: {
  vouch: PendingVouch;
  onApprove: (id: string) => void;
  onReject: (id: string) => void;
}) {
  const [showRejectInput, setShowRejectInput] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [actionError, setActionError] = useState('');

  const approveMutation = useApproveVouch();
  const rejectMutation = useRejectVouch();
  const busy = approveMutation.isPending || rejectMutation.isPending;

  const handleApprove = async () => {
    setActionError('');
    try {
      await approveMutation.mutateAsync(vouch.vouchId);
      onApprove(vouch.vouchId);
    } catch (err: any) {
      setActionError(err.message ?? 'Failed.');
    }
  };

  const handleDeny = async () => {
    setActionError('');
    try {
      await rejectMutation.mutateAsync({
        vouchId: vouch.vouchId,
        reason: rejectReason.trim() || undefined,
      });
      onReject(vouch.vouchId);
    } catch (err: any) {
      setActionError(err.message ?? 'Failed.');
    }
  };

  return (
    <div className="bg-white rounded-2xl border border-gray-100 p-4">
      <div className="flex gap-3 justify-between">
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-gray-900 text-sm">{vouch.fullName}</p>
          <p className="text-xs text-gray-500">Class of {vouch.graduationYear}</p>
          {vouch.nickName && <p className="text-xs text-gray-500">A.K.A {vouch.nickName}</p>}
          <p className="text-xs text-gray-500 mt-1">{vouch.email}</p>
          {/* {vouch.department && (
            <p className="text-xs text-gray-500">Former Role: {vouch.department}</p>
          )} */}
        </div>

        {/* Confirm + Deny stacked */}
        {!showRejectInput && (
          <div className="flex flex-col gap-2 flex-shrink-0">
            <button
              type="button"
              onClick={handleApprove}
              disabled={busy}
              className={voucherConfirmButtonClassName}
            >
              {approveMutation.isPending ? (
                <Icon icon="mdi:loading" className="w-4 h-4 animate-spin" />
              ) : (
                'Confirm'
              )}
            </button>
            <button
              type="button"
              onClick={() => {
                setShowRejectInput(true);
                setActionError('');
              }}
              disabled={busy}
              className={voucherDenyButtonClassName}
            >
              Deny
            </button>
          </div>
        )}
      </div>

      {showRejectInput && (
        <div className="mt-3 space-y-2 border-t border-gray-100 pt-3">
          <label className="text-xs font-medium text-gray-700">
            Reason <span className="text-gray-400 font-normal">(optional)</span>
          </label>
          <TextareaInput
            rows={2}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder="e.g. I do not recognise this person as an alumni member."
            className="gap-2"
            textareaClassName="rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm shadow-none placeholder:text-gray-400 focus:border-primary-400 focus:ring-1 focus:ring-primary-200 resize-none"
          />
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleDeny}
              disabled={busy}
              className={`${voucherActionButtonClassName} border-[#C80000] bg-[#C80000] text-white hover:bg-[#A90000]`}
            >
              {rejectMutation.isPending && (
                <Icon icon="mdi:loading" className="w-3.5 h-3.5 animate-spin" />
              )}
              Confirm deny
            </button>
            <button
              type="button"
              onClick={() => {
                setShowRejectInput(false);
                setRejectReason('');
                setActionError('');
              }}
              disabled={busy}
              className="border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold px-4 py-1.5 rounded-full"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {actionError && (
        <p className="mt-2 text-xs text-red-500 bg-red-50 rounded-lg px-3 py-1.5">{actionError}</p>
      )}
    </div>
  );
}

// ─── Registered event row ─────────────────────────────────────────────────────

function RegisteredEventRow({ event }: { event: Event }) {
  const dateStr = formatDateRange(event.startDate, event.endDate);

  return (
    <AppLink
      href={EVENT_ROUTES.DETAIL(event.id)}
      className="flex items-center gap-3 sm:gap-4 p-2 rounded-2xl hover:bg-gray-50 transition-colors"
    >
      <div className="w-[72px] h-[72px] rounded-xl overflow-hidden flex-shrink-0 bg-gray-100">
        {event.image ? (
          <img
            src={event.image}
            alt={event.title}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            {/* <Icon icon="mdi:calendar-month-outline" className="w-6 h-6 text-gray-300" /> */}
            <Calendar className="text-gray-600" size={15} />
          </div>
        )}
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-bold text-gray-900 text-sm leading-snug line-clamp-1">{event.title}</p>
        <p className="text-gray-500 text-xs mt-0.5 line-clamp-2 leading-relaxed">
          {event.description}
        </p>
        {event.location && (
          <p className="text-gray-600 text-[11px] mt-1 flex items-center gap-1 truncate">
            {/* <Icon icon="mdi:map-marker-outline" className="w-3 h-3 flex-shrink-0" /> */}
            <MapPin size={15} />
            <span className="truncate">{event.location}</span>
          </p>
        )}
        {dateStr && (
          <p className="text-gray-600 text-[11px] mt-0.5 flex items-center gap-1">
            {/* <Icon icon="mdi:clock-outline" className="w-3 h-3 flex-shrink-0" /> */}
            <Clock size={15} />
            {dateStr}
          </p>
        )}
      </div>
    </AppLink>
  );
}

// ─── Suggested alumni member row ─────────────────────────────────────────────────────

function SuggestedAlumnaRow({
  alumnus,
  viewerMemberId,
  isSignedIn,
}: {
  alumnus: any;
  viewerMemberId?: string;
  isSignedIn: boolean;
}) {
  const initials = alumnus.name
    ?.split(' ')
    .slice(0, 2)
    .map((n: string) => n[0])
    .join('')
    .toUpperCase();
  const displayPhoto = resolveProfilePhoto({
    photoUrl: alumnus.photo,
    privacy: alumnus.privacy,
    isOwner: alumnus.memberId === viewerMemberId,
    isSignedIn,
  });
  return (
    <AppLink
      href={ALUMNI_ROUTES.PROFILE(alumnus.memberId)}
      className="flex items-center gap-3 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50/50 -mx-1 px-1 rounded-xl transition-colors"
    >
      <div className="w-12 h-12 rounded-full overflow-hidden flex-shrink-0 bg-gray-100 flex items-center justify-center">
        {displayPhoto ? (
          <img
            src={displayPhoto}
            alt={alumnus.name}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <span className="text-sm font-bold text-gray-500">{initials || '?'}</span>
        )}
      </div>
      <div className="min-w-0">
        <p className="font-semibold text-gray-900 text-sm">{alumnus.name}</p>
        <p className="text-xs text-gray-500">Class of {alumnus.graduationYear}</p>
        {alumnus.nickName && <p className="text-xs text-gray-400">A.K.A {alumnus.nickName}</p>}
      </div>
    </AppLink>
  );
}

// ─── Panel card ───────────────────────────────────────────────────────────────

function PanelCard({
  title,
  action,
  children,
}: {
  title: string;
  action?: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div className="flex items-center justify-between px-5 py-3.5 border-b border-gray-50">
        <h2 className="font-bold text-gray-900 text-sm">{title}</h2>
        {action}
      </div>
      <div className="p-4 space-y-3">{children}</div>
    </div>
  );
}

function SkeletonRows({ n = 2 }: { n?: number }) {
  return (
    <div className="space-y-3 animate-pulse">
      {Array.from({ length: n }).map((_, i) => (
        <div key={i} className="flex gap-3">
          <div className="w-14 h-14 rounded-xl bg-gray-100 flex-shrink-0" />
          <div className="flex-1 space-y-2 pt-1">
            <div className="h-4 bg-gray-100 rounded w-3/4" />
            <div className="h-3 bg-gray-100 rounded w-full" />
            <div className="h-3 bg-gray-100 rounded w-1/2" />
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function UserDashboardPage() {
  const { data: currentUser, isLoading: profileLoading } = useCurrentUser();
  const { data: pendingVouches = [], isLoading: vouchesLoading } = usePendingVouches();
  const { data: allAlumni = [], isLoading: alumniLoading } = useAlumni({ action_type: 'approved' });
  const { events: myEvents = [], isLoading: myEventsLoading } = useMyEvents();

  const [removedVouchIds, setRemovedVouchIds] = useState<Set<string>>(new Set());
  const handleVouchApprove = (id: string) => setRemovedVouchIds((p) => new Set([...p, id]));
  const handleVouchReject = (id: string) => setRemovedVouchIds((p) => new Set([...p, id]));

  const visibleVouches = pendingVouches.filter((v) => !removedVouchIds.has(v.vouchId));

  const myRegisteredEvents = myEvents
    .filter((e: Event) => new Date(e.startDate) >= new Date())
    .slice(0, 5);

  const suggestedAlumni = allAlumni
    .filter(
      (a) => a.email !== currentUser?.email && a.graduationYear == currentUser?.graduationYear,
    )
    .slice(0, 3);
  const hasSuggestedAlumni = suggestedAlumni.length > 0;
  const hasMyRegisteredEvents = myRegisteredEvents.length > 0;

  return (
    <>
      <SEO title="Dashboard" description="Your alumni dashboard" />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-6 sm:py-8 space-y-4 sm:space-y-5">
          {/* ── Header ───────────────────────────────────────────────── */}
          <div className="pb-4 border-b border-gray-200">
            {profileLoading ? (
              <div className="space-y-2 animate-pulse">
                <div className="h-7 bg-gray-200 rounded w-52" />
                <div className="h-4 bg-gray-200 rounded w-28" />
              </div>
            ) : (
              <>
                <h1 className="type-section-title text-gray-900">
                  Welcome back,{' '}
                  {currentUser?.otherNames || currentUser?.fullName?.split(' ')[0] || 'Alumni'}
                </h1>
                <p className="text-sm text-gray-500 mt-0.5">
                  Class of {currentUser?.graduationYear}
                </p>
              </>
            )}
          </div>

          {/* ── Profile Completeness ─────────────────────────────────── */}
          {profileLoading ? (
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 animate-pulse space-y-4">
              <div className="h-5 bg-gray-200 rounded w-40" />
              <div className="h-3 bg-gray-200 rounded w-2/3" />
              <div className="h-2 bg-gray-100 rounded-full" />
              <div className="grid grid-cols-4 gap-x-6 gap-y-2">
                {Array.from({ length: 11 }).map((_, i) => (
                  <div key={i} className="h-4 bg-gray-100 rounded" />
                ))}
              </div>
            </div>
          ) : (
            <ProfileCompletenessCard user={currentUser} />
          )}

          {/* ── Attestation | Suggested Alumni row ──────────────────── */}
          <div className="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-4">
            {/* Pending Attestation */}
            <PanelCard
              title="Pending your Attestation"
              // action={
              //   <AppLink
              //     href="#"
              //     className="text-xs font-semibold text-primary-500 hover:text-primary-600 flex items-center gap-0.5"
              //   >
              //     View All <Icon icon="mdi:chevron-right" className="w-3.5 h-3.5" />
              //   </AppLink>
              // }
            >
              {vouchesLoading ? (
                <SkeletonRows n={2} />
              ) : visibleVouches.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-8 text-center">
                  <div className="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center mb-2">
                    <Icon icon="mdi:check-all" className="w-5 h-5 text-primary-500" />
                  </div>
                  <p className="text-sm font-medium text-gray-700">All caught up!</p>
                  <p className="text-xs text-gray-400 mt-0.5">No pending attestations.</p>
                </div>
              ) : (
                visibleVouches.map((v) => (
                  <AttestationRow
                    key={v.vouchId}
                    vouch={v}
                    onApprove={handleVouchApprove}
                    onReject={handleVouchReject}
                  />
                ))
              )}
            </PanelCard>

            {/* Suggested Alumni */}
            <PanelCard
              title="Suggested Alumni"
              action={
                <AppLink
                  href={ALUMNI_ROUTES.PROFILES}
                  className="text-xs font-semibold text-primary-500 hover:text-primary-600 flex items-center gap-0.5"
                >
                  View Directory <Icon icon="mdi:chevron-right" className="w-3.5 h-3.5" />
                </AppLink>
              }
            >
              {alumniLoading ? (
                <div className="space-y-2 animate-pulse">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="flex items-center gap-3 py-2">
                      <div className="w-12 h-12 rounded-full bg-gray-100 flex-shrink-0" />
                      <div className="space-y-1.5 flex-1">
                        <div className="h-4 bg-gray-100 rounded w-28" />
                        <div className="h-3 bg-gray-100 rounded w-16" />
                      </div>
                    </div>
                  ))}
                </div>
              ) : suggestedAlumni.length > 0 ? (
                <div className="-mt-1">
                  {suggestedAlumni.map((a) => (
                    <SuggestedAlumnaRow
                      key={a.memberId}
                      alumnus={a}
                      viewerMemberId={currentUser?.memberId}
                      isSignedIn={Boolean(currentUser?.memberId)}
                    />
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-400 py-4 text-center">No suggestions yet.</p>
              )}
            </PanelCard>
          </div>

          {/* hasMyRegisteredEvents */}

          {/* ── My Registered Events ─────────────────────────────────── */}
          {/* Left column only, matching the layout above */}
          <div className="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-4">
            <PanelCard
              title="My Registered Events"
              action={
                hasMyRegisteredEvents && (
                  <AppLink
                    href={EVENT_ROUTES.MY_EVENTS}
                    className="text-xs font-semibold text-primary-500 hover:text-primary-600 flex items-center gap-0.5"
                  >
                    View All <Icon icon="mdi:chevron-right" className="w-3.5 h-3.5" />
                  </AppLink>
                )
              }
            >
              {myEventsLoading ? (
                <SkeletonRows n={2} />
              ) : myRegisteredEvents.length > 0 ? (
                myRegisteredEvents.map((event: Event) => (
                  <RegisteredEventRow key={event.id} event={event} />
                ))
              ) : (
                <div className="flex flex-col items-center justify-center py-8 text-center">
                  <Icon
                    icon="mdi:calendar-blank-outline"
                    className="w-10 h-10 text-gray-300 mb-2"
                  />
                  <p className="text-sm text-gray-500 mb-3">
                    You haven't registered for any upcoming events yet.
                  </p>
                  <AppLink
                    href={EVENT_ROUTES.ROOT}
                    className="text-sm font-semibold text-primary-500 hover:text-primary-600 inline-flex items-center gap-1"
                  >
                    Browse Events <Icon icon="mdi:arrow-right" className="w-4 h-4" />
                  </AppLink>
                </div>
              )}
            </PanelCard>

            {/* Right slot intentionally empty to keep the column width */}
            <div />
          </div>
        </div>
      </div>
    </>
  );
}
