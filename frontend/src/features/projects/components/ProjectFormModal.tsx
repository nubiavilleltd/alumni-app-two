// features/projects/components/ProjectFormModal.tsx

import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Icon } from '@iconify/react';
import { Modal } from '@/shared/components/ui/Modal';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import Button from '@/shared/components/ui/Button';
import { useCreateProject, useUpdateProject } from '../hooks/useProjects';
import { useImageManager } from '@/shared/hooks/useImageManager';
import type { Project } from '../types/project.types';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';

// ─── Validation schema ────────────────────────────────────────────────────────

const projectFormSchema = z
  .object({
    title: z.string().trim().min(2, 'Title must be at least 2 characters'),
    description: z.string().trim().min(10, 'Description must be at least 10 characters'),
    targetAmount: z
      .number({ error: 'Please enter a valid amount' })
      .min(0, 'Amount must be 0 or more')
      .optional(),
    amountRaised: z
      .number({ error: 'Please enter a valid amount' })
      .min(0, 'Amount must be 0 or more')
      .default(0),
    status: z.enum(['ongoing', 'completed']).default('ongoing'),
    conductedBy: z.string().trim().min(1, 'Conducted By is required'),
    location: z.string().trim().min(5, 'Location is required'),
    startDate: z.string().min(1, 'Start date is required'),
    endDate: z.string().optional(),
    sortOrder: z.number({ error: 'Please enter a whole number' }).int().min(0).optional(),
    isFeatured: z.boolean().optional(),
    show_in_announcements: z.boolean().default(false),
  })
  .superRefine((data, ctx) => {
    if (data.startDate) {
      const selectedDate = new Date(data.startDate);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (selectedDate < today) {
        ctx.addIssue({
          code: 'custom',
          path: ['startDate'],
          message: 'Start date cannot be in the past',
        });
      }
    }

    if (data.startDate && data.endDate && data.endDate < data.startDate) {
      ctx.addIssue({
        code: 'custom',
        path: ['endDate'],
        message: 'End date cannot be before start date',
      });
    }
  });

type ProjectFormValues = z.infer<typeof projectFormSchema>;

// ─── Main component ───────────────────────────────────────────────────────────

interface ProjectFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  editData?: Project | null;
}

