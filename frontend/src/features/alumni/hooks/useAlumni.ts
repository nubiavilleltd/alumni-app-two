// features/alumni/hooks/useAlumni.ts

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { alumniService, type GetAlumniParams } from '@/features/alumni/services/alumni.service';

// ─── Query Keys ───────────────────────────────────────────────────────────────
export const alumniKeys = {
  all: ['alumni'] as const,
  list: (params?: object) => [...alumniKeys.all, 'list', params] as const,
  detail: (id: string) => [...alumniKeys.all, 'detail', id] as const,
};

// ─── Queries ──────────────────────────────────────────────────────────────────

/** All alumni — raw, unfiltered */
export function useAlumni(params?: GetAlumniParams) {
  return useQuery({
    queryKey: alumniKeys.list(params),
    queryFn: () => alumniService.getAll(params),
    staleTime: 1000 * 60 * 5,
  });
}

/** Single alumni member by ID */
export function useAlumnus(id: string) {
  return useQuery({
    queryKey: alumniKeys.detail(id),
    queryFn: () => alumniService.getById(id),
    enabled: !!id,
    staleTime: 1000 * 60 * 5,
  });
}

/** Featured alumni for homepage */
export function useFeaturedAlumni(count = 3) {
  return useQuery({
    queryKey: alumniKeys.list(),
    queryFn: () => alumniService.getAll(),
    staleTime: 1000 * 60 * 5,
    select: (data) => data.slice(0, count),
  });
}

/** Alumni filtered by graduation year */
export function useAlumniByYear(year: string) {
  return useQuery({
    queryKey: alumniKeys.list({ year }),
    queryFn: () => alumniService.getAll({ year }),
    enabled: !!year,
    staleTime: 1000 * 60 * 5,
  });
}

// ─── Mutations ────────────────────────────────────────────────────────────────

/** Update alumni profile */
export function useUpdateAlumnus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: FormData }) =>
      alumniService.update(id, payload),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: alumniKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: alumniKeys.all });
    },
  });
}
