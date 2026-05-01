import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseMutationOptions, UseMutationResult } from '@tanstack/react-query';

/**
 * Factory for creating TanStack Query mutations that invalidate
 * one or more query keys on success.
 *
 * @example
 * const update = useMutationWithInvalidate(
 *   ({ id, payload }) => api.update(id, payload),
 *   (vars) => [['photos'], ['photo', vars.id]],
 * );
 */
export function useMutationWithInvalidate<TVariables, TData = unknown, TError = Error>(
  mutationFn: (variables: TVariables) => Promise<TData>,
  getInvalidateKeys: (variables: TVariables) => unknown[][],
  options?: Omit<UseMutationOptions<TData, TError, TVariables>, 'mutationFn'>,
): UseMutationResult<TData, TError, TVariables> {
  const queryClient = useQueryClient();
  const userOnSuccess = options?.onSuccess;

  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      const variables = args[1];
      const keys = getInvalidateKeys(variables);
      keys.forEach((key) => {
        queryClient.invalidateQueries({ queryKey: key });
      });
      userOnSuccess?.(...args);
    },
  });
}
