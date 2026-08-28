import { Icon } from '@iconify/react';
import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { Modal } from '@/shared/components/ui/Modal';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { useEvent } from '../hooks/useEvents';
import { useEventAttendees } from '../hooks/useEventAttendees';
import { useEventSurveySubmissionDetail, useEventSurveySubmissions } from '../hooks/useEventSurvey';
import { eventSurveyFunctionsApi } from '../api/firebase/survey.functions';
import type { EventAttendee } from '../api/adapters/event-attendees.adapter';
import { ROUTES } from '@/shared/constants/routes';
import { ADMIN_ROUTES } from '@/features/admin/routes';
import { useAllUsers } from '@/features/admin/hooks/useUserManagement';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import type { UserAccount } from '@/features/admin/api/adapters/user-management.adapter';
import {
  getStoredEventSurveyAvailability,
  setStoredEventSurveyAvailability,
} from '../lib/eventSurveyAvailability';
import type {
  EventSurveySubmissionFormView,
  EventSurveySubmissionListItem,
  EventSurveySubmissionQuestionView,
  FirebaseSurveyFormVersionRef,
  FirebaseSurveyStoredAnswer,
  GetEventSurveySubmissionDetailResponse,
} from '../api/firebase/survey.types';

function formatRegisteredAt(date?: string) {
  if (!date) return 'Recently registered';

  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function formatRegisteredDateForExport(date?: string) {
  if (!date) return '';

  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatRegisteredTimeForExport(date?: string) {
  if (!date) return '';

  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return '';

  return parsed.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  });
}

