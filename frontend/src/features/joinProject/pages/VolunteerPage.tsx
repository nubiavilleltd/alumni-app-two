import { SEO } from '@/shared/common/SEO';
import ContainerBackground from '@/shared/containers/ContainerBackground';
import { AppLink } from '@/shared/components/ui/AppLink';
import { JOIN_PROJECT_ROUTES } from '../routes';

// TODO: replace with the correct route once provided
const JOIN_PROJECT_URL = '#';

// TODO: replace with actual image paths from the public folder
const PLACEHOLDER_IMAGE = '/placeholder-image.png';

interface VolunteerOpportunity {
  id: string;
  title: string;
  description: string;
  image: string;
  ctaHref?: string;
}

const opportunities: VolunteerOpportunity[] = [
  {
    id: 'join-a-project',
    title: 'Join a Project',
    description:
      'Find a project or worthwhile cause and jump in! Be a part of something bigger than yourself and join other community volunteers in making an impact.',
    image: "/join-projects.png",
    ctaHref: JOIN_PROJECT_ROUTES.PROJECTS,
  },
  {
    id: 'project-leaders',
    title: 'Project Leaders',
    description:
      'You know what your community needs. Lead a project and be the catalyst for making a lasting difference in your community.',
    image: "/project-leaders.png",
  },
  {
    id: 'think-tank',
    title: 'Think Tank',
    description:
      "Contribute innovative ideas and strategic insights. Help develop solutions for the association's growth.",
    image: "/think-thank.png",
  },
  {
    id: 'become-a-representative',
    title: 'Become a Representative',
    description:
      'Connect members and represent your alumni group. Promote engagement and strengthen community connections.',
    image: "/become-rep.png",
  },
];

// ─── Opportunity card ───────────────────────────────────────────────────────

function OpportunityCard({ opportunity }: { opportunity: VolunteerOpportunity }) {
  const handleVolunteer = () => {
    console.log('Volunteer now:', opportunity.id);
  };

  return (
    <div className="bg-white border border-primary-200 rounded-2xl shadow-sm overflow-hidden flex gap-5">
      <div className="w-32 sm:w-36 flex-shrink-0  overflow-hidden">
        <img
          src={opportunity.image}
          alt={opportunity.title}
          className="w-full h-full object-cover"
          loading="lazy"
        />
      </div>

      <div className="flex flex-col p-2">
        <h3 className="text-gray-900 font-bold text-xl leading-snug">{opportunity.title}</h3>

        <p className="text-gray-500 text-sm leading-relaxed mt-2">{opportunity.description}</p>

        {opportunity.ctaHref ? (
          <AppLink
            href={opportunity.ctaHref}
            className="mt-4 self-start inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm transition-colors"
          >
            Volunteer Now
          </AppLink>
        ) : (
          <button
            type="button"
            onClick={handleVolunteer}
            className="mt-4 self-start inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm transition-colors"
          >
            Volunteer Now
          </button>
        )}
      </div>
    </div>
  );
}

// ─── Page ───────────────────────────────────────────────────────────────────

export default function VolunteerPage() {
  return (
    <>
      <SEO
        title="Volunteer"
        description="Through the generosity of our alumni, we continue to support meaningful community initiatives."
      />

      {/* Hero */}
      <section className="relative h-[520px] sm:h-[700px] overflow-hidden container-custom">
        <img
          src={"/bg-volunteer.png"}
          alt="Volunteers"
          className="absolute inset-0 w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent" />

        <div className="relative h-full mx-auto flex flex-col justify-center sm:px-0">
          <span className="inline-flex w-fit items-center px-4 py-1.5 rounded-full border border-white/40 text-white text-xs font-semibold mb-4">
            A Spirit of Service
          </span>

          <p className="text-white text-lg sm:text-xl font-medium mb-1">Volunteer Opportunities</p>

          <h1 className="text-white font-extrabold text-4xl sm:text-6xl md:text-7xl leading-tight mb-4">
            Step Into Impact
          </h1>

          <p className="text-white text-base sm:text-lg md:text-xl max-w-2xl leading-relaxed">
            Join our volunteer network to organise events and drive initiatives that strengthen
            the alumni association and the community around us.
          </p>
        </div>
      </section>

      {/* Opportunities grid */}
      {/* <ContainerBackground> */}
        <div className="container-custom mx-auto py-10">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {opportunities.map((opportunity) => (
              <OpportunityCard key={opportunity.id} opportunity={opportunity} />
            ))}
          </div>
        </div>
    </>
  );
}