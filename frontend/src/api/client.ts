import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { toast } from 'sonner';
import { copy } from '../data/copy';
import type { ApiError } from '../types';

const TOKEN_KEY = 'auth_token';

let token: string | null = localStorage.getItem(TOKEN_KEY);

export function setToken(newToken: string): void {
  token = newToken;
  localStorage.setItem(TOKEN_KEY, newToken);
}

export function clearToken(): void {
  token = null;
  localStorage.removeItem(TOKEN_KEY);
}

export function getToken(): string | null {
  return token;
}

export const apiClient = axios.create({
  baseURL: '/api/v1',
});

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  config.headers.set('Accept', 'application/json');
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const status = error.response?.status;

    if (status === 401) {
      clearToken();
      window.location.href = '/login';
      return Promise.reject(error);
    }

    if (status === 413) {
      toast.error(copy.errors.tooLarge);
      return Promise.reject(error);
    }

    if (status === 422) {
      return Promise.reject(error);
    }

    if (status !== undefined && status >= 500) {
      toast.error(copy.errors.serverError);
      return Promise.reject(error);
    }

    return Promise.reject(error);
  },
);
