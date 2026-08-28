import type { RegisterDetailsFormValues } from '../../types/auth.types';
import { safeParseInt } from '@/lib/utils/adapters';
import { formatOptionalNigerianPhoneNumber } from '@/shared/utils/nigerianPhoneNumber';

// ─────────────────────────────────────────────────────────────
// Registration → Backend
// ─────────────────────────────────────────────────────────────

export function mapRegistrationPayload(values: RegisterDetailsFormValues) {
  const payload = {
    // Basic
    first_name: values.otherNames,
    last_name: values.surname,
    email: values.email,

    // Contact
    phone: formatOptionalNigerianPhoneNumber(values.whatsappPhone),

    // Background
    name_in_school: values.nameInSchool,
    nick_name: values.nickName,
    graduation_year: String(values.graduationYear),

    voucher_id: values.voucherId,

    // System
    user_role: 'alumni',
    chapter_id: '1',
    year: new Date().getFullYear().toString(),
    department: '',

    // Extended profile defaults
    alternative_phone: '',
    birth_date: '',
    house_color: '',
    is_coordinator: '0',
    residential_address: values.residentialAddress,
    area: '',
    // state: values.state,
    city: values.city,
    employment_status: '',
    occupation: '',
    industry_sector: '',
    years_of_experience: '',
    is_volunteer: '0',
  };

  if (!values.isSocialSignup) {
    return {
      ...payload,
      password: values.password,
    };
  }

  return {
    ...payload,
    provider: 'google',
  };
}

// ─────────────────────────────────────────────────────────────
// Backend → Registration Response
// ─────────────────────────────────────────────────────────────

export function mapRegistrationResponse(raw: unknown) {
  const d = raw as Record<string, any>;

  return {
    status: 'verification_required' as const,

    message: d.message || d.msg || 'Verification code sent to your email',

    expiresInMinutes: safeParseInt(d.expiresInMinutes) ?? safeParseInt(d.expires_in_minutes) ?? 10,

    userId: d.user_id,

    draft: {
      fullName: `${d.first_name || d.otherNames || ''} ${d.last_name || d.surname || ''}`.trim(),

      email: d.email || '',

      phoneNumber: d.phone || d.phoneNumber || '',

      graduationYear: safeParseInt(d.graduation_year) ?? safeParseInt(d.graduationYear) ?? 0,
    },
  };
}

// ─────────────────────────────────────────────────────────────
// Email Verification → Backend
// ─────────────────────────────────────────────────────────────

export function mapVerificationPayload(email: string, code: string, userId: string) {
  return {
    email,
    verify_code: code,
    user_id: userId,
  };
}

// ─────────────────────────────────────────────────────────────
// Backend → Verification Response
// ─────────────────────────────────────────────────────────────

export function mapVerificationResponse(raw: unknown) {
  const d = raw as Record<string, any>;

  return {
    status: 'pending_admin_approval' as const,

    message: d.message || d.msg || 'Email verified. Awaiting admin approval.',

    draft: {
      fullName:
        d.full_name || d.fullName || `${d.first_name || ''} ${d.last_name || ''}`.trim() || '',

      email: d.email || '',

      phoneNumber: d.phone || d.phoneNumber || '',

      graduationYear: safeParseInt(d.graduation_year) ?? safeParseInt(d.graduationYear) ?? 0,

      userId: d.user_id,
    },
  };
}
