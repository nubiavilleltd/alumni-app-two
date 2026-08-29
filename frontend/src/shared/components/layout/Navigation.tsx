import { Icon } from '@iconify/react';
import { clsx } from 'clsx';
import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { twMerge } from 'tailwind-merge';

import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { useAuth } from '@/features/authentication/hooks/useAuth';
import { AUTH_ROUTES } from '@/features/authentication/routes';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import { useTokenStore } from '@/features/authentication/stores/useTokenStore';
import { authApi } from '@/features/authentication/services/auth.service';

import { ADMIN_ROUTES } from '@/features/admin/routes';
import { ALUMNI_ROUTES } from '@/features/alumni/routes';
import { EVENT_ROUTES } from '@/features/events/routes';
import { MARKETPLACE_ROUTES } from '@/features/marketplace/routes';
import { ANNOUNCEMENT_ROUTES } from '@/features/announcements/routes';

import { useMessagesInbox } from '@/features/messages/hooks/useMessages';
import type { MessageThreadSummary } from '@/features/messages/types/messages.types';

import { ROUTES } from '@/shared/constants/routes';
import { USER_ROUTES } from '@/features/user/routes';

import { AppLink } from '../ui/AppLink';
import { useToastStore } from '../ui/Toast';
import HeaderLogo from '../ui/HeaderLogo';
import { useCartStore } from '@/features/store/stores/useCartStore';


type NavChild = {
  label: string;
  url: string;
  description?: string;
};

type NavItem = NavChild & {
  children?: NavChild[];
};

type CurrentUser = {
  fullName?: string;
  email?: string;
  avatarInitials?: string;
  photo?: string | null;
  role?: string;
  memberId?: string;
  id?: string;
};

const authenticatedMenuItems: NavChild[] = [
  { label: 'View Profile', url: USER_ROUTES.PROFILE },
  { label: 'Dashboard', url: USER_ROUTES.DASHBOARD },
  { label: 'Message Center', url: ROUTES.MESSAGES },
  { label: 'My Registered Events', url: EVENT_ROUTES.MY_EVENTS },
  { label: 'My Market', url: MARKETPLACE_ROUTES.MY_BUSINESS },
  { label: 'My Job Posts', url: ROUTES.MY_JOB_POSTS },
  { label: 'My Shopping Cart', url: ROUTES.STORE.CART },
  { label: 'My Order History', url: ROUTES.ORDER.ROOT },
  { label: 'Settings', url: USER_ROUTES.SETTINGS },
];

const navSurfaceClassName = 'bg-[#05245B]';

const expandedNavColumns: NavItem[][] = [
  [
    { label: 'About Us', url: ROUTES.ABOUT },
    {
      label: 'News & Events',
      url: ROUTES.NEWS,
      children: [
        {
          label: 'Announcements',
          url: ROUTES.NEWS,
          description: 'Latest association updates',
        },
        {
          label: 'Events',
          url: EVENT_ROUTES.ROOT,
          description: 'Upcoming events and reunions',
        },
        {
          label: 'Our Projects',
          url: ROUTES.PROJECTS.ROOT,
          description: 'Community initiatives in action',
        },
        {
          label: 'Blog',
          url: ANNOUNCEMENT_ROUTES.BLOG,
          description: 'Stories, insights and updates',
        },
        {
          label: 'Live News',
          url: ROUTES.LIVE_NEWS.ROOT,
          description: 'Trusted local and global news',
        },
      ],
    },
  ],
  [
    {
      label: 'Marketplace',
      url: MARKETPLACE_ROUTES.ROOT,
      children: [
        {
          label: 'Marketplace',
          url: MARKETPLACE_ROUTES.ROOT,
          description: 'Discover member-owned businesses',
        },
        {
          label: 'Job Vacancies',
          url: ROUTES.JOB_VACANCIES,
          description: 'Explore career opportunities',
        },
        {
          label: 'Alumni Store',
          url: ROUTES.STORE.ROOT,
          description: 'Official alumni merchandise',
        },
      ],
    },
    { label: 'Alumni Directory', url: ALUMNI_ROUTES.PROFILES },
  ],
  [
    { label: 'Welfare', url: ROUTES.WELFARE },
  ],
  [
    { label: 'Volunteer', url: ROUTES.JOIN_PROJECTS.VOLUNTEER },
    { label: 'Contact Us', url: ROUTES.CONTACT },
  ],
];

