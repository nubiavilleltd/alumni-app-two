import { useEffect, useMemo, useState } from 'react';
import { FolderOpen, SlidersHorizontal } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { Pagination } from '@/shared/components/ui/Pagination';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import { HierarchicalLocationFilter } from '@/shared/components/ui/HierarchicalLocationFilter';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';

import { Project } from '@/features/projects/types/project.types';
import { useProjects } from '@/features/projects/hooks/useProjects';
import { JoinProjectCard, JoinProjectCardSkeleton } from '../components/JoinProjectCard';

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

export default function JoinProjectsPage() {
  const currentUser = useIdentityStore((state) => state.user);
  const isAdmin = currentUser?.role === 'admin';

  const { filters, setFilter, clearFilters } = usePersistedFilters('join-projects-filters', {
    searchTerm: '',
    yearFilter: '',
    statusFilter: '',
    locationFilter: '',
    fundingFilter: '',
    featuredFilter: '',
  });
  const { searchTerm, yearFilter, statusFilter, locationFilter, fundingFilter, featuredFilter } =
    filters;
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
  const [currentPage, setCurrentPage] = useUrlPagination();

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

  const locationOptions = useMemo(() => {
    const query = searchTerm.trim().toLowerCase();
    const matchesWithoutLocation = (project: Project) => {
      const matchesSearch =
        !query ||
        project.title.toLowerCase().includes(query) ||
        project.description.toLowerCase().includes(query) ||
        project.location?.toLowerCase().includes(query) ||
        project.status?.toLowerCase().includes(query);
      const matchesYear =
        !yearFilter ||
        (project.startDate && new Date(project.startDate).getFullYear().toString() === yearFilter);
      const matchesStatus = !statusFilter || project.status === statusFilter;
      const matchesFunding =
        !fundingFilter ||
        (fundingFilter === 'funded'
          ? Boolean(project.targetAmount && project.amountRaised >= project.targetAmount)
          : Boolean(!project.targetAmount || project.amountRaised < project.targetAmount));
      const matchesFeatured = !featuredFilter || project.isFeatured === 1;

      return matchesSearch && matchesYear && matchesStatus && matchesFunding && matchesFeatured;
    };

    return Array.from(new Set(projects.map((project) => project.location).filter(Boolean)))
      .sort((a, b) => a.localeCompare(b))
      .map((value) => ({
        label: value,
        value,
        count: projects.filter(
          (project) => matchesWithoutLocation(project) && project.location === value,
        ).length,
      }));
  }, [featuredFilter, fundingFilter, projects, searchTerm, statusFilter, yearFilter]);

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
      const matchesStatus = !statusFilter || p.status === statusFilter;
      const matchesLocation = !locationFilter || p.location === locationFilter;
      const matchesFunding =
        !fundingFilter ||
        (fundingFilter === 'funded'
          ? Boolean(p.targetAmount && p.amountRaised >= p.targetAmount)
          : Boolean(!p.targetAmount || p.amountRaised < p.targetAmount));
      const matchesFeatured = !featuredFilter || p.isFeatured === 1;
      return (
        matchesSearch &&
        matchesYear &&
        matchesStatus &&
        matchesLocation &&
        matchesFunding &&
        matchesFeatured
      );
    });
  }, [
    featuredFilter,
    fundingFilter,
    locationFilter,
    projects,
    searchTerm,
    statusFilter,
    yearFilter,
  ]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  const pageStart = (currentPage - 1) * ITEMS_PER_PAGE;
  const visible = filtered.slice(pageStart, pageStart + ITEMS_PER_PAGE);

  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const changePage = (p: number) => {
    setCurrentPage(p);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const resetFilters = (key: keyof typeof filters) => (value: string) => {
    setFilter(key, value);
  };

  const activeAdvancedFilterCount = [
    statusFilter,
    locationFilter,
    fundingFilter,
    featuredFilter,
  ].filter(Boolean).length;
  const hasActiveFilters = Boolean(searchTerm.trim() || yearFilter || activeAdvancedFilterCount);

  const clearAllFilters = () => {
    clearFilters();
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    const filterKeys: Record<string, keyof typeof filters> = {
      status: 'statusFilter',
      location: 'locationFilter',
      funding: 'fundingFilter',
      featured: 'featuredFilter',
    };
    const filterKey = filterKeys[key];
    if (filterKey) setFilter(filterKey, value);
  };

  return (
    <>
      <SEO
        title="Join Projects"
        description="Through the generosity of our alumni, we continue to support meaningful community initiatives."
      />

      <section className="min-h-screen bg-[#F8F8F7] py-6">
        <div className="container-custom mx-auto">
          {/* Header */}
          <div className="mb-12 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="max-w-2xl">
              <h1 className="type-section-title text-gray-900">Join a Project</h1>
            </div>
          </div>

          {/* Filter row — identical layout to AlumniDirectoryPage */}
          <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex w-full flex-col gap-4 sm:flex-row sm:items-center">
              <div className="flex-1 w-full sm:max-w-xl">
                <SearchInput
                  value={searchTerm}
                  onValueChange={resetFilters('searchTerm')}
                  placeholder="Search here..."
                  inputClassName="!h-10 !py-0"
                />
              </div>
              <div className="w-full sm:w-auto">
                <FilterDropdown
                  value={yearFilter}
                  onChange={resetFilters('yearFilter')}
                  placeholder="Filter by Year"
                  options={[
                    { label: 'All', value: '' },
                    ...years.map((y) => ({ label: String(y), value: String(y) })),
                  ]}
                />
              </div>
            </div>
            <button
              type="button"
              onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
              aria-expanded={showAdvancedFilters}
              className="flex h-10 w-full shrink-0 items-center justify-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-600 shadow-sm transition-colors hover:bg-gray-50 sm:w-auto"
            >
              <SlidersHorizontal className="h-4 w-4" />
              Advanced filters
              {activeAdvancedFilterCount > 0 && (
                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] text-white">
                  {activeAdvancedFilterCount}
                </span>
              )}
            </button>
          </div>

          {showAdvancedFilters && (
            <AdvancedFiltersPanel
              title="Refine project results"
              description="Narrow projects by status, location, funding state, or featured status."
              gridClassName="grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
              customContent={
                <HierarchicalLocationFilter
                  label="Location"
                  placeholder="All locations"
                  levelOneLabel="Location"
                  value={{ state: locationFilter, city: '' }}
                  options={locationOptions}
                  onChange={(selection) => {
                    setFilter('locationFilter', selection.state);
                  }}
                />
              }
              fields={[
                {
                  key: 'status',
                  kind: 'select',
                  label: 'Status',
                  value: statusFilter,
                  placeholder: 'All statuses',
                  options: [
                    { label: 'Ongoing', value: 'ongoing' },
                    { label: 'Completed', value: 'completed' },
                  ],
                },
              ]}
              onFieldChange={handleAdvancedFilterChange}
              onReset={clearAllFilters}
              hasActiveFilters={activeAdvancedFilterCount > 0}
            />
          )}

          {hasActiveFilters && (
            <div className="mb-8 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
              <span>
                Showing {filtered.length} {filtered.length === 1 ? 'project' : 'projects'} matching
                your filters
              </span>
              <ClearFiltersButton onClick={clearAllFilters} label="Clear all filters" />
            </div>
          )}

          {/* Grid */}
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {Array.from({ length: 8 }).map((_, i) => (
                <JoinProjectCardSkeleton key={i} />
              ))}
            </div>
          ) : visible.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
              {visible.map((project) => (
                <JoinProjectCard key={project.id} project={project} />
              ))}
            </div>
          ) : (
            <EmptyState
              icon={<FolderOpen strokeWidth={2.4} />}
              title="No projects found"
              description={
                hasActiveFilters
                  ? 'Try adjusting your search or filter.'
                  : isAdmin
                    ? 'No projects yet. Create the first one!'
                    : 'Check back later for updates.'
              }
            />
          )}

          {/* Pagination */}
          {!isLoading && totalPages > 1 && (
            <div className="sticky bottom-0 mt-6 bg-[#F8F8F7] py-4">
              <div className="text-center text-[11px] text-gray-400">
                Showing {pageStart + 1}-{Math.min(pageStart + ITEMS_PER_PAGE, filtered.length)} of{' '}
                {filtered.length} projects
              </div>
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
