import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { BriefcaseBusiness, Plus, SearchX, X } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { Button } from '@/shared/components/ui/Button';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { BaseInput } from '@/shared/components/ui/input/BaseInput';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { toast } from '@/shared/components/ui/Toast';
import { ROUTES } from '@/shared/constants/routes';
import { useRequireSignIn } from '@/features/authentication/hooks/useRequireSignIn';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useTokenStore } from '@/features/authentication/stores/useTokenStore';
import { useCreateVacancy } from '../hooks/useCreateVacancy';
import { useJobVacancies } from '../hooks/useJobVacancies';
import { useUpdateVacancy } from '../hooks/useManageVacancy';
import type { JobVacancyViewModel } from '../api/adapters';
import {
  formatJobDate,
  formatMoneyAmount,
  getJobPillLabels,
  getSalaryDisplay,
} from '../utils/jobVacancyDisplay';
import {
  APPLICATION_TYPE_OPTIONS,
  CURRENCY_OPTIONS,
  JOB_TYPE_OPTIONS,
  LEVEL_OF_EXPERTISE_OPTIONS,
  WORKPLACE_TYPE_OPTIONS,
  type ApplicationType,
  type CreateVacancyPayload,
  type JobType,
  type LevelOfExpertise,
  type VacancyCurrency,
  type WorkplaceType,
} from '../types/jobVacancies.types';

export type JobCardTone = 'mint' | 'rose' | 'slate' | 'lavender' | 'sky' | 'green';

type JobFormState = {
  title: string;
  companyName: string;
  jobType: JobType | '';
  workplaceType: WorkplaceType | '';
  level: LevelOfExpertise | '';
  location: string;
  salary: string;
  currency: VacancyCurrency;
  deadline: string;
  tags: string[];
  tagDraft: string;
  aboutRole: string;
  responsibilities: string;
  requirements: string;
  applicationMode: ApplicationType;
  applicationDestination: string;
};

type JobFormErrors = Partial<Record<keyof JobFormState, string>>;

const jobCardTones: JobCardTone[] = ['mint', 'rose', 'slate', 'lavender', 'sky', 'green'];
const MAX_KEYWORDS = 10;
const MAX_KEYWORD_LENGTH = 30;
const JOB_VACANCIES_PER_PAGE = 12;

const jobPanelToneClassNames: Record<JobCardTone, string> = {
  mint: 'bg-[#e5f6f3]',
  rose: 'bg-[#fae7f5]',
  slate: 'bg-[#edf1f5]',
  lavender: 'bg-[#e9e2ff]',
  sky: 'bg-[#e7f5ff]',
  green: 'bg-[#e6f5e5]',
};

export const jobsPageShellClassName = 'container-custom pb-16 pt-8';
export const jobsPageHeaderClassName =
  'mb-7 flex flex-col gap-4 md:mb-[2.7rem] md:flex-row md:items-start md:justify-between md:gap-6';
export const jobsPageTitleClassName = 'type-section-title text-[#071116]';
export const jobsPageSubtitleClassName = 'type-card-body mt-2 text-[#59626c]';
export const jobsPagePostButtonClassName =
  'type-button min-h-[1.5rem] rounded-full px-[1.45rem] tracking-normal shadow-none max-md:w-full [&>svg]:h-[1.35rem] [&>svg]:w-[1.35rem]';
export const jobsGridClassName =
  'grid grid-cols-1 gap-x-[1.4rem] gap-y-[1.05rem] md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
const jobsModalBackdropClassName =
  'fixed inset-0 z-[80] flex items-start justify-center bg-[rgba(7,17,22,0.55)] p-3 sm:items-center sm:p-6';
const jobsModalSurfaceClassName =
  'relative w-full max-w-[57rem] max-h-[calc(100vh-2.5rem)] overflow-y-auto rounded-[1.2rem] bg-white shadow-[0_2rem_4rem_rgba(7,17,22,0.22)] sm:max-h-[min(75vh,43.5rem)] sm:rounded-[1.75rem]';
const jobsModalCloseButtonClassName =
  'sticky top-3 z-10 ml-auto mr-3 mt-3 flex h-10 w-10 items-center justify-center text-primary-500 transition-colors hover:text-primary-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 sm:top-4 sm:mr-4 sm:mt-4 sm:h-12 sm:w-12';
const jobsFormLabelClassName = 'text-sm font-bold text-[#858585]';
const jobsFormControlClassName =
  '!min-h-[2.95rem] !rounded-full !bg-[#f8f8f7] !shadow-none focus-within:!ring-2 focus-within:!ring-primary-200';
const jobsFormInputClassName =
  '!h-[2.95rem] !px-[1rem] !py-0 !text-[0.95rem] !font-medium !text-[#071116] placeholder:!text-[#858585]';
