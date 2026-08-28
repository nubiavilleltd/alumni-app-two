// // features/user/components/ui/ProfileInfoPanel.tsx
// //
// // Shared right-column content used by both UserProfilePage and AlumniProfilePage.
// // Renders About Me, Bio fields, Address, Professional Info sections.
// // 'copyable' prop adds copy-to-clipboard on contact fields (AlumniProfilePage).
// // 'owner' prop controls which fields are visible.

// import { useState } from 'react';
// import { Icon } from '@iconify/react';
// import { toast } from '@/shared/components/ui/Toast';

// // ─── Types ────────────────────────────────────────────────────────────────────

// export interface ProfileInfoData {
//   // About
//   bio?: string;

//   // Bio section
//   fullName?: string;
//   maidenName?: string;
//   nicknameInSchool?: string;
//   email?: string;
//   whatsapp?: string;
//   altPhone?: string;
//   dateOfBirth?: string;

//   // Address
//   streetAddress?: string;
//   area?: string;
//   state?: string;
//   city?: string;

//   // Professional
//   employmentStatus?: string;
//   occupation?: string;
//   industrySector?: string;
//   yearsOfExperience?: string | number;

//   // Social (shown as icon row in sidebar, but also accessible here)
//   linkedin?: string;
//   twitter?: string;
//   instagram?: string;
//   facebook?: string;
//   tiktok?: string;
// }

// interface ProfileInfoPanelProps {
//   data: ProfileInfoData;
//   /** If true, shows copy buttons next to contact fields */
//   copyable?: boolean;
//   /** If false (viewing another person's profile), some fields may be hidden */
//   isOwner?: boolean;
// }

// // ─── Copy button ──────────────────────────────────────────────────────────────

// function CopyButton({ value }: { value: string }) {
//   const [copied, setCopied] = useState(false);

//   const handleCopy = async () => {
//     try {
//       await navigator.clipboard.writeText(value);
//       setCopied(true);
//       setTimeout(() => setCopied(false), 2000);
//     } catch {
//       toast.error('Could not copy to clipboard');
//     }
//   };

//   return (
//     <button
//       type="button"
//       onClick={handleCopy}
//       title="Copy to clipboard"
//       className="text-primary-400 hover:text-primary-600 transition-colors flex-shrink-0"
//     >
//       <Icon icon={copied ? 'mdi:check' : 'mdi:content-copy'} className="w-4 h-4" />
//     </button>
//   );
// }

// // ─── Section container ────────────────────────────────────────────────────────

// function Section({ title, children }: { title: string; children: React.ReactNode }) {
//   return (
//     <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
//       <h2 className="text-sm font-bold text-gray-900 mb-4">{title}</h2>
//       {children}
//     </div>
//   );
// }

// // ─── Field row ────────────────────────────────────────────────────────────────

// function FieldRow({
//   label,
//   value,
//   copyable,
// }: {
//   label: string;
//   value?: string | number;
//   copyable?: boolean;
// }) {
//   if (value === undefined || value === null || value === '') return null;

//   return (
//     <div className="flex flex-col sm:grid sm:grid-cols-[180px_1fr] gap-x-4 gap-y-0.5 py-2.5 border-b border-gray-50 last:border-0">
//       <span className="text-xs sm:text-sm text-gray-400 sm:text-gray-500 shrink-0">{label}:</span>
//       <div className="flex items-center gap-2 min-w-0">
//         <span className="text-sm text-gray-700 break-all sm:break-words">{value}</span>
//         {copyable && typeof value === 'string' && <CopyButton value={value} />}
//       </div>
//     </div>
//   );
// }

// // ─── Main panel ───────────────────────────────────────────────────────────────

// export function ProfileInfoPanel({
//   data,
//   copyable = false,
//   isOwner = false,
// }: ProfileInfoPanelProps) {
//   const hasBioFields =
//     data.fullName ||
//     data.maidenName ||
//     data.nicknameInSchool ||
//     data.email ||
//     data.whatsapp ||
//     data.altPhone ||
//     data.dateOfBirth;

