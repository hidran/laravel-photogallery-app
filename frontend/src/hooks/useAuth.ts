import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { login, logout, register, me } from '../api/auth';
import { useMutationWithInvalidate } from './useMutationWithInvalidate';
import type { LoginPayload, RegisterPayload } from '../api/auth';
import { getToken } from '../api/client';

export function useLogin() {
  return useMutationWithInvalidate(
    (payload: LoginPayload) => login(payload),
    () => [['auth']],
  );
}

export function useRegister() {
  return useMutationWithInvalidate(
    (payload: RegisterPayload) => register(payload),
    () => [['auth']],
  );
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