const userAccountTriggerClassName =
  'inline-flex min-h-[3.35rem] w-full items-center rounded-full border border-white/25 bg-transparent text-white no-underline shadow-none transition-colors duration-200 hover:border-white/40 hover:bg-white/5';
const expandedNavHeadingClassName =
  'text-left text-[1.05rem] font-semibold leading-normal tracking-[0.03em] text-white no-underline transition-colors duration-150 hover:text-[#0077CC] focus-visible:text-white lg:text-[1.125rem]';
const expandedNavChildClassName =
  'text-left text-sm font-semibold leading-normal text-white no-underline transition-colors duration-150 hover:text-[#0077CC] focus-visible:text-white';

function cn(...inputs: Array<string | false | null | undefined>) {
  return twMerge(clsx(inputs));
}

function getDisplayName(user: CurrentUser) {
  return user.fullName?.trim() || user.email?.split('@')[0] || 'Member';
}

function getInitials(user: CurrentUser) {
  const storedInitials = user.avatarInitials?.trim();

  if (storedInitials) return storedInitials;

  const name = getDisplayName(user);
  const parts = name.split(/\s+/).filter(Boolean);

  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }

  return parts[0]?.slice(0, 2).toUpperCase() || '?';
}

function isPathActive(pathname: string, url: string) {
  if (url === ROUTES.HOME) return pathname === ROUTES.HOME;

  return pathname === url || pathname.startsWith(`${url}/`);
}

function formatUnreadBadgeCount(count: number) {
  if (count > 99) return '99+';

  return count.toString();
}

function UnreadMessagesBadge({ count, className = '' }: { count: number; className?: string }) {
  if (count <= 0) return null;

  return (
    <span
      className={cn(
        'inline-flex min-w-5 items-center justify-center rounded-full bg-[#ef4444] px-1.5 py-0.5 text-[0.68rem] font-extrabold leading-none text-white',
        className,
      )}
    >
      {formatUnreadBadgeCount(count)}
    </span>
  );
}

function buildMessageFlashTitle(thread: MessageThreadSummary) {
  if (thread.type === 'group' && thread.lastMessageSenderName?.trim()) {
    return `${thread.lastMessageSenderName.trim()} in ${thread.title}`;
  }

  return thread.title;
}

function buildMessageFlashBody(thread: MessageThreadSummary) {
  const preview = thread.lastMessagePreview.trim();

  return preview || 'You have a new unread message.';
}

function BrandMark({ mobile = false }: { mobile?: boolean }) {
  return (
    <AppLink
      href={ROUTES.HOME}
      className={cn(
        'relative isolate flex overflow-hidden bg-transparent text-white no-underline',
        mobile
          ? 'min-h-[5.25rem] items-center py-3 sm:min-h-[5.5rem] sm:py-3.5'
          : 'items-center justify-start py-3 xl:py-4 2xl:py-5',
      )}
    >
      <HeaderLogo
        className={cn(
          'relative z-10 min-w-0',
          mobile ? 'w-full justify-start gap-2.5' : 'max-w-full justify-start gap-3',
        )}
        imageClassName={cn(mobile ? 'h-12 w-auto sm:h-14' : 'h-16 w-auto')}
        wordmarkClassName={cn(
          mobile
            ? 'w-[11.25rem] max-w-[calc(100vw-8rem)] text-white sm:w-[13.25rem]'
            : 'w-[15.75rem] max-w-full text-white',
        )}
      />
    </AppLink>
  );
}

