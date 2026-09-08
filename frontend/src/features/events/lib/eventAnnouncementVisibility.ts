import {
  hasAnnouncementMarker,
  serializeAnnouncementDescription,
  stripAnnouncementMarker,
  SYSTEM_ANNOUNCEMENT_MARKER,
} from '@/shared/utils/announcementVisibility';

/** Compatibility aliases for the event announcement bridge. */
export const EVENT_ANNOUNCEMENT_TAG = '__system:show-in-announcements';
export const EVENT_ANNOUNCEMENT_MARKER = SYSTEM_ANNOUNCEMENT_MARKER;

export const hasEventAnnouncementMarker = hasAnnouncementMarker;
export const stripEventAnnouncementMarker = stripAnnouncementMarker;

export const serializeEventDescription = serializeAnnouncementDescription;
