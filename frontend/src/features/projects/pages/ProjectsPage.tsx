// features/projects/pages/ProjectsPage.tsx
//
// Redesigned to match the screenshot:
// - Left-aligned "Our Projects" heading with subtitle
// - Filter row: search left, "Filter by Year" dropdown right (same pattern as AlumniDirectoryPage)
// - 4-column card grid with blue-overlay cards
// - Pagination component (replaces load-more)
// - Admin Create button in the header (visible only to admins)
// - Cream/off-white background

import { useEffect, useMemo, useState } from 'react';
import { FolderOpen, Plus } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { Pagination } from '@/shared/components/ui/Pagination';
import EmptyState from '@/shared/components/ui/EmptyState';
import { useProjects } from '../hooks/useProjects';
import { ProjectCard, ProjectCardSkeleton } from '../components/ProjectCard';
import { Link } from 'react-router-dom';
import { ROUTES } from '@/shared/constants/routes';

// ─── Responsive items per page (mirrors AlumniDirectoryPage) ─────────────────

function useItemsPerPage() {
  const [items, setItems] = useState(12);

  useEffect(() => {
    const update = () => {
      if (window.innerWidth < 640)
        setItems(6); // mobile  — 1 col × 6
      else if (window.innerWidth < 1024)
        setItems(8); // tablet  — 2 col × 4
      else setItems(12); // desktop — 4 col × 3
    };
    update();
    window.addEventListener('resize', update);
    return () => window.removeEventListener('resize', update);
  }, []);

  return items;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ProjectsPage() {

  const [searchTerm, setSearchTerm] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);

  const ITEMS_PER_PAGE = useItemsPerPage();

  const { data: projects = [], isLoading } = useProjects();

  // Year options derived from projects
  const years = useMemo(() => {
    return [
      ...new Set(
        projects
          .map((p) => {
            if (!p.startDate) return null;
            return new Date(p.startDate).getFullYear();
          })
          .filter(Boolean) as number[],
      ),
    ].sort((a, b) => b - a);
  }, [projects]);

  // Filtered list
  const filtered = useMemo(() => {
    const q = searchTerm.trim().toLowerCase();
    return projects.filter((p) => {
      const matchesSearch =
        !q ||
        p.title.toLowerCase().includes(q) ||
        p.description.toLowerCase().includes(q) ||
        p.location?.toLowerCase().includes(q) ||
        p.status?.toLowerCase().includes(q);
      const matchesYear =
        !yearFilter ||
        (p.startDate && new Date(p.startDate).getFullYear().toString() === yearFilter);
      return matchesSearch && matchesYear;
    });
  }, [projects, searchTerm, yearFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  const pageStart = (currentPage - 1) * ITEMS_PER_PAGE;
  const visible = filtered.slice(pageStart, pageStart + ITEMS_PER_PAGE);

  const changePage = (p: number) => {
    setCurrentPage(p);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const resetFilters = (setter: (v: string) => void) => (v: string) => {
    setter(v);
    setCurrentPage(1);
  };




  return (
    <>
      <SEO
        title="Our Projects"
        description="Through the generosity of our alumni, we continue to support meaningful community initiatives."
      />

      <section className="min-h-screen bg-[#F8F8F7] py-6">
        <div className="container-custom mx-auto">
          {/* Header */}
          <div className="mb-12 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="max-w-2xl">
              <h1 className="type-section-title text-gray-900">Our Projects</h1>
              <p className="text-gray-500 text-sm sm:text-base mt-1">
                Through the generosity of our alumni, we continue to support and improve the world
                around us
              </p>
            </div>

            <div className="flex w-full flex-wrap items-center justify-center gap-3 sm:w-auto sm:flex-shrink-0 sm:justify-end">
              <Link
                to={ROUTES.NEWS}
                className="flex-1 rounded-full border-2 border-primary-500 px-5 py-2.5 text-center text-sm font-bold whitespace-nowrap text-primary-500 transition-colors hover:bg-primary-500 hover:text-white sm:flex-none"
              >
                Go to Announcements
              </Link>
              <Link
                to={ROUTES.EVENTS.ROOT}
                className="flex-1 rounded-full border-2 border-primary-500 px-5 py-2.5 text-center text-sm font-bold whitespace-nowrap text-primary-500 transition-colors hover:bg-primary-500 hover:text-white sm:flex-none"
              >
                Go to Events
              </Link>
            </div>
          </div>

          {/* Filter row — identical layout to AlumniDirectoryPage */}
          <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex w-full flex-col gap-4 sm:flex-row sm:items-center">
              <div className="flex-1 w-full sm:max-w-xl">
                <SearchInput
                  value={searchTerm}
                  onValueChange={resetFilters(setSearchTerm)}
                  placeholder="Search here..."
                  inputClassName="!h-10 !py-0"
                />
              </div>
              <div className="w-full sm:w-auto">
                <FilterDropdown
                  value={yearFilter}
                  onChange={resetFilters(setYearFilter)}
                  placeholder="Filter by Year"
                  options={[
                    { label: 'All', value: '' },
                    ...years.map((y) => ({ label: String(y), value: String(y) })),
                  ]}
                />
              </div>
            </div>
    
          </div>

          {/* Grid */}
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {Array.from({ length: 8 }).map((_, i) => (
                <ProjectCardSkeleton key={i} />
              ))}
            </div>
          ) : visible.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {visible.map((project) => (
                <ProjectCard
                  key={project.id}
                  project={project}
                  showAdminActions={false}
                />
              ))}
            </div>
          ) : (
            <EmptyState
              icon={<FolderOpen strokeWidth={2.4} />}
              title="No projects found"
              description={
                searchTerm || yearFilter
                  ? 'Try adjusting your search or filter.'
                  : 'Check back later for updates.'
              }
            />
          )}

          {/* Pagination */}
          {!isLoading && totalPages > 1 && (
            <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                onPageChange={changePage}
              />
            </div>
          )}
        </div>
      </section>

    </>
  );
}
