// // features/marketplace/components/PostYourBusinessModal.tsx

import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  Check,
  ChevronDown,
  ChevronUp,
  CircleAlert,
  Search,
  Upload,
  X,
  Camera,
} from 'lucide-react';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { ImageUpload } from '@/shared/components/ui/ImageUpload';
import { Button } from '@/shared/components/ui/Button';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { PhoneNumberInput } from '@/shared/components/ui/input/PhoneNumberInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { toTitleCase } from '@/shared/utils/textHelpers';
import {
  useCreateListing,
  useUpdateListing,
  useMarketplaceCategories,
} from '../hooks/useMarketplace';
import { useImageManager } from '@/shared/hooks/useImageManager';
import type { Business, CreateListingFormData } from '../types/marketplace.types';
import {
  MAX_INSTAGRAM_HASHTAGS,
  normalizeLegacyHashtags,
  parseHashtags,
  serializeHashtags,
  validateHashtags,
} from '../utils/hashtags';
import {
  formatOptionalNigerianPhoneNumber,
  NIGERIAN_PHONE_PLACEHOLDER,
  parseStoredNigerianPhoneNumber,
  validateNigerianPhoneNumber,
} from '@/shared/utils/nigerianPhoneNumber';

// ─── Zod Schema ────────────────────────────────────────────────────────────────

const postBusinessSchema = z
  .object({
    name: z
      .string()
      .min(1, 'Business name is required')
      .min(2, 'Business name must be at least 2 characters')
      .max(100, 'Business name is too long'),

    category: z.string().min(1, 'Please select a category'),

    description: z
      .string()
      .min(1, 'Description is required')
      .min(20, 'Please provide at least 20 characters')
      .max(5000, 'Description is too long'),

    address: z.string().min(1, 'Address is required').min(2, 'Please provide a valid address'),
    city: z.string().min(1, 'City is required').min(2, 'Please provide a valid city'),
    state: z.string().min(1, 'State is required').min(2, 'Please provide a valid state'),

    phone: z.string().trim().min(1, 'Phone number is required'),
    website: z.string().optional(),
    messagePrompt: z.string().max(750, 'Message prompt must be 750 characters or fewer').optional(),

    whatsapp: z.string().trim().optional(),

    socials: z
      .object({
        instagram: z.string().optional(),
        // instagramHashtag: z
        //   .string()
        //   .max(50, 'Hashtag is too long. Max characters allowed: 50')
        //   .regex(/^[A-Za-z0-9_]*$/, 'Only letters, numbers, and underscores allowed')
        //   .optional(),
        instagramHashtag: z.string().optional(),
        facebook: z.string().optional(),
        linkedin: z.string().optional(),
        x: z.string().optional(),
        tiktok: z.string().optional(),
      })
      .optional(),
  })
  .superRefine((data, ctx) => {
    const phoneError = validateNigerianPhoneNumber(data.phone);
    if (phoneError) {
      ctx.addIssue({ code: 'custom', path: ['phone'], message: phoneError });
    }
    if (data.whatsapp) {
      const whatsappError = validateNigerianPhoneNumber(data.whatsapp);
      if (whatsappError) {
        ctx.addIssue({ code: 'custom', path: ['whatsapp'], message: whatsappError });
      }
    }
    const hashtagError = validateHashtags(data.socials?.instagramHashtag);
    if (hashtagError) {
      ctx.addIssue({
        code: 'custom',
        path: ['socials', 'instagramHashtag'],
        message: hashtagError,
      });
    }
  });

type PostBusinessFormValues = z.infer<typeof postBusinessSchema>;

// ─── Types ────────────────────────────────────────────────────────────────────

