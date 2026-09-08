// features/announcements/services/announcement.service.ts

import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import { handleApiError } from '@/lib/errors/apiErrorHandler';
import {
  adaptBirthday,
  extractAnnouncementFromResponse,
  extractAnnouncementIdFromSlug,
  mapAnnouncementToCreatePayload,
  mapAnnouncementToDeletePayload,
  mapAnnouncementToUpdatePayload,
  mapBackendAnnouncementList,
  mapEventToAnnouncement,
  mapGetAnnouncementsPayload,
  mapProjectToAnnouncement,
} from '@/features/announcements/api/adapters/announcement.adapter';
import { eventsService } from '@/features/events/services/event.service';
import { projectsService } from '@/features/projects/services/projects.service';
import type {
  AnnouncementMutationInput,
  AnnouncementType,
  Birthday,
  GetAnnouncementsParams,
  NewsItem,
} from '@/features/announcements/types/announcement.types';

const ANNOUNCEMENT_FETCH_TYPES = ['info', 'warning', 'success', 'event'] as const;

async function fetchAnnouncements(params?: GetAnnouncementsParams): Promise<NewsItem[]> {
  const payload = mapGetAnnouncementsPayload(params);
  const { data } = await apiClient.post(API_ENDPOINTS.ANNOUNCEMENTS.LIST, payload);
  return mapBackendAnnouncementList(data);
}

function dedupeAnnouncements(items: NewsItem[]): NewsItem[] {
  const seen = new Set<string>();

  return items.filter((item) => {
    const key = String(item.id);
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function fetchAnnouncementsByTypeFallback(
  params?: GetAnnouncementsParams,
): Promise<NewsItem[]> {
  const baseParams = params ? { ...params } : {};
  delete baseParams.type;

  const results = await Promise.all(
    ANNOUNCEMENT_FETCH_TYPES.map((type) =>
      fetchAnnouncements({ ...baseParams, type: type as AnnouncementType }),
    ),
  );

  return dedupeAnnouncements(results.flat());
}

export const announcementService = {
  async getPublicFeed(params?: GetAnnouncementsParams): Promise<NewsItem[]> {
    const [announcementsResult, eventsResult, projectsResult] = await Promise.allSettled([
      announcementService.getAll(params),
      // Events are intentionally fetched without filter params so the temporary marker
      // can be evaluated client-side until the backend has a first-class boolean field.
      eventsService.getAll(),
      projectsService.getAll(),
    ]);

    if (announcementsResult.status === 'rejected') {
      throw announcementsResult.reason;
    }

    const announcements = announcementsResult.value;
    const events =
      eventsResult.status === 'fulfilled'
        ? eventsResult.value
            .filter((event) => event.showInAnnouncements)
            .map(mapEventToAnnouncement)
        : [];
    const projects =
      projectsResult.status === 'fulfilled'
        ? projectsResult.value
            .filter((project) => project.showInAnnouncements)
            .map(mapProjectToAnnouncement)
        : [];

    return [...announcements, ...events, ...projects].sort(
      (a, b) => new Date(b.startsAt || b.date).getTime() - new Date(a.startsAt || a.date).getTime(),
    );
  },

  async getAll(params?: GetAnnouncementsParams): Promise<NewsItem[]> {
    const canUseTypeFallback = !params?.type;

    try {
      const items = await fetchAnnouncements(params);

      // Some backend environments only return data when `type` is explicit.
      if (items.length > 0 || !canUseTypeFallback) return items;

      return await fetchAnnouncementsByTypeFallback(params);
    } catch (error) {
      if (canUseTypeFallback) {
        try {
          return await fetchAnnouncementsByTypeFallback(params);
        } catch (fallbackError) {
          throw handleApiError(
            fallbackError,
            'Unable to load announcements.',
            'announcementService.getAll',
          );
        }
      }

      throw handleApiError(error, 'Unable to load announcements.', 'announcementService.getAll');
    }
  },

  async getBySlug(slug: string): Promise<NewsItem | undefined> {
    try {
      const id = extractAnnouncementIdFromSlug(slug);
      if (id) {
        const items = await announcementService.getAll({ id });
        return items[0];
      }

      const items = await announcementService.getAll();
      return items.find((item) => item.slug === slug);
    } catch (error: any) {
      if (error.response?.status === 404) return undefined;
      throw handleApiError(
        error,
        'Unable to load this announcement.',
        'announcementService.getBySlug',
      );
    }
  },

  async create(input: AnnouncementMutationInput): Promise<NewsItem> {
    try {
      const payload = mapAnnouncementToCreatePayload(input);
      const { data } = await apiClient.post(API_ENDPOINTS.ANNOUNCEMENTS.CREATE, payload);
      const created = extractAnnouncementFromResponse(data);

      if (created) return created;

      const createdId = data?.data?.id ?? data?.id;
      if (createdId) {
        const items = await announcementService.getAll({ id: String(createdId) });
        if (items[0]) return items[0];
      }

      throw new Error('Announcement created but the response did not include announcement data.');
    } catch (error) {
      throw handleApiError(
        error,
        'Unable to create the announcement.',
        'announcementService.create',
      );
    }
  },

  async update(id: string, input: Partial<AnnouncementMutationInput>): Promise<NewsItem> {
    try {
      const payload = mapAnnouncementToUpdatePayload(id, input);
      const { data } = await apiClient.post(API_ENDPOINTS.ANNOUNCEMENTS.MANAGE, payload);
      const updated = extractAnnouncementFromResponse(data);

      if (updated) return updated;

      const items = await announcementService.getAll({ id });
      if (items[0]) return items[0];

      throw new Error('Announcement updated but could not be reloaded.');
    } catch (error) {
      throw handleApiError(
        error,
        'Unable to update the announcement.',
        'announcementService.update',
      );
    }
  },

  async delete(id: string): Promise<void> {
    try {
      const payload = mapAnnouncementToDeletePayload(id);
      await apiClient.post(API_ENDPOINTS.ANNOUNCEMENTS.MANAGE, payload, {
        headers: { 'Content-Type': 'application/json' },
      });
    } catch (error) {
      throw handleApiError(
        error,
        'Unable to delete the announcement.',
        'announcementService.delete',
      );
    }
  },

  async getBirthdays(): Promise<Birthday[]> {
    try {
      const { data } = await apiClient.post(API_ENDPOINTS.ANNOUNCEMENTS.BIRTHDAYS);
      return data.birthdays.map(adaptBirthday);
    } catch (error) {
      throw handleApiError(error, 'Unable to load birthdays.', 'announcementService.getAll');
    }
  },
};
