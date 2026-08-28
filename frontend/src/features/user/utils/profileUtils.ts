// // features/user/utils/profileUtils.ts
// // Shared helpers used by both UserProfilePage and AlumniProfilePage.

// import {
//   employmentStatusOptions,
//   industrySectorOptions,
//   occupationOptions,
// } from '@/features/authentication/constants/profileOptions';
// import type { PrivacySettings } from '@/features/authentication/types/auth.types';

// // ─── Label + date helpers ─────────────────────────────────────────────────────

// export function resolveLabel(
//   value: string | undefined,
//   options: readonly { label: string; value: string }[],
// ): string | undefined {
//   if (!value) return undefined;
//   return options.find((o) => o.value === value)?.label ?? value;
// }

// export function formatDate(iso: string | undefined): string | undefined {
//   if (!iso) return undefined;
//   return new Date(iso).toLocaleDateString('en-US', {
//     year: 'numeric',
//     month: 'long',
//     day: 'numeric',
//   });
// }

// const GROUP_FIELD_MAP: Record<PrivacyGroup, (keyof PrivacySettings)[]> = {
//   photo: ['photo'],

//   socials: ['socials'],

//   employmentStatus: [
//     'employmentStatus',
//     'yearsOfExperience',
//   ],
//   yearsOfExperience: [
//     'yearsOfExperience',
//   ],

//   residentialAddress: [
//     'area',
//     'city',
//     'state',
//     "residentialAddress"
//   ],
//   area: [
//     'area',

//   ],
//   city: [

//     'city',

//   ],
//   state: [

//     'state',

//   ],

//   birthDate: [
//     'birthDate',
//   ],

//   whatsappPhone: [
//     'whatsappPhone',

//   ],
//   'alternativePhone': [
//     'alternativePhone',

//   ],

// };

// // ─── Privacy resolver ─────────────────────────────────────────────────────────
// //
// // Returns whether a privacy GROUP is visible to the viewer.
// // - Owner always sees everything.
// // - Visitor only sees fields where privacy is 'public'.

// export type PrivacyGroup = keyof PrivacySettings;

// // export function isGroupVisible(
// //   group: PrivacyGroup,
// //   privacy: PrivacySettings | undefined,
// //   isOwner: boolean,
// // ): boolean {
// //   if (isOwner) return true;
// //   if (!privacy) return false; // no settings = default all private for safety
// //   return privacy[group] === 'public';
// // }

// export function isGroupVisible(
//   group: PrivacyGroup,
//   privacy: PrivacySettings | undefined,
//   isOwner: boolean,
//   isSignedIn = false,
// ): boolean {
//   if (isOwner) return true;

//   if (!privacy) return false;

//   const fields = GROUP_FIELD_MAP[group];

//   return fields.some((field) => {
//     const visibility = privacy[field];

//     switch (visibility) {
//       case 'public':
//         return true;

//       case 'members':
//         return isSignedIn;

//       case 'private':
//         return false;

//       default:
//         return false;
//     }
//   });
// }

// // ─── Shared profile data builder ──────────────────────────────────────────────
// //
// // Accepts raw alumni or currentUser shaped data + privacy + viewer context.
// // Returns the ProfileInfoData shape consumed by ProfileInfoPanel.
// // Each field is either:
// //   - the resolved value        → visible & has data
// //   - ''  (empty string)        → visible but no data (owner only — shows blank)
// //   - undefined                 → hidden entirely (non-owner + private/missing)

// export interface RawProfileSource {
//   bio?: string;
//   fullName?: string;
//   nameInSchool?: string;
//   nickName?: string;
//   email?: string;
//   whatsappPhone?: string;
//   alternativePhone?: string;
//   birthDate?: string;
//   residentialAddress?: string;
//   area?: string;
//   state?: string;
//   city?: string;
//   zone?: string;
//   employmentStatus?: string;
//   occupations?: string[];
//   industrySectors?: string[];
//   yearsOfExperience?: string | number;
//   position?: string;
//   company?: string;
//   instagram?: string;
//   facebook?: string;
//   twitter?: string;
//   tiktok?: string;
//   linkedin?: string;
//   privacy?: PrivacySettings;
// }

// export interface ResolvedProfileData {
//   // About
//   bio?: string;

