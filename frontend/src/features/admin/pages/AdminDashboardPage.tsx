// features/admin/pages/AdminDashboardPage.tsx
// REDESIGNED: Matches screenshot exactly with:
// - Dark blue gradient banner with tabs
// - 4 stat cards (Total, Active, Inactive, Pending)
// - Pending Approvals section with full details
// - Fully responsive on all screen sizes

import {
  AlertCircle,
  CheckCheck,
  Clock3,
  LoaderCircle,
  type LucideIcon,
  UserCheck,
  UserX,
  Users,
} from 'lucide-react';
import { useState } from 'react';
import { useAdminDashboard, useApproveMember, useRejectMember } from '../hooks/useAdminDashboard';
import { SEO } from '@/shared/common/SEO';
import { toast } from '@/shared/components/ui/Toast';
import type { PendingMember } from '../api/adminDashboardApi';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { AdminBanner } from '../components/AdminBanner';

const approvalActionButtonClassName =
  'flex h-[33px] w-[134px] items-center justify-center gap-1 rounded-[48px] border-2 px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-50';
const confirmApprovalButtonClassName = `${approvalActionButtonClassName} border-primary-500 bg-primary-500 text-white hover:bg-primary-600`;
const denyApprovalButtonClassName = `${approvalActionButtonClassName} border-[#C80000] bg-transparent text-[#C80000] hover:bg-red-50`;

// ═══════════════════════════════════════════════════════════════════════════
// PENDING APPROVAL ROW
// ═══════════════════════════════════════════════════════════════════════════

