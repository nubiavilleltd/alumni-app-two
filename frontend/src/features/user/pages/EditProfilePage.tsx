// features/user/pages/EditProfilePage.tsx
// Route: /user/profile/edit  (ProtectedRoute)
// MODIFIED: City field in Address section changed from free-text Input to a
// Select dropdown backed by useCities() — mirrors the same change on RegisterDetailsPage.

import { useEffect, useRef, useState, type DragEvent as ReactDragEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Icon } from '@iconify/react';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { ROUTES } from '@/shared/constants/routes';
import { USER_ROUTES } from '../routes';
import { useCurrentUser, currentUserKeys } from '@/features/authentication/hooks/useCurrentUser';
import { userService } from '../services/user.service';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { toast } from '@/shared/components/ui/Toast';
import {
  employmentStatusOptions,
  industrySectorOptions,
  occupationOptions,
  yearsOfExperienceOptions,
  houseColorOptions,
} from '@/features/authentication/constants/profileOptions';
import { NIGERIA_STATES } from '@/features/authentication/constants/nigerianStates';
import type { AuthSessionUser } from '@/features/authentication/types/auth.types';
import { useCities } from '@/features/authentication/hooks/useCities';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { PhoneNumberInput } from '@/shared/components/ui/input/PhoneNumberInput';
import { getDateYearsAgo } from '@/shared/utils/dateHelpers';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import {
  formatOptionalNigerianPhoneNumber,
  NIGERIAN_PHONE_PLACEHOLDER,
  normalizeNigerianPhoneNumber,
  parseStoredNigerianPhoneNumber,
  validateNigerianPhoneNumber,
} from '@/shared/utils/nigerianPhoneNumber';
import {
  DEFAULT_IMAGE_UPLOAD_ACCEPT,
  validateFilesAgainstAcceptList,
} from '@/shared/utils/fileValidation';
import { useBreakpoint } from '@/shared/hooks/useBreakpoint';

const breadcrumbItems = [
  { label: 'Home', href: ROUTES.HOME },
  { label: 'Dashboard', href: USER_ROUTES.DASHBOARD },
  { label: 'My Profile', href: USER_ROUTES.PROFILE },
  { label: 'Edit' },
];

// ─── Form state ───────────────────────────────────────────────────────────────

interface FormState {
  firstName: string;
  lastName: string;
  nameInSchool: string;
  nickName: string;
  whatsappPhone: string;
  alternativePhone: string;
  birthDate: string;
  bio: string;
  houseColor: string;
  residentialAddress: string;
  area: string;
  city: string;
  state: string;
  employmentStatus: string;
  occupation: string;
  industrySector: string;
  yearsOfExperience: string;
  company: string;
  position: string;
  isVolunteer: boolean;
  linkedin: string;
  twitter: string;
  instagram: string;
  facebook: string;
  tiktok: string;
}

// function resolveYearsOfExperience(years: number | undefined): string {
//   if (years == null) return '';
//   const match = [...yearsOfExperienceOptions].reverse().find((o) => o.value <= years);
//   return match ? String(match.value) : String(yearsOfExperienceOptions[0].value);
// }

function toFormState(user: AuthSessionUser | null | undefined): FormState {
  return {
    firstName: user?.otherNames ?? '',
    lastName: user?.surname ?? '',
    nameInSchool: user?.nameInSchool ?? '',
    nickName: user?.nickName ?? '',
    whatsappPhone: parseStoredNigerianPhoneNumber(user?.whatsappPhone),
    alternativePhone: parseStoredNigerianPhoneNumber(user?.alternativePhone),
    birthDate: user?.birthDate ?? '',
    bio: user?.bio ?? '',
    houseColor: user?.houseColor ?? '',
    residentialAddress: user?.residentialAddress ?? '',
    area: user?.area ?? '',
    city: user?.city ?? '',
    state: user?.state ?? '',
    employmentStatus: user?.employmentStatus ?? '',
    occupation: user?.occupations?.[0] ?? '',
    industrySector: user?.industrySectors?.[0] ?? '',
    // yearsOfExperience: resolveYearsOfExperience(user?.yearsOfExperience),
    yearsOfExperience: user?.yearsOfExperience ?? '',
    company: user?.company ?? '',
    position: user?.position ?? '',
    isVolunteer: user?.isVolunteer ?? false,
    linkedin: user?.linkedin ?? '',
    twitter: user?.twitter ?? '',
    instagram: user?.instagram ?? '',
    facebook: user?.facebook ?? '',
    tiktok: user?.tiktok ?? '',
  };
}

