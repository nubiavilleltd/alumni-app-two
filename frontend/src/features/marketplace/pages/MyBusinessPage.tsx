// features/marketplace/pages/MyBusinessPage.tsx

import { ComponentType, useEffect, useMemo, useState } from 'react';
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Globe,
  LoaderCircle,
  MapPin,
  Plus,
  SearchX,
  SlidersHorizontal,
  Store,
} from 'lucide-react';

import {
  IconBrandFacebook,
  IconBrandInstagram,
  IconBrandLinkedin,
  IconBrandTiktok,
  IconBrandX,
} from '@tabler/icons-react';

import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import {
  HierarchicalLocationFilter,
  type HierarchicalLocationNode,
  type HierarchicalLocationSelection,
} from '@/shared/components/ui/HierarchicalLocationFilter';
import { PostBusinessModal } from '../components/PostYourBusinessModal';
import { useMyBusinesses, useDeleteListing } from '../hooks/useMarketplace';
import type { Business } from '../types/marketplace.types';
import { MARKETPLACE_ROUTES } from '../routes';
import { ROUTES } from '@/shared/constants/routes';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { useLocations } from '@/shared/hooks/useLocations';
import type { LocationGroup } from '@/shared/types/location.types';
import { toTitleCase } from '@/shared/utils/textHelpers';
import { normalizeLegacyHashtags, parseHashtags } from '../utils/hashtags';
import { matchesLocationPart, normalizeLocationPart } from '@/shared/utils/location';
import { ProtectedContactValue } from '@/shared/components/ui/ProtectedContactValue';
const MY_BUSINESSES_PER_PAGE = 6;

type MyBusinessFilterState = {
  search: string;
  category: string;
  city: string;
  state: string;
};

function matchesMyBusinessFilters(business: Business, filters: MyBusinessFilterState) {
  const query = filters.search.trim().toLowerCase();
  const searchableFields = [
    business.name,
    business.owner,
    business.category,
    business.description,
    business.location,
    business.address,
    business.city,
    business.state,
  ];

  return (
    (!query || searchableFields.some((field) => field.toLowerCase().includes(query))) &&
    (!filters.category || business.category === filters.category) &&
    matchesLocationPart(business.city, filters.city) &&
    matchesLocationPart(business.state, filters.state)
  );
}

type MutableLocationNode = {
  label: string;
  value: string;
  children: Map<string, MutableLocationNode>;
};

