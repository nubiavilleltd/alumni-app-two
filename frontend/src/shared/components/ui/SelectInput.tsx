import { forwardRef, useState, useRef, useEffect, useCallback, useMemo } from 'react';
import { renderIcon, type AppIcon } from '@/shared/utils/renderIcon';

interface SelectOption {
  label: string;
  value: string;
  count?: number;
  disabled?: boolean;
}

function findEnabledOptionIndex(options: SelectOption[], startIndex: number, direction: 1 | -1) {
  if (options.length === 0) return 0;

  const normalizedStart = Math.min(Math.max(startIndex, 0), options.length - 1);
  for (let offset = 0; offset < options.length; offset += 1) {
    const index = (normalizedStart + direction * offset + options.length * 2) % options.length;
    if (!options[index].disabled) return index;
  }

  return 0;
}

interface SelectInputProps extends Omit<
  React.SelectHTMLAttributes<HTMLSelectElement>,
  'onChange' | 'onBlur'
> {
  label?: string;
  error?: string;
  hint?: string;
  options: readonly SelectOption[];
  placeholder?: string;
  sortOptionsAlphabetically?: boolean;
  controlClassName?: string;
  labelClassName?: string;
  onChange?: (event: React.ChangeEvent<HTMLSelectElement>) => void;
  onBlur?: (event: React.FocusEvent<HTMLSelectElement>) => void;
  chevronDownIcon?: AppIcon;
  chevronUpIcon?: AppIcon;
  searchIcon?: AppIcon;
  selectedIcon?: AppIcon;
  errorIcon?: AppIcon;
}

