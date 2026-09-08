import type { Event } from '../../types/event.types';
import {
  generateSlug,
  safeParseInt,
  stringToBoolean,
  extractList,
  safeParseDate,
} from '@/lib/utils/adapters';
import { EVENT_SURVEY_TAG } from '../../lib/eventSurveyAvailability';
import {
  EVENT_ANNOUNCEMENT_TAG,
  hasEventAnnouncementMarker,
  isEventPast,
  serializeEventDescription,
  stripEventAnnouncementMarker,
} from '../../lib/eventAnnouncementVisibility';

function mapRSVPStatus(status: unknown): 'going' | 'maybe' | 'not_going' | null {
  if (!status || status === '') return 'not_going'; // clean "unregistered"

  const map: Record<string, 'going' | 'maybe' | 'not_going'> = {
    going: 'going',
    maybe: 'maybe',
    not_going: 'not_going',
  };

  return map[String(status)] ?? null;
}

// Returns undefined instead of today when value is null/empty
export function safeParseOptionalDate(value: unknown): string | undefined {
  if (!value) return undefined;
  return safeParseDate(value);
}

// ─────────────────────────────────────────────────────────────
// Inbound (backend → frontend)
// ─────────────────────────────────────────────────────────────

export function mapBackendEventToFrontend(raw: unknown): Event {
  const d = raw as Record<string, any>;

  const normalizedTags = parseTags(d.tags ?? d.event_tags ?? d.eventTags ?? d.tag).map((tag) =>
    tag.trim().toLowerCase(),
  );
  const rawDescription = String(d.description ?? d.content ?? '');
  const hasDescriptionAnnouncementMarker = hasEventAnnouncementMarker(rawDescription);
  const cleanDescription = stripEventAnnouncementMarker(rawDescription);
  const hasRegistrationQuestions =
    readRegistrationQuestionFlag(d, normalizedTags) ??
    (normalizedTags.includes(EVENT_SURVEY_TAG) ? true : null);
  const showInAnnouncements =
    !isEventPast({
      startDate: String(d.start_date ?? d.event_date ?? ''),
      endDate: d.end_date ? String(d.end_date) : undefined,
      startTime: d.start_time ? String(d.start_time) : undefined,
      endTime: d.end_time ? String(d.end_time) : undefined,
    }) &&
    (hasDescriptionAnnouncementMarker ||
      normalizedTags.includes(EVENT_ANNOUNCEMENT_TAG) ||
      stringToBoolean(d.show_in_announcements ?? d.showInAnnouncements) === true);

  return {
    id: String(d.id ?? ''),
    slug: generateSlug(d.title ?? 'event', d.id ?? '', 'event'),

    title: d.title ?? 'Untitled Event',
    description: cleanDescription,
    content: cleanDescription,

    image: d.event_banner || '',

    startDate: safeParseDate(d.start_date || d.event_date),
    endDate: safeParseOptionalDate(d.end_date),

    startTime: d.start_time?.slice(0, 5),
    endTime: d.end_time?.slice(0, 5),

    location: d.location ?? '',

    isVirtual: stringToBoolean(d.is_virtual) ?? false,
    virtualLink: d.virtual_link || undefined,
    attire: d.attire || undefined,
    category: d.category || undefined,
    tags: normalizedTags.filter(
      (tag) => tag !== EVENT_SURVEY_TAG && tag !== EVENT_ANNOUNCEMENT_TAG,
    ),
    showInAnnouncements,

    featured: stringToBoolean(d.is_featured) ?? false,

    status: mapStatus(d.status, d.event_date),

    capacity: safeParseInt(d.max_attendees),
    allowGuests: stringToBoolean(d.allow_guests) ?? false,
    hasRegistrationQuestions,

    createdBy: d.created_by_name || d.created_by || 'Organizer',
    attendeeCount: safeParseInt(d.attendee_count),

    registrations: [],

    createdAt: safeParseDate(d.created_at),
    publishedAt: d.created_at,
    updatedAt: d.updated_at,

    type: d.category || 'event',
    rsvpStatus: mapRSVPStatus(d.my_rsvp),
  };
}

