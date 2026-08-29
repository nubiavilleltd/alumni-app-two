//src/App.tsx

import { Navigate, Route, Routes } from 'react-router-dom';
import { RootLayout } from './shared/components/layout/RootLayout';
import { ErrorBoundary } from './shared/components/ui/ErrorBoundary';
import { ProtectedRoute } from './shared/components/routing/ProtectedRoute';
import { AdminRoute } from './shared/components/routing/AdminRoute';

import { ROUTES } from '@/shared/constants/routes';

// Pages
import { HomePage } from './pages/home/HomePage';
import { AboutPage } from './pages/about/AboutPage';
import { ContactUsPage } from './features/contactUs/pages/ContactUsPage';
import { PrivacyPage } from './pages/legal/PrivacyPage';
import { TermsPage } from './pages/legal/TermsPage';
import { NotFoundPage } from './pages/errors/NotFoundPage';
import ProjectsPage from './features/projects/pages/ProjectsPage';
import LeadershipPage from './features/leadership/pages/LeadershipPage';
import AnnouncementsPage from './features/announcements/pages/BlogIndexPage';
import BlogPostPage from './features/announcements/pages/BlogPostPage';
import { BlogComingSoonPage } from './features/announcements/pages/BlogComingSoonPage';
import { BlogDetailPage } from './features/blogs/pages/BlogDetailPage';
import { ANNOUNCEMENT_ROUTES } from './features/announcements/routes';

import { AuthPage } from './features/authentication/pages/AuthPage';
import { RegisterDetailsPage } from './features/authentication/pages/RegisterDetailsPage';
import { RegisterVerificationPage } from './features/authentication/pages/RegisterVerificationPage';
import { RegisterSuccessPage } from './features/authentication/pages/RegisterSuccessPage';

import { AlumniRedirectPage } from './features/alumni/pages/AlumniRedirectPage';
import { AlumniDirectoryPage } from './features/alumni/pages/AlumniDirectoryPage';
import { AlumniProfilePage } from './features/alumni/pages/AlumniProfilePage';

import { EventsPage } from './features/events/pages/EventsPage';
import { EventDetailPage } from './features/events/pages/EventDetailPage';
import { MyEventsPage } from './features/events/pages/MyEventsPage';
import AttendeesPage from './features/events/pages/AttendeesPage';
import AdminEventsPage from './features/events/pages/AdminEventsPage';
import CreateEventPage from '@/features/events/pages/CreateEventPage';
import EditEventPage from '@/features/events/pages/EditEventPage';

import MarketPlacePage from './features/marketplace/pages/MarketPlacePage';
import MyBusinessPage from './features/marketplace/pages/MyBusinessPage';
import BusinessDetailPage from './features/marketplace/pages/BusinessDetailPage';

import { UserDashboardPage } from './features/user/pages/UserDashboardPage';
import UserProfilePage from './features/user/pages/UserProfilePage';
import SettingsPage from './features/user/pages/SettingsPage';
import { MessagesPage } from './features/messages/pages/MessagesPage';

