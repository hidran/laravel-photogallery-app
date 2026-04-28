import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { usePhotos } from '../hooks';
import { MasonryGrid } from '../components/MasonryGrid';
import { PhotoLightbox } from '../components/PhotoLightbox';
import { UploadModal } from '../components/UploadModal';
import { copy } from '../data/copy';
import type { Photo } from '../types';
import type { PhotosIndexParams } from '../api/photos';

export function GalleryPage() {
  const [searchParams] = useSearchParams();
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);
  const [uploadOpen, setUploadOpen] = useState(false);

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

  const allPhotos = useMemo(
    () => data?.pages.flatMap((page) => page.data) ?? [],
    [data],
  );

  // Listen to custom event from Navbar to open upload modal
  useEffect(() => {
    const handler = () => setUploadOpen(true);
    window.addEventListener('open-upload-modal', handler);
    return () => window.removeEventListener('open-upload-modal', handler);
  }, []);

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
    return <p className="p-6 text-center text-gray-500">{copy.gallery.emptyState}</p>;
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

      <UploadModal isOpen={uploadOpen} onClose={() => setUploadOpen(false)} />
    </>
  );
}

export default GalleryPage;
