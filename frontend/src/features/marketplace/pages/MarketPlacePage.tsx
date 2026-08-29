// features/marketplace/pages/MarketPlacePage.tsx

import { ComponentType, useEffect, useMemo, useState } from 'react';
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  CircleAlert,
  CircleX,
  Globe,
  Mail,
  MapPin,
  Phone,
  Plus,
  Search,
  Store,
} from 'lucide-react';

import {
  IconBrandFacebook,
  IconBrandInstagram,
  IconBrandLinkedin,
  IconBrandTiktok,
  IconBrandWhatsapp,
  IconBrandX,
} from '@tabler/icons-react';
import { useNavigate } from 'react-router-dom';
import { normalizeLegacyHashtags, parseHashtags } from '../utils/hashtags';
import { SEO } from '@/shared/common/SEO';
import { Button } from '@/shared/components/ui/Button';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { PostBusinessModal } from '../components/PostYourBusinessModal';
import EmptyState from '@/shared/components/ui/EmptyState';
import {
  useMarketplace,
  useMarketplaceCategories,
} from '@/features/marketplace/hooks/useMarketplace';
import type { Business } from '../types/marketplace.types';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import { useRequireSignIn } from '@/features/authentication/hooks/useRequireSignIn';
import { MARKETPLACE_ROUTES } from '../routes';
import { resolveProfilePhoto } from '@/features/user/utils/profileUtils';

const ITEMS_PER_PAGE = 9;
const DEMO_PRIORITY_BUSINESS_OWNERS = ['lolu jumat', 'fcd fcd', 'felix ohemu'];
const DEFAULT_MARKETPLACE_DRAFT_MESSAGE = (businessName: string) =>
  `Hi, I'm interested in ${businessName}. I'd like to know more about your services.`;

const marketplaceSearchInputClassName =
  '!h-full !border-0 !bg-transparent !px-0 !pl-8 text-[clamp(0.95rem,0.9vw,1.05rem)] font-normal text-[#111820] placeholder:text-[#858585] !shadow-none !ring-0 focus:!ring-0 focus-visible:!ring-0';

const marketplaceFilterSelectClassName = [
  '!w-full',
  '[&_.select-input__control-wrap]:w-full',
  '[&_.select-input__control-wrap>button]:!min-h-full',
  '[&_.select-input__control-wrap>button]:!rounded-full',
  '[&_.select-input__control-wrap>button]:!border-0',
  '[&_.select-input__control-wrap>button]:!bg-transparent',
  '[&_.select-input__control-wrap>button]:!px-4',
  '[&_.select-input__control-wrap>button]:!text-[clamp(0.9rem,0.82vw,0.98rem)]',
  '[&_.select-input__control-wrap>button]:!font-semibold',
  '[&_.select-input__control-wrap>button]:!text-[#838383]',
  '[&_.select-input__control-wrap>button]:!shadow-none',
  '[&_.select-input__control-wrap>button]:focus:!ring-0',
  '[&_.select-input__control-wrap>button]:focus-visible:!ring-0',
  '[&_.select-input__icon]:!right-3',
  '[&_.select-input__icon]:!h-[1.1rem]',
  '[&_.select-input__icon]:!w-[1.1rem]',
  '[&_.select-input__icon]:!text-[#777777]',
].join(' ');

const marketplaceGridClassName =
  'grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8 xl:grid-cols-[repeat(auto-fit,minmax(min(100%,19.25rem),1fr))] xl:gap-x-[1.5rem] xl:gap-y-[2.75rem]';

type SocialLinkEntry = {
  key: string;
  href: string;
  label: string;
  Icon: ComponentType<{ className?: string; size?: number; stroke?: number }>;
};

