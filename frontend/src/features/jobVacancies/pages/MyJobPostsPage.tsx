import { useEffect, useMemo, useState } from 'react';
import { BriefcaseBusiness, Plus, SearchX, SlidersHorizontal, Trash2, UserX } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { Button } from '@/shared/components/ui/Button';
import EmptyState from '@/shared/components/ui/EmptyState';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import {
  HierarchicalLocationFilter,
  type HierarchicalLocationNode,
  type HierarchicalLocationSelection,
} from '@/shared/components/ui/HierarchicalLocationFilter';
import { DeleteConfirmModal } from '@/features/events/components/DeleteConfirmModal';
import { toast } from '@/shared/components/ui/Toast';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useJobVacancies } from '../hooks/useJobVacancies';
import { useDeleteVacancy } from '../hooks/useManageVacancy';
import { matchesLocationPart, normalizeLocationPart } from '@/shared/utils/location';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import type { JobVacancyViewModel } from '../api/adapters';
import {
  getTone,
  JobCard,
  JobsLoadingState,
  jobsGridClassName,
  jobsPageHeaderClassName,
  jobsPagePostButtonClassName,
  jobsPageShellClassName,
  jobsPageSubtitleClassName,
  jobsPageTitleClassName,
  PostJobModal,
} from './JobVacanciesPage';

const myJobCardEditButtonClassName =
  'min-h-[2.85rem] rounded-full bg-primary-500 px-6 py-2 text-[0.95rem] font-extrabold leading-none text-white transition-colors hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200';
const myJobCardDeleteActionClassName =
  'inline-flex h-10 w-10 items-center justify-center rounded-full bg-transparent text-[#c81e1e] transition-colors hover:bg-red-50 hover:text-[#ab1b1b] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-100';
const MY_JOB_POSTS_PER_PAGE = 8;

type MyJobPostFilterState = {
  search: string;
  status: string;
  jobType: string;
  workplace: string;
  city: string;
  state: string;
};

const JOB_POST_STATUS_OPTIONS = [
  { label: 'Active posts', value: 'active' },
  { label: 'Expired posts', value: 'expired' },
];

function isExpiredJobPost(job: JobVacancyViewModel) {
  const deadline = new Date(job.postedAt);
  return !Number.isNaN(deadline.getTime()) && deadline < new Date();
}

function matchesMyJobPostFilters(job: JobVacancyViewModel, filters: MyJobPostFilterState) {
  const query = filters.search.trim().toLowerCase();
  const searchableFields = [
    job.title,
    job.companyName,
    job.location,
    job.address ?? '',
    job.city ?? '',
    job.state ?? '',
    job.jobType,
    job.workplaceType,
    job.levelOfExpertise,
    ...job.tags,
  ];

  return (
    (!query || searchableFields.some((field) => field.toLowerCase().includes(query))) &&
    (!filters.status ||
      (filters.status === 'expired' ? isExpiredJobPost(job) : !isExpiredJobPost(job))) &&
    (!filters.jobType || job.jobType === filters.jobType) &&
    (!filters.workplace || job.workplaceType === filters.workplace) &&
    matchesLocationPart(job.city, filters.city) &&
    matchesLocationPart(job.state, filters.state)
  );
}

type MutableLocationNode = {
  label: string;
  value: string;
  children: Map<string, MutableLocationNode>;
};

