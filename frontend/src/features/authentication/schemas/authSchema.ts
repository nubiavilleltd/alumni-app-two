// features/authentication/schemas/authSchema.ts
//
// emailVerificationSchema only validates `code`.
// userId is NOT a form field — it is server-issued state stored in
// RegistrationFlowState in RegisterForm.tsx.

import { z } from 'zod';
import { NIGERIA_STATES } from '../constants/nigerianStates';
import { validateNigerianPhoneNumber } from '@/shared/utils/nigerianPhoneNumber';

const currentYear = new Date().getFullYear();

const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters')
  .regex(/[a-z]/, 'Password must include at least one lowercase letter')
  .regex(/[A-Z]/, 'Password must include at least one uppercase letter')
  .regex(/\d/, 'Password must include at least one number')
  .regex(/[^A-Za-z0-9]/, 'Password must include at least one special character');

export const loginSchema = z.object({
  email: z.string().trim().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
  rememberMe: z.boolean().default(false),
});

export const forgotPasswordSchema = z.object({
  email: z.string().trim().email('Please enter a valid email address'),
});

export const registerDetailsSchema = z
  .object({
    surname: z
      .string()
      .trim()
      .min(2, 'Last name must be at least 2 characters')
      .max(50, 'Last name must be 50 characters or less')
      .regex(/^[A-Za-z][A-Za-z\s'.-]*$/, 'Please enter a valid last name'),

    otherNames: z
      .string()
      .trim()
      .min(2, 'First name must be at least 2 characters')
      .max(80, 'First name must be 80 characters or less')
      .regex(/^[A-Za-z][A-Za-z\s'.-]*$/, 'Please enter valid first name'),

    nameInSchool: z
      .string()
      .trim()
      .max(80, 'Former name must be 80 characters or less')
      .refine(
        (value) => value === '' || /^[A-Za-z][A-Za-z\s'.-]*$/.test(value),
        'Please enter a valid former name',
      ),

    email: z.string().trim().email('Please enter a valid email address'),
    area: z.string().trim().min(2, 'Area must be at least 2 characters'),
    // state: z.string().refine((val) => NIGERIA_STATES.includes(val as any), {
    //   message: 'Please select a valid state',
    // }),

    whatsappPhone: z.string().trim(),
    city: z.string().min(1, 'Please select a city'),
    // city: z
    //   .string()
    //   .trim()
    //   .min(2, 'City must be at least 2 characters')
    //   .regex(/^[A-Za-z][A-Za-z\s'.-]*$/, 'Please enter a valid city name'),
    residentialAddress: z
      .string()
      .trim()
      .min(2, 'Residential Address must be at least 2 characters'),
    nickName: z.string().optional(),
    // nickName: z.string().trim().min(1, 'Preferred Nickname is required'),

    graduationYear: z.coerce
      .number()
      .int('Graduation year must be a whole number')
      .min(1966, 'Graduation year is too early')
      .max(currentYear, `Graduation year cannot be later than ${currentYear}`),

    isSocialSignup: z.boolean().optional(),
    password: z.string().optional(),
    confirmPassword: z.string().optional(),
    voucherId: z.string().min(1, 'Please select a voucher who will approve your registration'),
  })
  .superRefine((data, ctx) => {
    const phoneError = validateNigerianPhoneNumber(data.whatsappPhone);
    if (phoneError) {
      ctx.addIssue({ code: 'custom', path: ['whatsappPhone'], message: phoneError });
    }

    if (data.isSocialSignup) {
      return;
    }

    const passwordResult = passwordSchema.safeParse(data.password ?? '');
    if (!passwordResult.success) {
      passwordResult.error.issues.forEach((issue) => {
        ctx.addIssue({ ...issue, path: ['password'] });
      });
    }

    if (!data.confirmPassword) {
      ctx.addIssue({
        code: 'custom',
        path: ['confirmPassword'],
        message: 'Please confirm your password',
      });
    } else if (data.password !== data.confirmPassword) {
      ctx.addIssue({
        code: 'custom',
        path: ['confirmPassword'],
        message: 'Passwords do not match',
      });
    }
  });

// Only validates the code the user types.
// userId is NOT included — it is server state, not user input.
export const emailVerificationSchema = z.object({
  code: z
    .string()
    .trim()
    .regex(/^\d{6}$/, 'Enter the 6-digit verification code'),
});

export const resetPasswordSchema = z
  .object({
    password: passwordSchema,
    confirmPassword: z.string().min(1, 'Please confirm your password'),
  })
  .refine((data) => data.password === data.confirmPassword, {
    path: ['confirmPassword'],
    message: 'Passwords do not match',
  });

export const changePasswordSchema = z
  .object({
    currentPassword: passwordSchema,
    newPassword: passwordSchema,
    confirmPassword: z.string().min(1, 'Please confirm your password'),
  })
  .refine((data) => data.newPassword === data.confirmPassword, {
    path: ['confirmPassword'],
    message: 'Passwords do not match',
  });
