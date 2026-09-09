// features/events/schemas/event.schema.ts

import { z } from 'zod';

function parseTimeToMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number);
  return h * 60 + m;
}

function toLocalMidnight(dateStr: string): Date {
  const [y, mo, d] = dateStr.split('-').map(Number);
  return new Date(y, mo - 1, d);
}

const eventFieldsSchema = z.object({
  title: z.string().min(3, 'Event Title must be at least 3 characters'),
  description: z.string().min(10, 'Event Details must be at least 10 characters'),
  location: z.string().min(1, 'Location is required'),
  visibility: z.enum(['public', 'members', 'premium']),
  status: z.enum(['upcoming', 'active', 'completed']),
  start_date: z.string().min(1, 'Start date is required'),
  end_date: z.string().optional(),
  start_time: z.string().min(1, 'Start time is required'),
  end_time: z.string().optional(),
  event_banner: z.any().optional(),
  show_in_announcements: z.boolean().default(false),
});

const updateEventFieldsSchema = eventFieldsSchema.extend({
  // Editing may also be used to correct or cancel an event that has already passed.
  status: z.enum(['upcoming', 'active', 'cancelled', 'completed']),
  // Keep schedule fields optional for legacy records that were created without a time.
  start_date: z.string().optional(),
  start_time: z.string().optional(),
});

type EventScheduleData = {
  start_date?: string;
  end_date?: string;
  start_time?: string;
  end_time?: string;
};

function validateEventSchedule(
  data: EventScheduleData,
  ctx: z.RefinementCtx,
  options: { requireFutureSchedule: boolean },
) {
  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  // Guard: don't run any cross-field rule if start_date isn't filled yet.
  if (!data.start_date) return;

  const startDate = toLocalMidnight(data.start_date);

  // This rule belongs to creation only. Existing historical records must remain editable.
  if (options.requireFutureSchedule && startDate < todayStart) {
    ctx.addIssue({
      code: 'custom',
      path: ['start_date'],
      message: 'Start date cannot be in the past',
    });
  }

  if (data.end_date) {
    const endDate = toLocalMidnight(data.end_date);
    if (endDate < startDate) {
      ctx.addIssue({
        code: 'custom',
        path: ['end_date'],
        message: 'End date cannot be before start date',
      });
    }
  }

  // This rule also belongs to creation only. It should not prevent an admin from
  // saving a correction to an event whose scheduled time has already passed.
  const isStartToday = startDate.getTime() === todayStart.getTime();
  if (options.requireFutureSchedule && data.start_time && isStartToday) {
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    if (parseTimeToMinutes(data.start_time) <= currentMinutes) {
      ctx.addIssue({
        code: 'custom',
        path: ['start_time'],
        message: 'Start time must be later than the current time',
      });
    }
  }

  const isSameDay = !data.end_date || data.end_date === data.start_date;
  if (data.start_time && data.end_time && isSameDay) {
    if (parseTimeToMinutes(data.end_time) <= parseTimeToMinutes(data.start_time)) {
      ctx.addIssue({
        code: 'custom',
        path: ['end_time'],
        message: 'End time must be after start time',
      });
    }
  }
}

/** Creation schema: new events must be scheduled for now or the future. */
export const eventBaseSchema = eventFieldsSchema.superRefine((data, ctx) => {
  validateEventSchedule(data, ctx, { requireFutureSchedule: true });
});

/** Update schema: historical events remain editable, while date ordering stays valid. */
export const updateEventSchema = updateEventFieldsSchema.superRefine((data, ctx) => {
  validateEventSchedule(data, ctx, { requireFutureSchedule: false });
});

// Kept as a compatibility alias for any consumers that still import the old name.
export const updatePastEventSchema = updateEventSchema;

export const createEventSchema = eventBaseSchema;

export type CreateEventFormData = z.infer<typeof createEventSchema>;
export type UpdateEventFormData = z.infer<typeof updateEventSchema>;

// // ─── Schema ───────────────────────────────────────────────────────────────────

// const editEventSchema = z
//   .object({
//     title: z.string().min(3, 'Event Title must be at least 3 characters'),
//     description: z.string().min(10, 'Description must be at least 10 characters'),
//     location: z.string().min(2, 'Location is required'),
//     event_date: z.string().min(1, 'Event date is required'),
//     start_time: z.string().optional(),
//     end_time: z.string().optional(),
//     visibility: z.enum(['public', 'members', 'premium']),
//     // Edit allows all statuses including cancelled — admin may need to cancel an event
//     status: z.enum(['upcoming', 'active', 'cancelled', 'completed']),
//     // max_attendees: z.number({ error: 'Please enter a valid number' }).min(0).default(0),
//   })
//   .refine(
//     (data) => {
//       if (!data.event_date) return true;
//       const selectedDate = new Date(data.event_date);
//       const today = new Date();
//       today.setHours(0, 0, 0, 0);
//       return selectedDate >= today;
//     },
//     { message: 'Event date cannot be in the past', path: ['event_date'] },
//   )
//   .refine(
//     (d) => {
//       if (!d.start_time || !d.end_time) return true;
//       return d.end_time > d.start_time;
//     },
//     { message: 'End time must be after start time', path: ['end_time'] },
//   );

// type EditEventFormData = z.infer<typeof editEventSchema>;
