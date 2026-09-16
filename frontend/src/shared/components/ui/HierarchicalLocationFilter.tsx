import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, ChevronLeft, ChevronUp, X } from 'lucide-react';

export type HierarchicalLocationNode = {
  label: string;
  value: string;
  count?: number;
  children?: HierarchicalLocationNode[];
};

export type HierarchicalLocationSelection = {
  state: string;
  city: string;
};

type LocationLevel = 'state' | 'city';

interface HierarchicalLocationFilterProps {
  value: HierarchicalLocationSelection;
  options: HierarchicalLocationNode[];
  onChange: (value: HierarchicalLocationSelection) => void;
  label?: string;
  placeholder?: string;
  className?: string;
  levelOneLabel?: string;
  levelTwoLabel?: string;
}

function getSelectionLabel(
  value: HierarchicalLocationSelection,
  options: HierarchicalLocationNode[],
  placeholder: string,
) {
  const state = options.find((option) => option.value === value.state);
  const city = state?.children?.find((option) => option.value === value.city);

  if (city) return [city.label, state?.label].filter(Boolean).join(', ');
  if (state) return state.label;
  return placeholder;
}

export function HierarchicalLocationFilter({
  value,
  options,
  onChange,
  label = 'Location',
  placeholder = 'All locations',
  className = '',
  levelOneLabel = 'State',
  levelTwoLabel = 'City',
}: HierarchicalLocationFilterProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [level, setLevel] = useState<LocationLevel>('state');
  const containerRef = useRef<HTMLDivElement>(null);
  const hasSelection = Boolean(value.state || value.city);

  const stateOption = useMemo(
    () => options.find((option) => option.value === value.state),
    [options, value.state],
  );
  const currentOptions = level === 'state' ? options : (stateOption?.children ?? []);
  const levelTitle =
    level === 'state'
      ? `Choose a ${levelOneLabel.toLowerCase()}`
      : `Choose a ${levelTwoLabel.toLowerCase()}`;

  useEffect(() => {
    if (!isOpen) return;

    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const openDropdown = () => {
    setLevel(value.city ? 'city' : 'state');
    setIsOpen(true);
  };

  const handleToggle = () => {
    if (isOpen) setIsOpen(false);
    else openDropdown();
  };

  const handleSelect = (option: HierarchicalLocationNode) => {
    if (option.count === 0) return;

    if (level === 'state') {
      onChange({ state: option.value, city: '' });
      if (option.children?.length) setLevel('city');
      else setIsOpen(false);
      return;
    }

    onChange({ state: value.state, city: option.value });
    setIsOpen(false);
  };

  const handleBack = () => {
    if (level === 'city') setLevel('state');
  };

  const handleClear = () => {
    onChange({ state: '', city: '' });
    setLevel('state');
    setIsOpen(false);
  };

  return (
    <div ref={containerRef} className={`flex min-w-0 flex-col gap-1 ${className}`}>
      {label ? <label className="block text-xs font-medium text-gray-600">{label}</label> : null}

      <div className="relative">
        <button
          type="button"
          onClick={handleToggle}
          onKeyDown={(event) => {
            if (event.key === 'Escape') setIsOpen(false);
          }}
          aria-expanded={isOpen}
          className={`flex h-10 w-full items-center rounded-3xl border bg-white px-4 py-2.5 pr-16 text-left text-sm outline-none transition-all shadow-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 ${
            isOpen
              ? 'border-primary-400 ring-2 ring-primary-100'
              : 'border-gray-200 hover:border-gray-300'
          }`}
        >
          <span
            className={`block min-w-0 truncate whitespace-nowrap ${hasSelection ? 'text-gray-700' : 'text-[#828282]'}`}
            title={getSelectionLabel(value, options, placeholder)}
          >
            {getSelectionLabel(value, options, placeholder)}
          </span>
        </button>

        {hasSelection ? (
          <button
            type="button"
            onClick={handleClear}
            className="absolute right-9 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            aria-label="Clear location filter"
          >
            <X className="h-4 w-4" />
          </button>
        ) : null}
        {isOpen ? (
          <ChevronUp className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        ) : (
          <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        )}

        {isOpen ? (
          <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
            <div className="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2">
              <div className="flex min-w-0 items-center gap-1">
                {level !== 'state' ? (
                  <button
                    type="button"
                    onClick={handleBack}
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700"
                    aria-label="Go back to previous location level"
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                ) : null}
                <span className="truncate text-xs font-semibold text-gray-600">{levelTitle}</span>
              </div>
              {hasSelection ? (
                <button
                  type="button"
                  onClick={handleClear}
                  className="shrink-0 text-xs font-semibold text-primary-600 transition-colors hover:text-primary-700"
                >
                  Clear
                </button>
              ) : null}
            </div>

            <div className="max-h-64 overflow-y-auto p-1">
              {currentOptions.length > 0 ? (
                [...currentOptions]
                  .sort((first, second) => {
                    const firstHasResults = first.count !== 0;
                    const secondHasResults = second.count !== 0;

                    if (firstHasResults !== secondHasResults) {
                      return firstHasResults ? -1 : 1;
                    }

                    return first.label.localeCompare(second.label);
                  })
                  .map((option) => {
                    const isDisabled = option.count === 0;

                    return (
                      <button
                        key={option.value}
                        type="button"
                        disabled={isDisabled}
                        onClick={() => handleSelect(option)}
                        className={`flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-colors ${
                          isDisabled
                            ? 'cursor-not-allowed text-gray-300'
                            : 'text-gray-700 hover:bg-gray-50'
                        }`}
                      >
                        <span className="min-w-0 truncate" title={option.label}>
                          {option.label}
                        </span>
                        {typeof option.count === 'number' ? (
                          <span
                            className={`min-w-7 rounded-full px-2 py-1 text-center text-xs font-semibold ${
                              isDisabled ? 'bg-gray-100 text-gray-300' : 'bg-gray-100 text-gray-500'
                            }`}
                          >
                            {option.count}
                          </span>
                        ) : null}
                      </button>
                    );
                  })
              ) : (
                <p className="px-3 py-8 text-center text-sm text-gray-400">
                  No more location levels available
                </p>
              )}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