const jobsFormSelectControlClassName =
  '!flex !h-[2.95rem] !items-center !rounded-full !bg-[#f8f8f7] !px-[1rem] !py-0 !pr-9 !shadow-none [&>span]:!text-[0.95rem] [&>span]:!font-medium [&>span.text-gray-400]:!text-[#858585] [&>span.text-gray-700]:!text-[#071116]';
const jobsFormDateInputClassName =
  '!h-[2.95rem] !rounded-full !bg-[#f8f8f7] !px-[1rem] !py-0 !text-[0.95rem] !font-medium !text-[#071116] placeholder:!text-[#858585] !shadow-none';
const jobsFormTextareaClassName =
  '!min-h-[9.5rem] !rounded-[1.35rem] !bg-[#f8f8f7] !px-5 !py-3.5 !text-[0.95rem] !font-medium !leading-[1.45] !text-[#071116] placeholder:!text-[#858585] !shadow-none';
const jobsFormSectionSpacingClassName = 'mt-6';

const initialJobFormState: JobFormState = {
  title: '',
  companyName: '',
  jobType: '',
  workplaceType: '',
  level: '',
  location: '',
  salary: '',
  currency: 'NGN',
  deadline: '',
  tags: [],
  tagDraft: '',
  aboutRole: '',
  responsibilities: '',
  requirements: '',
  applicationMode: 'email',
  applicationDestination: '',
};

function getInitialJobFormState(): JobFormState {
  return {
    ...initialJobFormState,
    tags: [],
  };
}

function isVacancyCurrency(value: string): value is VacancyCurrency {
  return CURRENCY_OPTIONS.some((option) => option.value === value);
}

function getDateInputValue(value: string) {
  if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10);

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toISOString().slice(0, 10);
}

function getSalaryFormValue(value: string) {
  const numericValue = value.replace(/[^\d.]/g, '');

  return numericValue || value;
}

function jobToFormState(job?: JobVacancyViewModel | null): JobFormState {
  if (!job) return getInitialJobFormState();

  return {
    title: job.title,
    companyName: job.companyName,
    jobType: job.jobType,
    workplaceType: job.workplaceType,
    level: job.levelOfExpertise,
    location: job.location,
    salary: getSalaryFormValue(job.salary),
    currency: isVacancyCurrency(job.currency) ? job.currency : 'NGN',
    deadline: getDateInputValue(job.postedAt),
    tags: [...job.tags],
    tagDraft: '',
    aboutRole: job.aboutRole,
    responsibilities: job.responsibilities,
    requirements: job.requirements,
    applicationMode: job.applicationMode,
    applicationDestination:
      job.applicationMode === 'email' ? (job.applicationEmail ?? '') : (job.applicationUrl ?? ''),
  };
}

