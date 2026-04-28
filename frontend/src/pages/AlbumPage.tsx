import { useCallback, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { usePhotos, useAlbum } from '../hooks';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { copy } from '../data/copy';
import type { Photo } from '../types';

export function AlbumPage() {
  const { albumId } = useParams<{ albumId: string }>();
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);

  const { data: albumData } = useAlbum(albumId ?? '');
  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos({
    album_id: albumId,
  });

  const allPhotos = useMemo(
    () => data?.pages.flatMap((page) => page.data) ?? [],
    [data],
  );

  const handlePhotoClick = useCallback((photo: Photo) => {
    setLightboxPhoto(photo);
  }, []);

  const handleLightboxNavigate = useCallback((photo: Photo) => {
    setLightboxPhoto(photo);
  }, []);

  const handleLightboxClose = useCallback(() => {
    setLightboxPhoto(null);
  }, []);

  const handleLoadMore = useCallback(() => {
    if (hasNextPage) {
      void fetchNextPage();
    }
  }, [fetchNextPage, hasNextPage]);

  if (isLoading) {
    return <p className="p-6 text-center text-gray-500">{copy.gallery.loading}</p>;
  }

  return (
    <>
      {albumData && (
        <h1 className="mb-6 text-2xl font-bold text-gray-900">{albumData.data.name}</h1>
      )}

      {allPhotos.length === 0 ? (
        <p className="p-6 text-center text-gray-500">{copy.gallery.emptyAlbum}</p>
      ) : (
        <MasonryGrid
          photos={allPhotos}
          onLoadMore={handleLoadMore}
          hasMore={!!hasNextPage}
          onClick={handlePhotoClick}
        />
      )}

      {lightboxPhoto && (
        <PhotoLightbox
          photo={lightboxPhoto}
          photos={allPhotos}
          onClose={handleLightboxClose}
          onNavigate={handleLightboxNavigate}
        />
      )}
    </>
  );
}

export default AlbumPage;
