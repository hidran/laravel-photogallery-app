import { Loader2, AlertCircle } from 'lucide-react';
import type { ProcessingStatus } from '../types';
import { copy } from '../data/copy';

interface ProcessingOverlayProps {
  status: ProcessingStatus;
  error: string | null;
}

export function ProcessingOverlay({ status, error }: ProcessingOverlayProps) {
  if (status === 'completed') {
    return null;
  }

  if (status === 'failed') {
    return (
      <div className="absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white">
        <AlertCircle className="h-8 w-8 text-red-500" />
        <span className="mt-2 text-sm text-red-300">{error ?? copy.errors.generic}</span>
      </div>
    );
  }

  // pending or processing
  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white">
      <Loader2 className="h-8 w-8 animate-spin" />
      <span className="mt-2 text-sm">{copy.upload.processing}</span>
    </div>
  );
}
