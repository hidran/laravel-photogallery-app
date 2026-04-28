import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getAlbums, getAlbum, createAlbum, updateAlbum, deleteAlbum } from '../api/albums';
import type { CreateAlbumPayload, UpdateAlbumPayload } from '../api/albums';

export function useAlbums() {
  return useQuery({
    queryKey: ['albums'],
    queryFn: () => getAlbums(),
  });
}

export function useAlbum(id: string) {
  return useQuery({
    queryKey: ['album', id],
    queryFn: () => getAlbum(id),
    enabled: id.length > 0,
  });
}

export function useCreateAlbum() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateAlbumPayload) => createAlbum(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['albums'] });
    },
  });
}

export function useUpdateAlbum() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateAlbumPayload }) =>
      updateAlbum(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['albums'] });
    },
  });
}

export function useDeleteAlbum() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deleteAlbum(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['albums'] });
    },
  });
}
