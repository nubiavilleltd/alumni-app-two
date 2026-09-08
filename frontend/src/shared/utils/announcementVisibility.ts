/**
 * Temporary frontend-only bridge until content types have a first-class
 * show-in-announcements field in the backend.
 */
export const SYSTEM_ANNOUNCEMENT_MARKER = '[[system:show-in-announcements]]';

export function hasAnnouncementMarker(value: unknown): boolean {
  return typeof value === 'string' && value.toLowerCase().includes(SYSTEM_ANNOUNCEMENT_MARKER);
}

export function stripAnnouncementMarker(value: string): string {
  const markerPattern = new RegExp(
    SYSTEM_ANNOUNCEMENT_MARKER.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    'gi',
  );

  return value
    .replace(markerPattern, '')
    .replace(/\n{3,}/g, '\n\n')
    .trimEnd();
}

export function serializeAnnouncementDescription(
  description: string,
  showInAnnouncements = false,
): string {
  const cleanDescription = stripAnnouncementMarker(description);
  if (!showInAnnouncements) return cleanDescription;

  return `${cleanDescription}${cleanDescription ? '\n\n' : ''}${SYSTEM_ANNOUNCEMENT_MARKER}`;
}
