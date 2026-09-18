// features/alumni/types/alumni.types.ts

import type { PrivacySettings } from '@/features/authentication/types/auth.types';

export interface Alumni {
  // ── Relational keys ──────────────────────────────────────────────────────
  id: string;
  memberId: string;
  chapterId?: string;
  role?: string;

  // ── Identity ──────────────────────────────────────────────────────────────
  slug: string;
  name: string;
  email: string;

  // ── Background ────────────────────────────────────────────────────────────────
  graduationYear: number;
  nameInSchool: string;
  nickName: string;
  houseColor?: string;

  // ── Contact ───────────────────────────────────────────────────────────────
  whatsappPhone: string;
  alternativePhone?: string;
  hasEmail?: boolean;
  hasWhatsapp?: boolean;
  hasAlternativePhone?: boolean;

  // ── Profile ───────────────────────────────────────────────────────────────
  photo: string;
  bio: string;
  birthDate?: string;

  // ── Location ──────────────────────────────────────────────────────────────
  location: string;
  city?: string;
  zone?: string;
  area?: string;
  state?: string;
  residentialAddress?: string;

  // ── Professional ──────────────────────────────────────────────────────────
  position: string;
  company: string;
  employmentStatus?: string;
  occupations?: string[];
  industrySectors?: string[];
  yearsOfExperience?: number;

  // ── Social Links ──────────────────────────────────────────────────────────
  linkedin?: string;
  twitter?: string;
  tiktok?: string;
  facebook?: string;
  website?: string;
  instagram?: string;

  // ── Status ────────────────────────────────────────────────────────────────
  isCoordinator: boolean;
  isVolunteer?: boolean;
  isApproved: boolean;
  isEmailVerified: boolean;
  isActive: boolean;
  isVisible: boolean;

  // ── Timestamps ────────────────────────────────────────────────────────────
  createdAt?: Date;
  updatedAt?: Date;
  lastLogin?: Date;

  // ═══════════════════════════════════════════════════════════════════════
  // ✅ NEW: Privacy Settings
  // ═══════════════════════════════════════════════════════════════════════
  privacy?: PrivacySettings;
}
