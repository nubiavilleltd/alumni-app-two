import { Icon } from '@iconify/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/shared/components/ui/Button';
import { AppLink } from '@/shared/components/ui/AppLink';
import { BaseInput } from '@/shared/components/ui/input/BaseInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import type { Contact } from '@/features/contactUs/types/contact.types';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { usePrefillFormFromUser } from '@/features/contactUs/hooks/usePrefillFormFromUser';
import { toast } from '@/shared/components/ui/Toast';
import { ProtectedContactValue } from '@/shared/components/ui/ProtectedContactValue';
import type { ProtectedContactField } from '@/shared/api/protectedContactApi';

/* ───────────────── TYPES ───────────────── */

type ContactMethod = {
  label: string;
  valueLines: string[];
  icon?: string;
  iconSrc?: string;
  href?: string;
  target?: string;
  rel?: string;
  protectedContact?: {
    field: Extract<ProtectedContactField, 'email' | 'phone'>;
    available: boolean;
    resourceType: string;
    resourceId: string;
  };
};

interface Props {
  title: string;
  description: string;
  contactMethods: ContactMethod[];
  onSubmit: (form: Contact) => Promise<void>;
  isSubmitting: boolean;
}

const contactFieldLabelClassName =
  'text-[0.94rem] font-semibold leading-[1.2] text-[#858585] md:text-[1rem]';
const contactInputControlClassName =
  'min-h-[2.8rem] rounded-full !border-0 !bg-[#f7f5f1] !shadow-none focus-within:!border-0 focus-within:outline focus-within:outline-3 focus-within:outline-primary-500/20 md:min-h-[3.15rem]';
const contactInputClassName =
  'h-[2.8rem] px-4 font-[inherit] text-[0.94rem] font-medium tracking-normal text-[#071116] placeholder:text-[#858585] md:h-[3.15rem] md:px-[1.05rem] md:text-[1rem]';
const contactTextareaClassName =
  'min-h-[7.9rem] rounded-[1.35rem] !border-0 !bg-[#f7f5f1] !shadow-none px-4 py-4 font-[inherit] text-[0.94rem] font-medium leading-[1.45] tracking-normal text-[#071116] placeholder:text-[#858585] focus:!border-0 focus:outline focus:outline-3 focus:outline-primary-500/20 md:min-h-[8.9rem] md:px-[1.05rem] md:py-[1.05rem] md:text-[1rem]';

/* ───────────────── FORM SETUP ───────────────── */

const initialFormState: Contact = {
  firstName: '',
  lastName: '',
  email: '',
  message: '',
};

type ContactFieldErrors = Partial<Record<keyof Contact, string>>;

function validateContactForm(form: Contact): ContactFieldErrors {
  const errors: ContactFieldErrors = {};

  if (!form.firstName.trim()) errors.firstName = 'First name is required.';
  if (!form.lastName.trim()) errors.lastName = 'Last name is required.';
  if (!form.email.trim()) {
    errors.email = 'Email address is required.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = 'Enter a valid email address.';
  }
  if (!form.message.trim()) errors.message = 'Message is required.';

  return errors;
}

/* ───────────────── COMPONENT ───────────────── */

