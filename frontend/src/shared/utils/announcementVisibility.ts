/**
 * Temporary frontend-only bridge until content types have a first-class
 * show-in-announcements field in the backend.
 */
export const SYSTEM_ANNOUNCEMENT_MARKER = '[[system:show-in-announcements]]';

export function hasAnnouncementMarker(value: unknown): boolean {
  return typeof value === 'string' && value.toLowerCase().includes(SYSTEM_ANNOUNCEMENT_MARKER);
}

export function stripAnnouncementMarker(value: string): string {
  const markerIndex = value.toLowerCase().lastIndexOf(SYSTEM_ANNOUNCEMENT_MARKER);
  if (markerIndex < 0) return value;

  const trailingContent = value.slice(markerIndex + SYSTEM_ANNOUNCEMENT_MARKER.length).trim();
  if (trailingContent) return value;

  return value.slice(0, markerIndex).trimEnd();
}

export function serializeAnnouncementDescription(
  description: string,
  showInAnnouncements = false,
): string {
  const cleanDescription = stripAnnouncementMarker(description);
  if (!showInAnnouncements) return cleanDescription;

  return `${cleanDescription}${cleanDescription ? '\n\n' : ''}${SYSTEM_ANNOUNCEMENT_MARKER}`;
}
