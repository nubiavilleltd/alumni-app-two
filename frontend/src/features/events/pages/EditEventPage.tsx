// features/events/pages/EditEventPage.tsx
// MODIFIED: Added banner image upload, uses shared DeleteConfirmModal,
// uses EVENT_ROUTES, uses toast for errors.

import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Icon } from '@iconify/react';
import { Trash2 } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import Button from '@/shared/components/ui/Button';
import { useEvent, useUpdateEvent, useDeleteEvent } from '../hooks/useEvents';
import { mapEventToUpdatePayload } from '../api/adapters/event.adapter';
import { DeleteConfirmModal } from '../components/DeleteConfirmModal';
import { toast } from '@/shared/components/ui/Toast';
import { EVENT_ROUTES } from '../routes';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { TimePicker } from '@/shared/components/ui/input/TimePicker';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { ROUTES } from '@/shared/constants/routes';
import { ADMIN_ROUTES } from '@/features/admin/routes';
import {
  UpdateEventFormData,
  updateEventSchema,
  updatePastEventSchema,
} from '../schemas/event.schema';
import {
  useEventSurveyForms,
  useUpsertEventSurveyForm,
  useArchiveEventSurveyForm,
} from '../hooks/useEventSurvey';
import {
  EventRegistrationFormBuilderModal,
  type EventRegistrationFormDraft,
  type EventRegistrationQuestionDraft,
} from '../components/EventRegistrationFormBuilderModal';
import { EventRegistrationQuestionField } from '../components/EventRegistrationQuestionField';
import {
  clearStoredEventSurveyAvailability,
  EVENT_SURVEY_TAG,
  setStoredEventSurveyAvailability,
} from '../lib/eventSurveyAvailability';
import { eventsService } from '../services/event.service';
import { useEventStatus } from '../hooks/useEventStatus';
import {
  eventFormDateInputClassName,
  eventFormFieldControlClassName,
  eventFormFieldLabelClassName,
  eventFormInputTextClassName,
  eventFormSelectClassName,
  eventFormSelectControlClassName,
  eventFormTextareaClassName,
  eventFormTimePickerClassName,
  eventFormUploadDropzoneClassName,
} from '../constants/eventFormStyles';
import { Pencil } from 'lucide-react';
import { canManageEvents } from '@/shared/permissions/event.permission';

type LocalRegistrationFormDraft = {
  localId: string;
  firebaseId?: string;
  draft: EventRegistrationFormDraft;
};

