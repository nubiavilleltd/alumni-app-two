/**
 * ============================================================================
 * VOUCHER PENDING APPROVALS ADAPTER
 * ============================================================================
 *
 * Maps between backend's voucher API responses and clean frontend types.
 *
 * Backend contract:
 *   POST /api/voucher_pending    → get pending vouches
 *   POST /api/vouch_action       → approve or reject a vouch
 *
 * ============================================================================
 */

import type { PendingVouch } from '../voucherApi';

// ═══════════════════════════════════════════════════════════════════════════
// OUTBOUND PAYLOADS (Frontend → Backend)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Body sent to POST /api/vouch_action (approve)
 *
 * {
 *   "vouch_id": 1,
 *   "action": "approve",
 *   "reason": ""
 * }
 */
export function buildVouchApprovePayload(vouchId: string) {
  return {
    vouch_id: vouchId,
    action: 'approve',
    reason: '',
  };
}

/**
 * Body sent to POST /api/vouch_action (reject)
 *
 * {
 *   "vouch_id": 1,
 *   "action": "reject",
 *   "reason": "I do not recognise this person as an alumni member."
 * }
 */
export function buildVouchRejectPayload(vouchId: string, reason?: string) {
  return {
    vouch_id: vouchId,
    action: 'reject',
    reason: reason || '',
  };
}

// ═══════════════════════════════════════════════════════════════════════════
// INBOUND RESPONSES (Backend → Frontend)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Maps a single raw vouch object from backend into PendingVouch.
 *
 * Backend structure:
 * {
 *   "vouch_id": 1,
 *   "status": "pending",
 *   "created_at": "2026-04-07 10:00:00",
 *   "user_id": 12,
 *   "fullname": "John Smith",
 *   "email": "john@example.com",
 *   "name_in_school": "John Doe",
 *   "nick_name": "JD",
 *   "residential_address": "12 Palm Avenue, Lagos",
 *   "graduation_year": "2010",
 *   "department": "Science",
 *   "phone": "08012345678"
 * }
 */
export function mapBackendVouchToPending(raw: any): PendingVouch | null {
  try {
    const vouchId = raw?.vouch_id ?? raw?.id;
    if (!vouchId) return null;

    return {
      vouchId: String(vouchId),
      userId: String(raw?.user_id ?? ''),
      fullName: raw?.fullname ?? raw?.full_name ?? raw?.fullName ?? 'Unknown',
      email: raw?.email ?? '',
      nameInSchool: raw?.name_in_school ?? raw?.nameInSchool ?? '',
      nickName: raw?.nick_name ?? raw?.nickName ?? raw?.nickname ?? '',
      residentialAddress:
        raw?.residential_address ?? raw?.residentialAddress ?? raw?.location ?? '',
      graduationYear: safeParseInt(raw?.graduation_year ?? raw?.graduationYear) ?? 0,
      department: raw?.department ?? '',
      phone: raw?.phone ?? '',
      status: raw?.status ?? 'pending',
      createdAt: safeParseDate(raw?.created_at ?? raw?.createdAt),
    };
  } catch {
    return null;
  }
}

/**
 * Handles array responses and filters out nulls.
 *
 * Backend response:
 * {
 *   "status": 200,
 *   "pending": [...]
 * }
 */
export function mapVouchListResponse(rawResponse: any): PendingVouch[] {
  const list: any[] = Array.isArray(rawResponse)
    ? rawResponse
    : Array.isArray(rawResponse?.pending)
      ? rawResponse.pending
      : Array.isArray(rawResponse?.data)
        ? rawResponse.data
        : [];

  return list
    .map((item) => {
      try {
        return mapBackendVouchToPending(item);
      } catch {
        return null;
      }
    })
    .filter((item): item is PendingVouch => item !== null);
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════

function safeParseInt(value: any): number | undefined {
  if (value === null || value === undefined || value === '') return undefined;
  const parsed = parseInt(String(value), 10);
  return isNaN(parsed) ? undefined : parsed;
}

/**
 * Accepts ISO strings, Unix timestamps (seconds), or returns a fallback.
 */
function safeParseDate(value: any): string {
  if (!value) return new Date().toISOString();

  // Already an ISO-looking string
  if (typeof value === 'string' && value.includes('T')) return value;

  // Unix timestamp (seconds)
  const asInt = parseInt(String(value), 10);
  if (!isNaN(asInt) && asInt > 1_000_000_000) {
    return new Date(asInt * 1000).toISOString();
  }

  // Try native Date parsing as last resort
  const parsed = new Date(value);
  return isNaN(parsed.getTime()) ? new Date().toISOString() : parsed.toISOString();
}
