import { AtSign, BriefcaseBusiness, Camera, CircleAlert, Music2, UserX, Users } from 'lucide-react';
import { useParams } from 'react-router-dom';
import { useAlumnus } from '@/features/alumni/hooks/useAlumni';
import { AppLink } from '@/shared/components/ui/AppLink';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import { SEO } from '@/shared/common/SEO';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';
import { occupationOptions } from '@/features/authentication/constants/profileOptions';
import { ALUMNI_ROUTES } from '../routes';
import { ROUTES } from '@/shared/constants/routes';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { ProfileCard, type SocialLink } from '@/features/user/components/ui/ProfileCard';
import { ProfileInfoPanel } from '@/features/user/components/ui/ProfileInfoPanel';
import { ProfileSkeleton } from '@/features/user/components/ProfileSkeleton';
import {
  buildProfileData,
  isGroupVisible,
  resolveLabel,
  resolveProfilePhoto,
} from '@/features/user/utils/profileUtils';
import { toast } from '@/shared/components/ui/Toast';
import { ProfileAddressCard } from '@/features/user/components/ui/ProfileAddressCard';

// ─── Error / empty states ─────────────────────────────────────────────────────

function ProfileError({ onRetry }: { onRetry: () => void }) {
  return (
    <section className="section">
      <div className="container-custom text-center py-12">
        <CircleAlert className="mx-auto mb-4 h-16 w-16 text-red-400" strokeWidth={2.2} />
        <h1 className="text-2xl font-bold mb-3">Something went wrong</h1>
        <p className="text-gray-500 mb-6">There was an error loading this profile.</p>
        <div className="flex gap-3 justify-center">
          <AppLink href={ALUMNI_ROUTES.PROFILES} className="btn btn-primary">
            Browse Directory
          </AppLink>
          <button onClick={onRetry} className="btn btn-outline">
            Try Again
          </button>
        </div>
      </div>
    </section>
  );
}