// ─── Skeleton ─────────────────────────────────────────────────────────────────
function BusinessCardSkeleton() {
  return (
    <div
      className="flex min-w-0 animate-pulse flex-col overflow-hidden rounded-[1.1rem] bg-white shadow-[0_1px_0_rgba(7,17,22,0.02)] sm:rounded-[1.45rem]"
      aria-hidden="true"
    >
      <div className="aspect-[448/292] w-full rounded-[1.35rem] bg-[#e6e8eb]" />
      <div className="flex flex-1 flex-col px-4 pb-4 pt-[1.1rem] sm:px-[1.2rem] sm:pb-[1.2rem] sm:pt-[1.45rem]">
        <div className="mb-[1.05rem] flex min-w-0 items-center gap-4">
          <div className="h-[3.75rem] w-[3.75rem] shrink-0 rounded-[0.8rem] bg-[#e6e8eb] sm:h-[4.35rem] sm:w-[4.35rem] sm:rounded-[0.9rem] 2xl:h-[4.65rem] 2xl:w-[4.65rem]" />
          <div className="min-w-0 flex-1">
            <div className="h-6 w-[78%] rounded-full bg-[#e6e8eb]" />
            <div className="mt-2 h-4 w-[58%] rounded-full bg-[#e6e8eb]" />
          </div>
        </div>
        <div className="h-[0.9rem] w-full rounded-full bg-[#e6e8eb]" />
        <div className="mt-3 h-[0.9rem] w-[92%] rounded-full bg-[#e6e8eb]" />
        <div className="mt-3 h-[0.9rem] w-[76%] rounded-full bg-[#e6e8eb]" />
        <div className="mt-4 flex flex-col gap-2.5">
          <div className="h-[0.9rem] w-[66%] rounded-full bg-[#e6e8eb]" />
          <div className="h-[0.9rem] w-[66%] rounded-full bg-[#e6e8eb]" />
          <div className="h-[0.9rem] w-[66%] rounded-full bg-[#e6e8eb]" />
        </div>
      </div>
    </div>
  );
}

