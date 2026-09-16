import { Check, Copy, Mail, MessageCircle, Phone } from 'lucide-react';
import { useState } from 'react';
import { fetchProtectedContact } from '@/shared/api/protectedContactApi';
import type { ProtectedContactField } from '@/shared/api/protectedContactApi';
import { toast } from './Toast';

type ProtectedContactValueProps = {
  value?: string | null;
  available?: boolean;
  field: ProtectedContactField;
  label: string;
  resourceType: string;
  resourceId: string;
  variant?: 'contact' | 'profile';
  tone?: 'default' | 'light';
  className?: string;
};

type ProtectedContactAvailabilityProps = {
  phone?: boolean;
  email?: boolean;
  whatsapp?: boolean;
  label?: string;
  className?: string;
};

function contactIcon(field: ProtectedContactField) {
  if (field === 'email') return Mail;
  if (field === 'whatsapp') return MessageCircle;
  return Phone;
}

function maskedContactValue(field: ProtectedContactField) {
  return field === 'email' ? '••••••••@••••••' : '••••••••••••';
}

/**
 * Displays a masked contact value and retrieves the raw value only when the
 * user explicitly requests a copy. The raw value is never rendered into the
 * page markup.
 */
export function ProtectedContactValue({
  value,
  available,
  field,
  label,
  resourceType,
  resourceId,
  variant = 'contact',
  tone = 'default',
  className = '',
}: ProtectedContactValueProps) {
  const [isOpening, setIsOpening] = useState(false);
  const [isCopying, setIsCopying] = useState(false);
  const [isCopied, setIsCopied] = useState(false);
  const Icon = contactIcon(field);

  const isAvailable = available ?? Boolean(value?.trim());
  if (!isAvailable) return null;

  const resolveContactValue = () =>
    fetchProtectedContact({
      resourceType,
      resourceId,
      field,
      fallbackValue: value,
    });

  const handleOpen = async () => {
    setIsOpening(true);

    try {
      const contactValue = await resolveContactValue();

      if (!contactValue) {
        throw new Error('Protected contact is unavailable');
      }

      if (field === 'email') {
        window.location.assign(`mailto:${contactValue}`);
      } else if (field === 'whatsapp') {
        window.open(`https://wa.me/${contactValue.replace(/\D/g, '')}`, '_blank', 'noopener,noreferrer');
      } else {
        window.location.assign(`tel:${contactValue.replace(/\s+/g, '')}`);
      }
    } catch {
      toast.error(`Unable to open the ${label.toLowerCase()} right now.`);
    } finally {
      setIsOpening(false);
    }
  };

  const handleCopy = async () => {
    setIsCopying(true);
    setIsCopied(false);

    try {
      const contactValue = await resolveContactValue();

      if (!contactValue || !navigator.clipboard) {
        throw new Error('Protected contact is unavailable');
      }

      await navigator.clipboard.writeText(contactValue);
      setIsCopied(true);
      toast.success(`${label} copied`);
      window.setTimeout(() => setIsCopied(false), 2000);
    } catch {
      toast.error(`Unable to copy the ${label.toLowerCase()} right now.`);
    } finally {
      setIsCopying(false);
    }
  };

  if (variant === 'profile') {
    return (
      <div
        className={`flex w-full flex-col gap-x-4 gap-y-0.5 py-2.5 text-left sm:grid sm:grid-cols-[180px_1fr] ${className}`}
      >
        <span className="text-xs text-gray-400 sm:text-sm sm:text-gray-500">{label}:</span>
        <span className="flex min-w-0 items-center gap-2">
          <button
            type="button"
            onClick={() => {
              void handleOpen();
            }}
            disabled={isOpening}
            className="flex min-w-0 flex-1 items-center gap-2 text-left transition-colors hover:text-primary-600 disabled:cursor-wait disabled:opacity-70"
            aria-label={`Open ${label}`}
            title={`Open ${label}`}
          >
            <Icon className="h-4 w-4 flex-shrink-0 text-gray-400" aria-hidden="true" />
            <span className="min-w-0 text-sm text-gray-700">{maskedContactValue(field)}</span>
          </button>
          <button
            type="button"
            onClick={() => {
              void handleCopy();
            }}
            disabled={isCopying}
            className="flex-shrink-0 text-primary-500 transition-colors hover:text-primary-600 disabled:cursor-wait disabled:opacity-70"
            aria-label={`Copy ${label}`}
            title={`Copy ${label}`}
          >
            {isCopied ? (
              <Check className="h-4 w-4" aria-hidden="true" />
            ) : (
              <Copy className="h-4 w-4" aria-hidden="true" />
            )}
          </button>
        </span>
      </div>
    );
  }

  const toneClasses =
    tone === 'light'
      ? {
          text: 'text-white',
          icon: 'text-white/75',
          action: 'text-white',
        }
      : {
          text: 'text-[#4e5d72]',
          icon: 'text-[#697586]',
          action: 'text-primary-500',
        };

  return (
    <div className={`inline-flex min-w-0 items-center gap-2.5 ${className}`}>
      <button
        type="button"
        onClick={() => {
          void handleOpen();
        }}
        disabled={isOpening}
        className={`inline-flex min-w-0 flex-1 items-center gap-2.5 text-left text-[1rem] font-medium leading-[1.35] ${toneClasses.text} transition-colors hover:text-primary-600 disabled:cursor-wait disabled:opacity-70`}
        aria-label={`Open ${label}`}
        title={`Open ${label}`}
      >
        <Icon className={`h-4 w-4 flex-shrink-0 ${toneClasses.icon}`} aria-hidden="true" />
        <span className="min-w-0 truncate">{maskedContactValue(field)}</span>
      </button>
      <button
        type="button"
        onClick={() => {
          void handleCopy();
        }}
        disabled={isCopying}
        className={`flex-shrink-0 transition-colors hover:text-primary-600 disabled:cursor-wait disabled:opacity-70 ${toneClasses.action}`}
        aria-label={`Copy ${label}`}
        title={`Copy ${label}`}
      >
        {isCopied ? (
          <Check className="h-4 w-4" aria-hidden="true" />
        ) : (
          <Copy className="h-4 w-4" aria-hidden="true" />
        )}
      </button>
    </div>
  );
}

/** Shows only which contact channels exist, without rendering their values. */
export function ProtectedContactAvailability({
  phone = false,
  email = false,
  whatsapp = false,
  label = 'Protected contact details',
  className = '',
}: ProtectedContactAvailabilityProps) {
  const fields = [
    phone && { field: 'phone' as const, label: 'Phone number' },
    email && { field: 'email' as const, label: 'Email address' },
    whatsapp && { field: 'whatsapp' as const, label: 'WhatsApp number' },
  ].filter(Boolean) as Array<{ field: ProtectedContactField; label: string }>;

  if (fields.length === 0) return null;

  return (
    <div className={`flex flex-wrap items-center gap-2 ${className}`} aria-label={label}>
      {fields.map(({ field, label: fieldLabel }) => {
        const Icon = contactIcon(field);

        return (
          <span
            key={field}
            className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#f1f3f5] text-[#697586]"
            title={`${fieldLabel} available`}
            aria-label={`${fieldLabel} available`}
          >
            <Icon className="h-4 w-4" aria-hidden="true" />
          </span>
        );
      })}
    </div>
  );
}
