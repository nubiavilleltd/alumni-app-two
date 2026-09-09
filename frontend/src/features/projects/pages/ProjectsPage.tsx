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
import { FolderOpen } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { HierarchicalLocationFilter } from '@/shared/components/ui/HierarchicalLocationFilter';
import { Pagination } from '@/shared/components/ui/Pagination';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import { useProjects } from '../hooks/useProjects';
import { ProjectCard, ProjectCardSkeleton } from '../components/ProjectCard';
import type { Project } from '../types/project.types';
import { Link } from 'react-router-dom';
import { ROUTES } from '@/shared/constants/routes';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { useLocations } from '@/shared/hooks/useLocations';
import type { LocationGroup } from '@/shared/types/location.types';

function normalizeFilterValue(value?: string | null) {
  return value?.trim().toLowerCase() ?? '';
}

interface ProjectFilterValues {
  searchTerm?: string;
  yearFilter?: string;
  statusFilter?: string;
  locationFilter?: string;
  cityFilter?: string;
}

function projectMatchesYear(project: Project, year: string) {
  return [project.startDate, project.endDate]
    .filter((date): date is string => Boolean(date))
    .some((date) => new Date(date).getFullYear().toString() === year);
}

function projectMatchesFilters(project: Project, filters: ProjectFilterValues) {
  const query = normalizeFilterValue(filters.searchTerm);
  const matchesSearch =
    !query ||
    [
      project.title,
      project.description,
      project.location,
      project.status,
      project.conductedBy,
      project.chapterName,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(query);
  const matchesYear = !filters.yearFilter || projectMatchesYear(project, filters.yearFilter);
  const matchesStatus =
    !filters.statusFilter ||
    normalizeFilterValue(project.status) === normalizeFilterValue(filters.statusFilter);
  const selectedLocation = filters.cityFilter || filters.locationFilter;
  const matchesLocation =
    !selectedLocation ||
    normalizeFilterValue(project.location).includes(normalizeFilterValue(selectedLocation));

  return matchesSearch && matchesYear && matchesStatus && matchesLocation;
}

// ─── Responsive items per page (mirrors AlumniDirectoryPage) ─────────────────

function useItemsPerPage() {
  const [items, setItems] = useState(12);

  console.log('useItemsPerPage: items =', items);

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
  const { filters, setFilter, setFilters, clearFilters } = usePersistedFilters('projects-filters', {
    searchTerm: '',
    yearFilter: '',
    statusFilter: '',
    locationFilter: '',
    cityFilter: '',
  });
  const { searchTerm, yearFilter, statusFilter, locationFilter, cityFilter } = filters;
  const [currentPage, setCurrentPage] = useUrlPagination();

  const ITEMS_PER_PAGE = useItemsPerPage();

  const { data: projects = [], isLoading } = useProjects();
  const { data: locations = [] } = useLocations();

  console.log('ProjectsPage: projects =', projects, 'isLoading =', isLoading, projects.length);

  // Year options derived from projects
  const years = useMemo(() => {
    return [
      ...new Set(
        projects
          .flatMap((p) => [p.startDate, p.endDate])
          .filter(Boolean)
          .map((date) => new Date(date as string).getFullYear())
          .filter((year) => Number.isFinite(year)),
      ),
    ].sort((a, b) => b - a);
  }, [projects]);

  const statusOptions = useMemo(() => {
    const availableStatuses = new Set(
      projects.map((project) => normalizeFilterValue(project.status)).filter(Boolean),
    );

    return [
      { label: 'Ongoing', value: 'ongoing' },
      { label: 'Completed', value: 'completed' },
    ]
      .filter((option) => availableStatuses.has(option.value))
      .map((option) => ({
        ...option,
        count: projects.filter(
          (project) =>
            projectMatchesFilters(project, {
              searchTerm,
              yearFilter,
              locationFilter,
              cityFilter,
            }) && normalizeFilterValue(project.status) === option.value,
        ).length,
      }));
  }, [cityFilter, locationFilter, projects, searchTerm, yearFilter]);

  const locationOptions = useMemo(() => {
    type LocationOptionGroup = {
      label: string;
      value: string;
      cities: Map<string, { label: string; value: string }>;
    };

    const groups = new Map<string, LocationOptionGroup>();
    const ensureGroup = (label: string) => {
      const value = normalizeFilterValue(label);
      if (!value) return null;

      const existing = groups.get(value);
      if (existing) return existing;

      const group = { label, value, cities: new Map() };
      groups.set(value, group);
      return group;
    };

    locations.forEach((location: LocationGroup) => {
      const group = ensureGroup(location.state);
      if (!group) return;

      location.cities.forEach((city) => {
        const value = normalizeFilterValue(city);
        if (value && !group.cities.has(value)) {
          group.cities.set(value, { label: city, value });
        }
      });
    });

    projects.forEach((project) => {
      const label = project.location?.trim();
      if (!label) return;

      const value = normalizeFilterValue(label);
      const isKnownLocation = locations.some(
        (location) =>
          normalizeFilterValue(location.state) === value ||
          location.cities.some((city) => normalizeFilterValue(city) === value),
      );

      if (!isKnownLocation) ensureGroup(label);
    });

    const countFor = (state: string, city = '') =>
      projects.filter((project) =>
        projectMatchesFilters(project, {
          searchTerm,
          yearFilter,
          statusFilter,
          locationFilter: state,
          cityFilter: city,
        }),
      ).length;

    return Array.from(groups.values())
      .sort((first, second) => first.label.localeCompare(second.label))
      .map((group) => ({
        label: group.label,
        value: group.value,
        count: countFor(group.value),
        ...(group.cities.size > 0
          ? {
              children: Array.from(group.cities.values())
                .sort((first, second) => first.label.localeCompare(second.label))
                .map((city) => ({ ...city, count: countFor(group.value, city.value) })),
            }
          : {}),
      }));
  }, [locations, projects, searchTerm, statusFilter, yearFilter]);

  const yearOptions = useMemo(
    () =>
      years.map((year) => ({
        label: String(year),
        value: String(year),
        count: projects.filter(
          (project) =>
            projectMatchesFilters(project, {
              searchTerm,
              statusFilter,
              locationFilter,
              cityFilter,
            }) && projectMatchesYear(project, String(year)),
        ).length,
      })),
    [cityFilter, locationFilter, projects, searchTerm, statusFilter, years],
  );

  // Filtered list
  const filtered = useMemo(
    () =>
      projects.filter((project) =>
        projectMatchesFilters(project, {
          searchTerm,
          yearFilter,
          statusFilter,
          locationFilter,
          cityFilter,
        }),
      ),
    [cityFilter, locationFilter, projects, searchTerm, statusFilter, yearFilter],
  );

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

  const hasActiveFilters = Boolean(
    searchTerm.trim() || statusFilter || locationFilter || cityFilter || yearFilter,
  );

  const clearAllFilters = () => {
    clearFilters();
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

          {/* Filter row */}
          <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
            <SearchInput
              value={searchTerm}
              onValueChange={resetFilters('searchTerm')}
              placeholder="Search projects"
              className="w-full lg:w-64 lg:flex-shrink-0"
              inputClassName="!h-10 !py-0"
            />
            <FilterDropdown
              value={statusFilter}
              onChange={resetFilters('statusFilter')}
              options={statusOptions}
              placeholder="Status"
              className="w-full lg:w-44 lg:flex-shrink-0"
              sortOptionsAlphabetically={false}
            />
            <HierarchicalLocationFilter
              label=""
              placeholder="Location"
              levelOneLabel="Location"
              value={{ state: locationFilter, city: cityFilter }}
              options={locationOptions}
              onChange={(selection) => {
                setFilters({
                  locationFilter: selection.state,
                  cityFilter: selection.city,
                });
              }}
              className="w-full lg:w-44 lg:flex-shrink-0"
            />
            <FilterDropdown
              value={yearFilter}
              onChange={resetFilters('yearFilter')}
              options={yearOptions}
              placeholder="Year"
              className="w-full lg:w-36 lg:flex-shrink-0"
              sortOptionsAlphabetically={false}
            />
            {hasActiveFilters && (
              <ClearFiltersButton onClick={clearAllFilters} className="w-full lg:w-auto" />
            )}
          </div>

          {hasActiveFilters && (
            <div className="mb-8 text-sm text-[#69727d]">
              <span>
                Showing {filtered.length} {filtered.length === 1 ? 'project' : 'projects'} matching
                your filters
              </span>
            </div>
          )}

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
                <ProjectCard key={project.id} project={project} showAdminActions={false} />
              ))}
            </div>
          ) : (
            <EmptyState
              icon={<FolderOpen strokeWidth={2.4} />}
              title="No projects found"
              description={
                hasActiveFilters
                  ? 'Try adjusting your search or filter.'
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
