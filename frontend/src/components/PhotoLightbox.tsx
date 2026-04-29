import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import FocusLock from 'react-focus-lock';
import { X, Heart, Trash2, ChevronLeft, ChevronRight, Info } from 'lucide-react';
import { useKeyboardShortcuts, useToggleFavorite, useDeletePhoto } from '../hooks';
import type { ShortcutHandler } from '../hooks';
import { copy } from '../data/copy';
import { ExifPanel } from './ExifPanel';
import type { Photo } from '../types';

interface PhotoLightboxProps {
  photo: Photo;
  photos: Photo[];
  onClose: () => void;
  onNavigate: (photo: Photo) => void;
}

export function PhotoLightbox({ photo, photos, onClose, onNavigate }: PhotoLightboxProps) {
  const titleId = useId();
  const [showExif, setShowExif] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const previousFocusRef = useRef<HTMLElement | null>(null);

  const toggleFavorite = useToggleFavorite();
  const deletePhoto = useDeletePhoto();

  const currentIndex = useMemo(
    () => photos.findIndex((p) => p.id === photo.id),
    [photos, photo.id],
  );
  const prevPhoto = currentIndex > 0 ? photos[currentIndex - 1] : undefined;
  const nextPhoto = currentIndex < photos.length - 1 ? photos[currentIndex + 1] : undefined;

  // Save and restore focus
  useEffect(() => {
    previousFocusRef.current = document.activeElement as HTMLElement | null;
    return () => {
      if (previousFocusRef.current) {
        previousFocusRef.current.focus();
      }
    };
  }, []);

  // Lock body scroll
  useEffect(() => {
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = original;
    };
  }, []);

  const handlePrev = useCallback(() => {
    if (prevPhoto) onNavigate(prevPhoto);
  }, [prevPhoto, onNavigate]);

  const handleNext = useCallback(() => {
    if (nextPhoto) onNavigate(nextPhoto);
  }, [nextPhoto, onNavigate]);

  const handleToggleFavorite = useCallback(() => {
    toggleFavorite.mutate({ id: photo.id, isFavorite: photo.is_favorite });
  }, [toggleFavorite, photo.id, photo.is_favorite]);

  const handleDelete = useCallback(() => {
    setShowDeleteConfirm(true);
  }, []);

  const handleConfirmDelete = useCallback(() => {
    deletePhoto.mutate(photo.id, {
      onSuccess: () => {
        onClose();
      },
    });
  }, [deletePhoto, photo.id, onClose]);

  const shortcuts: ShortcutHandler[] = useMemo(
    () => [
      { key: 'ArrowLeft', action: handlePrev, enabled: !!prevPhoto },
      { key: 'ArrowRight', action: handleNext, enabled: !!nextPhoto },
      { key: 'Escape', action: onClose },
      { key: 'f', action: handleToggleFavorite },
      { key: 'Delete', action: handleDelete },
    ],
    [handlePrev, handleNext, onClose, handleToggleFavorite, handleDelete, prevPhoto, nextPhoto],
  );

  useKeyboardShortcuts(shortcuts);

  const handleBackdropClick = useCallback(
    (e: React.MouseEvent) => {
      if (e.target === e.currentTarget) {
        onClose();
      }
    },
    [onClose],
  );

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
      onClick={handleBackdropClick}
    >
      <FocusLock returnFocus={false}>
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className="relative flex h-full w-full flex-col"
        >
          {/* Prefetch next/prev images */}
          {prevPhoto?.urls.large && (
            <link rel="prefetch" href={prevPhoto.urls.large} as="image" />
          )}
          {nextPhoto?.urls.large && (
            <link rel="prefetch" href={nextPhoto.urls.large} as="image" />
          )}

          {/* Top bar */}
          <div className="flex shrink-0 items-center justify-between p-4">
            <h2 id={titleId} className="truncate text-lg font-medium text-white">
              {photo.title}
            </h2>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setShowExif((prev) => !prev)}
                className="rounded-full p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                aria-label={copy.lightbox.toggleExif}
              >
                <Info className="h-5 w-5" />
              </button>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                aria-label={copy.lightbox.close}
              >
                <X className="h-5 w-5" />
              </button>
            </div>
          </div>

          {/* Main content area */}
          <div className="relative flex min-h-0 flex-1 items-center justify-center">
            {/* Prev button */}
            {prevPhoto && (
              <button
                type="button"
                onClick={handlePrev}
                className="absolute left-4 z-10 rounded-full bg-black/50 p-2 text-white/70 transition-colors hover:bg-black/70 hover:text-white"
                aria-label={copy.lightbox.previous}
              >
                <ChevronLeft className="h-6 w-6" />
              </button>
            )}

            {/* Photo */}
            <img
              src={photo.urls.large ?? ''}
              alt={photo.title}
              fetchPriority="high"
              width={photo.width ?? undefined}
              height={photo.height ?? undefined}
              className="max-h-full max-w-full object-contain"
            />

            {/* Next button */}
            {nextPhoto && (
              <button
                type="button"
                onClick={handleNext}
                className="absolute right-4 z-10 rounded-full bg-black/50 p-2 text-white/70 transition-colors hover:bg-black/70 hover:text-white"
                aria-label={copy.lightbox.next}
              >
                <ChevronRight className="h-6 w-6" />
              </button>
            )}

            {/* EXIF panel (slides from right) */}
            <div
              className={`absolute right-0 top-0 h-full w-64 transform bg-gray-900/95 shadow-xl transition-transform duration-200 ${
                showExif ? 'translate-x-0' : 'translate-x-full'
              }`}
            >
              <ExifPanel exif={photo.exif} />
            </div>
          </div>

          {/* Bottom bar */}
          <div className="flex shrink-0 items-center justify-between p-4">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={handleToggleFavorite}
                className={`rounded-full p-2 transition-colors hover:bg-white/10 ${
                  photo.is_favorite ? 'text-red-500' : 'text-white/70 hover:text-white'
                }`}
                aria-label={
                  photo.is_favorite ? copy.favorites.remove : copy.favorites.add
                }
              >
                <Heart
                  className="h-5 w-5"
                  fill={photo.is_favorite ? 'currentColor' : 'none'}
                />
              </button>
              <button
                type="button"
                onClick={handleDelete}
                className="rounded-full p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-red-400"
                aria-label={copy.lightbox.delete}
              >
                <Trash2 className="h-5 w-5" />
              </button>
            </div>

            {photo.urls.original && (
              <a
                href={photo.urls.original}
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm text-blue-400 hover:text-blue-300"
              >
                {copy.lightbox.viewFullSize}
              </a>
            )}
          </div>

          {/* Delete confirmation dialog */}
          {showDeleteConfirm && (
            <div className="absolute inset-0 z-20 flex items-center justify-center bg-black/70">
              <div className="mx-4 max-w-sm rounded-lg bg-gray-800 p-6 text-white shadow-xl">
                <p className="mb-4 text-sm">{copy.lightbox.deleteConfirm}</p>
                <div className="flex justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => setShowDeleteConfirm(false)}
                    className="rounded-md px-3 py-1.5 text-sm text-gray-300 hover:text-white"
                  >
                    {copy.common.cancel}
                  </button>
                  <button
                    type="button"
                    onClick={handleConfirmDelete}
                    className="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700"
                  >
                    {copy.lightbox.delete}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </FocusLock>
    </div>,
    document.body,
  );
}
