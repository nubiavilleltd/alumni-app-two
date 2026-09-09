import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';

function parsePage(value: string | null): number {
  const page = Number(value);
  return Number.isInteger(page) && page > 0 ? page : 1;
}

/**
 * Keeps a list's pagination state in the URL so it survives navigation to a
 * detail page and is restored by the browser's Back button.
 */
export function useUrlPagination(param = 'page') {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = parsePage(searchParams.get(param));

  const setPage = useCallback(
    (nextPage: number) => {
      const normalizedPage = Number.isInteger(nextPage) && nextPage > 0 ? nextPage : 1;

      setSearchParams(
        (currentParams) => {
          const nextParams = new URLSearchParams(currentParams);

          if (normalizedPage === 1) {
            nextParams.delete(param);
          } else {
            nextParams.set(param, String(normalizedPage));
          }

          return nextParams;
        },
        { replace: true },
      );
    },
    [param, setSearchParams],
  );

  return [page, setPage] as const;
}
