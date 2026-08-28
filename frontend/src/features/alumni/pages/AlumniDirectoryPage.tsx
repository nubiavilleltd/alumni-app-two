// features/alumni/pages/AlumniDirectoryPage.tsx

import { Icon } from '@iconify/react';
import { useEffect, useMemo, useState } from 'react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import EmptyState from '@/shared/components/ui/EmptyState';
import { Pagination } from '@/shared/components/ui/Pagination';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { ALUMNI_ROUTES } from '../routes';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';
import { Alumni } from '../types/alumni.types';
import { resolveProfilePhoto, resolveVisibleField } from '@/features/user/utils/profileUtils';

/* ───────────────────────────────────────────────────────────── */
/* Responsive items per page */
/* ───────────────────────────────────────────────────────────── */

function generateInitialsAvatar(name: string): string {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(
    name,
  )}&background=E5E7EB&color=6B7280&size=256`;
}

function useItemsPerPage() {
  const [items, setItems] = useState(12);

  useEffect(() => {
    const update = () => {
      if (window.innerWidth < 640)
        setItems(6); // mobile
      else if (window.innerWidth < 1024)
        setItems(8); // tablet
      else setItems(12); // desktop
    };

    update();
    window.addEventListener('resize', update);
    return () => window.removeEventListener('resize', update);
  }, []);

  return items;
}

/* ───────────────────────────────────────────────────────────── */
/* Card */
/* ───────────────────────────────────────────────────────────── */

function AlumniCard({ entry, currentUser, onMessageClick, isMessagePending }: any) {
  // const photoVisible = isFieldVisible(entry, 'photo', currentUser);
  // const displayPhoto = getPhotoDisplay(entry.photo, photoVisible);
  const classLabel = `Class '${String(entry.graduationYear).slice(-2)}`;
  const isOwnProfile = entry.memberId === currentUser?.memberId;

  // const occupation = entry.position || entry.occupations?.[0] || '';

  const isOwner = entry.memberId === currentUser?.memberId;
  const isSignedIn = Boolean(currentUser?.memberId);
  const displayPhoto = resolveProfilePhoto({
    photoUrl: entry.photo,
    privacy: entry.privacy,
    isOwner,
    isSignedIn,
  });
  // entry.memberId == '39' &&
  //   console.log('canSeePhoto', { canSeePhoto, photo: entry.photo, photo2: displayPhoto });

  const visibleGraduationYear = resolveVisibleField(
    entry.graduationYear,
    'birthDate', // TEMPORARY until you create graduationYear privacy
    entry.privacy,
    isOwner,
    isSignedIn,
  );

  const visibleOccupation = resolveVisibleField(
    entry.position || entry.occupations?.[0],
    'employmentStatus', // TEMPORARY mapping
    entry.privacy,
    isOwner,
    isSignedIn,
  );

  // const classLabel = visibleGraduationYear
  //   ? `Class '${String(visibleGraduationYear).slice(-2)}`
  //   : null;

  return (

    <div
      className="
    relative
    rounded-2xl
    overflow-hidden
    group
    cursor-pointer
   h-[250px]
    sm:h-[315px]
    lg:h-[375px]
  "
    >
      {/* Image */}
      <div className="absolute inset-0">
        {/* <div className="absolute top-0 left-0 right-0 h-[58%] sm:h-full"> */}
        <img
          src={displayPhoto ?? generateInitialsAvatar(entry.name)}
          alt={entry.name}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          loading="lazy"
        />
      </div>
      {/* <div className="absolute inset-0">
        {displayPhoto ? (
          <img
            src={displayPhoto }
            alt={entry.name}
            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full bg-gray-100 flex items-center justify-center">
            <Icon icon="mdi:account-circle" className="w-24 h-24 text-gray-300" />
          </div>
        )}
      </div> */}

      {/* Overlay */}
      <div className="absolute bottom-0 left-0 right-0 bg-primary-600/70 backdrop-blur-sm px-4 pt-3 pb-4 rounded-2xl">
        <div className="flex flex-col gap-2">
          {' '}
          <p className="text-white font-semibold text-sm truncate">{entry.name}</p>
          <p className="text-white/90 text-xs font-semibold">{classLabel}</p>
          {/* <p className="text-white/90 text-xs font-semibold">{classLabel ?? '\u00A0'}</p> */}
          {/* {occupation ? (
            <p className="text-white/90 text-xs truncate font-semibold">{occupation}</p>
          ) : (
            <p className="text-white/90 text-xs truncate font-semibold">&nbsp;</p>
          )} */}
          {visibleOccupation ? (
            <p className="text-white/90 text-xs truncate font-semibold">{visibleOccupation}</p>
          ) : (
            <p className="text-white/90 text-xs truncate font-semibold">&nbsp;</p>
          )}
        </div>

        <div className="flex gap-2 mt-3">
          <AppLink
            href={ALUMNI_ROUTES.PROFILE(entry.memberId)}
            className="flex-1 text-center border border-2 border-white text-white font-bold text-xs py-1.5 rounded-full"
          >
            View Profile
          </AppLink>

          <button
            onClick={() => onMessageClick(entry)}
            disabled={!entry.memberId || isOwnProfile || isMessagePending}
            title={isOwnProfile ? 'You cannot message yourself' : ''}
            className="flex-1 bg-white text-primary-600 font-bold text-xs py-1.5 rounded-full disabled:opacity-50"
          >
            {isMessagePending ? 'Opening…' : 'Send Message'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ───────────────────────────────────────────────────────────── */
/* Page */
/* ───────────────────────────────────────────────────────────── */

export function AlumniDirectoryPage() {
  const currentUser = useIdentityStore((state) => state.user);

  const [searchTerm, setSearchTerm] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);

  const ITEMS_PER_PAGE = useItemsPerPage();

  const { startDirectConversation, isPending } = useStartDirectConversation();
  const [pendingId, setPendingId] = useState<string | null>(null);

  const { data: alumni = [], isLoading } = useAlumni({ action_type: 'approved' });

  const years = useMemo(
    () => [...new Set(alumni.map((e) => e.graduationYear))].sort((a, b) => b - a),
    [alumni],
  );

  const filtered = useMemo(() => {
    const q = searchTerm.toLowerCase();

    let result = alumni.filter(
      (e) =>
        (!q || e.name.toLowerCase().includes(q)) &&
        (!yearFilter || e.graduationYear.toString() === yearFilter),
    );

    // ✅ PRIORITIZE USER'S GRADUATION YEAR (only when no filter is applied)
    if (!yearFilter && currentUser?.graduationYear) {
      result = [...result].sort((a, b) => {
        if (a.graduationYear === currentUser.graduationYear) return -1;
        if (b.graduationYear === currentUser.graduationYear) return 1;
        return b.graduationYear - a.graduationYear; // fallback: newest first
      });
    }

    return result;
  }, [alumni, searchTerm, yearFilter, currentUser]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  const start = (currentPage - 1) * ITEMS_PER_PAGE;
  const visible = filtered.slice(start, start + ITEMS_PER_PAGE);

  const changePage = (p: number) => {
    setCurrentPage(p);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  async function handleMessage(entry: Alumni) {
    if (!entry.memberId) return;

    const isOwner = entry.memberId === currentUser?.memberId;
    const isSignedIn = Boolean(currentUser?.memberId);
    const displayPhoto = resolveProfilePhoto({
      photoUrl: entry.photo,
      privacy: entry.privacy,
      isOwner,
      isSignedIn,
    });

    const recipientHeadline =
      entry.position && entry.company
        ? `${entry.position} at ${entry.company}`
        : entry.position || entry.occupations?.[0] || `Class of ${entry.graduationYear}`;

    setPendingId(entry.memberId);
    await startDirectConversation({
      participantMemberId: entry.memberId,
      recipientProfile: {
        fullName: entry.name,
        avatar: displayPhoto,
        photoVisibility: entry.privacy?.photo,
        headline: recipientHeadline,
        location: entry.location || entry.city,
        graduationYear: entry.graduationYear,
        slug: entry.slug,
        profileHref: `/alumni/profiles/${entry.memberId}`,
      },
    });
    setPendingId(null);
  }

  return (
    <>
      <SEO title="Alumni Directory" />

      <section className="min-h-screen bg-[#F8F8F7] py-8">
        <div className="container-custom mx-auto">
          {/* Title */}
          <h1 className="type-section-title mb-6">Alumni Directory</h1>

          {/* Filters */}
          <div className="flex flex-col sm:flex-row items-center gap-4 mb-8">
            <div className="flex-1 w-full sm:max-w-xl">
              <SearchInput
                value={searchTerm}
                onValueChange={(v) => {
                  setSearchTerm(v);
                  setCurrentPage(1);
                }}
                placeholder="Search by name"
                inputClassName="!h-10 !py-0"
              />

            </div>

            <div className="w-full sm:w-auto">
              <FilterDropdown
                value={yearFilter}
                onChange={(v) => {
                  setYearFilter(v);
                  setCurrentPage(1);
                }}
                placeholder="Graduation Year"
                options={years.map((y) => ({ label: String(y), value: String(y) }))}
              />
            </div>
          </div>

          {/* Grid */}
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="bg-gray-200 h-64 rounded-2xl animate-pulse" />
              ))}
            </div>
          ) : visible.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {visible.map((entry) => (
                <AlumniCard
                  key={entry.id}
                  entry={entry}
                  currentUser={currentUser}
                  onMessageClick={handleMessage}
                  isMessagePending={isPending && pendingId === entry.memberId}
                />
              ))}
            </div>
          ) : (
            <EmptyState title="No alumni found" description="Try adjusting filters." />
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                onPageChange={changePage}
              />
            </div>
          )}
        </div>
      </section>
    </>
  );
}