// ─── Field components ─────────────────────────────────────────────────────────

function Label({ children, required }: { children: React.ReactNode; required?: boolean }) {
  return (
    <label className="block text-sm font-medium text-gray-700 mb-1">
      {children}
      {required && <span className="text-red-500 ml-0.5">*</span>}
    </label>
  );
}

function Input({
  name,
  value,
  onChange,
  placeholder,
  type = 'text',
  inputMode,
  disabled,
}: {
  name: string;
  value: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  placeholder?: string;
  type?: string;
  inputMode?: React.HTMLAttributes<HTMLInputElement>['inputMode'];
  disabled?: boolean;
}) {
  return (
    <input
      type={type}
      name={name}
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      inputMode={inputMode}
      disabled={disabled}
      className="w-full px-4 py-2.5 rounded-3xl border border-gray-200 bg-gray-50 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-400 focus:bg-white transition-all disabled:opacity-60"
    />
  );
}


function SectionCard({
  title,
  children,
  defaultOpen = true,
}: {
  title: string;
  children: React.ReactNode;
  defaultOpen?: boolean;
}) {
  const { isMobile } = useBreakpoint();

  const [open, setOpen] = useState(() => {
    // Desktop/tablet => always open
    if (typeof window !== 'undefined' && window.innerWidth >= 640) {
      return true;
    }

    // Mobile => respect defaultOpen
    return defaultOpen;
  });

  // Ensure desktop/tablet always stay open
  useEffect(() => {
    if (!isMobile) {
      setOpen(true);
    }
  }, [isMobile]);

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-visible">
      <button
        type="button"
        onClick={() => {
          if (isMobile) {
            setOpen((v) => !v);
          }
        }}
        className={`w-full flex items-center justify-between px-5 sm:px-6 py-4 ${
          isMobile ? 'cursor-pointer' : 'cursor-default'
        }`}
      >
        <span className="text-sm font-bold text-gray-900">{title}</span>

        {isMobile && (
          <Icon
            icon={open ? 'mdi:chevron-up' : 'mdi:chevron-down'}
            className="w-4 h-4 text-gray-400"
          />
        )}
      </button>

      {open && <div className="px-5 sm:px-6 pb-6 pt-0">{children}</div>}
    </div>
  );
}

// ─── Photo upload ─────────────────────────────────────────────────────────────

