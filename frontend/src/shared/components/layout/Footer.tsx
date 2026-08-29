import { Icon } from '@iconify/react';
import { getSiteConfig } from '@/data/content';
import { AppLink } from '../ui/AppLink';
import FooterBgImage from '/footer-bg-image.png';
import FooterLogo from '../ui/FooterLogo';
import AddressLocationIcon from '/addressLocation.svg';
import LocationPhoneIcon from '/locationPhone.svg';
import LocationMessageIcon from '/locationMessage.svg';
import { ROUTES } from '@/shared/constants/routes';

const SOCIAL_ICON_MAP: Record<string, string> = {
  facebook: 'mdi:facebook',
  instagram: 'mdi:instagram',
  tiktok: 'ic:baseline-tiktok',
  tiktokalt: 'simple-icons:tiktok',
  twitter: 'ri:twitter-x-fill',
  x: 'ri:twitter-x-fill',
  linkedin: 'mdi:linkedin',
  youtube: 'mdi:youtube',
  whatsapp: 'mdi:whatsapp',
};

function resolveSocialIcon(iconName: string): string {
  const key = iconName.toLowerCase().replace(/[^a-z]/g, '');
  return SOCIAL_ICON_MAP[key] ?? `mdi:${iconName}`;
}

const footerSocialLinkClassName =
  'relative flex h-10 w-10 items-center justify-center rounded-full bg-transparent transition-all duration-200 hover:scale-105 before:absolute before:inset-0 before:rounded-full before:p-px before:bg-[linear-gradient(90deg,#015C9E_0%,#FFFFFF_50%,#015C9E_100%)] before:[-webkit-mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)] before:[-webkit-mask-composite:xor] before:[mask-composite:exclude] before:pointer-events-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white';
const footerSocialIconClassName = 'h-5 w-5 text-white';
const footerContactIconClassName = 'flex-shrink-0 opacity-80';

const QUICK_LINKS = [
  { label: 'About Us', href: ROUTES.ABOUT },
  { label: 'Announcements', href: ROUTES.NEWS },
  { label: 'Events', href: ROUTES.EVENTS.ROOT },
  { label: 'Our Projects', href: ROUTES.PROJECTS.ROOT },
  { label: 'Volunteer', href: ROUTES.JOIN_PROJECTS.VOLUNTEER },
  { label: 'FAQs', href: ROUTES.FAQS },
  { label: 'Contact Us', href: ROUTES.CONTACT },
];

const COMMUNITY_LINKS = [
  { label: 'Member Directory', href: ROUTES.ALUMNI.ROOT },
  { label: 'Marketplace', href: ROUTES.MARKETPLACE.ROOT },
  { label: 'Welfare', href: ROUTES.WELFARE },
  { label: 'Social Media Feed', href: ROUTES.SOCIAL_MEDIA_FEED },
  { label: 'Polls', href: '/polls' },
];

const LEGAL_LINKS = [
  { label: 'Privacy Policy', href: ROUTES.PRIVACY },
  { label: 'Terms of Use', href: ROUTES.TERMS },
  { label: 'Code of Conduct', href: '/code-of-conduct' },
];

