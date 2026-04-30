import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { Modal } from './Modal';
import { DropZone } from './DropZone';
import { UploadProgressList } from './UploadProgressList';
import { useUploadFlow } from '../hooks/useUploadFlow';
import { useAlbums, useTags } from '../hooks';
import { copy } from '../data/copy';

interface UploadModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function UploadModal({ isOpen, onClose }: UploadModalProps) {
  const [selectedAlbumId, setSelectedAlbumId] = useState('');
  const [selectedTagSlugs, setSelectedTagSlugs] = useState<string[]>([]);

  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();
  const albums = albumsData?.data ?? [];
  const allTags = tagsData?.data ?? [];

  const { upload, isUploading, batchStatus, progress, files, reset } = useUploadFlow(() => {
    toast.success(copy.upload.complete);
    setSelectedAlbumId('');
    setSelectedTagSlugs([]);
    onClose();
  });

  const handleFilesSelected = useCallback(
    (selectedFiles: File[]) => {
      const meta: { album_id?: string; tags?: string[] } = {};
      if (selectedAlbumId) meta.album_id = selectedAlbumId;
      if (selectedTagSlugs.length > 0) meta.tags = selectedTagSlugs;
      upload(selectedFiles, Object.keys(meta).length > 0 ? meta : undefined);
    },
    [upload, selectedAlbumId, selectedTagSlugs],
  );

  const handleError = useCallback((message: string) => {
    toast.error(message);
  }, []);

  const handleClose = useCallback(() => {
    if (!isUploading && !progress) {
      reset();
      setSelectedAlbumId('');
      setSelectedTagSlugs([]);
      onClose();
    }
  }, [isUploading, progress, reset, onClose]);

  const handleToggleTag = useCallback((slug: string) => {
    setSelectedTagSlugs((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug],
    );
  }, []);

  const statusText = progress
    ? progress.finished
      ? copy.upload.complete
      : copy.upload.processing
    : isUploading
      ? copy.upload.uploading
      : null;

  const isDisabled = isUploading || (progress !== null && !progress.finished);

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={copy.upload.title}>
      {/* Metadata fields — shown before upload starts */}
      {!isDisabled && (
        <div className="mb-4 flex flex-col gap-3">
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">
              {copy.albums.nameLabel}
            </label>
            <select
              value={selectedAlbumId}
              onChange={(e) => setSelectedAlbumId(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">No album</option>
              {albums.map((album) => (
                <option key={album.id} value={album.id}>
                  {album.name}
                </option>
              ))}
            </select>
          </div>

          {allTags.length > 0 && (
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">
                {copy.tags.title}
              </label>
              <div className="flex flex-wrap gap-1.5">
                {allTags.map((tag) => (
                  <button
                    key={tag.id}
                    type="button"
                    onClick={() => handleToggleTag(tag.slug)}
                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                      selectedTagSlugs.includes(tag.slug)
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                    }`}
                  >
                    {tag.name}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      <DropZone onFilesSelected={handleFilesSelected} onError={handleError} disabled={isDisabled} />
      {statusText && <p className="mt-3 text-center text-sm text-gray-500">{statusText}</p>}
      <UploadProgressList files={files} batchPhotos={batchStatus} />
    </Modal>
  );
}
