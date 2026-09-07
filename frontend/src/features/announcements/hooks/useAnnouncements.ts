import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { announcementService } from '@/features/announcements/services/announcement.service';
import { toast } from '@/shared/components/ui/Toast';
import type {
  AnnouncementMutationInput,
  GetAnnouncementsParams,
} from '@/features/announcements/types/announcement.types';

// ─── Query Keys ───────────────────────────────────────────────────────────────
export const announcementKeys = {
  all: ['announcements'] as const,
  list: (params?: object) => [...announcementKeys.all, 'list', params] as const,
  detail: (slug: string) => [...announcementKeys.all, 'detail', slug] as const,
};

export const publicAnnouncementKeys = {
  all: ['public-announcement-feed'] as const,
  list: (params?: object) => [...publicAnnouncementKeys.all, 'list', params] as const,
};

// ─── Queries ──────────────────────────────────────────────────────────────────

/** All announcements */
export function useAnnouncements(params?: GetAnnouncementsParams) {
  return useQuery({
    queryKey: announcementKeys.list(params),
    queryFn: () => announcementService.getAll(params),
    staleTime: 1000 * 60 * 5,
  });
}

/** Public feed: real announcements plus events explicitly marked for display. */
export function usePublicAnnouncements(params?: GetAnnouncementsParams) {
  return useQuery({
    queryKey: publicAnnouncementKeys.list(params),
    queryFn: () => announcementService.getPublicFeed(params),
    staleTime: 1000 * 60 * 5,
  });
}

/** Single announcement by slug */
export function useAnnouncement(slug: string) {
  return useQuery({
    queryKey: announcementKeys.detail(slug),
    queryFn: () => announcementService.getBySlug(slug),
    enabled: !!slug,
    staleTime: 1000 * 60 * 5,
  });
}

/** Latest N announcements for homepage — sorted most recent first */
export function useLatestAnnouncements(count = 5) {
  return useQuery({
    queryKey: publicAnnouncementKeys.list(),
    queryFn: () => announcementService.getPublicFeed(),
    staleTime: 1000 * 60 * 5,
    select: (data) =>
      [...data]
        .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
        .slice(0, count),
  });
}

export function useCreateAnnouncement() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: AnnouncementMutationInput) => announcementService.create(input),
    onSuccess: () => {
      toast.success('Announcement created successfully');
      queryClient.invalidateQueries({ queryKey: announcementKeys.all });
    },
    onError: (error: any) => {
      toast.fromError(error);
    },
  });
}

export function useUpdateAnnouncement() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, input }: { id: string; input: Partial<AnnouncementMutationInput> }) =>
      announcementService.update(id, input),
    onSuccess: () => {
      toast.success('Announcement updated successfully');
      queryClient.invalidateQueries({ queryKey: announcementKeys.all });
    },
    onError: (error: any) => {
      toast.fromError(error);
    },
  });
}

export function useDeleteAnnouncement() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => announcementService.delete(id),
    onSuccess: () => {
      toast.success('Announcement deleted successfully');
      queryClient.invalidateQueries({ queryKey: announcementKeys.all });
    },
    onError: (error: any) => {
      toast.fromError(error);
    },
  });
}

export function useBirthdayAnnouncements() {
  return useQuery({
    queryKey: ['birthdays'],
    queryFn: () => announcementService.getBirthdays(),
    staleTime: 1000 * 60 * 5,
  });
}
