import { useCallback, useMemo, useState } from 'react';
import { Heart, X, Check } from 'lucide-react';
import { usePhotos, useRemoveFavoritesBatch, useRemoveAllFavorites } from '../hooks';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { copy } from '../data/copy';
import type { Photo } from '../types';

export function FavoritesPage() {
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos({ favorites: true });
  const removeBatch = useRemoveFavoritesBatch();
  const removeAll = useRemoveAllFavorites();

  const allPhotos = useMemo(
    () => data?.pages.flatMap((page) => page.data) ?? [],
    [data],
  );

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
        setLightboxPhoto(photo);
      }
    },
    [selectMode, toggleSelect],
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
          renderOverlay={
            selectMode
              ? (photo) => (
                  <div
                    className={`absolute inset-0 flex items-start justify-end p-2 ${
                      selectedIds.has(photo.id)
                        ? 'bg-blue-500/20 ring-2 ring-inset ring-blue-500'
                        : ''
                    }`}
                  >
                    <div
                      className={`flex h-6 w-6 items-center justify-center rounded-full border-2 ${
                        selectedIds.has(photo.id)
                          ? 'border-blue-600 bg-blue-600 text-white'
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
          onClose={() => setLightboxPhoto(null)}
          onNavigate={(photo) => setLightboxPhoto(photo)}
        />
      )}
    </>
  );
}

export default FavoritesPage;
