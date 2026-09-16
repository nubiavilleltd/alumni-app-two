import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import {
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Globe,
  Hash,
  MapPin,
  MessageCircle,
  SearchX,
  Share2,
  Store,
  User,
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
import { Button } from '@/shared/components/ui/Button';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ProtectedContactValue } from '@/shared/components/ui/ProtectedContactValue';
import { toast } from '@/shared/components/ui/Toast';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { normalizeLegacyHashtags, parseHashtags } from '../utils/hashtags';
import { useMarketplaceListing } from '../hooks/useMarketplace';
import { MARKETPLACE_ROUTES } from '../routes';
import type { Business } from '../types/marketplace.types';

type SocialLinkEntry = {
  key: string;
  href: string;
  label: string;
  Icon: typeof IconBrandInstagram;
};

const DEFAULT_MARKETPLACE_DRAFT_MESSAGE = (businessName: string) =>
  `Hi, I'm interested in ${businessName}. I'd like to know more about your services.`;

function formatCategoryLabel(category: string) {
  return category
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ');
}

function getWebsiteHref(value: string) {
  return /^https?:\/\//i.test(value) ? value : `https://${value}`;
}

function getOwnerInitials(ownerName: string) {
  const parts = ownerName.trim().split(/\s+/).filter(Boolean);

  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }

  return parts[0]?.slice(0, 2).toUpperCase() || '?';
}

