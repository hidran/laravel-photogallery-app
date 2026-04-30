import { useCallback, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import type { Photo } from '../types';

export function useLightboxNav(photos: Photo[]) {
  const [searchParams, setSearchParams] = useSearchParams();
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);

  const photoIdParam = searchParams.get('photo');

  // Open lightbox from URL — sync state during render instead of effect
  const [syncedParam, setSyncedParam] = useState<string | null>(null);
  if (photoIdParam !== syncedParam) {
    setSyncedParam(photoIdParam);
    if (photoIdParam && photos.length > 0) {
      const found = photos.find((p) => p.id === photoIdParam);
      if (found) {
        setLightboxPhoto(found);
      }
    } else if (!photoIdParam) {
      setLightboxPhoto(null);
    }
  }

  // Also resolve when photos load after the param is already set
  if (photoIdParam && !lightboxPhoto && photos.length > 0 && syncedParam === photoIdParam) {
    const found = photos.find((p) => p.id === photoIdParam);
    if (found) {
      setLightboxPhoto(found);
    }
  }

  const openLightbox = useCallback(
    (photo: Photo) => {
      setLightboxPhoto(photo);
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

  const navigateLightbox = useCallback(
    (photo: Photo) => {
      setLightboxPhoto(photo);
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
    setLightboxPhoto(null);
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
      navigateLightbox,
      closeLightbox,
    }),
    [lightboxPhoto, openLightbox, navigateLightbox, closeLightbox],
  );
}
