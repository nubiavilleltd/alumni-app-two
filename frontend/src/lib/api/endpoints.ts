// lib/api/endpoints.ts
//
// Single source of truth for all API endpoint paths.
// When a backend URL changes, update it here only.

export const API_ENDPOINTS = {
  // ─── Auth ─────────────────────────────────────────────────────────────────
  AUTH: {
    LOGIN: '/api/login',
    REGISTER: '/api/register',
    LOGOUT: '/api/logout',
    REFRESH_TOKEN: '/api/refresh_token',
    FORGOT_PASSWORD: '/api/forgot_password',
    RESET_PASSWORD: '/api/reset_password',
    VERIFY_EMAIL: '/api/verify_email',
    RESEND_VERIFY_EMAIL: '/api/resend_verify_email',
    GET_VOUCHERS: '/api/get_vouchers',
    GET_CITIES: '/api/get_cities',
    SOCIAL_LOGIN: '/socials/social_login',
    SOCIAL_SIGNUP: '/socials/social_signup',
  },

  // ─── Alumni ───────────────────────────────────────────────────────────────
  ALUMNI: {
    LIST: '/api/get_users_by_action',
    DETAIL: (slug: string) => `/api/alumni/${slug}`,
    UPDATE: (id: string) => `/api/alumni/${id}`,
  },

  // ─── Marketplace ──────────────────────────────────────────────────────────
  // All three backend endpoints live here. The service no longer defines
  // its own local ENDPOINTS object.
  MARKETPLACE: {
    GET_LISTINGS: '/api/get_listings',
    CREATE_LISTING: '/api/create_listing',
    MANAGE_LISTING: '/api/manage_listing', // update + delete via function_type flag
  },

  LIVENEWS: {
    GET_LIVE_NEWS: '/news/feeds',
  },

  // ─── Shared locations ─────────────────────────────────────────────────────
  // Proposed backend contract: GET /api/fetch_locations
  LOCATIONS: {
    FETCH: '/api/fetch_locations',
  },

  // ─── Events ───────────────────────────────────────────────────────────────
  EVENTS: {
    GET_EVENTS: '/api/get_events',
    CREATE_EVENT: '/api/create_event',
    MANAGE_EVENT: '/api/manage_event',
    REGISTER_EVENT: '/api/register_event',
    MANAGE_EVENT_RSVP: '/api/manage_event_rsvp',
    GET_ATTENDEES: '/api/get_event_attendees',
  },

  // ─── User / Profile ───────────────────────────────────────────────────────

  USER: {
    UPDATE_PROFILE: '/api/update_profile',
    GET_PROFILE_VISIBILITY: '/api/get_profile_visibility',
    UPDATE_PROFILE_VISIBILITY: '/api/update_profile_visibility',
    GET_USER_PROFILE: '/api/get_user_profile',
    GET_USERS_BY_ACTION: '/api/get_users_by_action',
    CHANGE_PASSWORD: '/api/change_user_password',
    MANAGE_ACCOUNT: '/api/manage_user_account',
  },

  // ─── Messages ──────────────────────────────────────────────────────────────
  MESSAGES: {
    THREADS: '/chat_api/v2_get_threads',
    THREAD_DETAIL: '/chat_api/v2_get_thread',
    DIRECT_THREAD: '/chat_api/v2_send_direct',
    GROUP_THREAD: '/chat_api/v2_create_group',
    SEND_MESSAGE: '/chat_api/v2_send_message',
    DELETE_MESSAGE: '/chat_api/v2_delete_message',
    MARK_READ: '/chat_api/v2_mark_read',
    ATTACHMENTS: '/chat_api/v2_upload_attachment',
    ATTACHMENT_DOWNLOAD: '/chat_api/v2_attachments',
    POLL: '/chat_api/v2_get_threads',
  },

  WELFARE: {
    GET_ZONES: '/api/get_zones',
  },

  // ─── Admin ────────────────────────────────────────────────────────────────
  ADMIN: {
    MEMBER_LIST: '/api/admin/members/list',
    APPROVE_USER: '/api/approve_user',
  },

  // ─── Projects ─────────────────────────────────────────────────────────────
  PROJECTS: {
    LIST: '/api/get_projects',
    CREATE: '/api/create_project',
    MANAGE: '/api/manage_project',
    // keep donate if it's separate
    DONATE: (id: string) => `/projects/${id}/donate`,
  },

  // ─── Contact ─────────────────────────────────────────────────────────────
  CONTACT: {
    CREATE: '/api/contact_us',
  },

  // ─── JOB VACANCIES ─────────────────────────────────────────────────────────────
  JOB_VACANCIES: {
    GET: '/api/get_vacancies',
    CREATE: '/api/create_vacancy',
    MANAGE: '/api/manage_vacancy',
  },

  // ─── Leadership ───────────────────────────────────────────────────────────
  LEADERSHIP: {
    GET_LEADERSHIP: '/api/get_leadership',
    // CMS endpoints (future):
    CREATE_LEADER: '/api/create_leader',
    MANAGE_LEADER: '/api/manage_leader',
  },

  // ─── Announcements ────────────────────────────────────────────────────────
  ANNOUNCEMENTS: {
    LIST: '/api/get_announcements',
    CREATE: '/api/create_announcement',
    MANAGE: '/api/manage_announcement',
    BIRTHDAYS: '/api/get_birthdays',
  },

  // ─── Pages Content ────────────────────────────────────────────────────────
  CONTENT: {
    HOMEPAGE: '/homepage',
    UPDATE_HOMEPAGE_TEXT: '/update_homepage_text',
    CREATE_CAROUSEL_IMAGE: '/create_carousel_image',
    UPDATE_CAROUSEL_IMAGE: '/update_carousel_image',
    REORDER_CAROUSEL: '/reorder_carousel',
    DELETE_CAROUSEL_IMAGE: '/delete_carousel_image',
    FAQS: '/faqs',
    CREATE_FAQ: '/create_faq',
    UPDATE_FAQ: '/update_faq',
    REORDER_FAQS: '/reorder_faqs',
    DELETE_FAQ: '/delete_faq',
    BLOG_CATEGORIES: '/blog_categories',
    CREATE_BLOG_CATEGORY: '/create_blog_category',
    UPDATE_BLOG_CATEGORY: '/update_blog_category',
    DELETE_BLOG_CATEGORY: '/delete_blog_category',
    REORDER_BLOG_CATEGORIES: '/reorder_categories',
    BLOG_POSTS: '/blog_posts',
    BLOG_POST_DETAIL: (idOrSlug: string) => `/blog_post_detail/${idOrSlug}`,
    CREATE_BLOG_POST: '/create_blog_post',
    UPDATE_BLOG_POST: '/update_blog_post',
    DELETE_BLOG_POST: '/delete_blog_post',
  },

  ADMIN_ENDPOINTS: {
    MEMBER_LIST: '/api/get_users_by_action', // POST — action_type flag in body
    APPROVE_USER: '/api/approve_user', // POST — action: approve | reject
  },

  VOUCHER: {
    GET_PENDING: '/api/voucher_pending',
    ACTION: '/api/vouch_action',
  },

  STORE: {
    FETCH_ADDRESSES: '/product/fetch_addresses',
    ADD_ADDRESS: '/product/add_address',
    EDIT_ADDRESS: '/product/edit_address',
    DELETE_ADDRESS: '/product/delete_address',

    FETCH_DELIVERY_ZONES: '/product/fetch_delivery_zones',

    INITIATE_CHECKOUT: '/product/initiate_checkout',
    VERIFY_PAYMENT: '/product/verify_payment',
  },

  ORDER: {
    FETCH_ORDERS: '/product/fetch_orders',
    // TEMPORARY: backend has not provided this endpoint yet.
    // Pointing at FETCH_ORDERS as a stand-in so admin UI can be built/tested.
    // ⚠️ This means the "admin" order list currently only returns the
    // current user's own orders, NOT all orders across the system.
    // TODO: revert to '/product/fetch_orders_management' once backend ships it.
    FETCH_ORDERS_MANAGEMENT: '/product/order_management',
    UPDATE_ORDER_STATUS: '/product/update_order_status',
  },
} as const;