interface PostBusinessModalProps {
  isOpen: boolean;
  onClose: () => void;
  editData?: Business | null;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

// function normalizeHashtag(value?: string): string | undefined {
//   if (!value) return undefined;
//   const cleaned = value.trim().replace(/^#+/, '').replace(/\s+/g, '');
//   return cleaned || undefined;
// }

function toFormState(data: Business | null | undefined): PostBusinessFormValues {
  if (!data) {
    return {
      name: '',
      category: '',
      description: '',
      address: '',
      city: '',
      state: '',
      phone: '',
      website: '',
      whatsapp: '',
      socials: {
        instagram: '',
        instagramHashtag: '',
        facebook: '',
        linkedin: '',
        x: '',
        tiktok: '',
      },
      messagePrompt: '',
    };
  }

  return {
    name: data.name,
    category: data.category,
    description: data.description,
    address: data.address,
    city: data.city,
    state: data.state,
    phone: parseStoredNigerianPhoneNumber(data.phone),
    website: data.website ?? '',
    whatsapp: data.whatsapp ? parseStoredNigerianPhoneNumber(data.whatsapp) : '',
    socials: {
      instagram: data.socials?.instagram ?? '',
      // instagramHashtag: data.socials?.instagramHashtag ?? '',
      instagramHashtag: normalizeLegacyHashtags(data.socials?.instagramHashtag) ?? '',
      facebook: data.socials?.facebook ?? '',
      linkedin: data.socials?.linkedin ?? '',
      x: data.socials?.x ?? '',
      tiktok: data.socials?.tiktok ?? '',
    },
    messagePrompt: data.messagePrompt ?? '',
  };
}

function toCreateListingFormData(
  form: PostBusinessFormValues,
  images: File[],
): CreateListingFormData {
  return {
    name: form.name,
    category: form.category,
    description: form.description,
    address: form.address,
    city: form.city,
    state: form.state,
    phone: formatOptionalNigerianPhoneNumber(form.phone),
    website: form.website || undefined,
    whatsapp: form.whatsapp ? formatOptionalNigerianPhoneNumber(form.whatsapp) : undefined,
    socials:
      form.socials && Object.values(form.socials).some((v) => v?.trim())
        ? {
            instagram: form.socials.instagram?.trim() || undefined,
            // instagramHashtag: normalizeHashtag(form.socials.instagramHashtag),
            instagramHashtag: serializeHashtags(parseHashtags(form.socials.instagramHashtag)),
            // instagramHashtag: form.socials.instagramHashtag,
            facebook: form.socials.facebook?.trim() || undefined,
            linkedin: form.socials.linkedin?.trim() || undefined,
            x: form.socials.x?.trim() || undefined,
            tiktok: form.socials.tiktok?.trim() || undefined,
          }
        : undefined,
    messagePrompt: form.messagePrompt?.trim() || undefined,
    images,
  };
}

// ─── Component ────────────────────────────────────────────────────────────────

export function PostBusinessModal({ isOpen, onClose, editData }: PostBusinessModalProps) {
  const isEditing = !!editData;

  const createMutation = useCreateListing();
  const updateMutation = useUpdateListing();

  const {
    allPreviews,
    newFiles,
    removedImages,
    handleImages,
    reset: resetImages,
  } = useImageManager();

  const { data: categoriesList = [] } = useMarketplaceCategories();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
    setError: setFormError,
  } = useForm<PostBusinessFormValues>({
    resolver: zodResolver(postBusinessSchema),
    defaultValues: toFormState(editData),
    mode: 'onChange',
  });

  // Re-sync when editData changes
  useEffect(() => {
    reset(toFormState(editData));
    resetImages(editData?.images ?? []);
  }, [editData, reset, resetImages]);

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  // Reset on close
  useEffect(() => {
    if (!isOpen) {
      reset({
        name: '',
        category: '',
        description: '',
        address: '',
        city: '',
        state: '',
        phone: '',
        website: '',
        whatsapp: '',
        socials: {
          instagram: '',
          instagramHashtag: '',
          facebook: '',
          linkedin: '',
          x: '',
          tiktok: '',
        },
        messagePrompt: '',
      });
      resetImages();
      setFormError('root', { message: '' });
    }
  }, [isOpen, reset, resetImages, setFormError]);

  const onSubmit = async (formData: PostBusinessFormValues) => {
    const listingFormData = toCreateListingFormData(formData, newFiles);

    if (isEditing) {
      if (!editData?.businessId) {
        setFormError('root', { message: 'Missing business ID' });
        return;
      }

      updateMutation.mutate(
        {
          id: editData.businessId,
          formData: {
            ...listingFormData,
            removeImages: removedImages.length ? removedImages : undefined,
            imageAction: newFiles.length > 0 ? 'add' : undefined,
          },
        },
        {
          onSuccess: onClose,
          onError: (err: any) =>
            setFormError('root', {
              message: err?.message ?? 'Failed to update. Please try again.',
            }),
        },
      );
    } else {
      createMutation.mutate(listingFormData, {
        onSuccess: onClose,
        onError: (err: any) =>
          setFormError('root', { message: err?.message ?? 'Failed to create. Please try again.' }),
      });
    }
  };

