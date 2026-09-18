/** Fetch one contact value from the authenticated protected-contact endpoint. */

import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';

export type ProtectedContactField = 'email' | 'phone' | 'whatsapp' | 'alternativePhone';

export interface ProtectedContactRequest {
  resourceType: string;
  resourceId: string;
  field: ProtectedContactField;
}

export async function fetchProtectedContact({
  resourceType,
  resourceId,
  field,
}: ProtectedContactRequest): Promise<string> {
  const backendField = field === 'alternativePhone' ? 'alternative_phone' : field;
  const { data } = await apiClient.get(
    API_ENDPOINTS.PROTECTED_CONTACT.GET(resourceType, resourceId, backendField),
  );

  const value = data?.value;
  if (typeof value !== 'string' || !value.trim()) {
    throw new Error('Protected contact response did not include a value');
  }

  return value.trim();
}
