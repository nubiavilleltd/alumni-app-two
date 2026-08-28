// features/user/components/ui/ProfileCard.tsx
//
// Shared sidebar card used by both UserProfilePage and AlumniProfilePage.
// Renders avatar, name, class year, role/position, location, socials,
// and conditionally the Edit Profile button (owner) or Message button (visitor).

import { AppLink } from '@/shared/components/ui/AppLink';
import { USER_ROUTES } from '@/features/user/routes';
import { HandHelping, MapPin, MessageCircleMore, Share2 } from 'lucide-react';
import { renderIcon, type AppIcon } from '@/shared/utils/renderIcon';

export type SocialLink = {
  icon: AppIcon;
  href: string;
  label: string;
};

interface ProfileCardProps {
  photo?: string;
  fullName: string;
  maidenName?: string;
  graduationYear?: number;
  positionLine?: string; // e.g. "Fashion Designer at Tara House"
  city?: string;
  isVolunteer?: boolean;
  socials?: SocialLink[];

  // Mode: 'owner' shows Edit Profile, 'visitor' shows Message, 'public' shows nothing
  mode: 'owner' | 'visitor' | 'public';

  // visitor mode
  onMessage?: () => void;
  isMessaging?: boolean;

  // share button
  onShare?: () => void;
}

function Avatar({
  photo,
  fullName,
  mode,
}: {
  photo?: string;
  fullName: string;
  mode: 'owner' | 'visitor' | 'public';
}) {
  const initials = fullName
    .split(' ')
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase();

  return (
    <div className="relative mx-auto mb-4">
      <div className="w-28 h-28 sm:w-32 sm:h-32 rounded-full overflow-hidden border-4 border-white shadow-md bg-primary-50 flex items-center justify-center">
        {photo ? (
          <img src={photo} alt={fullName} className="w-full h-full object-cover" />
        ) : (
          <span className="text-3xl font-bold text-primary-400">{initials || '?'}</span>
        )}
      </div>
      {/* Edit photo overlay — owner only */}
      {/* {mode === 'owner' && (
        <AppLink
          href={USER_ROUTES.EDIT_PROFILE}
          className="absolute bottom-0.5 right-0.5 w-8 h-8 rounded-full bg-primary-500 hover:bg-primary-600 text-white flex items-center justify-center shadow-md transition-colors"
        //   title="Edit profile photo"
        >
          <Icon icon="mdi:pencil" className="w-4 h-4" />
        </AppLink>
      )} */}
    </div>
  );
}

export function ProfileCard({
  photo,
  fullName,
  maidenName,
  graduationYear,
  positionLine,
  city,
  isVolunteer,
  socials = [],
  mode,
  onMessage,
  isMessaging,
  onShare,
}: ProfileCardProps) {
  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 flex flex-col items-center text-center">
      {/* Top row: share + volunteer */}
      <div className="w-full flex items-center justify-between mb-3">
        <button
          type="button"
          onClick={onShare}
          className="w-8 h-8 flex items-center border border-primary-200 justify-center rounded-full hover:bg-primary-100 text-primary-500 hover:text-primary-600 transition-colors"
          title="Share profile"
        >
          {/* <Icon icon="mdi:share-variant-outline" className="w-4 h-4" /> */}
          <Share2 size={20} />
        </button>

        {isVolunteer ? (
          <div className="inline-flex items-center gap-1.5 bg-primary-50 border border-primary-100 rounded-lg px-3 py-1 text-xs font-semibold text-primary-600">
            {/* <Icon icon="mdi:hand-helping-outline" className="w-3.5 h-3.5" /> */}
            <HandHelping size={17} />
            Volunteer
          </div>
        ) : (
          <span />
        )}
      </div>

      {/* Avatar */}
      <Avatar photo={photo} fullName={fullName} mode={mode} />

      {/* Name */}
      <h1 className="text-base sm:text-lg font-bold text-gray-900 leading-snug">{fullName}</h1>

      {maidenName && <p className="text-xs text-gray-400 mt-0.5">formerly {maidenName}</p>}

      {graduationYear && (
        <p className="text-sm text-gray-500 mt-0.5">Class '{String(graduationYear).slice(-2)}</p>
      )}

      {positionLine && <p className="text-xs text-gray-500 mt-1 leading-snug">{positionLine}</p>}

      {city && (
        <p className="text-xs text-gray-400 mt-1 flex items-center gap-1">
          <MapPin className="w-3.5 h-3.5 flex-shrink-0" strokeWidth={2.4} />
          {city}
        </p>
      )}

      {/* Social icons */}
      {socials.length > 0 && (
        <div className="flex items-center gap-4 mt-4">
          {socials.map((s) => (
            <a
              key={s.label}
              href={s.href}
              target="_blank"
              rel="noopener noreferrer"
              title={s.label}
              className="text-gray-600 hover:text-gray-600 transition-colors"
            >
              {renderIcon(s.icon, 'w-5 h-5')}
            </a>
          ))}
        </div>
      )}

      {/* CTA */}
      {mode === 'owner' && (
        <AppLink
          href={USER_ROUTES.EDIT_PROFILE}
          className="mt-5 w-full inline-flex items-center justify-center gap-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold py-2.5 rounded-3xl transition-colors"
        >
          Edit Profile
        </AppLink>
      )}

      {(mode === 'visitor' || mode === 'public') && onMessage && (
        <button
          type="button"
          onClick={onMessage}
          disabled={isMessaging}
          className="mt-5 w-full inline-flex items-center justify-center gap-2 bg-primary-500 hover:bg-primary-600 disabled:bg-primary-200 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors"
        >
          {/* <Icon
              icon={isMessaging ? 'mdi:loading' : 'mdi:message-outline'}
              className={`w-4 h-4 ${isMessaging ? 'animate-spin' : ''}`}
            /> */}
          <MessageCircleMore size={20} />

          {isMessaging ? 'Opening...' : 'Send Message'}
        </button>
      )}
    </div>
  );
}
