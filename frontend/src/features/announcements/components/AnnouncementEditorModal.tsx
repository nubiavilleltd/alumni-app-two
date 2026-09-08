import { Icon } from '@iconify/react';
import { useEffect, useState } from 'react';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import { Modal } from '@/shared/components/ui/Modal';
import {
  useCreateAnnouncement,
  useUpdateAnnouncement,
} from '@/features/announcements/hooks/useAnnouncements';
import type {
  AnnouncementMutationInput,
  AnnouncementType,
  NewsItem,
} from '@/features/announcements/types/announcement.types';

type EditorState = {
  title: string;
  content: string;
  type: AnnouncementType;
  year: string;
  startsAt: string;
  endsAt: string;
};

const announcementTypeOptions = [{ label: 'Info', value: 'info' }] as const;

const legacyAnnouncementTypeOptions = [
  ...announcementTypeOptions,
  { label: 'Event', value: 'event' },
] as const;

const ANNOUNCEMENT_FALLBACK_IMAGE = '/news-1.png';
const ANNOUNCEMENT_DATE_RANGE_ERROR = 'End date cannot be before start date.';

function canReuseAnnouncementImage(preview?: string) {
  if (!preview) return false;
  if (preview === ANNOUNCEMENT_FALLBACK_IMAGE) return false;
  return !preview.startsWith('blob:') && !preview.startsWith('data:');
}

async function imageUrlToFile(imageUrl: string) {
  const response = await fetch(imageUrl);
  if (!response.ok) {
    throw new Error('Unable to prepare the cover image for upload. Please select it again.');
  }

  const blob = await response.blob();
  const extension = blob.type.split('/')[1] || 'jpg';
  const pathname = imageUrl.split('?')[0]?.split('#')[0] ?? '';
  const filename =
    pathname.split('/').pop()?.trim() ||
    `announcement-cover.${extension.replace(/[^a-z0-9]/gi, '')}`;

  return new File([blob], filename, {
    type: blob.type || 'image/jpeg',
    lastModified: Date.now(),
  });
}

