import { ChevronRight, Clock3, Hammer, MapPin } from 'lucide-react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { useProjects } from '@/features/projects/hooks/useProjects';
import type { Project } from '@/features/projects/types/project.types';
import { ROUTES } from '@/shared/constants/routes';
import EmptyState from '@/shared/components/ui/EmptyState';
import { formatDateRange } from '@/shared/utils/dateHelpers';
import { HomeSectionHeader } from './HomeSectionHeader';

const PROJECT_FALLBACK_IMAGES = [
  '/project-1.png',
  '/project-2.png',
  '/project-3.png',
  '/news-3.png',
];
const PROJECT_ICON_STROKE = 2.4;
const PROJECT_META_ICON_STROKE = 2.8;

function getProjectImage(project: Project, index: number) {
  return project.images?.[0] || PROJECT_FALLBACK_IMAGES[index % PROJECT_FALLBACK_IMAGES.length];
}

function getProjectMeta(project: Project) {
  const location = project.chapterName || 'Community Network';
  const dateRange = formatDateRange(project.startDate, project.endDate);
  return { location, dateRange };
}

function HomeProjectCard({ project, index }: { project: Project; index: number }) {
  const meta = getProjectMeta(project);

  return (
    <article className="group relative flex min-h-[22rem] overflow-hidden rounded-2xl bg-[#e9edf1] text-white shadow-[0_1rem_2rem_rgba(7,17,22,0.08)] sm:min-h-[23rem]">
      <img
        src={getProjectImage(project, index)}
        alt=""
        className="absolute inset-0 h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-[1.03]"
      />

      <div className="absolute inset-x-3 bottom-3 flex min-h-[10.5rem] flex-col rounded-[0.8rem] bg-primary-500/80 px-[1.1rem] pb-[1.05rem] pt-4 backdrop-blur-[5px]">
        {/* CONTENT */}
        <div className="flex-1">
          <h3 className="type-card-title line-clamp-2 text-white">{project.title}</h3>

          <p className="type-small mt-[0.45rem] line-clamp-2 overflow-hidden text-white/90">
            {project.description}
          </p>

          {/* META */}
          {/* META */}
          <div className="mt-[0.75rem] space-y-[0.55rem]">
            <div className="type-small flex min-w-0 items-center gap-[0.3rem] text-inherit">
              <MapPin
                aria-hidden="true"
                strokeWidth={PROJECT_META_ICON_STROKE}
                className="h-4 w-4 shrink-0"
              />

              <span className="truncate">{meta.location}</span>
            </div>

            {meta.dateRange && (
              <div className="type-small flex min-w-0 items-center gap-[0.3rem] text-inherit">
                <Clock3
                  aria-hidden="true"
                  strokeWidth={PROJECT_META_ICON_STROKE}
                  className="h-4 w-4 shrink-0"
                />

                <span className="truncate">{meta.dateRange}</span>
              </div>
            )}
          </div>
        </div>

        {/* BUTTON */}
        <AppLink
          href={ROUTES.PROJECTS.DETAIL(project.id)}
          className="type-small mt-[0.1rem] inline-flex items-center gap-[0.15rem] text-white no-underline transition-colors hover:text-white/80"
        >
          View Details
          <ChevronRight
            aria-hidden="true"
            strokeWidth={PROJECT_ICON_STROKE}
            className="h-[1.15rem] w-[1.15rem] shrink-0"
          />
        </AppLink>
      </div>
    </article>
  );
}

function HomeProjectSkeleton() {
  return (
    <div className="pointer-events-none relative min-h-[22rem] animate-pulse overflow-hidden rounded-2xl bg-[#e9edf1] shadow-[0_1rem_2rem_rgba(7,17,22,0.08)] sm:min-h-[23rem]">
      <div className="absolute inset-x-3 bottom-3 rounded-[0.8rem] bg-primary-500/80 px-[1.1rem] pb-[1.05rem] pt-4 backdrop-blur-[5px]">
        <span className="block h-3 rounded-full bg-white/55" />
        <span className="mt-[0.65rem] block h-3 rounded-full bg-white/55" />
        <span className="mt-[0.65rem] block h-3 rounded-full bg-white/55" />
      </div>
    </div>
  );
}

export default function OurProjects() {
  const { data: projects = [], isLoading } = useProjects();
  const isEmpty = !isLoading && projects.length === 0;

  return (
    <section className="home-feature-section">
      <div className="container-custom">
        <HomeSectionHeader
          eyebrow="Our Projects"
          title="Through the generosity of our alumni, we continue to support meaningful community initiatives"
          href={ROUTES.PROJECTS.ROOT}
          showViewAll={!isEmpty}
        />

        {isEmpty ? (
          <EmptyState
            icon={<Hammer strokeWidth={PROJECT_ICON_STROKE} />}
            title="No projects available right now"
            description="No projects to display yet. New initiatives to support the community will appear here."
          />
        ) : (
          <div className="grid grid-cols-1 gap-7 md:grid-cols-2 xl:grid-cols-4">
            {isLoading
              ? Array.from({ length: 4 }).map((_, i) => <HomeProjectSkeleton key={i} />)
              : projects
                  .slice(0, 4)
                  .map((project, index) => (
                    <HomeProjectCard key={project.id} project={project} index={index} />
                  ))}
          </div>
        )}
      </div>
    </section>
  );
}
