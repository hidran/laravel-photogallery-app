import { useCallback } from 'react';
import { toast } from 'sonner';
import { Modal } from './Modal';
import { DropZone } from './DropZone';
import { UploadProgressList } from './UploadProgressList';
import { useUploadFlow } from '../hooks/useUploadFlow';
import { copy } from '../data/copy';

interface UploadModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function UploadModal({ isOpen, onClose }: UploadModalProps) {
  const { upload, isUploading, batchStatus, progress, files, reset } = useUploadFlow(() => {
    toast.success(copy.upload.complete);
    onClose();
  });

  const handleFilesSelected = useCallback(
    (selectedFiles: File[]) => {
      upload(selectedFiles);
    },
    [upload],
  );

  const handleError = useCallback((message: string) => {
    toast.error(message);
  }, []);

  const handleClose = useCallback(() => {
    if (!isUploading && !progress) {
      reset();
      onClose();
    }
  }, [isUploading, progress, reset, onClose]);

  const statusText = progress
    ? progress.finished
      ? copy.upload.complete
      : copy.upload.processing
    : isUploading
      ? copy.upload.uploading
      : null;

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={copy.upload.title}>
      <DropZone
        onFilesSelected={handleFilesSelected}
        onError={handleError}
        disabled={isUploading || (progress !== null && !progress.finished)}
      />
      {statusText && <p className="mt-3 text-center text-sm text-gray-500">{statusText}</p>}
      <UploadProgressList files={files} batchPhotos={batchStatus} />
    </Modal>
  );
}
