// pages/resources/ResourcesPage.tsx
// Route: /resources
// Design: 2×2 grid of category cards with coloured icon badges.
// Each resource link is blue with an external link icon where applicable.

import { ExternalLink, HeartHandshake, type LucideIcon, Rocket, Scale, Shield } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { ROUTES } from '@/shared/constants/routes';
import { AppLink } from '@/shared/components/ui/AppLink';

// ─── Types ────────────────────────────────────────────────────────────────────

interface ResourceLink {
  label: string;
  description: string;
  href?: string;
  external?: boolean;
}

interface ResourceCategory {
  id: string;
  title: string;
  icon: LucideIcon;
  iconBg: string;
  iconColor: string;
  links: ResourceLink[];
}

// ─── Data ─────────────────────────────────────────────────────────────────────

const categories: ResourceCategory[] = [
  {
    id: 'safety',
    title: 'Support & Safety',
    icon: Shield,
    iconBg: 'bg-red-100',
    iconColor: 'text-red-500',
    links: [
      {
        label: 'WARIF',
        description: 'Confidential support resources for people affected by violence.',
        href: 'https://warif.org',
        external: true,
      },
      {
        label: 'Lagos State DSVRT',
        description: 'Domestic and Sexual Violence Response Team - Legal and psychosocial support.',
        href: 'https://lagosstate.gov.ng',
        external: true,
      },
    ],
  },
  {
    id: 'career',
    title: 'Career & Growth',
    icon: Rocket,
    iconBg: 'bg-green-100',
    iconColor: 'text-green-600',
    links: [
      {
        label: 'Networking 101',
        description: 'Guide to building meaningful professional relationships.',
      },
      {
        label: 'Mentorship Program',
        description: 'How to find a mentor or become one within the alumni network.',
      },
      {
        label: 'Job Vacancies',
        description: 'Exclusive job listings for alumni.',
        href: ROUTES.JOB_VACANCIES,
      },
    ],
  },
  {
    id: 'legal',
    title: 'Legal Aid / Referral',
    icon: Scale,
    iconBg: 'bg-purple-100',
    iconColor: 'text-purple-600',
    links: [
      {
        label: 'Legal Aid',
        description:
          'Access reliable legal support and guidance to help you navigate disputes, rights, and formal processes with confidence.',
      },
      {
        label: 'Referral',
        description:
          'Connect with trusted professionals and services through verified referrals within the alumni network.',
      },
    ],
  },
  {
    id: 'counselling',
    title: 'Counselling',
    icon: HeartHandshake,
    iconBg: 'bg-sky-100',
    iconColor: 'text-sky-500',
    // No clickable links — just descriptive paragraphs
    links: [
      {
        label: '',
        description: 'A safe space to talk, reflect, and receive guidance for your wellbeing.',
      },
      {
        label: '',
        description:
          'Confidential support to help you navigate personal, emotional, and professional challenges.',
      },
      {
        label: '',
        description:
          'Access professional counselling and support tailored to your personal and mental wellbeing.',
      },
    ],
  },
];

// ─── Icon Badge ───────────────────────────────────────────────────────────────

function IconBadge({
  icon: IconComponent,
  bg,
  color,
}: {
  icon: LucideIcon;
  bg: string;
  color: string;
}) {
  return (
    <div className={`flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl ${bg}`}>
      <IconComponent aria-hidden="true" className={`h-7 w-7 ${color}`} strokeWidth={2.25} />
    </div>
  );
}

// ─── Resource Card ────────────────────────────────────────────────────────────

function ResourceCard({ category }: { category: ResourceCategory }) {
  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-7 flex flex-col gap-5">
      {/* Icon */}
      <IconBadge icon={category.icon} bg={category.iconBg} color={category.iconColor} />

      {/* Title */}
      <h2 className="text-[24px] font-[600] text-gray-900">{category.title}</h2>

      {/* Links / descriptions */}
      <div className="space-y-4">
        {category.links.map((link, i) => (
          <div key={i} className="space-y-1">
            {/* {link.label ? (
              link.href ? (
                <Link
                  to={link.href}
                  //   target={link.external ? '_blank' : undefined}
                  rel={link.external ? 'noopener noreferrer' : undefined}
                  className="type-small inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 transition-colors"
                >
                  {link.label}
                  {link.external && (
                    <Icon icon="mdi:open-in-new" className="w-3.5 h-3.5 flex-shrink-0" />
                  )}
                </Link>
              ) : (
                <button
                  type="button"
                  className="type-small inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 transition-colors text-left"
                >
                  {link.label}
                </button>
              )
            ) : null} */}

            {link.label ? (
              link.href ? (
                <AppLink
                  href={link.href}
                  target={link.external ? '_blank' : undefined}
                  rel={link.external ? 'noopener noreferrer' : undefined}
                  className="inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 font-semibold text-[18px] transition-colors"
                  endAdornment={
                    link.external ? (
                      <ExternalLink className="h-3.5 w-3.5 flex-shrink-0" strokeWidth={2.25} />
                    ) : null
                  }
                >
                  {link.label}
                </AppLink>
              ) : (
                <button
                  type="button"
                  className="inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 font-semibold text-[18px] transition-colors text-left"
                >
                  {link.label}
                </button>
              )
            ) : null}
            <p className="text-[16px] font-[500] text-[#4B5563]">{link.description}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ResourcesPage() {
  return (
    <>
      <SEO
        title="Resources"
        description="A collection of tools, links, and articles to support your personal and professional growth, as well as your well-being."
      />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-8 sm:py-10">
          {/* ── Header ───────────────────────────────────────────────── */}
          <div className="mb-8">
            <h1 className="type-section-title mb-2 text-gray-900">Resources</h1>
            <p className="type-card-body max-w-2xl text-gray-600">
              A collection of tools, links, and articles to support your personal and professional
              growth, as well as your well-being.
            </p>
          </div>

          {/* ── 2×2 grid ─────────────────────────────────────────────── */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            {categories.map((cat) => (
              <ResourceCard key={cat.id} category={cat} />
            ))}
          </div>
        </div>
      </div>
    </>
  );
}
