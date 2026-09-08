// features/projects/api/adapters/project.adapter.ts
//
// Maps between backend project format and frontend Project type.
// UPDATE THIS FILE when the backend changes — nothing else needs to touch.
//
// Backend → Frontend field map:
//   id            → id
//   amount_raised → amountRaised
//   target_amount → targetAmount
//   sort_order    → sortOrder
//   is_featured   → isFeatured
//   created_by_name → createdByName
//   chapter_name  → chapterName

import { parseImages, stringToBoolean } from '@/lib/utils/adapters';
import {
  hasAnnouncementMarker,
  serializeAnnouncementDescription,
  stripAnnouncementMarker,
} from '@/shared/utils/announcementVisibility';
import { CreateProjectFormData, Project, UpdateProjectFormData } from '../types/project.types';

// function normalizeDate(value: unknown): string | undefined {
//   if (!value) return undefined;

//   const str = String(value).trim();

//   // Handle bad backend values
//   if (
//     str === '' ||
//     str === '0000-00-00' ||
//     str === '0000-00-00 00:00:00' ||
//     str.toLowerCase() === 'null'
//   ) {
//     return undefined;
//   }

//   return str;
// }

// ─── Inbound (backend → frontend) ────────────────────────────────────────────

function normalizeDate(value: unknown): string | undefined {
  if (!value) return undefined;

  const str = String(value).trim();

  // Extract date part first (before any checks)
  const datePart = str.split('T')[0].split(' ')[0];

  // Handle bad backend values
  if (
    datePart === '' ||
    datePart === '0000-00-00' ||
    datePart === 'null' ||
    datePart === 'undefined'
  ) {
    return undefined;
  }

  return datePart;
}

export function mapBackendProject(raw: unknown): Project {
  const d = raw as Record<string, unknown>;
  const rawDescription = String(d.description ?? '');
  const showInAnnouncements =
    hasAnnouncementMarker(rawDescription) ||
    stringToBoolean(d.show_in_announcements ?? d.showInAnnouncements) === true;

  return {
    id: String(d.id ?? ''),
    title: String(d.title ?? ''),
    description: stripAnnouncementMarker(rawDescription),
    images: parseImages(d.images),
    amountRaised: Number(d.amount_raised ?? 0),
    targetAmount: d.target_amount ? Number(d.target_amount) : undefined,
    status: d.status === 'completed' ? 'completed' : 'ongoing',
    conductedBy: String(d.conducted_by ?? ''),
    location: String(d.location ?? ''),
    startDate: normalizeDate(d.start_date) ?? '',
    endDate: normalizeDate(d.end_date),
    sortOrder: d.sort_order ? Number(d.sort_order) : undefined,
    isFeatured: d.is_featured ? Number(d.is_featured) : 0,
    showInAnnouncements,
    createdAt: d.created_at ? String(d.created_at) : undefined,
    createdByName: d.created_by_name ? String(d.created_by_name) : undefined,
    chapterName: d.chapter_name ? String(d.chapter_name) : null,
  };
}

export function mapBackendProjectList(rawList: unknown[]): Project[] {
  if (!Array.isArray(rawList)) return [];
  return rawList
    .map((item) => {
      try {
        return mapBackendProject(item);
      } catch {
        return null;
      }
    })
    .filter((p): p is Project => p !== null);
}

// ─── Outbound (frontend → backend) ───────────────────────────────────────────

/**
 * Build create-project payload.
 * Uses FormData when images are provided, plain JSON otherwise.
 */
export function mapProjectToCreatePayload(
  formData: CreateProjectFormData,
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    title: formData.title,
    description: serializeAnnouncementDescription(
      formData.description,
      formData.show_in_announcements,
    ),
    show_in_announcements: formData.show_in_announcements ? '1' : '0',
    status: formData.status,
    conducted_by: formData.conductedBy,
    location: formData.location,
    start_date: formData.startDate,
    end_date: formData.endDate,
  };

  if (formData.targetAmount != null) base.target_amount = String(formData.targetAmount);
  if (formData.amountRaised != null) base.amount_raised = String(formData.amountRaised);
  if (formData.sortOrder != null) base.sort_order = String(formData.sortOrder);
  if (formData.isFeatured != null) base.is_featured = formData.isFeatured ? '1' : '0';

  if (formData.images.length > 0) {
    const fd = new FormData();
    Object.entries(base).forEach(([k, v]) => fd.append(k, String(v ?? '')));
    // Backend expects multiple "images" entries — browser names them images[0], images[1], etc.
    formData.images.forEach((img) => fd.append('images[]', img));
    return fd;
  }

  return base;
}

/**
 * Build update-project payload.
 * Sends to /manage_project with function_type: "update".
 * Handles the three image modes: add, replace, remove specific URLs.
 */
export function mapProjectToUpdatePayload(
  projectId: string,
  formData: UpdateProjectFormData,
): FormData | Record<string, unknown> {
  const base: Record<string, unknown> = {
    id: projectId,
    function_type: 'update',
  };

  if (formData.title != null) base.title = formData.title;
  if (formData.description != null) {
    base.description = serializeAnnouncementDescription(
      formData.description,
      formData.show_in_announcements,
    );
  }
  if (formData.show_in_announcements != null) {
    base.show_in_announcements = formData.show_in_announcements ? '1' : '0';
  }
  if (formData.status != null) base.status = formData.status;
  if (formData.conductedBy != null) base.conducted_by = formData.conductedBy;
  if (formData.location != null) base.location = formData.location;
  if (formData.startDate != null) base.start_date = formData.startDate;
  if (formData.endDate != null) base.end_date = formData.endDate;
  if (formData.targetAmount != null) base.target_amount = String(formData.targetAmount);
  if (formData.amountRaised != null) base.amount_raised = String(formData.amountRaised);
  if (formData.sortOrder != null) base.sort_order = String(formData.sortOrder);
  if (formData.isFeatured != null) base.is_featured = formData.isFeatured ? '1' : '0';
  if (formData.imageAction) base.image_action = formData.imageAction;

  // remove_images must be a JSON string of URL array
  if (formData.removeImages?.length) {
    base.remove_images = JSON.stringify(formData.removeImages);
  }

  const hasNewImages = (formData.images?.length ?? 0) > 0;

  if (hasNewImages) {
    const fd = new FormData();
    Object.entries(base).forEach(([k, v]) => fd.append(k, String(v ?? '')));
    formData.images!.forEach((img) => fd.append('images', img));
    return fd;
  }

  return base;
}

/** Build delete-project payload for /manage_project. */
export function mapProjectToDeletePayload(projectId: string): Record<string, unknown> {
  return { id: projectId, function_type: 'delete' };
}

/** Build payload to fetch a single project by ID. */
export function mapGetSingleProjectPayload(id: string): Record<string, unknown> {
  return { id };
}

/** Build paginated fetch payload. */
export function mapGetProjectsPayload(params?: {
  limit?: number;
  offset?: number;
}): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  if (params?.limit != null) payload.limit = String(params.limit);
  if (params?.offset != null) payload.offset = String(params.offset);
  return payload;
}
