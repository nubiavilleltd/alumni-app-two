import { MegaphoneOff, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { SEO } from '@/shared/common/SEO';
import { ButtonLink } from '@/shared/components/ui/Button';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { AdminBanner } from '@/features/admin/components/AdminBanner';
import { DeleteConfirmModal } from '@/features/events/components/DeleteConfirmModal';
import {
  useAnnouncements,
  useDeleteAnnouncement,
} from '@/features/announcements/hooks/useAnnouncements';
import { AnnouncementEditorModal } from '@/features/announcements/components/AnnouncementEditorModal';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';
import type { AnnouncementType, NewsItem } from '@/features/announcements/types/announcement.types';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';

type SortDirection = 'newest' | 'oldest';
const ADMIN_ANNOUNCEMENTS_PER_PAGE = 6;

const announcementTypeOptions = [
  { label: 'Info', value: 'info' },
  { label: 'Event', value: 'event' },
] as const;

const filterOptions = [{ label: 'All types', value: 'all' }, ...announcementTypeOptions] as const;
const sortOptions = [
  { label: 'Newest first', value: 'newest' },
  { label: 'Oldest first', value: 'oldest' },
] as const;

function formatAnnouncementDate(date?: string) {
  if (!date) return 'Not scheduled';

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

function buildSummary(item: NewsItem) {
  return item.excerpt?.trim() || item.content?.trim() || 'No summary provided yet.';
}

function typeBadgeClass(type: AnnouncementType) {
  switch (type) {
    case 'event':
      return 'bg-blue-100 text-blue-800';
    case 'info':
    default:
      return 'bg-accent-100 text-accent-800';
  }
}

export function AdminAnnouncementsPage() {
  const { data: announcements = [], isLoading } = useAnnouncements();
  const deleteAnnouncement = useDeleteAnnouncement();

  const { filters, setFilter } = usePersistedFilters('admin-announcements-filters', {
    searchQuery: '',
    selectedType: 'all' as 'all' | AnnouncementType,
    sortDirection: 'newest' as SortDirection,
  });
  const { searchQuery, selectedType, sortDirection } = filters;
  const [editorOpen, setEditorOpen] = useState(false);
  const [editingAnnouncement, setEditingAnnouncement] = useState<NewsItem | null>(null);
  const [announcementToDelete, setAnnouncementToDelete] = useState<NewsItem | null>(null);
  const [currentPage, setCurrentPage] = useUrlPagination();

  const sortedAnnouncements = useMemo(() => {
    const getCreatedTime = (item: NewsItem) => {
      const timestamp = new Date(item.date).getTime();
      return Number.isNaN(timestamp) ? 0 : timestamp;
    };

    return [...announcements].sort((a, b) => {
      const difference = getCreatedTime(b) - getCreatedTime(a);
      return sortDirection === 'oldest' ? -difference : difference;
    });
  }, [announcements, sortDirection]);

  const filteredAnnouncements = useMemo(() => {
    const query = searchQuery.trim().toLowerCase();

    return sortedAnnouncements.filter((item) => {
      const matchesType = selectedType === 'all' || item.type === selectedType;
      if (!matchesType) return false;

      if (!query) return true;

      return (
        item.title.toLowerCase().includes(query) ||
        (item.content ?? '').toLowerCase().includes(query) ||
        (item.excerpt ?? '').toLowerCase().includes(query)
      );
    });
  }, [searchQuery, selectedType, sortedAnnouncements]);
  const totalPages = Math.max(
    1,
    Math.ceil(filteredAnnouncements.length / ADMIN_ANNOUNCEMENTS_PER_PAGE),
  );
  const visibleAnnouncements = filteredAnnouncements.slice(
    (currentPage - 1) * ADMIN_ANNOUNCEMENTS_PER_PAGE,
    currentPage * ADMIN_ANNOUNCEMENTS_PER_PAGE,
  );

  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const eventCount = sortedAnnouncements.filter((item) => item.type === 'event').length;
  const scheduledCount = sortedAnnouncements.filter((item) => item.startsAt || item.endsAt).length;

  const handleDelete = async () => {
    if (!announcementToDelete) return;
    await deleteAnnouncement.mutateAsync(String(announcementToDelete.id));
    setAnnouncementToDelete(null);
  };

  return (
    <>
      <SEO title="Manage Announcements" description="Create and manage community announcements." />
      <AdminBanner activeTab="announcements" title="Announcements" />

      <section className="min-h-screen bg-[#F8F8F7] py-8 sm:py-10">
        <div className="container-custom w-full space-y-8">
          <div className="flex justify-end">
            <div className="flex flex-wrap gap-3">
              <ButtonLink href={ANNOUNCEMENT_ROUTES.ROOT} variant="outline">
                View public page
              </ButtonLink>
              <button
                type="button"
                onClick={() => {
                  setEditingAnnouncement(null);
                  setEditorOpen(true);
                }}
                className="flex w-full flex-shrink-0 items-center justify-center gap-1.5 rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600 sm:w-auto"
              >
                Create announcement
                <Plus className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-accent-100">
              <p className="text-sm font-medium text-accent-500">Total announcements</p>
              <p className="mt-3 text-3xl font-bold text-accent-950">
                {sortedAnnouncements.length}
              </p>
            </div>
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-accent-100">
              <p className="text-sm font-medium text-accent-500">Event updates</p>
              <p className="mt-3 text-3xl font-bold text-accent-950">{eventCount}</p>
            </div>
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-accent-100">
              <p className="text-sm font-medium text-accent-500">Scheduled announcements</p>
              <p className="mt-3 text-3xl font-bold text-accent-950">{scheduledCount}</p>
            </div>
          </div>

          <div>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
              <div className="flex-1">
                <label htmlFor="admin-announcements-search" className="sr-only">
                  Search announcements by title or content
                </label>
                <SearchInput
                  id="admin-announcements-search"
                  value={searchQuery}
                  onValueChange={(value) => {
                    setFilter('searchQuery', value);
                  }}
                  placeholder="Search by title or content..."
                  className="w-full"
                  inputClassName="!h-[56px] !border-0 !shadow-none focus:!ring-0"
                />
              </div>

              <div className="flex flex-wrap items-start gap-2">
                {filterOptions.map((filter) => (
                  <button
                    key={filter.value}
                    type="button"
                    onClick={() => {
                      setFilter(
                        'selectedType',
                        filter.value as 'all' | AnnouncementType,
                      );
                    }}
                    className={`rounded-full px-4 py-2 text-sm font-semibold transition-colors ${
                      selectedType === filter.value
                        ? 'bg-primary-500 text-white'
                        : 'bg-accent-50 text-accent-700 hover:bg-primary-50'
                    }`}
                  >
                    {filter.label}
                  </button>
                ))}

                <div className="min-w-[11.5rem] flex-1 sm:flex-none">
                  <SelectInput
                    id="announcement-sort-direction"
                    options={sortOptions}
                    value={sortDirection}
                    onChange={(event) => {
                      setFilter('sortDirection', event.target.value as SortDirection);
                    }}
                  />
                </div>
              </div>
            </div>
          </div>

          {isLoading ? (
            <div className="grid gap-4 lg:grid-cols-2">
              {Array.from({ length: 4 }).map((_, index) => (
                <div
                  key={index}
                  className="h-48 animate-pulse rounded-2xl bg-white shadow-sm ring-1 ring-accent-100"
                />
              ))}
            </div>
          ) : filteredAnnouncements.length === 0 ? (
            <div className="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-accent-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-50">
                <MegaphoneOff className="h-7 w-7 text-accent-700" />
              </div>
              <h2 className="mt-4 text-2xl font-bold text-accent-950">No matching announcements</h2>
              <p className="mt-2 text-sm text-accent-500">
                Try a different filter, or create a new announcement to get started.
              </p>
            </div>
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              {visibleAnnouncements.map((item) => (
                <article
                  key={item.slug}
                  className="h-full overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-accent-100"
                >
                  <div className="grid gap-0 lg:h-full lg:grid-cols-[240px_1fr] lg:items-stretch">
                    <div className="h-52 bg-accent-100 lg:h-full">
                      <img src={item.image} alt="" className="h-full w-full object-cover" />
                    </div>

                    <div className="flex h-full flex-col gap-5 p-5">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span
                            className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] ${typeBadgeClass(item.type)}`}
                          >
                            {item.type}
                          </span>
                          <span className="text-sm text-accent-500">
                            {formatAnnouncementDate(item.startsAt || item.date)}
                          </span>
                          {item.chapterId && (
                            <span className="rounded-full bg-accent-50 px-3 py-1 text-xs font-medium text-accent-700">
                              Chapter {item.chapterId}
                            </span>
                          )}
                          {item.year && (
                            <span className="rounded-full bg-accent-50 px-3 py-1 text-xs font-medium text-accent-700">
                              Year {item.year}
                            </span>
                          )}
                        </div>

                        <h2 className="mt-4 text-2xl font-bold text-accent-950">{item.title}</h2>
                        <p className="mt-3 text-sm leading-7 text-accent-600">
                          {buildSummary(item)}
                        </p>
                      </div>

                      <div className="mt-auto flex flex-wrap items-center justify-between gap-3">
                        <ButtonLink
                          href={ANNOUNCEMENT_ROUTES.DETAIL(item.slug)}
                          variant="outline"
                          size="sm"
                        >
                          View public page
                        </ButtonLink>

                        <div className="flex flex-wrap gap-2">
                          <button
                            type="button"
                            onClick={() => {
                              setEditingAnnouncement(item);
                              setEditorOpen(true);
                            }}
                            className="inline-flex items-center gap-2 rounded-xl border border-primary-200 px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50"
                          >
                            <Pencil className="h-4 w-4" />
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => setAnnouncementToDelete(item)}
                            className="inline-flex items-center gap-2 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                          >
                            <Trash2 className="h-4 w-4" />
                            Delete
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}

          {!isLoading && filteredAnnouncements.length > 0 ? (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => {
                setCurrentPage(page);
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
            />
          ) : null}
        </div>
      </section>

      <AnnouncementEditorModal
        announcement={editingAnnouncement}
        isOpen={editorOpen}
        onClose={() => {
          setEditorOpen(false);
          setEditingAnnouncement(null);
        }}
      />

      {announcementToDelete && (
        <DeleteConfirmModal
          title={announcementToDelete.title}
          heading="Delete Announcement?"
          isDeleting={deleteAnnouncement.isPending}
          onConfirm={() => void handleDelete()}
          onCancel={() => setAnnouncementToDelete(null)}
          description={`Delete "${announcementToDelete.title}"? This announcement will disappear from the homepage and announcements page.`}
        />
      )}
    </>
  );
}
