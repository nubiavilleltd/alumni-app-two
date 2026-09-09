// features/alumni/pages/AlumniDirectoryPage.tsx

import { Icon } from '@iconify/react';
import { useEffect, useMemo, useState } from 'react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import { Pagination } from '@/shared/components/ui/Pagination';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { ALUMNI_ROUTES } from '../routes';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';
import { Alumni } from '../types/alumni.types';
import {
  HierarchicalLocationFilter,
  type HierarchicalLocationNode,
  type HierarchicalLocationSelection,
} from '@/shared/components/ui/HierarchicalLocationFilter';
import { resolveProfilePhoto, resolveVisibleField } from '@/features/user/utils/profileUtils';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { useLocations } from '@/shared/hooks/useLocations';
import type { LocationGroup } from '@/shared/types/location.types';
import { normalizeLocationPart } from '@/shared/utils/location';

/* ───────────────────────────────────────────────────────────── */
/* Responsive items per page */
/* ───────────────────────────────────────────────────────────── */

function generateInitialsAvatar(name: string): string {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(
    name,
  )}&background=E5E7EB&color=6B7280&size=256`;
}

type AlumniLocationFilterValues = {
  searchTerm: string;
  yearFilter: string;
  state: string;
  city: string;
};

type MutableLocationNode = {
  label: string;
  value: string;
  children: Map<string, MutableLocationNode>;
};

function getAlumniLocationParts(entry: Alumni) {
  const state = entry.state?.trim() ?? '';
  const city = entry.city?.trim() || entry.location?.trim() || '';

  return { state, city };
}

function matchesAlumniLocation(entry: Alumni, location: HierarchicalLocationSelection) {
  if (!location.state && !location.city) return true;

  const parts = getAlumniLocationParts(entry);
  const entryState = normalizeLocationPart(parts.state || parts.city);
  const entryCity = normalizeLocationPart(parts.city);

  if (location.city) {
    return (
      normalizeLocationPart(location.state) === normalizeLocationPart(parts.state) &&
      entryCity === normalizeLocationPart(location.city)
    );
  }

  return entryState === normalizeLocationPart(location.state);
}

function buildAlumniLocationHierarchy(
  alumni: Alumni[],
  filters: AlumniLocationFilterValues,
  locationCatalogue: readonly LocationGroup[],
): HierarchicalLocationNode[] {
  const states = new Map<string, MutableLocationNode>();
  const query = filters.searchTerm.trim().toLowerCase();

  const matchesWithoutLocation = (entry: Alumni) => {
    const searchableFields = [
      entry.name,
      entry.position,
      entry.company,
      entry.location,
      entry.city,
      entry.state,
      ...(entry.occupations ?? []),
      ...(entry.industrySectors ?? []),
    ];

    return (
      (!query || searchableFields.filter(Boolean).join(' ').toLowerCase().includes(query)) &&
      (!filters.yearFilter || entry.graduationYear.toString() === filters.yearFilter)
    );
  };

  const addLocation = (stateLabel: string, cityLabel?: string) => {
    stateLabel = stateLabel.trim();
    const stateValue = normalizeLocationPart(stateLabel);
    if (!stateLabel || !stateValue) return;

    let stateNode = states.get(stateValue);
    if (!stateNode) {
      stateNode = { label: stateLabel, value: stateValue, children: new Map() };
      states.set(stateValue, stateNode);
    }

    cityLabel = cityLabel?.trim();
    const cityValue = normalizeLocationPart(cityLabel);
    if (!cityValue || stateNode.children.has(cityValue)) return;

    stateNode.children.set(cityValue, {
      label: cityLabel ?? '',
      value: cityValue,
      children: new Map(),
    });
  };

  locationCatalogue.forEach((location) => {
    addLocation(location.state);
    location.cities.forEach((city) => addLocation(location.state, city));
  });

  alumni.forEach((entry) => {
    const parts = getAlumniLocationParts(entry);
    if (parts.state && parts.city) addLocation(parts.state, parts.city);
    else if (parts.city) addLocation(parts.city);
  });

  const countForLocation = (location: HierarchicalLocationSelection) =>
    alumni.filter(
      (entry) => matchesWithoutLocation(entry) && matchesAlumniLocation(entry, location),
    ).length;

  const toOptions = (
    nodes: Map<string, MutableLocationNode>,
    parent: HierarchicalLocationSelection,
  ): HierarchicalLocationNode[] =>
    Array.from(nodes.values())
      .sort((first, second) => first.label.localeCompare(second.label))
      .map((node) => {
        const selection =
          nodes === states
            ? { state: node.value, city: '' }
            : { state: parent.state, city: node.value };
        const children = Array.from(node.children.values()).length
          ? toOptions(node.children, selection)
          : undefined;

        return {
          label: node.label,
          value: node.value,
          count: countForLocation(selection),
          ...(children ? { children } : {}),
        };
      });

  return toOptions(states, { state: '', city: '' });
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

  const { filters, setFilter, setFilters, clearFilters } = usePersistedFilters(
    'alumni-directory-filters',
    {
      searchTerm: '',
      yearFilter: '',
      locationFilter: '',
      cityFilter: '',
    },
  );
  const { searchTerm, yearFilter, locationFilter, cityFilter } = filters;
  const [currentPage, setCurrentPage] = useUrlPagination();

  const ITEMS_PER_PAGE = useItemsPerPage();

  const { startDirectConversation, isPending } = useStartDirectConversation();
  const [pendingId, setPendingId] = useState<string | null>(null);

  const { data: alumni = [], isLoading } = useAlumni({ action_type: 'approved' });
  const { data: locations = [] } = useLocations();

  const years = useMemo(
    () => [...new Set(alumni.map((e) => e.graduationYear))].sort((a, b) => b - a),
    [alumni],
  );

  const locationHierarchy = useMemo(
    () =>
      buildAlumniLocationHierarchy(
        alumni,
        {
          searchTerm,
          yearFilter,
          state: locationFilter,
          city: cityFilter,
        },
        locations,
      ),
    [alumni, cityFilter, locationFilter, locations, searchTerm, yearFilter],
  );

  /* ═══════════════════════════════════════════════════════════════════
   * 🚨 DEMO-ONLY CODE — REMOVE AFTER THE DEMO 🚨
   * ═══════════════════════════════════════════════════════════════════
   * This helper decides whether an alumni entry has a real, visible
   * photo (as opposed to falling back to the generated initials avatar).
   * It's used below purely to push "photos first" for the demo so the
   * directory looks populated/impressive on stage.
   *
   * This is NOT meant to be permanent product behavior — ranking real
   * users lower just because they haven't uploaded a photo (or have
   * privacy settings that hide it) is not a fair long-term sort, and
   * it will silently change search/pagination order in a way product
   * hasn't signed off on. Delete this block and revert `filtered`
   * to the version in the "ORIGINAL SORT" comment below once the
   * demo is done.
   * ═══════════════════════════════════════════════════════════════════ */
  const hasVisiblePhotoForDemo = (entry: Alumni) => {
    const isOwner = entry.memberId === currentUser?.memberId;
    const isSignedIn = Boolean(currentUser?.memberId);
    const photo = resolveProfilePhoto({
      photoUrl: entry.photo,
      privacy: entry.privacy,
      isOwner,
      isSignedIn,
    });
    return Boolean(photo);
  };
  /* 🚨 END DEMO-ONLY HELPER 🚨 */

  const filtered = useMemo(() => {
    const q = searchTerm.toLowerCase();

    let result = alumni.filter((e) => {
      const searchableFields = [
        e.name,
        e.position,
        e.company,
        e.location,
        e.city,
        e.state,
        ...(e.occupations ?? []),
        ...(e.industrySectors ?? []),
      ];
      return (
        (!q || searchableFields.filter(Boolean).join(' ').toLowerCase().includes(q)) &&
        (!yearFilter || e.graduationYear.toString() === yearFilter) &&
        matchesAlumniLocation(e, { state: locationFilter, city: cityFilter })
      );
    });

    /* ─────────────────────────────────────────────────────────────
     * 🚨 DEMO-ONLY SORT — REMOVE AFTER THE DEMO 🚨
     * Photos-first, then the original graduation-year priority logic.
     * ───────────────────────────────────────────────────────────── */
    result = [...result].sort((a, b) => {
      // 1. Alumni with a visible photo float to the top for the demo.
      const aHasPhoto = hasVisiblePhotoForDemo(a);
      const bHasPhoto = hasVisiblePhotoForDemo(b);
      if (aHasPhoto !== bHasPhoto) return aHasPhoto ? -1 : 1;

      // 2. Existing behavior: prioritize the current user's own grad year
      //    (only when no year filter is applied).
      if (!yearFilter && currentUser?.graduationYear) {
        if (a.graduationYear === currentUser.graduationYear) return -1;
        if (b.graduationYear === currentUser.graduationYear) return 1;
      }

      // 3. Fallback: newest graduation year first.
      return b.graduationYear - a.graduationYear;
    });
    /* 🚨 END DEMO-ONLY SORT 🚨 */

    /* ─────────────────────────────────────────────────────────────
     * ORIGINAL SORT — restore this (and delete the block above)
     * once the demo is over:
     *
     * if (!yearFilter && currentUser?.graduationYear) {
     *   result = [...result].sort((a, b) => {
     *     if (a.graduationYear === currentUser.graduationYear) return -1;
     *     if (b.graduationYear === currentUser.graduationYear) return 1;
     *     return b.graduationYear - a.graduationYear; // fallback: newest first
     *   });
     * }
     * ───────────────────────────────────────────────────────────── */

    return result;
  }, [alumni, currentUser, locationFilter, cityFilter, searchTerm, yearFilter]);

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

  const hasActiveFilters = Boolean(searchTerm.trim() || yearFilter || locationFilter || cityFilter);

  const clearAllFilters = () => {
    clearFilters();
  };

  return (
    <>
      <SEO title="Alumni Directory" />

      <section className="min-h-screen bg-[#F8F8F7] py-8">
        <div className="container-custom mx-auto">
          {/* Title */}
          <h1 className="type-section-title mb-6">Alumni Directory</h1>

          {/* Filters */}
          <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
            <div className="w-full lg:w-64 lg:flex-shrink-0">
              <SearchInput
                value={searchTerm}
                onValueChange={(v) => setFilter('searchTerm', v)}
                placeholder="Search by name, work or location"
                inputClassName="!h-10 !py-0"
              />
            </div>

            <HierarchicalLocationFilter
              label=""
              placeholder="Location"
              value={{ state: locationFilter, city: cityFilter }}
              options={locationHierarchy}
              onChange={(selection) => {
                setFilters({
                  locationFilter: selection.state,
                  cityFilter: selection.city,
                });
              }}
              className="w-full lg:w-48 lg:flex-shrink-0"
            />

            <FilterDropdown
              value={yearFilter}
              onChange={(v) => setFilter('yearFilter', v)}
              placeholder="Graduation year"
              options={years.map((y) => ({ label: String(y), value: String(y) }))}
              className="w-full lg:w-44 lg:flex-shrink-0"
            />

            {hasActiveFilters && (
              <ClearFiltersButton onClick={clearAllFilters} className="w-full lg:w-auto" />
            )}
          </div>

          {hasActiveFilters && (
            <div className="mb-8 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
              <span>
                Showing {filtered.length} {filtered.length === 1 ? 'alumnus' : 'alumni'} matching
                your filters
              </span>
              <ClearFiltersButton onClick={clearAllFilters} label="Clear all filters" />
            </div>
          )}

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