function toInputDate(value?: string) {
  if (!value) return '';
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  if (value.includes('T')) return value.slice(0, 10);
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(value)) {
    return value.slice(0, 10);
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '';

  const year = parsed.getFullYear();
  const month = String(parsed.getMonth() + 1).padStart(2, '0');
  const day = String(parsed.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function toBackendDate(value: string) {
  if (!value.trim()) return undefined;
  return value.trim();
}

function getInitialEditorState(item?: NewsItem) {
  return {
    title: item?.title ?? '',
    content: item?.content ?? item?.excerpt ?? '',
    type: item?.type ?? 'info',
    year: item?.year ? String(item.year) : '',
    startsAt: toInputDate(item?.startsAt),
    endsAt: toInputDate(item?.endsAt),
  };
}

export function AnnouncementEditorModal({
  announcement,
  isOpen,
  onClose,
  onSubmitted,
}: {
  announcement?: NewsItem | null;
  isOpen: boolean;
  onClose: () => void;
  onSubmitted?: (announcement: NewsItem) => void;
}) {
  const createAnnouncement = useCreateAnnouncement();
  const updateAnnouncement = useUpdateAnnouncement();

  const [form, setForm] = useState<EditorState>(getInitialEditorState(announcement ?? undefined));
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreviews, setImagePreviews] = useState<string[]>([]);
  const [formError, setFormError] = useState('');

  const isEditMode = Boolean(announcement);
  const isSubmitting = createAnnouncement.isPending || updateAnnouncement.isPending;

  useEffect(() => {
    if (!isOpen) return;

    setForm(getInitialEditorState(announcement ?? undefined));
    setImageFile(null);
    setImagePreviews(announcement?.image ? [announcement.image] : []);
    setFormError('');
  }, [announcement, isOpen]);

  const handleFieldChange = <K extends keyof EditorState>(field: K, value: EditorState[K]) => {
    const next = { ...form, [field]: value };
    setForm(next);

    if (next.startsAt && next.endsAt && next.endsAt < next.startsAt) {
      setFormError(ANNOUNCEMENT_DATE_RANGE_ERROR);
    } else if (formError === ANNOUNCEMENT_DATE_RANGE_ERROR) {
      setFormError('');
    }
  };

  const handleImageChange = (files: File[], previews: string[]) => {
    setImageFile(files[0] ?? null);
    setImagePreviews(previews);
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');

    if (!form.title.trim()) {
      setFormError('Title is required.');
      return;
    }

    if (!form.content.trim()) {
      setFormError('Content is required.');
      return;
    }

    if (form.startsAt && form.endsAt && form.endsAt < form.startsAt) {
      setFormError(ANNOUNCEMENT_DATE_RANGE_ERROR);
      return;
    }

    let submitImage = imageFile;

    if (!submitImage && canReuseAnnouncementImage(imagePreviews[0])) {
      try {
        submitImage = await imageUrlToFile(imagePreviews[0]);
      } catch (error: any) {
        setFormError(error.message ?? 'Please select a cover image before saving.');
        return;
      }
    }

    if (!submitImage) {
      setFormError('Cover image is required.');
      return;
    }

    const payload: AnnouncementMutationInput = {
      title: form.title.trim(),
      content: form.content.trim(),
      type: form.type,
      year: form.year.trim() || undefined,
      startsAt: toBackendDate(form.startsAt),
      endsAt: toBackendDate(form.endsAt),
      image: submitImage,
    };

    try {
      const savedAnnouncement =
        isEditMode && announcement
          ? await updateAnnouncement.mutateAsync({
              id: String(announcement.id),
              input: payload,
            })
          : await createAnnouncement.mutateAsync(payload);

      onSubmitted?.(savedAnnouncement);
      onClose();
    } catch (error: any) {
      setFormError(error.message ?? 'Unable to save this announcement.');
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => {
        if (!isSubmitting) onClose();
      }}
      title={isEditMode ? 'Edit Announcement' : 'Create Announcement'}
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        <FormInput
          label="Title"
          value={form.title}
          onChange={(event) => handleFieldChange('title', event.target.value)}
          placeholder="Welcome to Alumni 2026"
          required
        />

        <TextareaInput
          label="Content"
          value={form.content}
          onChange={(event) => handleFieldChange('content', event.target.value)}
          placeholder="Write the full announcement here..."
          rows={6}
          required
        />

        <div className="grid gap-4 md:grid-cols-2">
          <SelectInput
            label="Type"
            options={
              announcement?.type === 'event'
                ? legacyAnnouncementTypeOptions
                : announcementTypeOptions
            }
            value={form.type}
            onChange={(event) => handleFieldChange('type', event.target.value as AnnouncementType)}
          />

          <FormInput
            label="Year"
            value={form.year}
            onChange={(event) => handleFieldChange('year', event.target.value)}
            placeholder="2026"
            type="number"
            min="1900"
          />
        </div>

        <div className="rounded-2xl bg-accent-50 px-4 py-3 text-sm text-accent-600">
          Leave year blank if the announcement should be visible to everyone.
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <DatePicker
            label="Starts At"
            value={form.startsAt}
            max={form.endsAt || undefined}
            onValueChange={(value) => handleFieldChange('startsAt', value)}
          />

          <DatePicker
            label="Ends At"
            value={form.endsAt}
            min={form.startsAt || undefined}
            onValueChange={(value) => handleFieldChange('endsAt', value)}
          />
        </div>

        <ImageUpload
          label="Cover Image"
          previews={imagePreviews}
          onChange={handleImageChange}
          multiple={false}
          hint="PNG, JPG, WEBP or GIF up to 2 MB"
        />

        {formError && (
          <div className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{formError}</div>
        )}

        <div className="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="px-4 py-2 text-sm font-medium text-accent-700 hover:text-accent-900 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50"
          >
            {isSubmitting && <Icon icon="mdi:loading" className="h-4 w-4 animate-spin" />}
            {isEditMode ? 'Save changes' : 'Publish announcement'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