function createLocalRegistrationFormId() {
  return `event-registration-draft-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function toSurveyQuestionPayload(question: EventRegistrationQuestionDraft, index: number) {
  return {
    id: question.id,
    label: question.label.trim(),
    type: question.type,
    required: question.required,
    placeholder: question.placeholder.trim(),
    options: question.options.map((option) => option.trim()).filter(Boolean),
    maxSelections: question.type === 'checkbox' ? question.maxSelections : null,
    order: index + 1,
  };
}

// ─── Options ──────────────────────────────────────────────────────────────────

const visibilityOptions = [
  { label: 'Public — everyone can see', value: 'public' },
  { label: 'Members only', value: 'members' },
  { label: 'Premium members only', value: 'premium' },
];

const statusOptions = [
  { label: 'Upcoming', value: 'upcoming' },
  { label: 'Active', value: 'active' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Completed', value: 'completed' },
];

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function EditEventPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const currentUser = useIdentityStore((state) => state.user);

  const { data: event, isLoading } = useEvent(id || '');
  const { isPast } = useEventStatus(event);

  const { data: surveyForms, isLoading: isSurveyFormsLoading } = useEventSurveyForms(id || '');

  const updateEvent = useUpdateEvent();
  const deleteEvent = useDeleteEvent();
  const upsertSurveyForm = useUpsertEventSurveyForm();
  const archiveSurveyForm = useArchiveEventSurveyForm();

  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [bannerPreview, setBannerPreview] = useState<string>('');
  const [isStatusManuallyChanged, setIsStatusManuallyChanged] = useState(false);

  const [isRegistrationBuilderOpen, setIsRegistrationBuilderOpen] = useState(false);
  const [registrationFormDrafts, setRegistrationFormDrafts] = useState<
    LocalRegistrationFormDraft[]
  >([]);
  const [activeRegistrationFormId, setActiveRegistrationFormId] = useState<string | null>(null);
  const [deletedFormIds, setDeletedFormIds] = useState<string[]>([]);
  const [isSavingSurveyForms, setIsSavingSurveyForms] = useState(false);
  const [hasInitializedSurveyForms, setHasInitializedSurveyForms] = useState(false);

  const schema = isPast ? updatePastEventSchema : updateEventSchema;

  // const {
  //   register,
  //   handleSubmit,
  //   setValue,
  //   watch,
  //   reset,
  //   trigger,
  //   formState: { errors },
  // } = useForm<UpdateEventFormData>({
  //   resolver: zodResolver(updateEventSchema) as any,
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    trigger,
    formState: { errors },
  } = useForm<UpdateEventFormData>({
    resolver: zodResolver(schema) as any,
    mode: 'onChange',
    defaultValues: {
      title: '',
      description: '',
      location: '',
      start_date: '',
      end_date: '',
      start_time: '',
      end_time: '',
      visibility: 'public',
      status: 'upcoming',
      show_in_announcements: false,
    },
  });

  const visibility = watch('visibility');
  const status = watch('status');
  const startDate = watch('start_date');
  const endDate = watch('end_date');
  const todayDate = new Date().toISOString().split('T')[0];

  useEffect(() => {
    if (surveyForms && !hasInitializedSurveyForms) {
      if (surveyForms.length > 0) {
        setRegistrationFormDrafts(
          surveyForms.map((form) => ({
            localId: createLocalRegistrationFormId(),
            firebaseId: form.id,
            draft: {
              name: form.name,
              questions: form.questions.map((q) => ({
                id: q.id,
                label: q.label,
                type: q.type,
                required: q.required,
                placeholder: q.placeholder,
                options: q.options,
                maxSelections: q.maxSelections,
              })),
            },
          })),
        );
      }
      setHasInitializedSurveyForms(true);
    }
  }, [surveyForms, hasInitializedSurveyForms]);

  // Populate form when event loads
  useEffect(() => {
    if (event) {
      reset({
        title: event.title,
        description: event.description,
        location: event.location,
        start_date: event.startDate.split('T')[0],
        end_date: event?.endDate?.split('T')[0],
        start_time: event.startTime || '',
        end_time: event.endTime || '',
        visibility: (event as any).visibility || 'public',
        status: (event as any).status || 'upcoming',
        show_in_announcements: event.showInAnnouncements ?? false,
      });
      // Pre-fill banner preview with current image if one exists
      if (event.image) setBannerPreview(event.image);
      setIsStatusManuallyChanged(false);
    }
  }, [event, reset]);

  useEffect(() => {
    if (!startDate || isStatusManuallyChanged) return;

    const selectedDate = new Date(startDate);
    const today = new Date();

    // Normalize today
    today.setHours(0, 0, 0, 0);

    let computedStatus: 'upcoming' | 'active' | 'completed';

    if (selectedDate > today) {
      computedStatus = 'upcoming';
    } else if (selectedDate.getTime() === today.getTime()) {
      computedStatus = 'active';
    } else {
      computedStatus = 'completed';
    }

    setValue('status', computedStatus);
  }, [startDate, isStatusManuallyChanged, setValue]);

  useEffect(() => {
    if (isPast) return;
    const subscription = watch((values, { name }) => {
      if (!name) return;

      const fieldName = name as keyof UpdateEventFormData;

      const triggerMap: Partial<
        Record<keyof UpdateEventFormData, Array<keyof UpdateEventFormData>>
      > = {
        start_date: ['end_date', 'start_time', 'end_time'],
        end_date: ['start_date', 'end_time'],
        start_time: ['end_time'],
        end_time: ['start_time'],
      };

      const deps = triggerMap[fieldName];
      if (!deps) return;

      const fieldsToTrigger = deps.filter((field) => {
        const val = values[field];
        return typeof val === 'string' && val.length > 0;
      });

      if (fieldsToTrigger.length) void trigger(fieldsToTrigger as any);
    });

    return () => subscription.unsubscribe();
  }, [trigger, watch]);

  const handleImageChange = (files: File[], previews: string[]) => {
    if (files.length > 0) {
      setBannerFile(files[0]);
      setBannerPreview(previews[0]);
    } else {
      // User cleared the image
      setBannerFile(null);
      setBannerPreview(previews[0] ?? '');
    }
  };

  const onSubmit = async (data: UpdateEventFormData) => {
    if (!id) return;

    const payload = mapEventToUpdatePayload(id, {
      title: data.title,
      description: data.description,
      location: data.location,
      start_date: data.start_date,
      end_date: data.end_date,
      start_time: data.start_time,
      end_time: data.end_time,
      visibility: data.visibility,
      status: data.status,
      show_in_announcements: data.show_in_announcements,
      event_banner: bannerFile,
    });

    try {
      await updateEvent.mutateAsync({ id, payload });

      if (registrationFormDrafts.length > 0 || deletedFormIds.length > 0) {
        setIsSavingSurveyForms(true);

        try {
          // 1. Archive deleted forms
          for (const formId of deletedFormIds) {
            await archiveSurveyForm.mutateAsync({ eventId: id, formId });
          }

          // 2. Upsert active forms
          for (const [index, item] of registrationFormDrafts.entries()) {
            await upsertSurveyForm.mutateAsync({
              eventId: id,
              formId: item.firebaseId,
              eventTitleSnapshot: data.title,
              name: item.draft.name.trim(),
              sortOrder: index + 1,
              questions: item.draft.questions.map((question, questionIndex) =>
                toSurveyQuestionPayload(question, questionIndex),
              ),
            });
          }

          const hasActiveForms = registrationFormDrafts.length > 0;
          setStoredEventSurveyAvailability(id, hasActiveForms);

          // If there are forms, make sure the event has the tag
          if (hasActiveForms) {
            const existingTags = (event as any)?.tags || [];
            if (!existingTags.includes(EVENT_SURVEY_TAG)) {
              try {
                await eventsService.update(
                  id,
                  mapEventToUpdatePayload(id, {
                    ...data,
                    tags: [...existingTags, EVENT_SURVEY_TAG],
                  } as any),
                );
              } catch {
                toast.error(
                  'Event and registration forms were saved, but survey metadata sync failed. Survey questions may be unavailable on other devices until this is fixed.',
                );
              }
            }
          } else {
            // Optional: remove the tag if there are no forms left
            const existingTags = (event as any)?.tags || [];
            if (existingTags.includes(EVENT_SURVEY_TAG)) {
              try {
                await eventsService.update(
                  id,
                  mapEventToUpdatePayload(id, {
                    ...data,
                    tags: existingTags.filter((t: string) => t !== EVENT_SURVEY_TAG),
                  } as any),
                );
              } catch {
                // ignore
              }
            }
          }
        } catch (surveyError: any) {
          toast.error(
            surveyError?.message ||
              'Event updated, but we could not save the additional info forms.',
          );
        } finally {
          setIsSavingSurveyForms(false);
        }
      }

      navigate(EVENT_ROUTES.DETAIL(id));
    } catch (error: any) {
      setIsSavingSurveyForms(false);
      toast.fromError(error);
    }
  };

  const handleDelete = () => {
    if (!id) return;
    deleteEvent.mutate(id, {
      onSuccess: () => navigate(EVENT_ROUTES.ROOT),
      onError: (error: any) => {
        setShowDeleteModal(false);
        toast.fromError(error);
      },
    });
  };

  const canUserManageEvents = canManageEvents(currentUser);

  // ── Access guard ──────────────────────────────────────────────────────────

  if (!canUserManageEvents) {
    return (
      <>
        <SEO title="Access Denied" />
        <section className="section">
          <div className="container-custom text-center">
            <Icon icon="mdi:lock-outline" className="w-16 h-16 text-red-400 mx-auto mb-4" />
            <h1 className="text-3xl font-bold mb-4">Access Denied</h1>
            <p className="text-gray-600 mb-6">You don't have permission to edit events.</p>
            <Button onClick={() => navigate(EVENT_ROUTES.ROOT)}>Back to Events</Button>
          </div>
        </section>
      </>
    );
  }

  // ── Loading ───────────────────────────────────────────────────────────────

  if (isLoading) {
    const breadcrumbItems = [
      { label: 'Home', href: ROUTES.HOME },
      { label: 'Admin Dashboard', href: ADMIN_ROUTES.DASHBOARD },
      { label: 'Events', href: ADMIN_ROUTES.EVENTS },
      { label: 'Edit Event' },
    ];
    return (
      <>
        <SEO title="Loading..." />
        <Breadcrumbs items={breadcrumbItems} />
        <section className="section">
          <div className="container-custom max-w-3xl">
            <div className="card p-6 animate-pulse space-y-4">
              <div className="h-8 bg-gray-200 rounded w-1/3" />
              <div className="h-12 bg-gray-200 rounded" />
              <div className="h-24 bg-gray-200 rounded" />
              <div className="h-12 bg-gray-200 rounded" />
            </div>
          </div>
        </section>
      </>
    );
  }

  // ── Not found ─────────────────────────────────────────────────────────────

  if (!event) {
    return (
      <section className="section">
        <div className="container-custom text-center">
          <Icon icon="mdi:calendar-alert" className="w-16 h-16 text-red-400 mx-auto mb-4" />
          <h1 className="text-3xl font-bold mb-4">Event Not Found</h1>
          <Button onClick={() => navigate(EVENT_ROUTES.ROOT)}>Back to Events</Button>
        </div>
      </section>
    );
  }

  const breadcrumbItems = [
    { label: 'Home', href: ROUTES.HOME },
    { label: 'Admin Dashboard', href: ADMIN_ROUTES.DASHBOARD },
    { label: 'Events', href: ADMIN_ROUTES.EVENTS },
    { label: event.title, href: ADMIN_ROUTES.EVENT_DETAIL(event.id) },
    { label: 'Edit' },
  ];

  return (
    <>
      <SEO title={`Edit — ${event.title}`} description="Edit event details" />
      <Breadcrumbs items={breadcrumbItems} />

      <section className="section bg-[#F8F8F7]">
        <div className="container-custom ">
          <div className="mb-8 flex items-center justify-between">
            <div>
              <h1 className="text-3xl md:text-4xl font-bold italic mb-2">Edit Event</h1>
              <p className="text-gray-500 text-sm">Update event details</p>
            </div>
            <button
              title="Delete Event"
              type="button"
              onClick={() => setShowDeleteModal(true)}
              className="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 border border-red-200 rounded-lg transition-colors"
            >
              <Icon icon="mdi:trash-can-outline" className="w-4 h-4" />
              <span className="hidden sm:inline">Delete Event</span>
            </button>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="card p-6 space-y-6">
            {isPast && (
              <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 mb-4">
                <Icon
                  icon="mdi:information-outline"
                  className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5"
                />
                <p className="text-sm text-amber-800">
                  This event has already taken place. You can still update the title, event details,
                  location, and banner image for record-keeping purposes. Date, time, and status
                  fields are locked.
                </p>
              </div>
            )}
            {/* ── Core ────────────────────────────────────────────────── */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <FormInput
                label="Event Title"
                id="title"
                required
                placeholder="e.g. Annual Alumni Reunion 2026"
                error={errors.title?.message}
                labelClassName={eventFormFieldLabelClassName}
                controlClassName={eventFormFieldControlClassName}
                inputClassName={eventFormInputTextClassName}
                {...register('title')}
              />

              <FormInput
                label="Location"
                id="location"
                required
                placeholder="Venue name, city"
                icon="mdi:map-marker-outline"
                error={errors.location?.message}
                labelClassName={eventFormFieldLabelClassName}
                controlClassName={eventFormFieldControlClassName}
                inputClassName={eventFormInputTextClassName}
                {...register('location')}
              />

              <DatePicker
                label="Start Date"
                id="event_date"
                disabled={isPast}
                required
                min={todayDate}
                max={endDate || undefined}
                error={errors.start_date?.message}
                labelClassName={eventFormFieldLabelClassName}
                inputClassName={eventFormDateInputClassName}
                value={startDate}
                onValueChange={(val) =>
                  setValue('start_date', val, {
                    shouldValidate: true,
                    shouldDirty: true,
                    shouldTouch: true,
                  })
                }
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <DatePicker
                label="End Date"
                id="end_date"
                disabled={isPast}
                min={startDate || todayDate}
                error={errors.end_date?.message}
                labelClassName={eventFormFieldLabelClassName}
                inputClassName={eventFormDateInputClassName}
                value={endDate}
                onValueChange={(val) =>
                  setValue('end_date', val, {
                    shouldValidate: true,
                    shouldDirty: true,
                    shouldTouch: true,
                  })
                }
              />
              <TimePicker
                label="Start Time"
                id="start_time"
                disabled={isPast}
                error={errors.start_time?.message}
                className={eventFormTimePickerClassName}
                value={watch('start_time')}
                onValueChange={(val) =>
                  setValue('start_time', val, {
                    shouldValidate: true,
                    shouldDirty: true,
                    shouldTouch: true,
                  })
                }
              />

              <TimePicker
                label="End Time"
                id="end_time"
                disabled={isPast}
                error={errors.end_time?.message}
                className={eventFormTimePickerClassName}
                value={watch('end_time')}
                onValueChange={(val) =>
                  setValue('end_time', val, {
                    shouldValidate: true,
                    shouldDirty: true,
                    shouldTouch: true,
                  })
                }
              />
            </div>

            <TextareaInput
              label="Event Details"
              id="description"
              required
              rows={4}
              placeholder="Describe the event..."
              error={errors.description?.message}
              labelClassName={eventFormFieldLabelClassName}
              textareaClassName={eventFormTextareaClassName}
              {...register('description')}
            />

            {/* ── Banner image ────────────────────────────────────────── */}
            <div>
              <label className="mb-2 block text-sm font-medium text-[#8f8b84]">
                Event Banner (Optional)
                <span className="ml-2 text-xs font-normal text-[#b3afa8]">
                  {bannerPreview ? 'Current image shown — upload to replace' : 'Optional'}
                </span>
              </label>
              <ImageUpload
                previews={bannerPreview ? [bannerPreview] : []}
                onChange={handleImageChange}
                hint="PNG or JPG — max 2 MB. Recommended: 1200×600 px"
                multiple={false}
                dropzoneClassName={eventFormUploadDropzoneClassName}
              />
            </div>

            {/* ── Classification ──────────────────────────────────────── */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <SelectInput
                label="Who is this event for"
                name="visibility"
                disabled={isPast}
                required
                options={visibilityOptions}
                value={visibility}
                onChange={(e) => setValue('visibility', e.target.value as any)}
                error={errors.visibility?.message}
                labelClassName={eventFormFieldLabelClassName}
                className={eventFormSelectClassName}
                controlClassName={eventFormSelectControlClassName}
              />
              <SelectInput
                label="Status"
                name="status"
                disabled={isPast}
                required
                options={statusOptions}
                value={status}
                // onChange={(e) => setValue('status', e.target.value as any)}
                onChange={(e) => {
                  setIsStatusManuallyChanged(true);
                  setValue('status', e.target.value as any);
                }}
                error={errors.status?.message}
                labelClassName={eventFormFieldLabelClassName}
                className={eventFormSelectClassName}
                controlClassName={eventFormSelectControlClassName}
              />
              {/* <FormInput
                label="Max Attendees"
                id="max_attendees"
                type="number"
                placeholder="0 = unlimited"
                hint="0 = no limit"
                error={errors.max_attendees?.message}
                {...register('max_attendees', { valueAsNumber: true })}
              /> */}
            </div>

            <label
              className={`flex items-start gap-3 rounded-2xl border border-primary-100 bg-primary-50/50 px-4 py-4 ${isPast ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
            >
              <input
                type="checkbox"
                disabled={isPast}
                className="mt-1 h-4 w-4 accent-primary-500"
                {...register('show_in_announcements')}
              />
              <span>
                <span className="block text-sm font-semibold text-gray-800">
                  Show this event in announcements
                </span>
                <span className="mt-1 block text-xs leading-5 text-gray-500">
                  {isPast
                    ? 'Past events cannot be shown in the public announcements feed.'
                    : 'This keeps the event as the single source of truth while also displaying it in the public announcements feed.'}
                </span>
              </span>
            </label>

            {!isPast && (
              <div className="rounded-[1.75rem] border border-primary-100 bg-primary-50/50 px-5 py-5">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div className="max-w-4xl">
                    <p className="text-[1.15rem] font-semibold leading-tight tracking-[0.01em] text-gray-800 md:text-[1.35rem]">
                      Would you like to request additional info from attendees regarding this event?
                    </p>
                    <p className="mt-2 text-sm text-gray-500 md:text-base">
                      Add optional registration forms here if this event needs extra attendee
                      details like meal choice, dress code, logistics, or special requests.
                    </p>
                  </div>

                  <button
                    type="button"
                    onClick={() => {
                      setActiveRegistrationFormId(null);
                      setIsRegistrationBuilderOpen(true);
                    }}
                    className="inline-flex  items-center justify-center rounded-full border-2 border-primary-500 px-6 text-sm font-semibold text-primary-500 transition-colors hover:bg-primary-50 md:px-7 md:text-base"
                  >
                    {registrationFormDrafts.length > 0
                      ? 'Add another section'
                      : 'Yes, request info'}
                  </button>
                </div>

                {registrationFormDrafts.length > 0 ? (
                  <div className="mt-6 space-y-5">
                    {registrationFormDrafts.map((item, index) => (
                      <div
                        key={item.localId}
                        className="rounded-[1.6rem] border border-primary-100 bg-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
                      >
                        <div className="flex flex-col gap-3 border-b border-primary-100 pb-4 md:flex-row md:items-start md:justify-between">
                          <div className="max-w-3xl">
                            <p className="mt-1 text-lg font-semibold text-gray-900">
                              {item.draft.name}
                            </p>
                          </div>

                          <div className="flex flex-wrap items-center gap-3 self-start md:self-center">
                            <button
                              type="button"
                              onClick={() => {
                                setActiveRegistrationFormId(item.localId);
                                setIsRegistrationBuilderOpen(true);
                              }}
                              className="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white px-3 py-1.5 text-sm font-semibold text-primary-500 transition-colors hover:bg-primary-50"
                            >
                              <Pencil className="h-4 w-4" />
                              Edit form
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                setRegistrationFormDrafts((current) =>
                                  current.filter((draftItem) => draftItem.localId !== item.localId),
                                );
                                if (item.firebaseId) {
                                  setDeletedFormIds((prev) => [...prev, item.firebaseId!]);
                                }
                                if (activeRegistrationFormId === item.localId) {
                                  setActiveRegistrationFormId(null);
                                }
                              }}
                              className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-red-500"
                            >
                              <Trash2 className="h-4 w-4" />
                              Remove
                            </button>
                          </div>
                        </div>

                        <div className="mt-4 space-y-4">
                          {item.draft.questions.map((question, questionIndex) => (
                            <EventRegistrationQuestionField
                              key={question.id}
                              question={question}
                              index={questionIndex}
                              mode="preview"
                            />
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : null}
              </div>
            )}

            <div className="flex gap-3 pt-4">
              <Button type="submit" loading={updateEvent.isPending || isSavingSurveyForms}>
                Save Changes
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate(EVENT_ROUTES.DETAIL(id!))}
              >
                Cancel
              </Button>
            </div>
          </form>
        </div>
      </section>

      <EventRegistrationFormBuilderModal
        isOpen={isRegistrationBuilderOpen}
        value={
          activeRegistrationFormId === null
            ? null
            : (registrationFormDrafts.find((item) => item.localId === activeRegistrationFormId)
                ?.draft ?? null)
        }
        onClose={() => {
          setIsRegistrationBuilderOpen(false);
          setActiveRegistrationFormId(null);
        }}
        onSave={(draft) => {
          setRegistrationFormDrafts((current) => {
            if (activeRegistrationFormId) {
              return current.map((item) =>
                item.localId === activeRegistrationFormId ? { ...item, draft } : item,
              );
            }

            return [...current, { localId: createLocalRegistrationFormId(), draft }];
          });
          setIsRegistrationBuilderOpen(false);
          setActiveRegistrationFormId(null);
          toast.success('Registration form updated in this event draft.');
        }}
      />

      {showDeleteModal && (
        <DeleteConfirmModal
          title={event.title}
          isDeleting={deleteEvent.isPending}
          onConfirm={handleDelete}
          onCancel={() => setShowDeleteModal(false)}
        />
      )}
    </>
  );
}