function getTodayDateInputValue() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function isValidUrl(value: string) {
  try {
    const parsed = new URL(value.trim());
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

function parseSalaryAmount(value: string) {
  const normalized = value.replace(/,/g, '').trim();
  if (!/^\d+(\.\d+)?$/.test(normalized)) return null;

  const amount = Number(normalized);
  if (!Number.isFinite(amount) || amount <= 0) return null;

  return amount;
}

function normalizeKeyword(value: string) {
  return value.replace(/\s+/g, ' ').trim();
}

function splitKeywords(value: string) {
  return value.split(/[,\n]/).map(normalizeKeyword).filter(Boolean);
}

function validateJobForm(form: JobFormState): JobFormErrors {
  const errors: JobFormErrors = {};
  const today = getTodayDateInputValue();

  if (!form.title.trim()) errors.title = 'Job title is required.';
  if (!form.companyName.trim()) errors.companyName = 'Company name is required.';
  if (!form.jobType) errors.jobType = 'Select a job type.';
  if (!form.workplaceType) errors.workplaceType = 'Select a workplace type.';
  if (!form.level) errors.level = 'Select a level of expertise.';
  if (!form.location.trim()) errors.location = 'Location is required.';
  if (!form.salary.trim()) errors.salary = 'Salary is required.';
  if (form.salary.trim() && parseSalaryAmount(form.salary) === null) {
    errors.salary = 'Enter a valid salary amount.';
  }
  if (!form.deadline.trim()) errors.deadline = 'Application deadline is required.';
  if (form.deadline.trim() && form.deadline < today) {
    errors.deadline = 'Application deadline cannot be in the past.';
  }
  if (!form.aboutRole.trim()) errors.aboutRole = 'Tell applicants about the role.';
  if (!form.responsibilities.trim()) errors.responsibilities = 'Responsibilities are required.';
  if (!form.requirements.trim()) errors.requirements = 'Requirements are required.';

  if (!form.applicationDestination.trim()) {
    errors.applicationDestination =
      form.applicationMode === 'email'
        ? 'Application email is required.'
        : 'Application link is required.';
  } else if (
    form.applicationMode === 'email' &&
    !isValidEmail(form.applicationDestination.trim())
  ) {
    errors.applicationDestination = 'Enter a valid application email.';
  } else if (form.applicationMode === 'link' && !isValidUrl(form.applicationDestination.trim())) {
    errors.applicationDestination = 'Enter a valid application link.';
  }

  return errors;
}

export function getTone(index: number): JobCardTone {
  return jobCardTones[index % jobCardTones.length];
}

function KeywordInput({
  tags,
  draft,
  error,
  disabled,
  onDraftChange,
  onAddKeyword,
  onRemoveKeyword,
  onRemoveLastKeyword,
}: {
  tags: string[];
  draft: string;
  error?: string;
  disabled?: boolean;
  onDraftChange: (value: string) => void;
  onAddKeyword: () => void;
  onRemoveKeyword: (keyword: string) => void;
  onRemoveLastKeyword: () => void;
}) {
  return (
    <div className={`${jobsFormSectionSpacingClassName} flex flex-col gap-1`}>
      <label htmlFor="job-keywords" className={jobsFormLabelClassName}>
        Job Tags / Keywords
      </label>

      <div
        className={`rounded-3xl border bg-white px-3 py-2 shadow-sm transition-colors ${
          error ? 'border-red-400' : 'border-gray-200 focus-within:border-primary-400'
        } ${disabled ? 'cursor-not-allowed bg-gray-50 opacity-60' : ''}`}
      >
        <div className="flex flex-wrap items-center gap-2">
          {tags.map((tag) => (
            <span key={tag} className="badge badge-primary gap-2">
              {tag}
              <button
                type="button"
                onClick={() => onRemoveKeyword(tag)}
                disabled={disabled}
                className="inline-flex h-4 w-4 items-center justify-center rounded-full text-primary-700 transition-colors hover:bg-primary-200 disabled:cursor-not-allowed"
                aria-label={`Remove ${tag}`}
              >
                <X className="h-3 w-3" strokeWidth={2.35} />
              </button>
            </span>
          ))}

          <input
            id="job-keywords"
            type="text"
            value={draft}
            disabled={disabled}
            placeholder={
              tags.length === 0 ? 'Type a keyword and press Enter' : 'Add another keyword'
            }
            onChange={(event) => onDraftChange(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                onAddKeyword();
              }

              if (event.key === 'Backspace' && !draft.trim() && tags.length > 0) {
                event.preventDefault();
                onRemoveLastKeyword();
              }
            }}
            onBlur={() => {
              if (draft.trim()) onAddKeyword();
            }}
            className="min-w-[180px] flex-1 border-0 bg-transparent py-0.5 text-sm text-gray-700 placeholder-gray-400 outline-none disabled:cursor-not-allowed"
          />

          <button
            type="button"
            disabled={disabled || !draft.trim()}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onAddKeyword}
            className="inline-flex min-h-[2rem] items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-600 transition-colors hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Plus className="h-3.5 w-3.5" strokeWidth={2.35} />
            Add
          </button>
        </div>
      </div>

      {error ? (
        <p className="text-xs text-red-500">{error}</p>
      ) : (
        <p className="text-xs text-gray-400">
          Press Enter or comma to add a keyword. {tags.length}/{MAX_KEYWORDS} added.
        </p>
      )}
    </div>
  );
}

