import {
  extractList,
  extractObject,
  generateSlug,
  safeParseDate,
  safeParseInt,
} from '@/lib/utils/adapters';
import type {
  Announcement,
  AnnouncementMutationInput,
  AnnouncementType,
  Birthday,
  BirthdayResponse,
  GetAnnouncementsParams,
} from '@/features/announcements/types/announcement.types';
import type { Event } from '@/features/events/types/event.types';
import { EVENT_ROUTES } from '@/features/events/routes';

const ANNOUNCEMENT_FALLBACK_IMAGE = '/news-1.png';
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, '') ?? '';

function toStringOrUndefined(value: unknown) {
  if (value === null || value === undefined || value === '') return undefined;
  return String(value);
}

function toAnnouncementType(value: unknown): AnnouncementType {
  switch (value) {
    case 'event':
      return value;
    case 'info':
    default:
      return 'info';
  }
}

function createExcerpt(value: string, maxLength = 180) {
  const normalized = value.replace(/\s+/g, ' ').trim();
  if (!normalized) return '';
  if (normalized.length <= maxLength) return normalized;
  return `${normalized.slice(0, maxLength).trimEnd()}...`;
}

function resolveAnnouncementImage(value: unknown): string | undefined {
  const image = toStringOrUndefined(value)?.trim();
  if (!image) return undefined;

  if (image === ANNOUNCEMENT_FALLBACK_IMAGE) return image;

  const isAbsolute = /^(https?:)?\/\//i.test(image) || /^(data|blob):/i.test(image);
  if (isAbsolute || !API_BASE_URL) return image;

  return image.startsWith('/') ? `${API_BASE_URL}${image}` : `${API_BASE_URL}/${image}`;
}

export function mapGetAnnouncementsPayload(params?: GetAnnouncementsParams) {
  if (!params) return {};

  return {
    ...(params.id ? { id: String(params.id) } : {}),
    ...(params.createdBy ? { created_by: String(params.createdBy) } : {}),
    ...(params.type ? { type: params.type } : {}),
    ...(params.year ? { year: String(params.year) } : {}),
  };
}

export function mapAnnouncementToCreatePayload(input: AnnouncementMutationInput) {
  const payload = new FormData();
  payload.append('title', input.title.trim());
  payload.append('content', input.content.trim());
  payload.append('type', input.type ?? 'info');

  if (input.chapterId?.trim()) payload.append('chapter_id', input.chapterId.trim());
  if (input.year?.trim()) payload.append('year', input.year.trim());
  if (input.startsAt?.trim()) payload.append('starts_at', input.startsAt.trim());
  if (input.endsAt?.trim()) payload.append('ends_at', input.endsAt.trim());
  if (input.image) payload.append('image', input.image, input.image.name);

  return payload;
}

export function mapAnnouncementToUpdatePayload(
  id: string,
  input: Partial<AnnouncementMutationInput>,
) {
  const payload = new FormData();
  payload.append('function_type', 'update');
  payload.append('id', id);

  if (input.title?.trim()) payload.append('title', input.title.trim());
  if (input.content?.trim()) payload.append('content', input.content.trim());
  if (input.type) payload.append('type', input.type);
  if (input.chapterId?.trim()) payload.append('chapter_id', input.chapterId.trim());
  if (input.year?.trim()) payload.append('year', input.year.trim());
  if (input.startsAt?.trim()) payload.append('starts_at', input.startsAt.trim());
  if (input.endsAt?.trim()) payload.append('ends_at', input.endsAt.trim());
  if (input.image) payload.append('image', input.image, input.image.name);

  return payload;
}

export function mapAnnouncementToDeletePayload(id: string) {
  return {
    function_type: 'delete',
    id: safeParseInt(id) ?? id,
  };
}

export function extractAnnouncementIdFromSlug(slug: string) {
  const match = slug.match(/-(\d+)$/);
  return match?.[1];
}