function buildMyJobPostLocationHierarchy(
  jobs: JobVacancyViewModel[],
  filters: MyJobPostFilterState,
): HierarchicalLocationNode[] {
  const states = new Map<string, MutableLocationNode>();
  const filtersWithoutLocation: MyJobPostFilterState = {
    ...filters,
    city: '',
    state: '',
  };

  jobs.forEach((job) => {
    const stateLabel = job.state.trim();
    const stateValue = normalizeLocationPart(stateLabel);
    if (!stateLabel || !stateValue) return;

    let stateNode = states.get(stateValue);
    if (!stateNode) {
      stateNode = { label: stateLabel, value: stateValue, children: new Map() };
      states.set(stateValue, stateNode);
    }

    const cityLabel = job.city.trim();
    const cityValue = normalizeLocationPart(cityLabel);
    if (!cityLabel || !cityValue || stateNode.children.has(cityValue)) return;

    stateNode.children.set(cityValue, {
      label: cityLabel,
      value: cityValue,
      children: new Map(),
    });
  });

  const countForLocation = (location: HierarchicalLocationSelection) =>
    jobs.filter((job) =>
      matchesMyJobPostFilters(job, {
        ...filtersWithoutLocation,
        ...location,
      }),
    ).length;

  const toOptions = (
    nodes: Map<string, MutableLocationNode>,
    parent: HierarchicalLocationSelection,
  ): HierarchicalLocationNode[] =>
    Array.from(nodes.values())
      .sort((first, second) => first.label.localeCompare(second.label))
      .map((node) => {
        const selection =
          nodes === states
            ? { state: node.value, city: '' }
            : { state: parent.state, city: node.value };
        const children = Array.from(node.children.values()).length
          ? toOptions(node.children, selection)
          : undefined;

        return {
          label: node.label,
          value: node.value,
          count: countForLocation(selection),
          ...(children ? { children } : {}),
        };
      });

  return toOptions(states, { state: '', city: '' });
}

function useCurrentOwnerIds() {
  const user = useIdentityStore((state) => state.user);

  return useMemo(
    () =>
      new Set(
        [user?.id, user?.memberId].filter((value): value is string => Boolean(value)).map(String),
      ),
    [user?.id, user?.memberId],
  );
}

