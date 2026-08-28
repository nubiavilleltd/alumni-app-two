// features/donation/pages/DonationPage.tsx

import { toast } from '@/shared/components/ui/Toast';
import { Icon } from '@iconify/react';
import { useState } from 'react';
import DonationImage from '/donation-image.png';
import { useLocation } from 'react-router-dom';

export const BANK_DETAILS = {
  bankName: 'Access Bank',
  accountNumber: '0722817496',
  accountName: 'Alumni Portal Association',
};

export function DonationPage() {
  const copyToClipboard = async (value: string, field: string) => {
    try {
      await navigator.clipboard.writeText(value);
      toast.success('Copied successfully');
    } catch (err) {
      toast.error('Failed to copy');
      console.error('Copy failed', err);
    }
  };



  return (
    <section className="page-inline-padding mx-auto flex flex-col bg-[#F8F8F7] py-6">
      {/* Title */}

      {/* <div className="grid lg:grid-cols-3 gap-10 items-center"> */}
      <h1 className="type-section-title text-gray-900">Make a Donation</h1>
      <div className="flex flex-col md:flex-row gap-5 justify-between">
        {/* LEFT CARD */}

        <div className="flex-1 flex flex-col py-8 px-6 sm:px-10 bg-white rounded-2xl shadow-sm border border-gray-100 max-w-3xl mt-6">
          <p className="text-gray-600 text-base sm:text-lg leading-relaxed mb-10">
            Give back and help create opportunities that benefit alumni and the wider community.
            Your contribution helps support members, fund initiatives, and strengthen our community.
          </p>

          {/* Bank Name */}
          <div className="mb-8">
            <p className="text-sm text-gray-600 font-normal mb-1.5">Bank Name</p>
            <p className="text-lg sm:text-2xl font-semibold text-gray-700 tracking-tight">
              {BANK_DETAILS.bankName}
            </p>
          </div>

          {/* Account Number */}
          <div className="mb-8">
            <p className="text-sm text-gray-600 font-normal mb-1.5">Account Number</p>
            <div className="flex items-center gap-4">
              <p className="text-lg sm:text-2xl font-semibold text-gray-900 tracking-tight">
                {BANK_DETAILS.accountNumber}
              </p>
              <button
                onClick={() => copyToClipboard(BANK_DETAILS.accountNumber, 'account')}
                className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
                aria-label="Copy account number"
              >
                <Icon icon="mdi:content-copy" className="w-5 h-5 text-primary-500" />
              </button>
            </div>
          </div>

          {/* Account Name */}
          <div>
            <p className="text-sm text-gray-600 font-normal mb-1.5">Account Name</p>
            <p className="text-lg sm:text-2xl font-semibold text-gray-900 tracking-tight">
              {BANK_DETAILS.accountName}
            </p>
          </div>
        </div>

        {/* RIGHT IMAGE */}
        <div className="flex justify-center lg:justify-end">
          <img
            src={DonationImage}
            alt="Donation Illustration"
            className="w-[260px] sm:w-[320px] lg:w-[360px] h-auto"
          />
        </div>
      </div>
    </section>
  );
}
