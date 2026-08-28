import { useEffect, useMemo, useState } from 'react';
import { BriefcaseBusiness, Plus, SearchX, Trash2, UserX } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { Button } from '@/shared/components/ui/Button';
import EmptyState from '@/shared/components/ui/EmptyState';
import { Pagination } from '@/shared/components/ui/Pagination';
import { DeleteConfirmModal } from '@/features/events/components/DeleteConfirmModal';
import { toast } from '@/shared/components/ui/Toast';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useJobVacancies } from '../hooks/useJobVacancies';
import { useDeleteVacancy } from '../hooks/useManageVacancy';
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

  const myVacancies = useMemo(
    () =>
      vacancies
        .filter((job) => job.ownerId && ownerIds.has(job.ownerId))
        .sort((a, b) => new Date(b.postedAt).getTime() - new Date(a.postedAt).getTime()),
    [ownerIds, vacancies],
  );
  const totalPages = Math.max(1, Math.ceil(myVacancies.length / MY_JOB_POSTS_PER_PAGE));
  const visibleVacancies = myVacancies.slice(
    (currentPage - 1) * MY_JOB_POSTS_PER_PAGE,
    currentPage * MY_JOB_POSTS_PER_PAGE,
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

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