export function mapBackendEventList(raw: unknown): Event[] {
  const list = extractList(raw, ['events', 'data']);

  return list
    .map((i) => {
      try {
        return mapBackendEventToFrontend(i);
      } catch {
        return null;
      }
    })
    .filter((i): i is Event => i !== null);
}

// ─────────────────────────────────────────────────────────────
// Outbound (frontend → backend)
// ─────────────────────────────────────────────────────────────

export function mapEventToCreatePayload(
  formData: {
    title: string;
    description: string;
    location: string;
    start_date: string;
    end_date?: string;
    start_time?: string;
    end_time?: string;
    color?: string;
    visibility?: string;
    status: string;
    max_attendees?: number;
    event_banner?: File | null;
    tags?: string[];
    show_in_announcements?: boolean;
  },
  userId: string,
  chapterId?: string,
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    user_id: userId,
    chapter_id: chapterId,
    title: formData.title,
    description: serializeEventDescription(
      formData.description,
      formData.show_in_announcements &&
        !isEventPast({
          startDate: formData.start_date,
          endDate: formData.end_date,
          startTime: formData.start_time,
          endTime: formData.end_time,
        }),
    ),
    location: formData.location,
    start_date: formData.start_date,
    end_date: formData.end_date,
    status: formData.status || 'upcoming',
    visibility: formData.visibility || 'public',
    is_approved: '1',
  };

  if (chapterId) base.chapter_id = chapterId;
  if (formData.start_time) base.start_time = formData.start_time;
  if (formData.end_time) base.end_time = formData.end_time;
  if (formData.color) base.color = formData.color;
  // if (formData.max_attendees) base.max_attendees = String(formData.max_attendees);
  const tags = [...(formData.tags ?? [])].filter((tag) => tag !== EVENT_ANNOUNCEMENT_TAG);
  if (tags.length) base.tags = JSON.stringify(tags);

  if (formData.event_banner) {
    const fd = new FormData();

    Object.entries(base).forEach(([k, v]) => {
      if (v !== undefined && v !== null) {
        fd.append(k, String(v));
      }
    });

    fd.append('event_banner', formData.event_banner);
    fd.append('chapter_id', chapterId as string);

    return fd;
  }

  return base;
}

export function mapEventToUpdatePayload(
  eventId: string,
  formData: {
    title: string;
    description: string;
    location: string;
    start_date: string;
    end_date?: string;
    start_time: string;
    end_time?: string;
    color?: string;
    visibility?: string;
    event_banner?: File | null;
    max_attendees?: number;
    status?: string;
    tags?: string[];
    show_in_announcements?: boolean;
  },
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    id: eventId,
    function_type: 'update',
    title: formData.title,
    description: serializeEventDescription(
      formData.description,
      formData.show_in_announcements &&
        !isEventPast({
          startDate: formData.start_date,
          endDate: formData.end_date,
          startTime: formData.start_time,
          endTime: formData.end_time,
        }),
    ),
    location: formData.location,
    end_date: formData.end_date,
    end_time: formData.end_time,
    start_date: formData.start_date,
    start_time: formData.start_time,
  };

  if (formData.start_time) base.start_time = formData.start_time;
  if (formData.end_time) base.end_time = formData.end_time;
  if (formData.start_date) base.start_time = formData.start_date;
  if (formData.end_date) base.start_time = formData.end_date;
  if (formData.color) base.color = formData.color;
  if (formData.visibility) base.visibility = formData.visibility;
  if (formData.max_attendees) base.max_attendees = String(formData.max_attendees);
  if (formData.status) base.status = formData.status;
  if (formData.tags !== undefined || formData.show_in_announcements !== undefined) {
    const tags = [...(formData.tags ?? [])].filter((tag) => tag !== EVENT_ANNOUNCEMENT_TAG);
    base.tags = JSON.stringify(tags);
  }

  if (formData.event_banner) {
    const fd = new FormData();

    Object.entries(base).forEach(([k, v]) => {
      if (v !== undefined && v !== null) {
        fd.append(k, String(v));
      }
    });

    fd.append('event_banner', formData.event_banner);

    return fd;
  }

  return base;
}