function ExpandedNavItem({ item, onNavigate }: { item: NavItem; onNavigate: () => void }) {
  const { pathname } = useLocation();
  const active =
    isPathActive(pathname, item.url) ||
    Boolean(item.children?.some((child) => isPathActive(pathname, child.url)));
  const [open, setOpen] = useState(false);

  if (item.children) {
    return (
      <div className="grid gap-3">
        <button
          type="button"
          className={cn(
            expandedNavHeadingClassName,
            'flex w-full cursor-pointer items-center justify-between gap-4 border-0 bg-transparent p-0',
            active && 'text-[#0077CC] underline decoration-4 underline-offset-8',
          )}
          aria-expanded={open}
          onClick={() => setOpen((prev) => !prev)}
        >
          <span>{item.label}</span>
          <Icon icon={open ? 'mdi:chevron-up' : 'mdi:chevron-down'} className="h-5 w-5 flex-none" />
        </button>

        <div className={cn('grid gap-4 overflow-hidden', !open && 'hidden')}>
          {item.children.map((child) => {
            const childActive = isPathActive(pathname, child.url);

            return (
              <AppLink
                key={child.url}
                href={child.url}
                onClick={onNavigate}
                className={cn(
                  expandedNavChildClassName,
                  'group -mx-2 rounded-lg px-2 py-1 transition-colors hover:bg-white/10 focus-visible:bg-white/10',
                  childActive && 'text-[#0077CC]',
                )}
              >
                <span
                  className={cn(
                    'block text-white transition-colors group-hover:text-[#0077CC]',
                    childActive && 'text-[#0077CC] underline decoration-2 underline-offset-8',
                  )}
                >
                  {child.label}
                </span>
                {child.description ? (
                  <span
                    className={cn(
                      'block pt-0.5 text-xs font-semibold leading-snug text-[#BDBDBD] transition-colors group-hover:text-blue-100 lg:text-[0.78rem]',
                      childActive && 'text-white/80',
                    )}
                  >
                    {child.description}
                  </span>
                ) : null}
              </AppLink>
            );
          })}
        </div>
      </div>
    );
  }

  return (
    <div className="grid gap-3">
      <AppLink
        href={item.url}
        onClick={onNavigate}
        className={cn(
          expandedNavHeadingClassName,
          active && 'text-[#0077CC] underline decoration-2 underline-offset-8',
        )}
      >
        {item.label}
      </AppLink>
    </div>
  );
}

function UserAvatar({ user, className = '' }: { user: CurrentUser; className?: string }) {
  const displayName = getDisplayName(user);

  const initials = getInitials(user);

  return (
    <span
      className={cn(
        'inline-flex h-[2.65rem] w-[2.65rem] flex-none items-center justify-center overflow-hidden rounded-full border border-white/25 bg-white/10',
        className,
      )}
    >
      {user.photo ? (
        <img src={user.photo} alt={displayName} className="h-full w-full object-cover" />
      ) : (
        <span className="flex h-full w-full items-center justify-center bg-white text-[0.8rem] font-extrabold text-[#0d78cb]">
          {initials}
        </span>
      )}
    </span>
  );
}

function UserDropdown({
  currentUser,
  onLogout,
  isLoggingOut,
  unreadThreadCount,
  onNavigate,
}: {
  currentUser: CurrentUser;
  onLogout: () => void;
  isLoggingOut: boolean;
  unreadThreadCount: number;
  onNavigate: () => void;
}) {
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  const isAdmin = currentUser.role?.includes("admin");
  const displayName = getDisplayName(currentUser);

  const baseMenuItems = authenticatedMenuItems.map((item) => {
    if (item.url === USER_ROUTES.PROFILE) return { ...item, label: 'My Profile' };
    if (item.url === USER_ROUTES.DASHBOARD) return { ...item, label: 'My Dashboard' };
    if (item.url === ROUTES.MESSAGES) return { ...item, label: 'Message Centre' };

    return item;
  });

  const menuItems = isAdmin
    ? [{ label: 'Admin Dashboard', url: ADMIN_ROUTES.DASHBOARD }, ...baseMenuItems]
    : baseMenuItems;

  return (
    <div className="w-full overflow-hidden rounded-[1rem] border border-white/25">
      <button
        type="button"
        className={cn(
          userAccountTriggerClassName,
          'cursor-pointer gap-3 border-0 px-[0.36rem] py-[0.32rem]',
        )}
        aria-expanded={open}
        onClick={() => setOpen((prev) => !prev)}
      >
        <UserAvatar user={currentUser} />
        <span className="flex flex-col gap-[0.1rem] text-left text-[0.85rem] leading-[1.05] text-white">
          <span className="font-semibold text-[11.7px] text-[#c9d6df]">Welcome,</span>
          <strong className="max-w-[7.65rem] overflow-hidden text-[0.9rem] font-bold text-ellipsis whitespace-nowrap text-white">
            {displayName}
          </strong>
        </span>
        <UnreadMessagesBadge count={unreadThreadCount} className="ml-1 flex-none" />
        <Icon
          icon={open ? 'mdi:chevron-up' : 'mdi:chevron-down'}
          className="h-[1.125rem] w-[1.125rem] flex-none text-[#9eb8ca]"
        />
      </button>

      {open && (
        <div className="grid gap-3 px-4 pb-4 pt-2">
          {menuItems.map((item) => (
            <AppLink
              key={item.url}
              href={item.url}
              className={cn(
                expandedNavChildClassName,
                'flex items-center justify-between text-white/90',
                isPathActive(pathname, item.url) &&
                  'text-[#0077CC] underline decoration-2 underline-offset-8',
              )}
              onClick={() => {
                setOpen(false);
                onNavigate();
              }}
            >
              <span>{item.label}</span>
              {item.url === ROUTES.MESSAGES ? (
                <UnreadMessagesBadge count={unreadThreadCount} />
              ) : null}
            </AppLink>
          ))}

          <button
            type="button"
            className="cursor-pointer border-0 bg-transparent p-0 text-left text-sm font-semibold text-white/90 transition-colors duration-150 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            disabled={isLoggingOut}
            onClick={() => {
              setOpen(false);
              onLogout();
            }}
          >
            {isLoggingOut ? 'Logging out...' : 'Logout'}
          </button>
        </div>
      )}
    </div>
  );
}