export const SelectInput = forwardRef<HTMLSelectElement, SelectInputProps>(
  (
    {
      label,
      error,
      hint,
      options,
      placeholder = 'Select an option',
      sortOptionsAlphabetically = true,
      controlClassName = '',
      labelClassName = '',
      chevronDownIcon = 'mdi:chevron-down',
      chevronUpIcon = 'mdi:chevron-up',
      searchIcon = 'mdi:magnify',
      selectedIcon = 'mdi:check',
      errorIcon = 'mdi:alert-circle-outline',
      id,
      name,
      required,
      disabled,
      className = '',
      value,
      onChange,
      onBlur,
      ...rest
    },
    ref,
  ) => {
    const inputId = id ?? name;
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [highlightedIndex, setHighlightedIndex] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const hiddenSelectRef = useRef<HTMLSelectElement>(null);
    const onChangeRef = useRef(onChange);
    const onBlurRef = useRef(onBlur);

    function toTitleCase(value: string) {
      return value.toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function normalizeSelectOptionLabel(label: string) {
      return toTitleCase(label.trim());
    }

    // Keep refs updated
    useEffect(() => {
      onChangeRef.current = onChange;
      onBlurRef.current = onBlur;
    }, [onChange, onBlur]);

    // Combine refs if needed
    useEffect(() => {
      if (ref) {
        if (typeof ref === 'function') {
          ref(hiddenSelectRef.current);
        } else {
          ref.current = hiddenSelectRef.current;
        }
      }
    }, [ref]);

    const normalizedOptions = useMemo(() => {
      const nextOptions = [...options].map((option) => ({
        ...option,
        label: normalizeSelectOptionLabel(option.label),
      }));

      if (!sortOptionsAlphabetically) {
        return nextOptions;
      }

      const [firstOption, ...remainingOptions] = nextOptions;

      if (!firstOption) {
        return nextOptions;
      }

      const sortedRemainingOptions = remainingOptions.sort((a, b) =>
        a.label.localeCompare(b.label),
      );
      const normalizedPlaceholder = normalizeSelectOptionLabel(placeholder);
      const keepFirstOptionFirst =
        firstOption.value === '' || firstOption.label === normalizedPlaceholder;

      return keepFirstOptionFirst
        ? [firstOption, ...sortedRemainingOptions]
        : [firstOption, ...sortedRemainingOptions].sort((a, b) => a.label.localeCompare(b.label));
    }, [options, placeholder, sortOptionsAlphabetically]);

    // Filter options based on search
    const filteredOptions = useMemo(
      () =>
        normalizedOptions.filter((option) =>
          option.label.toLowerCase().includes(searchQuery.toLowerCase()),
        ),
      [normalizedOptions, searchQuery],
    );

    // Show search only if there are more than 5 options total
    const showSearch = normalizedOptions.length > 5;

    // Get display label for selected value
    const selectedOption = normalizedOptions.find((opt) => opt.value === value);
    const displayLabel = selectedOption?.label || '';

    // Ref for the options list to handle scrolling
    const optionsListRef = useRef<HTMLDivElement>(null);

    // Scroll highlighted item into view
    useEffect(() => {
      if (isOpen && optionsListRef.current) {
        const highlightedElement = optionsListRef.current.children[highlightedIndex] as HTMLElement;
        if (highlightedElement) {
          highlightedElement.scrollIntoView({
            block: 'nearest',
            behavior: 'smooth',
          });
        }
      }
    }, [highlightedIndex, isOpen]);

    // Close dropdown when clicking outside
    useEffect(() => {
      const handleClickOutside = (event: MouseEvent) => {
        if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
          setIsOpen(false);
          setSearchQuery('');
        }
      };

      if (isOpen) {
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
      }
    }, [isOpen]);

    // Focus search input when dropdown opens
    useEffect(() => {
      if (isOpen && searchInputRef.current) {
        searchInputRef.current.focus();
      }
    }, [isOpen]);

    // Reset highlighted index when search changes
    useEffect(() => {
      setHighlightedIndex(findEnabledOptionIndex(filteredOptions, 0, 1));
    }, [filteredOptions, searchQuery]);

    const handleToggle = () => {
      if (!disabled) {
        setIsOpen(!isOpen);
        if (!isOpen) {
          setHighlightedIndex(findEnabledOptionIndex(filteredOptions, 0, 1));
        }
        if (isOpen) {
          setSearchQuery('');
        }
      }
    };

    const handleSelect = useCallback((option: SelectOption) => {
      if (option.disabled) return;
      if (!hiddenSelectRef.current) return;

      // Update the hidden select's value first
      const prevValue = hiddenSelectRef.current.value;
      hiddenSelectRef.current.value = option.value;

      // Only trigger change if value actually changed
      if (prevValue !== option.value) {
        // Create a native-like change event
        const nativeEvent = new Event('change', { bubbles: true });
        Object.defineProperty(nativeEvent, 'target', {
          writable: false,
          value: hiddenSelectRef.current,
        });
        Object.defineProperty(nativeEvent, 'currentTarget', {
          writable: false,
          value: hiddenSelectRef.current,
        });

        // Dispatch the native event (for react-hook-form's register)
        hiddenSelectRef.current.dispatchEvent(nativeEvent);

        // Also call the onChange prop if provided (for manual handlers)
        if (onChangeRef.current) {
          const syntheticEvent = {
            ...nativeEvent,
            target: hiddenSelectRef.current,
            currentTarget: hiddenSelectRef.current,
          } as unknown as React.ChangeEvent<HTMLSelectElement>;
          onChangeRef.current(syntheticEvent);
        }
      }

      setIsOpen(false);
      setSearchQuery('');
    }, []);

    const handleKeyDown = useCallback(
      (event: React.KeyboardEvent) => {
        if (disabled) return;

        switch (event.key) {
          case 'Enter':
            event.preventDefault();
            if (isOpen && filteredOptions[highlightedIndex]) {
              handleSelect(filteredOptions[highlightedIndex]);
            } else if (!isOpen) {
              setIsOpen(true);
              setHighlightedIndex(findEnabledOptionIndex(filteredOptions, 0, 1));
            }
            break;
          case 'Escape':
            event.preventDefault();
            setIsOpen(false);
            setSearchQuery('');
            break;
          case 'ArrowDown':
            event.preventDefault();
            if (!isOpen) {
              setIsOpen(true);
              setHighlightedIndex(findEnabledOptionIndex(filteredOptions, 0, 1));
            } else {
              setHighlightedIndex((prev) => findEnabledOptionIndex(filteredOptions, prev + 1, 1));
            }
            break;
          case 'ArrowUp':
            event.preventDefault();
            if (isOpen) {
              setHighlightedIndex((prev) => findEnabledOptionIndex(filteredOptions, prev - 1, -1));
            }
            break;
          case 'Tab':
            setIsOpen(false);
            setSearchQuery('');
            break;
        }
      },
      [disabled, isOpen, filteredOptions, highlightedIndex, handleSelect],
    );

    const handleBlur = useCallback(() => {
      // Small delay to check if focus moved outside the component
      setTimeout(() => {
        if (
          containerRef.current &&
          !containerRef.current.contains(document.activeElement) &&
          hiddenSelectRef.current
        ) {
          // Create a native-like blur event
          const nativeEvent = new FocusEvent('blur', { bubbles: true });
          Object.defineProperty(nativeEvent, 'target', {
            writable: false,
            value: hiddenSelectRef.current,
          });
          Object.defineProperty(nativeEvent, 'currentTarget', {
            writable: false,
            value: hiddenSelectRef.current,
          });

          // Dispatch the native event (for react-hook-form's register)
          hiddenSelectRef.current.dispatchEvent(nativeEvent);

          // Also call the onBlur prop if provided (for manual handlers)
          if (onBlurRef.current) {
            const syntheticEvent = {
              ...nativeEvent,
              target: hiddenSelectRef.current,
              currentTarget: hiddenSelectRef.current,
            } as unknown as React.FocusEvent<HTMLSelectElement>;
            onBlurRef.current(syntheticEvent);
          }
        }
      }, 0);
    }, []);

    return (
      <div className={`select-input flex flex-col gap-1 ${className}`}>
        {label && (
          <label
            htmlFor={inputId}
            className={`select-input__label block text-sm font-medium text-gray-700 ${labelClassName}`}
          >
            {label}
            {required && <span className="text-red-500 ml-0.5">*</span>}
          </label>
        )}

        <div ref={containerRef} className="select-input__control-wrap relative" onBlur={handleBlur}>
          {/* Hidden native select for form compatibility */}
          <select
            ref={hiddenSelectRef}
            id={inputId}
            name={name}
            required={required}
            disabled={disabled}
            value={value}
            onChange={() => {}} // Controlled by handleSelect
            className="sr-only"
            tabIndex={-1}
            aria-hidden="true"
            {...rest}
          >
            <option value="" disabled>
              {placeholder}
            </option>
            {normalizedOptions.map((opt) => (
              <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                {opt.label}
              </option>
            ))}
          </select>

          {/* Custom dropdown trigger */}
          <button
            type="button"
            onClick={handleToggle}
            onKeyDown={handleKeyDown}
            disabled={disabled}
            className={`w-full text-left border rounded-3xl px-4 py-2.5 pr-9 text-sm outline-none transition-all shadow-sm
              ${error ? 'border-red-400 focus:border-red-400 focus:ring-2 focus:ring-red-100' : 'border-gray-200 focus:border-primary-400 focus:ring-2 focus:ring-primary-100'}
              ${disabled ? 'opacity-50 bg-gray-50 cursor-not-allowed' : 'bg-white cursor-pointer hover:border-gray-300'}
              ${isOpen ? 'border-primary-400 ring-2 ring-primary-100' : ''}
              ${controlClassName}
            `}
          >
            <span
              className={`block min-w-0 truncate whitespace-nowrap ${
                displayLabel ? 'text-gray-700' : 'text-[#828282]'
              }`}
              title={displayLabel || placeholder}
            >
              {displayLabel || placeholder}
            </span>
          </button>

          {renderIcon(
            isOpen ? chevronUpIcon : chevronDownIcon,
            `select-input__icon pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 transition-colors ${
              disabled ? 'text-gray-300' : 'text-gray-400'
            }`,
          )}

          {/* Dropdown menu */}
          {isOpen && !disabled && (
            <div className="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-lg overflow-hidden">
              {/* Search input */}
              {showSearch && (
                <div className="p-2 border-b border-gray-100">
                  <div className="relative">
                    {renderIcon(
                      searchIcon,
                      'absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400',
                    )}
                    <input
                      ref={searchInputRef}
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      onKeyDown={handleKeyDown}
                      placeholder="Search..."
                      className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                    />
                  </div>
                </div>
              )}

              {/* Options list */}
              <div ref={optionsListRef} className="max-h-60 overflow-y-auto overscroll-contain">
                {filteredOptions.length === 0 ? (
                  <div className="px-4 py-8 text-center text-sm text-gray-400">
                    No options found
                  </div>
                ) : (
                  filteredOptions.map((option, index) => (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => handleSelect(option)}
                      onMouseEnter={() => setHighlightedIndex(index)}
                      disabled={option.disabled}
                      className={`w-full text-left px-4 py-2.5 text-sm transition-colors
                        ${value === option.value ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-700'}
                        ${highlightedIndex === index && value !== option.value ? 'bg-gray-50' : ''}
                        ${option.disabled ? 'cursor-not-allowed text-gray-300' : ''}
                        hover:bg-gray-50
                      `}
                    >
                      <div className="flex items-center justify-between gap-2">
                        <span className="truncate" title={option.label}>
                          {option.label}
                        </span>
                        {typeof option.count === 'number' && (
                          <span
                            className={`min-w-6 rounded-full px-1.5 py-0.5 text-center text-[11px] font-semibold ${
                              option.disabled
                                ? 'bg-gray-100 text-gray-300'
                                : 'bg-gray-100 text-gray-500'
                            }`}
                          >
                            {option.count}
                          </span>
                        )}
                        {value === option.value &&
                          renderIcon(selectedIcon, 'h-4 w-4 flex-shrink-0 text-primary-600')}
                      </div>
                    </button>
                  ))
                )}
              </div>
            </div>
          )}
        </div>

        {error ? (
          <p className="select-input__message select-input__message--error text-xs text-red-500 flex items-center gap-1">
            {renderIcon(errorIcon, 'h-3 w-3')}
            {error}
          </p>
        ) : hint ? (
          <p className="select-input__message select-input__message--hint text-xs text-gray-400">
            {hint}
          </p>
        ) : null}
      </div>
    );
  },
);

SelectInput.displayName = 'SelectInput';