export function mapEventToDeletePayload(eventId: string): Record<string, unknown> {
  return {
    id: eventId,
    function_type: 'delete',
  };
}

export function mapGetSingleEventPayload(eventId: string): Record<string, unknown> {
  return {
    id: eventId,
  };
}

export function mapFilterEventsPayload(params: {
  search?: string;
  visibility?: string;
  status?: string;
  chapterId?: string;
  year?: string;
}): Record<string, unknown> {
  const payload: Record<string, unknown> = {};

  if (params.visibility) payload.visibility = params.visibility;
  if (params.status) payload.status = params.status;
  if (params.chapterId) payload.chapter_id = params.chapterId;
  if (params.year) payload.year = params.year;

  if (!payload.status && !payload.visibility) {
    payload.status = 'upcoming';
  }

  return payload;
}

// ─────────────────────────────────────────────────────────────
// RSVP / Attendees
// ─────────────────────────────────────────────────────────────

export function mapRegisterEventPayload(
  userId: string,
  eventId: string,
  status: 'going' | 'maybe' | 'not_going' = 'going',
  additionalInfo: string,
): Record<string, unknown> {
  return {
    user_id: userId,
    event_id: eventId,
    status,
    year: new Date().getFullYear().toString(),
    additional_info: additionalInfo,
  };
}

export function mapCancelRegistrationPayload(
  userId: string,
  eventId: string,
): Record<string, unknown> {
  return {
    user_id: userId,
    event_id: eventId,
    function_type: 'cancel',
  };
}

export function mapUpdateRSVPPayload(
  userId: string,
  eventId: string,
  status: 'going' | 'maybe' | 'not_going',
): Record<string, unknown> {
  return {
    user_id: userId,
    event_id: eventId,
    function_type: 'update',
    status,
  };
}

export function mapGetEventAttendeesPayload(
  eventId: string,
  status?: string,
): Record<string, unknown> {
  const payload: Record<string, unknown> = { event_id: eventId };

  if (status) payload.status = status;

  return payload;
}

export function mapGetUserEventsPayload(userId: string): Record<string, unknown> {
  return {
    user_id: userId,
  };
}

// ─────────────────────────────────────────────────────────────
// Adapter-only helpers
// ─────────────────────────────────────────────────────────────

function mapStatus(status: string, date: string) {
  //   if (!status) {
  //     return new Date(date) < new Date() ? 'completed' : 'published';
  //   }

  if (!status) {
    return new Date(date + 'Z') < new Date() ? 'completed' : 'published';
  }

  const map: Record<string, any> = {
    upcoming: 'published',
    active: 'published',
    cancelled: 'cancelled',
    completed: 'completed',
    draft: 'draft',
  };

  return map[status] || 'published';
}

function parseTags(v: unknown): string[] {
  if (!v) return [];

  if (Array.isArray(v)) return v;

  if (typeof v === 'string') {
    try {
      const parsed = JSON.parse(v);
      if (Array.isArray(parsed)) return parsed;
    } catch {
      return v
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
    }
  }

  return [];
}

function readRegistrationQuestionFlag(
  raw: Record<string, any>,
  normalizedTags: string[],
): boolean | null {
  const explicitBooleanCandidates = [
    raw.has_registration_questions,
    raw.has_registration_forms,
    raw.has_survey_forms,
    raw.has_forms,
  ];

  for (const candidate of explicitBooleanCandidates) {
    const parsed = stringToBoolean(candidate);
    if (parsed !== null) {
      return parsed as boolean;
    }
  }

  const formCountCandidates = [
    raw.registration_form_count,
    raw.registration_forms_count,
    raw.survey_form_count,
    raw.survey_forms_count,
  ];

  for (const candidate of formCountCandidates) {
    if (candidate === undefined || candidate === null || candidate === '') continue;
    const parsed = safeParseInt(candidate);
    if (typeof parsed === 'number' && Number.isFinite(parsed)) {
      return parsed > 0;
    }
  }

  if (normalizedTags.includes(EVENT_SURVEY_TAG)) {
    return true;
  }

  return null;
}