//   const hasAddress = data.streetAddress || data.state || data.city;

//   const hasProfessional =
//     data.employmentStatus || data.occupation || data.industrySector || data.yearsOfExperience;

//   return (
//     <div className="space-y-4">
//       {/* About Me */}
//       {data.bio && (
//         <Section title="About Me">
//           <p className="text-sm text-gray-600 leading-relaxed">{data.bio}</p>
//         </Section>
//       )}

//       {/* Bio */}
//       {hasBioFields && (
//         <Section title="Bio">
//           <FieldRow label="Full Name" value={data.fullName} />
//           <FieldRow label="Former Name" value={data.maidenName} />
//           <FieldRow label="Preferred Nickname" value={data.nicknameInSchool} />
//           <FieldRow label="Email" value={data.email} copyable={copyable} />
//           <FieldRow label="WhatsApp" value={data.whatsapp} copyable={copyable} />
//           <FieldRow label="Alt. Phone" value={data.altPhone} copyable={copyable} />
//           <FieldRow label="Date of Birth" value={data.dateOfBirth} />
//         </Section>
//       )}

//       {/* Address — only shown if we have data */}
//       {/* {hasAddress && (
//         <Section title="Address">
//           <FieldRow label="Street Number and Name" value={data.streetAddress} />
//           <FieldRow label="Area"                   value={data.area} />
//           <FieldRow label="City"                   value={data.city} />
//         </Section>
//       )} */}

//       {/* Professional Info */}
//       {hasProfessional && (
//         <Section title="Professional Info">
//           <FieldRow label="Employment Status" value={data.employmentStatus} />
//           <FieldRow label="Occupation" value={data.occupation} />
//           <FieldRow label="Industry Sector" value={data.industrySector} />
//           <FieldRow label="Years of Experience" value={data.yearsOfExperience} />
//         </Section>
//       )}
//     </div>
//   );
// }

// features/user/components/ui/ProfileInfoPanel.tsx

import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { toast } from '@/shared/components/ui/Toast';
import CopyButton from '@/shared/components/ui/CopyButton';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface ProfileInfoData {
  bio?: string;
  fullName?: string;
  maidenName?: string;
  nicknameInSchool?: string;
  email?: string;
  whatsapp?: string;
  altPhone?: string;
  dateOfBirth?: string;
  streetAddress?: string;
  area?: string;
  state?: string;
  city?: string;
  zone?: string;
  employmentStatus?: string;
  occupation?: string;
  industrySector?: string;
  yearsOfExperience?: string | number;
  instagram?: string;
  facebook?: string;
  twitter?: string;
  tiktok?: string;
  linkedin?: string;
}

interface ProfileInfoPanelProps {
  data: ProfileInfoData;
  copyable?: boolean;
  isOwner?: boolean;
}

// ─── Copy button ──────────────────────────────────────────────────────────────



// ─── Section ──────────────────────────────────────────────────────────────────

function Section({
  title,
  className = '',
  children,
}: {
  title: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div
      className={`bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 ${className}`}
    >
      <h2 className="text-sm font-bold text-gray-900 mb-4">{title}</h2>
      {children}
    </div>
  );
}

// ─── Field row ────────────────────────────────────────────────────────────────
//
// value === undefined  → hide the row entirely (non-owner + private/missing)
// value === ''         → show label with empty slot (owner sees unfilled fields)
// value = string       → show label + value normally

function FieldRow({
  label,
  value,
  copyable,
}: {
  label: string;
  value?: string | number;
  copyable?: boolean;
}) {
  // Hide entirely
  if (value === undefined || value === null) return null;

  const isEmpty = value === '';

  return (
    <div className="flex flex-col sm:grid sm:grid-cols-[180px_1fr] gap-x-4 gap-y-0.5 py-2.5 border-b border-gray-50 last:border-0">
      <span className="text-xs sm:text-sm text-gray-400 sm:text-gray-500 shrink-0">{label}:</span>
      <div className="flex items-center gap-2 min-w-0">
        {isEmpty ? (
          <span className="text-sm text-gray-300 italic">Not provided</span>
        ) : (
          <>
            <span className="text-sm text-gray-700 break-all sm:break-words">{value}</span>
            {copyable && typeof value === 'string' && <CopyButton value={value} />}
          </>
        )}
      </div>
    </div>
  );
}