function PhotoUpload({
  currentPhoto,
  preview,
  fullName,
  error,
  onSelectFile,
}: {
  currentPhoto?: string;
  preview: string | null;
  fullName: string;
  error?: string | null;
  onSelectFile: (file: File) => void;
}) {
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const [isDragActive, setIsDragActive] = useState(false);
  const initials = fullName
    .split(' ')
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase();
  const displaySrc = preview ?? currentPhoto;

  const handleNativeChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (file) {
      onSelectFile(file);
    }
  };

  const isDraggingFiles = (event: ReactDragEvent<HTMLElement>) =>
    Array.from(event.dataTransfer.types).includes('Files');

  const handleDragEnter = (event: ReactDragEvent<HTMLDivElement>) => {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    setIsDragActive(true);
  };

  const handleDragOver = (event: ReactDragEvent<HTMLDivElement>) => {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
    if (!isDragActive) {
      setIsDragActive(true);
    }
  };

  const handleDragLeave = (event: ReactDragEvent<HTMLDivElement>) => {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof Node && event.currentTarget.contains(nextTarget)) {
      return;
    }

    setIsDragActive(false);
  };

  const handleDrop = (event: ReactDragEvent<HTMLDivElement>) => {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    setIsDragActive(false);
    const file = event.dataTransfer.files?.[0];
    if (file) {
      onSelectFile(file);
    }
  };

  return (
    <div className="mb-6 flex items-start gap-5">
      <div
        className={`relative flex-shrink-0 rounded-full transition-shadow ${
          isDragActive ? 'ring-4 ring-primary-100' : ''
        }`}
        onClick={() => fileInputRef.current?.click()}
        onDragEnter={handleDragEnter}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInputRef.current?.click();
          }
        }}
        role="button"
        tabIndex={0}
      >
        <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-white bg-primary-50 shadow-md">
          {displaySrc ? (
            <img src={displaySrc} alt={fullName} className="w-full h-full object-cover" />
          ) : (
            <span className="text-2xl font-bold text-primary-400">{initials || '?'}</span>
          )}
        </div>
        <span className="absolute bottom-0 right-0 flex h-7 w-7 items-center justify-center rounded-full bg-primary-500 text-white shadow transition-colors hover:bg-primary-600">
          <Icon icon="mdi:pencil" className="w-3.5 h-3.5" />
        </span>
        {isDragActive ? (
          <div className="absolute inset-0 flex items-center justify-center rounded-full bg-primary-500/15 text-[10px] font-semibold text-primary-700">
            Drop image
          </div>
        ) : null}
        <input
          ref={fileInputRef}
          id="photo-upload"
          type="file"
          accept={DEFAULT_IMAGE_UPLOAD_ACCEPT}
          onChange={handleNativeChange}
          className="sr-only"
        />
      </div>
      {error ? (
        <p className="pt-6 text-xs text-red-500">{error}</p>
      ) : (
        <p className="pt-6 text-xs text-gray-400">Click or drag a photo here</p>
      )}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const stateOptions = NIGERIA_STATES.map((s) => ({ label: s, value: s }));
