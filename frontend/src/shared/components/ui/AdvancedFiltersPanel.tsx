import { SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { FilterDropdown } from './FilterDropdown';
import { DatePicker } from './input/DatePicker';
import { SearchInput } from './input/SearchInput';

export interface AdvancedFilterOption {
  label: string;
  value: string;
  count?: number;
  disabled?: boolean;
}

export type AdvancedFilterField =
  | {
      key: string;
      kind: 'search';
      value: string;
      placeholder?: string;
    }
  | {
      key: string;
      kind: 'select';
      value: string;
      label?: string;
      placeholder?: string;
      options: AdvancedFilterOption[];
    }
  | {
      key: string;
      kind: 'date';
      value: string;
      label?: string;
      placeholder?: string;
      min?: string;
      max?: string;
    };

interface AdvancedFiltersPanelProps {
  title: string;
  description: string;
  fields: AdvancedFilterField[];
  onFieldChange: (key: string, value: string) => void;
  onReset: () => void;
  hasActiveFilters: boolean;
  activeFilterCount?: number;
  gridClassName?: string;
  className?: string;
  customContent?: ReactNode;
}

export function AdvancedFiltersPanel({
  title,
  description,
  fields,
  onFieldChange,
  onReset,
  hasActiveFilters,
  activeFilterCount,
  gridClassName = 'grid-cols-1 gap-4 md:grid-cols-3',
  className = 'mt-4 mb-8',
  customContent,
}: AdvancedFiltersPanelProps) {
  const visibleActiveFilterCount =
    activeFilterCount ?? fields.filter((field) => field.value.trim().length > 0).length;

  return (
    <div
      className={`${className} relative rounded-[1.5rem] border border-[#dfe8f1] bg-[#f7fafc] shadow-[0_1rem_2.5rem_rgba(17,55,84,0.06)]`}
    >
      <div className="flex flex-wrap items-start justify-between gap-4 rounded-t-[1.5rem] border-b border-[#e6edf3] bg-white/80 px-4 py-4 sm:px-5">
        <div className="flex min-w-0 items-start gap-3">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
            <SlidersHorizontal className="h-4 w-4" strokeWidth={2.2} />
          </span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-sm font-semibold text-[#071116]">{title}</h2>
              {visibleActiveFilterCount > 0 && (
                <span className="rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-primary-700">
                  {visibleActiveFilterCount} active
                </span>
              )}
            </div>
            <p className="mt-1 max-w-2xl text-xs leading-5 text-[#69727d]">{description}</p>
          </div>
        </div>
        {hasActiveFilters && (
          <button
            type="button"
            onClick={onReset}
            className="shrink-0 rounded-full border border-[#d9e5ee] bg-white px-3 py-1.5 text-xs font-semibold text-primary-600 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700"
          >
            Clear filters
          </button>
        )}
      </div>

      <div className={`grid gap-x-4 gap-y-3 rounded-b-[1.5rem] p-4 sm:p-5 ${gridClassName}`}>
        {customContent}
        {fields.map((field) => {
          if (field.kind === 'search') {
            return (
              <SearchInput
                key={field.key}
                value={field.value}
                onValueChange={(value) => onFieldChange(field.key, value)}
                placeholder={field.placeholder}
                inputClassName="!h-10 !py-0"
                containerClassName="h-10"
              />
            );
          }

          if (field.kind === 'date') {
            return (
              <DatePicker
                key={field.key}
                id={`advanced-filter-${field.key}`}
                label={field.label}
                value={field.value}
                onValueChange={(value) => onFieldChange(field.key, value)}
                placeholder={field.placeholder}
                min={field.min}
                max={field.max}
                className="w-full"
                labelClassName="text-xs font-medium text-gray-600"
                inputClassName="!h-10 !py-0"
              />
            );
          }

          return (
            <FilterDropdown
              key={field.key}
              label={field.label}
              value={field.value}
              onChange={(value) => onFieldChange(field.key, value)}
              options={field.options}
              placeholder={field.placeholder}
              className="!w-full"
            />
          );
        })}
      </div>
    </div>
  );
}
