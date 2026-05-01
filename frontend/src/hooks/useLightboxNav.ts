import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import type { Photo } from '../types';

export function useLightboxNav(photos: Photo[]) {
  const [searchParams, setSearchParams] = useSearchParams();
  const photoIdParam = searchParams.get('photo');

  const lightboxPhoto = useMemo(() => {
    if (!photoIdParam || photos.length === 0) return null;
    return photos.find((p) => p.id === photoIdParam) ?? null;
  }, [photoIdParam, photos]);

  const openLightbox = useCallback(
    (photo: Photo) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev);
          next.set('photo', photo.id);
          return next;
        },
        { replace: true },
      );
    },
    [setSearchParams],
  );

  const closeLightbox = useCallback(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.delete('photo');
        return next;
      },
      { replace: true },
    );
  }, [setSearchParams]);

  return useMemo(
    () => ({
      lightboxPhoto,
      openLightbox,
      navigateLightbox: openLightbox,
      closeLightbox,
    }),
    [lightboxPhoto, openLightbox, closeLightbox],
  );
}
