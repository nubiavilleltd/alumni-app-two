// features/authentication/pages/RegisterDetailsPage.tsx
// MODIFIED: city field changed from free-text FormInput to a SelectInput
// backed by useCities(). The selected city's name is stored in the form
// (and ultimately sent to the backend) exactly as before — only the UI changes.

import { zodResolver } from '@hookform/resolvers/zod';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useLocation, useNavigate } from 'react-router-dom';
import { AppLink } from '@/shared/components/ui/AppLink';
import { Button } from '@/shared/components/ui/Button';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { DatePicker } from '@/shared/components/ui/input/DatePicker';
import { PhoneNumberInput } from '@/shared/components/ui/input/PhoneNumberInput';
import { PasswordInput } from '@/shared/components/ui/input/PasswordInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { authApi } from '../services/auth.service';
import { registerDetailsSchema } from '../schemas/authSchema';
import type {
  RegisterDetailsFormValues,
  RegisterDetailsSubmitValues,
  SocialAuthProvider,
  SocialSignupResponse,
  Voucher,
} from '../types/auth.types';
import { PasswordStrengthMeter } from '../components/PasswordStrengthMeter';
import { RegistrationShell } from '../components/RegistrationShell';
import { AUTH_ROUTES } from '../routes';
import {
  clearRegistrationFlow,
  loadRegistrationFlow,
  saveRegistrationFlow,
} from '../lib/registrationFlow';
import { markInitialVerificationSend } from '../lib/verificationResendThrottle';
import { useCities } from '../hooks/useCities';
import { NIGERIAN_PHONE_PLACEHOLDER } from '@/shared/utils/nigerianPhoneNumber';
import { GoogleAuthButton } from '../components/GoogleAuthButton';
import { FacebookAuthButton } from '../components/FacebookAuthButton';
import { toast } from '@/shared/components/ui/Toast';
import { userService } from '@/features/user/services/user.service';
// import { useCities } from '@/features/alumni/hooks/useCities';

type NormalizedSocialAuthResponse = {
  provider: SocialAuthProvider;
  userId?: string;
  providerUserId: string;
  firstName: string | null;
  lastName: string | null;
  email: string | null;
  emailVerified: boolean;
  avatarUrl: string | null;
  accessToken?: string;
  source: SocialAuthProvider;
  raw?: unknown;
};

type RegisterLocationState = {
  socialOnboarding?: {
    provider: SocialAuthProvider;
    userId: string;
    email?: string;
    fullName?: string;
    message?: string;
    accessToken?: string;
  };
};

function getProviderLabel(provider: SocialAuthProvider) {
  return provider === 'facebook' ? 'Facebook' : 'Google';
}

function buildRegisterDefaultValues(
  currentYear: number,
  savedValues?: RegisterDetailsFormValues | null,
): RegisterDetailsFormValues {
  return {
    surname: savedValues?.surname ?? '',
    otherNames: savedValues?.otherNames ?? '',
    nameInSchool: savedValues?.nameInSchool ?? '',
    nickName: savedValues?.nickName ?? '',
    email: savedValues?.email ?? '',
    whatsappPhone: savedValues?.whatsappPhone ?? '',
    graduationYear: savedValues?.graduationYear ?? currentYear,
    password: savedValues?.password ?? '',
    confirmPassword: savedValues?.confirmPassword ?? '',
    isSocialSignup: savedValues?.isSocialSignup ?? false,
    voucherId: savedValues?.voucherId ?? '',
    city: savedValues?.city ?? '',
    area: savedValues?.area ?? '',
    // state: 'Lagos',
    residentialAddress: savedValues?.residentialAddress ?? '',
  };
}

function splitFullName(fullName?: string) {
  const parts = fullName?.trim().split(/\s+/).filter(Boolean) ?? [];

  return {
    firstName: parts.length > 1 ? parts.slice(0, -1).join(' ') : (parts[0] ?? ''),
    lastName: parts.length > 1 ? parts[parts.length - 1] : '',
  };
}

function getRegistrationErrorDetails(error: unknown) {
  const apiError = error as Partial<Error> & { status?: number };

  return {
    message: apiError.message || 'Registration failed. Please try again.',
    status: apiError.status,
  };
}

function asRecord(value: unknown) {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : null;
}

function readString(...values: unknown[]) {
  const value = values.find((item) => typeof item === 'string' && item.trim().length > 0);
  return typeof value === 'string' ? value.trim() : undefined;
}

