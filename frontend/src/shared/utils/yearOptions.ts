export type YearValue = number | string | null | undefined;

type AvailableYearOptions = {
  minYear?: number;
  maxYear?: number;
};

const DEFAULT_MIN_YEAR = 1950;

function parseYear(value: YearValue) {
  const year = typeof value === 'number' ? value : Number(String(value ?? '').trim());

  return Number.isInteger(year) ? year : undefined;
}

/** Returns unique, valid years ordered from newest to oldest. */
export function getAvailableYears(
  values: Iterable<YearValue>,
  { minYear = DEFAULT_MIN_YEAR, maxYear = new Date().getFullYear() }: AvailableYearOptions = {},
) {
  return [
    ...new Set(Array.from(values, parseYear).filter((year): year is number => year !== undefined)),
  ]
    .filter((year) => year >= minYear && year <= maxYear)
    .sort((first, second) => second - first);
}

/** Returns a sensible fallback range when no API-backed years are available. */
export function getGenericYearOptions({
  minYear = DEFAULT_MIN_YEAR,
  maxYear = new Date().getFullYear(),
}: AvailableYearOptions = {}) {
  return Array.from({ length: Math.max(0, maxYear - minYear + 1) }, (_, index) => maxYear - index);
}

export function getYearOptions(values: Iterable<YearValue>, options?: AvailableYearOptions) {
  const availableYears = getAvailableYears(values, options);
  const years = availableYears.length > 0 ? availableYears : getGenericYearOptions(options);

  return years.map((year) => ({ label: String(year), value: String(year) }));
}
