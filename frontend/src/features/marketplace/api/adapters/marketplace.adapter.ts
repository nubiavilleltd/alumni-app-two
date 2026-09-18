// features/marketplace/api/adapters/marketplace.adapter.ts
//
// Transforms between backend listing format and frontend Business type.
// UPDATE THIS FILE when the backend changes — nothing else needs to touch.
//
// Backend → Frontend field map:
//   id          → businessId
//   user_id     → ownerId
//   seller_name → owner
//   title       → name

import type {
  Business,
  CreateListingFormData,
  GetMarketplaceParams,
  Socials,
  UpdateListingFormData,
} from '../../types/marketplace.types';
import { generateSlug, parseImages, extractList } from '@/lib/utils/adapters';
import { combineLocationParts, splitLocation } from '@/shared/utils/location';

// ─── Inbound (backend → frontend) ────────────────────────────────────────────

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, '') ?? '';

function getNestedRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};
}

function readAvailability(value: unknown): boolean {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function isRealPhotoUrl(value: unknown): value is string {
  const photo = typeof value === 'string' ? value.trim() : '';

  return Boolean(
    photo &&
    photo !== 'default.png' &&
    !photo.includes('ui-avatars.com') &&
    !photo.includes('default-avatar'),
  );
}

function resolvePhotoUrl(value: unknown): string | undefined {
  if (!isRealPhotoUrl(value)) return undefined;

  const photo = value.trim();
  const isAbsolute = /^(https?:)?\/\//i.test(photo) || /^(data|blob):/i.test(photo);

  if (isAbsolute || !API_BASE_URL) return photo;
  return photo.startsWith('/') ? `${API_BASE_URL}${photo}` : `${API_BASE_URL}/${photo}`;
}

function resolveOwnerPhoto(raw: Record<string, unknown>): string | undefined {
  const profile = getNestedRecord(raw.profile);
  const user = getNestedRecord(raw.user);
  const seller = getNestedRecord(raw.seller);
  const owner = getNestedRecord(raw.owner);

  return resolvePhotoUrl(
    raw.seller_avatar ??
      raw.seller_photo ??
      raw.owner_avatar ??
      raw.owner_photo ??
      raw.user_avatar ??
      raw.user_photo ??
      raw.profile_photo ??
      raw.avatar ??
      raw.photo ??
      seller.avatar ??
      seller.photo ??
      owner.avatar ??
      owner.photo ??
      user.avatar ??
      user.photo ??
      profile.avatar ??
      profile.photo,
  );
}

function resolveListingMessagePrompt(raw: Record<string, unknown>): string | undefined {
  const candidates = [
    raw.message_prompt,
    raw.messagePrompt,
    raw.draft_message,
    raw.draftMessage,
    raw.message_template,
    raw.messageTemplate,
  ];

  for (const candidate of candidates) {
    const value = typeof candidate === 'string' ? candidate.trim() : '';
    if (value) return value;
  }

  return undefined;
}

// One place to change if the backend contract turns out different
function socialsToPayloadFields(socials?: Socials): Record<string, string> {
  if (!socials) return {};

  const fields: Record<string, string> = {};
  if (socials.instagram?.trim()) fields.social_instagram = socials.instagram.trim();
  if (socials.instagramHashtag?.trim()) {
    fields.social_instagram_hashtag = socials.instagramHashtag.trim().replace(/^#+/, '');
  }
  if (socials.facebook?.trim()) fields.social_facebook = socials.facebook.trim();
  if (socials.linkedin?.trim()) fields.social_linkedin = socials.linkedin.trim();
  if (socials.x?.trim()) fields.social_x = socials.x.trim();
  if (socials.tiktok?.trim()) fields.social_tiktok = socials.tiktok.trim();
  return fields;
}

function payloadFieldsToSocials(raw: Record<string, unknown>): Socials | undefined {
  const socialMedia = getNestedRecord(raw.social_media);

  const socials: Socials = {
    instagram: socialMedia.instagram_url ? String(socialMedia.instagram_url).trim() : undefined,
    instagramHashtag: socialMedia.instagram_hashtag
      ? String(socialMedia.instagram_hashtag).trim().replace(/^#+/, '')
      : undefined,
    facebook: socialMedia.facebook_url ? String(socialMedia.facebook_url).trim() : undefined,
    linkedin: socialMedia.linkedin_url ? String(socialMedia.linkedin_url).trim() : undefined,
    x: socialMedia.twitter_url ? String(socialMedia.twitter_url).trim() : undefined,
    tiktok: socialMedia.tiktok_url ? String(socialMedia.tiktok_url).trim() : undefined,
  };

  return Object.values(socials).some(Boolean) ? socials : undefined;
}

export function mapBackendListingToBusiness(raw: unknown): Business {
  const d = raw as Record<string, unknown>;
  const location = String(d.location ?? '');
  const locationParts = splitLocation(location);

  return {
    businessId: String(d.id ?? ''),
    ownerId: String(d.user_id ?? ''),
    chapterId:
      d.chapter_id === undefined || d.chapter_id === null ? undefined : String(d.chapter_id),
    chapterName: d.chapter_name ? String(d.chapter_name) : undefined,
    year: d.year === undefined || d.year === null ? undefined : String(d.year),
    owner: String(d.seller_name ?? 'Unknown'),
    ownerPhoto: resolveOwnerPhoto(d),
    slug: generateSlug(String(d.title ?? ''), String(d.id ?? ''), 'business'),
    name: String(d.title ?? 'Untitled'),
    category: String(d.category ?? 'other'),
    description: String(d.description ?? ''),
    location,
    ...locationParts,
    // Public listing responses contain availability flags, never raw contacts.
    phone: '',
    email: undefined,
    website: d.website ? String(d.website) : undefined,
    whatsapp: undefined,
    hasPhone: readAvailability(d.has_phone),
    hasEmail: readAvailability(d.has_email),
    hasWhatsapp: readAvailability(d.has_whatsapp),
    socials: payloadFieldsToSocials(d),
    messagePrompt: resolveListingMessagePrompt(d),
    images: parseImages(d.images),
  };
}

export function mapBackendListingList(rawResponse: unknown): Business[] {
  const list = extractList(rawResponse, ['listings', 'data']);

  return list
    .map((item) => {
      try {
        return mapBackendListingToBusiness(item);
      } catch {
        return null;
      }
    })
    .filter((item): item is Business => item !== null);
}

// ─── Outbound (frontend → backend) ───────────────────────────────────────────

/**
 * Build the create-listing payload.
 * Returns FormData when images are present, plain object otherwise.
 * The axios interceptor adds token + jwt automatically.
 */
export function mapBusinessToCreatePayload(
  formData: CreateListingFormData,
  userId: string,
  chapterId?: string,
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    user_id: userId,
    title: formData.name,
    description: formData.description,
    category: formData.category,
    location: combineLocationParts(formData),
    phone: formData.phone,
    business_name: formData.name,
    contact_info: formData.phone,
    status: 'active',
    year: new Date().getFullYear().toString(),
    price: '0.00',
    price_type: 'free',
    is_featured: '0',
    message_prompt: formData.messagePrompt?.trim() ?? '',
  };

  if (chapterId) base.chapter_id = chapterId;
  Object.assign(base, socialsToPayloadFields(formData.socials));
  if (formData.whatsapp?.trim()) base.whatsapp = formData.whatsapp.trim();

  if (formData.website?.trim()) base.website = formData.website.trim();

  if (formData.images.length > 0) {
    const fd = new FormData();
    Object.entries(base).forEach(([k, v]) => fd.append(k, String(v ?? '')));
    formData.images.forEach((img) => fd.append('images[]', img));
    return fd;
  }

  return base;
}

export function mapBusinessToUpdatePayload(
  businessId: string,
  formData: UpdateListingFormData,
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    id: businessId,
    function_type: 'update',
    title: formData.name,
    description: formData.description,
    category: formData.category,
    location: combineLocationParts(formData),
    phone: formData.phone,
    status: 'active',
    message_prompt: formData.messagePrompt?.trim() ?? '',
  };

  Object.assign(base, socialsToPayloadFields(formData.socials));
  if (formData.whatsapp?.trim()) base.whatsapp = formData.whatsapp.trim();

  if (formData.website?.trim()) {
    base.website = formData.website.trim();
  }

  // ✅ Only set if defined
  if (formData.imageAction) {
    base.image_action = formData.imageAction;
  }

  // ✅ Required for deletion
  if (formData.removeImages?.length) {
    base.remove_images = JSON.stringify(formData.removeImages);
  }

  const hasNewImages = (formData.images?.length ?? 0) > 0;

  if (hasNewImages) {
    const fd = new FormData();

    Object.entries(base).forEach(([k, v]) => {
      fd.append(k, String(v ?? ''));
    });

    // ✅ FIXED HERE
    formData.images!.forEach((img) => {
      fd.append('images', img);
    });

    return fd;
  }

  return base;
}

/** Build the delete-listing payload for /manage_listing. */
export function mapBusinessToDeletePayload(businessId: string): Record<string, unknown> {
  return { id: businessId, function_type: 'delete' };
}

/** Build payload to fetch a single listing by ID. */
export function mapGetSingleListingPayload(listingId: string): Record<string, unknown> {
  return { id: listingId };
}

/** Build payload to filter listings. Only includes defined params. */
export function mapFilterListingsPayload(
  params: Pick<
    GetMarketplaceParams,
    'search' | 'category' | 'userId' | 'chapterId' | 'year' | 'status'
  >,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {
    status: params.status ?? 'active',
  };

  if (params.search) payload.search = params.search;
  if (params.category) payload.category = params.category;
  if (params.userId) payload.user_id = params.userId;
  if (params.chapterId) payload.chapter_id = params.chapterId;
  if (params.year) payload.year = params.year;

  return payload;
}