import { AdminDashboardPage } from './features/admin/pages/AdminDashboardPage';
import ProjectDetailsPage from './features/projects/pages/ProjectDetail';
import { ADMIN_ORDER_ROUTES, ADMIN_ROUTES, ADMIN_STORE_ROUTES } from './features/admin/routes';
import { AdminMembersPage } from './features/admin/pages/AdminMembersPage';
import { AdminPagesContentPage } from './features/admin/pages/AdminPagesContentPage';
import { AdminEventRegistrationsPage } from './features/events/pages/AdminEventRegistrationsPage';
import { AdminAnnouncementsPage } from './features/announcements/pages/AdminAnnouncementsPage';
import { COMING_SOON_ROUTES } from './config/comingSoonRoutes';
import { ComingSoonRouteHandler } from './pages/errors/ComingSoonRouteHandler';
import { GuestRoute } from './shared/components/routing/GuestRoute';
import EditProfilePage from './features/user/pages/EditProfilePage';
import ResourcesPage from './pages/resources/ResourcesPage';
import WelfarePage from './features/welfare/pages/WelfarePage';
import WelfareZonesPage from './features/welfare/pages/WelfareZonesPage';
import JobVacanciesPage from './features/jobVacancies/pages/JobVacanciesPage';
import JobVacancyDetailPage from './features/jobVacancies/pages/JobVacancyDetailPage';
import MyJobPostsPage from './features/jobVacancies/pages/MyJobPostsPage';
import { DonationPage } from './features/donation/pages/DonationPage';
import { WelfareCommitteeContactPage } from './features/contactUs/pages/WelfareCommitteeContactPage';
import AdminProjectsPage from './features/projects/pages/AdminProjectsPage';
import { LogoutGate } from './shared/components/routing/LogoutGate';
import { LIVE_NEWS_ROUTES } from './features/liveNews/routes';
import LiveNewsPage from './features/liveNews/pages/LiveNewsPage';
import LiveNewsDetailPage from './features/liveNews/pages/LiveNewsDetailPage';
import SocialMediaFeedPage from './features/socialMedia/pages/SocialMediaFeedPage';
import { StorePage } from './features/store/pages/StorePage';
import { CartPage } from './features/store/pages/CartPage';
import { CheckoutPage } from './features/store/pages/CheckoutPage';
import { FaqPage } from './features/faqs/pages/FaqPage';
import { AdminStorePage } from './features/admin/pages/AdminStorePage';
import { ProductCreatePage } from './features/admin/pages/ProductCreatePage';
import { ProductEditPage } from './features/admin/pages/ProductEditPage';
import OrderHistoryPage from './features/store/pages/OrderHistoryPage';
import AdminOrderManagementPage from './features/admin/pages/AdminOrderManagementPage';
import OrderDetailsPage from './features/store/pages/OrderDetailsPage';
import AdminOrderDetailsPage from './features/admin/pages/AdminOrderDetailsPage';
import JoinProjectsPage from './features/joinProject/pages/JoinProjectsPage';
import VolunteerPage from './features/joinProject/pages/VolunteerPage';
import AdminProjectDetailsPage from './features/projects/pages/AdminProjectDetail';
import { AdminEventDetailPage } from './features/events/pages/AdminEventDetailPage';