function initialsFor(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

function normalizeMatchValue(value?: string) {
  return String(value ?? '')
    .trim()
    .toLowerCase();
}

function buildUserLookup(users: UserAccount[], key: 'id' | 'email') {
  return new Map(
    users.map((user) => [normalizeMatchValue(user[key]), user] as const).filter(([value]) => value),
  );
}

function buildFallbackFormsFromRegistration(
  detail?: GetEventSurveySubmissionDetailResponse,
): EventSurveySubmissionFormView[] {
  if (!detail?.registration) {
    return [];
  }

  const answersByFormId = detail.registration.answers.reduce<
    Record<string, FirebaseSurveyStoredAnswer[]>
  >(
    (
      accumulator: Record<string, FirebaseSurveyStoredAnswer[]>,
      answer: FirebaseSurveyStoredAnswer,
    ) => {
      if (!accumulator[answer.formId]) {
        accumulator[answer.formId] = [];
      }

      accumulator[answer.formId].push(answer);
      return accumulator;
    },
    {},
  );

  return detail.registration.formVersions.map((formVersion: FirebaseSurveyFormVersionRef) => ({
    formId: formVersion.formId,
    formName: formVersion.formName,
    formVersionId: formVersion.formVersionId,
    formVersionNumber: formVersion.formVersionNumber,
    questions: (answersByFormId[formVersion.formId] ?? [])
      .slice()
      .sort((a: FirebaseSurveyStoredAnswer, b: FirebaseSurveyStoredAnswer) => a.order - b.order)
      .map((answer: FirebaseSurveyStoredAnswer) => ({
        id: answer.questionId,
        label: answer.questionLabel,
        type: answer.questionType,
        required: answer.required,
        placeholder: '',
        options: [],
        maxSelections: null,
        order: answer.order,
        value: answer.value,
      })),
  }));
}

function hasViewableSurveyResponse(submission?: EventSurveySubmissionListItem) {
  if (!submission) {
    return false;
  }

  // Older function responses may not include hasSurveyResponse yet.
  // If we matched a survey submission record at all, treat it as viewable.
  return submission.hasSurveyResponse ?? true;
}

function renderQuestionValue(question: EventSurveySubmissionQuestionView) {
  if (question.type === 'long_answer') {
    return (
      <div className="min-h-32 rounded-3xl bg-[#f7f5f2] px-4 py-3 text-sm text-accent-700">
        {typeof question.value === 'string' && question.value.trim()
          ? question.value
          : 'No response'}
      </div>
    );
  }

  if (question.type === 'multiple_choice') {
    const selectedValue = typeof question.value === 'string' ? question.value : '';

    if (question.options.length === 0) {
      return (
        <div className="rounded-3xl bg-[#f7f5f2] px-4 py-3 text-sm text-accent-700">
          {selectedValue || 'No response'}
        </div>
      );
    }

    return (
      <div className="space-y-2">
        {question.options.map((option) => {
          const isSelected = selectedValue === option;

          return (
            <div key={option} className="flex items-center gap-3 text-sm text-accent-700">
              <span
                className={`flex h-5 w-5 items-center justify-center rounded-full border ${
                  isSelected ? 'border-primary-500' : 'border-accent-300'
                }`}
              >
                {isSelected ? <span className="h-2.5 w-2.5 rounded-full bg-primary-500" /> : null}
              </span>
              <span>{option}</span>
            </div>
          );
        })}
      </div>
    );
  }

  if (question.type === 'checkbox') {
    const selectedValues = new Set(Array.isArray(question.value) ? question.value : []);

    if (question.options.length === 0) {
      return (
        <div className="rounded-3xl bg-[#f7f5f2] px-4 py-3 text-sm text-accent-700">
          {selectedValues.size > 0 ? Array.from(selectedValues).join(', ') : 'No response'}
        </div>
      );
    }

    return (
      <div className="space-y-2">
        {question.options.map((option) => {
          const isSelected = selectedValues.has(option);

          return (
            <div key={option} className="flex items-center gap-3 text-sm text-accent-700">
              <span
                className={`flex h-5 w-5 items-center justify-center rounded border ${
                  isSelected ? 'border-primary-500 bg-white text-primary-500' : 'border-accent-300'
                }`}
              >
                {isSelected ? <Icon icon="mdi:check" className="h-3.5 w-3.5" /> : null}
              </span>
              <span>{option}</span>
            </div>
          );
        })}
      </div>
    );
  }

  const value = Array.isArray(question.value)
    ? question.value.join(', ')
    : (question.value?.trim() ?? '');

  return (
    <div className="rounded-3xl bg-[#f7f5f2] px-4 py-3 text-sm text-accent-700">
      {value || 'No response'}
    </div>
  );
}

function formatSurveyAnswerForExport(question: EventSurveySubmissionQuestionView) {
  if (Array.isArray(question.value)) {
    return question.value.join(', ');
  }

  return typeof question.value === 'string' ? question.value.trim() : '';
}

function sanitizeFilename(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function escapeCsvCell(value: string) {
  return `"${value.replace(/"/g, '""')}"`;
}

function downloadCsvFile(filename: string, headers: string[], rows: Record<string, string>[]) {
  const csvRows = [
    headers.map(escapeCsvCell).join(','),
    ...rows.map((row) => headers.map((header) => escapeCsvCell(row[header] ?? '')).join(',')),
  ];

  const blob = new Blob(['\ufeff', csvRows.join('\r\n')], {
    type: 'text/csv;charset=utf-8;',
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function SurveyResponseModal({
  attendee,
  isLoading,
  detail,
  onClose,
}: {
  attendee: EventAttendee | null;
  isLoading: boolean;
  detail?: GetEventSurveySubmissionDetailResponse;
  onClose: () => void;
}) {
  const resolvedForms =
    detail?.forms && detail.forms.length > 0
      ? detail.forms
      : buildFallbackFormsFromRegistration(detail);

  return (
    <Modal
      isOpen={!!attendee}
      onClose={onClose}
      title={attendee ? `${attendee.fullName}'s Requested Info` : 'Requested Info'}
    >
      {isLoading ? (
        <div className="flex items-center gap-2 py-6 text-sm text-accent-500">
          <Icon icon="mdi:loading" className="h-4 w-4 animate-spin text-primary-500" />
          Loading requested information...
        </div>
      ) : resolvedForms.length ? (
        <div className="space-y-8">
          {resolvedForms.map((form: EventSurveySubmissionFormView) => (
            <section key={form.formId} className="space-y-4">
              <h3 className="text-2xl font-semibold text-accent-700">{form.formName}</h3>
              <div className="grid gap-5 md:grid-cols-2">
                {form.questions.map((question: EventSurveySubmissionQuestionView) => {
                  const isWide =
                    question.type === 'long_answer' ||
                    (question.type === 'checkbox' && question.options.length > 3);

                  return (
                    <div key={question.id} className={`space-y-2 ${isWide ? 'md:col-span-2' : ''}`}>
                      <p className="text-sm font-medium text-accent-700">{question.label}</p>
                      {renderQuestionValue(question)}
                    </div>
                  );
                })}
              </div>
            </section>
          ))}

          {detail?.registration.additionalInfo ? (
            <section className="space-y-2">
              <h3 className="text-lg font-semibold text-accent-700">Extra Note</h3>
              <div className="min-h-28 rounded-3xl bg-[#f7f5f2] px-4 py-3 text-sm text-accent-700">
                {detail.registration.additionalInfo}
              </div>
            </section>
          ) : null}
        </div>
      ) : (
        <div className="py-6 text-sm text-accent-500">
          No requested information was saved for this attendee.
        </div>
      )}
    </Modal>
  );
}

function AttendeeCard({
  attendee,
  hasSurveyResponse,
  onViewRequestedInfo,
}: {
  attendee: EventAttendee;
  hasSurveyResponse: boolean;
  onViewRequestedInfo: (attendee: EventAttendee) => void;
}) {
  return (
    <article className="rounded-2xl border border-accent-100 bg-white p-5 shadow-sm transition hover:border-primary-200 hover:shadow-md">
      <div className="flex min-w-0 items-center justify-between gap-4">
        <div className="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-100 to-accent-100 text-sm font-bold text-primary-700">
          {initialsFor(attendee.fullName)}
        </div>

        <div className="min-w-0 flex-1">
          <h2 className="truncate text-lg font-semibold text-accent-950">{attendee.fullName}</h2>
          <p className="mt-1 flex items-center gap-2 text-sm text-accent-500">
            <Icon
              icon="mdi:calendar-clock-outline"
              className="h-4 w-4 flex-shrink-0 text-accent-400"
            />
            <span>{formatRegisteredAt(attendee.registeredAt)}</span>
          </p>
          {attendee.graduationYear ? (
            <p className="mt-1 flex items-center gap-2 text-sm text-accent-500">
              <Icon icon="mdi:account-outline" className="h-4 w-4 flex-shrink-0 text-accent-400" />
              <span>Class of {attendee.graduationYear}</span>
            </p>
          ) : null}
        </div>

        {hasSurveyResponse ? (
          <button
            type="button"
            onClick={() => onViewRequestedInfo(attendee)}
            className="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-primary-200 bg-primary-50 text-primary-600 transition-colors hover:border-primary-300 hover:bg-primary-100"
            aria-label={`View requested information for ${attendee.fullName}`}
            title="View requested information"
          >
            <Icon icon="mdi:eye-outline" className="h-5 w-5" />
          </button>
        ) : null}
      </div>
    </article>
  );
}

function AttendeeCardSkeleton() {
  return (
    <div className="animate-pulse rounded-2xl border border-accent-100 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-4">
          <div className="h-14 w-14 rounded-full bg-accent-100" />
          <div className="space-y-2">
            <div className="h-5 w-40 rounded bg-accent-100" />
            <div className="h-4 w-52 rounded bg-accent-50" />
          </div>
        </div>
        <div className="h-6 w-16 rounded-full bg-accent-100" />
      </div>

      <div className="mt-4 space-y-2">
        <div className="h-4 w-44 rounded bg-accent-50" />
        <div className="h-4 w-36 rounded bg-accent-50" />
      </div>
    </div>
  );
}

export default function AttendeesPage() {
  const { id = '' } = useParams();
  const [selectedSurveyTarget, setSelectedSurveyTarget] = useState<{
    attendee: EventAttendee;
    submissionUserId: string;
  } | null>(null);
  const [isExporting, setIsExporting] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  const { data: event } = useEvent(id);
  const cachedSurveyAvailability = useMemo(() => getStoredEventSurveyAvailability(id), [id]);
  const { data: attendeeData, isLoading } = useEventAttendees(id, 'going');
  const registrationAttendees = useMemo(
    () => attendeeData?.attendees ?? [],
    [attendeeData?.attendees],
  );
  const shouldLoadUserProfiles = registrationAttendees.some((attendee) => !attendee.graduationYear);
  const { data: allUsers = [], isLoading: isLoadingUserProfiles } = useAllUsers({
    enabled: shouldLoadUserProfiles,
  });
  const usersById = useMemo(() => buildUserLookup(allUsers, 'id'), [allUsers]);
  const usersByEmail = useMemo(() => buildUserLookup(allUsers, 'email'), [allUsers]);
  const attendees = useMemo(
    () =>
      registrationAttendees.map((attendee) => {
        if (attendee.graduationYear) {
          return attendee;
        }

        const matchedUser =
          usersById.get(normalizeMatchValue(attendee.userId)) ??
          usersByEmail.get(normalizeMatchValue(attendee.email));

        if (!matchedUser?.graduationYear) {
          return attendee;
        }

        return {
          ...attendee,
          graduationYear: matchedUser.graduationYear,
        };
      }),
    [registrationAttendees, usersByEmail, usersById],
  );
  const filteredAttendees = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();

    if (!normalizedSearch) {
      return attendees;
    }

    return attendees.filter((attendee) => {
      const searchableValues = [
        attendee.fullName,
        attendee.email,
        attendee.phone,
        attendee.graduationYear ? String(attendee.graduationYear) : '',
      ]
        .join(' ')
        .toLowerCase();

      return searchableValues.includes(normalizedSearch);
    });
  }, [attendees, searchTerm]);
  const shouldAttemptSurveyLookup =
    !!id && attendees.length > 0 && cachedSurveyAvailability !== 'disabled';
  const {
    data: surveySubmissions = [],
    error: surveySubmissionsError,
    isLoading: isLoadingSurveySubmissions,
  } = useEventSurveySubmissions(id, shouldAttemptSurveyLookup);
  const {
    data: selectedSubmissionDetail,
    isLoading: isLoadingSubmissionDetail,
    error: selectedSubmissionDetailError,
  } = useEventSurveySubmissionDetail(
    id,
    selectedSurveyTarget?.submissionUserId || '',
    !!id && !!selectedSurveyTarget?.submissionUserId,
  );
  const surveySubmissionsByUserId = useMemo(
    () =>
      new Map(
        surveySubmissions.map(
          (submission) => [normalizeMatchValue(submission.userId), submission] as const,
        ),
      ),
    [surveySubmissions],
  );
  const surveySubmissionsByEmail = useMemo(
    () =>
      new Map(
        surveySubmissions.map(
          (submission) => [normalizeMatchValue(submission.userEmail), submission] as const,
        ),
      ),
    [surveySubmissions],
  );

  const getMatchedSubmission = (
    attendee: EventAttendee,
  ): EventSurveySubmissionListItem | undefined => {
    const byUserId = surveySubmissionsByUserId.get(normalizeMatchValue(attendee.userId));
    if (byUserId) {
      return byUserId;
    }

    const byEmail = surveySubmissionsByEmail.get(normalizeMatchValue(attendee.email));
    if (byEmail) {
      return byEmail;
    }

    return undefined;
  };

  useEffect(() => {
    console.log('[AttendeesPage] survey lookup gate', {
      eventId: id,
      eventHasRegistrationQuestions: event?.hasRegistrationQuestions ?? null,
      cachedSurveyAvailability,
      attendeeCount: attendees.length,
      profileLookupEnabled: shouldLoadUserProfiles,
      shouldAttemptSurveyLookup,
    });
  }, [
    attendees.length,
    cachedSurveyAvailability,
    event?.hasRegistrationQuestions,
    id,
    shouldLoadUserProfiles,
    shouldAttemptSurveyLookup,
  ]);

  useEffect(() => {
    if (id && surveySubmissions.length > 0) {
      setStoredEventSurveyAvailability(id, true);
    }
  }, [id, surveySubmissions.length]);

  useEffect(() => {
    console.log('[AttendeesPage] survey submission match debug', {
      eventId: id,
      attendees: attendees.map((attendee) => ({
        attendeeUserId: attendee.userId,
        attendeeEmail: attendee.email,
        matchedSubmission: (() => {
          const matchedSubmission = getMatchedSubmission(attendee);

          return matchedSubmission
            ? {
                userId: matchedSubmission.userId,
                userEmail: matchedSubmission.userEmail,
                hasSurveyResponse: matchedSubmission.hasSurveyResponse,
                willShowEyeIcon: hasViewableSurveyResponse(matchedSubmission),
              }
            : null;
        })(),
      })),
      surveySubmissions,
    });
  }, [attendees, id, surveySubmissions]);

  useEffect(() => {
    if (surveySubmissionsError) {
      console.error('[AttendeesPage] failed to load survey submissions', surveySubmissionsError);
    }
  }, [surveySubmissionsError]);

  useEffect(() => {
    if (selectedSubmissionDetailError) {
      console.error(
        '[AttendeesPage] failed to load survey submission detail',
        selectedSubmissionDetailError,
      );
    }
  }, [selectedSubmissionDetailError]);

  useEffect(() => {
    if (selectedSubmissionDetail) {
      console.log('[AttendeesPage] survey submission detail', selectedSubmissionDetail);
    }
  }, [selectedSubmissionDetail]);

  const pageTitle =
    (attendeeData?.eventTitle && attendeeData.eventTitle !== 'Unknown Event'
      ? attendeeData.eventTitle
      : event?.title) || 'Event attendees';
  const eventDate = formatDateRange(event?.startDate ?? attendeeData?.eventDate, event?.endDate);
  const totalCount = attendeeData?.goingCount ?? attendees.length;
  const breadcrumbItems = [
    { label: 'Home', href: ROUTES.HOME },
    { label: 'Admin Dashboard', href: ADMIN_ROUTES.DASHBOARD },
    { label: 'Events', href: ADMIN_ROUTES.EVENTS },
    ...(id ? [{ label: pageTitle, href: ADMIN_ROUTES.EVENT_DETAIL(id) }] : []),
    { label: 'Attendees' },
  ];

  async function handleExportAttendees() {
    if (!attendees.length || !id || isExporting) {
      return;
    }

    setIsExporting(true);

    try {
      const questionColumns: string[] = [];
      const questionColumnSet = new Set<string>();
      const incompleteSurveyExports: string[] = [];
      const rows = await Promise.all(
        attendees.map(async (attendee) => {
          const matchedSubmission = getMatchedSubmission(attendee);
          let detail: GetEventSurveySubmissionDetailResponse | undefined;

          if (matchedSubmission) {
            try {
              detail = await eventSurveyFunctionsApi.getEventSurveySubmissionDetail({
                eventId: id,
                userId: matchedSubmission.userId,
              });
            } catch (error) {
              console.error('[AttendeesPage] failed to export survey submission detail', {
                attendee,
                matchedSubmission,
                error,
              });
              incompleteSurveyExports.push(attendee.fullName);
            }
          }

          const resolvedForms =
            detail?.forms && detail.forms.length > 0
              ? detail.forms
              : buildFallbackFormsFromRegistration(detail);

          const row: Record<string, string> = {
            Name: attendee.fullName,
            Email: attendee.email || '',
            Phone: attendee.phone || '',
            'Graduation Year': attendee.graduationYear ? String(attendee.graduationYear) : '',
            'Registration Date': formatRegisteredDateForExport(attendee.registeredAt),
            'Registration Time': formatRegisteredTimeForExport(attendee.registeredAt),
            'RSVP Status': attendee.status || 'going',
          };

          resolvedForms.forEach((form: EventSurveySubmissionFormView) => {
            form.questions.forEach((question: EventSurveySubmissionQuestionView) => {
              const columnName = `${form.formName}: ${question.label}`;
              if (!questionColumnSet.has(columnName)) {
                questionColumnSet.add(columnName);
                questionColumns.push(columnName);
              }

              row[columnName] = formatSurveyAnswerForExport(question);
            });
          });

          row['Additional Info'] = detail?.registration.additionalInfo?.trim() ?? '';

          return row;
        }),
      );

      const headers = [
        'Name',
        'Email',
        'Phone',
        'Graduation Year',
        'Registration Date',
        'Registration Time',
        'RSVP Status',
        ...questionColumns,
        'Additional Info',
      ];

      downloadCsvFile(
        `${sanitizeFilename(pageTitle || 'event-attendees') || 'event-attendees'}-${new Date()
          .toISOString()
          .slice(0, 10)}.csv`,
        headers,
        rows,
      );

      if (incompleteSurveyExports.length > 0) {
        window.alert(
          `Attendee export completed, but survey answers could not be loaded for: ${incompleteSurveyExports.join(
            ', ',
          )}.`,
        );
      }
    } catch (error) {
      console.error('[AttendeesPage] failed to export attendees', error);
      window.alert('Unable to export the attendee list right now. Please try again.');
    } finally {
      setIsExporting(false);
    }
  }

  const isPreparingExport =
    isLoading || isLoadingSurveySubmissions || (shouldLoadUserProfiles && isLoadingUserProfiles);
  const exportButtonDisabled = isPreparingExport || isExporting || attendees.length === 0;
  const exportButtonLabel = isPreparingExport
    ? 'Loading attendees...'
    : isExporting
      ? 'Exporting...'
      : 'Export List';

  return (
    <>
      <SEO title={`${pageTitle} Attendees`} description={`View attendees for ${pageTitle}.`} />
      <Breadcrumbs items={breadcrumbItems} />

      <main className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-8">
          <header className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between pb-6">
            <div>
              <h1 className="text-3xl font-bold leading-tight text-accent-950 sm:text-4xl">
                {pageTitle}
              </h1>
              <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-accent-500">
                {eventDate && (
                  <span className="inline-flex items-center gap-2">
                    <Icon icon="mdi:calendar-clock-outline" className="h-4 w-4" />
                    {eventDate}
                  </span>
                )}
                <span className="inline-flex items-center gap-2">
                  <Icon icon="mdi:account-group-outline" className="h-4 w-4" />
                  {totalCount} attendee{totalCount === 1 ? '' : 's'}
                </span>
              </div>
            </div>

            <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center xl:w-auto">
              <SearchInput
                value={searchTerm}
                onValueChange={setSearchTerm}
                onClear={() => setSearchTerm('')}
                placeholder="Search attendees"
                containerClassName="w-full sm:w-[22rem] xl:w-[24rem]"
                inputClassName="min-h-[3.4rem] border-0 bg-[#f7f5f2] text-base text-accent-700 shadow-none placeholder:text-accent-400 focus:ring-2 focus:ring-primary-200"
                iconClassName="text-accent-400"
              />

              <button
                type="button"
                onClick={handleExportAttendees}
                disabled={exportButtonDisabled}
                className="inline-flex items-center gap-2 rounded-full bg-primary-500 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-600 disabled:cursor-not-allowed disabled:bg-primary-200"
              >
                <span>{exportButtonLabel}</span>
                {isPreparingExport || isExporting ? (
                  <Icon icon="mdi:loading" className="h-4 w-4 animate-spin" />
                ) : (
                  <Icon icon="mdi:download-outline" className="h-4 w-4" />
                )}
              </button>
            </div>
          </header>

          <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {isLoading ? (
              Array.from({ length: 6 }).map((_, index) => <AttendeeCardSkeleton key={index} />)
            ) : filteredAttendees.length > 0 ? (
              filteredAttendees.map((attendee) => {
                const matchedSubmission = getMatchedSubmission(attendee);
                const hasSurveyResponse = hasViewableSurveyResponse(matchedSubmission);

                return (
                  <AttendeeCard
                    key={attendee.userId}
                    attendee={attendee}
                    hasSurveyResponse={hasSurveyResponse}
                    onViewRequestedInfo={() => {
                      if (!matchedSubmission) {
                        console.warn('[AttendeesPage] no matched survey submission for attendee', {
                          attendee,
                          surveySubmissions,
                        });
                        return;
                      }

                      setSelectedSurveyTarget({
                        attendee,
                        submissionUserId: matchedSubmission.userId,
                      });
                    }}
                  />
                );
              })
            ) : (
              <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm ring-1 ring-accent-100 md:col-span-2 xl:col-span-3">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-50">
                  <Icon icon="mdi:account-group-outline" className="h-7 w-7 text-accent-700" />
                </div>
                <h2 className="mt-4 text-2xl font-bold text-accent-950">
                  {searchTerm.trim() ? 'No matching attendees' : 'No attendees yet'}
                </h2>
                <p className="mt-2 text-sm text-accent-500">
                  {searchTerm.trim()
                    ? 'Try a different name, email, phone number, or graduation year.'
                    : 'No confirmed attendees yet for this event.'}
                </p>
              </div>
            )}
          </div>
        </div>
      </main>

      <SurveyResponseModal
        attendee={selectedSurveyTarget?.attendee ?? null}
        detail={selectedSubmissionDetail}
        isLoading={isLoadingSubmissionDetail}
        onClose={() => setSelectedSurveyTarget(null)}
      />
    </>
  );
}