const employmentOptions = employmentStatusOptions.map((o) => ({ label: o.label, value: o.value }));
const occupationOpts = occupationOptions.map((o) => ({ label: o.label, value: o.value }));
const industrySectorOpts = industrySectorOptions.map((o) => ({ label: o.label, value: o.value }));
const yearsExpOpts = yearsOfExperienceOptions.map((o) => ({
  label: o.label,
  value: String(o.value),
}));
const houseColorOpts = houseColorOptions.map((o) => ({ label: o.label, value: o.value }));
export default function EditProfilePage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const updateUser = useIdentityStore((state) => state.updateUser);
  const { data: currentUser, isLoading } = useCurrentUser();

  // ── Cities from API ────────────────────────────────────────────────────────
  const { data: cities = [], isLoading: isLoadingCities } = useCities();

  const cityOptions = [...cities]
    .sort((a, b) => a.city.localeCompare(b.city))
    .map((c) => ({
      label: c.city,
      value: c.city,
    }));

  const citiesZoneMapping = cities.reduce(
    (acc, item) => {
      acc[item.city] = {
        zoneId: item.zoneId,
        zone: item.zone,
        chapterId: item.chapterId,
        cityId: item.cityId,
      };

      return acc;
    },
    {} as Record<
      string,
      {
        zoneId: number;
        zone: string;
        chapterId: number;
        cityId: number;
      }
    >,
  );

  const [form, setForm] = useState<FormState>(() => toFormState(currentUser));
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Populate form when data arrives
  useEffect(() => {
    if (currentUser) {
      setForm(toFormState(currentUser));
    }
  }, [currentUser]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>,
  ) =>
    setForm((prev) => {
      if (e.target.name === 'whatsappPhone') {
        return {
          ...prev,
          whatsappPhone: normalizeNigerianPhoneNumber(e.target.value),
        };
      }

      if (e.target.name === 'alternativePhone') {
        return {
          ...prev,
          alternativePhone: normalizeNigerianPhoneNumber(e.target.value),
        };
      }

      return { ...prev, [e.target.name]: e.target.value };
    });

  const handlePhotoChange = (file: File) => {
    const { validFiles, errors } = validateFilesAgainstAcceptList([file], {
      accept: DEFAULT_IMAGE_UPLOAD_ACCEPT,
    });

    if (errors.length > 0 || validFiles.length === 0) {
      const message = errors[0] ?? 'Please choose a valid image.';
      setPhotoError(message);
      toast.error(message);
      return;
    }

    setPhotoError(null);
    setPhotoFile(file);
    const reader = new FileReader();
    reader.onload = () => setPhotoPreview(reader.result as string);
    reader.readAsDataURL(file);
  };

  const handleSave = async () => {
    if (!currentUser?.id) return;

    const whatsappPhoneError = validateNigerianPhoneNumber(form.whatsappPhone);
    if (whatsappPhoneError) {
      toast.error(whatsappPhoneError);
      return;
    }

    const alternativePhoneError = validateNigerianPhoneNumber(form.alternativePhone, {
      required: false,
    });
    if (alternativePhoneError) {
      toast.error(`Alternative phone: ${alternativePhoneError}`);
      return;
    }

    setIsSaving(true);

    const updates: Partial<AuthSessionUser> = {
      otherNames: form.firstName.trim(),
      surname: form.lastName.trim(),
      fullName: `${form.firstName.trim()} ${form.lastName.trim()}`.trim(),
      nameInSchool: form.nameInSchool.trim() || '',
      nickName: form.nickName.trim() || '',
      whatsappPhone: formatOptionalNigerianPhoneNumber(form.whatsappPhone) || '',
      alternativePhone: formatOptionalNigerianPhoneNumber(form.alternativePhone) || '',
      birthDate: form.birthDate || undefined,
      bio: form.bio.trim() || '',
      houseColor: form.houseColor || '',
      residentialAddress: form.residentialAddress.trim() || '',
      area: form.area || '',
      city: form.city || '',
      // state: form.state || "",
      employmentStatus: form.employmentStatus || '',
      occupations: form.occupation ? [form.occupation] : undefined,
      industrySectors: form.industrySector ? [form.industrySector] : undefined,
      yearsOfExperience: form.yearsOfExperience || undefined,
      company: form.company.trim() || '',
      position: form.position.trim() || '',
      isVolunteer: form.isVolunteer,
      linkedin: form.linkedin.trim() || '',
      twitter: form.twitter.trim() || '',
      instagram: form.instagram.trim() || '',
      facebook: form.facebook.trim() || '',
      tiktok: form.tiktok.trim() || '',
    };


    try {
      const saved = await userService.updateProfile({
        userId: currentUser.id,
        updates,
        photoFile: photoFile ?? undefined,
      });
      updateUser(saved);
      await queryClient.invalidateQueries({ queryKey: currentUserKeys.all });
      toast.success('Profile updated successfully!');
      navigate(USER_ROUTES.PROFILE);
    } catch (err: any) {
      toast.fromError(err);
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <>
        <SEO title="Edit Profile" />
        <Breadcrumbs items={breadcrumbItems} />
        <section className="section py-8">
          <div className="container-custom max-w-3xl animate-pulse space-y-4">
            <div className="h-24 bg-white rounded-2xl" />
            <div className="h-64 bg-white rounded-2xl" />
            <div className="h-48 bg-white rounded-2xl" />
          </div>
        </section>
      </>
    );
  }

  const fullName = `${form.firstName} ${form.lastName}`.trim();

  return (
    <>
      <SEO title="Edit Profile" description="Edit your alumni profile." />
      <Breadcrumbs items={breadcrumbItems} />

      <section className="section bg-[#F8F8F7] py-8">
        <div className="container-custom">
          <div className="space-y-5">
            {/* ── Photo ───────────────────────────────────────────────── */}
            <PhotoUpload
              currentPhoto={currentUser?.photo}
              preview={photoPreview}
              fullName={fullName || currentUser?.fullName || ''}
              error={photoError}
              onSelectFile={handlePhotoChange}
            />

            {/* ── Bio ─────────────────────────────────────────────────── */}
            <SectionCard title="Bio">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <Label>First Name</Label>
                  <Input
                    name="firstName"
                    value={form.firstName}
                    onChange={handleChange}
                    placeholder="First name"
                  />
                </div>
                <div>
                  <Label>Last Name</Label>
                  <Input
                    name="lastName"
                    value={form.lastName}
                    onChange={handleChange}
                    placeholder="Last name"
                  />
                </div>
                <div>
                  <Label>Former Name</Label>
                  <Input
                    name="nameInSchool"
                    value={form.nameInSchool}
                    onChange={handleChange}
                    placeholder="Former name"
                  />
                </div>
                <div>
                  <Label>Preferred Nickname</Label>
                  <Input
                    name="nickName"
                    value={form.nickName}
                    onChange={handleChange}
                    placeholder="Nickname"
                  />
                </div>
                <div>
                  <Label>Email Address</Label>
                  <Input
                    name="email"
                    value={currentUser?.email ?? ''}
                    onChange={() => {}}
                    placeholder="Email"
                    disabled
                  />
                </div>
                <div>
                  <Label>WhatsApp</Label>
                  <PhoneNumberInput
                    name="whatsappPhone"
                    value={form.whatsappPhone}
                    onChange={handleChange}
                    placeholder={NIGERIAN_PHONE_PLACEHOLDER}
                    controlClassName="!rounded-3xl !border-gray-200 !bg-gray-50 !shadow-none focus-within:!border-primary-400 focus-within:!ring-2 focus-within:!ring-primary-300 focus-within:!bg-white"
                    inputClassName="!px-4 !py-2.5 !text-sm !text-gray-800 placeholder:!text-gray-400"
                  />
                </div>
                <div>
                  <Label>Alt. Phone</Label>
                  <PhoneNumberInput
                    name="alternativePhone"
                    value={form.alternativePhone}
                    onChange={handleChange}
                    placeholder={NIGERIAN_PHONE_PLACEHOLDER}
                    controlClassName="!rounded-3xl !border-gray-200 !bg-gray-50 !shadow-none focus-within:!border-primary-400 focus-within:!ring-2 focus-within:!ring-primary-300 focus-within:!bg-white"
                    inputClassName="!px-4 !py-2.5 !text-sm !text-gray-800 placeholder:!text-gray-400"
                  />
                </div>

                <div>
                  <Label>Date of Birth</Label>
                  <DatePicker
                    id="birthDate"
                    value={form.birthDate}
                    onValueChange={(val) =>
                      setForm((prev) => ({
                        ...prev,
                        birthDate: val,
                      }))
                    }
                    max={getDateYearsAgo(15)}
                    placeholder="Select date of birth"
                    hint="You must be at least 15 years old"
                  />
                </div>
              </div>
            </SectionCard>

            {/* ── About Me ────────────────────────────────────────────── */}
            <SectionCard title="About Me">
              <TextareaInput
                name="bio"
                value={form.bio}
                onChange={handleChange}
                rows={5}
                placeholder="Tell other alumni about yourself..."
                className="gap-2"
                textareaClassName="rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 placeholder-gray-400 shadow-none focus:border-primary-400 focus:ring-2 focus:ring-primary-300 focus:bg-white transition-all resize-none"
              />
            </SectionCard>

            {/* ── Professional Info ────────────────────────────────────── */}
            <SectionCard title="Professional Info" defaultOpen={false}>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <Label>Employment Status</Label>
                  <SelectInput
                    name="employmentStatus"
                    value={form.employmentStatus}
                    onChange={handleChange}
                    options={employmentOptions}
                    placeholder="Select status"
                  />
                </div>
                <div>
                  <Label>Occupation</Label>
                  <Input
                    name="occupation"
                    value={form.occupation}
                    onChange={handleChange}
                    placeholder="e.g. Software Engineer"
                  />
                </div>
                <div>
                  <Label>Industry Sector</Label>
                  <SelectInput
                    name="industrySector"
                    value={form.industrySector}
                    onChange={handleChange}
                    options={industrySectorOpts}
                    placeholder="Select sector"
                  />
                </div>
                <div>
                  <Label>Years of Experience</Label>
                  <SelectInput
                    name="yearsOfExperience"
                    value={form.yearsOfExperience}
                    onChange={handleChange}
                    options={yearsExpOpts}
                    placeholder="Select range"
                  />
                </div>
                <div>
                  <Label>Current Company</Label>
                  <Input
                    name="company"
                    value={form.company}
                    onChange={handleChange}
                    placeholder="Company name"
                  />
                </div>
                <div>
                  <Label>Current Position</Label>
                  <Input
                    name="position"
                    value={form.position}
                    onChange={handleChange}
                    placeholder="Job title"
                  />
                </div>
              </div>
            </SectionCard>

            {/* ── Address ─────────────────────────────────────────────── */}
            <SectionCard title="Address" defaultOpen={false}>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="sm:col-span-3">
                  <Label>Street Number and Name</Label>
                  <Input
                    name="residentialAddress"
                    value={form.residentialAddress}
                    onChange={handleChange}
                    placeholder="e.g. 23A Dolphin Estate"
                  />
                </div>
                {/* <div>
                  <Label>State</Label>
                  <SelectInput
                    name="state"
                    value={form.state}
                    onChange={handleChange}
                    options={stateOptions}
                    placeholder="Select State"
                    disabled
                  />
                </div> */}

                <div>
                  <Label>Area</Label>
                  <Input
                    name="area"
                    value={form.area}
                    onChange={handleChange}
                    placeholder="Enter Area"
                  />
                </div>

                {/* ── CHANGED: city is now a dropdown backed by the API ── */}
                <div>
                  <Label>City</Label>
                  <SelectInput
                    name="city"
                    value={form.city}
                    onChange={handleChange}
                    options={cityOptions}
                    placeholder={isLoadingCities ? 'Loading cities...' : 'Select your city'}
                    disabled={isLoadingCities}
                  />
                </div>

                {/* Replace this with zone instead */}
                <div>
                  <Label>Zone</Label>
                  <Input
                    name="zone"
                    value={citiesZoneMapping[form?.city]?.zone ?? ''}
                    placeholder=""
                    disabled
                  />
                </div>
              </div>
            </SectionCard>

            {/* ── Socials ─────────────────────────────────────────────── */}
            <SectionCard title="Socials" defaultOpen={false}>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {[
                  {
                    name: 'instagram',
                    label: 'Instagram',
                    placeholder: 'https://www.instagram.com/your_handle',
                  },
                  {
                    name: 'facebook',
                    label: 'Facebook',
                    placeholder: 'https://www.facebook.com/your_profile',
                  },
                  {
                    name: 'linkedin',
                    label: 'LinkedIn',
                    placeholder: 'Enter your LinkedIn profile link',
                  },
                  { name: 'twitter', label: 'X', placeholder: 'https://www.x.com/your_handle' },
                  {
                    name: 'tiktok',
                    label: 'TikTok',
                    placeholder: 'https://www.tiktok.com/@your_handle',
                  },
                ].map(({ name, label, placeholder }) => (
                  <div key={name}>
                    <Label>{label}</Label>
                    <Input
                      name={name}
                      value={(form as any)[name]}
                      onChange={handleChange}
                      placeholder={placeholder}
                    />
                  </div>
                ))}
              </div>
            </SectionCard>

            {/* ── Volunteer ────────────────────────────────────────────── */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 sm:px-6 py-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.isVolunteer}
                  onChange={(e) => setForm((p) => ({ ...p, isVolunteer: e.target.checked }))}
                  className="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-400"
                />
                <div>
                  <p className="text-sm font-medium text-gray-800">
                    Would you like to volunteer for events and projects?
                  </p>
                  <p className="text-xs text-gray-400 mt-0.5">Yes, I am interested</p>
                </div>
              </label>
            </div>

            {/* ── Save / Cancel ────────────────────────────────────────── */}
            <div className="flex flex-col-reverse sm:flex-row gap-3 pt-2 pb-6">
              {/* <button
                type="button"
                onClick={() => navigate(USER_ROUTES.PROFILE)}
                disabled={isSaving}
                className="flex-1 sm:flex-none px-6 py-3 rounded-3xl border border-red-400 text-sm font-semibold text-red-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
              >
                Cancel
              </button> */}
              <button
                type="button"
                onClick={handleSave}
                disabled={isSaving}
                className="flex-1 sm:flex-none px-8 py-3 rounded-3xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold flex items-center justify-center gap-2 transition-colors disabled:opacity-60"
              >
                {isSaving ? (
                  <>
                    <Icon icon="mdi:loading" className="w-4 h-4 animate-spin" /> Saving...
                  </>
                ) : (
                  'Save Changes'
                )}
              </button>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