export function JobCard({
  job,
  tone,
  onDetails,
  actions,
  panelAction,
  primaryAction,
}: {
  job: JobVacancyViewModel;
  tone: JobCardTone;
  onDetails?: (job: JobVacancyViewModel) => void;
  actions?: ReactNode;
  panelAction?: ReactNode;
  primaryAction?: ReactNode;
}) {
  const pillLabels = getJobPillLabels(job);

  return (
    <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-[#d7e7f4] bg-white shadow-[0_1px_2px_rgba(7,17,22,0.03)]">
      <div
        className={`m-[0.55rem] flex min-h-[15.8rem] flex-1 flex-col rounded-[0.8rem] px-[1.35rem] pb-[1.25rem] pt-[1.35rem] ${jobPanelToneClassNames[tone]}`}
      >
        <div className="flex items-start justify-between gap-3">
          <time
            dateTime={job.createdAt || job.postedAt}
            className="inline-flex min-h-9 items-center rounded-full bg-white/80 px-4 py-[0.35rem] text-[0.88rem] font-bold leading-none text-[#59626c]"
          >
            {formatJobDate(job.createdAt || job.postedAt)}
          </time>

          {panelAction ? <div className="shrink-0">{panelAction}</div> : null}
        </div>

        <p className="mt-[1.35rem] text-[0.9rem] font-semibold leading-[1.2] text-gray-700">
          {job.companyName}
        </p>
        <h2 className="mt-[0.7rem] text-[clamp(1.35rem,1.5vw,1.75rem)] font-semibold leading-[1.18] text-[#071116]">
          {job.title}
        </h2>
        {job.postedByName ? (
          <p className="mt-2 text-[0.82rem] font-semibold leading-[1.25] text-[#59626c]">
            Posted by {job.postedByName}
          </p>
        ) : null}

        <div className="mt-[1.45rem] flex flex-wrap gap-[0.6rem]">
          {pillLabels.map((label) => (
            <span
              key={label}
              className="inline-flex min-h-[2.15rem] items-center rounded-full border border-[rgba(7,17,22,0.35)] px-[0.85rem] py-[0.35rem] text-[0.75rem] font-bold leading-none text-[#59626c]"
            >
              {label}
            </span>
          ))}
        </div>
      </div>

      <div className="mt-auto flex items-end justify-between gap-4 px-[1.35rem] pb-[1.0rem] pt-4 max-sm:flex-wrap">
        <div>
          <p className="text-[1.45rem] font-extrabold leading-none text-[#071116]">
            {getSalaryDisplay(job)}
          </p>
          <p className="mt-[0.35rem] text-[0.9rem] font-semibold leading-[1.15] text-[#59626c]">
            {job.location}
          </p>
        </div>

        <div className="flex flex-wrap justify-end gap-2">
          {primaryAction ??
            (onDetails ? (
              <button
                type="button"
                className="min-h-[2.5rem] rounded-full bg-primary-500 px-4 py-1 text-[0.95rem] font-extrabold leading-none text-white transition-colors hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                onClick={() => onDetails(job)}
              >
                Details
              </button>
            ) : null)}
          {actions}
        </div>
      </div>
    </article>
  );
}

export function PostJobModal({
  chapterId,
  editData,
  onClose,
  onSubmitted,
}: {
  chapterId?: string;
  editData?: JobVacancyViewModel | null;
  onClose: () => void;
  onSubmitted: () => void;
}) {
  const isEditing = Boolean(editData);
  const createVacancy = useCreateVacancy();
  const updateVacancy = useUpdateVacancy();
  const [form, setForm] = useState<JobFormState>(() => jobToFormState(editData));
  const applicationDestinationCacheRef = useRef<Record<ApplicationType, string>>({
    email: editData?.applicationEmail ?? '',
    link: editData?.applicationUrl ?? '',
  });
  const [fieldErrors, setFieldErrors] = useState<JobFormErrors>({});
  const [formError, setFormError] = useState('');
  const [flyerFile, setFlyerFile] = useState<File | null>(null);
  const [flyerPreviews, setFlyerPreviews] = useState<string[]>(
    editData?.flyer ? [editData.flyer] : [],
  );
  const minDeadline = getTodayDateInputValue();
  const isSubmitting = createVacancy.isPending || updateVacancy.isPending;

  useEffect(() => {
    applicationDestinationCacheRef.current = {
      email: editData?.applicationEmail ?? '',
      link: editData?.applicationUrl ?? '',
    };
    setForm(jobToFormState(editData));
    setFieldErrors({});
    setFormError('');
    setFlyerFile(null);
    setFlyerPreviews(editData?.flyer ? [editData.flyer] : []);
  }, [editData]);

  const handleFieldChange = <K extends keyof JobFormState>(field: K, value: JobFormState[K]) => {
    setForm((prev) => {
      if (field === 'applicationDestination') {
        applicationDestinationCacheRef.current[prev.applicationMode] = String(value);
      }

      return { ...prev, [field]: value };
    });
    setFieldErrors((prev) => ({ ...prev, [field]: undefined }));
    setFormError('');
  };

  const handleApplicationModeChange = (nextMode: ApplicationType) => {
    setForm((prev) => {
      applicationDestinationCacheRef.current[prev.applicationMode] = prev.applicationDestination;

      return {
        ...prev,
        applicationMode: nextMode,
        applicationDestination: applicationDestinationCacheRef.current[nextMode] ?? '',
      };
    });
    setFieldErrors((prev) => ({ ...prev, applicationDestination: undefined }));
    setFormError('');
  };

  const handleImageChange = (files: File[], previews: string[]) => {
    setFlyerFile(files[0] ?? null);
    setFlyerPreviews(previews);
    setFormError('');
  };

  const handleAddKeywords = () => {
    const candidates = splitKeywords(form.tagDraft);

    if (candidates.length === 0) {
      setForm((prev) => ({ ...prev, tagDraft: '' }));
      return;
    }

    const nextKeywords = [...form.tags];
    let nextError = '';

    for (const candidate of candidates) {
      if (candidate.length > MAX_KEYWORD_LENGTH) {
        nextError = `Each keyword must be ${MAX_KEYWORD_LENGTH} characters or fewer.`;
        continue;
      }

      const exists = nextKeywords.some((tag) => tag.toLowerCase() === candidate.toLowerCase());
      if (exists) {
        if (!nextError) nextError = `"${candidate}" has already been added.`;
        continue;
      }

      if (nextKeywords.length >= MAX_KEYWORDS) {
        nextError = `You can add up to ${MAX_KEYWORDS} keywords.`;
        break;
      }

      nextKeywords.push(candidate);
    }

    setForm((prev) => ({
      ...prev,
      tags: nextKeywords,
      tagDraft: '',
    }));

    setFieldErrors((prev) => ({
      ...prev,
      tags: nextError || undefined,
    }));
  };

  const handleRemoveKeyword = (keyword: string) => {
    setForm((prev) => ({
      ...prev,
      tags: prev.tags.filter((tag) => tag !== keyword),
    }));
    setFieldErrors((prev) => ({ ...prev, tags: undefined }));
  };

  const handleRemoveLastKeyword = () => {
    setForm((prev) => ({
      ...prev,
      tags: prev.tags.slice(0, -1),
    }));
    setFieldErrors((prev) => ({ ...prev, tags: undefined }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const errors = validateJobForm(form);
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    if (!chapterId) {
      setFormError('We could not determine your chapter yet. Please refresh and try again.');
      return;
    }

    const payload: CreateVacancyPayload = {
      job_title: form.title.trim(),
      company_name: form.companyName.trim(),
      job_type: form.jobType as JobType,
      workplace_type: form.workplaceType as WorkplaceType,
      level_of_expertise: form.level as LevelOfExpertise,
      location: form.location.trim(),
      salary: formatMoneyAmount(form.salary, form.currency, form.salary.trim()),
      currency: form.currency,
      application_deadline: form.deadline,
      keywords: form.tags.length > 0 ? form.tags.join(', ') : undefined,
      about_role: form.aboutRole.trim(),
      responsibilities: form.responsibilities.trim(),
      requirements: form.requirements.trim(),
      application_type: form.applicationMode,
      application_email:
        form.applicationMode === 'email' ? form.applicationDestination.trim() : undefined,
      application_link:
        form.applicationMode === 'link' ? form.applicationDestination.trim() : undefined,
      chapter_id: chapterId,
      ...(flyerFile ? { flyer: flyerFile } : {}),
    };

    try {
      if (isEditing && editData) {
        await updateVacancy.mutateAsync({ id: editData.id, ...payload });
        toast.success('Job vacancy updated successfully.');
      } else {
        await createVacancy.mutateAsync(payload);
        toast.success('Job vacancy posted successfully.');
      }
      onSubmitted();
    } catch (error: any) {
      const message =
        error?.message ??
        (isEditing
          ? 'Unable to update this job vacancy right now.'
          : 'Unable to post this job vacancy right now.');
      setFormError(message);
      toast.fromError(error);
    }
  };

  return (
    <div className={jobsModalBackdropClassName} role="presentation" onClick={onClose}>
      <section
        className={jobsModalSurfaceClassName}
        role="dialog"
        aria-modal="true"
        aria-labelledby="post-job-title"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          className={jobsModalCloseButtonClassName}
          onClick={onClose}
          aria-label="Close"
        >
          <X className="h-7 w-7 sm:h-8 sm:w-8" strokeWidth={2.2} />
        </button>

        <form className="px-4 pb-6 pt-1 sm:px-5 md:px-8" onSubmit={handleSubmit}>
          <h2 id="post-job-title" className="sr-only">
            {isEditing ? 'Edit Job' : 'Post a Job'}
          </h2>

          <div className="grid grid-cols-1 gap-x-6 gap-y-6 md:grid-cols-2">
            <BaseInput
              label="Job Title"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormControlClassName}
              inputClassName={jobsFormInputClassName}
              name="title"
              value={form.title}
              onChange={(event) => handleFieldChange('title', event.target.value)}
              placeholder="Enter the job title"
              error={fieldErrors.title}
              required
              disabled={isSubmitting}
            />
            <BaseInput
              label="Company Name"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormControlClassName}
              inputClassName={jobsFormInputClassName}
              name="companyName"
              value={form.companyName}
              onChange={(event) => handleFieldChange('companyName', event.target.value)}
              placeholder="Enter the company name"
              error={fieldErrors.companyName}
              required
              disabled={isSubmitting}
            />
            <SelectInput
              label="Employment Type"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormSelectControlClassName}
              name="jobType"
              value={form.jobType}
              onChange={(event) => handleFieldChange('jobType', event.target.value as JobType | '')}
              placeholder="Select an employment type"
              options={JOB_TYPE_OPTIONS}
              error={fieldErrors.jobType}
              required
              disabled={isSubmitting}
            />
            <SelectInput
              label="Work Mode"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormSelectControlClassName}
              name="workplaceType"
              value={form.workplaceType}
              onChange={(event) =>
                handleFieldChange('workplaceType', event.target.value as WorkplaceType | '')
              }
              placeholder="Select a work mode"
              options={WORKPLACE_TYPE_OPTIONS}
              error={fieldErrors.workplaceType}
              required
              disabled={isSubmitting}
            />
            <SelectInput
              label="Level of Expertise"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormSelectControlClassName}
              name="level"
              value={form.level}
              onChange={(event) =>
                handleFieldChange('level', event.target.value as LevelOfExpertise | '')
              }
              placeholder="Select a level of expertise"
              options={LEVEL_OF_EXPERTISE_OPTIONS}
              error={fieldErrors.level}
              required
              disabled={isSubmitting}
            />
            <BaseInput
              label="Location (City)"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormControlClassName}
              inputClassName={jobsFormInputClassName}
              name="location"
              value={form.location}
              onChange={(event) => handleFieldChange('location', event.target.value)}
              placeholder="Enter the location of the job"
              error={fieldErrors.location}
              required
              disabled={isSubmitting}
            />
            <BaseInput
              label="Salary"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormControlClassName}
              inputClassName={jobsFormInputClassName}
              name="salary"
              value={form.salary}
              onChange={(event) => handleFieldChange('salary', event.target.value)}
              placeholder="Enter the job salary amount"
              hint={
                parseSalaryAmount(form.salary) !== null
                  ? `Display preview: ${formatMoneyAmount(form.salary, form.currency, form.salary)}`
                  : undefined
              }
              error={fieldErrors.salary}
              required
              disabled={isSubmitting}
            />
            <SelectInput
              label="Currency"
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormSelectControlClassName}
              name="currency"
              value={form.currency}
              onChange={(event) =>
                handleFieldChange('currency', event.target.value as VacancyCurrency)
              }
              options={CURRENCY_OPTIONS}
              disabled={isSubmitting}
            />
            <DatePicker
              label="Application Deadline"
              labelClassName={jobsFormLabelClassName}
              inputClassName={jobsFormDateInputClassName}
              name="deadline"
              value={form.deadline}
              onValueChange={(value) => handleFieldChange('deadline', value)}
              min={minDeadline}
              placeholder="Select the application deadline"
              error={fieldErrors.deadline}
              required
              disabled={isSubmitting}
            />
          </div>

          <KeywordInput
            tags={form.tags}
            draft={form.tagDraft}
            error={fieldErrors.tags}
            disabled={isSubmitting}
            onDraftChange={(value) => {
              handleFieldChange('tagDraft', value);
              if (fieldErrors.tags) {
                setFieldErrors((prev) => ({ ...prev, tags: undefined }));
              }
            }}
            onAddKeyword={handleAddKeywords}
            onRemoveKeyword={handleRemoveKeyword}
            onRemoveLastKeyword={handleRemoveLastKeyword}
          />

          <TextareaInput
            className={jobsFormSectionSpacingClassName}
            label="About this Role"
            labelClassName={jobsFormLabelClassName}
            textareaClassName={jobsFormTextareaClassName}
            name="aboutRole"
            value={form.aboutRole}
            onChange={(event) => handleFieldChange('aboutRole', event.target.value)}
            placeholder="Write a short description about the job"
            rows={5}
            error={fieldErrors.aboutRole}
            required
            disabled={isSubmitting}
          />

          <TextareaInput
            className={jobsFormSectionSpacingClassName}
            label="Responsibilities"
            labelClassName={jobsFormLabelClassName}
            textareaClassName={jobsFormTextareaClassName}
            name="responsibilities"
            value={form.responsibilities}
            onChange={(event) => handleFieldChange('responsibilities', event.target.value)}
            placeholder="Enter the responsibilities involved in this job"
            rows={5}
            error={fieldErrors.responsibilities}
            required
            disabled={isSubmitting}
          />

          <TextareaInput
            className={jobsFormSectionSpacingClassName}
            label="Requirements"
            labelClassName={jobsFormLabelClassName}
            textareaClassName={jobsFormTextareaClassName}
            name="requirements"
            value={form.requirements}
            onChange={(event) => handleFieldChange('requirements', event.target.value)}
            placeholder="Enter the job requirements"
            rows={5}
            error={fieldErrors.requirements}
            required
            disabled={isSubmitting}
          />

          <div
            className={`${jobsFormSectionSpacingClassName} grid grid-cols-1 gap-6 md:grid-cols-2`}
          >
            <fieldset className="m-0 border-0 p-0">
              <legend className="mb-4 text-sm font-bold leading-[1.2] text-[#858585]">
                Applications for this job will be done by:
              </legend>
              <div className="flex flex-wrap gap-x-7 gap-y-3">
                {APPLICATION_TYPE_OPTIONS.map((option) => (
                  <label
                    key={option.value}
                    className="inline-flex items-center gap-2.5 text-sm font-bold text-[#858585]"
                  >
                    <input
                      type="radio"
                      name="applicationMode"
                      value={option.value}
                      checked={form.applicationMode === option.value}
                      onChange={() => handleApplicationModeChange(option.value)}
                      disabled={isSubmitting}
                      className="h-5 w-5 accent-primary-500"
                    />
                    <span>{option.label === 'Link' ? 'Job Application Link' : option.label}</span>
                  </label>
                ))}
              </div>
            </fieldset>

            <BaseInput
              label={form.applicationMode === 'email' ? 'Application Email' : 'Application Link'}
              labelClassName={jobsFormLabelClassName}
              controlClassName={jobsFormControlClassName}
              inputClassName={jobsFormInputClassName}
              name={form.applicationMode === 'email' ? 'applicationEmail' : 'applicationUrl'}
              type={form.applicationMode === 'email' ? 'email' : 'url'}
              value={form.applicationDestination}
              onChange={(event) => handleFieldChange('applicationDestination', event.target.value)}
              placeholder={
                form.applicationMode === 'email'
                  ? 'Enter the email to send applications to'
                  : 'Enter the link to send applicants to'
              }
              error={fieldErrors.applicationDestination}
              required
              disabled={isSubmitting}
            />
          </div>

          <div className={jobsFormSectionSpacingClassName}>
            <ImageUpload
              label="Job Flyer (Optional)"
              labelClassName={jobsFormLabelClassName}
              className="gap-1"
              dropzoneClassName="rounded-[1.35rem] border-gray-200 bg-[#fbfbfa] py-7"
              previews={flyerPreviews}
              onChange={handleImageChange}
              multiple={false}
              hint="PNG, JPG, WEBP or GIF up to 2 MB"
            />
          </div>

          {formError ? (
            <div className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{formError}</div>
          ) : null}

          <Button
            type="submit"
            size="lg"
            className="mx-auto mt-8 flex min-h-[3rem] w-full max-w-[13rem] rounded-full border-0 px-7 text-[1rem] font-extrabold tracking-normal shadow-none"
            loading={isSubmitting}
          >
            {isEditing ? 'Update Job' : 'Submit'}
          </Button>
        </form>
      </section>
    </div>
  );
}

