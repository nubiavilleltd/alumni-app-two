import React from 'react';

import { SEO } from '@/shared/common/SEO';
import { useSubmitContactForm } from '@/features/contactUs/hooks/useContactUs';
import { getSiteConfig } from '@/data/content';
import { ContactPageLayout } from '../components/ContactPageLayout';
import { toGoogleMapsHref, toTelephoneHref } from '../utils';

export function WelfareCommitteeContactPage() {
  const config = getSiteConfig();
  const contactConfig = config.contact ?? {};

  const submitContactForm = useSubmitContactForm();

  const address = String(contactConfig.address ?? 'Lagos, Nigeria').trim();
  const phone = String(contactConfig.phone ?? '+234 800 000 0000').trim();
  const email = String(contactConfig.email ?? 'info@alumniportal.org').trim();

  const contactMethods = [
    {
      label: 'Find us',
      valueLines: [address],
      iconSrc: '/contactLocation.svg',
      href: toGoogleMapsHref([address]),
      target: '_blank' as const,
      rel: 'noreferrer',
    },
    {
      label: 'Call us',
      valueLines: [phone],
      iconSrc: '/contactPhone.svg',
      href: toTelephoneHref(phone),
    },
    {
      label: 'Email us',
      valueLines: [email],
      iconSrc: '/contactMessage.svg',
      href: `mailto:${email}`,
    },
  ];

  return (
    <>
      <SEO
        title="Contact Us"
        description="Get in touch with the Alumni Portal for membership, events, and website support."
      />

      <ContactPageLayout
        title="Get in touch with us"
        description="Have questions about membership, events, or the website? We're here to help."
        contactMethods={contactMethods}
        onSubmit={async (form) => {
          await submitContactForm.mutateAsync(form);
        }}
        isSubmitting={submitContactForm.isPending}
      />
    </>
  );
}
