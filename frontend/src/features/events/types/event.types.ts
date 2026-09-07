export type Event = {
  // ── Identity ─────────────────────────────────────────────────────────
  id: string; // ➕ NEW
  slug: string;
  title: string;

  // ── Timing ────────────────────────────────────────────────────────────
  // date: string;
  startDate: string;
  endDate?: string;
  startTime: string; // ➕ NEW
  endTime?: string; // ➕ NEW

  // ── Content ───────────────────────────────────────────────────────────
  description: string;
  content: string;
  image: string;

  // ── Location ──────────────────────────────────────────────────────────
  location: string;
  isVirtual: boolean;
  virtualLink?: string; // ➕ NEW
  attire: string;

  // ── Classification ────────────────────────────────────────────────────
  category: string;
  tags: string[];
  featured: boolean;
  /** Temporary bridge flag derived from EVENT_ANNOUNCEMENT_TAG. */
  showInAnnouncements?: boolean;
  status?: 'draft' | 'published' | 'cancelled' | 'completed'; // ➕ NEW

  // ── Registration ──────────────────────────────────────────────────────
  capacity?: number; // ➕ NEW
  allowGuests?: boolean; // ➕ NEW
  attendeeCount?: number;
  hasRegistrationQuestions?: boolean | null;

  // ── Relations ─────────────────────────────────────────────────────────
  createdBy?: string;
  registrations?: string[];

  // ── Timestamps ────────────────────────────────────────────────────────
  createdAt: string; // ➕ NEW
  publishedAt?: string; // ➕ NEW
  updatedAt?: string; // ➕ NEW

  // ── Legacy ────────────────────────────────────────────────────────────
  type?: string; // @deprecated - kept for backward compatibility

  rsvpStatus?: 'going' | 'maybe' | 'not_going' | null;
};
