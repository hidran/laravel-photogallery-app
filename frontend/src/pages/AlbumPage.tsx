import { useCallback, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Pencil, Check, X } from 'lucide-react';
import { toast } from 'sonner';
import { usePhotos, useAlbum, useUpdateAlbum, useDeletePhoto } from '../hooks';
import { useLightboxNav } from '../hooks/useLightboxNav';
import { useMe } from '../hooks/useAuth';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { copy } from '../data/copy';
import type { Photo } from '../types';

export function AlbumPage() {
  const { albumId } = useParams<{ albumId: string }>();
  const { data: meData } = useMe();
  const updateAlbum = useUpdateAlbum();
  const deletePhoto = useDeletePhoto();

  const [editingDesc, setEditingDesc] = useState(false);
  const [descValue, setDescValue] = useState('');
  const [photoToDelete, setPhotoToDelete] = useState<Photo | null>(null);

  const { data: albumData } = useAlbum(albumId ?? '');
  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos({ album_id: albumId });

  const allPhotos = useMemo(() => data?.pages.flatMap((page) => page.data) ?? [], [data]);
  const { lightboxPhoto, openLightbox, navigateLightbox, closeLightbox } =
    useLightboxNav(allPhotos);

  const album = albumData?.data;
  const currentUserId = meData?.data?.id;
  const isOwner = album && currentUserId === album.owner?.id;

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

  const handleStartEditDesc = useCallback(() => {
    setDescValue(album?.description ?? '');
    setEditingDesc(true);
  }, [album?.description]);

  const handleSaveDesc = useCallback(() => {
    if (!albumId) return;
    updateAlbum.mutate(
      { id: albumId, payload: { description: descValue || null } },
      { onSuccess: () => setEditingDesc(false) },
    );
  }, [updateAlbum, albumId, descValue]);

  const handleLoadMore = useCallback(() => {
    if (hasNextPage) void fetchNextPage();
  }, [fetchNextPage, hasNextPage]);

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-brand-600" />
      </div>
    );
  }

  return (
    <>
      {album && (
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">{album.name}</h1>
          {editingDesc ? (
            <div className="mt-2 flex items-start gap-2">
              <textarea
                value={descValue}
                onChange={(e) => setDescValue(e.target.value)}
                placeholder={copy.albums.descriptionPlaceholder}
                rows={2}
                className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                autoFocus
              />
              <button
                type="button"
                onClick={handleSaveDesc}
                className="rounded-lg p-2 text-green-600 hover:bg-green-50"
              >
                <Check className="h-4 w-4" />
              </button>
              <button
                type="button"
                onClick={() => setEditingDesc(false)}
                className="rounded-lg p-2 text-gray-400 hover:bg-gray-100"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          ) : (
            <div className="group mt-1 flex items-center gap-2">
              <p className="text-sm text-gray-500">
                {album.description || (isOwner ? copy.albums.descriptionPlaceholder : '')}
              </p>
              {isOwner && (
                <button
                  type="button"
                  onClick={handleStartEditDesc}
                  className="hidden rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 group-hover:inline-flex"
                  aria-label="Edit description"
                >
                  <Pencil className="h-3 w-3" />
                </button>
              )}
            </div>
          )}
          <p className="mt-1 text-xs text-gray-400">
            {album.photos_count} photo{album.photos_count !== 1 ? 's' : ''}
          </p>
        </div>
      )}

      {allPhotos.length === 0 ? (
        <div className="flex flex-col items-center gap-3 p-12 text-center">
          <p className="text-gray-500">{copy.gallery.emptyAlbum}</p>
        </div>
      ) : (
        <MasonryGrid
          photos={allPhotos}
          onLoadMore={handleLoadMore}
          hasMore={!!hasNextPage}
          onClick={openLightbox}
          onDelete={handleCardDelete}
          {...(currentUserId ? { currentUserId } : {})}
        />
      )}

      {lightboxPhoto && (
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

export default AlbumPage;