//   // Bio section
//   fullName?: string;
//   maidenName?: string;
//   nicknameInSchool?: string;
//   email?: string;
//   whatsapp?: string;
//   altPhone?: string;
//   dateOfBirth?: string;

//   // Address
//   streetAddress?: string;
//   area?: string;
//   state?: string;
//   city?: string;
//   zone?: string;

//   // Professional
//   employmentStatus?: string;
//   occupation?: string;
//   industrySector?: string;
//   yearsOfExperience?: string;

//   // Socials
//   instagram?: string;
//   facebook?: string;
//   twitter?: string;
//   tiktok?: string;
//   linkedin?: string;

//   // Derived
//   positionLine?: string;
// }

// // Sentinel: owner can see a field exists but has no value yet.
// // We use '' so FieldRow can distinguish "hide" (undefined) from "show empty" ('').
// const EMPTY = '';

// function ownerOrValue(isOwner: boolean, value: string | undefined): string | undefined {
//   if (value) return value;             // has data — always show
//   if (isOwner) return EMPTY;           // no data but owner — show blank slot
//   return undefined;                    // no data + not owner — hide
// }

// function visibleOrUndefined(
//   visible: boolean,
//   isOwner: boolean,
//   value: string | undefined,
// ): string | undefined {
//   if (!visible) {
//     // Private group: owner still sees it, others don't
//     if (isOwner) return value ?? EMPTY;
//     return undefined;
//   }
//   return ownerOrValue(isOwner, value);
// }

// export function buildProfileData(
//   src: RawProfileSource,
//   isOwner: boolean,
// ): ResolvedProfileData {
//   const p = src.privacy;

//   const canSeeWhatsapp      = isGroupVisible('whatsappPhone',      p, isOwner);
//   const canSeeAltPhone      = isGroupVisible('alternativePhone',   p, isOwner);
//   const canSeeBirthDate     = isGroupVisible('birthDate',          p, isOwner);
//   const canSeeAddress       = isGroupVisible('residentialAddress', p, isOwner);
//   const canSeeEmployment    = isGroupVisible('employmentStatus',   p, isOwner);
//   const canSeeSocials       = isGroupVisible('socials',            p, isOwner);
//   const canSeeYearsExp      = isGroupVisible('yearsOfExperience',  p, isOwner);

//   const occupationLabel = resolveLabel(src.occupations?.[0], occupationOptions);
//   const employmentLabel = resolveLabel(src.employmentStatus, employmentStatusOptions);
//   const industrySectorLabel = src.industrySectors
//     ?.map((s) => resolveLabel(s, industrySectorOptions))
//     .filter(Boolean)
//     .join(', ');
//   const occupationFull = src.occupations
//     ?.map((o) => resolveLabel(o, occupationOptions))
//     .filter(Boolean)
//     .join(', ');

//   const positionLine =
//     [
//       src.position || occupationLabel,
//       src.company ? `at ${src.company}` : undefined,
//     ]
//       .filter(Boolean)
//       .join(' ') || undefined;

//   // former name — only show if different from full name
//   const maidenName =
//     src.nameInSchool && src.nameInSchool !== src.fullName ? src.nameInSchool : undefined;

//   return {
//     bio: src.bio,

//     // These fields are always visible (name, nickname, email are not privacy-gated)
//     fullName:          ownerOrValue(isOwner, src.fullName),
//     maidenName:        ownerOrValue(isOwner, maidenName),
//     nicknameInSchool:  ownerOrValue(isOwner, src.nickName),
//     // email:             ownerOrValue(isOwner, src.email),

//     whatsapp:     visibleOrUndefined(canSeeWhatsapp,   isOwner, src.whatsappPhone),
//     altPhone:     visibleOrUndefined(canSeeAltPhone,   isOwner, src.alternativePhone),
//     dateOfBirth:  visibleOrUndefined(canSeeBirthDate,  isOwner, formatDate(src.birthDate)),

//     streetAddress: visibleOrUndefined(canSeeAddress, isOwner, src.residentialAddress),
//     area:          visibleOrUndefined(canSeeAddress, isOwner, src.area),
//     state:         visibleOrUndefined(canSeeAddress, isOwner, src.state),
//     city:          visibleOrUndefined(canSeeAddress, isOwner, src.city),
//     zone:          visibleOrUndefined(canSeeAddress, isOwner, src.zone),

