import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  getPhotos,
  getPhoto,
  updatePhoto,
  deletePhoto,
  deletePhotosBatch,
  addFavorite,
  removeFavorite,
  removeFavoritesBatch,
  removeAllFavorites,
} from '../api/photos';
import type { PhotosIndexParams, UpdatePhotoPayload } from '../api/photos';
import type { Photo, PaginatedResponse, SingleResponse } from '../types';

export function usePhotos(params: PhotosIndexParams = {}) {
  return useInfiniteQuery({
    queryKey: ['photos', params] as const,
    queryFn: ({ pageParam }) => getPhotos({ ...params, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage: PaginatedResponse<Photo>) =>
      lastPage.meta.next_cursor ?? undefined,
  });
}

export function usePhoto(id: string) {
  return useQuery({
    queryKey: ['photo', id],
    queryFn: () => getPhoto(id),
    enabled: id.length > 0,
  });
}

export function useUpdatePhoto() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdatePhotoPayload }) =>
      updatePhoto(id, payload),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
      queryClient.invalidateQueries({ queryKey: ['photo', variables.id] });
    },
  });
}

export function useDeletePhoto() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deletePhoto(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
    },
  });
}

export function useDeletePhotosBatch() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (photoIds: string[]) => deletePhotosBatch(photoIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
    },
  });
}

export function useToggleFavorite() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, isFavorite }: { id: string; isFavorite: boolean }) =>
      isFavorite ? removeFavorite(id) : addFavorite(id),
    onMutate: async ({ id, isFavorite }) => {
      await queryClient.cancelQueries({ queryKey: ['photo', id] });
      await queryClient.cancelQueries({ queryKey: ['photos'] });

      const previousPhoto = queryClient.getQueryData<SingleResponse<Photo>>(['photo', id]);

      // Update single-photo cache
      queryClient.setQueryData<SingleResponse<Photo>>(['photo', id], (old) => {
        if (!old) return old;
        return {
          ...old,
          data: {
            ...old.data,
            is_favorite: !isFavorite,
            favorites_count: old.data.favorites_count + (isFavorite ? -1 : 1),
          },
        };
      });

      // Update all infinite list caches (photos list)
      queryClient.setQueriesData<{ pages: PaginatedResponse<Photo>[]; pageParams: unknown[] }>(
        { queryKey: ['photos'] },
        (old) => {
          if (!old) return old;
          return {
            ...old,
            pages: old.pages.map((page) => ({
              ...page,
              data: page.data.map((photo) =>
                photo.id === id
                  ? {
                      ...photo,
                      is_favorite: !isFavorite,
                      favorites_count: photo.favorites_count + (isFavorite ? -1 : 1),
                    }
                  : photo,
              ),
            })),
          };
        },
      );

      return { previousPhoto };
    },
    onError: (_err, { id }, context) => {
      if (context?.previousPhoto) {
        queryClient.setQueryData(['photo', id], context.previousPhoto);
      }
      // onSettled handles list refetch for both success and error
    },
    onSettled: (_data, _error, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
      queryClient.invalidateQueries({ queryKey: ['photo', id] });
    },
  });
}

export function useRemoveFavoritesBatch() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (photoIds: string[]) => removeFavoritesBatch(photoIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
    },
  });
}

export function useRemoveAllFavorites() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => removeAllFavorites(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
    },
  });
}
