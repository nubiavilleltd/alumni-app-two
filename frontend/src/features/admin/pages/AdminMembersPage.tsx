/**
 * ============================================================================
 * ADMIN MEMBERS MANAGEMENT PAGE - WITH ROLE MANAGEMENT
 * ============================================================================
 *
 * Route: /admin/members
 *
 * Features:
 * - View all users
 * - Activate/Deactivate accounts
 * - Change user roles (admin <-> member) ← NEW
 *
 * ============================================================================
 */

import {
  Check,
  CheckCircle,
  LoaderCircle,
  Mail,
  Phone,
  Plus,
  UserSearch,
  UserX,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, useMemo } from 'react';
import { AppLink } from '@/shared/components/ui/AppLink';
import { SEO } from '@/shared/common/SEO';
import {
  useAdminDeactivateUser,
  useAdminActivateUser,
} from '@/features/admin/hooks/useUserManagement';
import { useChangeUserRole } from '@/features/admin/hooks/useRoleManagement';
import type { AccountStatus } from '@/features/admin/api/adapters/user-management.adapter';
import {
  type UserRole,
} from '@/features/admin/api/adapters/role-management.adapter';
import { useAlumni } from '@/features/alumni/hooks/useAlumni';
import type { Alumni } from '@/features/alumni/types/alumni.types';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { getPhotoDisplay, resolveProfilePhoto } from '@/features/user/utils/profileUtils';
import { Avatar } from '@/shared/components/ui/Avatar';
import { useUrlPagination } from '@/shared/hooks/useUrlPagination';
import { usePersistedFilters } from '@/shared/hooks/usePersistedFilters';
import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { Pagination } from '@/shared/components/ui/Pagination';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { AdminBanner } from '@/features/admin/components/AdminBanner';
import { useLeadership, useRemoveLeadershipMember } from '@/features/leadership/hooks/useLeadership';
import { AddExcoModal } from '@/features/leadership/components/AddExcoModal';
import { LeadershipMember } from '@/features/leadership/types/leadership.types';
import { EditLeadershipModal } from '@/features/leadership/components/EditLeadershipModal';
import { AddAdminModal } from '@/features/admin/components/AddAdminModal';
import { EditAdminModal } from '@/features/admin/components/EditAdminModal';
import { toast } from '@/shared/components/ui/Toast';
import { alumni } from '@/data/site-data';
import { isSuperAdmin } from '@/shared/permissions/base';

