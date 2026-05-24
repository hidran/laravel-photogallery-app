import { useQuery } from '@tanstack/react-query';
import { getAlbums, getAlbum, createAlbum, updateAlbum, deleteAlbum } from '../api/albums';
import { useMutationWithInvalidate } from './useMutationWithInvalidate';
import { createQueryHook } from './createQueryHook';
import type { CreateAlbumPayload, UpdateAlbumPayload } from '../api/albums';

export const useAlbums = createQueryHook('albums', getAlbums);

export function useAlbum(id: string) {
  return useQuery({
    queryKey: ['album', id],
    queryFn: () => getAlbum(id),
    enabled: id.length > 0,
  });
}

export function useCreateAlbum() {
  return useMutationWithInvalidate(
    (payload: CreateAlbumPayload) => createAlbum(payload),
    () => [['albums']],
  );
}

export function useUpdateAlbum() {
  return useMutationWithInvalidate(
    ({ id, payload }: { id: string; payload: UpdateAlbumPayload }) =>
      updateAlbum(id, payload),
    () => [['albums']],
  );
}

export function useDeleteAlbum() {
  return useMutationWithInvalidate(
    (id: string) => deleteAlbum(id),
    () => [['albums']],
  );
}
