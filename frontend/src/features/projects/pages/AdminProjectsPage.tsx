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
import { Folder, Plus } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { Pagination } from '@/shared/components/ui/Pagination';
import { useProjects } from '../hooks/useProjects';
import { ProjectCard, ProjectCardSkeleton } from '../components/ProjectCard';
import { ProjectFormModal } from '../components/ProjectFormModal';
import type { Project } from '../types/project.types';
import { Link } from 'react-router-dom';
import { ROUTES } from '@/shared/constants/routes';
import { AdminBanner } from '@/features/admin/components/AdminBanner';
import { canManageProjects } from '@/shared/permissions/project.permission';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';

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

export default function AdminProjectsPage() {
 

  const { data: currentUser } = useCurrentUser();

  const canUserManageProjects = canManageProjects(currentUser)

  const [searchTerm, setSearchTerm] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [showFormModal, setShowFormModal] = useState(false);
  const [editProject, setEditProject] = useState<Project | null>(null);

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

  const openCreate = () => {
    setEditProject(null);
    setShowFormModal(true);
  };
  const openEdit = (p: Project) => {
    setEditProject(p);
    setShowFormModal(true);
  };
  const closeModal = () => {
    setShowFormModal(false);
    setEditProject(null);
  };

  return (
    <>
      <SEO
        title="Our Projects"
        description="Through the generosity of our alumni, we continue to support meaningful community initiatives."
      />
      <AdminBanner activeTab="projects" title="Projects" />
      <section className="min-h-screen bg-[#F8F8F7] py-8 sm:py-10">
        <div className="container-custom mx-auto">
          {/* Header */}
          <div className="mb-8 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
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
                  // inputClassName="!h-[56px] !border-0 !shadow-none focus:!ring-0"
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
                  // selectClassName="[&_.select-input__control-wrap>button]:!h-[56px] [&_.select-input__control-wrap>button]:!border-0 [&_.select-input__control-wrap>button]:!shadow-none [&_.select-input__control-wrap>button]:focus:!ring-0"
                />
              </div>
            </div>
            {canUserManageProjects && visible.length > 0 && (
              <button
                type="button"
                onClick={openCreate}
                className="flex flex-shrink-0 items-center justify-center gap-1.5 rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600"
              >
                <Plus className="w-4 h-4" />
                <span className="hidden sm:inline">Create Project</span>
              </button>
            )}
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
                  showAdminActions={true}
                  onEdit={canUserManageProjects ? openEdit : undefined}
                />
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center px-6 py-20 text-center">
              <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50">
                <Folder className="h-8 w-8 text-primary-300" />
              </div>
              <h3 className="mb-1 text-base font-semibold text-gray-700">No projects found</h3>
              <p className="max-w-xs text-sm leading-relaxed text-gray-400">
                {searchTerm || yearFilter
                  ? 'Try adjusting your search or filter.'
                  : canUserManageProjects
                    ? 'No projects yet. Create the first one!'
                    : 'Check back later for updates.'}
              </p>
              {canUserManageProjects && !searchTerm && !yearFilter ? (
                <button
                  type="button"
                  onClick={openCreate}
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-primary-500 px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-600"
                >
                  <Plus className="h-4 w-4" />
                  Create Project
                </button>
              ) : null}
            </div>
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

      <ProjectFormModal isOpen={showFormModal} onClose={closeModal} editData={editProject} />
    </>
  );
}