function generateInitialsAvatar(name: string): string {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(
    name,
  )}&background=E5E7EB&color=6B7280&size=256`;
}

// ═══════════════════════════════════════════════════════════════════════════
// TYPES
// ═══════════════════════════════════════════════════════════════════════════

type DisplayUser = {
  id: string;
  fullName: string;
  email: string;
  phone: string;
  role: string;
  accountStatus: AccountStatus;
  photo?: string;
  leadership?: LeadershipMember;
  alumni: Alumni;
};

type MemberFilterValue = 'all' | 'active' | 'inactive' | 'admin' | 'exco';


const ADMIN_MEMBERS_PER_PAGE = 10;
const memberActionButtonClassName =
  'flex h-[33px] items-center justify-center gap-1 rounded-[48px] border-2 px-4 py-2 text-sm font-semibold leading-none transition-colors disabled:opacity-50';

type MemberStatCardProps = {
  label: string;
  value: number;
  icon: LucideIcon;
  gradient: string;
};

function MemberStatCard({ label, value, icon: Icon, gradient }: MemberStatCardProps) {
  return (
    <div className={`h-[116px] rounded-[24px] bg-gradient-to-br ${gradient} p-5 text-white`}>
      <div className="flex h-full items-center justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-white/80">{label}</p>
          <p className="mt-2 text-3xl font-bold">{value}</p>
        </div>
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10">
          <Icon className="h-7 w-7 text-white" />
        </div>
      </div>
    </div>
  );
}

function mapUserRole(role:string){
  if(!role) return "alumni"
  if(role === "admin"){
    return "super admin"
  }
  return role
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: MAP ALUMNI TO DISPLAY USER
// ═══════════════════════════════════════════════════════════════════════════

function mapAlumniToDisplayUser(alumni: Alumni, currentUserMemberId?: string, leadership?: LeadershipMember): DisplayUser {
//   if(currentUserMemberId == "39"){
// console.log("bnnn", ['alumni', 'member'].includes(alumni.role ?? 'alumni') ? 'member' : 'admin',)
//   }
  return {
    id: alumni.memberId,
    fullName: alumni.name,
    email: alumni.email,
    phone: alumni.whatsappPhone,
    // role: ['alumni', 'member'].includes(alumni.role ?? 'alumni') ? 'member' : 'admin',
    // role: alumni.role ?? "alumni",
    role: mapUserRole(alumni.role ?? "alumni"),
    accountStatus: alumni.isActive ? 'active' : 'inactive',
    photo: resolveProfilePhoto({
      photoUrl: alumni.photo,
      privacy: alumni.privacy,
      isOwner: alumni.memberId === currentUserMemberId,
      isSignedIn: Boolean(currentUserMemberId),
    }),
    leadership,
    alumni
  };
}

function AdminMembersPageSkeleton() {
  return (
    <section className="min-h-screen animate-pulse bg-[#F8F8F7] py-8 sm:py-10">
      <div className="container-custom space-y-10">
        {/* Stats */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 min-[1390px]:grid-cols-[repeat(3,410px)] min-[1390px]:justify-between">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-[116px] rounded-[24px] bg-gray-200" />
          ))}
        </div>

        {/* Filters */}
        <div className="flex flex-col gap-4 sm:flex-row sm:gap-6">
          <div className="h-12 flex-1 rounded-full bg-white" />
          <div className="flex gap-2">
            <div className="h-12 w-[82px] rounded-full bg-gray-200" />
            <div className="h-12 w-[100px] rounded-full bg-gray-200" />
            <div className="h-12 w-[100px] rounded-full bg-gray-200" />
          </div>
        </div>

        {/* User rows */}
        <div className="space-y-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <UserRowSkeleton key={i} />
          ))}
        </div>
      </div>
    </section>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// ✅ NEW: CHANGE ROLE MODAL
// ═══════════════════════════════════════════════════════════════════════════



// ═══════════════════════════════════════════════════════════════════════════
// USER ROW COMPONENT - UPDATED WITH ROLE CHANGE BUTTON
// ═══════════════════════════════════════════════════════════════════════════



// function toTitleCase(text: string) {
//   if (!text) return "";

//   return text
//     .toLowerCase()
//     .trim()
//     .replace(/\s+/g, " ")
//     .split(" ")
//     .map(word => word.charAt(0).toUpperCase() + word.slice(1))
//     .join(" ");
// }


function UserRow({
  user,
  activeFilter,
  onEditLeadership,
  onEditAdminRole,
}: {
  user: DisplayUser;
  activeFilter: MemberFilterValue;
  onEditLeadership: (leader: LeadershipMember) => void;
  onEditAdminRole: (admin: Alumni) => void;
}) {
  const [showConfirm, setShowConfirm] = useState(false);
  const [actionType, setActionType] = useState
    <'activate' | 'deactivate' | 'removeAdmin' | 'removeExco' | null
    >(null);

  const deactivate = useAdminDeactivateUser();
  const activate = useAdminActivateUser();
  const changeRole = useChangeUserRole(); // reused for "Remove as Admin" — real endpoint today
  const removeLeadershipMember = useRemoveLeadershipMember();

  const isActive = user.accountStatus === 'active';
  const isBusy =
    deactivate.isPending || activate.isPending || changeRole.isPending || removeLeadershipMember.isPending;
    let errorMessage = '';
    let successMessage = '';

  const handleAction = async () => {
    try {
      if (actionType === 'deactivate') {
        await deactivate.mutateAsync(user.id);
        successMessage = `User successfully deactivated`;
        errorMessage = `Failed to deactivate user. Please try again.`;
      } else if (actionType === 'activate') {
        await activate.mutateAsync(user.id);
        successMessage = `User successfully activated`;
        errorMessage = `Failed to activate user. Please try again.`;
      } else if (actionType === 'removeAdmin') {
        await changeRole.mutateAsync({ userId: user.id, newRole: 'alumni' });
        successMessage = `User successfully removed as ADMIN`;
        errorMessage = `Failed to remove user as ADMIN. Please try again.`;
      } else if (actionType === 'removeExco' && user.leadership) {
        await removeLeadershipMember.mutateAsync(user.leadership.id);
        successMessage = `User successfully removed as EXCO`;
        errorMessage = `Failed to remove user as EXCO. Please try again.`;
      }
      toast.success(successMessage || 'Action completed successfully');
      setShowConfirm(false);
      setActionType(null);
    } catch (error) {
      toast.error(errorMessage || 'An error occurred. Please try again.');
      // Error toast shown by mutation
    }
  };

  const openConfirm = (action: 'activate' | 'deactivate' | 'removeAdmin' | 'removeExco') => {
    setActionType(action);
    setShowConfirm(true);
  };

  const closeConfirm = () => {
    setShowConfirm(false);
    setActionType(null);
  };

  return (
    <div className="bg-white rounded-xl border border-gray-100 p-4 hover:border-primary-200 hover:shadow-sm transition-all">
      <div className="flex items-center gap-3">
        <Avatar src={user.photo ?? generateInitialsAvatar(user.fullName)} alt={user.fullName} size={48} />

        <div className="flex-1 min-w-0 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <p className="font-semibold text-gray-900">{user.fullName}</p>
              {/* {user.role === 'admin' && (
               
              )} */}
               <span
                className={`px-2 py-0.5 text-[10px] font-bold rounded-full flex-shrink-0 ${isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                  }`}
              >
                {isActive ? 'ACTIVE' : 'INACTIVE'}
              </span>
              
              {user.leadership && (
                <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-bold rounded-full flex-shrink-0">
                  {user.leadership.role.toUpperCase()}
                </span>
              )}

                 {user.role.includes('admin') && (
                <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-full flex-shrink-0">
                  {user.role.toUpperCase()}
                </span>
              )}

            </div>

            <div className="flex items-center gap-1 mt-1 text-xs text-gray-500 min-w-0">
              <Mail className="w-3.5 h-3.5 flex-shrink-0" />
              <span className="truncate">{user.email}</span>
            </div>

            {user.phone && (
              <div className="flex items-center gap-1 mt-0.5 text-xs text-gray-500">
                <Phone className="w-3.5 h-3.5 flex-shrink-0" />
                <span>{user.phone}</span>
              </div>
            )}
          </div>

          {!showConfirm ? (
            <div className="flex flex-col items-stretch gap-2 flex-shrink-0 w-full sm:flex-row sm:items-center md:w-auto">
              {activeFilter === 'exco' && user.leadership ? (
                <>
                  <button
                    onClick={() => onEditLeadership(user.leadership!)}
                    disabled={isBusy}
                    className={`${memberActionButtonClassName} border-purple-200 text-purple-600 hover:bg-purple-50`}
                  >
                    Edit Leadership Role
                  </button>
                  <button
                    onClick={() => openConfirm('removeExco')}
                    disabled={isBusy}
                    className={`${memberActionButtonClassName} border-red-200 text-red-600 hover:bg-red-50`}
                  >
                    Remove as Exco
                  </button>
                </>
              ) : activeFilter === 'admin' ? (
                // "Edit Admin Role" intentionally not here yet — needs the
                // admin sub-role picker we haven't built. Remove is real today.
                <>

                <button
                    onClick={() => onEditAdminRole(user.alumni)}
                    disabled={isBusy}
                    className={`${memberActionButtonClassName} border-primary-500 text-primary-600 hover:bg-primary-50`}
                  >
                    Edit Admin Role
                  </button>

                  <button
                    onClick={() => openConfirm('removeAdmin')}
                    disabled={isBusy}
                    className={`${memberActionButtonClassName} border-red-200 text-red-600 hover:bg-red-50`}
                  >
                    Remove as Admin
                  </button>
                </>

              ) : (
                <button
                  onClick={() => openConfirm(isActive ? 'deactivate' : 'activate')}
                  disabled={isBusy}
                  className={`${memberActionButtonClassName} ${isActive
                      ? 'border-red-200 text-red-600 hover:bg-red-50'
                      : 'border-green-200 text-green-600 hover:bg-green-50'
                    }`}
                >
                  {isActive ? 'Deactivate' : 'Activate'}
                </button>
              )}
            </div>
          ) : (
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-shrink-0 w-full md:w-auto">
              <button
                onClick={handleAction}
                disabled={isBusy}
                className={`px-4 py-2 rounded-lg text-sm font-semibold text-white transition-colors text-center ${actionType === 'activate' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'
                  }`}
              >
                {isBusy ? <LoaderCircle className="w-4 h-4 animate-spin mx-auto" /> : 'Confirm'}
              </button>
              <button
                onClick={closeConfirm}
                disabled={isBusy}
                className="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors text-center"
              >
                Cancel
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// SKELETON (unchanged)
// ═══════════════════════════════════════════════════════════════════════════

function UserRowSkeleton() {
  return (
    <div className="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3 flex-1">
          <div className="w-12 h-12 rounded-full bg-gray-200" />
          <div className="flex-1 space-y-2">
            <div className="h-4 w-48 bg-gray-200 rounded" />
            <div className="h-3 w-64 bg-gray-100 rounded" />
          </div>
        </div>
        <div className="h-[33px] w-[134px] rounded-[48px] bg-gray-200" />
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE (unchanged except imports)
// ═══════════════════════════════════════════════════════════════════════════

export function AdminMembersPage() {
  const currentUser = useIdentityStore((state) => state.user);
  const { data: alumniList = [], isLoading } = useAlumni();

  console.log("alumn list", {alumniList})
  const { data: leadershipList = [] } = useLeadership();

  // const excoRoleByMemberId = useMemo(() => {
  //   const map = new Map<string, string>();
  //   leadershipList.forEach((leader) => {
  //     map.set(leader.memberId, leader.role);
  //   });
  //   return map;
  // }, [leadershipList]);



  const { filters, setFilter } = usePersistedFilters('admin-members-filters', {
    searchQuery: '',
    statusFilter: 'all' as MemberFilterValue,
  });
  const { searchQuery, statusFilter } = filters;
  const [currentPage, setCurrentPage] = useUrlPagination();
  const [isAddAdminModalOpen, setIsAddAdminModalOpen] = useState(false);
  const [isAddExcoModalOpen, setIsAddExcoModalOpen] = useState(false);
  const [editingLeader, setEditingLeader] = useState<LeadershipMember | null>(null);
  const [editingAdmin, setEditingAdmin] = useState<Alumni | null>(null);

  const leadershipByMemberId = useMemo(() => {
    const map = new Map<string, LeadershipMember>();
    leadershipList.forEach((leader) => {
      map.set(leader.memberId, leader);
    });
    return map;
  }, [leadershipList]);


    const adminMemberIds = useMemo(
    () =>
      alumniList
        .filter((alumni) => !['alumni', 'member'].includes(alumni.role ?? 'alumni'))
        .map((alumni) => alumni.memberId),
    [alumniList],
  );



  const users = useMemo(() => {
    return alumniList.map((alumni) =>
      mapAlumniToDisplayUser(
        alumni,
        currentUser?.memberId,
        leadershipByMemberId.get(alumni.memberId),
      ),
    );
  }, [alumniList, currentUser?.memberId, leadershipByMemberId]);


  const filteredUsers = useMemo(() => {
    let filtered = users;

    if (statusFilter === 'active' || statusFilter === 'inactive') {
      filtered = filtered.filter((u) => u.accountStatus === statusFilter);
    } else if (statusFilter === 'admin') {
      const allAdmins = filtered.filter((u) => u.role.includes('admin'));
      const nonSuperAdmin = allAdmins.filter(admin => admin.role !== "super admin");
      filtered = isSuperAdmin(currentUser) ? allAdmins : nonSuperAdmin
    } else if (statusFilter === 'exco') {
      filtered = filtered.filter((u) => Boolean(u.leadership));
    }

    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (u) => u.fullName.toLowerCase().includes(query) || u.email.toLowerCase().includes(query),
      );
    }

    return filtered;
  }, [users, statusFilter, searchQuery]);

  const totalPages = Math.max(1, Math.ceil(filteredUsers.length / ADMIN_MEMBERS_PER_PAGE));
  const visibleUsers = filteredUsers.slice(
    (currentPage - 1) * ADMIN_MEMBERS_PER_PAGE,
    currentPage * ADMIN_MEMBERS_PER_PAGE,
  );



  useEffect(() => {
    if (!isLoading && currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, isLoading, totalPages]);

  const activeCount = users.filter((u) => u.accountStatus === 'active').length;
  const inactiveCount = users.filter((u) => u.accountStatus === 'inactive').length;
  const memberStats: MemberStatCardProps[] = [
    {
      label: 'Total Members',
      value: users.length,
      icon: Users,
      gradient: 'from-primary-500 to-primary-700',
    },
    {
      label: 'Active Members',
      value: activeCount,
      icon: CheckCircle,
      gradient: 'from-green-500 to-green-700',
    },
    {
      label: 'Inactive Members',
      value: inactiveCount,
      icon: UserX,
      gradient: 'from-gray-500 to-gray-700',
    },
  ];
  const MEMBER_FILTERS: Array<{ label: string; value: MemberFilterValue }> = [
    { label: 'All', value: 'all' },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Admins', value: 'admin' },
    { label: 'Excos', value: 'exco' },
  ];

  const changePage = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  if (isLoading) {
    return (
      <>
        <SEO title="Manage Members" description="View and manage all members" />
        <AdminBanner activeTab="members" title="Members" />
        <AdminMembersPageSkeleton />
      </>
    );
  }

  return (
    <>
      <SEO title="Manage Members" description="View and manage all members" />
      <AdminBanner activeTab="members" title="Members" />

      <section className="min-h-screen bg-[#F8F8F7] py-8 sm:py-10">
        <div className="container-custom space-y-10">
          {/* Stats Cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 min-[1390px]:grid-cols-[repeat(3,410px)] min-[1390px]:justify-between">
            {memberStats.map((stat) => (
              <MemberStatCard key={stat.label} {...stat} />
            ))}
          </div>

          {/* Filters */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
            <div className="min-w-0 flex-1">
              <SearchInput
                placeholder="Search by name or email..."
                value={searchQuery}
                onValueChange={(value) => setFilter('searchQuery', value)}
                className="w-full"
                inputClassName="!h-12 !rounded-[48px] !border-0 !pl-12 !pr-5 !text-base !shadow-[0_4px_20px_0_rgba(0,0,0,0.05)] focus:!ring-0"
                iconClassName="!left-5 !h-5 !w-5 !text-[#828282]"
              />
            </div>

            {/* Status Filter */}
            <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:flex-nowrap">
              {MEMBER_FILTERS.map((filter) => (
                <button
                  key={filter.value}
                  type="button"
                  onClick={() => {
                    setFilter('statusFilter', filter.value);
                  }}
                  className={`h-12 whitespace-nowrap rounded-[40px] border px-6 text-base font-semibold transition-colors ${statusFilter === filter.value
                    ? 'border-primary-500 bg-primary-500 text-white'
                    : 'border-primary-100 bg-white text-gray-500 hover:border-primary-300'
                    }`}
                >
                  {filter.label}
                </button>
              ))}
            </div>
          </div>

          {statusFilter === 'admin' && (
            <button
              type="button"
              onClick={() => setIsAddAdminModalOpen(true)}
              className="flex w-fit items-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600"
            >
              Add Admin
              <Plus className="h-4 w-4" />
            </button>
          )}

          {statusFilter === 'exco' && (
            <button
              type="button"
              onClick={() => setIsAddExcoModalOpen(true)}
              className="flex w-fit items-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600"
            >
              Add Exco
              <Plus className="h-4 w-4" />
            </button>
          )}

          {/* Users List */}
          <div className="space-y-3">
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => <UserRowSkeleton key={i} />)
            ) : filteredUsers.length === 0 ? (
              <div className="text-center py-12 bg-white rounded-xl border border-gray-100">
                <UserSearch className="w-16 h-16 text-gray-300 mx-auto mb-3" />
                <p className="text-gray-500 text-sm">
                  {searchQuery ? 'No users found matching your search' : 'No users found'}
                </p>
              </div>
            ) : (
              visibleUsers.map((user) => (
                <UserRow key={user.id} user={user} activeFilter={statusFilter} onEditLeadership={setEditingLeader} onEditAdminRole={setEditingAdmin} />
              ))
            )}
          </div>

          {!isLoading && filteredUsers.length > 0 ? (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={changePage}
            />
          ) : null}
        </div>
      </section>

      <AddExcoModal
        isOpen={isAddExcoModalOpen}
        onClose={() => setIsAddExcoModalOpen(false)}
        excludeMemberIds={leadershipList.map((leader) => leader.memberId)}
      />

      {editingLeader && (
        <EditLeadershipModal
          leader={editingLeader}
          isOpen={Boolean(editingLeader)}
          onClose={() => setEditingLeader(null)}
        />
      )}

           <AddAdminModal
        isOpen={isAddAdminModalOpen}
        onClose={() => setIsAddAdminModalOpen(false)}
        excludeMemberIds={adminMemberIds}
      />

      {editingAdmin && (
        <EditAdminModal
          admin={editingAdmin}
          isOpen={Boolean(editingAdmin)}
          onClose={() => setEditingAdmin(null)}
        />
      )}
    </>
  );
}