function readResponseString(response: unknown, ...keys: string[]) {
  const root = asRecord(response);
  if (!root) return undefined;

  const nested = [root, root.data, root.user, root.profile]
    .map(asRecord)
    .filter(Boolean) as Array<Record<string, unknown>>;

  return readString(...nested.flatMap((source) => keys.map((key) => source[key])));
}

function getApiErrorResponse(error: unknown) {
  return error instanceof Error
    ? (error as Error & { details?: { response?: unknown } }).details?.response
    : undefined;
}

export function RegisterDetailsPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const locationState = (location.state as RegisterLocationState | null) ?? null;
  const currentYear = new Date().getFullYear();
  const savedFlow = useMemo(() => loadRegistrationFlow(), []);

  // ── Cities from API ──────────────────────────────────────────────────────────
  const { data: cities = [], isLoading: isLoadingCities } = useCities();

  // const cityOptions = cities.map((c) => ({
  //   label: c.city,
  //   value: c.city, // store city name string — same as old free-text value
  // }));

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

  useEffect(() => {
    if (savedFlow?.step === 'success') {
      clearRegistrationFlow();
    }
  }, [savedFlow]);

  const graduationYearOptions = Array.from({ length: currentYear - 1966 + 1 }, (_, index) => ({
    label: String(currentYear - index),
    value: String(currentYear - index),
  }));

  const [allVouchers, setAllVouchers] = useState<Voucher[]>([]);
  const [filteredVouchers, setFilteredVouchers] = useState<Voucher[]>([]);
  const [isLoadingVouchers, setIsLoadingVouchers] = useState(false);
  const [birthDate, setBirthDate] = useState('');
  const [selectedSocialProvider, setSelectedSocialProvider] = useState<SocialAuthProvider | null>(
    null,
  );
  const [socialAuthResponse, setSocialAuthResponse] = useState<NormalizedSocialAuthResponse | null>(
    null,
  );
  const [socialAuthStatus, setSocialAuthStatus] = useState<string | null>(null);
  const todayDate = new Date().toISOString().split('T')[0];

  const detailForm = useForm<RegisterDetailsFormValues, unknown, RegisterDetailsSubmitValues>({
    resolver: zodResolver(registerDetailsSchema),
    defaultValues: buildRegisterDefaultValues(
      currentYear,
      savedFlow?.step === 'verification' ? savedFlow.formValues : null,
    ),
    mode: 'onChange',
  });

  useEffect(() => {
    const onboarding = locationState?.socialOnboarding;
    if (!onboarding) {
      return;
    }

    const { firstName, lastName } = splitFullName(onboarding.fullName);
    const providerLabel = getProviderLabel(onboarding.provider);

    detailForm.setValue('isSocialSignup', true, { shouldDirty: true, shouldValidate: true });
    detailForm.setValue('otherNames', firstName, { shouldDirty: true, shouldValidate: true });
    detailForm.setValue('surname', lastName, { shouldDirty: true, shouldValidate: true });
    detailForm.setValue('email', onboarding.email ?? '', {
      shouldDirty: true,
      shouldValidate: true,
    });
    detailForm.setValue('password', '', { shouldDirty: false, shouldValidate: false });
    detailForm.setValue('confirmPassword', '', { shouldDirty: false, shouldValidate: false });
    detailForm.clearErrors(['password', 'confirmPassword']);

    setSelectedSocialProvider(onboarding.provider);
    setSocialAuthResponse({
      provider: onboarding.provider,
      userId: onboarding.userId,
      providerUserId: '',
      firstName: firstName || null,
      lastName: lastName || null,
      email: onboarding.email ?? null,
      emailVerified: Boolean(onboarding.email),
      avatarUrl: null,
      accessToken: onboarding.accessToken,
      source: onboarding.provider,
      raw: onboarding,
    });
    setSocialAuthStatus(
      onboarding.message ?? `${providerLabel} connected. Complete the remaining fields.`,
    );
  }, [detailForm, locationState]);

  const passwordValue = detailForm.watch('password') ?? '';
  const confirmPasswordValue = detailForm.watch('confirmPassword') ?? '';
  const isSocialSignup = Boolean(detailForm.watch('isSocialSignup'));
  const emailValue = detailForm.watch('email') ?? '';
  const graduationYear = detailForm.watch('graduationYear');
  const cityValue = detailForm.watch('city');
  const passwordsMatch = passwordValue.length > 0 && confirmPasswordValue === passwordValue;

  // useEffect(() => {
  //   if (detailForm.getValues('state') !== 'Lagos') {
  //     detailForm.setValue('state', 'Lagos', {
  //       shouldDirty: false,
  //       shouldValidate: true,
  //     });
  //   }
  // }, [detailForm]);

  useEffect(() => {
    const loadVouchers = async () => {
      setIsLoadingVouchers(true);
      const vouchers = await authApi.getVouchers();
      setAllVouchers(vouchers);
      setIsLoadingVouchers(false);
    };

    void loadVouchers();
  }, []);

  useEffect(() => {
    if (graduationYear) {
      const nextVouchers = allVouchers;
      setFilteredVouchers(nextVouchers);

      const currentVoucherId = detailForm.getValues('voucherId');
      if (currentVoucherId) {
        const isValidVoucher = nextVouchers.some(
          (voucher) => String(voucher.id) === currentVoucherId,
        );
        if (!isValidVoucher) {
          detailForm.setValue('voucherId', '');
          detailForm.setError('voucherId', {
            type: 'manual',
            message: 'Please select a voucher for the selected graduation year',
          });
        } else {
          detailForm.clearErrors('voucherId');
        }
      }
    } else {
      setFilteredVouchers([]);
    }
  }, [allVouchers, detailForm, graduationYear]);

  const voucherOptions = filteredVouchers.map((voucher) => ({
    label: `${voucher.fullName} (${voucher.email})`,
    value: String(voucher.id),
  }));

  const selectedSocialLabel = selectedSocialProvider
    ? getProviderLabel(selectedSocialProvider)
    : null;

  const applySocialProfile = useCallback(
    (profile: SocialSignupResponse) => {
      const providerLabel = getProviderLabel(profile.provider);

      detailForm.setValue('otherNames', profile.firstName ?? '', {
        shouldDirty: true,
        shouldValidate: true,
      });
      detailForm.setValue('surname', profile.lastName ?? '', {
        shouldDirty: true,
        shouldValidate: true,
      });

      if (profile.email) {
        detailForm.setValue('email', profile.email, {
          shouldDirty: true,
          shouldValidate: true,
        });
        detailForm.clearErrors('email');
      } else {
        detailForm.setValue('email', '', {
          shouldDirty: true,
          shouldValidate: true,
        });
      }

      detailForm.setValue('isSocialSignup', true, {
        shouldDirty: true,
        shouldValidate: true,
      });
      detailForm.setValue('password', '', { shouldDirty: true, shouldValidate: false });
      detailForm.setValue('confirmPassword', '', { shouldDirty: true, shouldValidate: false });
      detailForm.clearErrors(['password', 'confirmPassword']);

      setSelectedSocialProvider(profile.provider);
      setSocialAuthResponse({
        provider: profile.provider,
        userId: profile.userId,
        providerUserId: profile.providerUserId,
        firstName: profile.firstName,
        lastName: profile.lastName,
        email: profile.email,
        emailVerified: profile.emailVerified,
        avatarUrl: profile.avatarUrl,
        accessToken: profile.accessToken,
        source: profile.provider,
        raw: profile.raw,
      });
      setSocialAuthStatus(`${providerLabel} connected. Complete the remaining fields.`);
    },
    [detailForm],
  );

  const hydrateSocialSignupAccessToken = useCallback(
    async (
      profile: SocialSignupResponse,
      credentials: { idToken?: string; accessToken?: string },
    ): Promise<SocialSignupResponse> => {
      if (profile.accessToken) {
        return profile;
      }

      try {
        const loginResponse = await authApi.socialLogin({
          provider: profile.provider,
          idToken: credentials.idToken,
          accessToken: credentials.accessToken,
        });

        return {
          ...profile,
          userId: profile.userId || loginResponse.user?.id || '',
          accessToken: loginResponse.accessToken,
          refreshToken: loginResponse.refreshToken,
        };
      } catch (error) {
        const response = getApiErrorResponse(error);
        const accessToken = readResponseString(response, 'access_token', 'accessToken', 'token');
        const refreshToken = readResponseString(response, 'refresh_token', 'refreshToken');
        const userId = readResponseString(response, 'user_id', 'userId', 'id');
        const email = readResponseString(response, 'email');
        const fullName = readResponseString(response, 'fullname', 'fullName', 'full_name', 'name');

        if (!accessToken) {
          return profile;
        }

        const { firstName, lastName } = splitFullName(fullName);

        return {
          ...profile,
          userId: profile.userId || userId || '',
          email: profile.email ?? email ?? null,
          firstName: profile.firstName ?? (firstName || null),
          lastName: profile.lastName ?? (lastName || null),
          accessToken,
          refreshToken,
        };
      }
    },
    [],
  );

  const handleGoogleSignupCredential = useCallback(
    async (idToken: string) => {
      try {
        setSelectedSocialProvider('google');
        setSocialAuthStatus('Checking your Google account...');
        const signupResponse = await authApi.socialSignup({ provider: 'google', idToken });
        const hydratedSignupResponse = await hydrateSocialSignupAccessToken(signupResponse, {
          idToken,
        });
        applySocialProfile(hydratedSignupResponse);
      } catch (error) {
        setSocialAuthStatus(null);
        const { message, status } = getRegistrationErrorDetails(error);

        if (status === 409 && detailForm.getValues('email')) {
          detailForm.setError('email', { type: 'server', message }, { shouldFocus: true });
        } else {
          detailForm.setError('root', { type: 'server', message });
        }

        toast.error(message);
      }
    },
    [applySocialProfile, detailForm, hydrateSocialSignupAccessToken],
  );

  const handleFacebookSignupAccessToken = useCallback(
    async (accessToken: string) => {
      try {
        setSelectedSocialProvider('facebook');
        setSocialAuthStatus('Checking your Facebook account...');
        const signupResponse = await authApi.socialSignup({ provider: 'facebook', accessToken });
        const hydratedSignupResponse = await hydrateSocialSignupAccessToken(signupResponse, {
          accessToken,
        });
        applySocialProfile(hydratedSignupResponse);
      } catch (error) {
        setSocialAuthStatus(null);
        const { message, status } = getRegistrationErrorDetails(error);

        if (status === 409 && detailForm.getValues('email')) {
          detailForm.setError('email', { type: 'server', message }, { shouldFocus: true });
        } else {
          detailForm.setError('root', { type: 'server', message });
        }

        toast.error(message);
      }
    },
    [applySocialProfile, detailForm, hydrateSocialSignupAccessToken],
  );

  const submitDetails = detailForm.handleSubmit(async (values) => {
    try {
      if (values.isSocialSignup) {
        if (!socialAuthResponse?.userId) {
          console.error('Social signup missing user ID:', socialAuthResponse);
          detailForm.setError('root', {
            message: 'We could not complete social registration. Please try again.',
          });
          return;
        }

        if (!socialAuthResponse.accessToken) {
          console.error('Social onboarding update requires an app access token:', {
            userId: socialAuthResponse.userId,
            response: socialAuthResponse.raw,
          });
          detailForm.setError('root', {
            message: 'We could not complete your profile. Please try again.',
          });
          return;
        }

        const saved = await userService.completeSocialOnboarding({
          userId: socialAuthResponse.userId,
          accessToken: socialAuthResponse.accessToken,
          updates: {
            otherNames: values.otherNames,
            surname: values.surname,
            email: values.email,
            whatsappPhone: values.whatsappPhone,
            nameInSchool: values.nameInSchool,
            nickName: values.nickName,
            graduationYear: values.graduationYear,
            residentialAddress: values.residentialAddress,
            area: values.area,
            city: values.city,
            birthDate: birthDate || undefined,
          },
          extraFields: {
            birth_date: birthDate || undefined,
            voucher_id: values.voucherId,
          },
        });

        navigate(AUTH_ROUTES.LOGIN, {
          replace: true,
          state: {
            loginNotice: 'Profile completed successfully. Your account is now pending approval.',
          },
        });
        return;
      }

      const response = await authApi.startRegistration(values);

      if (!response.userId) {
        console.error('Registration response did not include user ID:', response);
        detailForm.setError('root', {
          message: 'We could not continue registration. Please try again.',
        });
        return;
      }

      markInitialVerificationSend(response.userId);

      saveRegistrationFlow({
        step: 'verification',
        formValues: values,
        verificationResponse: response,
        userId: response.userId,
        completionResponse: null,
      });

      navigate(AUTH_ROUTES.REGISTER_VERIFY);
    } catch (error: unknown) {
      const { message, status } = getRegistrationErrorDetails(error);

      if (status === 409) {
        detailForm.setError('email', { type: 'server', message }, { shouldFocus: true });
        return;
      }

      detailForm.setError('root', {
        message,
      });
    }
  });

  return (
    <RegistrationShell step="details">
      <form
        className="auth-form auth-form--registration-details"
        onSubmit={submitDetails}
        noValidate
      >
        {!isSocialSignup || socialAuthStatus ? (
          <div className="auth-social-signup" aria-label="Social sign up options">
            {!isSocialSignup ? (
              <>
                <div className="auth-social-signup__buttons">
                  <GoogleAuthButton
                    label="Sign up with Google"
                    text="signup_with"
                    onCredential={handleGoogleSignupCredential}
                  />
                  <FacebookAuthButton
                    label="Sign up with Facebook"
                    className={
                      selectedSocialProvider === 'facebook' ? 'auth-social-button--selected' : ''
                    }
                    onAccessToken={handleFacebookSignupAccessToken}
                  />
                </div>

                <div className="auth-social-divider" aria-hidden="true">
                  <span />
                  <p>or</p>
                  <span />
                </div>
              </>
            ) : null}

            {socialAuthStatus ? (
              <div className="auth-social-status-panel" aria-live="polite">
                <p className="auth-social-status">{socialAuthStatus}</p>
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="auth-form-grid auth-form-grid--two">
          <FormInput
            label="First Name"
            required
            id="otherNames"
            placeholder="e.g. Adaeze"
            error={detailForm.formState.errors.otherNames?.message}
            {...detailForm.register('otherNames')}
          />
          <FormInput
            label="Last Name"
            id="surname"
            required
            placeholder="e.g. Okonkwo"
            error={detailForm.formState.errors.surname?.message}
            {...detailForm.register('surname')}
          />
        </div>

        <div className="auth-form-grid auth-form-grid--two">
          <FormInput
            label="Former Name"
            id="nameInSchool"
            placeholder="Ezegburu"
            hint=""
            error={detailForm.formState.errors.nameInSchool?.message}
            {...detailForm.register('nameInSchool')}
          />
          <FormInput
            label="Preferred Nickname"
            id="nickName"
            placeholder="MJ"
            hint=""
            error={detailForm.formState.errors.nickName?.message}
            {...detailForm.register('nickName')}
          />
        </div>

        <div className="auth-form-grid auth-form-grid--two">
          {socialAuthResponse?.emailVerified ? (
            <input type="hidden" value={emailValue} {...detailForm.register('email')} />
          ) : null}
          <FormInput
            label="Email Address"
            id="email"
            required
            type="email"
            placeholder="you@example.com"
            disabled={Boolean(socialAuthResponse?.emailVerified)}
            hint={
              socialAuthResponse?.emailVerified && selectedSocialLabel
                ? `${selectedSocialLabel} email verified`
                : undefined
            }
            value={socialAuthResponse?.emailVerified ? emailValue : undefined}
            error={detailForm.formState.errors.email?.message}
            {...(!socialAuthResponse?.emailVerified ? detailForm.register('email') : {})}
          />

          <DatePicker
            label="Date of Birth"
            id="birthDate"
            value={birthDate}
            max={todayDate}
            placeholder="Select date of birth"
            onValueChange={setBirthDate}
          />
        </div>

        {!isSocialSignup ? (
          <>
            <PasswordInput
              label="Password"
              id="password"
              disableCopy
              required
              autoComplete="new-password"
              placeholder="Create a secure password"
              error={detailForm.formState.errors.password?.message}
              {...detailForm.register('password')}
            />

            <div>
              <PasswordInput
                label="Confirm Password"
                id="confirmPassword"
                disablePaste
                required
                autoComplete="new-password"
                placeholder="Re-enter your password"
                error={detailForm.formState.errors.confirmPassword?.message}
                {...detailForm.register('confirmPassword')}
              />
              {!detailForm.formState.errors.confirmPassword && confirmPasswordValue ? (
                <p
                  className={`auth-field-hint ${
                    passwordsMatch ? 'auth-field-hint--success' : 'auth-field-hint--muted'
                  }`}
                >
                  {passwordsMatch ? 'Passwords match' : 'Passwords must match exactly'}
                </p>
              ) : null}
            </div>

            {passwordValue && <PasswordStrengthMeter password={passwordValue} />}
          </>
        ) : null}

        <div className="auth-phone-field">
          <PhoneNumberInput
            label="WhatsApp Phone Number"
            id="whatsappPhone"
            required
            placeholder={NIGERIAN_PHONE_PLACEHOLDER}
            hint="Enter your 11-digit Nigerian phone number starting with 0"
            error={detailForm.formState.errors.whatsappPhone?.message}
            {...detailForm.register('whatsappPhone')}
          />
        </div>

        <div className="auth-form-grid auth-form-grid--two">
          {/* <TextareaInput
          label="Street Name and Address"
          id="residentialAddress"
          required
          rows={5}
          placeholder=""
          error={detailForm.formState.errors.residentialAddress?.message}
          {...detailForm.register('residentialAddress')}
        /> */}
          <FormInput
            label="Street Name and Address"
            id="residentialAddress"
            required
            placeholder=""
            error={detailForm.formState.errors.residentialAddress?.message}
            {...detailForm.register('residentialAddress')}
          />

          <FormInput
            label="Area"
            id="area"
            required
            type="text"
            placeholder=""
            error={detailForm.formState.errors.area?.message}
            {...detailForm.register('area')}
          />
        </div>

        <div className="auth-form-grid auth-form-grid--two">
          {/* <SelectInput
            label="State of Residence"
            id="state"
            required
            options={stateOptions}
            placeholder="Lagos"
            error={detailForm.formState.errors.state?.message}
            value={detailForm.watch('state') || 'Lagos'}
            disabled
            onChange={(e) => {
              detailForm.setValue('state', e.target.value, {
                shouldValidate: true,
                shouldDirty: true,
              });
            }}
            onBlur={() => detailForm.trigger('state')}
          /> */}

          {/* ── CHANGED: city is now a dropdown backed by the API ── */}
          <SelectInput
            label="City"
            id="city"
            required
            options={cityOptions}
            placeholder={isLoadingCities ? 'Loading cities...' : 'Select your city'}
            disabled={isLoadingCities}
            error={detailForm.formState.errors.city?.message}
            value={cityValue || ''}
            onChange={(e) => {
              detailForm.setValue('city', e.target.value, {
                shouldValidate: true,
                shouldDirty: true,
              });
            }}
            onBlur={() => detailForm.trigger('city')}
          />
          <FormInput
            label="Zone"
            id="zone"
            type="text"
            placeholder=""
            disabled
            value={citiesZoneMapping[cityValue]?.zone ?? ''}
          />
        </div>

        <SelectInput
          label="Year of Graduation"
          id="graduationYear"
          required
          options={graduationYearOptions}
          placeholder="Select Graduation Year"
          error={detailForm.formState.errors.graduationYear?.message}
          value={detailForm.watch('graduationYear')?.toString() || ''}
          onChange={(event) => {
            detailForm.setValue('graduationYear', parseInt(event.target.value, 10), {
              shouldValidate: true,
              shouldDirty: true,
            });
          }}
          onBlur={() => detailForm.trigger('graduationYear')}
        />

        <SelectInput
          label="Voucher (Someone to approve your registration)"
          id="voucherId"
          required
          options={voucherOptions}
          placeholder={
            isLoadingVouchers
              ? 'Loading vouchers...'
              : filteredVouchers.length === 0 && graduationYear
                ? 'No vouchers available for this graduation year'
                : 'Select a voucher'
          }
          error={detailForm.formState.errors.voucherId?.message}
          disabled={isLoadingVouchers || filteredVouchers.length === 0}
          hint="Select a member who will vouch for you"
          value={detailForm.watch('voucherId') || ''}
          onChange={(event) => {
            detailForm.setValue('voucherId', event.target.value, {
              shouldValidate: true,
              shouldDirty: true,
            });
          }}
          onBlur={() => detailForm.trigger('voucherId')}
        />

        {detailForm.formState.errors.root && (
          <div className="auth-alert auth-alert--error">
            <p>{detailForm.formState.errors.root.message}</p>
          </div>
        )}

        <Button
          type="submit"
          fullWidth
          loading={detailForm.formState.isSubmitting}
          rightIcon={detailForm.formState.isSubmitting ? undefined : 'mdi:arrow-right'}
          className="auth-submit-button rounded-full"
        >
          {detailForm.formState.isSubmitting ? 'Checking...' : 'Continue'}
        </Button>

        <p className="auth-card__footer-text auth-card__footer-text--compact">
          Already have an account?{' '}
          <AppLink href={AUTH_ROUTES.LOGIN} className="auth-card__footer-link">
            Login
          </AppLink>
        </p>
      </form>
    </RegistrationShell>
  );
}
