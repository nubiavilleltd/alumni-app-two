import {
  CreateVacancyPayload,
  JobVacancy,
  JobType,
  LevelOfExpertise,
  UpdateVacancyPayload,
  WorkplaceType,
} from '../types/jobVacancies.types';
import { splitLocation } from '@/shared/utils/location';

export type JobVacancyViewModel = {
  id: string;
  ownerId?: string;
  postedByName?: string;
  title: string;
  companyName: string;
  jobType: JobType;
  workplaceType: WorkplaceType;
  levelOfExpertise: LevelOfExpertise;
  postedAt: string;
  createdAt?: string;
  salary: string;
  currency: string;
  location: string;
  address: string;
  city: string;
  state: string;
  tags: string[];
  requirements: string;
  responsibilities: string;
  aboutRole: string;
  applicationMode: 'email' | 'link';
  applicationEmail?: string;
  applicationUrl?: string;
  flyer?: string;
};

function isNumericIdentifier(value: unknown) {
  return typeof value === 'number' || (typeof value === 'string' && /^\d+$/.test(value.trim()));
}

function toOptionalString(value: unknown) {
  return value !== null && value !== undefined && value !== '' ? String(value) : undefined;
}

export function vacancyToViewModel(vacancy: JobVacancy): JobVacancyViewModel {
  const location = String(vacancy.location ?? '');
  const locationParts = splitLocation(location);
  const directOwnerId =
    vacancy.created_by ??
    vacancy.createdBy ??
    vacancy.user_id ??
    vacancy.userId ??
    vacancy.member_id;
  const legacyPostedById = isNumericIdentifier(vacancy.posted_by)
    ? vacancy.posted_by
    : isNumericIdentifier(vacancy.postedBy)
      ? vacancy.postedBy
      : undefined;
  const ownerId = directOwnerId ?? legacyPostedById;
  const postedByName =
    vacancy.posted_by_name ??
    vacancy.postedByName ??
    (!isNumericIdentifier(vacancy.posted_by) ? vacancy.posted_by : undefined) ??
    (!isNumericIdentifier(vacancy.postedBy) ? vacancy.postedBy : undefined);
  const createdAt =
    vacancy.created_at ??
    vacancy.createdAt ??
    vacancy.posted_at ??
    vacancy.postedAt ??
    vacancy.updated_at;

  return {
    id: String(vacancy.id),
    ownerId: toOptionalString(ownerId),
    postedByName: toOptionalString(postedByName),
    title: vacancy.job_title,
    companyName: vacancy.company_name,
    jobType: vacancy.job_type,
    workplaceType: vacancy.workplace_type,
    levelOfExpertise: vacancy.level_of_expertise,
    postedAt: vacancy.application_deadline,
    createdAt:
      createdAt !== null && createdAt !== undefined && createdAt !== ''
        ? String(createdAt)
        : undefined,
    salary: vacancy.salary,
    currency: vacancy.currency ? String(vacancy.currency) : 'NGN',
    location,
    ...locationParts,
    tags: vacancy.keywords
      ? vacancy.keywords
          .split(',')
          .map((keyword) => keyword.trim())
          .filter(Boolean)
      : [],
    requirements: vacancy.requirements,
    responsibilities: vacancy.responsibilities,
    aboutRole: vacancy.about_role,
    applicationMode: vacancy.application_type,
    applicationEmail: vacancy.application_email,
    applicationUrl: vacancy.application_link,
    flyer: vacancy.flyer,
  };
}

export function createVacancyFormData(payload: CreateVacancyPayload): FormData {
  const formData = new FormData();

  formData.append('job_title', payload.job_title);
  formData.append('company_name', payload.company_name);
  formData.append('job_type', payload.job_type);
  formData.append('workplace_type', payload.workplace_type);
  formData.append('level_of_expertise', payload.level_of_expertise);
  formData.append('location', payload.location);
  formData.append('salary', payload.salary);
  if (payload.currency) formData.append('currency', String(payload.currency));
  formData.append('application_deadline', payload.application_deadline);
  formData.append('keywords', payload.keywords ?? '');
  formData.append('about_role', payload.about_role);
  formData.append('responsibilities', payload.responsibilities);
  formData.append('requirements', payload.requirements);
  formData.append('application_type', payload.application_type);
  formData.append('chapter_id', String(payload.chapter_id));

  if (payload.application_type === 'email' && payload.application_email) {
    formData.append('application_email', payload.application_email);
  }

  if (payload.application_type === 'link' && payload.application_link) {
    formData.append('application_link', payload.application_link);
  }

  if (payload.flyer) {
    formData.append('flyer', payload.flyer);
  }

  return formData;
}

export function createUpdateVacancyPayload(payload: UpdateVacancyPayload) {
  return {
    function_type: 'update' as const,
    ...payload,
  };
}

export function createDeleteVacancyPayload(id: number | string) {
  return {
    function_type: 'delete' as const,
    id,
  };
}
