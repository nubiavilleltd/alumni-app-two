// features/projects/hooks/useProjects.ts

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from '@/shared/components/ui/Toast';
import { publicAnnouncementKeys } from '@/features/announcements/hooks/useAnnouncements';
import { projectsService } from '../services/projects.service';
import type {
  CreateProjectFormData,
  DonatePayload,
  UpdateProjectFormData,
} from '../types/project.types';

// ─── Query keys ───────────────────────────────────────────────────────────────

export const projectKeys = {
  all: ['projects'] as const,
  list: () => [...projectKeys.all, 'list'] as const,
  detail: (id: string) => [...projectKeys.all, 'detail', id] as const,
};

// ─── Queries ──────────────────────────────────────────────────────────────────

/** All projects */
export function useProjects() {
  return useQuery({
    queryKey: projectKeys.list(),
    queryFn: () => projectsService.getAll(),
    staleTime: 1000 * 60 * 10,
  });
}

/** Single project by id */
export function useProject(id: string) {
  return useQuery({
    queryKey: projectKeys.detail(id),
    queryFn: () => projectsService.getById(id),
    enabled: !!id,
    staleTime: 1000 * 60 * 10,
  });
}

// ─── Admin mutations ──────────────────────────────────────────────────────────

/** Create a new project */
export function useCreateProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (formData: CreateProjectFormData) => projectsService.create(formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.all });
      queryClient.invalidateQueries({ queryKey: publicAnnouncementKeys.all });
      toast.success('Project created successfully.');
    },
    onError: (error: any) => toast.fromError(error),
  });
}

/** Update an existing project */
export function useUpdateProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, formData }: { id: string; formData: UpdateProjectFormData }) =>
      projectsService.update(id, formData),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: projectKeys.list() });
      queryClient.invalidateQueries({ queryKey: publicAnnouncementKeys.all });
      toast.success('Project updated successfully.');
    },
    onError: (error: any) => toast.fromError(error),
  });
}

/** Delete a project */
export function useDeleteProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => projectsService.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.all });
      queryClient.invalidateQueries({ queryKey: publicAnnouncementKeys.all });
      toast.success('Project deleted.');
    },
    onError: (error: any) => toast.fromError(error),
  });
}

/** Donate to a project */
export function useDonateToProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: DonatePayload }) =>
      projectsService.donate(id, payload),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(id) });
      toast.success('Thank you for your donation!');
    },
    onError: (error: any) => toast.fromError(error),
  });
}