export function ProjectFormModal({ isOpen, onClose, editData }: ProjectFormModalProps) {
  const isEditing = !!editData;

  const createMutation = useCreateProject();
  const updateMutation = useUpdateProject();

  const {
    allPreviews,
    newFiles,
    removedImages,
    handleImages,
    reset: resetImages,
  } = useImageManager();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    trigger,
    formState: { errors },
  } = useForm<ProjectFormValues>({
    resolver: zodResolver(projectFormSchema) as any,
    mode: 'onChange',
    defaultValues: {
      title: '',
      description: '',
      status: 'ongoing',
      conductedBy: '',
      location: '',
      startDate: '',
      endDate: '',
      targetAmount: undefined,
      amountRaised: 0,
      sortOrder: undefined,
      isFeatured: false,
      show_in_announcements: false,
    },
  });

  const startDate = watch('startDate');
  const endDate = watch('endDate');

  // Sync form + images when edit data changes
  useEffect(() => {
    if (isOpen && editData) {
      reset({
        title: editData.title,
        description: editData.description,
        status: editData.status,
        conductedBy: editData.conductedBy,
        location: editData.location,
        startDate: editData.startDate,
        endDate: editData.endDate,
        targetAmount: editData.targetAmount,
        amountRaised: editData.amountRaised,
        sortOrder: editData.sortOrder,
        isFeatured: Boolean(editData.isFeatured),
        show_in_announcements: Boolean(editData.showInAnnouncements),
      });
      resetImages(editData.images ?? []);
    } else {
      reset({
        title: '',
        description: '',
        status: 'ongoing',
        amountRaised: 0,
        isFeatured: false,
        show_in_announcements: false,
      });
      resetImages();
    }
  }, [isOpen, editData, reset, resetImages]);

  useEffect(() => {
    if (!startDate && !endDate) return;
    void trigger(['startDate', 'endDate']);
  }, [endDate, startDate, trigger]);

  // ── Submit ──────────────────────────────────────────────────────────────────

  const onSubmit = async (values: ProjectFormValues) => {
    if (isEditing && editData) {
      updateMutation.mutate(
        {
          id: editData.id,
          formData: {
            ...values,
            images: newFiles,
            removeImages: removedImages.length ? removedImages : undefined,
            imageAction: newFiles.length > 0 ? 'add' : undefined,
          },
        },
        { onSuccess: onClose },
      );
    } else {
      createMutation.mutate({ ...values, images: newFiles }, { onSuccess: onClose });
    }
  };

  const isLoading = createMutation.isPending || updateMutation.isPending;

  // const statusOptions = [
  //   { label: 'Ongoing', value: 'ongoing' },
  //   { label: 'Completed', value: 'completed' },
  // ];

  const statusOptions: { label: string; value: ProjectFormValues['status'] }[] = [
    { label: 'Ongoing', value: 'ongoing' },
    { label: 'Completed', value: 'completed' },
  ];

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={isEditing ? 'Edit Project' : 'Create Project'}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-5">
        <FormInput
          label="Project Title"
          id="title"
          required
          placeholder="e.g. Computer Donation 2025"
          error={errors.title?.message}
          {...register('title')}
        />

        <TextareaInput
          label="Description"
          id="description"
          required
          rows={4}
          placeholder="Describe the project..."
          error={errors.description?.message}
          {...register('description')}
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {/* <SelectInput
            label="Status"
            id="status"
            required
            options={statusOptions}
            placeholder="Select status"
            error={errors.status?.message}
            {...register('status')}
          /> */}

          <SelectInput
            label="Status"
            id="status"
            required
            options={statusOptions}
            placeholder="Select status"
            error={errors.status?.message}
            value={watch('status')}
            onChange={(e) =>
              setValue('status', e.target.value as ProjectFormValues['status'], {
                shouldValidate: true,
                shouldDirty: true,
              })
            }
          />

          <FormInput
            label="Conducted by"
            id="conductedBy"
            type="text"
            required
            placeholder="Who carried out the project"
            error={errors.conductedBy?.message}
            {...register('conductedBy')}
          />
          {/* <DatePicker
                          label="Event Date"
                          id="event_date"
                          required
                          min={new Date().toISOString().split('T')[0]} // same as before
                          error={errors.startDate?.message}
                          value={watch('startDate')} // controlled
                          onValueChange={(val) => setValue('startDate', val, { shouldValidate: true })}
                        /> */}
          {/* <FormInput
            label="Sort Order"
            id="sortOrder"
            type="number"
            placeholder="e.g. 1"
            hint="Lower = shown first"
            error={errors.sortOrder?.message}
            {...register('sortOrder', { valueAsNumber: true })}
          /> */}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <DatePicker
            label="Start Date"
            id="startDate"
            required
            min={new Date().toISOString().split('T')[0]}
            max={endDate || undefined}
            error={errors.startDate?.message}
            value={startDate}
            onValueChange={(val) =>
              setValue('startDate', val, { shouldValidate: true, shouldDirty: true })
            }
          />

          <DatePicker
            label="End Date (Optional)"
            id="endDate"
            min={startDate || new Date().toISOString().split('T')[0]}
            error={errors.endDate?.message}
            value={endDate}
            onValueChange={(val) =>
              setValue('endDate', val, { shouldValidate: true, shouldDirty: true })
            }
          />
          {/* <FormInput
            label="Sort Order"
            id="sortOrder"
            type="number"
            placeholder="e.g. 1"
            hint="Lower = shown first"
            error={errors.sortOrder?.message}
            {...register('sortOrder', { valueAsNumber: true })}
          /> */}
        </div>

        <div className="grid grid-cols-1">
          <FormInput
            label="Location"
            id="location"
            type="text"
            required
            placeholder="Where the project is sited"
            error={errors.location?.message}
            {...register('location')}
          />
        </div>

        <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-primary-100 bg-primary-50/50 px-4 py-4">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 accent-primary-500"
            {...register('show_in_announcements')}
          />
          <span>
            <span className="block text-sm font-semibold text-gray-800">
              Show this project in announcements
            </span>
            <span className="mt-1 block text-xs leading-5 text-gray-500">
              Keep the project as the single source of truth while also displaying it in the public
              announcements feed.
            </span>
          </span>
        </label>

        {/* <label className="flex items-center gap-3 cursor-pointer">
          <input
            type="checkbox"
            className="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-400"
            {...register('isFeatured')}
          />
          <span className="text-sm font-medium text-gray-700">Featured project</span>
        </label> */}

        <ImageUpload
          label="Images"
          hint="jpg, jpeg, png, svg, gif, webp — max 2 MB each"
          previews={allPreviews}
          onChange={handleImages}
          accept=".jpg,.jpeg,.png,.svg,image/jpeg,image/png,image/svg+xml,image/gif,image/webp"
          multiple
        />

        {isEditing && (removedImages.length > 0 || newFiles.length > 0) && (
          <p className="text-xs text-primary-600 -mt-3">
            {removedImages.length > 0 && `${removedImages.length} image(s) will be removed. `}
            {newFiles.length > 0 && `${newFiles.length} new image(s) will be added.`}
          </p>
        )}

        <div className="flex flex-col sm:flex-row gap-3 pt-2">
          <Button type="submit" className="flex-1" disabled={isLoading}>
            {isLoading ? (
              <span className="flex items-center justify-center gap-2">
                <Icon icon="mdi:loading" className="w-4 h-4 animate-spin" />
                {isEditing ? 'Saving...' : 'Creating...'}
              </span>
            ) : isEditing ? (
              'Save Changes'
            ) : (
              'Create Project'
            )}
          </Button>
          <Button
            variant="outline"
            type="button"
            onClick={onClose}
            disabled={isLoading}
            className="flex-1"
          >
            Cancel
          </Button>
        </div>
      </form>
    </Modal>
  );
}
