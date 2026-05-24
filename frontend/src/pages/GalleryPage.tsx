import { useCallback, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Check } from 'lucide-react';
import { toast } from 'sonner';
import { usePhotos, useDeletePhoto, useDeletePhotosBatch } from '../hooks';
import { useLightboxNav } from '../hooks/useLightboxNav';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { SelectionToolbar } from '../components/gallery/SelectionToolbar';
import { SelectModeButton } from '../components/gallery/SelectModeButton';
import { EmptyState } from '../components/gallery/EmptyState';
import { copy } from '../data/copy';
import type { Photo } from '../types';
import type { PhotosIndexParams } from '../api/photos';

export function GalleryPage() {
  const [searchParams] = useSearchParams();
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);
  const [photoToDelete, setPhotoToDelete] = useState<Photo | null>(null);
  const deletePhoto = useDeletePhoto();
  const deletePhotosBatch = useDeletePhotosBatch();

  const params: PhotosIndexParams = useMemo(() => {
    const result: PhotosIndexParams = {};
    const search = searchParams.get('search');
    const tags = searchParams.getAll('tags[]');
    const sort = searchParams.get('sort');
    const order = searchParams.get('order');
    if (search) result.search = search;
    if (tags.length > 0) result.tags = tags;
    if (sort) result.sort = sort;
    if (order === 'asc' || order === 'desc') result.order = order;
    return result;
  }, [searchParams]);

  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos(params);
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

  const handleCardDelete = useCallback((photo: Photo) => {
    setPhotoToDelete(photo);
  }, []);

  const handleConfirmSingleDelete = useCallback(() => {
    if (!photoToDelete) return;
    deletePhoto.mutate(photoToDelete.id, {
      onSuccess: () => {
        setPhotoToDelete(null);
        toast.success(copy.gallery.photoDeleted);
      },
    });
  }, [photoToDelete, deletePhoto]);

  const handleDeleteSelected = useCallback(async () => {
    const ids = Array.from(selectedIds);
    try {
      await deletePhotosBatch.mutateAsync(ids);
      setSelectedIds(new Set());
      setSelectMode(false);
      setShowBulkDeleteConfirm(false);
      toast.success(copy.gallery.photosDeleted(ids.length));
    } catch {
      toast.error(copy.errors.generic);
    }
  }, [selectedIds, deletePhotosBatch]);

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
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-brand-600" />
      </div>
    );
  }

  if (allPhotos.length === 0) {
    const hasFilters = searchParams.has('search') || searchParams.getAll('tags[]').length > 0;
    return <EmptyState hasFilters={hasFilters} />;
  }

  return (
    <>
      {selectMode && (
        <SelectionToolbar
          selectedCount={selectedIds.size}
          onDelete={() => setShowBulkDeleteConfirm(true)}
          onCancel={handleCancelSelect}
          isPending={deletePhoto.isPending || deletePhotosBatch.isPending}
        />
      )}

      {!selectMode && <SelectModeButton onClick={() => setSelectMode(true)} />}

      <MasonryGrid
        photos={allPhotos}
        onLoadMore={handleLoadMore}
        hasMore={!!hasNextPage}
        onClick={handlePhotoClick}
        onDelete={handleCardDelete}
        renderOverlay={
          selectMode
            ? (photo) => (
                <div
                  className={`flex h-full w-full items-start justify-end p-2.5 transition-all ${
                    selectedIds.has(photo.id) ? 'bg-brand-500/20' : ''
                  }`}
                >
                  <div
                    className={`flex h-6 w-6 items-center justify-center rounded-full border-2 shadow-sm transition-all ${
                      selectedIds.has(photo.id)
                        ? 'scale-110 border-brand-600 bg-brand-600 text-white'
                        : 'border-white/90 bg-white/80 text-transparent backdrop-blur-sm'
                    }`}
                  >
                    <Check className="h-3.5 w-3.5" />
                  </div>
                </div>
              )
            : undefined
        }
      />

      {lightboxPhoto && !selectMode && (
        <PhotoLightbox
          photo={lightboxPhoto}
          photos={allPhotos}
          onClose={closeLightbox}
          onNavigate={navigateLightbox}
        />
      )}

      <ConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleDeleteSelected}
        title={copy.gallery.deleteSelected}
        message={copy.gallery.deleteSelectedConfirm(selectedIds.size)}
        confirmLabel={copy.common.delete}
        variant="danger"
        isPending={deletePhoto.isPending || deletePhotosBatch.isPending}
      />

      <ConfirmDialog
        isOpen={photoToDelete !== null}
        onClose={() => setPhotoToDelete(null)}
        onConfirm={handleConfirmSingleDelete}
        title={copy.lightbox.delete}
        message={copy.lightbox.deleteConfirm}
        confirmLabel={copy.common.delete}
        variant="danger"
        isPending={deletePhoto.isPending}
      />
    </>
  );
}

export default GalleryPage;
