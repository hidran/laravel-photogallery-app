import { memo, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { useUpdatePhoto, useAlbums, useTags } from '../hooks';
import { copy } from '../data/copy';
import type { Photo } from '../types';

interface LightboxEditFormProps {
  photo: Photo;
  onClose: () => void;
}

interface EditFormData {
  title: string;
  description: string;
  albumId: string;
  tagSlugs: string[];
  newTagInput: string;
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

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { isSubmitting },
  } = useForm<EditFormData>({
    defaultValues: {
      title: photo.title,
      description: photo.description ?? '',
      albumId: photo.album?.id ?? '',
      tagSlugs: photo.tags.map((t) => t.slug),
      newTagInput: '',
    },
  });

  const tagSlugs = watch('tagSlugs');
  const newTagInput = watch('newTagInput');

  const handleToggleTag = useCallback(
    (slug: string) => {
      setValue(
        'tagSlugs',
        tagSlugs.includes(slug) ? tagSlugs.filter((s) => s !== slug) : [...tagSlugs, slug],
      );
    },
    [tagSlugs, setValue],
  );

  const handleAddNewTag = useCallback(() => {
    const name = newTagInput.trim();
    if (name && !tagSlugs.includes(name)) {
      setValue('tagSlugs', [...tagSlugs, name]);
      setValue('newTagInput', '');
    }
  }, [newTagInput, tagSlugs, setValue]);

  const handleRemoveTag = useCallback(
    (slug: string) => {
      setValue(
        'tagSlugs',
        tagSlugs.filter((s) => s !== slug),
      );
    },
    [tagSlugs, setValue],
  );

  const onSubmit = (data: EditFormData) => {
    const payload: {
      title: string;
      description: string | null;
      album_id: string | null;
      tags: string[];
      new_tags?: string[];
    } = {
      title: data.title,
      description: data.description || null,
      album_id: data.albumId || null,
      tags: data.tagSlugs.filter((s) => allTags.some((t) => t.slug === s)),
    };

    const newTags = data.tagSlugs.filter((s) => !allTags.some((t) => t.slug === s));
    if (newTags.length > 0) {
      payload.new_tags = newTags;
    }

    updatePhoto.mutate(
      { id: photo.id, payload },
      {
        onSuccess: () => {
          onClose();
        },
      },
    );
  };

  return (
    <form
      onSubmit={handleSubmit(onSubmit)}
      className="absolute bottom-0 left-0 right-0 bg-gray-900/95 p-4 shadow-xl"
    >
      <div className="mx-auto flex max-w-2xl flex-col gap-3">
        <input
          type="text"
          {...register('title')}
          placeholder="Title"
          className="rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white placeholder:text-gray-400 focus:border-blue-500 focus:outline-none"
        />
        <textarea
          {...register('description')}
          placeholder="Description"
          rows={2}
          className="rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white placeholder:text-gray-400 focus:border-blue-500 focus:outline-none"
        />
        <select
          {...register('albumId')}
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
                tagSlugs.includes(tag.slug)
                  ? 'bg-brand-600 text-white'
                  : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
              }`}
            >
              {tag.name}
            </button>
          ))}
          {tagSlugs
            .filter((s) => !allTags.some((t) => t.slug === s))
            .map((name) => (
              <button
                key={`new-${name}`}
                type="button"
                onClick={() => handleRemoveTag(name)}
                className="rounded-full bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700"
              >
                {name} &times;
              </button>
            ))}
          <input
            type="text"
            value={newTagInput}
            onChange={(e) => setValue('newTagInput', e.target.value)}
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
            type="submit"
            disabled={isSubmitting || updatePhoto.isPending}
            className="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {copy.common.save}
          </button>
        </div>
      </div>
    </form>
  );
});
