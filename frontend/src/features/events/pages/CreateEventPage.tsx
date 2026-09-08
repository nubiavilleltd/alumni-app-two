// features/events/pages/CreateEventPage.tsx
// MODIFIED: Added status field, improved validation, uses EVENT_ROUTES,
// uses currentUser.id (not memberId) for backend payload.

import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Icon } from '@iconify/react';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import Button from '@/shared/components/ui/Button';
import { useCreateEvent } from '../hooks/useEvents';
import { mapEventToCreatePayload, mapEventToUpdatePayload } from '../api/adapters/event.adapter';
import { toast } from '@/shared/components/ui/Toast';
import { EVENT_ROUTES } from '../routes';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { useRequireSignIn } from '@/features/authentication/hooks/useRequireSignIn';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { TimePicker } from '@/shared/components/ui/input/TimePicker';
import { ROUTES } from '@/shared/constants/routes';
import { ADMIN_ROUTES } from '@/features/admin/routes';
import { useUpsertEventSurveyForm } from '../hooks/useEventSurvey';
import { eventsService } from '../services/event.service';
import {
  clearStoredEventSurveyAvailability,
  EVENT_SURVEY_TAG,
  setStoredEventSurveyAvailability,
} from '../lib/eventSurveyAvailability';
import {
  EventRegistrationFormBuilderModal,
  type EventRegistrationFormDraft,
  type EventRegistrationQuestionDraft,
} from '../components/EventRegistrationFormBuilderModal';
import { EventRegistrationQuestionField } from '../components/EventRegistrationQuestionField';
import { AUTH_ROUTES } from '@/features/authentication/routes';
import { CreateEventFormData, createEventSchema } from '../schemas/event.schema';
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
import { canManageEvents } from '@/shared/permissions/event.permission';

type LocalRegistrationFormDraft = {
  localId: string;
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
  // { label: 'Premium members only', value: 'premium' },
];

