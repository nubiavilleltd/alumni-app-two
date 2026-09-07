// features/marketplace/services/marketplace.service.ts

import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import { handleApiError } from '@/lib/errors/apiErrorHandler';
import { extractList } from '@/lib/utils/adapters';
import {
  mapBackendListingList,
  mapBackendListingToBusiness,
  mapBusinessToCreatePayload,
  mapBusinessToUpdatePayload,
  mapBusinessToDeletePayload,
  mapFilterListingsPayload,
  mapGetSingleListingPayload,
} from '../api/adapters/marketplace.adapter';
import type {
  Business,
  CreateListingFormData,
  GetMarketplaceParams,
  UpdateListingFormData,
} from '../types/marketplace.types';

export const marketplaceService = {
  /**
   * Fetch all active listings with optional filters.
   * POST /get_listings
   */
  async getAll(params?: GetMarketplaceParams): Promise<Business[]> {
    try {
      const payload = mapFilterListingsPayload({
        search: params?.search,
        category: params?.category,
        chapterId: params?.chapterId,
        year: params?.year,
        userId: params?.userId,
        status: 'active',
      });

      const { data } = await apiClient.post(API_ENDPOINTS.MARKETPLACE.GET_LISTINGS, payload);
      const list = extractList(data, ['listings', 'data']);
      return mapBackendListingList(list);
    } catch (error) {
      throw handleApiError(
        error,
        'Unable to load marketplace businesses. Please try again.',
        'marketplaceService.getAll',
      );
    }
  },

  /**
   * Fetch a single listing by its backend ID.
   * POST /get_listings  { id: "1" }
   */
  async getById(id: string): Promise<Business | null> {
    try {
      const payload = mapGetSingleListingPayload(id);
      const { data } = await apiClient.post(API_ENDPOINTS.MARKETPLACE.GET_LISTINGS, payload);

      // Response may be a single object or a one-item list
      const raw =
        (data as Record<string, unknown>).listing ??
        (data as Record<string, unknown>).data ??
        extractList(data, ['listings'])[0] ??
        null;

      if (!raw) return null;
      return mapBackendListingToBusiness(raw);
    } catch (error: any) {
      if (error.response?.status === 404) return null;
      throw handleApiError(
        error,
        'Unable to load this business. Please try again.',
        'marketplaceService.getById',
      );
    }
  },

  /**
   * Fetch listings belonging to a specific user.
   * POST /get_listings  { user_id: "5", status: "active" }
   *
   * NOTE: Pass the backend numeric user ID (user.id), NOT the frontend memberId.
   */
  async getByOwner(userId: string): Promise<Business[]> {
    try {
      const payload = mapFilterListingsPayload({ userId, status: 'active' });
      const { data } = await apiClient.post(API_ENDPOINTS.MARKETPLACE.GET_LISTINGS, payload);
      const list = extractList(data, ['listings', 'data']);
      return mapBackendListingList(list);
    } catch (error) {
      throw handleApiError(
        error,
        'Unable to load your businesses. Please try again.',
        'marketplaceService.getByOwner',
      );
    }
  },

  /**
   * Static category list — matches the backend contract values.
   * TODO: replace with a real endpoint if the backend adds one.
   */
  async getCategories(): Promise<string[]> {
    return ['jobs', 'housing', 'items', 'services', 'tutoring', 'other'];
  },

  /**
   * Create a new listing.
   * POST /create_listing  (JSON or FormData depending on whether images exist)
   */
  async create(
    formData: CreateListingFormData,
    userId: string,
    chapterId?: string,
  ): Promise<Business> {
    try {
      const payload = mapBusinessToCreatePayload(formData, userId, chapterId);
      const { data } = await apiClient.post(API_ENDPOINTS.MARKETPLACE.CREATE_LISTING, payload);

      // Try to extract the created listing from the response
      const created =
        (data as Record<string, unknown>).listing ?? (data as Record<string, unknown>).data ?? null;

      if (created) return mapBackendListingToBusiness(created);

      // If backend returns an ID but not the full object, fetch it
      const newId = (data as Record<string, unknown>).id;
      if (newId) {
        const fetched = await marketplaceService.getById(String(newId));
        if (fetched) return fetched;
      }

      throw new Error('Business created but could not be retrieved.');
    } catch (error) {
      throw handleApiError(
        error,
        'Failed to create your business. Please check your details and try again.',
        'marketplaceService.create',
      );
    }
  },

  /**
   * Update an existing listing.
   * POST /manage_listing  { function_type: "update", ... }
   */
  async update(id: string, formData: UpdateListingFormData): Promise<Business> {
    try {
      const payload = mapBusinessToUpdatePayload(id, formData);
      await apiClient.post(API_ENDPOINTS.MARKETPLACE.MANAGE_LISTING, payload);

      // Backend doesn't reliably return the updated object — re-fetch
      const updated = await marketplaceService.getById(id);
      if (!updated) throw new Error('Listing updated but could not be retrieved.');
      return updated;
    } catch (error) {
      throw handleApiError(
        error,
        'Failed to update your business. Please try again.',
        'marketplaceService.update',
      );
    }
  },

  /**
   * Delete a listing.
   * POST /manage_listing  { function_type: "delete", id }
   */
  async delete(id: string): Promise<void> {
    try {
      const payload = mapBusinessToDeletePayload(id);
      await apiClient.post(API_ENDPOINTS.MARKETPLACE.MANAGE_LISTING, payload);
    } catch (error) {
      throw handleApiError(
        error,
        'Failed to delete your business. Please try again.',
        'marketplaceService.delete',
      );
    }
  },
};
