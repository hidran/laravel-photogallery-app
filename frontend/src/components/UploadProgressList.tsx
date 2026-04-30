import { Check, X, Loader2 } from 'lucide-react';
import type { BatchPhoto } from '../types';

interface UploadProgressListProps {
  files: File[];
  batchPhotos: BatchPhoto[];
}

function StatusIcon({ status }: { status: BatchPhoto['processing_status'] }) {
  switch (status) {
    case 'completed':
      return <Check className="h-4 w-4 text-green-500" />;
    case 'failed':
      return <X className="h-4 w-4 text-red-500" />;
    case 'pending':
    case 'processing':
      return <Loader2 className="h-4 w-4 animate-spin text-blue-500" />;
  }
}

export function UploadProgressList({ files, batchPhotos }: UploadProgressListProps) {
  if (files.length === 0 && batchPhotos.length === 0) {
    return null;
  }

  // If we have batch data, show that; otherwise show uploading state for files
  if (batchPhotos.length > 0) {
    return (
      <ul className="mt-4 max-h-60 space-y-2 overflow-y-auto">
        {batchPhotos.map((photo) => (
          <li
            key={photo.id}
            className="flex items-center justify-between rounded bg-gray-50 px-3 py-2"
          >
            <span className="truncate text-sm text-gray-700">{photo.title}</span>
            <StatusIcon status={photo.processing_status} />
          </li>
        ))}
      </ul>
    );
  }

  return (
    <ul className="mt-4 max-h-60 space-y-2 overflow-y-auto">
      {files.map((file, index) => (
        <li
          key={`${file.name}-${index}`}
          className="flex items-center justify-between rounded bg-gray-50 px-3 py-2"
        >
          <span className="truncate text-sm text-gray-700">{file.name}</span>
          <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
        </li>
      ))}
    </ul>
  );
}