  const isLoading = createMutation.isPending || updateMutation.isPending || isSubmitting;
  const categoryOptions = categoriesList.map((cat) => ({ label: toTitleCase(cat), value: cat }));
  const categoryValue = watch('category') ?? '';
  const hashtagCount = parseHashtags(watch('socials.instagramHashtag')).length;

  if (!isOpen) return null;

  const fieldLabelClassName = 'font-medium text-gray-500';
  const fieldControlClassName =
    '!border-transparent !bg-[#fbf8f3] !shadow-none focus-within:!border-primary-100 focus-within:!ring-2 focus-within:!ring-primary-100';
  const fieldInputClassName = 'placeholder:text-gray-400';
  const textareaClassName =
    '!border-transparent !bg-[#fbf8f3] !shadow-none placeholder:text-gray-400 focus:!border-primary-100 focus:!ring-2 focus:!ring-primary-100';
  const compactUploadLabelClassName = 'font-medium text-gray-500';

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto bg-black/35 px-4 py-6 backdrop-blur-[1.5px]"
      onClick={onClose}
    >
      <div className="flex min-h-full items-start justify-center lg:items-center">
        <section
          className="relative my-auto w-full max-w-[62rem] overflow-y-auto rounded-[2rem] bg-white shadow-[0_28px_72px_rgba(15,23,42,0.16)] max-h-[82vh]"
          role="dialog"
          aria-modal="true"
          aria-labelledby="post-business-title"
          onClick={(event) => event.stopPropagation()}
        >
          <button
            type="button"
            onClick={onClose}
            className="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-primary-500 transition-colors hover:bg-primary-50 md:right-6 md:top-6"
            aria-label="Close modal"
          >
            <X className="h-7 w-7" />
          </button>

          <div className="px-5 pb-6 pt-9 sm:px-8 md:px-10 md:pb-8 md:pt-10">
            <h2 id="post-business-title" className="sr-only">
              {isEditing ? 'Edit Your Business' : 'Post Your Business'}
            </h2>

            <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-5">
              {errors.root?.message && (
                <div className="rounded-[1.4rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                  {errors.root.message}
                </div>
              )}

              <div className="grid gap-x-6 gap-y-5 md:grid-cols-2 md:gap-x-8 md:gap-y-6">
                <FormInput
                  label="Business Name"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="name"
                  required
                  placeholder="Enter your business name"
                  error={errors.name?.message}
                  {...register('name')}
                />

                <SelectInput
                  label="Category"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  id="category"
                  required
                  placeholder="Select a category"
                  options={categoryOptions}
                  value={categoryValue}
                  error={errors.category?.message}
                  chevronDownIcon={ChevronDown}
                  chevronUpIcon={ChevronUp}
                  searchIcon={Search}
                  selectedIcon={Check}
                  errorIcon={CircleAlert}
                  {...register('category')}
                />

                <TextareaInput
                  label="Description"
                  labelClassName={fieldLabelClassName}
                  textareaClassName={textareaClassName}
                  className="md:col-span-2"
                  id="description"
                  required
                  rows={5}
                  placeholder="Write a short description of your business"
                  error={errors.description?.message}
                  {...register('description')}
                />

                <PhoneNumberInput
                  label="Phone Number"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="phone"
                  required
                  placeholder={NIGERIAN_PHONE_PLACEHOLDER}
                  error={errors.phone?.message}
                  {...register('phone')}
                />

                <FormInput
                  label="Address"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="address"
                  required
                  placeholder="Enter the address of your business"
                  error={errors.address?.message}
                  {...register('address')}
                />

                <FormInput
                  label="City"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="city"
                  required
                  placeholder="Enter the city"
                  error={errors.city?.message}
                  {...register('city')}
                />

