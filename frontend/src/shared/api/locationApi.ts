import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import { NIGERIA_LOCATIONS } from '@/shared/constants/nigeriaLocations';
import type { LocationGroup } from '@/shared/types/location.types';

const STATE_ALIASES: Record<string, string> = {
  Nassarawa: 'Nasarawa',
};

function normalizeStateName(value: string): string {
  return STATE_ALIASES[value] ?? value;
}

function asNonEmptyString(value: unknown): string | null {
  if (typeof value !== 'string') return null;

  const normalized = value.trim();
  return normalized || null;
}

function normalizeCities(value: unknown): string[] {
  if (!Array.isArray(value)) return [];

  return [
    ...new Set(
      value
        .map((item) => {
          if (typeof item === 'string') return asNonEmptyString(item);

          if (item && typeof item === 'object') {
            const entry = item as Record<string, unknown>;
            return asNonEmptyString(entry.name ?? entry.city ?? entry.lga ?? entry.area);
          }

          return null;
        })
        .filter((city): city is string => city !== null),
    ),
  ];
}

function isLocationGroup(value: LocationGroup | null): value is LocationGroup {
  return value !== null;
}

function normalizeLocationList(value: unknown): LocationGroup[] {
  if (Array.isArray(value)) {
    return value
      .map((item): LocationGroup | null => {
        if (!item || typeof item !== 'object') return null;

        const entry = item as Record<string, unknown>;
        const stateValue = asNonEmptyString(entry.state ?? entry.state_name ?? entry.name);
        const cities = normalizeCities(entry.cities ?? entry.lgas ?? entry.areas);

        return stateValue && cities.length > 0
          ? { state: normalizeStateName(stateValue), cities }
          : null;
      })
      .filter(isLocationGroup);
  }

  if (value && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>)
      .map(([state, cities]): LocationGroup | null => {
        const normalizedState = asNonEmptyString(state);
        const normalizedCities = normalizeCities(cities);

        return normalizedState && normalizedCities.length > 0
          ? { state: normalizeStateName(normalizedState), cities: normalizedCities }
          : null;
      })
      .filter(isLocationGroup);
  }

  return [];
}

function readApiLocations(responseData: unknown): LocationGroup[] {
  if (!responseData || typeof responseData !== 'object') return [];

  const response = responseData as Record<string, unknown>;
  const payload = response.data ?? response.locations ?? responseData;

  return normalizeLocationList(payload);
}

/**
 * Fetches the shared location catalogue and falls back to the bundled Nigerian
 * state/LGA data until the backend endpoint is available.
 */
export async function fetchLocations(): Promise<readonly LocationGroup[]> {
  try {
    const { data } = await apiClient.get(API_ENDPOINTS.LOCATIONS.FETCH);
    const locations = readApiLocations(data);

    return locations.length > 0 ? locations : getFallbackLocations();
  } catch (error) {
    if (import.meta.env.DEV) {
      console.warn('[locationApi] fetch_locations unavailable; using fallback locations.', error);
    }

    return getFallbackLocations();
  }
}

function getFallbackLocations(): readonly LocationGroup[] {
  return NIGERIA_LOCATIONS.map((location) => ({
    ...location,
    state: normalizeStateName(location.state),
  }));
}