function LinkColumn({
  heading,
  links,
}: {
  heading: string;
  links: { label: string; href: string }[];
}) {
  return (
    <div>
      <h4 className="mb-4 text-sm font-bold tracking-wide text-white">{heading}</h4>
      <ul className="space-y-3.5">
        {links.map(({ label, href }) => (
          <li key={label}>
            <AppLink
              href={href}
              className="text-sm leading-snug text-white/90 transition-colors duration-150 hover:text-white"
            >
              {label}
            </AppLink>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function Footer() {
  const config = getSiteConfig();
  const currentYear = new Date().getFullYear();

  const socialLinks: { name: string; url: string; icon: string }[] = config.social_links ?? [];

  return (
    <footer className="relative min-h-[626px] overflow-hidden bg-[#021E44] text-white">
      <div
        className="absolute inset-0 bg-cover bg-[center_top] bg-no-repeat"
        style={{ backgroundImage: `url(${FooterBgImage})` }}
        aria-hidden="true"
      />

      <div
        className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,30,68,0)_0%,rgba(2,30,68,0.35)_34%,rgba(2,30,68,0.88)_58%,#021E44_78%,#021E44_100%)]"
        aria-hidden="true"
      />

      <div className="relative z-10">
        <div className="container-custom flex min-h-[626px] flex-col justify-end pt-48 sm:pt-60 lg:pt-72 xl:pt-80">
          <div className="grid grid-cols-1 gap-10 pb-9 sm:pb-11 lg:grid-cols-[minmax(17rem,0.95fr)_auto_minmax(27rem,1fr)_auto_auto] lg:items-start lg:gap-0">
            <div className="lg:pr-10 xl:pr-14">
              <div className="mt-6 mb-6">
                <FooterLogo />
              </div>

              <p className="max-w-[340px] text-sm leading-relaxed text-white">
                Connecting generations of graduates through a global network built on excellence,
                integrity, and service.
              </p>

              <div className="mt-6 space-y-3">
                <div className="flex items-start gap-3">
                  <img
                    src={AddressLocationIcon}
                    alt=""
                    aria-hidden="true"
                    className={`${footerContactIconClassName} mt-0.5 h-5 w-[17px]`}
                  />
                  <span className="text-sm leading-snug text-white">
                    {config.organization?.address || 'Lagos, Nigeria'}
                  </span>
                </div>
                <div className="flex items-center gap-3">
                  <img
                    src={LocationMessageIcon}
                    alt=""
                    aria-hidden="true"
                    className={`${footerContactIconClassName} h-[14px] w-[17px]`}
                  />
                  <AppLink
                    href={`mailto:${config.contact?.email || 'info@alumniportal.org'}`}
                    className="text-sm text-white transition-colors hover:text-white"
                  >
                    {config.contact?.email || 'info@alumniportal.org'}
                  </AppLink>
                </div>
                <div className="flex items-center gap-3">
                  <img
                    src={LocationPhoneIcon}
                    alt=""
                    aria-hidden="true"
                    className={`${footerContactIconClassName} h-4 w-[17px]`}
                  />
                  <AppLink
                    href={`tel:${config.contact?.phone || '+2348000000000'}`}
                    className="text-sm text-white transition-colors hover:text-white"
                  >
                    {config.contact?.phone || '+234 800 000 0000'}
                  </AppLink>
                </div>
              </div>
            </div>

            <div className="mx-6 hidden w-px min-h-52 self-stretch bg-gradient-to-b from-white/0 via-white/70 to-white/0 lg:block xl:mx-10" />

            <div className="lg:px-2">
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-8 lg:gap-x-10 xl:gap-x-14">
                <LinkColumn heading="Quick Links" links={QUICK_LINKS} />
                <LinkColumn heading="Community" links={COMMUNITY_LINKS} />
                <div className="col-span-2 sm:col-span-1">
                  <LinkColumn heading="Legal & Policies" links={LEGAL_LINKS} />
                </div>
              </div>
            </div>

            <div className="mx-6 hidden w-px min-h-52 self-stretch bg-[linear-gradient(180deg,#015C9E_0%,#FFFFFF_50%,#015C9E_100%)] lg:block xl:mx-10" />

            {socialLinks.length > 0 && (
              <div className="hidden flex-col items-center justify-start gap-4 lg:flex">
                {socialLinks.map((social) => (
                  <AppLink
                    key={social.name}
                    href={social.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    ariaLabel={social.name}
                    className={footerSocialLinkClassName}
                  >
                    <Icon
                      icon={resolveSocialIcon(social.icon)}
                      aria-hidden="true"
                      className={footerSocialIconClassName}
                    />
                  </AppLink>
                ))}
              </div>
            )}
          </div>

          <div className="py-5">
            <p className="text-left text-xs font-[500] text-[#BDBDBD]">
              © {currentYear} Alumni Portal. All rights reserved.
            </p>
          </div>
        </div>
      </div>

      {socialLinks.length > 0 && (
        <div className="relative z-10 border-t border-white/10 lg:hidden">
          <div className="container-custom py-5">
            <div className="flex flex-wrap items-center justify-center gap-3">
              {socialLinks.map((social) => (
                <AppLink
                  key={social.name}
                  href={social.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  ariaLabel={social.name}
                  className={footerSocialLinkClassName}
                >
                  <Icon
                    icon={resolveSocialIcon(social.icon)}
                    aria-hidden="true"
                    className={footerSocialIconClassName}
                  />
                </AppLink>
              ))}
            </div>
          </div>
        </div>
      )}
    </footer>
  );
}
