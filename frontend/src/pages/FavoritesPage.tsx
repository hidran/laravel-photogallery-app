import { useCallback, useMemo, useState } from 'react';
import { Heart, X, Check } from 'lucide-react';
import { toast } from 'sonner';
import {
  usePhotos,
  useRemoveFavoritesBatch,
  useRemoveAllFavorites,
  useDeletePhoto,
} from '../hooks';
import { useMe } from '../hooks/useAuth';
import { useLightboxNav } from '../hooks/useLightboxNav';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { copy } from '../data/copy';
import type { Photo } from '../types';

export function FavoritesPage() {
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [photoToDelete, setPhotoToDelete] = useState<Photo | null>(null);

  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos({ favorites: true });
  const removeBatch = useRemoveFavoritesBatch();
  const removeAll = useRemoveAllFavorites();
  const deletePhoto = useDeletePhoto();
  const { data: meData } = useMe();
  const currentUserId = meData?.data?.id;

  const allPhotos = useMemo(() => data?.pages.flatMap((page) => page.data) ?? [], [data]);

  const { lightboxPhoto, openLightbox, navigateLightbox, closeLightbox } =
    useLightboxNav(allPhotos);

  const toggleSelect = useCallback((photoId: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(photoId)) {
        next.delete(photoId);
      } else {
        next.add(photoId);
      }
      return next;
    });
  }, []);

  const handlePhotoClick = useCallback(
    (photo: Photo) => {
      if (selectMode) {
        toggleSelect(photo.id);
      } else {
        openLightbox(photo);
      }
    },
    [selectMode, toggleSelect, openLightbox],
  );

  const handleRemoveSelected = useCallback(() => {
    const ids = Array.from(selectedIds);
    removeBatch.mutate(ids, {
      onSuccess: () => {
        setSelectedIds(new Set());
        setSelectMode(false);
      },
    });
  }, [selectedIds, removeBatch]);

  const handleRemoveAll = useCallback(() => {
    removeAll.mutate(undefined, {
      onSuccess: () => {
        setSelectedIds(new Set());
        setSelectMode(false);
      },
    });
  }, [removeAll]);

  const handleCardDelete = useCallback((photo: Photo) => {
    setPhotoToDelete(photo);
  }, []);

  const handleConfirmDelete = useCallback(() => {
    if (!photoToDelete) return;
    deletePhoto.mutate(photoToDelete.id, {
      onSuccess: () => {
        setPhotoToDelete(null);
        toast.success('Photo deleted');
      },
    });
  }, [photoToDelete, deletePhoto]);

  const handleCancelSelect = useCallback(() => {
    setSelectMode(false);
    setSelectedIds(new Set());
  }, []);

  const handleLoadMore = useCallback(() => {
    if (hasNextPage) {
      void fetchNextPage();
    }
  }, [fetchNextPage, hasNextPage]);

  if (isLoading) {
    return <p className="p-6 text-center text-gray-500">{copy.gallery.loading}</p>;
  }

  if (allPhotos.length === 0) {
    return <p className="p-6 text-center text-gray-500">{copy.gallery.emptyFavorites}</p>;
  }

  return (
    <>
      {/* Action bar */}
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-semibold text-gray-900">{copy.favorites.title}</h2>
        <div className="flex items-center gap-2">
          {selectMode ? (
            <>
              <span className="text-sm text-gray-500">
                {copy.favorites.selectedCount(selectedIds.size)}
              </span>
              <button
                type="button"
                onClick={handleRemoveSelected}
                disabled={selectedIds.size === 0 || removeBatch.isPending}
                className="inline-flex items-center gap-1 rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              >
                <Heart className="h-4 w-4" />
                {copy.favorites.removeSelected}
              </button>
              <button
                type="button"
                onClick={handleRemoveAll}
                disabled={removeAll.isPending}
                className="inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
              >
                {copy.favorites.removeAll}
              </button>
              <button
                type="button"
                onClick={handleCancelSelect}
                className="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                <X className="h-4 w-4" />
                {copy.favorites.cancelSelect}
              </button>
            </>
          ) : (
            <button
              type="button"
              onClick={() => setSelectMode(true)}
              className="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              {copy.favorites.selectMode}
            </button>
          )}
        </div>
      </div>

      {/* Grid with optional selection overlay */}
      <div className="relative">
        <MasonryGrid
          photos={allPhotos}
          onLoadMore={handleLoadMore}
          hasMore={!!hasNextPage}
          onClick={handlePhotoClick}
          onDelete={handleCardDelete}
          {...(currentUserId ? { currentUserId } : {})}
          renderOverlay={
            selectMode
              ? (photo) => (
                  <div
                    className={`flex h-full w-full items-start justify-end p-2 ${
                      selectedIds.has(photo.id)
                        ? 'bg-brand-500/20 ring-2 ring-inset ring-brand-500'
                        : ''
                    }`}
                  >
                    <div
                      className={`flex h-6 w-6 items-center justify-center rounded-full border-2 ${
                        selectedIds.has(photo.id)
                          ? 'border-brand-600 bg-brand-600 text-white'
                          : 'border-white bg-white/80 text-transparent'
                      }`}
                    >
                      <Check className="h-4 w-4" />
                    </div>
                  </div>
                )
              : undefined
          }
        />
      </div>

      {lightboxPhoto && !selectMode && (
        <PhotoLightbox
          photo={lightboxPhoto}
          photos={allPhotos}
          onClose={closeLightbox}
          onNavigate={navigateLightbox}
        />
      )}

      <ConfirmDialog
        isOpen={photoToDelete !== null}
        onClose={() => setPhotoToDelete(null)}
        onConfirm={handleConfirmDelete}
        title={copy.lightbox.delete}
        message={copy.lightbox.deleteConfirm}
        confirmLabel={copy.common.delete}
        variant="danger"
        isPending={deletePhoto.isPending}
      />
    </>
  );
}

export default FavoritesPage;
