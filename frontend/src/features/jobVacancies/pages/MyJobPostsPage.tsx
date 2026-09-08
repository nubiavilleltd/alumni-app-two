import { useEffect, useMemo, useState } from 'react';
import { BriefcaseBusiness, Plus, SearchX, SlidersHorizontal, Trash2, UserX } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { Button } from '@/shared/components/ui/Button';
import EmptyState from '@/shared/components/ui/EmptyState';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { AdvancedFiltersPanel } from '@/shared/components/ui/AdvancedFiltersPanel';
import { DeleteConfirmModal } from '@/features/events/components/DeleteConfirmModal';
import { toast } from '@/shared/components/ui/Toast';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useJobVacancies } from '../hooks/useJobVacancies';
import { useDeleteVacancy } from '../hooks/useManageVacancy';
import { matchesLocationPart } from '@/shared/utils/location';
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
  address: string;
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
    matchesLocationPart(job.address, filters.address) &&
    matchesLocationPart(job.city, filters.city) &&
    matchesLocationPart(job.state, filters.state)
  );
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
  const [currentPage, setCurrentPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [jobTypeFilter, setJobTypeFilter] = useState('');
  const [workplaceFilter, setWorkplaceFilter] = useState('');
  const [addressFilter, setAddressFilter] = useState('');
  const [cityFilter, setCityFilter] = useState('');
  const [stateFilter, setStateFilter] = useState('');
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
      address: addressFilter,
      city: cityFilter,
      state: stateFilter,
    }),
    [addressFilter, cityFilter, jobTypeFilter, search, stateFilter, statusFilter, workplaceFilter],
  );
  const filteredVacancies = useMemo(
    () => myVacancies.filter((job) => matchesMyJobPostFilters(job, filterState)),
    [filterState, myVacancies],
  );
  const facetOptions = useMemo(() => {
    const toOptions = (values: Array<string | undefined>) =>
      Array.from(new Set(values.filter(Boolean) as string[]))
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({ label: value, value }));

    return {
      addresses: toOptions(myVacancies.map((job) => job.address)),
      cities: toOptions(myVacancies.map((job) => job.city)),
      states: toOptions(myVacancies.map((job) => job.state)),
    };
  }, [myVacancies]);
  const activeAdvancedFilterCount = [
    statusFilter,
    jobTypeFilter,
    workplaceFilter,
    addressFilter,
    cityFilter,
    stateFilter,
  ].filter(Boolean).length;
  const hasActiveFilters = Boolean(search.trim() || activeAdvancedFilterCount);
  const totalPages = Math.max(1, Math.ceil(filteredVacancies.length / MY_JOB_POSTS_PER_PAGE));
  const visibleVacancies = filteredVacancies.slice(
    (currentPage - 1) * MY_JOB_POSTS_PER_PAGE,
    currentPage * MY_JOB_POSTS_PER_PAGE,
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  useEffect(() => {
    setCurrentPage(1);
  }, [
    addressFilter,
    cityFilter,
    jobTypeFilter,
    search,
    stateFilter,
    statusFilter,
    workplaceFilter,
  ]);

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
    setSearch('');
    setStatusFilter('');
    setJobTypeFilter('');
    setWorkplaceFilter('');
    setAddressFilter('');
    setCityFilter('');
    setStateFilter('');
    setCurrentPage(1);
  };

  const handleAdvancedFilterChange = (key: string, value: string) => {
    if (key === 'status') setStatusFilter(value);
    if (key === 'jobType') setJobTypeFilter(value);
    if (key === 'workplace') setWorkplaceFilter(value);
    if (key === 'address') setAddressFilter(value);
    if (key === 'city') setCityFilter(value);
    if (key === 'state') setStateFilter(value);
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
                    onValueChange={setSearch}
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
                    {
                      key: 'address',
                      kind: 'select',
                      label: 'Address',
                      value: addressFilter,
                      placeholder: 'All addresses',
                      options: facetOptions.addresses,
                    },
                    {
                      key: 'city',
                      kind: 'select',
                      label: 'City',
                      value: cityFilter,
                      placeholder: 'All cities',
                      options: facetOptions.cities,
                    },
                    {
                      key: 'state',
                      kind: 'select',
                      label: 'State',
                      value: stateFilter,
                      placeholder: 'All states',
                      options: facetOptions.states,
                    },
                  ]}
                  onFieldChange={handleAdvancedFilterChange}
                  onReset={clearAllFilters}
                  hasActiveFilters={activeAdvancedFilterCount > 0}
                />
              )}

              {hasActiveFilters && (
                <div className="mb-6 mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#69727d]">
                  <span>
                    Showing {filteredVacancies.length} of {myVacancies.length} job{' '}
                    {myVacancies.length === 1 ? 'post' : 'posts'}
                  </span>
                  <button
                    type="button"
                    onClick={clearAllFilters}
                    className="font-semibold text-primary-600 transition-colors hover:text-primary-700"
                  >
                    Clear all filters
                  </button>
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
