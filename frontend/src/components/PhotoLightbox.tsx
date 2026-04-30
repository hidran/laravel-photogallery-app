import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import FocusLock from 'react-focus-lock';
import { X, Heart, Trash2, ChevronLeft, ChevronRight, Info, Pencil } from 'lucide-react';
import {
  useKeyboardShortcuts,
  useToggleFavorite,
  useDeletePhoto,
  useUpdatePhoto,
  useAlbums,
  useTags,
} from '../hooks';
import type { ShortcutHandler } from '../hooks';
import { copy } from '../data/copy';
import { ExifPanel } from './ExifPanel';
import { useMe } from '../hooks/useAuth';
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
  const [editMode, setEditMode] = useState(false);
  const previousFocusRef = useRef<HTMLElement | null>(null);

  const toggleFavorite = useToggleFavorite();
  const deletePhoto = useDeletePhoto();
  const updatePhoto = useUpdatePhoto();
  const { data: meData } = useMe();
  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();

  const isOwner = meData?.data?.id === photo.owner.id;
  const albums = albumsData?.data ?? [];
  const allTags = tagsData?.data ?? [];

  // Edit form state
  const [editTitle, setEditTitle] = useState(photo.title);
  const [editDescription, setEditDescription] = useState(photo.description ?? '');
  const [editAlbumId, setEditAlbumId] = useState(photo.album?.id ?? '');
  const [editTagSlugs, setEditTagSlugs] = useState<string[]>(photo.tags.map((t) => t.slug));
  const [newTagNames, setNewTagNames] = useState<string[]>([]);
  const [newTagInput, setNewTagInput] = useState('');

  // Reset edit form when photo changes — use key-based reset via tracking photo.id
  const [trackedPhotoId, setTrackedPhotoId] = useState(photo.id);
  if (trackedPhotoId !== photo.id) {
    setTrackedPhotoId(photo.id);
    setEditTitle(photo.title);
    setEditDescription(photo.description ?? '');
    setEditAlbumId(photo.album?.id ?? '');
    setEditTagSlugs(photo.tags.map((t) => t.slug));
    setNewTagNames([]);
    setNewTagInput('');
    setEditMode(false);
  }

  const currentIndex = useMemo(
    () => photos.findIndex((p) => p.id === photo.id),
    [photos, photo.id],
  );
  const prevPhoto = currentIndex > 0 ? photos[currentIndex - 1] : undefined;
  const nextPhoto = currentIndex < photos.length - 1 ? photos[currentIndex + 1] : undefined;

  useEffect(() => {
    previousFocusRef.current = document.activeElement as HTMLElement | null;
    return () => {
      if (previousFocusRef.current) {
        previousFocusRef.current.focus();
      }
    };
  }, []);

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

  const handleAddNewTag = useCallback(() => {
    const name = newTagInput.trim();
    if (name && !newTagNames.includes(name)) {
      setNewTagNames((prev) => [...prev, name]);
      setNewTagInput('');
    }
  }, [newTagInput, newTagNames]);

  const handleRemoveNewTag = useCallback((name: string) => {
    setNewTagNames((prev) => prev.filter((n) => n !== name));
  }, []);

  const handleSaveEdit = useCallback(() => {
    const payload: {
      title: string;
      description: string | null;
      album_id: string | null;
      tags: string[];
      new_tags?: string[];
    } = {
      title: editTitle,
      description: editDescription || null,
      album_id: editAlbumId || null,
      tags: editTagSlugs,
    };
    if (newTagNames.length > 0) payload.new_tags = newTagNames;

    updatePhoto.mutate(
      { id: photo.id, payload },
      {
        onSuccess: () => {
          setEditMode(false);
          setNewTagNames([]);
          setNewTagInput('');
        },
      },
    );
  }, [updatePhoto, photo.id, editTitle, editDescription, editAlbumId, editTagSlugs, newTagNames]);

  const handleToggleTag = useCallback((slug: string) => {
    setEditTagSlugs((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug],
    );
  }, []);

  const shortcuts: ShortcutHandler[] = useMemo(
    () => [
      { key: 'ArrowLeft', action: handlePrev, enabled: !!prevPhoto && !editMode },
      { key: 'ArrowRight', action: handleNext, enabled: !!nextPhoto && !editMode },
      { key: 'Escape', action: editMode ? () => setEditMode(false) : onClose },
      { key: 'f', action: handleToggleFavorite, enabled: !editMode },
      { key: 'Delete', action: handleDelete, enabled: !editMode },
    ],
    [
      handlePrev,
      handleNext,
      onClose,
      handleToggleFavorite,
      handleDelete,
      prevPhoto,
      nextPhoto,
      editMode,
    ],
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
          {prevPhoto?.urls.large && <link rel="prefetch" href={prevPhoto.urls.large} as="image" />}
          {nextPhoto?.urls.large && <link rel="prefetch" href={nextPhoto.urls.large} as="image" />}

          {/* Top bar */}
          <div className="flex shrink-0 items-center justify-between p-4">
            <h2 id={titleId} className="truncate text-lg font-medium text-white">
              {photo.title}
            </h2>
            <div className="flex items-center gap-2">
              {isOwner && (
                <button
                  type="button"
                  onClick={() => setEditMode((prev) => !prev)}
                  className={`rounded-full p-2 transition-colors hover:bg-white/10 ${
                    editMode ? 'text-blue-400' : 'text-white/70 hover:text-white'
                  }`}
                  aria-label={copy.lightbox.edit}
                >
                  <Pencil className="h-5 w-5" />
                </button>
              )}
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

            <img
              src={photo.urls.large ?? ''}
              alt={photo.title}
              fetchPriority="high"
              width={photo.width ?? undefined}
              height={photo.height ?? undefined}
              className="max-h-full max-w-full object-contain"
            />

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

            {/* EXIF panel */}
            <div
              className={`absolute right-0 top-0 h-full w-64 transform bg-gray-900/95 shadow-xl transition-transform duration-200 ${
                showExif ? 'translate-x-0' : 'translate-x-full'
              }`}
            >
              <ExifPanel exif={photo.exif} />
            </div>

            {/* Edit panel */}
            {editMode && isOwner && (
              <div className="absolute bottom-0 left-0 right-0 bg-gray-900/95 p-4 shadow-xl">
                <div className="mx-auto flex max-w-2xl flex-col gap-3">
                  <input
                    type="text"
                    value={editTitle}
                    onChange={(e) => setEditTitle(e.target.value)}
                    placeholder="Title"
                    className="rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white placeholder:text-gray-400 focus:border-blue-500 focus:outline-none"
                  />
                  <textarea
                    value={editDescription}
                    onChange={(e) => setEditDescription(e.target.value)}
                    placeholder="Description"
                    rows={2}
                    className="rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white placeholder:text-gray-400 focus:border-blue-500 focus:outline-none"
                  />
                  <select
                    value={editAlbumId}
                    onChange={(e) => setEditAlbumId(e.target.value)}
                    className="rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">No album</option>
                    {albums.map((album) => (
                      <option key={album.id} value={album.id}>
                        {album.name}
                      </option>
                    ))}
                  </select>
                  <div className="flex flex-wrap gap-1.5">
                    {allTags.map((tag) => (
                      <button
                        key={tag.id}
                        type="button"
                        onClick={() => handleToggleTag(tag.slug)}
                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                          editTagSlugs.includes(tag.slug)
                            ? 'bg-brand-600 text-white'
                            : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                        }`}
                      >
                        {tag.name}
                      </button>
                    ))}
                    {newTagNames.map((name) => (
                      <button
                        key={`new-${name}`}
                        type="button"
                        onClick={() => handleRemoveNewTag(name)}
                        className="rounded-full bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700"
                      >
                        {name} &times;
                      </button>
                    ))}
                    <input
                      type="text"
                      value={newTagInput}
                      onChange={(e) => setNewTagInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          handleAddNewTag();
                        }
                      }}
                      placeholder={copy.lightbox.newTagPlaceholder}
                      className="w-24 rounded-full border border-gray-600 bg-gray-800 px-2.5 py-1 text-xs text-white placeholder:text-gray-500 focus:border-brand-500 focus:outline-none"
                    />
                  </div>
                  <div className="flex justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => setEditMode(false)}
                      className="rounded-md px-3 py-1.5 text-sm text-gray-300 hover:text-white"
                    >
                      {copy.common.cancel}
                    </button>
                    <button
                      type="button"
                      onClick={handleSaveEdit}
                      disabled={updatePhoto.isPending}
                      className="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                      Save
                    </button>
                  </div>
                </div>
              </div>
            )}
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
                aria-label={photo.is_favorite ? copy.favorites.remove : copy.favorites.add}
              >
                <Heart className="h-5 w-5" fill={photo.is_favorite ? 'currentColor' : 'none'} />
              </button>
              {isOwner && (
                <button
                  type="button"
                  onClick={handleDelete}
                  className="rounded-full p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-red-400"
                  aria-label={copy.lightbox.delete}
                >
                  <Trash2 className="h-5 w-5" />
                </button>
              )}
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