export function JobsLoadingState() {
  return (
    <div className={jobsGridClassName}>
      {Array.from({ length: 6 }).map((_, index) => (
        <div
          key={index}
          className="flex h-full flex-col overflow-hidden rounded-2xl border border-[#d7e7f4] bg-white shadow-[0_1px_2px_rgba(7,17,22,0.03)] animate-pulse"
        >
          <div className="m-[0.55rem] min-h-[15.8rem] flex-1 rounded-[0.8rem] bg-gray-100 px-[1.35rem] pb-[1.25rem] pt-[1.35rem]">
            <div className="h-4 w-24 rounded bg-gray-200" />
            <div className="mt-6 h-4 w-28 rounded bg-gray-200" />
            <div className="mt-4 h-7 w-3/4 rounded bg-gray-200" />
            <div className="mt-6 flex gap-2">
              <div className="h-8 w-20 rounded-full bg-gray-200" />
              <div className="h-8 w-24 rounded-full bg-gray-200" />
            </div>
          </div>
          <div className="mt-auto flex items-end justify-between gap-4 px-[1.35rem] pb-[1.2rem] pt-4 max-sm:flex-wrap">
            <div className="space-y-2">
              <div className="h-4 w-24 rounded bg-gray-200" />
              <div className="h-4 w-32 rounded bg-gray-200" />
            </div>
            <div className="h-10 w-20 rounded-full bg-gray-200" />
          </div>
        </div>
      ))}
    </div>
  );
}