function buildMyBusinessLocationHierarchy(
  businesses: Business[],
  filters: MyBusinessFilterState,
  locationCatalogue: readonly LocationGroup[],
): HierarchicalLocationNode[] {
  const states = new Map<string, MutableLocationNode>();
  const filtersWithoutLocation: MyBusinessFilterState = {
    ...filters,
    city: '',
    state: '',
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
    if (!cityLabel || !cityValue || stateNode.children.has(cityValue)) return;

    stateNode.children.set(cityValue, {
      label: cityLabel,
      value: cityValue,
      children: new Map(),
    });
  };

  locationCatalogue.forEach((location) => {
    addLocation(location.state);
    location.cities.forEach((city) => addLocation(location.state, city));
  });

  businesses.forEach((business) => {
    addLocation(business.state, business.city);
  });

  const countForLocation = (location: HierarchicalLocationSelection) =>
    businesses.filter((business) =>
      matchesMyBusinessFilters(business, {
        ...filtersWithoutLocation,
        ...location,
      }),
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

// ─── Skeleton ─────────────────────────────────────────────────────────────────
function MyBusinessCardSkeleton() {
  return (
    <div className="overflow-hidden rounded-[1.35rem] border border-[#e7edf5] bg-white p-2 shadow-[0_12px_26px_rgba(7,17,22,0.08)] animate-pulse">
      <div className="h-44 w-full rounded-[1.05rem] bg-gray-200" />
      <div className="flex flex-col gap-3 px-2 pb-2 pt-3.5">
        <div className="flex items-center gap-3">
          <div className="h-14 w-14 rounded-[0.95rem] bg-gray-200" />
          <div className="min-w-0 flex-1 space-y-2">
            <div className="h-4.5 w-3/4 rounded bg-gray-200" />
            <div className="h-3.5 w-1/2 rounded bg-gray-200" />
          </div>
        </div>
        <div className="space-y-1.5">
          <div className="h-3.5 w-full rounded bg-gray-200" />
          <div className="h-3.5 w-5/6 rounded bg-gray-200" />
        </div>
        <div className="space-y-1.5">
          <div className="h-3.5 w-3/4 rounded bg-gray-200" />
          <div className="h-3.5 w-2/3 rounded bg-gray-200" />
        </div>
        <div className="grid grid-cols-2 gap-2.5 pt-2">
          <div className="h-10 rounded-full bg-gray-200" />
          <div className="h-10 rounded-full bg-gray-200" />
        </div>
      </div>
    </div>
  );
}

// ─── Business Card ────────────────────────────────────────────────────────────
function getOwnerInitials(ownerName: string) {
  const parts = ownerName.trim().split(/\s+/).filter(Boolean);

  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }

  return parts[0]?.slice(0, 2).toUpperCase() || '?';
}

function getWebsiteHref(website: string) {
  return /^https?:\/\//i.test(website) ? website : `https://${website}`;
}

function isRealProfilePhoto(photo?: string | null) {
  return Boolean(photo && !photo.includes('ui-avatars.com') && !photo.includes('default-avatar'));
}

type SocialLinkEntry = {
  key: string;
  href: string;
  label: string;
  Icon: ComponentType<{ size?: number; stroke?: number }>;
};

function MyBusinessCard({
  business,
  ownerPhoto,
  onEdit,
  onDelete,
  isDeleting,
}: {
  business: Business;
  ownerPhoto?: string;
  onEdit: (business: Business) => void;
  onDelete: (business: Business) => void;
  isDeleting: boolean;
}) {
  const [imgIndex, setImgIndex] = useState(0);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [ownerPhotoFailed, setOwnerPhotoFailed] = useState(false);
  const ownerInitials = getOwnerInitials(business.owner);
  const showOwnerPhoto = isRealProfilePhoto(ownerPhoto) && !ownerPhotoFailed;
  const hasPhone = business.hasPhone ?? false;
  const hasWebsite = Boolean(business.website?.trim());

  const hasWhatsapp = business.hasWhatsapp ?? false;

  const instagramHref = business.socials?.instagram?.trim();
  // const hashtags = parseHashtags(business.socials?.instagramHashtag);
  const hashtags = parseHashtags(normalizeLegacyHashtags(business.socials?.instagramHashtag));

  const hasHashtagRow = hashtags.length > 0;

  const socialLinks: SocialLinkEntry[] = (
    [
      // business.socials?.instagram && {
      //   key: 'instagram',
      //   href: business.socials.instagram,
      //   label: `${business.name} on Instagram`,
      //   Icon: IconBrandInstagram,
      // },

      !hasHashtagRow &&
        instagramHref && {
          key: 'instagram',
          href: instagramHref,
          label: `${business.name} on Instagram`,
          Icon: IconBrandInstagram,
        },
      business.socials?.facebook && {
        key: 'facebook',
        href: business.socials.facebook,
        label: `${business.name} on Facebook`,
        Icon: IconBrandFacebook,
      },
      business.socials?.linkedin && {
        key: 'linkedin',
        href: business.socials.linkedin,
        label: `${business.name} on LinkedIn`,
        Icon: IconBrandLinkedin,
      },
      business.socials?.x && {
        key: 'x',
        href: business.socials.x,
        label: `${business.name} on X`,
        Icon: IconBrandX,
      },
      business.socials?.tiktok && {
        key: 'tiktok',
        href: business.socials.tiktok,
        label: `${business.name} on TikTok`,
        Icon: IconBrandTiktok,
      },
    ] as Array<SocialLinkEntry | false | undefined>
  ).filter((entry): entry is SocialLinkEntry => Boolean(entry));

  useEffect(() => {
    setOwnerPhotoFailed(false);
  }, [ownerPhoto]);

  const prev = (e: React.MouseEvent) => {
    e.preventDefault();
    setImgIndex((i) => (i === 0 ? business.images.length - 1 : i - 1));
  };

  const next = (e: React.MouseEvent) => {
    e.preventDefault();
    setImgIndex((i) => (i === business.images.length - 1 ? 0 : i + 1));
  };

  return (
    // <article className="overflow-hidden rounded-[1.35rem] border border-[#e7edf5] bg-white shadow-[0_12px_26px_rgba(7,17,22,0.08)] transition-shadow hover:shadow-[0_16px_32px_rgba(7,17,22,0.12)]">
    <article className="flex h-full flex-col overflow-hidden rounded-[1.35rem] border border-[#e7edf5] bg-white shadow-[0_12px_26px_rgba(7,17,22,0.08)] transition-shadow hover:shadow-[0_16px_32px_rgba(7,17,22,0.12)]">
      <div className="group relative h-44 w-full overflow-hidden rounded-[1.05rem] bg-gray-100">
        {business.images.length > 0 ? (
          <img
            src={business.images[imgIndex]}
            alt={business.name}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-gray-50">
            <Store className="h-14 w-14 text-gray-300" />
          </div>
        )}
        {business.images.length > 1 && (
          <>
            <button
              type="button"
              onClick={prev}
              className="absolute left-2.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-gray-700 shadow opacity-0 transition-opacity group-hover:opacity-100"
              aria-label={`Previous image for ${business.name}`}
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <button
              type="button"
              onClick={next}
              className="absolute right-2.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-gray-700 shadow opacity-0 transition-opacity group-hover:opacity-100"
              aria-label={`Next image for ${business.name}`}
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </>
        )}
        {business.images.length > 1 && (
          <div className="absolute bottom-2.5 left-1/2 flex -translate-x-1/2 gap-1">
            {business.images.map((_, i) => (
              <span
                key={i}
                className={`block rounded-full transition-all duration-200 ${
                  i === imgIndex ? 'h-1.5 w-3 bg-white' : 'h-1.5 w-1.5 bg-white/55'
                }`}
              />
            ))}
          </div>
        )}
        <span className="absolute left-3 top-3 max-w-[calc(100%-1.5rem)] truncate rounded-[0.9rem] bg-[#0b6b9f] px-3 py-1 text-[11px] font-bold text-white shadow-[0_8px_20px_rgba(11,107,159,0.28)]">
          {toTitleCase(business.category)}
        </span>
      </div>

      {/* <div className="flex flex-1 flex-col gap-3 pb-2 pt-3.5"> */}
      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex items-center gap-3">
          <div className="flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-[0.95rem] bg-primary-50 text-base font-bold text-primary-500">
            {showOwnerPhoto ? (
              <img
                src={ownerPhoto}
                alt=""
                className="h-full w-full object-cover"
                loading="lazy"
                onError={() => setOwnerPhotoFailed(true)}
              />
            ) : (
              <span>{ownerInitials}</span>
            )}
          </div>
          <div className="min-w-0">
            <h3 className="truncate text-lg font-semibold leading-tight text-accent-950">
              {business.name}
            </h3>
            <p className="mt-0.5 truncate text-sm font-medium text-accent-500">{business.owner}</p>
          </div>
        </div>

        <p className="line-clamp-2 text-sm font-medium leading-5 text-accent-500">
          {business.description}
        </p>

        <div className="space-y-2 text-sm font-medium text-accent-500">
          <ProtectedContactValue
            available={hasPhone}
            field="phone"
            label="Phone number"
            resourceType="marketplace-business"
            resourceId={business.businessId}
          />

          <ProtectedContactValue
            available={hasWhatsapp}
            field="whatsapp"
            label="WhatsApp number"
            resourceType="marketplace-business"
            resourceId={business.businessId}
          />

          <div className="flex items-start gap-3">
            <MapPin strokeWidth={2.6} className="mt-0.5 h-5 w-5 flex-shrink-0" />
            <span className="min-w-0 break-words">
              {[business.address, business.city, business.state].filter(Boolean).join(', ')}
            </span>
          </div>

          {hasWebsite && (
            <a
              href={getWebsiteHref(business.website!)}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-start gap-3 transition-colors hover:text-primary-600"
            >
              <Globe strokeWidth={2.6} className="mt-0.5 h-5 w-5 flex-shrink-0" />
              <span className="min-w-0 break-all">{business.website}</span>
            </a>
          )}
        </div>

        {/* {(socialLinks.length > 0 || business.socials?.instagramHashtag) && (
          <div className="flex flex-wrap items-center gap-2">
            {business.socials?.instagramHashtag && (
              
                <a href={`https://www.instagram.com/explore/tags/${encodeURIComponent(
                  business.socials.instagramHashtag.replace(/^#+/, ''),
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-full px-2.5 py-1 text-[0.72rem] font-bold leading-none text-white shadow-sm"
                style={{ background: 'linear-gradient(45deg, #f9ce34, #ee2a7b, #6228d7)' }}
              >
                #{business.socials.instagramHashtag.replace(/^#+/, '')}
              </a>
            )}
            {socialLinks.map(({ key, href, label, Icon }) => (
              
                <a key={key}
                href={getWebsiteHref(href)}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-50 text-accent-500 transition-colors hover:bg-primary-50 hover:text-primary-600"
              >
                <Icon size={22} stroke={2} />
              </a>
            ))}
          </div>
        )} */}

        {(hasHashtagRow || socialLinks.length > 0) && (
          <div className="mt-3 flex flex-col gap-2">
            {hasHashtagRow && (
              <div className="flex flex-wrap items-center gap-2">
                {instagramHref && (
                  <a
                    href={getWebsiteHref(instagramHref)}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`${business.name} on Instagram`}
                    onClick={(event) => event.stopPropagation()}
                    className="flex h-8 w-8 items-center justify-center rounded-full text-[#5f6873] transition-colors hover:bg-primary-50 hover:text-primary-600"
                  >
                    <IconBrandInstagram size={22} stroke={2} />
                  </a>
                )}
                {hashtags.map((tag) => (
                  <a
                    key={tag}
                    href={`https://www.instagram.com/explore/tags/${encodeURIComponent(tag)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(event) => event.stopPropagation()}
                    className="rounded-full px-2.5 py-1 text-[0.72rem] font-bold leading-none text-white shadow-sm"
                    style={{ background: 'linear-gradient(45deg, #f9ce34, #ee2a7b, #6228d7)' }}
                  >
                    #{tag}
                  </a>
                ))}
              </div>
            )}

            {socialLinks.length > 0 && (
              <div className="flex flex-wrap items-center gap-2">
                {socialLinks.map(({ key, href, label, Icon }) => (
                  <a
                    key={key}
                    href={getWebsiteHref(href)}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={label}
                    onClick={(event) => event.stopPropagation()}
                    className="flex h-8 w-8 items-center justify-center rounded-full text-[#5f6873] transition-colors hover:bg-primary-50 hover:text-primary-600"
                  >
                    <Icon size={22} stroke={2} />
                  </a>
                ))}
              </div>
            )}
          </div>
        )}

        <div className="mt-auto grid grid-cols-2 items-start gap-2.5 pt-2">
          <button
            type="button"
            onClick={() => onEdit(business)}
            disabled={isDeleting}
            className="flex h-10 items-center justify-center rounded-full bg-primary-500 px-4 text-sm font-semibold text-white transition-colors hover:bg-primary-600 disabled:opacity-50"
          >
            Edit
          </button>

          {showDeleteConfirm ? (
            <div className="col-span-1 grid gap-2">
              <button
                type="button"
                onClick={() => onDelete(business)}
                disabled={isDeleting}
                className="flex min-h-9 items-center justify-center rounded-full bg-red-600 px-4 text-xs font-semibold text-white transition-colors hover:bg-red-700 disabled:opacity-50"
              >
                {isDeleting ? (
                  <LoaderCircle className="h-4 w-4 animate-spin" />
                ) : (
                  <Check className="h-4 w-4" />
                )}
                Confirm
              </button>
              <button
                type="button"
                onClick={() => setShowDeleteConfirm(false)}
                disabled={isDeleting}
                className="flex min-h-9 items-center justify-center rounded-full border border-gray-200 px-4 text-xs font-semibold text-gray-500 transition-colors hover:bg-gray-50"
              >
                Cancel
              </button>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setShowDeleteConfirm(true)}
              disabled={isDeleting}
              className="flex min-h-10 items-center justify-center rounded-full border-[2px] border-red-600 bg-white px-4 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50"
            >
              Delete
            </button>
          )}
        </div>
      </div>
    </article>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function MyBusinessPage() {
  const [showPostModal, setShowPostModal] = useState(false);
  const [editBusiness, setEditBusiness] = useState<Business | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useUrlPagination();
  const { filters, setFilter, setFilters, clearFilters } = usePersistedFilters(
    'my-business-filters',
    {
      search: '',
      categoryFilter: '',
      cityFilter: '',
      stateFilter: '',
    },
  );
  const { search, categoryFilter, cityFilter, stateFilter } = filters;
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
  const currentUser = useIdentityStore((state) => state.user);

  const { data: myBusinesses = [], isLoading, refetch } = useMyBusinesses();
  const { data: locations = [] } = useLocations();
  const deleteMutation = useDeleteListing();

  const handleEdit = (business: Business) => {
    setEditBusiness(business);
    setShowPostModal(true);
  };

  const handleDelete = async (business: Business) => {
    setDeletingId(business.businessId);
    deleteMutation.mutate(business.businessId, {
      onSettled: () => {
        setDeletingId(null);
        refetch();
      },
    });
  };

  const handleCloseModal = () => {
    setShowPostModal(false);
    setEditBusiness(null);
    refetch();
  };
  const filterState = useMemo<MyBusinessFilterState>(
    () => ({
      search,
      category: categoryFilter,
      city: cityFilter,
      state: stateFilter,
    }),
    [categoryFilter, cityFilter, search, stateFilter],
  );
  const filteredBusinesses = useMemo(
    () => myBusinesses.filter((business) => matchesMyBusinessFilters(business, filterState)),
    [filterState, myBusinesses],
  );
  const facetOptions = useMemo(() => {
    const categories = Array.from(
      new Set(myBusinesses.map((business) => business.category.trim()).filter(Boolean)),
    )
      .sort((a, b) => a.localeCompare(b))
      .map((value) => ({ label: value, value }));
    return {
      categories,
    };
  }, [myBusinesses]);
  const locationHierarchy = useMemo(
    () => buildMyBusinessLocationHierarchy(myBusinesses, filterState, locations),
    [filterState, locations, myBusinesses],
  );
  const activeAdvancedFilterCount = [categoryFilter, cityFilter, stateFilter].filter(
    Boolean,
  ).length;
  const hasLocationFilter = Boolean(cityFilter || stateFilter);
  const hasActiveFilters = Boolean(search.trim() || activeAdvancedFilterCount);
  const totalPages = Math.max(1, Math.ceil(filteredBusinesses.length / MY_BUSINESSES_PER_PAGE));
  const visibleBusinesses = filteredBusinesses.slice(
    (currentPage - 1) * MY_BUSINESSES_PER_PAGE,
    currentPage * MY_BUSINESSES_PER_PAGE,
  );

  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const clearAllFilters = () => {
    clearFilters();
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    if (key === 'category') setFilter('categoryFilter', value);
  };

  const breadcrumbItems = [
    { label: 'Home', href: ROUTES.HOME },
    { label: 'Marketplace', href: MARKETPLACE_ROUTES.ROOT },
    { label: 'My Marketplace' },
  ];

  return (
    <>
      <SEO
        title="My Market"
        description="Manage your business listings on the Alumni Marketplace."
      />
      <Breadcrumbs items={breadcrumbItems} />

      <section className="section bg-[#F8F8F7]">
        <div className="container-custom">
          {/* Header */}
          <div className="flex items-start justify-between mb-8">
            <div>
              <h1 className="type-section-title mb-1">My Market</h1>
              <p className="text-gray-500 text-sm">
                Manage and update your business listings in the Alumni Marketplace.
              </p>
            </div>
            {myBusinesses.length > 0 && (
              <button
                type="button"
                onClick={() => setShowPostModal(true)}
                className="flex-shrink-0 flex items-center gap-1.5 bg-primary-500 hover:bg-primary-600 text-white text-xs md:text-base font-semibold px-3 md:px-8 py-2.5 rounded-3xl transition-colors"
              >
                <span className="hidden md:inline">Add New Business</span>
                <Plus className="h-4 w-4" />
              </button>
            )}
          </div>

          {!isLoading && myBusinesses.length > 0 && (
            <>
              <div className="mb-5 flex w-full items-center gap-3">
                <div className="min-w-0 flex-1 sm:max-w-xl">
                  <SearchInput
                    value={search}
                    onValueChange={(value) => setFilter('search', value)}
                    placeholder="Search your businesses"
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
                  title="Refine your businesses"
                  description="Find your listings by business category or location."
                  customContent={
                    <HierarchicalLocationFilter
                      label="Location"
                      placeholder="All locations"
                      value={{ state: stateFilter, city: cityFilter }}
                      options={locationHierarchy}
                      onChange={(selection) => {
                        setFilters({
                          stateFilter: selection.state,
                          cityFilter: selection.city,
                        });
                      }}
                    />
                  }
                  fields={[
                    {
                      key: 'category',
                      kind: 'select',
                      label: 'Category',
                      value: categoryFilter,
                      placeholder: 'All categories',
                      options: facetOptions.categories,
                    },
                  ]}
                  onFieldChange={handleAdvancedFilterChange}
                  onReset={clearAllFilters}
                  hasActiveFilters={activeAdvancedFilterCount > 0 || hasLocationFilter}
                />
              )}

              {hasActiveFilters && (
                <div className="mb-6 mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                  <span>
                    Showing {filteredBusinesses.length} of {myBusinesses.length} businesses
                  </span>
                  <ClearFiltersButton onClick={clearAllFilters} label="Clear all filters" />
                </div>
              )}
            </>
          )}

          {/* Grid */}
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {Array.from({ length: 3 }).map((_, i) => (
                <MyBusinessCardSkeleton key={i} />
              ))}
            </div>
          ) : myBusinesses.length > 0 ? (
            filteredBusinesses.length > 0 ? (
              <>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {visibleBusinesses.map((business) => (
                    <MyBusinessCard
                      key={business.businessId}
                      business={business}
                      ownerPhoto={business.ownerPhoto ?? currentUser?.photo}
                      onEdit={handleEdit}
                      onDelete={handleDelete}
                      isDeleting={deletingId === business.businessId}
                    />
                  ))}
                </div>
                <Pagination
                  currentPage={currentPage}
                  totalPages={totalPages}
                  prevIcon={ChevronLeft}
                  nextIcon={ChevronRight}
                  onPageChange={handlePageChange}
                />
              </>
            ) : (
              <EmptyState
                icon={SearchX}
                title="No matching businesses"
                description="Try changing your search or filters."
                actionLabel="Clear filters"
                onAction={clearAllFilters}
              />
            )
          ) : (
            <EmptyState
              icon={Store}
              title="You have no businesses posted yet"
              description="Add your business to the Alumni Marketplace and let other members find and support you."
              actionLabel="Add Your Business"
              onAction={() => setShowPostModal(true)}
            />
          )}
        </div>
      </section>

      <PostBusinessModal
        isOpen={showPostModal}
        onClose={handleCloseModal}
        editData={editBusiness}
      />
    </>
  );
}
