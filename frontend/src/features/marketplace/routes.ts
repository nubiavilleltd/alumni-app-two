export const MARKETPLACE_ROUTES = {
  ROOT: '/marketplace',
  MY_BUSINESS: '/marketplace/my-business',
  DETAIL_PATH: '/marketplace/:id',
  DETAIL: (id: string | number) => `/marketplace/${id}`,
} as const;