export default function JobVacanciesPage() {
  const navigate = useNavigate();
  const requireSignIn = useRequireSignIn();
  const user = useIdentityStore((state) => state.user);
  const accessToken = useTokenStore((state) => state.accessToken);
  const { data: vacancies = [], isLoading, isError, error, refetch } = useJobVacancies();

const [isPostModalOpen, setIsPostModalOpen] = useState(false);
const [currentPage, setCurrentPage] = useState(1);
const [search, setSearch] = useState('');

const canPostJob = Boolean(user?.chapterId && accessToken);

const orderedVacancies = useMemo(
  () =>
    [...vacancies].sort(
      (a, b) => new Date(b.postedAt).getTime() - new Date(a.postedAt).getTime(),
    ),
  [vacancies],
);

// const filteredVacancies = useMemo(() => {
//   const query = search.toLowerCase();
//   if (!query) return orderedVacancies;

//   return orderedVacancies.filter(
//     (job) =>
//       job.title.toLowerCase().includes(query) ||
//       job.companyName.toLowerCase().includes(query) ||
//       job.location.toLowerCase().includes(query),
//   );
// }, [orderedVacancies, search]);

const filteredVacancies = useMemo(() => {
  const query = search.toLowerCase().trim();
  if (!query) return orderedVacancies;

  return orderedVacancies.filter((job) => {
    const searchableFields = [
      job.title,
      job.companyName,
      job.postedByName ?? '',
      job.location,
      formatJobDate(job.createdAt || job.postedAt),
      getSalaryDisplay(job),
      ...getJobPillLabels(job),
    ];

    return searchableFields.some((field) => field.toLowerCase().includes(query));
  });
}, [orderedVacancies, search]);

const totalPages = Math.max(1, Math.ceil(filteredVacancies.length / JOB_VACANCIES_PER_PAGE));
const visibleVacancies = filteredVacancies.slice(
  (currentPage - 1) * JOB_VACANCIES_PER_PAGE,
  currentPage * JOB_VACANCIES_PER_PAGE,
);

useEffect(() => {
  if (currentPage > totalPages) {
    setCurrentPage(totalPages);
  }
}, [currentPage, totalPages]);

useEffect(() => {
  setCurrentPage(1);
}, [search]);

  const handleOpenPostModal = () => {
    if (!canPostJob) {
      requireSignIn({
        message: 'Please sign in with your alumni account before posting a job vacancy.',
      });
      return;
    }

    setIsPostModalOpen(true);
  };

  const handleOpenJobDetails = (job: JobVacancyViewModel) => {
    navigate(ROUTES.JOB_VACANCY_DETAIL(job.id));
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <>
      <SEO
        title="Job Vacancies"
        description="Discover exclusive job listings shared with the FGGC Owerri Alumnae Association."
      />

      <main className="min-h-full bg-[#F8F8F7] text-[#071116]">
        <section className={jobsPageShellClassName} aria-labelledby="jobs-page-title">
          <header className={jobsPageHeaderClassName}>
            <div className="flex-1">
              <div className="flex items-center justify-between gap-4 lg:block">
                <h1 id="jobs-page-title" className={jobsPageTitleClassName}>
                  Job Vacancies
                </h1>
                <Button
                  type="button"
                  size="sm"
                  className="inline-flex min-h-10 w-10 shrink-0 items-center justify-center rounded-full px-0 shadow-none lg:hidden"
                  onClick={handleOpenPostModal}
                  aria-label="Post a Job"
                >
                  <Plus strokeWidth={2.35} />
                </Button>
              </div>
              <p className={jobsPageSubtitleClassName}>Discover exclusive job listings</p>
            </div>

            <Button
              type="button"
              size="sm"
              className={`${jobsPagePostButtonClassName} hidden lg:inline-flex`}
              onClick={handleOpenPostModal}
            >
              Post a Job
              <Plus strokeWidth={2.35} />
            </Button>
      </header>

          {!isLoading && !isError && orderedVacancies.length > 0 ? (
            <div className="mb-6 w-full sm:max-w-xl">
              <SearchInput
                value={search}
                onValueChange={setSearch}
                placeholder="Search job vacancies"
                inputClassName="!h-10 !py-0"
              />
            </div>
          ) : null}

          {isLoading ? <JobsLoadingState /> : null}

          {!isLoading && isError ? (
            <EmptyState
              icon={SearchX}
              title="We couldn't load job vacancies"
              description={error instanceof Error ? error.message : 'Please try again.'}
              actionLabel="Try Again"
              onAction={() => {
                void refetch();
              }}
            />
          ) : null}

          {!isLoading && !isError && orderedVacancies.length === 0 ? (
            <EmptyState
              icon={BriefcaseBusiness}
              title="No job vacancies yet"
              description="Once a job is posted, it will show up here for the community to explore."
              actionLabel={canPostJob ? 'Post the First Job' : undefined}
              onAction={canPostJob ? () => setIsPostModalOpen(true) : undefined}
            />
          ) : null}

          {!isLoading && !isError && orderedVacancies.length > 0 && filteredVacancies.length === 0 ? (
            <EmptyState
              title="No job vacancies found"
              description="Try adjusting your search."
            />
          ) : null}

          {!isLoading && !isError && filteredVacancies.length > 0 ? (
            <>
              <div className={jobsGridClassName}>
                {visibleVacancies.map((job, index) => (
                  <JobCard
                    key={job.id}
                    job={job}
                    tone={getTone((currentPage - 1) * JOB_VACANCIES_PER_PAGE + index)}
                    onDetails={handleOpenJobDetails}
                  />
                ))}
              </div>
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                onPageChange={handlePageChange}
              />
            </>
          ) : null}
        </section>
      </main>

      {isPostModalOpen ? (
        <PostJobModal
          chapterId={user?.chapterId}
          onClose={() => setIsPostModalOpen(false)}
          onSubmitted={() => {
            setIsPostModalOpen(false);
          }}
        />
      ) : null}
    </>
  );
}
