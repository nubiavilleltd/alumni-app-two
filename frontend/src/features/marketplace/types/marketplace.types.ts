// features/marketplace/types/marketplace.types.ts

// ─── Socials ──────────────────────────────────────────────────────────────────
export interface Socials {
  instagram?: string;
  instagramHashtag?: string;
  facebook?: string;
  linkedin?: string;
  x?: string;
  tiktok?: string;
}

// ─── Business entity ──────────────────────────────────────────────────────────
export interface Business {
  // ── Relational keys ──────────────────────────────────────────────────────
  businessId: string; // 'BIZ-{year}-{seq}' — stable unique ID
  ownerId: string; // FK → MockAuthAccount.memberId
  chapterId?: string;
  chapterName?: string;
  year?: string;

  // ── Identity ──────────────────────────────────────────────────────────────
  slug: string;
  name: string;
  owner: string; // display name — denormalised for convenience
  ownerPhoto?: string;

  // ── Details ───────────────────────────────────────────────────────────────
  category: string;
  description: string;
  location: string;
  address: string;
  city: string;
  state: string;
  phone: string;
  email?: string;
  website?: string;
  whatsapp?: string;
  socials?: Socials;
  messagePrompt?: string;
  images: string[];
}

// ─── Service params / payloads ────────────────────────────────────────────────
export interface GetMarketplaceParams {
  search?: string;
  category?: string;
  chapterId?: string;
  year?: string;
  userId?: string;
  status?: string;
  page?: number;
}

export interface PostBusinessPayload {
  name: string;
  category: string;
  description: string;
  location: string;
  address: string;
  city: string;
  state: string;
  phone: string;
  website?: string;
  whatsapp?: string;
  socials?: Socials;
  messagePrompt?: string;
  images: File[];
}

export interface CreateListingFormData {
  name: string;
  category: string;
  description: string;
  location?: string;
  address: string;
  city: string;
  state: string;
  phone: string;
  website?: string;
  whatsapp?: string;
  socials?: Socials;
  messagePrompt?: string;
  images: File[];
}

export interface UpdateListingFormData extends Partial<CreateListingFormData> {
  // imageAction controls how existing images are handled on update
  imageAction?: 'add' | 'replace';
  // removeImages: array of existing image URLs to delete
  removeImages?: string[];
}
