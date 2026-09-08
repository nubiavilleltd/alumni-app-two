import {
  hasAnnouncementMarker,
  serializeAnnouncementDescription,
  stripAnnouncementMarker,
  SYSTEM_ANNOUNCEMENT_MARKER,
} from '@/shared/utils/announcementVisibility';

/** Compatibility aliases for the event announcement bridge. */
export const EVENT_ANNOUNCEMENT_TAG = '__system:show-in-announcements';
export const EVENT_ANNOUNCEMENT_MARKER = SYSTEM_ANNOUNCEMENT_MARKER;

type EventDateFields = {
  startDate?: string;
  endDate?: string;
  startTime?: string;
  endTime?: string;
};

/** Returns true once the event's end date/time has passed. */
export function isEventPast({ startDate, endDate, startTime, endTime }: EventDateFields) {
  const dateValue = endDate || startDate;
  if (!dateValue) return false;

  const datePart = dateValue.slice(0, 10);
  const [year, month, day] = datePart.split('-').map(Number);
  if (![year, month, day].every(Number.isFinite)) return false;

  const [hours = 23, minutes = 59] = (endTime || '23:59').split(':').map(Number);
  const endTimestamp = new Date(year, month - 1, day, hours, minutes, 59).getTime();

  return Number.isFinite(endTimestamp) && Date.now() > endTimestamp;
}

export const hasEventAnnouncementMarker = hasAnnouncementMarker;
export const stripEventAnnouncementMarker = stripAnnouncementMarker;

export const serializeEventDescription = serializeAnnouncementDescription;
