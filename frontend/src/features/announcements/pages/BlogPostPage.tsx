import { ArrowLeft, Clock3, FileSearch, LoaderCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import { AppLink } from '@/shared/components/ui/AppLink';
import { AnnouncementEditorModal } from '@/features/announcements/components/AnnouncementEditorModal';
import {
  useAnnouncement,
  useBirthdayAnnouncements,
  useDeleteAnnouncement,
} from '@/features/announcements/hooks/useAnnouncements';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';

const FALLBACK_IMAGE = '/news-1.png';
const pageShellClassName = 'container-custom pb-16 pt-4 sm:pb-14 sm:pt-5';

function formatAnnouncementDate(date?: string) {
  if (!date) return '';

  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(date);
  const parsed = new Date(isDateOnly ? `${date}T00:00:00` : date);
  if (Number.isNaN(parsed.getTime())) return date;

  if (isDateOnly) {
    return parsed.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  return parsed.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function splitAnnouncementContent(content?: string, excerpt?: string) {
  const source = content?.trim() || excerpt?.trim() || '';
  if (!source) return [];

  return source
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean);
}

export default function BlogPostPage() {
  const { slug = '' } = useParams();
  const navigate = useNavigate();
  const user = useIdentityStore((state) => state.user);
  const { data: announcement, isLoading } = useAnnouncement(slug);
 
  const deleteAnnouncement = useDeleteAnnouncement();
  const [isEditorOpen, setIsEditorOpen] = useState(false);

  const bodyParagraphs = splitAnnouncementContent(announcement?.content, announcement?.excerpt);
  const isAdmin = user?.role === 'admin';

  async function handleDeleteAnnouncement() {
    if (!announcement || deleteAnnouncement.isPending) {
      return;
    }

    const shouldDelete = window.confirm(
      `Delete "${announcement.title}"? This action cannot be undone.`,
    );

    if (!shouldDelete) {
      return;
    }

    await deleteAnnouncement.mutateAsync(String(announcement.id));
    navigate(ANNOUNCEMENT_ROUTES.ROOT, { replace: true });
  }

  return (
    <>
      <SEO
        title={announcement?.title || 'Announcement'}
        description={
          announcement?.excerpt || 'Read the latest announcement from the alumni community.'
        }
        image={announcement?.image}
      />

      <main className="announcements-page bg-[#F8F8F7]">
        <section className={pageShellClassName}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <AppLink
              href={ANNOUNCEMENT_ROUTES.ROOT}
              className="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700"
            >
              <ArrowLeft className="h-4 w-4" strokeWidth={2.4} />
              Back to announcements
            </AppLink>

            {isAdmin && announcement && (
              <div className="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  onClick={() => setIsEditorOpen(true)}
                  disabled={deleteAnnouncement.isPending}
                  className="inline-flex items-center gap-1.5 rounded-full border-2 border-primary-500 bg-white px-3 py-2 text-xs font-semibold text-primary-500 shadow-sm transition-colors hover:bg-primary-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-60 sm:px-4 sm:text-sm whitespace-nowrap"
                >
                  Edit Announcement
                </button>

                <button
                  type="button"
                  onClick={() => void handleDeleteAnnouncement()}
                  disabled={deleteAnnouncement.isPending}
                  className="inline-flex items-center gap-1.5 rounded-full border-2 border-red-500 bg-white px-3 py-2 text-xs font-semibold text-red-500 shadow-sm transition-colors hover:bg-red-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-60 sm:px-4 sm:text-sm whitespace-nowrap"
                >
                  {deleteAnnouncement.isPending ? (
                    <LoaderCircle className="h-4 w-4 animate-spin" strokeWidth={2.4} />
                  ) : (
                    <Trash2 className="h-4 w-4" strokeWidth={2.4} />
                  )}
                  <span>
                    {deleteAnnouncement.isPending ? 'Deleting...' : 'Delete Announcement'}
                  </span>
                </button>
              </div>
            )}
          </div>

          {isLoading ? (
            <div className="mt-6 animate-pulse">
              <div className="aspect-[16/8] rounded-t-[2rem] bg-accent-100" />
              <div className="space-y-4 py-6 md:py-8">
                <div className="h-4 w-40 rounded bg-accent-100" />
                <div className="h-10 w-3/4 rounded bg-accent-100" />
                <div className="h-4 w-full rounded bg-accent-100" />
                <div className="h-4 w-5/6 rounded bg-accent-100" />
                <div className="h-4 w-4/6 rounded bg-accent-100" />
              </div>
            </div>
          ) : !announcement ? (
            <div className="mt-6 rounded-[2rem] bg-white p-10 text-center shadow-sm ring-1 ring-accent-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-50">
                <FileSearch className="h-7 w-7 text-accent-700" strokeWidth={2.2} />
              </div>
              <h1 className="mt-4 text-2xl font-bold text-accent-900">Announcement not found</h1>
              <p className="mt-2 text-sm text-accent-500">
                It may have been removed or the link may no longer be valid.
              </p>
            </div>
          ) : (
            <article className="mt-6">
              <div className="overflow-hidden rounded-t-[2rem] bg-accent-100">
                <img
                  src={announcement.image || FALLBACK_IMAGE}
                  alt=""
                  className="block max-h-[36rem] w-full object-fit"
                />
              </div>

              <div className="py-6 md:py-8">
                <div className="flex flex-wrap items-center gap-3">
                  <span className="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-primary-700">
                    {announcement.type}
                  </span>
                  <span className="inline-flex items-center gap-2 text-sm text-accent-500">
                    <Clock3 className="h-4 w-4" strokeWidth={2.2} />
                    {formatAnnouncementDate(announcement.startsAt || announcement.date)}
                  </span>
                </div>

                <h1 className="type-section-title mt-4 text-accent-950">{announcement.title}</h1>

                {announcement.excerpt && (
                  <p className="mt-4 max-w-3xl text-lg leading-8 text-accent-600">
                    {announcement.excerpt}
                  </p>
                )}

                {(announcement.year || announcement.endsAt) && (
                  <div className="mt-6 grid gap-3 rounded-2xl bg-accent-50 p-4 text-sm text-accent-700 md:grid-cols-2">
                    {announcement.year && (
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-accent-400">
                          Year
                        </p>
                        <p className="mt-1 font-medium">{announcement.year}</p>
                      </div>
                    )}
                    {announcement.endsAt && (
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-accent-400">
                          Ends
                        </p>
                        <p className="mt-1 font-medium">
                          {formatAnnouncementDate(announcement.endsAt)}
                        </p>
                      </div>
                    )}
                  </div>
                )}

                <div className="mt-8 space-y-5 text-base leading-8 text-accent-700">
                  {bodyParagraphs.length > 0 ? (
                    bodyParagraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)
                  ) : (
                    <p>This announcement does not have a full body yet.</p>
                  )}
                </div>
              </div>
            </article>
          )}
        </section>
      </main>

      <AnnouncementEditorModal
        announcement={announcement}
        isOpen={isEditorOpen}
        onClose={() => setIsEditorOpen(false)}
        onSubmitted={(updatedAnnouncement) => {
          navigate(ANNOUNCEMENT_ROUTES.DETAIL(updatedAnnouncement.slug), { replace: true });
        }}
      />
    </>
  );
}
