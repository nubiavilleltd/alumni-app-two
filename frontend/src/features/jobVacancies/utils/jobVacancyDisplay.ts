import type { JobVacancyViewModel } from '../api/adapters';
import {
  JOB_TYPE_OPTIONS,
  LEVEL_OF_EXPERTISE_OPTIONS,
  WORKPLACE_TYPE_OPTIONS,
} from '../types/jobVacancies.types';

export function formatJobDate(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

export function formatMoneyAmount(
  value: string,
  currency: string = 'NGN',
  fallback: string = value,
): string {
  const normalized = value.replace(/,/g, '').trim();
  if (!/^\d+(\.\d+)?$/.test(normalized)) return fallback;

  const amount = Number(normalized);
  if (!Number.isFinite(amount)) return fallback;

  try {
    return new Intl.NumberFormat(currency === 'NGN' ? 'en-NG' : 'en', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: amount % 1 === 0 ? 0 : 2,
    }).format(amount);
  } catch {
    return fallback;
  }
}

export function getSalaryDisplay(job: JobVacancyViewModel) {
  return formatMoneyAmount(job.salary, 'NGN', job.salary);
}

function getOptionLabel<T extends string>(options: { label: string; value: T }[], value?: T) {
  if (!value) return '';

  return options.find((option) => option.value === value)?.label ?? value;
}

export function getJobPillLabels(job: JobVacancyViewModel) {
  return [
    getOptionLabel(JOB_TYPE_OPTIONS, job.jobType),
    getOptionLabel(LEVEL_OF_EXPERTISE_OPTIONS, job.levelOfExpertise),
    getOptionLabel(WORKPLACE_TYPE_OPTIONS, job.workplaceType),
  ].filter(Boolean);
}