export function mapBackendAnnouncement(raw: any): Announcement | null {
  try {
    const id = raw?.id ?? raw?.announcement_id;
    if (id === null || id === undefined || id === '') return null;

    const title = toStringOrUndefined(raw?.title)?.trim() ?? 'Untitled Announcement';
    const content =
      toStringOrUndefined(raw?.content) ??
      toStringOrUndefined(raw?.body) ??
      toStringOrUndefined(raw?.description) ??
      '';
    const type = toAnnouncementType(raw?.type);
    const chapterId = toStringOrUndefined(raw?.chapter_id ?? raw?.chapterId);
    const year = safeParseInt(raw?.year);
    const startsAt = toStringOrUndefined(raw?.starts_at ?? raw?.startsAt);
    const endsAt = toStringOrUndefined(raw?.ends_at ?? raw?.endsAt);
    const createdAt =
      raw?.created_at ?? raw?.createdAt ?? raw?.published_at ?? raw?.publishedAt ?? startsAt;
    const image =
      resolveAnnouncementImage(raw?.image) ??
      resolveAnnouncementImage(raw?.imag) ??
      resolveAnnouncementImage(raw?.images) ??
      resolveAnnouncementImage(raw?.image_url) ??
      resolveAnnouncementImage(raw?.imageUrl) ??
      resolveAnnouncementImage(raw?.banner) ??
      ANNOUNCEMENT_FALLBACK_IMAGE;

    return {
      id: String(id),
      slug:
        toStringOrUndefined(raw?.slug) ??
        `${generateSlug(title, String(id), 'announcement')}-${id}`,
      title,
      content,
      excerpt:
        toStringOrUndefined(raw?.excerpt) ??
        toStringOrUndefined(raw?.summary) ??
        createExcerpt(content),
      image,
      date: safeParseDate(createdAt),
      type,
      tag: toStringOrUndefined(raw?.tag) ?? type.toUpperCase(),
      createdBy: toStringOrUndefined(raw?.created_by ?? raw?.createdBy),
      chapterId,
      year,
      startsAt: startsAt ? safeParseDate(startsAt) : undefined,
      endsAt: endsAt ? safeParseDate(endsAt) : undefined,
      featured: Boolean(raw?.featured),
      source: 'announcement',
      sourceId: String(id),
    };
  } catch {
    return null;
  }
}

/** Map a marked event into the public announcement-feed shape. */
export function mapEventToAnnouncement(event: Event): Announcement {
  const content = event.description || event.content || '';
  const eventDate = event.startDate || event.createdAt;
  const parsedYear = new Date(eventDate).getFullYear();

  return {
    id: `event:${event.id}`,
    slug: `event-${event.slug || event.id}`,
    title: event.title,
    content,
    excerpt: createExcerpt(content),
    image: event.image || ANNOUNCEMENT_FALLBACK_IMAGE,
    date: event.createdAt || eventDate,
    type: 'event',
    tag: 'EVENT',
    createdBy: event.createdBy,
    year: Number.isNaN(parsedYear) ? undefined : parsedYear,
    startsAt: eventDate,
    endsAt: event.endDate,
    featured: event.featured,
    source: 'event',
    sourceId: event.id,
    href: EVENT_ROUTES.DETAIL(event.id),
  };
}

export function mapBackendAnnouncementList(data: unknown): Announcement[] {
  const list = extractList(data, ['announcements', 'data', 'items']);

  if (list.length === 0) {
    const single = extractObject(data, ['data', 'announcement', 'item']);
    const mapped = single ? mapBackendAnnouncement(single) : null;
    return mapped ? [mapped] : [];
  }

  return list
    .map((item) => mapBackendAnnouncement(item))
    .filter((item): item is Announcement => item !== null);
}

export function extractAnnouncementFromResponse(data: unknown): Announcement | null {
  const object = extractObject(data, ['data', 'announcement', 'item']);
  return object ? mapBackendAnnouncement(object) : null;
}

export function adaptBirthday(data: BirthdayResponse): Birthday {
  return {
    userId: data.user_id,
    nameInSchool: data.name_in_school,
    classLabel: data.class_label,
    avatar: data.avatar,
    fullName: data.fullname,
  };
}
