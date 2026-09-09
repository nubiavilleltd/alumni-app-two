import { useQuery } from '@tanstack/react-query';
import { fetchLocations } from '@/shared/api/locationApi';
import type { LocationGroup } from '@/shared/types/location.types';

export const locationKeys = {
  all: ['locations'] as const,
};

export function useLocations() {
  return useQuery<readonly LocationGroup[]>({
    queryKey: locationKeys.all,
    queryFn: fetchLocations,
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
  });
}
