// features/marketplace/services/livenews.service.ts

import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import { handleApiError } from '@/lib/errors/apiErrorHandler';
import { extractList } from '@/lib/utils/adapters';

import { LiveNewsItem } from '../types/livenews.types';
import { mapBackendLiveNewsList } from '../api/adapters/livenews.adapter';

const LIVE_NEWS_MAX_RETRIES = 4;
const LIVE_NEWS_RETRY_DELAY_MS = 400;

function waitBeforeRetry(retryNumber: number) {
  return new Promise<void>((resolve) => {
    window.setTimeout(resolve, LIVE_NEWS_RETRY_DELAY_MS * retryNumber);
  });
}

export const liveNewsService = {
  /**
   * Fetch all active listings with optional filters.
   * POST /get_listings
   */
  async getAll(): Promise<LiveNewsItem[]> {
    let lastError: unknown;

    for (let attempt = 0; attempt <= LIVE_NEWS_MAX_RETRIES; attempt += 1) {
      try {
        const { data } = await apiClient.post(API_ENDPOINTS.LIVENEWS.GET_LIVE_NEWS);
        const list = extractList(data, ['articles']);
        const news = mapBackendLiveNewsList(list);

        // An empty response is treated as transient as well. The upstream
        // feed can occasionally return no articles before it becomes ready.
        if (news.length > 0 || attempt === LIVE_NEWS_MAX_RETRIES) {
          return news;
        }
      } catch (error) {
        lastError = error;

        if (attempt === LIVE_NEWS_MAX_RETRIES) {
          throw handleApiError(
            error,
            'Unable to load live news. Please try again.',
            'liveNewsService.getAll',
          );
        }
      }

      await waitBeforeRetry(attempt + 1);
    }

    throw handleApiError(
      lastError,
      'Unable to load live news. Please try again.',
      'liveNewsService.getAll',
    );
  },
};