function formatCategoryLabel(category: string) {
  return category
    .split(/[\s_-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

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

function getDemoOwnerPriority(ownerName: string) {
  const normalizedOwnerName = ownerName.trim().toLowerCase();
  const priorityIndex = DEMO_PRIORITY_BUSINESS_OWNERS.indexOf(normalizedOwnerName);

  return priorityIndex === -1 ? Number.POSITIVE_INFINITY : priorityIndex;
}

function sortBusinessesForDemo(a: Business, b: Business) {
  const aPriority = getDemoOwnerPriority(a.owner);
  const bPriority = getDemoOwnerPriority(b.owner);

  if (aPriority === bPriority) return 0;
  return aPriority - bPriority;
}


// ─── Business Card ────────────────────────────────────────────────────────────
function BusinessCard({
  business,
  currentUserMemberId,
  ownerPhoto,
  onMessageClick,
  isMessagePending,
  detailHref,
}: {
  business: Business;
  currentUserMemberId?: string;
  ownerPhoto?: string;
  onMessageClick: (business: Business) => void;
  isMessagePending: boolean;
  detailHref: string;
}) {
  const [imgIndex, setImgIndex] = useState(0);
  const [ownerPhotoFailed, setOwnerPhotoFailed] = useState(false);
  const navigate = useNavigate();
  const isOwnBusiness = business.ownerId === currentUserMemberId;
  const ownerInitials = getOwnerInitials(business.owner);
  const showOwnerPhoto = isRealProfilePhoto(ownerPhoto) && !ownerPhotoFailed;
  const hasPhone = Boolean(business.phone.trim());
  const hasEmail = Boolean(business.email?.trim());
  const hasWebsite = Boolean(business.website?.trim());
  const hasWhatsapp = Boolean(business.whatsapp?.trim());


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
    e.stopPropagation();
    setImgIndex((i) => (i === 0 ? business.images.length - 1 : i - 1));
  };

  const next = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setImgIndex((i) => (i === business.images.length - 1 ? 0 : i + 1));
  };

  const openDetails = () => {
    navigate(detailHref);
  };

  const handleCardKeyDown = (event: React.KeyboardEvent<HTMLElement>) => {
    if (event.target !== event.currentTarget) return;

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openDetails();
    }
  };

  return (
    <article
      role="link"
      tabIndex={0}
      aria-label={`View ${business.name}`}
      onClick={openDetails}
      onKeyDown={handleCardKeyDown}
      className="group/card flex min-w-0 cursor-pointer flex-col overflow-hidden rounded-[1.1rem] bg-white shadow-[0_1px_0_rgba(7,17,22,0.02)] transition-shadow hover:shadow-[0_14px_34px_rgba(7,17,22,0.08)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 sm:rounded-[1.45rem]"
    >
      {/* Image carousel */}
      <div className="relative aspect-[448/292] w-full overflow-hidden rounded-[1.35rem] bg-[#d9dde2]">
        {business.images.length > 0 ? (
          <img
            src={business.images[imgIndex]}
            alt={business.name}
            className="block h-full w-full object-cover transition-transform duration-300 ease-out group-hover/card:scale-[1.02]"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-[#edf0f2]">
            <Store className="h-14 w-14 text-[#a7aeb6]" />
          </div>
        )}
        {business.images.length > 1 && (
          <>
            <button
              type="button"
              aria-label={`Previous image for ${business.name}`}
              onClick={prev}
              className="absolute left-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#29313a] opacity-0 transition-[opacity,background-color] duration-200 hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 group-hover/card:opacity-100 sm:flex"
            >
              <ChevronLeft className="h-6 w-6" />
            </button>
            <button
              type="button"
              aria-label={`Next image for ${business.name}`}
              onClick={next}
              className="absolute right-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#29313a] opacity-0 transition-[opacity,background-color] duration-200 hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 group-hover/card:opacity-100 sm:flex"
            >
              <ChevronRight className="h-6 w-6" />
            </button>
          </>
        )}
        <span className="absolute left-[1.35rem] top-[1.45rem] max-w-[calc(100%-2.7rem)] truncate rounded-[0.95rem] bg-primary-500/60 px-4 py-[0.43rem] text-[clamp(0.86rem,0.95vw,1.28rem)] font-extrabold leading-[1.15] text-white">
          {formatCategoryLabel(business.category)}
        </span>
      </div>

      {/* Info */}
      <div className="flex flex-1 flex-col px-4 pb-4 pt-[1.1rem] sm:px-[1.2rem] sm:pb-[1.2rem] sm:pt-[1.45rem]">
        <div className="mb-[1.05rem] flex min-w-0 items-center gap-4">
          <div className="flex h-[2.75rem] w-[2.75rem] shrink-0 items-center justify-center sm:h-[3rem] sm:w-[3rem] 2xl:h-[4.65rem] 2xl:w-[4.65rem]">
            <div
              className="flex h-full w-full items-center justify-center overflow-hidden rounded-[0.8rem] bg-primary-50 text-[1rem] font-extrabold text-primary-400 sm:rounded-[0.9rem] sm:text-[1.25rem] 2xl:text-[1.35rem]"
              aria-hidden="true"
            >
              {showOwnerPhoto ? (
                <img
                  src={ownerPhoto}
                  alt=""
                  loading="lazy"
                  onError={() => setOwnerPhotoFailed(true)}
                  className="h-full w-full object-cover"
                />
              ) : (
                <span>{ownerInitials}</span>
              )}
            </div>
          </div>
          <div className="min-w-0">
            <h3 className="truncate text-[clamp(1rem,0.92vw,1.08rem)] font-semibold leading-[1.12] text-[#071116]">
              {business.name}
            </h3>
            <p className="mt-[0.3rem] truncate text-[clamp(0.84rem,0.8vw,0.94rem)] font-medium leading-[1.2] text-[#555c68]">
              {business.owner}
            </p>
          </div>
        </div>

        <p className="overflow-hidden text-[clamp(0.88rem,0.82vw,0.98rem)] font-medium leading-[1.28] text-[#565e69] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:3]">
          {business.description}
        </p>

        <div className="mt-[0.85rem] space-y-2 text-[clamp(0.84rem,0.8vw,0.94rem)] font-medium leading-[1.25] text-[#555c68]">
          {hasPhone && (
            <a
              href={`tel:${business.phone.replace(/\s+/g, '')}`}
              className="flex items-start gap-2.5 no-underline transition-colors hover:text-primary-600"
              onClick={(event) => event.stopPropagation()}
            >
              <Phone
                strokeWidth={2.6}
                className="mt-0.5 h-[1.05rem] w-[1.05rem] shrink-0 text-[#5f6873]"
              />
              <span className="min-w-0 break-words">{business.phone}</span>
            </a>
          )}

          {hasEmail && (
            <a
              href={`mailto:${business.email}`}
              className="flex items-start gap-2.5 no-underline transition-colors hover:text-primary-600"
              onClick={(event) => event.stopPropagation()}
            >
              <Mail
                strokeWidth={2.6}
                className="mt-0.5 h-[1.05rem] w-[1.05rem] shrink-0 text-[#5f6873]"
              />
              <span className="min-w-0 break-all">{business.email}</span>
            </a>
          )}

          <div className="flex items-start gap-2.5">
            <MapPin
              strokeWidth={2.6}
              className="mt-0.5 h-[1.05rem] w-[1.05rem] shrink-0 text-[#5f6873]"
            />
            <span className="min-w-0 break-words">{business.location}</span>
          </div>

          {hasWebsite && (
            <a
              href={getWebsiteHref(business.website!)}
              target="_blank"
              rel="noopener noreferrer"
              className="flex min-w-0 items-start gap-2.5 no-underline transition-colors hover:text-primary-600"
              onClick={(event) => event.stopPropagation()}
            >
              <Globe
                strokeWidth={2.6}
                className="mt-0.5 h-[1.05rem] w-[1.05rem] shrink-0 text-[#5f6873]"
              />
              <span className="min-w-0 break-all">{business.website}</span>
            </a>
          )}

        </div>

        {/* {(socialLinks.length > 0 || business.socials?.instagramHashtag) && (
          // <div className="mt-3 flex flex-wrap items-center gap-2" style={{ marginLeft: "-5px" }}>
          <div className="mt-3 flex flex-wrap items-center gap-2">

            {business.socials?.instagramHashtag && (
              <a
               href={`https://www.instagram.com/explore/tags/${encodeURIComponent(
                  business.socials.instagramHashtag.replace(/^#+/, ''),
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={(event) => event.stopPropagation()}
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
                onClick={(event) => event.stopPropagation()}
                // className="flex h-8 w-8 items-center justify-center rounded-full bg-[#f1f3f5] text-[#5f6873] transition-colors hover:bg-primary-50 hover:text-primary-600"
                className="flex h-8 w-8 items-center justify-center rounded-full text-[#5f6873] transition-colors hover:bg-primary-50 hover:text-primary-600"
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
                  
                    <a href={getWebsiteHref(instagramHref)}
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
                  
                    <a key={tag}
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
                  
                    <a key={key}
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



        <div
          className="mt-auto flex items-center gap-3 pt-3 max-sm:flex-wrap"
          aria-label={`Contact ${business.name}`}
        >
          <Button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              onMessageClick(business);
            }}
            disabled={isOwnBusiness || isMessagePending}
            title={isOwnBusiness ? 'You cannot message yourself' : ''}
            loading={isMessagePending}
            className="min-h-10 min-w-[9rem] max-w-full rounded-full border-0 px-2 text-[0.88rem] font-bold leading-none tracking-normal shadow-none focus-visible:ring-4 focus-visible:ring-primary-200"
          >
            <span>{isMessagePending ? 'Opening...' : 'Send Message'}</span>
          </Button>


          {hasWhatsapp && (

            <a href={`https://wa.me/${business.whatsapp!.replace(/\D/g, '')}`}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`Message ${business.name} on WhatsApp`}
              onClick={(event) => event.stopPropagation()}
              className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white transition-transform hover:brightness-95 active:translate-y-px focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
            >
              <IconBrandWhatsapp size={20} stroke={2} />
            </a>
          )}
        </div>
      </div>
    </article>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function MarketPlacePage() {
  const [searchTerm, setSearchTerm] = useState('');
  const [category, setCategory] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [showPostModal, setShowPostModal] = useState(false);
  const [pendingBusinessId, setPendingBusinessId] = useState<string | null>(null);
  const currentUser = useIdentityStore((state) => state.user);
  const requireSignIn = useRequireSignIn();
  const { startDirectConversation, isPending: isStartingConversation } =
    useStartDirectConversation();

  const { data: businesses = [], isLoading, error } = useMarketplace();
  const { data: categoriesList = [] } = useMarketplaceCategories();
  const { data: alumni = [] } = useAlumni({ action_type: 'approved' });
  const isSignedIn = Boolean(currentUser?.memberId);

  const alumniByMemberId = useMemo(() => {
    const entries = new Map<string, (typeof alumni)[number]>();

    alumni.forEach((entry) => {
      entries.set(String(entry.id), entry);
      entries.set(String(entry.memberId), entry);
    });

    return entries;
  }, [alumni]);

  const ownerPhotoById = useMemo(() => {
    const photos = new Map<string, string | null>();

    alumni.forEach((entry) => {
      const displayPhoto = resolveProfilePhoto({
        photoUrl: entry.photo,
        privacy: entry.privacy,
        isOwner: entry.memberId === currentUser?.memberId,
        isSignedIn,
      });
      const photo = isRealProfilePhoto(displayPhoto) ? (displayPhoto ?? null) : null;

      photos.set(String(entry.id), photo);
      photos.set(String(entry.memberId), photo);
    });

    return photos;
  }, [alumni, currentUser?.memberId, isSignedIn]);

  const filtered = useMemo(() => {
    const q = searchTerm.trim().toLowerCase();
    return businesses
      .filter((b) => {
        const matchesSearch =
          !q || b.name.toLowerCase().includes(q) || b.description.toLowerCase().includes(q);
        const matchesCategory = !category || b.category === category;
        return matchesSearch && matchesCategory;
      })
      .sort(sortBusinessesForDemo);
  }, [businesses, searchTerm, category]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  const visible = filtered.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE);
  const categoryOptions = useMemo(
    () => categoriesList.map((cat) => ({ label: formatCategoryLabel(cat), value: cat })),
    [categoriesList],
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const handlePostBusinessClick = () => {
    if (!currentUser) {
      requireSignIn({
        message: 'Please sign in to post your business',
        from: MARKETPLACE_ROUTES.MY_BUSINESS,
      });
      return;
    }
    setShowPostModal(true);
  };

  const handleFilterChange = (setter: (v: string) => void) => (value: string) => {
    setter(value);
    setCurrentPage(1);
  };

  async function handleStartBusinessConversation(business: Business) {
    setPendingBusinessId(business.businessId);
    const ownerEntry = alumniByMemberId.get(String(business.ownerId));

    await startDirectConversation({
      participantMemberId: business.ownerId,
      topic: `Marketplace enquiry about ${business.name}`,
      draftMessage:
        business.messagePrompt?.trim() || DEFAULT_MARKETPLACE_DRAFT_MESSAGE(business.name),
      marketplaceBusinessId: business.businessId,
      recipientProfile: {
        fullName: business.owner,
        avatar: ownerPhotoById.get(String(business.ownerId)) ?? undefined,
        photoVisibility: ownerEntry?.privacy?.photo,
        headline: `Owner of ${business.name}`,
        location: business.location,
        profileHref: `/alumni/profiles/${business.ownerId}`,
      },
    });
    setPendingBusinessId((current) => (current === business.businessId ? null : current));
  }

  return (
    <>
      <SEO
        title="Marketplace"
        description="Discover and support businesses owned by Our Members."
      />

      <main className="min-h-full bg-[#F8F8F7] text-[#071116]">
        <section className="page-inline-padding w-full max-w-[100vw] pb-16 pt-8 lg:pb-20 lg:pt-8 xl:pb-24 xl:pt-8">
          <div className="mb-10 flex flex-col gap-6 lg:mb-[3.65rem] lg:flex-row lg:items-start lg:justify-between lg:gap-8">
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-4 lg:block">
                <h1 className="type-section-title text-[#071116]">Marketplace</h1>
                <Button
                  type="button"
                  size="sm"
                  onClick={handlePostBusinessClick}
                  leftIcon={Plus}
                  className="mt-0 inline-flex min-h-10 w-10 shrink-0 items-center justify-center self-start rounded-full px-0 text-sm font-semibold leading-none tracking-normal shadow-none transition-transform hover:bg-primary-600 active:translate-y-px focus-visible:ring-4 focus-visible:ring-primary-200 [&>svg]:h-5 [&>svg]:w-5 lg:hidden"
                  aria-label="Post Your Business"
                />
              </div>
              <p className="mt-1 max-w-3xl break-words text-[clamp(0.98rem,1.37vw,1.8rem)] font-medium leading-[1.2] text-[#58606b]">
                <span className="block sm:inline">Discover and support businesses owned by</span>
                <span className="block sm:inline"> our members</span>
              </p>
            </div>

            <Button
              type="button"
              size="sm"
              onClick={handlePostBusinessClick}
              rightIcon={Plus}
              className="mt-0 hidden min-h-10 w-auto justify-center self-end rounded-full pl-3 pr-3 text-sm font-semibold leading-none tracking-normal shadow-none transition-transform hover:bg-primary-600 active:translate-y-px focus-visible:ring-4 focus-visible:ring-primary-200 [&>svg]:h-5 [&>svg]:w-5 sm:min-w-[14rem] sm:pl-5 sm:pr-6 sm:text-base sm:[&>svg]:h-5 sm:[&>svg]:w-5 lg:mt-3 lg:inline-flex"
            >
              <span>Post Your Business</span>
            </Button>
          </div>

          <div className="mb-10 flex flex-col gap-4 lg:mb-[2.15rem] lg:flex-row lg:items-center lg:justify-between">
            {/* <div className="flex h-[3.1rem] w-full items-center gap-2 rounded-full bg-white px-[0.95rem] text-[#858585] lg:h-12 lg:max-w-[28rem]"> */}
            <div className="flex h-[3.1rem] w-full items-center gap-2 rounded-full text-[#858585] lg:h-12 lg:max-w-[28rem]">
              <label htmlFor="marketplace-search" className="sr-only">
                Search marketplace businesses
              </label>
              {/* <SearchInput
                id="marketplace-search"
                value={searchTerm}
                onValueChange={handleFilterChange(setSearchTerm)}
                placeholder="Search here"
                showClearButton={true}
                className="w-full"
                containerClassName="h-full"
                inputClassName={marketplaceSearchInputClassName}
                iconClassName="left-0 h-5 w-5 text-[#858585]"
                searchIcon={Search}
                clearIcon={CircleX}
                errorIcon={CircleAlert}
              /> */}
              <SearchInput
                id="marketplace-search"
                value={searchTerm}
                onValueChange={handleFilterChange(setSearchTerm)}
                placeholder="Search here"
                showClearButton={true}
                className="w-full"
                containerClassName="h-full"
                inputClassName="!h-10 !py-0"
              />
            </div>

            <FilterDropdown
              value={category}
              onChange={handleFilterChange(setCategory)}
              options={categoryOptions}
              placeholder="Filter by Category"
              className="h-[3.1rem] w-full sm:!w-full lg:h-12 lg:!w-[12.5rem] lg:!min-w-[12.5rem]"
              selectClassName={marketplaceFilterSelectClassName}
              clearIcon={CircleX}
              chevronDownIcon={ChevronDown}
              chevronUpIcon={ChevronUp}
            />
          </div>

          {/* Error State */}
          {error && (
            <div className="flex min-h-64 flex-col items-center justify-center text-center text-gray-500">
              <CircleAlert className="mb-4 h-16 w-16 text-red-400" />
              <p>Failed to load businesses. Please try again later.</p>
            </div>
          )}

          {/* Grid */}
          {isLoading ? (
            <div className={marketplaceGridClassName}>
              {Array.from({ length: ITEMS_PER_PAGE }).map((_, i) => (
                <BusinessCardSkeleton key={i} />
              ))}
            </div>
          ) : !error && visible.length > 0 ? (
            <div className={marketplaceGridClassName}>
              {visible.map((business) => (
                <BusinessCard
                  key={business.businessId}
                  business={business}
                  currentUserMemberId={currentUser?.memberId}
                  ownerPhoto={
                    ownerPhotoById.has(business.ownerId)
                      ? (ownerPhotoById.get(business.ownerId) ?? undefined)
                      : business.ownerPhoto
                  }
                  onMessageClick={handleStartBusinessConversation}
                  isMessagePending={
                    isStartingConversation && pendingBusinessId === business.businessId
                  }
                  detailHref={MARKETPLACE_ROUTES.DETAIL(business.businessId)}
                />
              ))}
            </div>
          ) : !error && visible.length === 0 ? (
            <EmptyState
              icon={Store}
              title="No businesses found"
              description="Try adjusting your search or be the first to list your business."
              actionLabel="Post Your Business"
              onAction={handlePostBusinessClick}
            />
          ) : null}

          {/* Load More */}
          {!isLoading && !error && filtered.length > 0 ? (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              prevIcon={ChevronLeft}
              nextIcon={ChevronRight}
              onPageChange={(page) => {
                setCurrentPage(page);
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
            />
          ) : null}
        </section>
      </main>

      <PostBusinessModal isOpen={showPostModal} onClose={() => setShowPostModal(false)} />
    </>
  );
}
