

import { useEffect, useMemo, useState } from 'react';
import { FolderOpen } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { Pagination } from '@/shared/components/ui/Pagination';
import EmptyState from '@/shared/components/ui/EmptyState';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';

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
                searchTerm || yearFilter
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