function ProfileNotFound() {
  return (
    <section className="section">
      <div className="container-custom text-center py-12">
        <UserX className="mx-auto mb-4 h-16 w-16 text-gray-400" strokeWidth={2.2} />
        <h1 className="text-2xl font-bold mb-3">Profile not found</h1>
        <p className="text-gray-500 mb-6">This profile doesn't exist or has been removed.</p>
        <AppLink href={ALUMNI_ROUTES.PROFILES} className="btn btn-primary">
          Browse Directory
        </AppLink>
      </div>
    </section>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function AlumniProfilePage() {
  const { slug = '' } = useParams();
  const { data: currentUser, isLoading: isLoadingProfile } = useCurrentUser();
  const { startDirectConversation, isPending: isStartingConversation } =
    useStartDirectConversation();
  const { data: alumnus, isLoading, error } = useAlumnus(slug);

  if (isLoading || isLoadingProfile) {
    return (
      <section className="section bg-[#F8F8F7] py-8">
        <div className="container-custom">
          <ProfileSkeleton />
        </div>
      </section>
    );
  }

  if (error) return <ProfileError onRetry={() => window.location.reload()} />;
  if (!alumnus) return <ProfileNotFound />;

  const isOwner = currentUser?.memberId === alumnus.memberId;
  const isSignedIn = !!currentUser;

  // Build privacy-aware profile data
  const profileData = buildProfileData({ ...alumnus, fullName: alumnus.name }, isOwner, isSignedIn);

  // Privacy group checks for the sidebar
  const canSeeAddress = isGroupVisible('residentialAddress', alumnus.privacy, isOwner, isSignedIn);
  const canSeeProfessional = isGroupVisible(
    'employmentStatus',
    alumnus.privacy,
    isOwner,
    isSignedIn,
  );
  const canSeeSocials = isGroupVisible('socials', alumnus.privacy, isOwner, isSignedIn);
  const profilePhoto = resolveProfilePhoto({
    photoUrl: alumnus.photo,
    privacy: alumnus.privacy,
    isOwner,
    isSignedIn,
  });

  // Socials for the ProfileCard icon row
  const socials: SocialLink[] = canSeeSocials
    ? ([
        alumnus.instagram && {
          icon: <Camera strokeWidth={2.2} />,
          href: alumnus.instagram,
          label: 'Instagram',
        },
        alumnus.facebook && {
          icon: <Users strokeWidth={2.2} />,
          href: alumnus.facebook,
          label: 'Facebook',
        },
        alumnus.twitter && {
          icon: <AtSign strokeWidth={2.2} />,
          href: alumnus.twitter,
          label: 'X (Twitter)',
        },
        alumnus.tiktok && {
          icon: <Music2 strokeWidth={2.2} />,
          href: alumnus.tiktok,
          label: 'TikTok',
        },
        alumnus.linkedin && {
          icon: <BriefcaseBusiness strokeWidth={2.2} />,
          href: alumnus.linkedin,
          label: 'LinkedIn',
        },
      ].filter(Boolean) as SocialLink[])
    : [];

  const breadcrumbItems = [
    { label: 'Home', href: ROUTES.HOME },
    { label: 'Profiles', href: ALUMNI_ROUTES.PROFILES },
    { label: alumnus.name || 'Profile' },
  ];

  const handleShare = () => {
    const url = window.location.href;
    if (navigator.share) {
      navigator.share({ title: alumnus.name, url }).catch(() => {});
    } else {
      navigator.clipboard.writeText(url).catch(() => {});
      toast.success('Profile link copied to clipboard!');
    }
  };

  const handleMessage = () => {
    const occupationLabel = resolveLabel(alumnus.occupations?.[0], occupationOptions);
    const recipientHeadline =
      alumnus.position && alumnus.company
        ? `${alumnus.position} at ${alumnus.company}`
        : (occupationLabel ?? `Class of ${alumnus.graduationYear}`);

    void startDirectConversation({
      participantMemberId: alumnus.memberId,
      topic: `Alumni profile conversation with ${alumnus.name}`,
      recipientProfile: {
        fullName: alumnus.name,
        avatar: profilePhoto,
        photoVisibility: alumnus.privacy?.photo,
        headline: recipientHeadline,
        location: alumnus.location || alumnus.city,
        graduationYear: alumnus.graduationYear,
        slug: alumnus.slug,
        profileHref: `/alumni/profiles/${alumnus.memberId}`,
      },
    });
  };

  return (
    <>
      <SEO title={alumnus.name || 'Alumni Profile'} description={alumnus.bio} />
      {/* <Breadcrumbs items={breadcrumbItems} /> */}

      <section className="section bg-[#F8F8F7] py-8">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6">
            {/* ── Left: sidebar ─────────────────────────────────────── */}
            <div className="space-y-4 lg:sticky lg:top-6 h-fit">
              <ProfileCard
                photo={profilePhoto}
                fullName={alumnus.name}
                maidenName={
                  alumnus.nameInSchool && alumnus.nameInSchool !== alumnus.name
                    ? alumnus.nameInSchool
                    : undefined
                }
                graduationYear={alumnus.graduationYear}
                positionLine={canSeeProfessional ? profileData.positionLine : undefined}
                city={canSeeAddress ? alumnus.city : undefined}
                isVolunteer={alumnus.isVolunteer}
                socials={socials}
                // mode={isOwner ? 'owner' : isSignedIn ? 'visitor' : 'public'}
                mode={isOwner ? 'owner' : isSignedIn ? 'visitor' : 'public'}
                onMessage={handleMessage}
                isMessaging={isStartingConversation}
                onShare={handleShare}
              />

              {/* Address sidebar card — respects residentialAddress privacy group */}
              {canSeeAddress && (
                <ProfileAddressCard
                  streetAddress={alumnus.residentialAddress}
                  area={alumnus.area}
                  state={alumnus.state}
                  city={alumnus.city}
                  zone={alumnus.zone}
                />
              )}
            </div>

            {/* ── Right: info panels ────────────────────────────────── */}
            <div>
              <ProfileInfoPanel
                data={profileData}
                copyable={!isOwner && isSignedIn}
                isOwner={isOwner}
                protectContactInfo={isSignedIn}
                contactResourceType="alumni-profile"
                contactResourceId={alumnus.memberId}
              />

              {!isOwner && isSignedIn && (
                <p className="text-xs text-gray-400 text-center mt-4">
                  Some fields may be hidden based on this member's privacy settings.
                </p>
              )}
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