export function Navigation() {
  const { pathname, search } = useLocation();
  const { isAuthenticated, user: storeUser } = useAuth();
  const clearTokens = useTokenStore((state) => state.clearTokens);
  const clearIdentity = useIdentityStore((state) => state.clearIdentity);
  const { data: freshUser } = useCurrentUser();
  const inboxQuery = useMessagesInbox();
  const currentUser = (freshUser ?? storeUser) as CurrentUser | null;
  const authenticatedUser = isAuthenticated && currentUser ? currentUser : null;
  const unreadThreadCount = authenticatedUser ? (inboxQuery.data?.unreadThreadCount ?? 0) : 0;
  const [mobileOpen, setMobileOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const mobileMenuRef = useRef<HTMLDivElement>(null);
  const mobileButtonRef = useRef<HTMLButtonElement>(null);
  const closeMobileMenu = () => setMobileOpen(false);
  const previousThreadStatesRef = useRef<
    Map<string, { unreadCount: number; lastActivityAt: string }>
  >(new Map());
  const hasPrimedMessageFlashRef = useRef(false);
  const activeMessagesThreadId =
    pathname === ROUTES.MESSAGES ? new URLSearchParams(search).get('threadId') : null;

  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  useEffect(() => {
    const handleOutsideClick = (event: PointerEvent) => {
      if (
        !mobileMenuRef.current?.contains(event.target as Node) &&
        !mobileButtonRef.current?.contains(event.target as Node)
      ) {
        setMobileOpen(false);
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMobileOpen(false);
      }
    };

    document.addEventListener('pointerdown', handleOutsideClick);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handleOutsideClick);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  useEffect(() => {
    if (!mobileOpen || typeof window === 'undefined' || window.innerWidth >= 1024) {
      return;
    }

    const originalOverflow = document.body.style.overflow;
    const originalTouchAction = document.body.style.touchAction;

    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';

    return () => {
      document.body.style.overflow = originalOverflow;
      document.body.style.touchAction = originalTouchAction;
    };
  }, [mobileOpen]);

  useEffect(() => {
    if (!authenticatedUser || !inboxQuery.data) {
      previousThreadStatesRef.current = new Map();
      hasPrimedMessageFlashRef.current = false;
      return;
    }

    const nextThreadStates = new Map(
      inboxQuery.data.threads.map((thread) => [
        thread.id,
        {
          unreadCount: thread.unreadCount,
          lastActivityAt: thread.lastActivityAt,
        },
      ]),
    );

    if (!hasPrimedMessageFlashRef.current) {
      previousThreadStatesRef.current = nextThreadStates;
      hasPrimedMessageFlashRef.current = true;
      return;
    }

    inboxQuery.data.threads.forEach((thread) => {
      if (thread.lastMessageIsOwn || thread.unreadCount <= 0) {
        return;
      }

      if (pathname === ROUTES.MESSAGES && activeMessagesThreadId === thread.id) {
        return;
      }

      const previousThreadState = previousThreadStatesRef.current.get(thread.id);
      const isNewUnreadThread = !previousThreadState;
      const hasIncomingUpdate =
        isNewUnreadThread ||
        thread.unreadCount > previousThreadState.unreadCount ||
        thread.lastActivityAt !== previousThreadState.lastActivityAt;

      if (!hasIncomingUpdate) {
        return;
      }

      useToastStore.getState().addToast({
        type: 'info',
        title: buildMessageFlashTitle(thread),
        message: buildMessageFlashBody(thread),
        duration: 3000,
      });
    });

    previousThreadStatesRef.current = nextThreadStates;
  }, [activeMessagesThreadId, authenticatedUser, inboxQuery.data, pathname]);



  const handleLogout = async () => {
    const setLoggingOut = useTokenStore.getState().setLoggingOut;

    setMobileOpen(false);
    setIsLoggingOut(true);
    setLoggingOut(true);

    try {
      if (authenticatedUser) {
        await authApi.logout();
      }
    } finally {
      clearTokens();
      clearIdentity();

      const { clearCart, clearOwner } = useCartStore.getState();
      clearCart();
      clearOwner();

      window.location.replace(window.location.origin + ROUTES.HOME);
    }
  };

  return (
    <nav
      className={cn(navSurfaceClassName, 'sticky top-0 z-50 text-white')}
      aria-label="Primary navigation"
    >
      <div className="container-custom">
        <div className="grid min-h-[5.5rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-4 lg:min-h-[5.4375rem]">
          <BrandMark mobile />

          <button
            ref={mobileButtonRef}
            type="button"
            className="inline-flex h-12 w-12 cursor-pointer items-center justify-center rounded-full border-0 bg-transparent text-white transition-colors duration-150 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            aria-label="Toggle navigation menu"
            aria-expanded={mobileOpen}
            aria-controls="primary-navigation-menu"
            onClick={() => setMobileOpen((prev) => !prev)}
          >
            <Icon
              icon={mobileOpen ? 'mdi:close' : 'mdi:menu'}
              className="pointer-events-none h-9 w-9"
            />
          </button>
        </div>
      </div>

      <div
        id="primary-navigation-menu"
        ref={mobileMenuRef}
        className={cn(
          navSurfaceClassName,
          'absolute inset-x-0 top-full z-40 border-t border-white/20 shadow-[0_1.5rem_3rem_rgba(2,30,68,0.32)] transition-[opacity,transform,visibility] duration-200',
          mobileOpen
            ? 'visible translate-y-0 opacity-100'
            : 'pointer-events-none invisible -translate-y-2 opacity-0',
        )}
      >
        <div className="container-custom max-h-[calc(100dvh-5.5rem)] overflow-y-auto overscroll-contain pb-[max(2rem,env(safe-area-inset-bottom))] pt-7 lg:max-h-[calc(100dvh-5.4375rem)] lg:pb-8 lg:pt-8">
          <div className="grid gap-8 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto] lg:gap-[clamp(2.25rem,6vw,9rem)]">
            {expandedNavColumns.map((column, index) => (
              <div key={index} className="grid content-start gap-8">
                {column.map((item) => (
                  <ExpandedNavItem key={item.label} item={item} onNavigate={closeMobileMenu} />
                ))}
              </div>
            ))}

            <div className="grid content-start gap-4 lg:w-[15rem]">
              {authenticatedUser ? (
                <UserDropdown
                  currentUser={authenticatedUser}
                  onLogout={handleLogout}
                  isLoggingOut={isLoggingOut}
                  unreadThreadCount={unreadThreadCount}
                  onNavigate={closeMobileMenu}
                />
              ) : (
                <AppLink
                  href={AUTH_ROUTES.LOGIN}
                  onClick={closeMobileMenu}
                  className="inline-flex h-10 w-[7.5rem] items-center justify-center rounded-full bg-white text-center text-base font-semibold tracking-[0.03em] text-[#05245B] no-underline transition-colors duration-150 hover:bg-white/90"
                >
                  Sign In
                </AppLink>
              )}
            </div>
          </div>
        </div>
      </div>
    </nav>
  );
}
