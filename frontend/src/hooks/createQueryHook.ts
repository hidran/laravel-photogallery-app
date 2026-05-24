import { useQuery, type UseQueryResult } from '@tanstack/react-query';

/**
 * Factory for creating simple TanStack Query hooks that fetch a single resource.
 *
 * @example
 * const useAlbums = createQueryHook('albums', getAlbums);
 * const useTags = createQueryHook('tags', getTags);
 */
export function createQueryHook<TData>(
  key: string,
  queryFn: () => Promise<TData>,
): () => UseQueryResult<TData, Error> {
  return () =>
    useQuery({
      queryKey: [key],
      queryFn,
    });
}
