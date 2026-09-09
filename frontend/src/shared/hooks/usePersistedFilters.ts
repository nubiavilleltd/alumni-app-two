import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

type FilterValues = Record<string, string>;

interface UsePersistedFiltersOptions {
  paginationParams?: string[];
}

function readUrlFilters<T extends FilterValues>(searchParams: URLSearchParams, defaults: T): T {
  const next = { ...defaults };

  (Object.keys(defaults) as Array<keyof T>).forEach((key) => {
    const value = searchParams.get(String(key));
    if (value !== null) next[key] = value as T[typeof key];
  });

  return next;
}

function readStoredFilters<T extends FilterValues>(storageKey: string, defaults: T): T {
  if (typeof window === 'undefined') return { ...defaults };

  try {
    const stored = window.sessionStorage.getItem(storageKey);
    if (!stored) return { ...defaults };

    const parsed = JSON.parse(stored) as Record<string, unknown>;
    const next = { ...defaults };

    (Object.keys(defaults) as Array<keyof T>).forEach((key) => {
      const value = parsed[String(key)];
      if (typeof value === 'string') next[key] = value as T[typeof key];
    });

    return next;
  } catch {
    return { ...defaults };
  }
}

function haveSameValues<T extends FilterValues>(first: T, second: T) {
  return (Object.keys(first) as Array<keyof T>).every((key) => first[key] === second[key]);
}

/**
 * Keeps page-specific filters reusable while persisting them across list/detail navigation.
 * URL values take precedence over session storage, so browser Back and shared URLs work too.
 */
export function usePersistedFilters<T extends FilterValues>(
  storageKey: string,
  defaults: T,
  { paginationParams = ['page'] }: UsePersistedFiltersOptions = {},
) {
  const [searchParams, setSearchParams] = useSearchParams();
  const filterKeysRef = useRef(Object.keys(defaults));
  const initialFiltersRef = useRef<T>(
    filterKeysRef.current.some((key) => searchParams.has(key))
      ? readUrlFilters(searchParams, defaults)
      : readStoredFilters(storageKey, defaults),
  );
  const [filters, setFiltersState] = useState<T>(initialFiltersRef.current);
  const filtersRef = useRef(filters);

  const applyFilters = useCallback(
    (nextFilters: T) => {
      filtersRef.current = nextFilters;
      setFiltersState(nextFilters);
      setSearchParams(
        (currentParams) => {
          const nextParams = new URLSearchParams(currentParams);

          filterKeysRef.current.forEach((key) => {
            const value = nextFilters[key] ?? '';
            if (value === defaults[key]) nextParams.delete(key);
            else nextParams.set(key, value);
          });

          paginationParams.forEach((param) => nextParams.delete(param));
          return nextParams;
        },
        { replace: true },
      );
    },
    [defaults, paginationParams, setSearchParams],
  );

  const setFilter = useCallback(
    <K extends keyof T>(key: K, value: T[K]) => {
      applyFilters({ ...filtersRef.current, [key]: value });
    },
    [applyFilters],
  );

  const setFilters = useCallback(
    (updates: Partial<T>) => {
      applyFilters({ ...filtersRef.current, ...updates });
    },
    [applyFilters],
  );

  const clearFilters = useCallback(() => {
    applyFilters({ ...defaults });
  }, [applyFilters, defaults]);

  useEffect(() => {
    filtersRef.current = filters;

    if (typeof window !== 'undefined') {
      window.sessionStorage.setItem(storageKey, JSON.stringify(filters));
    }
  }, [filters, storageKey]);

  useEffect(() => {
    if (!filterKeysRef.current.some((key) => searchParams.has(key))) return;

    const urlFilters = readUrlFilters(searchParams, defaults);
    if (haveSameValues(filtersRef.current, urlFilters)) return;

    filtersRef.current = urlFilters;
    setFiltersState(urlFilters);
  }, [defaults, searchParams]);

  return { filters, setFilter, setFilters, clearFilters };
}
