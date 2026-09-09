/**
 * Temporary protected-contact adapter.
 *
 * The public list/detail APIs should eventually omit raw contact values and
 * the caller should use this request shape to retrieve one field on demand.
 * For now, the fallback value simulates that protected response locally.
 *
 * Proposed backend contract:
 *   GET /api/protected-contact/:resourceType/:resourceId/:field
 *   -> { value: string }
 */

export type ProtectedContactField = 'email' | 'phone' | 'whatsapp' | 'alternativePhone';

export interface ProtectedContactRequest {
  resourceType: string;
  resourceId: string;
  field: ProtectedContactField;
  fallbackValue?: string | null;
}

export async function fetchProtectedContact({
  fallbackValue,
}: ProtectedContactRequest): Promise<string> {
  await new Promise<void>((resolve) => {
    window.setTimeout(resolve, 180);
  });

  return fallbackValue?.trim() ?? '';
}
