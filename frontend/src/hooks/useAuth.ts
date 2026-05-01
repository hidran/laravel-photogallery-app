import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { login, logout, register, me } from '../api/auth';
import type { LoginPayload, RegisterPayload } from '../api/auth';
import { getToken } from '../api/client';

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: LoginPayload) => login(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['auth'] });
    },
  });
}

export function useRegister() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: RegisterPayload) => register(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['auth'] });
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => logout(),
    onSuccess: () => {
      queryClient.clear();
    },
  });
}

export function useMe() {
  // `enabled` is re-evaluated on each render cycle (TanStack Query v5).
  // After login, `setToken()` runs in the API wrapper before `onSuccess`,
  // then `queryClient.invalidateQueries` triggers a re-render, at which
  // point `getToken()` returns the new token and `enabled` becomes true.
  return useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => me(),
    enabled: getToken() !== null,
  });
}