// Status on create: admin may want to log a completed past event retroactively.
const statusOptions = [
  { label: 'Upcoming', value: 'upcoming' },
  { label: 'Active', value: 'active' },
  { label: 'Completed', value: 'completed' },
];

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CreateEventPage() {
  const requireSignIn = useRequireSignIn();
  const navigate = useNavigate();
  const { data: currentUser, isLoading } = useCurrentUser();
  const createEvent = useCreateEvent();
  const upsertSurveyForm = useUpsertEventSurveyForm();

  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [bannerPreview, setBannerPreview] = useState<string>('');
  const [isRegistrationBuilderOpen, setIsRegistrationBuilderOpen] = useState(false);
  const [registrationFormDrafts, setRegistrationFormDrafts] = useState<
    LocalRegistrationFormDraft[]
  >([]);
  const [activeRegistrationFormId, setActiveRegistrationFormId] = useState<string | null>(null);
  const [isSavingSurveyForms, setIsSavingSurveyForms] = useState(false);

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    trigger,
    formState: { errors, touchedFields },
  } = useForm<CreateEventFormData>({
    resolver: zodResolver(createEventSchema) as any,
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
      // max_attendees: 0,
    },
  });

  const visibility = watch('visibility');
  const status = watch('status');
  const startDate = watch('start_date');
  const endDate = watch('end_date');
  const todayDate = new Date().toISOString().split('T')[0];

  useEffect(() => {
    const subscription = watch((values, { name }) => {
      if (!name) return;

      const fieldName = name as keyof CreateEventFormData;

      const triggerMap: Partial<
        Record<keyof CreateEventFormData, Array<keyof CreateEventFormData>>
      > = {
        start_date: ['start_time', 'end_date', 'end_time'],
        end_date: ['end_time'],
        start_time: ['end_time'],
        end_time: ['start_time'],
      };

      const deps = triggerMap[fieldName];
      if (!deps) return;

      // Only trigger siblings that already have a value
      const fieldsToTrigger = deps.filter((field) => {
        const val = values[field as keyof CreateEventFormData];
        return typeof val === 'string' && val.length > 0;
      });

      if (fieldsToTrigger.length) trigger(fieldsToTrigger as any);
    });

    return () => subscription.unsubscribe();
  }, [watch, trigger]);

  const handleImageChange = (files: File[], previews: string[]) => {
    if (files.length > 0) {
      setBannerFile(files[0]);
      setBannerPreview(previews[0]);
    } else {
      setBannerFile(null);
      setBannerPreview(previews[0] ?? '');
    }
  };

  const onSubmit = async (data: CreateEventFormData) => {
    if (!currentUser?.id) {
      requireSignIn({ message: 'You must be logged in to create events.' });
      return;
    }

    const payload = mapEventToCreatePayload(
      {
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
        // max_attendees: data.max_attendees,
        event_banner: bannerFile,
      },
      currentUser.id, // ← backend numeric ID, not memberId
      currentUser.chapterId,
    );

    try {
      const createdEvent = await createEvent.mutateAsync(payload);

      if (registrationFormDrafts.length > 0) {
        setIsSavingSurveyForms(true);

        try {
          for (const [index, item] of registrationFormDrafts.entries()) {
            await upsertSurveyForm.mutateAsync({
              eventId: createdEvent.id,
              eventTitleSnapshot: createdEvent.title || data.title,
              name: item.draft.name.trim(),
              sortOrder: index + 1,
              questions: item.draft.questions.map((question, questionIndex) =>
                toSurveyQuestionPayload(question, questionIndex),
              ),
            });
          }

          setStoredEventSurveyAvailability(createdEvent.id, true);

          try {
            await eventsService.update(
              createdEvent.id,
              mapEventToUpdatePayload(createdEvent.id, {
                title: data.title,
                description: data.description,
                location: data.location,
                start_date: data.start_date,
                end_date: data.end_date,
                start_time: data.start_time,
                end_time: data.end_time,
                visibility: data.visibility,
                show_in_announcements: data.show_in_announcements,
                // max_attendees: data.max_attendees,
                status: data.status,
                tags: [EVENT_SURVEY_TAG],
              }),
            );
          } catch {
            toast.error(
              'Event and registration forms were saved, but survey metadata sync failed. Survey questions may be unavailable on other devices until this is fixed.',
            );
          }
        } catch (surveyError: any) {
          clearStoredEventSurveyAvailability(createdEvent.id);
          toast.error(
            surveyError?.message ||
              'Event created, but we could not save the additional info forms. Please try again from the event management area.',
          );
        } finally {
          setIsSavingSurveyForms(false);
        }
      } else {
        setStoredEventSurveyAvailability(createdEvent.id, false);

        // The create endpoint may ignore the temporary tags bridge. Re-send the
        // marker through the existing event-management update endpoint so the
        // public announcements feed can read it after a fresh fetch.
        if (data.show_in_announcements) {
          try {
            await eventsService.update(
              createdEvent.id,
              mapEventToUpdatePayload(createdEvent.id, {
                title: data.title,
                description: data.description,
                location: data.location,
                start_date: data.start_date,
                end_date: data.end_date,
                start_time: data.start_time,
                end_time: data.end_time,
                visibility: data.visibility,
                status: data.status,
                show_in_announcements: true,
                tags: [],
              }),
            );
          } catch {
            toast.error(
              'Event created, but we could not sync its announcements visibility. Please edit the event and try again.',
            );
          }
        }
      }

      navigate(EVENT_ROUTES.ROOT);
    } catch (error: any) {
      setIsSavingSurveyForms(false);
      toast.fromError(error);
    }
  };

  const canUserManageEvents = canManageEvents(currentUser);

  if (!canUserManageEvents) {
    return (
      <>
        <SEO title="Access Denied" />
        <section className="section">
          <div className="container-custom text-center">
            <Icon icon="mdi:lock-outline" className="w-16 h-16 text-red-400 mx-auto mb-4" />
            <h1 className="text-3xl font-bold mb-4">Access Denied</h1>
            <p className="text-gray-600 mb-6">You don't have permission to create events.</p>
            <Button onClick={() => navigate(EVENT_ROUTES.ROOT)}>Back to Events</Button>
          </div>
        </section>
      </>
    );
  }

  const breadcrumbItems = [
    { label: 'Home', href: ROUTES.HOME },
    { label: 'Admin Dashboard', href: ADMIN_ROUTES.DASHBOARD },
    { label: 'Events', href: ADMIN_ROUTES.EVENTS },
    { label: 'Create Event' },
  ];

  const activeRegistrationFormDraft =
    activeRegistrationFormId === null
      ? null
      : (registrationFormDrafts.find((item) => item.localId === activeRegistrationFormId)?.draft ??
        null);

  return (
    <>
      <SEO title="Create Event" description="Create a new event" />
      <Breadcrumbs items={breadcrumbItems} />

      <section className="section bg-[#F8F8F7]">
        <div className="container-custom">
          <div className="mb-8">
            <h1 className="text-3xl md:text-4xl font-bold mb-2">Create Event</h1>
            <p className="text-gray-500 text-sm">Add a new event to the alumni calendar</p>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="card p-6 space-y-6">
            {/* ── Core fields ─────────────────────────────────────────── */}

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
                // style={{backgroundColor:"#f5f4f0"}}
              />

              <FormInput
                label="Location"
                id="location"
                required
                placeholder="Venue name, city"
                // icon={MapPin}
                error={errors.location?.message}
                labelClassName={eventFormFieldLabelClassName}
                controlClassName={eventFormFieldControlClassName}
                inputClassName={eventFormInputTextClassName}
                {...register('location')}
              />

              <DatePicker
                label="Start Date"
                id="event_date"
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
                required
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
                Event Banner
                <span className="ml-2 text-xs font-normal text-[#b3afa8]">Optional</span>
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
                label="Who is this event for?"
                name="visibility"
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
                required
                options={statusOptions}
                value={status}
                onChange={(e) => setValue('status', e.target.value as any)}
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

            <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-primary-100 bg-primary-50/50 px-4 py-4">
              <input
                type="checkbox"
                className="mt-1 h-4 w-4 accent-primary-500"
                {...register('show_in_announcements')}
              />
              <span>
                <span className="block text-sm font-semibold text-gray-800">
                  Show this event in announcements
                </span>
                <span className="mt-1 block text-xs leading-5 text-gray-500"></span>
              </span>
            </label>

            <div className="rounded-[1.75rem] px-5 py-5">
              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="max-w-4xl">
                  <p className="text-[1.15rem] font-semibold leading-tight tracking-[0.01em] text-gray-800 md:text-[1.35rem]">
                    Would you like to request additional info from attendees regarding this event?
                  </p>
                  <button
                    type="button"
                    onClick={() => {
                      setActiveRegistrationFormId(null);
                      setIsRegistrationBuilderOpen(true);
                    }}
                    className="inline-flex mt-2 items-center justify-center rounded-full border-2 border-primary-500 px-6 text-sm font-semibold text-primary-500 transition-colors hover:bg-primary-50 md:px-7 md:text-base"
                  >
                    {registrationFormDrafts.length > 0
                      ? 'Add another section'
                      : 'Yes, request info'}
                  </button>
                </div>
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
                            <Icon icon="mdi:pencil-outline" className="h-4 w-4" />
                            Edit form
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setRegistrationFormDrafts((current) =>
                                current.filter((draftItem) => draftItem.localId !== item.localId),
                              );
                              if (activeRegistrationFormId === item.localId) {
                                setActiveRegistrationFormId(null);
                              }
                            }}
                            className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-red-500"
                          >
                            <Icon icon="mdi:delete-outline" className="h-4 w-4" />
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

            <div className="flex gap-3 pt-2">
              <Button type="submit" loading={createEvent.isPending || isSavingSurveyForms}>
                Create Event
              </Button>
              {/* <Button type="button" variant="outline" onClick={() => navigate(EVENT_ROUTES.ROOT)}>
                Cancel
              </Button> */}
            </div>
          </form>
        </div>
      </section>

      <EventRegistrationFormBuilderModal
        isOpen={isRegistrationBuilderOpen}
        value={activeRegistrationFormDraft}
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
          toast.success('Registration form added to this event draft.');
        }}
      />
    </>
  );
}