function buildSocialLinks(business: Business): SocialLinkEntry[] {
  return (
    [
      business.socials?.instagram && {
        key: 'instagram',
        href: business.socials.instagram,
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
}

function BusinessDetailSkeleton() {
  return (
    <main className="min-h-screen bg-[#F8F8F7] py-8">
      <div className="container-custom animate-pulse space-y-6">
        <div className="h-6 w-64 rounded bg-gray-200" />
        <div className="h-[420px] rounded-3xl bg-gray-200" />
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="h-80 rounded-3xl bg-white" />
          <div className="h-80 rounded-3xl bg-white" />
        </div>
      </div>
    </main>
  );
}

export default function BusinessDetailPage() {
  const { id = '' } = useParams<{ id: string }>();
  const { data: business, isLoading, isError, error, refetch } = useMarketplaceListing(id);
  const currentUser = useIdentityStore((state) => state.user);
  const { startDirectConversation, isPending: isStartingConversation } =
    useStartDirectConversation();
  const [activeImage, setActiveImage] = useState(0);
  const [isMessagePending, setIsMessagePending] = useState(false);

  const images = business?.images ?? [];
  const activeImageSrc = images[activeImage];
  const isOwnBusiness = Boolean(
    business && currentUser?.memberId && business.ownerId === currentUser.memberId,
  );
  const socialLinks = useMemo(() => (business ? buildSocialLinks(business) : []), [business]);
  const hashtags = useMemo(
    () =>
      business ? parseHashtags(normalizeLegacyHashtags(business.socials?.instagramHashtag)) : [],
    [business],
  );

  useEffect(() => {
    setActiveImage(0);
  }, [business?.businessId]);

  const goToPreviousImage = () => {
    setActiveImage((current) => (current === 0 ? images.length - 1 : current - 1));
  };

  const goToNextImage = () => {
    setActiveImage((current) => (current === images.length - 1 ? 0 : current + 1));
  };

  const handleShare = async () => {
    if (typeof window === 'undefined') return;

    try {
      await navigator.clipboard.writeText(window.location.href);
      toast.success('Business link copied.');
    } catch {
      toast.info('Copy this page link from your browser address bar.');
    }
  };

  const handleStartBusinessConversation = async () => {
    if (!business || isOwnBusiness) return;

    setIsMessagePending(true);
    await startDirectConversation({
      participantMemberId: business.ownerId,
      topic: `Marketplace enquiry about ${business.name}`,
      draftMessage:
        business.messagePrompt?.trim() || DEFAULT_MARKETPLACE_DRAFT_MESSAGE(business.name),
      marketplaceBusinessId: business.businessId,
      recipientProfile: {
        fullName: business.owner,
        avatar: business.ownerPhoto,
        headline: `Owner of ${business.name}`,
        location: [business.address, business.city, business.state].filter(Boolean).join(', '),
        profileHref: `/alumni/profiles/${business.ownerId}`,
      },
    });
    setIsMessagePending(false);
  };

  if (isLoading) return <BusinessDetailSkeleton />;

  if (isError) {
    return (
      <main className="min-h-screen bg-[#F8F8F7] py-10">
        <SEO title="Business Details" description="View marketplace business details." />
        <EmptyState
          icon={SearchX}
          title="We couldn't load this business"
          description={error instanceof Error ? error.message : 'Please try again.'}
          actionLabel="Try Again"
          onAction={() => {
            void refetch();
          }}
        />
      </main>
    );
  }

  if (!business) {
    return (
      <main className="min-h-screen bg-[#F8F8F7] py-10">
        <SEO title="Business Not Found" description="The requested business could not be found." />
        <EmptyState
          icon={Store}
          title="Business not found"
          description="This business may have been removed or is no longer available."
          actionLabel="Back to Marketplace"
          actionHref={MARKETPLACE_ROUTES.ROOT}
        />
      </main>
    );
  }

  const breadcrumbItems = [
    { label: 'Home', href: '/' },
    { label: 'Marketplace', href: MARKETPLACE_ROUTES.ROOT },
    { label: business.name },
  ];

  return (
    <>
      <SEO title={business.name} description={business.description} />
      <Breadcrumbs items={breadcrumbItems} />

      <main className="min-h-screen bg-[#F8F8F7] text-[#071116]">
        <section className="container-custom py-6 sm:py-8 lg:py-10">
          <div className="mb-6 overflow-hidden rounded-3xl bg-gray-100 shadow-sm">
            {activeImageSrc ? (
              <div className="relative aspect-[16/10] sm:aspect-[16/8] lg:h-[430px] lg:aspect-auto">
                <img
                  src={activeImageSrc}
                  alt={business.name}
                  className="h-full w-full object-cover"
                />
                {images.length > 1 ? (
                  <>
                    <button
                      type="button"
                      aria-label="Previous business image"
                      onClick={goToPreviousImage}
                      className="absolute left-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#29313a] transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                    >
                      <ChevronLeft className="h-6 w-6" />
                    </button>
                    <button
                      type="button"
                      aria-label="Next business image"
                      onClick={goToNextImage}
                      className="absolute right-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#29313a] transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                    >
                      <ChevronRight className="h-6 w-6" />
                    </button>
                  </>
                ) : null}
              </div>
            ) : (
              <div className="flex aspect-[16/10] items-center justify-center sm:aspect-[16/8] lg:h-[430px] lg:aspect-auto">
                <Store className="h-20 w-20 text-gray-300" />
              </div>
            )}
          </div>

          {images.length > 1 ? (
            <div className="mb-8 flex gap-2 overflow-x-auto pb-1">
              {images.map((image, index) => (
                <button
                  key={`${image}-${index}`}
                  type="button"
                  onClick={() => setActiveImage(index)}
                  className={`h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border-2 transition-all ${
                    activeImage === index
                      ? 'border-primary-500 ring-2 ring-primary-200'
                      : 'border-transparent hover:border-gray-300'
                  }`}
                  aria-label={`Show image ${index + 1}`}
                >
                  <img src={image} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          ) : null}

          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <article className="min-w-0 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 sm:p-6">
              <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="inline-flex rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-600">
                  {formatCategoryLabel(business.category)}
                </span>
              </div>

              <h1 className="text-2xl font-bold leading-tight text-gray-900 sm:text-3xl md:text-4xl">
                {business.name}
              </h1>

              <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium text-gray-600">
                <span className="inline-flex items-center gap-2">
                  <User className="h-4 w-4" />
                  {business.owner}
                </span>
                {business.address || business.city || business.state ? (
                  <span className="inline-flex items-center gap-2">
                    <MapPin className="h-4 w-4" />
                    {[business.address, business.city, business.state].filter(Boolean).join(', ')}
                  </span>
                ) : null}
              </div>

              <section className="mt-8">
                <h2 className="mb-3 text-lg font-bold text-gray-900">About this business</h2>
                <p className="whitespace-pre-wrap text-sm leading-7 text-gray-600 sm:text-base">
                  {business.description}
                </p>
              </section>
            </article>

            <aside className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 sm:p-6">
              <div className="mb-5 flex items-center gap-3">
                <div className="flex h-14 w-14 flex-none items-center justify-center overflow-hidden rounded-2xl bg-primary-50 text-base font-extrabold text-primary-500">
                  {business.ownerPhoto ? (
                    <img src={business.ownerPhoto} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <span>{getOwnerInitials(business.owner)}</span>
                  )}
                </div>
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-gray-900">{business.owner}</p>
                  <p className="truncate text-xs font-medium text-gray-500">
                    Owner of {business.name}
                  </p>
                </div>
              </div>

              <div className="space-y-3 text-sm font-medium text-gray-600">
                <ProtectedContactValue
                  value={business.phone}
                  field="phone"
                  label="Phone number"
                  resourceType="marketplace-business"
                  resourceId={business.businessId}
                />
                <ProtectedContactValue
                  value={business.email}
                  field="email"
                  label="Email address"
                  resourceType="marketplace-business"
                  resourceId={business.businessId}
                />
                <ProtectedContactValue
                  value={business.whatsapp}
                  field="whatsapp"
                  label="WhatsApp number"
                  resourceType="marketplace-business"
                  resourceId={business.businessId}
                />

                {business.website ? (
                  <a
                    href={getWebsiteHref(business.website)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-start gap-2.5 transition-colors hover:text-primary-600"
                  >
                    <Globe className="mt-0.5 h-4 w-4 flex-none" />
                    <span className="min-w-0 break-all">{business.website}</span>
                    <ExternalLink className="mt-0.5 h-3.5 w-3.5 flex-none" />
                  </a>
                ) : null}
              </div>

              <div className="mt-6 flex flex-col gap-3">
                <Button
                  type="button"
                  onClick={() => {
                    void handleStartBusinessConversation();
                  }}
                  disabled={isOwnBusiness || isStartingConversation || isMessagePending}
                  loading={isStartingConversation || isMessagePending}
                  leftIcon={MessageCircle}
                  fullWidth
                  className="rounded-full"
                >
                  {isOwnBusiness ? 'Your business' : 'Send Message'}
                </Button>

                <Button
                  type="button"
                  onClick={handleShare}
                  variant="ghost"
                  leftIcon={Share2}
                  fullWidth
                  className="rounded-full"
                >
                  Share Business
                </Button>
              </div>

              {socialLinks.length > 0 || hashtags.length > 0 ? (
                <div className="mt-6 border-t border-gray-100 pt-5">
                  <h2 className="mb-3 text-sm font-bold text-gray-900">Social links</h2>
                  <div className="flex flex-wrap gap-2">
                    {socialLinks.map(({ key, href, label, Icon }) => (
                      <a
                        key={key}
                        href={getWebsiteHref(href)}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={label}
                        className="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-colors hover:bg-primary-50 hover:text-primary-600"
                      >
                        <Icon size={19} stroke={2} />
                      </a>
                    ))}
                    {hashtags.map((tag) => (
                      <a
                        key={tag}
                        href={`https://www.instagram.com/explore/tags/${encodeURIComponent(tag)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 transition-colors hover:bg-primary-50 hover:text-primary-600"
                      >
                        <Hash className="h-3 w-3" />
                        {tag}
                      </a>
                    ))}
                  </div>
                </div>
              ) : null}
            </aside>
          </div>
        </section>
      </main>

    </>
  );
}