function PendingApprovalRow({
  member,
  onApprove,
  onReject,
}: {
  member: PendingMember;
  onApprove: (id: string) => void;
  onReject: (id: string, reason?: string) => void;
}) {
  const [showRejectInput, setShowRejectInput] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [actionError, setActionError] = useState('');

  const approveMutation = useApproveMember();
  const rejectMutation = useRejectMember();
  const busy = approveMutation.isPending || rejectMutation.isPending;

  const handleConfirm = async () => {
    setActionError('');
    try {
      await approveMutation.mutateAsync(member.id);
      onApprove(member.id);
      toast.success('Account successfully approved');
    } catch (error: any) {
      setActionError(error.message ?? 'Approval failed. Please try again.');
    }
  };

  const handleDeny = async () => {
    setActionError('');
    try {
      await rejectMutation.mutateAsync({
        userId: member.id,
        reason: rejectReason.trim() || undefined,
      });
      onReject(member.id, rejectReason.trim() || undefined);
      toast.success('Account successfully rejected');
    } catch (error: any) {
      setActionError(error.message ?? 'Rejection failed. Please try again.');
    }
  };

  return (
    <div className="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
      <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        {/* Left: User Info */}
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2 mb-2">
            <h3 className="font-semibold text-gray-900 text-base">
              {member.fullName}
              {member.nickName && member.nickName.trim() && (
                <span className="font-normal text-gray-600"> formerly {member.nameInSchool}</span>
              )}
            </h3>
          </div>

          <div className="space-y-1.5 text-sm">
            <div className="flex flex-wrap gap-x-6 gap-y-1">
              <p className="text-gray-700">
                <span className="font-medium">Class of</span> {member.graduationYear}
              </p>
              {member.nickName && member.nickName.trim() && (
                <p className="text-gray-700">
                  <span className="font-medium">A.K.A</span> {member.nickName}
                </p>
              )}
            </div>

            <p className="text-gray-700">{member.email}</p>

            {/* {member.department && member.department.trim() && (
              <p className="text-gray-700">
                <span className="font-medium">Former Role:</span> {member.department}
              </p>
            )} */}
          </div>

          <p className="text-xs text-gray-400 mt-3">
            Registered on{' '}
            {new Date(member.submittedAt).toLocaleDateString('en-US', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
            })}
          </p>
        </div>

        {/* Right: Action Buttons */}
        {!showRejectInput && (
          <div className="flex flex-shrink-0 gap-3 lg:flex-col">
            <button
              type="button"
              disabled={busy}
              onClick={handleConfirm}
              className={confirmApprovalButtonClassName}
            >
              {approveMutation.isPending ? (
                <LoaderCircle className="w-4 h-4 animate-spin" />
              ) : (
                'Confirm'
              )}
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => {
                setShowRejectInput(true);
                setActionError('');
              }}
              className={denyApprovalButtonClassName}
            >
              Deny
            </button>
          </div>
        )}
      </div>

      {/* Deny Reason Input */}
      {showRejectInput && (
        <div className="mt-4 pt-4 border-t border-gray-100 space-y-3">
          <label className="block text-sm font-medium text-gray-700">
            Reason for denial <span className="text-gray-400 font-normal">(optional)</span>
          </label>
          <TextareaInput
            rows={2}
            placeholder="e.g. Could not verify graduation details provided."
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            className="gap-2"
            textareaClassName="rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 shadow-none placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-200 resize-none"
          />
          <div className="flex gap-3">
            <button
              type="button"
              disabled={busy}
              onClick={handleDeny}
              className={`${approvalActionButtonClassName} border-[#C80000] bg-[#C80000] text-white hover:bg-[#A90000]`}
            >
              {rejectMutation.isPending && <LoaderCircle className="w-4 h-4 animate-spin" />}
              Confirm deny
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => {
                setShowRejectInput(false);
                setRejectReason('');
                setActionError('');
              }}
              className="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold px-6 py-2 rounded-full transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {actionError && (
        <p className="mt-3 text-sm text-red-500 bg-red-50 rounded-xl px-4 py-2">{actionError}</p>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// STAT CARD
// ═══════════════════════════════════════════════════════════════════════════

interface StatCardProps {
  label: string;
  value: string | number;
  icon: LucideIcon;
  color: 'blue' | 'cyan' | 'gray' | 'orange';
}

function StatCard({ label, value, icon, color }: StatCardProps) {
  const Icon = icon;
  const colorClasses = {
    blue: 'from-[#1e5aa8] to-[#2563eb] text-white',
    cyan: 'from-[#0891b2] to-[#06b6d4] text-white',
    gray: 'from-[#475569] to-[#64748b] text-white',
    orange: 'from-[#ea580c] to-[#f97316] text-white',
  };

  return (
    <div
      className={`min-h-[120px] rounded-[24px] bg-gradient-to-br ${colorClasses[color]} p-6 shadow-lg`}
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-white/80 mb-2">{label}</p>
          <p className="text-4xl font-bold">{value}</p>
        </div>
        <div className="flex h-[50px] w-[50px] flex-shrink-0 items-center justify-center rounded-2xl bg-white/20">
          <Icon className="h-[18px] w-[20px] text-white" />
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// SKELETON
// ═══════════════════════════════════════════════════════════════════════════

function AdminDashboardSkeleton() {
  return (
    <div className="min-h-screen bg-[#F8F8F7]">
      <div className="container-custom py-6 sm:py-8">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 animate-pulse">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-32 bg-gray-200 rounded-2xl" />
          ))}
        </div>

        {/* Pending Approvals Skeleton */}
        <div className="bg-white rounded-2xl p-6 animate-pulse">
          <div className="h-6 w-48 bg-gray-200 rounded mb-6" />
          <div className="space-y-4">
            {[1, 2].map((i) => (
              <div key={i} className="h-40 bg-gray-100 rounded-xl" />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════

export function AdminDashboardPage() {
  const { data: dashboard, isLoading, isError, refetch } = useAdminDashboard();
  const { data: alumniList = [], isLoading: isLoadingAlumni } = useAlumni();

  const [removedIds, setRemovedIds] = useState<Set<string>>(new Set());

  const handleApprove = (id: string) => setRemovedIds((prev) => new Set([...prev, id]));
  const handleReject = (id: string) => setRemovedIds((prev) => new Set([...prev, id]));

  if (isLoading || isLoadingAlumni) {
    return (
      <>
        <SEO title="Admin Dashboard" description="Admin dashboard" />
        <AdminBanner activeTab="dashboard" title="Dashboard" headingLevel="h1" />
        <AdminDashboardSkeleton />
      </>
    );
  }

  if (isError || !dashboard) {
    return (
      <>
        <SEO title="Admin Dashboard" description="Admin dashboard" />
        <AdminBanner activeTab="dashboard" title="Dashboard" headingLevel="h1" />
        <section className="min-h-screen bg-[#F8F8F7]">
          <div className="container-custom py-6 sm:py-8">
            <div className="mx-auto max-w-2xl rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
              <AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-4" />
              <h1 className="text-2xl font-bold text-gray-900 mb-2">Dashboard unavailable</h1>
              <p className="text-sm text-gray-600 mb-6">
                Could not load admin dashboard data. Please try again.
              </p>
              <button type="button" className="btn btn-primary" onClick={() => void refetch()}>
                Try again
              </button>
            </div>
          </div>
        </section>
      </>
    );
  }

  const visiblePending = dashboard.pendingApprovals.filter((m) => !removedIds.has(m.id));

  console.log(dashboard, 'dashboard.pendingApprovals');

  // Calculate stats
  const totalMembers = alumniList.length; // This should come from backend
  const activeMembers = alumniList.filter((m) => m.isActive !== false).length;
  const inactiveMembers = totalMembers - activeMembers;
  const pendingApprovals = visiblePending.length;

  console.log('Stats:', { alumniList});

  return (
    <>
      <SEO title="Admin Dashboard" description="Admin dashboard" />
      <AdminBanner activeTab="dashboard" title="Dashboard" headingLevel="h1" />

      <div className="min-h-screen bg-[#F8F8F7]">
        <div className="container-custom py-8 sm:py-10">
          {/* ══════════════════════════════════════════════════════════
              STAT CARDS (4 columns)
              ═══════════════════════════════════════════════════════ */}
          <div className="mb-8 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total Members" value={totalMembers} icon={Users} color="blue" />
            <StatCard label="Active Members" value={activeMembers} icon={UserCheck} color="cyan" />
            <StatCard label="Inactive Members" value={inactiveMembers} icon={UserX} color="gray" />
            <StatCard
              label="Pending Approvals"
              value={pendingApprovals}
              icon={Clock3}
              color="orange"
            />
          </div>

          {/* ══════════════════════════════════════════════════════════
              PENDING APPROVALS SECTION
              ═══════════════════════════════════════════════════════ */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
              <h2 className="text-lg font-bold text-gray-900">Pending Approvals</h2>
            </div>

            <div className="p-6">
              {visiblePending.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-center">
                  <div className="w-16 h-16 rounded-full bg-primary-50 flex items-center justify-center mb-4">
                    <CheckCheck className="w-8 h-8 text-primary-500" />
                  </div>
                  <p className="font-semibold text-gray-900 text-lg mb-1">All caught up!</p>
                  <p className="text-sm text-gray-500">No registrations awaiting approval.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {visiblePending.map((member) => (
                    <PendingApprovalRow
                      key={member.id}
                      member={member}
                      onApprove={handleApprove}
                      onReject={handleReject}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
