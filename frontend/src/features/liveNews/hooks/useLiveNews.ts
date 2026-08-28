// features/alumni/hooks/useAlumni.ts

import { useQuery } from '@tanstack/react-query';
import { liveNewsService } from '../services/livenews.service';

// ─── Query Keys ───────────────────────────────────────────────────────────────
export const liveNewsKeys = {
  all: ['livenews'] as const,
  list: () => [...liveNewsKeys.all, 'list'] as const,
  detail: (id: string) => [...liveNewsKeys.all, 'detail', id] as const,
};

// ─── Queries ──────────────────────────────────────────────────────────────────

/** All alumni — raw, unfiltered */
export function useLiveNews() {
  return useQuery({
    queryKey: liveNewsKeys.list(),
    queryFn: () => liveNewsService.getAll(),
    staleTime: 1000 * 60 * 5,
  });
}

/** Single alumni member by ID */
// export function useAlumnus(id: string) {
//   return useQuery({
//     queryKey: alumniKeys.detail(id),
//     queryFn: () => alumniService.getById(id),
//     enabled: !!id,
//     staleTime: 1000 * 60 * 5,
//   });
// }