// ─── Section visibility helpers ───────────────────────────────────────────────
//
// A section renders if at least one field is NOT undefined.
// (Empty string '' counts as visible — it's an owner placeholder.)

function hasAny(...values: (string | number | undefined)[]): boolean {
  return values.some((v) => v !== undefined);
}

// ─── Main panel ───────────────────────────────────────────────────────────────

export function ProfileInfoPanel({
  data,
  copyable = false,
  isOwner = false,
}: ProfileInfoPanelProps) {
  const hasBioFields = hasAny(
    data.fullName,
    data.maidenName,
    data.nicknameInSchool,
    data.email,
    data.whatsapp,
    data.altPhone,
    data.dateOfBirth,
  );

  const hasAddress = hasAny(data.streetAddress, data.area, data.state, data.city, data.zone);

  const hasProfessional = hasAny(
    data.employmentStatus,
    data.occupation,
    data.industrySector,
    data.yearsOfExperience,
  );

  const hasSocials = hasAny(
    data.instagram,
    data.facebook,
    data.twitter,
    data.tiktok,
    data.linkedin,
    data.email,
  );

  return (
    <div className="space-y-4">
      <Section title="About Me">
        {data.bio ? (
          <p className="text-sm text-gray-600 leading-relaxed">{data.bio}</p>
        ) : (
          <span className="text-sm text-gray-300 italic">Not provided</span>
        )}
      </Section>

      {/* Bio */}
      {hasBioFields && (
        <Section title="Bio">
          <FieldRow label="Full Name" value={data.fullName} />
          <FieldRow label="Former Name" value={data.maidenName} />
          <FieldRow label="Preferred Nickname" value={data.nicknameInSchool} />
          {hasSocials && <FieldRow label="Email" value={data.email} copyable={true} />}
          <FieldRow label="WhatsApp" value={data.whatsapp} copyable={true} />
          <FieldRow label="Alt. Phone" value={data.altPhone} copyable={true} />
          <FieldRow label="Date of Birth" value={data.dateOfBirth} />
        </Section>
      )}

      {/* Address */}
      {hasAddress && (
        <Section className="block lg:hidden" title="Address">
          <FieldRow label="Street" value={data.streetAddress} />
          <FieldRow label="Area" value={data.area} />
          {/* <FieldRow label="State" value={data.state} /> */}
          <FieldRow label="City" value={data.city} />
          <FieldRow label="Zone" value={data.zone} />
        </Section>
      )}

      {/* Professional Info */}
      {hasProfessional && (
        <Section title="Professional Info">
          <FieldRow label="Employment Status" value={data.employmentStatus} />
          <FieldRow label="Occupation" value={data.occupation} />
          <FieldRow label="Industry Sector" value={data.industrySector} />
          <FieldRow label="Years of Experience" value={data.yearsOfExperience} />
        </Section>
      )}

      {/* Socials */}
      {hasSocials && (
        <Section title="Social Links">
          <FieldRow label="Instagram" value={data.instagram} copyable={true} />
          <FieldRow label="Facebook" value={data.facebook} copyable={true} />
          <FieldRow label="X (Twitter)" value={data.twitter} copyable={true} />
          <FieldRow label="TikTok" value={data.tiktok} copyable={true} />
          <FieldRow label="LinkedIn" value={data.linkedin} copyable={true} />
        </Section>
      )}

      {/* Owner hint */}
      {isOwner && (
        <p className="text-xs text-gray-400 text-center">
          Fields marked "Not provided" are visible only to you. Others cannot see empty fields.
        </p>
      )}
    </div>
  );
}
