export type JobType = 'full_time' | 'part_time' | 'contract' | 'internship' | 'freelance';

export type WorkplaceType = 'remote' | 'hybrid' | 'on_site';

export type LevelOfExpertise = 'entry_level' | 'mid_level' | 'senior_level' | 'executive';

export type ApplicationType = 'email' | 'link';
export type VacancyCurrency = 'NGN';

export type JobVacancy = {
  id: number | string;
  job_title: string;
  company_name: string;
  user_id?: number | string;
  job_type: JobType;
  workplace_type: WorkplaceType;
  level_of_expertise: LevelOfExpertise;
  location: string;
  address?: string;
  city?: string;
  state?: string;
  salary: string;
  currency?: VacancyCurrency | string;
  application_deadline: string;
  keywords?: string;
  about_role: string;
  responsibilities: string;
  requirements: string;
  application_type: ApplicationType;
  application_email?: string;
  application_link?: string;
  chapter_id?: number | string;
  created_by?: number | string;
  createdBy?: number | string;
  userId?: number | string;
  posted_by?: number | string;
  postedBy?: number | string;
  posted_by_name?: string;
  postedByName?: string;
  member_id?: number | string;
  flyer?: string;
  created_at?: string;
  createdAt?: string;
  posted_at?: string;
  postedAt?: string;
  updated_at?: string;
};

export type CreateVacancyPayload = {
  job_title: string;
  company_name: string;
  job_type: JobType;
  workplace_type: WorkplaceType;
  level_of_expertise: LevelOfExpertise;
  location: string;
  salary: string;
  currency?: VacancyCurrency | string;
  application_deadline: string;
  keywords?: string;
  about_role: string;
  responsibilities: string;
  requirements: string;
  application_type: ApplicationType;
  application_email?: string;
  application_link?: string;
  chapter_id: number | string;
  flyer?: File | null;
};

export type GetVacanciesFilters = Partial<{
  job_type: JobType;
  workplace_type: WorkplaceType;
  level_of_expertise: LevelOfExpertise;
  location: string;
}>;

export type UpdateVacancyPayload = Partial<CreateVacancyPayload> & {
  id: number | string;
};

export type DeleteVacancyPayload = {
  id: number | string;
};

export type VacancyOption<T extends string = string> = {
  label: string;
  value: T;
};

export const JOB_TYPE_OPTIONS: VacancyOption<JobType>[] = [
  { value: 'full_time', label: 'Full Time' },
  { value: 'part_time', label: 'Part Time' },
  { value: 'contract', label: 'Contract' },
  { value: 'internship', label: 'Internship' },
  { value: 'freelance', label: 'Freelance' },
];

export const WORKPLACE_TYPE_OPTIONS: VacancyOption<WorkplaceType>[] = [
  { value: 'remote', label: 'Remote' },
  { value: 'hybrid', label: 'Hybrid' },
  { value: 'on_site', label: 'On Site' },
];

export const LEVEL_OF_EXPERTISE_OPTIONS: VacancyOption<LevelOfExpertise>[] = [
  { value: 'entry_level', label: 'Entry Level' },
  { value: 'mid_level', label: 'Mid Level' },
  { value: 'senior_level', label: 'Senior Level' },
  { value: 'executive', label: 'Executive' },
];

export const APPLICATION_TYPE_OPTIONS: VacancyOption<ApplicationType>[] = [
  { value: 'email', label: 'Email' },
  { value: 'link', label: 'Link' },
];