export function ContactPageLayout({
  title,
  description,
  contactMethods,
  onSubmit,
  isSubmitting,
}: Props) {
  const { data: currentUser, isLoading } = useCurrentUser();

  const [form, setForm] = useState<Contact>(initialFormState);
  const [fieldErrors, setFieldErrors] = useState<ContactFieldErrors>({});
  const [formError, setFormError] = useState('');

  usePrefillFormFromUser<Contact>({
    currentUser,
    setForm,
    mapUserToForm: (user) => ({
      firstName: user.otherNames || user.fullName?.split(' ')[0] || '',
      lastName: user.surname || user.fullName?.split(' ')[1] || '',
      email: user.email || '',
    }),
  });

  const handleFieldChange = <K extends keyof Contact>(field: K, value: Contact[K]) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    setFieldErrors((prev) => ({ ...prev, [field]: undefined }));
    setFormError('');
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const errors = validateContactForm(form);
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }

    setFieldErrors({});
    setFormError('');
    // toast.success('Message successfully sent');

    try {
      await onSubmit(form);
      setForm(initialFormState);
    } catch (error: any) {
      setFormError(error.message ?? 'Unable to send your message right now. Please try again.');
    }
  };

  return (
    <main className="min-h-[calc(100vh-5rem)] overflow-hidden font-sans text-[#071116]">
      <div className="relative mx-auto grid gap-10 px-[var(--app-page-inline-padding)] pb-16 pt-10 min-[761px]:pt-11 min-[1180px]:grid-cols-[minmax(18rem,0.92fr)_minmax(0,1.38fr)] min-[1180px]:gap-[clamp(2.5rem,4vw,4.5rem)] min-[1180px]:pb-24 min-[1180px]:pt-[clamp(2.75rem,5vw,4.75rem)]">
        <section className="relative z-[2] pt-0" aria-labelledby="contact-title">
          <h1 id="contact-title" className="type-contact-hero m-0 max-w-[33rem] text-[#071116]">
            {title}
          </h1>
          <p className="type-card-body mt-4 max-w-[26rem] text-[#4e5d72]">{description}</p>

          <div
            className="mt-10 grid grid-cols-1 gap-7 min-[761px]:mt-9 min-[761px]:grid-cols-3 min-[761px]:gap-5 min-[1180px]:mt-[clamp(2.5rem,4vw,3.5rem)] min-[1180px]:grid-cols-1 min-[1180px]:gap-[clamp(1.75rem,2.75vw,2.35rem)]"
            aria-label="Contact details"
          >
            {contactMethods.map((method) => (
              <article key={method.label} className="min-[1180px]:max-w-[25rem]">
                <div className="flex items-center gap-[0.85rem] min-[1180px]:items-center">
                  <span
                    className="inline-flex h-[2.95rem] w-[2.95rem] flex-none items-center justify-center rounded-[0.85rem] border border-[#dceafe] bg-white text-[#10202e] shadow-[0_1px_0_rgba(7,17,22,0.03)]"
                    aria-hidden="true"
                  >
                    {method.iconSrc ? (
                      <img src={method.iconSrc} alt="" className="h-1/2 w-3/4 object-contain" />
                    ) : method.icon ? (
                      <Icon icon={method.icon} className="h-[1.3rem] w-[1.3rem]" />
                    ) : null}
                  </span>
                  <h2 className="type-card-title m-0 text-[#071116]">{method.label}</h2>
                </div>
                {method.protectedContact ? (
                  <ProtectedContactValue
                    available={method.protectedContact.available}
                    field={method.protectedContact.field}
                    label={method.label}
                    resourceType={method.protectedContact.resourceType}
                    resourceId={method.protectedContact.resourceId}
                    className="mt-[0.8rem] max-w-full min-[1180px]:max-w-[23rem]"
                  />
                ) : (
                  <AppLink
                    href={method.href ?? '#'}
                    className="mt-[0.8rem] inline-block max-w-full text-[1rem] font-medium leading-[1.35] text-[#4e5d72] no-underline transition-[color,transform] duration-150 hover:text-primary-600 focus-visible:text-primary-600 focus-visible:outline-none min-[761px]:max-w-full min-[1180px]:max-w-[23rem] min-[1180px]:text-[clamp(1rem,1.18vw,1.16rem)]"
                    target={method.target}
                    rel={method.rel}
                    ariaLabel={`${method.label}: ${method.valueLines.join(', ')}`}
                  >
                    {method.valueLines.map((line) => (
                      <span key={line} className="block">
                        {line}
                      </span>
                    ))}
                  </AppLink>
                )}
              </article>
            ))}
          </div>
        </section>

        <section
          className="relative z-[2] w-full rounded-[1.55rem] bg-white px-[1.45rem] pb-[1.55rem] pt-[1.8rem] shadow-[0_0_0_1px_rgba(7,17,22,0.02)] md:rounded-[1.8rem] md:p-6 min-[761px]:p-6 min-[1180px]:rounded-[clamp(1.55rem,2.5vw,2rem)] min-[1180px]:px-[clamp(1.45rem,2.7vw,2rem)] min-[1180px]:pb-[clamp(1.55rem,2.75vw,1.95rem)] min-[1180px]:pt-[clamp(1.8rem,3vw,2.4rem)]"
          aria-label="Send us a message"
        >
          <img
            className="pointer-events-none absolute left-[clamp(-6rem,-6vw,-4.25rem)] top-[clamp(-3.8rem,-5vw,-2.65rem)] z-[1] hidden h-auto w-[clamp(8.5rem,11vw,10.5rem)] select-none min-[1180px]:block"
            src="/contact_arrow.png"
            alt=""
            aria-hidden="true"
          />
          <form className="flex flex-col" onSubmit={handleSubmit}>
            <div className="grid grid-cols-1 gap-x-[1.25rem] gap-y-[1.35rem] md:grid-cols-2 md:gap-x-[1.5rem] md:gap-y-[1.5rem] min-[1180px]:gap-x-[clamp(1.25rem,2.2vw,1.85rem)] min-[1180px]:gap-y-[clamp(1.2rem,2vw,1.65rem)]">
              <BaseInput
                value={form.firstName}
                onChange={(event) => handleFieldChange('firstName', event.target.value)}
                label="First Name"
                name="firstName"
                placeholder="Enter your first name"
                autoComplete="given-name"
                error={fieldErrors.firstName}
                labelClassName={contactFieldLabelClassName}
                controlClassName={contactInputControlClassName}
                inputClassName={contactInputClassName}
                required
                disabled={isSubmitting}
              />
              <BaseInput
                value={form.lastName}
                onChange={(event) => handleFieldChange('lastName', event.target.value)}
                label="Last Name"
                name="lastName"
                placeholder="Enter your last name"
                autoComplete="family-name"
                error={fieldErrors.lastName}
                labelClassName={contactFieldLabelClassName}
                controlClassName={contactInputControlClassName}
                inputClassName={contactInputClassName}
                required
                disabled={isSubmitting}
              />
              <BaseInput
                className="md:col-span-2"
                value={form.email}
                onChange={(event) => handleFieldChange('email', event.target.value)}
                label="Email Address"
                name="email"
                type="email"
                placeholder="Enter your email address"
                autoComplete="email"
                error={fieldErrors.email}
                labelClassName={contactFieldLabelClassName}
                controlClassName={contactInputControlClassName}
                inputClassName={contactInputClassName}
                required
                disabled={isSubmitting}
              />
              <TextareaInput
                className="md:col-span-2"
                value={form.message}
                onChange={(event) => handleFieldChange('message', event.target.value)}
                label="How can we help you?"
                name="message"
                placeholder="Write your message here..."
                rows={6}
                error={fieldErrors.message}
                labelClassName={contactFieldLabelClassName}
                textareaClassName={contactTextareaClassName}
                required
                disabled={isSubmitting}
              />
            </div>

            {formError && (
              <div className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{formError}</div>
            )}

            <Button
              type="submit"
              size="lg"
              className="type-button mt-[1.55rem] min-h-[2.9rem] min-w-[min(11.75rem,100%)] self-start rounded-full border-0 bg-primary-500 px-6 font-[inherit] text-white shadow-[0_18px_30px_rgb(var(--color-primary-500)/0.16)] hover:bg-primary-600 max-md:w-full"
              disabled={isSubmitting || isLoading}
            >
              Send message
            </Button>
          </form>
        </section>
      </div>
    </main>
  );
}
