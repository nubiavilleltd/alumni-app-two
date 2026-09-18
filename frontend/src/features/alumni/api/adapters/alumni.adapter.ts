import type { Alumni } from '../../types/alumni.types';
import { generateSlug, safeParseInt, stringToBoolean, safeParseDate } from '@/lib/utils/adapters';
import { mapBackendPrivacyToFrontend } from '@/features/user/api/adapters/privacy.adapter';
import { mapActualUserRole } from '@/features/user/api/adapters/user.adapter';

// ─── Helpers (local, minimal) ───────────────────────────────────────────────

const safeString = (v: unknown, fallback = '') => (v ? String(v).trim() : fallback);

const optionalString = (v: unknown) => (v ? String(v).trim() : undefined);

const readAvailability = (v: unknown): boolean =>
  v === true || v === 1 || v === '1' || v === 'true';

const parseArray = (v: unknown): string[] | undefined => {
  if (!v) return undefined;
  if (Array.isArray(v)) return v;
  if (typeof v === 'string') {
    const arr = v
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    return arr.length ? arr : undefined;
  }
  return undefined;
};

/**
 * ✅ Generate initials avatar URL as fallback
 * This should ONLY be called when we truly don't have a photo
 */
function generateInitialsAvatar(name: string): string {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(
    name,
  )}&background=E5E7EB&color=6B7280&size=256`;
}

/**
 * ✅ FIXED: Determine photo URL with proper fallback logic
 *
 * Priority:
 * 1. Real uploaded photo from backend
 * 2. Initials avatar (only as last resort)
 */
function resolvePhotoUrl(avatarField: any, name: string): string {
  const BASE_URL = import.meta.env.VITE_API_BASE_URL;
  // Check if we have a real photo URL
  const hasRealPhoto =
    avatarField &&
    avatarField !== 'default.png' &&
    avatarField !== '' &&
    !String(avatarField).includes('ui-avatars.com');

  if (hasRealPhoto) {
    return `${BASE_URL}/${String(avatarField)}`;
  }

  // Fallback to initials avatar
  return generateInitialsAvatar(name);
}

// ─── Inbound ────────────────────────────────────────────────────────────────

/**
 * ✅ ENHANCED: Maps backend alumni data to frontend Alumni format
 *
 * NEW: Privacy settings support
 * - Maps backend privacy to frontend format
 * - Defaults to public privacy settings if not provided
 *
 * PHOTO HANDLING:
 * - Checks multiple possible avatar fields (d.avatar, profile.avatar)
 * - Only uses real uploaded photos, not "default.png"
 * - Falls back to initials avatar ONLY when no real photo exists
 */
export function mapBackendAlumniToFrontend(raw: unknown): Alumni {
  const d = raw as Record<string, any>;

  const profile = d.profile || {};

  const name = d.fullname || `${d.first_name || ''} ${d.last_name || ''}`.trim() || 'Unknown';
  const city = profile.city || d.city || '';

  // ✅ FIXED: Check multiple avatar sources and resolve properly
  const avatarField = d.avatar || profile.avatar || d.photo;

  // ═══════════════════════════════════════════════════════════════════════
  // ✅ NEW: Privacy mapping
  // ═══════════════════════════════════════════════════════════════════════

  return {
    id: String(d.id ?? ''),
    memberId: String(d.id ?? ''),
    slug: generateSlug(name, d.id ?? '', 'alumni'),

    name,
    // Public profile responses expose availability flags, never raw contacts.
    email: '',

    graduationYear: safeParseInt(d.graduation_year) ?? new Date().getFullYear(),
    nameInSchool: safeString(d.name_in_school),
    nickName: safeString(d.nick_name),
    houseColor: safeString(d.house_color),

    whatsappPhone: '',
    alternativePhone: undefined,
    hasEmail: readAvailability(d.has_email),
    hasWhatsapp: readAvailability(d.has_whatsapp ?? d.has_phone),
    hasAlternativePhone: readAvailability(d.has_alternative_phone),

    // ✅ FIXED: Use proper photo resolution logic
    photo: resolvePhotoUrl(avatarField, name),

    bio: safeString(d.bio),
    birthDate: optionalString(d.birth_date),

    city,
    location: city,
    zone: d.zone_name,
    area: optionalString(d.area),
    state: optionalString(d.state),
    residentialAddress: optionalString(d.residential_address),

    position: safeString(profile.current_position),
    company: safeString(profile.current_company),
    employmentStatus: optionalString(d.employment_status),

    occupations: parseArray(d.occupation),
    industrySectors: parseArray(d.industry_sector),

    yearsOfExperience: safeParseInt(d.years_of_experience),

    linkedin: optionalString(profile.linkedin),
    twitter: optionalString(profile.twitter),
    facebook: optionalString(profile.facebook),
    website: optionalString(profile.website),
    instagram: optionalString(profile.instagram),
    tiktok: optionalString(profile.tiktok),

    isCoordinator: stringToBoolean(d.is_coordinator) ?? false,
    isVolunteer: stringToBoolean(d.is_volunteer),
    isApproved: stringToBoolean(d.is_approved) ?? false,
    isEmailVerified: stringToBoolean(d.email_verified) ?? false,
    isActive: stringToBoolean(d.active) ?? false,
    isVisible: profile.is_visible !== false,
    // role: d.user_role === 'admin' ? 'admin' : 'member',
    role: mapActualUserRole(d.user_role),

    // ═══════════════════════════════════════════════════════════════════════
    // ✅ NEW: Privacy settings
    // ═══════════════════════════════════════════════════════════════════════
    privacy: mapBackendPrivacyToFrontend(profile),
  };
}

export function mapBackendAlumniList(raw: unknown): Alumni[] {
  if (!Array.isArray(raw)) return [];

  return raw
    .map((item) => {
      try {
        return mapBackendAlumniToFrontend(item);
      } catch (err) {
        console.error('Failed to map alumni item:', item, err);
        return null;
      }
    })
    .filter((a): a is Alumni => a !== null);
}