                <FormInput
                  label="State"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="state"
                  required
                  placeholder="Enter the state"
                  error={errors.state?.message}
                  {...register('state')}
                />

                <FormInput
                  label="Website"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="website"
                  type="url"
                  placeholder="Enter the web address of your business"
                  error={errors.website?.message}
                  {...register('website')}
                />

                <PhoneNumberInput
                  label="WhatsApp Number"
                  labelClassName={fieldLabelClassName}
                  controlClassName={fieldControlClassName}
                  inputClassName={fieldInputClassName}
                  id="whatsapp"
                  placeholder={NIGERIAN_PHONE_PLACEHOLDER}
                  error={errors.whatsapp?.message}
                  {...register('whatsapp')}
                />

                <div className="md:col-span-2">
                  <p className="font-medium text-gray-500 mb-3">Socials</p>
                  <div className="grid gap-x-6 gap-y-5 md:grid-cols-2 md:gap-x-8 md:gap-y-6">
                    <FormInput
                      label="Instagram"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="instagram"
                      type="url"
                      placeholder="https://instagram.com/yourbusiness"
                      error={errors.socials?.instagram?.message}
                      {...register('socials.instagram')}
                    />

                    {/* <FormInput
                      label="Instagram Hashtag"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="instagramHashtag"
                      type="text"
                      placeholder="yourbusiness"
                      error={errors.socials?.instagramHashtag?.message}
                      {...register('socials.instagramHashtag')}
                    /> */}
                    <FormInput
                      label="Instagram Hashtag(s)"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="instagramHashtag"
                      type="text"
                      placeholder="urbanroots, ecofriendly, gardening"
                      hint={`Separate with commas. ${hashtagCount}/${MAX_INSTAGRAM_HASHTAGS} used.`}
                      error={errors.socials?.instagramHashtag?.message}
                      {...register('socials.instagramHashtag')}
                    />
                    <FormInput
                      label="Facebook"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="facebook"
                      type="url"
                      placeholder="https://facebook.com/yourbusiness"
                      error={errors.socials?.facebook?.message}
                      {...register('socials.facebook')}
                    />
                    <FormInput
                      label="LinkedIn"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="linkedin"
                      type="url"
                      placeholder="https://linkedin.com/company/yourbusiness"
                      error={errors.socials?.linkedin?.message}
                      {...register('socials.linkedin')}
                    />
                    <FormInput
                      label="X"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="x"
                      type="url"
                      placeholder="https://x.com/yourbusiness"
                      error={errors.socials?.x?.message}
                      {...register('socials.x')}
                    />
                    <FormInput
                      label="TikTok"
                      labelClassName={fieldLabelClassName}
                      controlClassName={fieldControlClassName}
                      inputClassName={fieldInputClassName}
                      id="tiktok"
                      type="url"
                      placeholder="https://tiktok.com/@yourbusiness"
                      error={errors.socials?.tiktok?.message}
                      {...register('socials.tiktok')}
                    />
                  </div>
                </div>

                <TextareaInput
                  label="Message Prompt"
                  labelClassName={fieldLabelClassName}
                  textareaClassName={textareaClassName}
                  className="md:col-span-2"
                  id="messagePrompt"
                  rows={4}
                  placeholder="Enter the message you want buyers to see when they contact you"
                  hint="Optional. This suggested message will prefill when someone clicks Send Message."
                  error={errors.messagePrompt?.message}
                  {...register('messagePrompt')}
                />
              </div>

              <ImageUpload
                label={isEditing ? 'Business Images' : 'Upload Images'}
                labelClassName={compactUploadLabelClassName}
                dropzoneClassName="py-5"
                hint={
                  isEditing
                    ? 'Existing images shown below. Upload new ones to add more.'
                    : 'PNG or JPG (max 800×400px)'
                }
                previews={allPreviews}
                onChange={handleImages}
                idleIcon={Camera}
                activeIcon={Upload}
                removeIcon={X}
                errorIcon={CircleAlert}
              />

              <div className="flex justify-end">
                <Button
                  type="submit"
                  size="lg"
                  loading={isLoading}
                  className="min-w-[11rem] px-8 shadow-none"
                >
                  {isEditing ? 'Update Business' : 'Post Business'}
                </Button>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  );
}
