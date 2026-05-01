import { useCallback, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Trash2, X, Check, MousePointer2, ImageOff } from 'lucide-react';
import { toast } from 'sonner';
import { usePhotos, useDeletePhoto } from '../hooks';
import { useMe } from '../hooks/useAuth';
import { useLightboxNav } from '../hooks/useLightboxNav';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { ConfirmDialog } from '../components/ConfirmDialog';
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
  const { data: meData } = useMe();
  const currentUserId = meData?.data?.id;

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

  // Single photo delete from card
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

  // Bulk delete — sequential to avoid N concurrent mutations
  const handleDeleteSelected = useCallback(async () => {
    const ids = Array.from(selectedIds);
    try {
      for (const id of ids) {
        await deletePhoto.mutateAsync(id);
      }
      setSelectedIds(new Set());
      setSelectMode(false);
      setShowBulkDeleteConfirm(false);
      toast.success(copy.gallery.photosDeleted(ids.length));
    } catch {
      toast.error(copy.errors.generic);
    }
  }, [selectedIds, deletePhoto]);

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
    return (
      <div className="flex flex-col items-center gap-4 py-24 text-center">
        <div className="rounded-2xl bg-gray-100 p-6">
          <ImageOff className="h-10 w-10 text-gray-400" />
        </div>
        <div>
          <p className="text-lg font-semibold text-gray-800">
            {hasFilters ? 'No results' : 'Your gallery is empty'}
          </p>
          <p className="mt-1 text-sm text-gray-500">
            {hasFilters ? 'Try different filters or search terms' : copy.gallery.emptyState}
          </p>
        </div>
        {!hasFilters && (
          <button
            type="button"
            onClick={() => window.dispatchEvent(new CustomEvent('open-upload-modal'))}
            className="mt-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700"
          >
            {copy.upload.title}
          </button>
        )}
      </div>
    );
  }

  return (
    <>
      {/* Select toolbar */}
      {selectMode && (
        <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-100 px-4 py-2.5">
          <span className="text-sm font-medium text-gray-700">
            {copy.gallery.selectedCount(selectedIds.size)}
          </span>
          <div className="ml-auto flex items-center gap-2">
            <button
              type="button"
              onClick={() => setShowBulkDeleteConfirm(true)}
              disabled={selectedIds.size === 0}
              className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-40"
            >
              <Trash2 className="h-3.5 w-3.5" />
              {copy.gallery.deleteSelected}
            </button>
            <button
              type="button"
              onClick={handleCancelSelect}
              className="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50"
            >
              <X className="h-3.5 w-3.5" />
              {copy.gallery.cancelSelect}
            </button>
          </div>
        </div>
      )}

      {!selectMode && (
        <div className="mb-4 flex items-center">
          <button
            type="button"
            onClick={() => setSelectMode(true)}
            className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700"
          >
            <MousePointer2 className="h-3.5 w-3.5" />
            {copy.gallery.selectMode}
          </button>
        </div>
      )}

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

      {/* Bulk delete confirm */}
      <ConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleDeleteSelected}
        title={copy.gallery.deleteSelected}
        message={copy.gallery.deleteSelectedConfirm(selectedIds.size)}
        confirmLabel={copy.common.delete}
        variant="danger"
        isPending={deletePhoto.isPending}
      />

      {/* Single photo delete confirm */}
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
