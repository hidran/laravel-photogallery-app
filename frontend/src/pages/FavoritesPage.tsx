import { useCallback, useMemo, useState } from 'react';
import { usePhotos } from '../hooks';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { copy } from '../data/copy';
import type { Photo } from '../types';

export function FavoritesPage() {
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);

  const { data, fetchNextPage, hasNextPage, isLoading } = usePhotos({ favorites: true });

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

  if (allPhotos.length === 0) {
    return <p className="p-6 text-center text-gray-500">{copy.gallery.emptyFavorites}</p>;
  }

  return (
    <>
      <MasonryGrid
        photos={allPhotos}
        onLoadMore={handleLoadMore}
        hasMore={!!hasNextPage}
        onClick={handlePhotoClick}
      />

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

export default FavoritesPage;