export default function App() {
  return (
    <ErrorBoundary>
      <LogoutGate>
        <Routes>
          <Route element={<RootLayout />}>
            {/* Public */}
            <Route
              path={ROUTES.HOME}
              element={
                <ErrorBoundary>
                  <HomePage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.ABOUT}
              element={
                <ErrorBoundary>
                  <AboutPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.CONTACT}
              element={
                <ErrorBoundary>
                  <ContactUsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.FAQS}
              element={
                <ErrorBoundary>
                  <FaqPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.WELFARE_COMMITTEE_CONTACT}
              element={
                <ErrorBoundary>
                  <WelfareCommitteeContactPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.NEWS}
              element={
                <ErrorBoundary>
                  <AnnouncementsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ANNOUNCEMENT_ROUTES.BLOG}
              element={
                <ErrorBoundary>
                  <BlogComingSoonPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ANNOUNCEMENT_ROUTES.BLOG_DETAIL_PATH}
              element={
                <ErrorBoundary>
                  <BlogDetailPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ANNOUNCEMENT_ROUTES.DETAIL_PATH}
              element={
                <ErrorBoundary>
                  <BlogPostPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.PRIVACY}
              element={
                <ErrorBoundary>
                  <PrivacyPage />
                </ErrorBoundary>
              }
            />

            <Route path={ROUTES.RESOURCES} element={<ResourcesPage />} />
            <Route path={ROUTES.WELFARE} element={<WelfarePage />} />
            <Route path={ROUTES.WELFARE_ZONES} element={<WelfareZonesPage />} />
            <Route path={ROUTES.DONATION} element={<DonationPage />} />
            <Route path={ROUTES.SOCIAL_MEDIA_FEED} element={<SocialMediaFeedPage />} />
            <Route path={ROUTES.LIVE_NEWS.ROOT} element={<LiveNewsPage />} />
            <Route path={ROUTES.LIVE_NEWS.DETAIL_PATH} element={<LiveNewsDetailPage />} />
            <Route
              path={ROUTES.JOB_VACANCIES}
              element={
                <ErrorBoundary>
                  <JobVacanciesPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.MY_JOB_POSTS}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <MyJobPostsPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />
            <Route
              path={ROUTES.JOB_VACANCY_DETAIL_PATH}
              element={
                <ErrorBoundary>
                  <JobVacancyDetailPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.TERMS}
              element={
                <ErrorBoundary>
                  <TermsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.JOIN_PROJECTS.PROJECTS}
              element={
                <ErrorBoundary>
                  <JoinProjectsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.JOIN_PROJECTS.VOLUNTEER}
              element={
                <ErrorBoundary>
                  <VolunteerPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.PROJECTS.ROOT}
              element={
                <ErrorBoundary>
                  <ProjectsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.PROJECTS.DETAIL_PATH}
              element={
                <ErrorBoundary>
                  <ProjectDetailsPage />
                </ErrorBoundary>
              }
            />

            <Route
              path={ROUTES.LEADERSHIP}
              element={
                <ErrorBoundary>
                  <LeadershipPage />
                </ErrorBoundary>
              }
            />

            {/* Alumni */}
            <Route path={ROUTES.ALUMNI.ROOT} element={<AlumniRedirectPage />} />
            <Route
              path={ROUTES.ALUMNI.PROFILES}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <AlumniDirectoryPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />
            <Route
              path={ROUTES.ALUMNI.PROFILE(':slug')}
              element={
                <ErrorBoundary>
                  <AlumniProfilePage />
                </ErrorBoundary>
              }
            />

            {/* Events */}
            <Route
              path={ROUTES.EVENTS.ROOT}
              element={
                <ErrorBoundary>
                  <EventsPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.EVENTS.ATTENDEES(':id')}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AttendeesPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />
            <Route
              path={ROUTES.EVENTS.DETAIL(':slug')}
              element={
                <ErrorBoundary>
                  <EventDetailPage />
                </ErrorBoundary>
              }
            />

            <Route
              path={ROUTES.EVENTS.MY_EVENTS}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <MyEventsPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.EVENTS.CREATE}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <CreateEventPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.EVENTS.EDIT(':id')}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <EditEventPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            {/* Marketplace */}
            <Route
              path={ROUTES.MARKETPLACE.ROOT}
              element={
                <ErrorBoundary>
                  <MarketPlacePage />
                </ErrorBoundary>
              }
            />

            <Route
              path={ROUTES.MARKETPLACE.MY_BUSINESS}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <MyBusinessPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.MARKETPLACE.DETAIL_PATH}
              element={
                <ErrorBoundary>
                  <BusinessDetailPage />
                </ErrorBoundary>
              }
            />

            {/* Auth */}
            <Route path={ROUTES.AUTH.ROOT} element={<Navigate to={ROUTES.AUTH.LOGIN} replace />} />
            {/* <Route path={ROUTES.AUTH.LOGIN} element={<AuthPage mode="login" />} /> */}
            <Route
              path={ROUTES.AUTH.LOGIN}
              element={
                <GuestRoute>
                  <AuthPage mode="login" />
                </GuestRoute>
              }
            />
            <Route
              path={ROUTES.AUTH.REGISTER}
              element={
                <GuestRoute>
                  <RegisterDetailsPage />
                </GuestRoute>
              }
            />
            <Route
              path={ROUTES.AUTH.REGISTER_VERIFY}
              element={
                <GuestRoute>
                  <RegisterVerificationPage />
                </GuestRoute>
              }
            />
            <Route
              path={ROUTES.AUTH.REGISTER_SUCCESS}
              element={
                <GuestRoute>
                  <RegisterSuccessPage />
                </GuestRoute>
              }
            />
            <Route
              path={ROUTES.AUTH.FORGOT_PASSWORD}
              element={<AuthPage mode="forgot-password" />}
            />
            <Route path={ROUTES.AUTH.RESET_PASSWORD} element={<AuthPage mode="reset-password" />} />
            <Route
              path={ROUTES.AUTH.RESET_PASSWORD_WITH_CODE(':code')}
              element={<AuthPage mode="reset-password" />}
            />

            {/* User */}
            <Route
              path={ROUTES.USER.DASHBOARD}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <UserDashboardPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.USER.EDIT_PROFILE}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <EditProfilePage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.USER.PROFILE}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <UserProfilePage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            <Route
              path={ROUTES.USER.SETTINGS}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <SettingsPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />
            <Route
              path={ROUTES.MESSAGES}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <MessagesPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />

            {/* Admin */}
            <Route
              path={ROUTES.ADMIN.DASHBOARD}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminDashboardPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.MEMBERS}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminMembersPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.EVENTS}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminEventsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

                 <Route
              path={ADMIN_ROUTES.EVENT_DETAIL_PATH}
              element={

                <AdminRoute>
                  <ErrorBoundary>
                    <AdminEventDetailPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.EVENT_REGISTRATIONS}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminEventRegistrationsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.ANNOUNCEMENTS}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminAnnouncementsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.PROJECTS}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminProjectsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.PROJECT_DETAIL_PATH}
              element={

                <AdminRoute>
                  <ErrorBoundary>
                    <AdminProjectDetailsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_ROUTES.PAGES_CONTENT}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminPagesContentPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ROUTES.STORE.ROOT}
              element={
                <ErrorBoundary>
                  <StorePage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.STORE.CART}
              element={
                <ErrorBoundary>
                  <CartPage />
                </ErrorBoundary>
              }
            />
            <Route
              path={ROUTES.STORE.CHECKOUT}
              element={
                <ErrorBoundary>
                  <CheckoutPage />
                </ErrorBoundary>
              }
            />

            {/* ORDER ROUTES */}
            <Route
              path={ROUTES.ORDER.ROOT}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <OrderHistoryPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />
            <Route
              path={ROUTES.ORDER.DETAIL_PATH}
              element={
                <ProtectedRoute>
                  <ErrorBoundary>
                    <OrderDetailsPage />
                  </ErrorBoundary>
                </ProtectedRoute>
              }
            />
            <Route
              path={ADMIN_ORDER_ROUTES.ROOT}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminOrderManagementPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />
            <Route
              path={ADMIN_ORDER_ROUTES.DETAIL_PATH}
              element={
                <AdminRoute>
                  <ErrorBoundary>
                    <AdminOrderDetailsPage />
                  </ErrorBoundary>
                </AdminRoute>
              }
            />

            <Route
              path={ADMIN_STORE_ROUTES.ROOT}
              element={
                <AdminRoute>
                  <AdminStorePage />
                </AdminRoute>
              }
            />
            <Route
              path={ADMIN_STORE_ROUTES.PRODUCT_CREATE}
              element={
                <AdminRoute>
                  <ProductCreatePage />
                </AdminRoute>
              }
            />
            <Route
              path={ADMIN_STORE_ROUTES.PRODUCT_EDIT_PATH}
              element={
                <AdminRoute>
                  <ProductEditPage />
                </AdminRoute>
              }
            />

            {/* Redirects */}
            <Route path="/home" element={<Navigate to={ROUTES.HOME} replace />} />
            {/* Coming Soon routes (dynamic) */}
            {COMING_SOON_ROUTES.map(({ prefix, title }) => (
              <Route
                key={prefix}
                path={`/${prefix}/*`}
                element={
                  <ErrorBoundary>
                    <ComingSoonRouteHandler title={title} />
                  </ErrorBoundary>
                }
              />
            ))}
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </LogoutGate>
    </ErrorBoundary>
  );
}
