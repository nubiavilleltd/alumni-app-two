import { SelectInput } from './SelectInput';
import { renderIcon, type AppIcon } from '@/shared/utils/renderIcon';

interface FilterDropdownProps {
  value: string;
  onChange: (value: string) => void;
  options: { label: string; value: string; count?: number; disabled?: boolean }[];
  label?: string;
  placeholder?: string;
  className?: string;
  selectClassName?: string;
  showClearButton?: boolean;
  clearLabel?: string;
  clearIcon?: AppIcon;
  chevronDownIcon?: AppIcon;
  chevronUpIcon?: AppIcon;
  sortOptionsAlphabetically?: boolean;
}

export function FilterDropdown({
  value,
  onChange,
  options,
  label,
  placeholder = 'All',
  className = '',
  selectClassName = '',
  showClearButton = true,
  clearLabel = 'Clear filter',
  clearIcon = 'mdi:close-circle',
  chevronDownIcon = 'mdi:chevron-down',
  chevronUpIcon = 'mdi:chevron-up',
  sortOptionsAlphabetically = true,
}: FilterDropdownProps) {
  const hasValue = value.trim().length > 0;
  const shouldShowClear = showClearButton && hasValue;
  const hasResultCounts = options.some((option) => typeof option.count === 'number');
  const displayOptions = hasResultCounts
    ? options
        .map((option) => ({
          ...option,
          disabled: option.count === 0 || option.disabled,
        }))
        .sort((first, second) => {
          const firstHasResults = first.count !== 0;
          const secondHasResults = second.count !== 0;

          if (firstHasResults !== secondHasResults) return firstHasResults ? -1 : 1;
          if (!sortOptionsAlphabetically) return 0;
          return first.label.localeCompare(second.label);
        })
    : options;

  return (
    <div className={`w-full sm:w-48 ${className}`}>
      {label && <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>}
      <div className="relative">
        <SelectInput
          value={value}
          onChange={(event) => onChange(event.target.value)}
          options={displayOptions}
          placeholder={placeholder}
          sortOptionsAlphabetically={hasResultCounts ? false : sortOptionsAlphabetically}
          className={`w-full ${selectClassName}`}
          // controlClassName={shouldShowClear ? '!pr-16' : ''}
          controlClassName={`!h-10 !py-0 flex items-center ${shouldShowClear ? '!pr-16' : ''}`}
          chevronDownIcon={chevronDownIcon}
          chevronUpIcon={chevronUpIcon}
        />

        {shouldShowClear ? (
          <button
            type="button"
            onClick={() => onChange('')}
            className="absolute right-9 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            aria-label={clearLabel}
          >
            {renderIcon(clearIcon, 'h-4 w-4')}
          </button>
        ) : null}
      </div>
    </div>
  );
}
