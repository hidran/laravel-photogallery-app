import { memo, useCallback, useState } from 'react';
import { useUpdatePhoto, useAlbums, useTags } from '../hooks';
import { copy } from '../data/copy';
import type { Photo } from '../types';

interface LightboxEditFormProps {
  photo: Photo;
  onClose: () => void;
}

export const LightboxEditForm = memo(function LightboxEditForm({
  photo,
  onClose,
}: LightboxEditFormProps) {
  const updatePhoto = useUpdatePhoto();
  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();

  const albums = albumsData?.data ?? [];
  const allTags = tagsData?.data ?? [];

  const [editTitle, setEditTitle] = useState(photo.title);
  const [editDescription, setEditDescription] = useState(photo.description ?? '');
  const [editAlbumId, setEditAlbumId] = useState(photo.album?.id ?? '');
  const [editTagSlugs, setEditTagSlugs] = useState<string[]>(photo.tags.map((t) => t.slug));
  const [newTagNames, setNewTagNames] = useState<string[]>([]);
  const [newTagInput, setNewTagInput] = useState('');

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

  const handleToggleTag = useCallback((slug: string) => {
    setEditTagSlugs((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug],
    );
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
          onClose();
          setNewTagNames([]);
          setNewTagInput('');
        },
      },
    );
  }, [updatePhoto, photo.id, editTitle, editDescription, editAlbumId, editTagSlugs, newTagNames, onClose]);

  return (
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
            onClick={onClose}
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
            {copy.common.save}
          </button>
        </div>
      </div>
    </div>
  );
});