export default function MyJobPostsPage() {
  const user = useIdentityStore((state) => state.user);
  const ownerIds = useCurrentOwnerIds();
  const { data: vacancies = [], isLoading, isError, error, refetch } = useJobVacancies();
  const deleteVacancy = useDeleteVacancy();

  const [isPostModalOpen, setIsPostModalOpen] = useState(false);
  const [editingJob, setEditingJob] = useState<JobVacancyViewModel | null>(null);
  const [jobToDelete, setJobToDelete] = useState<JobVacancyViewModel | null>(null);
  const [currentPage, setCurrentPage] = useUrlPagination();
  const { filters, setFilter, setFilters, clearFilters } = usePersistedFilters(
    'my-job-posts-filters',
    {
      search: '',
      statusFilter: '',
      jobTypeFilter: '',
      workplaceFilter: '',
      cityFilter: '',
      stateFilter: '',
    },
  );
  const { search, statusFilter, jobTypeFilter, workplaceFilter, cityFilter, stateFilter } = filters;
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);

  const myVacancies = useMemo(
    () =>
      vacancies
        .filter((job) => job.ownerId && ownerIds.has(job.ownerId))
        .sort((a, b) => new Date(b.postedAt).getTime() - new Date(a.postedAt).getTime()),
    [ownerIds, vacancies],
  );
  const filterState = useMemo<MyJobPostFilterState>(
    () => ({
      search,
      status: statusFilter,
      jobType: jobTypeFilter,
      workplace: workplaceFilter,
      city: cityFilter,
      state: stateFilter,
    }),
    [cityFilter, jobTypeFilter, search, stateFilter, statusFilter, workplaceFilter],
  );
  const filteredVacancies = useMemo(
    () => myVacancies.filter((job) => matchesMyJobPostFilters(job, filterState)),
    [filterState, myVacancies],
  );
  const locationHierarchy = useMemo(
    () => buildMyJobPostLocationHierarchy(myVacancies, filterState),
    [filterState, myVacancies],
  );
  const activeAdvancedFilterCount = [statusFilter, jobTypeFilter, workplaceFilter].filter(
    Boolean,
  ).length;
  const hasLocationFilter = Boolean(cityFilter || stateFilter);
  const hasActiveFilters = Boolean(search.trim() || activeAdvancedFilterCount || hasLocationFilter);
  const totalPages = Math.max(1, Math.ceil(filteredVacancies.length / MY_JOB_POSTS_PER_PAGE));
  const visibleVacancies = filteredVacancies.slice(
    (currentPage - 1) * MY_JOB_POSTS_PER_PAGE,
    currentPage * MY_JOB_POSTS_PER_PAGE,
  );

  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const handleDeleteVacancy = async () => {
    if (!jobToDelete) return;

    try {
      await deleteVacancy.mutateAsync({ id: jobToDelete.id });
      toast.success('Job vacancy deleted successfully.');
      setJobToDelete(null);
    } catch (deleteError: any) {
      toast.fromError(deleteError);
    }
  };

  const handleEditJob = (job: JobVacancyViewModel) => {
    setEditingJob(job);
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const clearAllFilters = () => {
    clearFilters();
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    const filterKeys: Record<string, keyof typeof filters> = {
      status: 'statusFilter',
      jobType: 'jobTypeFilter',
      workplace: 'workplaceFilter',
    };
    const filterKey = filterKeys[key];
    if (filterKey) setFilter(filterKey, value);
  };

  const hasOwnerIdentity = ownerIds.size > 0;

  return (
    <>
      <SEO
        title="My Job Posts"
        description="Manage the job vacancies you shared with the Alumni Portal."
      />

      <main className="min-h-full bg-[#F8F8F7] text-[#071116]">
        <section className={jobsPageShellClassName} aria-labelledby="my-job-posts-title">
          <header className={jobsPageHeaderClassName}>
            <div>
              <h1 id="my-job-posts-title" className={jobsPageTitleClassName}>
                My Job Posts
              </h1>
              <p className={jobsPageSubtitleClassName}>
                Manage the job vacancies you shared with the community.
              </p>
            </div>

            <Button
              type="button"
              size="lg"
              className={jobsPagePostButtonClassName}
              onClick={() => setIsPostModalOpen(true)}
            >
              Post a Job
              <Plus strokeWidth={2.35} />
            </Button>
          </header>

          {!isLoading && !isError && myVacancies.length > 0 && (
            <>
              <div className="mb-5 flex w-full items-center gap-3">
                <div className="min-w-0 flex-1 sm:max-w-xl">
                  <SearchInput
                    value={search}
                    onValueChange={(value) => setFilter('search', value)}
                    placeholder="Search your job posts"
                    inputClassName="!h-10 !py-0"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setShowAdvancedFilters((isVisible) => !isVisible)}
                  aria-expanded={showAdvancedFilters}
                  className="flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:bg-gray-50 sm:px-4 sm:text-sm"
                >
                  <SlidersHorizontal className="h-4 w-4" />
                  <span className="hidden sm:inline">Advanced filters</span>
                  {activeAdvancedFilterCount > 0 && (
                    <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] text-white">
                      {activeAdvancedFilterCount}
                    </span>
                  )}
                </button>
              </div>

              {showAdvancedFilters && (
                <AdvancedFiltersPanel
                  title="Refine your job posts"
                  description="Find your vacancies by status, employment type, workplace, or location."
                  customContent={
                    <HierarchicalLocationFilter
                      label="Location"
                      placeholder="All locations"
                      value={{ state: stateFilter, city: cityFilter }}
                      options={locationHierarchy}
                      onChange={(selection) => {
                        setFilters({
                          stateFilter: selection.state,
                          cityFilter: selection.city,
                        });
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
                      options: JOB_POST_STATUS_OPTIONS,
                    },
                    {
                      key: 'jobType',
                      kind: 'select',
                      label: 'Job type',
                      value: jobTypeFilter,
                      placeholder: 'All job types',
                      options: [
                        { label: 'Full time', value: 'full_time' },
                        { label: 'Part time', value: 'part_time' },
                        { label: 'Contract', value: 'contract' },
                        { label: 'Internship', value: 'internship' },
                        { label: 'Freelance', value: 'freelance' },
                      ],
                    },
                    {
                      key: 'workplace',
                      kind: 'select',
                      label: 'Workplace',
                      value: workplaceFilter,
                      placeholder: 'All workplaces',
                      options: [
                        { label: 'Remote', value: 'remote' },
                        { label: 'Hybrid', value: 'hybrid' },
                        { label: 'On site', value: 'on_site' },
                      ],
                    },
                  ]}
                  onFieldChange={handleAdvancedFilterChange}
                  onReset={clearAllFilters}
                  hasActiveFilters={activeAdvancedFilterCount > 0 || hasLocationFilter}
                />
              )}

              {hasActiveFilters && (
                <div className="mb-6 mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                  <span>
                    Showing {filteredVacancies.length} of {myVacancies.length} job{' '}
                    {myVacancies.length === 1 ? 'post' : 'posts'}
                  </span>
                  <ClearFiltersButton onClick={clearAllFilters} label="Clear all filters" />
                </div>
              )}
            </>
          )}

          {isLoading ? <JobsLoadingState /> : null}

          {!isLoading && isError ? (
            <EmptyState
              icon={SearchX}
              title="We couldn't load your job posts"
              description={error instanceof Error ? error.message : 'Please try again.'}
              actionLabel="Try Again"
              onAction={() => {
                void refetch();
              }}
            />
          ) : null}

          {!isLoading && !isError && !hasOwnerIdentity ? (
            <EmptyState
              icon={UserX}
              title="We couldn't identify your account"
              description="Please sign out and sign back in, then try again."
            />
          ) : null}

          {!isLoading && !isError && hasOwnerIdentity && myVacancies.length === 0 ? (
            <EmptyState
              icon={BriefcaseBusiness}
              title="No job posts yet"
              description="Jobs you post will appear here so you can edit or delete them."
              actionLabel="Post a Job"
              onAction={() => setIsPostModalOpen(true)}
            />
          ) : null}

          {!isLoading && !isError && myVacancies.length > 0 ? (
            <>
              {filteredVacancies.length > 0 ? (
                <>
                  <div className={jobsGridClassName}>
                    {visibleVacancies.map((job, index) => (
                      <JobCard
                        key={job.id}
                        job={job}
                        tone={getTone((currentPage - 1) * MY_JOB_POSTS_PER_PAGE + index)}
                        primaryAction={
                          <button
                            type="button"
                            className={myJobCardEditButtonClassName}
                            onClick={() => handleEditJob(job)}
                          >
                            Edit
                          </button>
                        }
                        panelAction={
                          <button
                            type="button"
                            className={myJobCardDeleteActionClassName}
                            onClick={() => setJobToDelete(job)}
                            aria-label={`Delete ${job.title}`}
                            title="Delete job"
                          >
                            <Trash2 className="h-[1.25rem] w-[1.25rem]" strokeWidth={2.35} />
                          </button>
                        }
                      />
                    ))}
                  </div>
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={handlePageChange}
                  />
                </>
              ) : (
                <EmptyState
                  icon={SearchX}
                  title="No matching job posts"
                  description="Try changing your search or filters."
                  actionLabel="Clear filters"
                  onAction={clearAllFilters}
                />
              )}
            </>
          ) : null}
        </section>
      </main>

      {isPostModalOpen ? (
        <PostJobModal
          chapterId={user?.chapterId}
          onClose={() => setIsPostModalOpen(false)}
          onSubmitted={() => setIsPostModalOpen(false)}
        />
      ) : null}

      {editingJob ? (
        <PostJobModal
          chapterId={user?.chapterId}
          editData={editingJob}
          onClose={() => setEditingJob(null)}
          onSubmitted={() => setEditingJob(null)}
        />
      ) : null}

      {jobToDelete ? (
        <DeleteConfirmModal
          title={jobToDelete.title}
          isDeleting={deleteVacancy.isPending}
          onCancel={() => setJobToDelete(null)}
          onConfirm={() => {
            void handleDeleteVacancy();
          }}
          heading="Delete Vacancy?"
          description={`Are you sure you want to delete "${jobToDelete.title}"? This action cannot be undone.`}
        />
      ) : null}
    </>
  );
}
