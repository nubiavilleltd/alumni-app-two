export type LocationParts = {
  address: string;
  city: string;
  state: string;
};

export const EMPTY_LOCATION_PARTS: LocationParts = {
  address: '',
  city: '',
  state: '',
};

/**
 * Encode frontend location fields into the existing backend `location` string.
 * JSON keeps commas and other punctuation inside each user-entered field intact.
 */
export function combineLocationParts(parts: Partial<LocationParts>): string {
  const locationParts: LocationParts = {
    address: parts.address?.trim() ?? '',
    city: parts.city?.trim() ?? '',
    state: parts.state?.trim() ?? '',
  };

  return Object.values(locationParts).some(Boolean) ? JSON.stringify(locationParts) : '';
}

/**
 * Parse the backend's structured location string. Legacy comma-separated
 * values are also supported so existing listings remain readable.
 */
export function splitLocation(value: unknown): LocationParts {
  if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
    const record = value as Record<string, unknown>;
    if ('address' in record || 'city' in record || 'state' in record) {
      return {
        address: typeof record.address === 'string' ? record.address.trim() : '',
        city: typeof record.city === 'string' ? record.city.trim() : '',
        state: typeof record.state === 'string' ? record.state.trim() : '',
      };
    }
  }

  const location = typeof value === 'string' ? value.trim() : '';
  if (!location) return { ...EMPTY_LOCATION_PARTS };

  try {
    const parsed = JSON.parse(location) as unknown;

    if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
      const record = parsed as Record<string, unknown>;

      if ('address' in record || 'city' in record || 'state' in record) {
        return {
          address: typeof record.address === 'string' ? record.address.trim() : '',
          city: typeof record.city === 'string' ? record.city.trim() : '',
          state: typeof record.state === 'string' ? record.state.trim() : '',
        };
      }
    }
  } catch {
    // Continue with the legacy comma-separated format below.
  }

  const segments = location
    .split(',')
    .map((segment) => segment.trim())
    .filter(Boolean);

  if (segments.length < 3) {
    return { address: location, city: '', state: '' };
  }

  return {
    address: segments.slice(0, -2).join(', '),
    city: segments.at(-2) ?? '',
    state: segments.at(-1) ?? '',
  };
}

export function normalizeLocationPart(value: unknown): string {
  return String(value ?? '')
    .trim()
    .toLowerCase();
}

export function matchesLocationPart(value: unknown, filter: string): boolean {
  return !filter || normalizeLocationPart(value) === normalizeLocationPart(filter);
}
