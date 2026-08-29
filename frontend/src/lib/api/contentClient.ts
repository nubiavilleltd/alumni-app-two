import axios from 'axios';
import { useTokenStore } from '@/features/authentication/stores/useTokenStore';
import { logError } from '@/lib/errors/errorUtils';

function readEnvUrl(value?: string) {
  return value?.trim().replace(/\/+$/, '') ?? '';
}

const configuredApiBaseUrl = readEnvUrl(import.meta.env.VITE_API_BASE_URL);
const configuredContentApiBaseUrl =
  readEnvUrl(import.meta.env.VITE_CONTENT_API_BASE_URL) ||
  (configuredApiBaseUrl ? `${configuredApiBaseUrl}/blog_api` : '');

export const contentApiClient = axios.create({
  baseURL: configuredContentApiBaseUrl,
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
});

function getPathname(url?: string) {
  if (!url) return '';

  try {
    return new URL(url, configuredContentApiBaseUrl || window.location.origin).pathname;
  } catch {
    return url;
  }
}

contentApiClient.interceptors.request.use(
  (config) => {
    const apiKey = import.meta.env.VITE_CONTENT_API_TOKEN || import.meta.env.VITE_API_TOKEN;
    const accessToken = useTokenStore.getState().accessToken;
    const shouldSkipBearer = Boolean(config.headers?.['X-Skip-Bearer']);

    if (apiKey) {
      config.headers['X-API-Key'] = apiKey;
    }

    if (accessToken && !shouldSkipBearer) {
      config.headers.Authorization = `Bearer ${accessToken}`;
    } else if (shouldSkipBearer) {
      delete config.headers.Authorization;
      delete config.headers['X-Skip-Bearer'];
    }

    if (config.data instanceof FormData) {
      delete config.headers['Content-Type'];
      delete config.headers['content-type'];
    }

    return config;
  },
  (error) => {
    logError(error, 'Content API Request');
    return Promise.reject(error);
  },
);

contentApiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.config) {
      console.log('Content API error details:', {
        status: error.response?.status,
        url: error.config.url,
        pathname: getPathname(error.config.url),
        method: error.config.method,
        hasApiKey: Boolean(error.config.headers?.['X-API-Key']),
        hasAuthorization: Boolean(error.config.headers?.Authorization),
        response: error.response?.data,
      });
    }

    logError(error, 'Content API Response');
    return Promise.reject(error);
  },
);
