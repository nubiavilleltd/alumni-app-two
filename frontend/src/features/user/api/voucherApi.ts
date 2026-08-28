/**
 * ============================================================================
 * VOUCHER API SERVICE
 * ============================================================================
 *
 * API layer for voucher-based approvals.
 * Used by members who vouched for new registrations.
 *
 * Integration status:
 *   ✅ getPendingVouches  → POST /api/voucher_pending
 *   ✅ approveVouch       → POST /api/vouch_action  { action: "approve" }
 *   ✅ rejectVouch        → POST /api/vouch_action  { action: "reject" }
 *
 * ============================================================================
 */

import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import {
  buildVouchApprovePayload,
  buildVouchRejectPayload,
  mapVouchListResponse,
} from './adapters/voucher.adapter';

// ═══════════════════════════════════════════════════════════════════════════
// TYPES
// ═══════════════════════════════════════════════════════════════════════════

export interface PendingVouch {
  vouchId: string;
  userId: string;
  fullName: string;
  email: string;
  graduationYear: number;
  nameInSchool?: string;
  nickName?: string;
  residentialAddress?: string;
  department: string;
  phone: string;
  status: string;
  createdAt: string; // ISO date string
}

export interface VouchActionResult {
  success: boolean;
  message: string;
}

// ═══════════════════════════════════════════════════════════════════════════
// API
// ═══════════════════════════════════════════════════════════════════════════

export const voucherApi = {
  /**
   * Fetch pending vouches for the current user.
   *
   * POST /api/voucher_pending
   *
   * Response:
   * {
   *   "status": 200,
   *   "pending": [...]
   * }
   */
  async getPendingVouches(): Promise<PendingVouch[]> {
    const { data } = await apiClient.post(API_ENDPOINTS.VOUCHER.GET_PENDING);
    return mapVouchListResponse(data);
  },

  /**
   * Approve a pending vouch.
   *
   * POST /api/vouch_action
   * {
   *   "vouch_id": 1,
   *   "action": "approve",
   *   "reason": ""
   * }
   *
   * Expected 200:
   * {
   *   "status": 200,
   *   "message": "Account approved by voucher"
   * }
   */
  async approveVouch(vouchId: string): Promise<VouchActionResult> {
    try {
      const payload = buildVouchApprovePayload(vouchId);
      const { data } = await apiClient.post(API_ENDPOINTS.VOUCHER.ACTION, payload);

      return {
        success: true,
        message: data?.message ?? 'Account approved successfully',
      };
    } catch (error: any) {
      const message =
        error.response?.data?.message ??
        error.response?.data?.error ??
        'Approval failed. Please try again.';

      // 401 = Invalid token
      if (error.response?.status === 401) {
        throw new Error('Session expired. Please log in again.');
      }

      // 404 = Vouch not found (belongs to another voucher)
      if (error.response?.status === 404) {
        throw new Error('Vouch record not found. It may belong to another voucher.');
      }

      // 409 = Already actioned
      if (error.response?.status === 409) {
        throw new Error('This vouch has already been actioned.');
      }

      throw new Error(message);
    }
  },

  /**
   * Reject a pending vouch with a reason.
   *
   * POST /api/vouch_action
   * {
   *   "vouch_id": 1,
   *   "action": "reject",
   *   "reason": "I do not recognise this person as an alumni member."
   * }
   *
   * Expected 200:
   * {
   *   "status": 200,
   *   "message": "Vouch denied. Admin can still approve the account."
   * }
   */
  async rejectVouch(vouchId: string, reason?: string): Promise<VouchActionResult> {
    try {
      const payload = buildVouchRejectPayload(vouchId, reason);
      const { data } = await apiClient.post(API_ENDPOINTS.VOUCHER.ACTION, payload);

      return {
        success: true,
        message: data?.message ?? 'Vouch rejected. Admin can still approve the account.',
      };
    } catch (error: any) {
      const message =
        error.response?.data?.message ??
        error.response?.data?.error ??
        'Rejection failed. Please try again.';

      if (error.response?.status === 401) {
        throw new Error('Session expired. Please log in again.');
      }

      if (error.response?.status === 404) {
        throw new Error('Vouch record not found. It may belong to another voucher.');
      }

      if (error.response?.status === 409) {
        throw new Error('This vouch has already been actioned.');
      }

      throw new Error(message);
    }
  },
};