//     employmentStatus:  visibleOrUndefined(canSeeEmployment, isOwner, employmentLabel),
//     occupation:        visibleOrUndefined(canSeeEmployment, isOwner, occupationFull),
//     industrySector:    visibleOrUndefined(canSeeEmployment, isOwner, industrySectorLabel),
//     yearsOfExperience: visibleOrUndefined(
//       canSeeYearsExp,
//       isOwner,
//       src.yearsOfExperience !== undefined ? String(src.yearsOfExperience) : undefined,
//     ),

//     instagram:  visibleOrUndefined(canSeeSocials, isOwner, src.instagram),
//     facebook:   visibleOrUndefined(canSeeSocials, isOwner, src.facebook),
//     twitter:    visibleOrUndefined(canSeeSocials, isOwner, src.twitter),
//     tiktok:     visibleOrUndefined(canSeeSocials, isOwner, src.tiktok),
//     linkedin:   visibleOrUndefined(canSeeSocials, isOwner, src.linkedin),
//     email:      visibleOrUndefined(canSeeSocials, isOwner, src.email),

//     positionLine,
//   };
// }

// features/user/utils/profileUtils.ts
//
// Shared helpers used by UserProfilePage, AlumniProfilePage, and AlumniDirectoryPage.
// Single source of truth for:
//   - Privacy group visibility
//   - Profile data building (privacy-aware)
//   - Label / date formatting
//   - Photo display resolution

import { Alumni } from '@/features/alumni/types/alumni.types';
import {
  employmentStatusOptions,
  industrySectorOptions,
  occupationOptions,
} from '@/features/authentication/constants/profileOptions';
import type { FieldVisibility, PrivacySettings } from '@/features/authentication/types/auth.types';

// ─── Label + date helpers ─────────────────────────────────────────────────────

export function resolveLabel(
  value: string | undefined,
  options: readonly { label: string; value: string }[],
): string | undefined {
  if (!value) return undefined;
  return options.find((o) => o.value === value)?.label ?? value;
}

