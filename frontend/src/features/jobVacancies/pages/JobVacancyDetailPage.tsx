import {
  BriefcaseBusiness,
  ExternalLink,
  Mail,
  MapPin,
  MessageCircle,
  SearchX,
  Share2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import EmptyState from '@/shared/components/ui/EmptyState';
import { EmailInstructionModal } from '@/shared/components/ui/EmailInstructionModal';
import { toast } from '@/shared/components/ui/Toast';
import { ROUTES } from '@/shared/constants/routes';
import { useJobVacancies } from '../hooks/useJobVacancies';
import type { JobVacancyViewModel } from '../api/adapters';
import { formatJobDate, getJobPillLabels, getSalaryDisplay } from '../utils/jobVacancyDisplay';
import { useStartDirectConversation } from '@/features/messages/hooks/useStartDirectConversation';

const DEFAULT_JOB_DRAFT_MESSAGE = (job: JobVacancyViewModel) =>
  `Hi, I'm interested in the ${job.title} vacancy at ${job.companyName}.`;

function getApplicationDetails(job: JobVacancyViewModel) {
  if (job.applicationMode === 'email') {
    return {
      href: job.applicationEmail ? `mailto:${job.applicationEmail}` : '#',
      label: job.applicationEmail,
      icon: Mail,
      target: undefined,
      rel: undefined,
    };
  }

  return {
    href: job.applicationUrl ?? '#',
    label: job.applicationUrl,
    icon: ExternalLink,
    target: '_blank',
    rel: 'noopener noreferrer',
  };
}

function splitTextList(value: string) {
  const lines = value
    .split(/\r?\n|;/)
    .map((item) => item.replace(/^[-*\u2022]\s*/, '').trim())
    .filter(Boolean);

  return lines.length > 1 ? lines : [];
}

function DetailSection({ title, content }: { title: string; content: string }) {
  const listItems = splitTextList(content);

  return (
    <section className="space-y-3">
      <h2 className="text-base font-bold text-[#071116] sm:text-lg">{title}</h2>
      {listItems.length > 0 ? (
        <ul className="list-disc space-y-1.5 pl-5 text-[15px] leading-7 text-slate-600 sm:text-base">
          {listItems.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      ) : (
        <p className="text-[15px] leading-7 text-slate-600 sm:text-base">{content}</p>
      )}
    </section>
  );
}

function JobDetailSkeleton() {
  return (
    <main className="min-h-screen bg-[#F8F8F7] py-4">
      <div className="mx-auto max-w-6xl animate-pulse space-y-8">
        <div className="h-56 rounded-[1.5rem] bg-white shadow-sm ring-1 ring-black/5" />
        <div className="h-96 rounded-[1.5rem] bg-white shadow-sm ring-1 ring-black/5" />
      </div>
    </main>
  );
}

export default function JobVacancyDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { data: vacancies = [], isLoading, isError, error, refetch } = useJobVacancies();
  const { startDirectConversation, isPending: isStartingConversation } =
    useStartDirectConversation();
  const [isMessagePending, setIsMessagePending] = useState(false);
  const [isEmailModalOpen, setIsEmailModalOpen] = useState(false);

  const job = useMemo(() => vacancies.find((vacancy) => vacancy.id === id), [id, vacancies]);

  const pillLabels = job ? getJobPillLabels(job) : [];
  const application = job ? getApplicationDetails(job) : null;
  const postedLabel = job?.createdAt
    ? `Posted on ${formatJobDate(job.createdAt)}`
    : 'Posted recently';

  const handleShare = async () => {
    if (typeof window === 'undefined') return;

    try {
      await navigator.clipboard.writeText(window.location.href);
      toast.success('Job link copied.');
    } catch {
      toast.info('Copy this page link from your browser address bar.');
    }
  };

  const handleMessagePoster = async () => {
    if (!job?.ownerId) return;

    setIsMessagePending(true);
    await startDirectConversation({
      participantMemberId: job.ownerId,
      topic: `Job vacancy enquiry about ${job.title}`,
      draftMessage: DEFAULT_JOB_DRAFT_MESSAGE(job),
      recipientProfile: {
        fullName: job.postedByName ?? 'Job poster',
        headline: `${job.companyName} vacancy poster`,
        location: [job.address, job.city, job.state].filter(Boolean).join(', ') || job.location,
        profileHref: `/alumni/profiles/${job.ownerId}`,
      },
    });
    setIsMessagePending(false);
  };

  if (isLoading) return <JobDetailSkeleton />;

  if (isError) {
    return (
      <main className="min-h-screen bg-[#F8F8F7] py-10">
        <SEO title="Job Details" description="View job vacancy details." />
        <EmptyState
          icon={SearchX}
          title="We couldn't load this job"
          description={error instanceof Error ? error.message : 'Please try again.'}
          actionLabel="Try Again"
          onAction={() => {
            void refetch();
          }}
        />
      </main>
    );
  }

  if (!job) {
    return (
      <main className="min-h-screen bg-[#F8F8F7] py-10">
        <SEO title="Job Not Found" description="The requested job vacancy could not be found." />
        <EmptyState
          icon={BriefcaseBusiness}
          title="Job not found"
          description="This vacancy may have been removed or is no longer available."
          actionLabel="Back to Job Vacancies"
          actionHref={ROUTES.JOB_VACANCIES}
        />
      </main>
    );
  }

  return (
    <>
      <SEO
        title={`${job.title} at ${job.companyName}`}
        description={`View details for ${job.title} at ${job.companyName}.`}
      />

      <main className="bg-[#F8F8F7] px-[var(--app-page-inline-padding)] py-10 text-[#071116] lg:flex lg:min-h-[calc(100dvh-10rem)] lg:flex-col">
        <div className="mx-auto w-full space-y-4 lg:flex lg:flex-col lg:flex-1 lg:space-y-4 lg:min-h-0">
          {/* Top card — fixed height on desktop, scrolls internally */}
          <section className="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-black/5 sm:p-6 lg:shrink-0 lg:overflow-y-auto">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-3">
                  <h1 className="text-xl font-bold tracking-tight text-[#071116] sm:text-[32px]">
                    {job.title}
                  </h1>
                  <span className="inline-flex rounded-xl bg-[#078e00]/25 px-3 py-2 text-sm">
                    <span className="text-[#4B5563]">Deadline: </span>
                    <span className="text-[#078E00] font-semibold ml-1">
                      {formatJobDate(job.postedAt)}
                    </span>
                  </span>
                </div>

                <div className="mt-2 space-y-1 text-slate-600">
                  <p className="text-[20px] font-[500]">{job.companyName}</p>
                  <p className="text-[24px] font-[600] text-[#4B5563] sm:text-xl">
                    {getSalaryDisplay(job)}
                  </p>
                  <p className="inline-flex items-center gap-1.5 text-sm font-semibold">
                    <MapPin className="h-4 w-4" strokeWidth={2.35} />
                    {[job.address, job.city, job.state].filter(Boolean).join(', ') || job.location}
                  </p>
                  <p className="text-sm font-medium text-slate-500">{postedLabel}</p>
                  {job.postedByName ? (
                    <p className="text-sm font-semibold text-slate-600">
                      Posted by {job.postedByName}
                    </p>
                  ) : null}
                </div>
              </div>

              <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                {application?.label ? (
                  job.applicationMode === 'email' ? (
                    <button
                      type="button"
                      onClick={() => setIsEmailModalOpen(true)}
                      className="inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-primary-600"
                    >
                      Apply
                      <Mail className="h-4 w-4" strokeWidth={2.35} />
                    </button>
                  ) : (
                    <a
                      href={application.href}
                      target={application.target}
                      rel={application.rel}
                      className="inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-primary-600"
                    >
                      Apply
                      <application.icon className="h-4 w-4" strokeWidth={2.35} />
                    </a>
                  )
                ) : null}
                {job.ownerId ? (
                  <button
                    type="button"
                    onClick={() => {
                      void handleMessagePoster();
                    }}
                    disabled={isStartingConversation || isMessagePending}
                    className="inline-flex items-center justify-center gap-2 rounded-full border border-primary-200 px-5 py-2 text-sm font-bold text-primary-600 transition-colors hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <MessageCircle className="h-4 w-4" strokeWidth={2.35} />
                    {isStartingConversation || isMessagePending ? 'Opening' : 'Message poster'}
                  </button>
                ) : null}
                <button
                  type="button"
                  onClick={handleShare}
                  className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-primary-200 text-primary-600 transition-colors hover:bg-primary-50"
                  aria-label="Share job"
                >
                  <Share2 className="h-4 w-4" strokeWidth={2.35} />
                </button>
              </div>
            </div>

            {pillLabels.length > 0 ? (
              <div className="mt-4 flex flex-wrap gap-2 lg:justify-end">
                {pillLabels.map((label) => (
                  <span
                    key={label}
                    className="rounded-xl bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-600"
                  >
                    {label}
                  </span>
                ))}
              </div>
            ) : null}
          </section>

          {/* Bottom card — fixed height with internal scrolling for longer job details */}
          <section className="h-[30rem] space-y-6 overflow-y-auto rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-black/5 sm:p-6 lg:flex-none">
            {job.flyer ? (
              <section className="space-y-3">
                <h2 className="text-base font-bold text-[#071116] sm:text-lg">Job Flyer</h2>
                <img
                  src={job.flyer}
                  alt={`${job.title} flyer`}
                  className="max-h-[28rem] w-full rounded-2xl object-cover"
                />
              </section>
            ) : null}

            <DetailSection title="About this Role" content={job.aboutRole} />
            <DetailSection title="Responsibilities" content={job.responsibilities} />
            <DetailSection title="Requirements" content={job.requirements} />

            {application?.label ? (
              <section className="space-y-3 border-t border-slate-100 pt-6">
                <h2 className="text-base font-bold text-[#071116] sm:text-lg">How to Apply</h2>
                <p className="text-[15px] leading-7 text-slate-600 sm:text-base">
                  {job.applicationMode === 'email'
                    ? 'Send your application to the email address below.'
                    : 'Use the application link below to continue.'}
                </p>
                {job.applicationMode === 'email' ? (
                  <button
                    type="button"
                    onClick={() => setIsEmailModalOpen(true)}
                    className="inline-flex max-w-full items-center gap-2 rounded-full bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-100"
                  >
                    <Mail className="h-4 w-4 shrink-0" strokeWidth={2.35} />
                    <span className="truncate">{application.label}</span>
                  </button>
                ) : (
                  <a
                    href={application.href}
                    target={application.target}
                    rel={application.rel}
                    className="inline-flex max-w-full items-center gap-2 rounded-full bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-100"
                  >
                    <application.icon className="h-4 w-4 shrink-0" strokeWidth={2.35} />
                    <span className="truncate">{application.label}</span>
                  </a>
                )}
              </section>
            ) : null}
          </section>
        </div>
      </main>

      <EmailInstructionModal
        isOpen={isEmailModalOpen}
        onClose={() => setIsEmailModalOpen(false)}
        email={job.applicationEmail ?? ''}
        title="Send your application"
        description={`Send your application for ${job.title} to:`}
      />
    </>
  );
}