export function formatDate(iso: string | undefined): string | undefined {
  if (!iso) return undefined;
  return new Date(iso).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export function parseFieldVisibility(value?: unknown) {
  if (!value) return {};

  if (typeof value === 'object') {
    return value as Record<string, unknown>;
  }

  if (typeof value !== 'string') {
    return {};
  }

  try {
    return JSON.parse(value);
  } catch {
    return {};
  }
}

export function normalizeFieldVisibility(
  value: unknown,
  fallback: FieldVisibility = 'private',
): FieldVisibility {
  if (value === 'members') {
    return 'members';
  }

  if (value === 'public' || value === true || value === 'true' || value === 1 || value === '1') {
    return 'public';
  }

  if (value === 'private' || value === false || value === 'false' || value === 0 || value === '0') {
    return 'private';
  }

  return fallback;
}

function isVisibilityAllowed(
  visibility: FieldVisibility | undefined,
  isOwner: boolean,
  isSignedIn = false,
): boolean {
  if (isOwner) return true;

  switch (visibility) {
    case 'public':
      return true;
    case 'members':
      return isSignedIn;
    case 'private':
      return false;
    default:
      return false;
  }
}

export function isFieldVisible(
  fieldOwner: Alumni,
  fieldName: keyof PrivacySettings,
  currentViewer: { memberId?: string | null } | null,
): boolean {
  const isOwner = currentViewer?.memberId === fieldOwner.memberId;

  return isGroupVisible(fieldName, fieldOwner.privacy, isOwner, Boolean(currentViewer?.memberId));
}

// ─── Photo display ────────────────────────────────────────────────────────────

export function resolveProfilePhoto(params: {
  photoUrl?: string | null;
  privacy?: PrivacySettings;
  photoVisibility?: FieldVisibility;
  isOwner: boolean;
  isSignedIn?: boolean;
}): string | undefined {
  const isVisible = isVisibilityAllowed(
    params.photoVisibility ?? params.privacy?.photo,
    params.isOwner,
    params.isSignedIn,
  );

  if (!isVisible) {
    return undefined;
  }

  return params.photoUrl ?? undefined;
}

/**
 * Resolve the photo URL to display given a privacy visibility flag.
 * Returns null when the photo is private — callers should render initials instead.
 */
export function getPhotoDisplay(photoUrl: string | undefined, isVisible: boolean): string | null {
  if (!isVisible) return null;
  return photoUrl ?? null;
}

// ─── Privacy group visibility ─────────────────────────────────────────────────
//
// Each privacy key in PrivacySettings maps to one or more DB fields.
// isGroupVisible checks whether ANY of those fields is visible to the viewer.

export type PrivacyGroup = keyof PrivacySettings;

const GROUP_FIELD_MAP: Record<PrivacyGroup, (keyof PrivacySettings)[]> = {
  photo: ['photo'],
  socials: ['socials'],
  //   employmentStatus:   ['employmentStatus', 'yearsOfExperience'],
  employmentStatus: ['employmentStatus'],
  yearsOfExperience: ['yearsOfExperience'],
  //   residentialAddress: ['residentialAddress', 'area', 'city', 'state'],
  residentialAddress: ['residentialAddress'],
  area: ['area'],
  city: ['city'],
  state: ['state'],
  birthDate: ['birthDate'],
  whatsappPhone: ['whatsappPhone'],
  alternativePhone: ['alternativePhone'],
};

/**
 * Returns whether a privacy GROUP is visible to the viewer.
 *
 * - Owner always sees everything.
 * - 'public' → visible to everyone.
 * - 'members' → visible only to signed-in users.
 * - 'private' → visible only to owner.
 */
export function isGroupVisible(
  group: PrivacyGroup,
  privacy: PrivacySettings | undefined,
  isOwner: boolean,
  isSignedIn = false,
): boolean {
  if (isOwner) return true;
  if (!privacy) return false;

  const fields = GROUP_FIELD_MAP[group];
  return fields.some((field) => {
    const visibility = privacy[field];

    if (!visibility) {
      return false;
    }

    return isVisibilityAllowed(visibility, isOwner, isSignedIn);
  });
}

/**
 * Convenience wrapper: resolve a typed value through a privacy group check.
 * Returns the value when visible, undefined when not.
 */
export function resolveVisibleField<T>(
  value: T,
  group: PrivacyGroup,
  privacy: PrivacySettings | undefined,
  isOwner: boolean,
  isSignedIn = false,
): T | undefined {
  return isGroupVisible(group, privacy, isOwner, isSignedIn) ? value : undefined;
}

// ─── Shared profile data builder ──────────────────────────────────────────────
//
// Accepts raw alumni-or-user shaped data + privacy + viewer context.
// Returns the ResolvedProfileData shape consumed by ProfileInfoPanel.
//
// Each field is one of:
//   string value  → visible and has data
//   ''            → visible but not yet filled in (owner-only placeholder)
//   undefined     → hidden entirely (non-owner + private/missing)

export interface RawProfileSource {
  bio?: string;
  fullName?: string;
  nameInSchool?: string;
  nickName?: string;
  email?: string;
  whatsappPhone?: string;
  alternativePhone?: string;
  birthDate?: string;
  residentialAddress?: string;
  area?: string;
  state?: string;
  city?: string;
  zone?: string;
  employmentStatus?: string;
  occupations?: string[];
  industrySectors?: string[];
  yearsOfExperience?: string | number;
  position?: string;
  company?: string;
  instagram?: string;
  facebook?: string;
  twitter?: string;
  tiktok?: string;
  linkedin?: string;
  privacy?: PrivacySettings;
}

export interface ResolvedProfileData {
  bio?: string;
  fullName?: string;
  maidenName?: string;
  nicknameInSchool?: string;
  email?: string;
  whatsapp?: string;
  altPhone?: string;
  dateOfBirth?: string;
  streetAddress?: string;
  area?: string;
  state?: string;
  city?: string;
  zone?: string;
  employmentStatus?: string;
  occupation?: string;
  industrySector?: string;
  yearsOfExperience?: string;
  instagram?: string;
  facebook?: string;
  twitter?: string;
  tiktok?: string;
  linkedin?: string;
  positionLine?: string;
}

// Owner sees a field label even when the value is missing; '' signals this.
const EMPTY = '';

function ownerOrValue(isOwner: boolean, value: string | undefined): string | undefined {
  if (value) return value; // has data — always show
  if (isOwner) return EMPTY; // no data but owner — show blank slot
  return undefined; // no data + not owner — hide entirely
}

function visibleOrUndefined(
  visible: boolean,
  isOwner: boolean,
  value: string | undefined,
): string | undefined {
  if (!visible) {
    // Private group: owner still sees their own value (or empty slot); others don't
    if (isOwner) return value ?? EMPTY;
    return undefined;
  }
  return ownerOrValue(isOwner, value);
}

export function buildProfileData(
  src: RawProfileSource,
  isOwner: boolean,
  isSignedIn = true,
): ResolvedProfileData {
  const p = src.privacy;

  const canSeeWhatsapp = isGroupVisible('whatsappPhone', p, isOwner, isSignedIn);
  const canSeeAltPhone = isGroupVisible('alternativePhone', p, isOwner, isSignedIn);
  const canSeeBirthDate = isGroupVisible('birthDate', p, isOwner, isSignedIn);
  const canSeeAddress = isGroupVisible('residentialAddress', p, isOwner, isSignedIn);
  const canSeeEmployment = isGroupVisible('employmentStatus', p, isOwner, isSignedIn);
  const canSeeSocials = isGroupVisible('socials', p, isOwner, isSignedIn);
  const canSeeYearsExp = isGroupVisible('yearsOfExperience', p, isOwner, isSignedIn);

  const occupationLabel = resolveLabel(src.occupations?.[0], occupationOptions);
  const employmentLabel = resolveLabel(src.employmentStatus, employmentStatusOptions);
  const industrySectorLabel = src.industrySectors
    ?.map((s) => resolveLabel(s, industrySectorOptions))
    .filter(Boolean)
    .join(', ');
  const occupationFull = src.occupations
    ?.map((o) => resolveLabel(o, occupationOptions))
    .filter(Boolean)
    .join(', ');

  const positionLine =
    [src.position || occupationLabel, src.company ? `at ${src.company}` : undefined]
      .filter(Boolean)
      .join(' ') || undefined;

  const maidenName =
    src.nameInSchool && src.nameInSchool !== src.fullName ? src.nameInSchool : undefined;

  return {
    bio: src.bio,

    // Always visible — not privacy-gated
    fullName: ownerOrValue(isOwner, src.fullName),
    maidenName: ownerOrValue(isOwner, maidenName),
    nicknameInSchool: ownerOrValue(isOwner, src.nickName),

    whatsapp: visibleOrUndefined(canSeeWhatsapp, isOwner, src.whatsappPhone),
    altPhone: visibleOrUndefined(canSeeAltPhone, isOwner, src.alternativePhone),
    dateOfBirth: visibleOrUndefined(canSeeBirthDate, isOwner, formatDate(src.birthDate)),

    streetAddress: visibleOrUndefined(canSeeAddress, isOwner, src.residentialAddress),
    area: visibleOrUndefined(canSeeAddress, isOwner, src.area),
    state: visibleOrUndefined(canSeeAddress, isOwner, src.state),
    city: visibleOrUndefined(canSeeAddress, isOwner, src.city),
    zone: visibleOrUndefined(canSeeAddress, isOwner, src.zone),

    employmentStatus: visibleOrUndefined(canSeeEmployment, isOwner, employmentLabel),
    occupation: visibleOrUndefined(canSeeEmployment, isOwner, occupationFull),
    industrySector: visibleOrUndefined(canSeeEmployment, isOwner, industrySectorLabel),
    yearsOfExperience: visibleOrUndefined(
      canSeeYearsExp,
      isOwner,
      src.yearsOfExperience !== undefined ? String(src.yearsOfExperience) : undefined,
    ),

    // Socials group — email included here per current ProfileInfoPanel grouping
    email: visibleOrUndefined(canSeeSocials, isOwner, src.email),
    instagram: visibleOrUndefined(canSeeSocials, isOwner, src.instagram),
    facebook: visibleOrUndefined(canSeeSocials, isOwner, src.facebook),
    twitter: visibleOrUndefined(canSeeSocials, isOwner, src.twitter),
    tiktok: visibleOrUndefined(canSeeSocials, isOwner, src.tiktok),
    linkedin: visibleOrUndefined(canSeeSocials, isOwner, src.linkedin),

    positionLine,
  };
}
