import { useCallback, useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useUpload, useBatchPoll } from './useUpload';
import type { BatchPhoto } from '../types';

interface UploadFlowResult {
  upload: (files: File[], meta?: { album_id?: string; tags?: string[] }) => void;
  isUploading: boolean;
  batchStatus: BatchPhoto[];
  progress: { total: number; pending: number; failed: number; finished: boolean } | null;
  files: File[];
  reset: () => void;
}

export function useUploadFlow(onComplete?: () => void): UploadFlowResult {
  const [batchId, setBatchId] = useState<string | null>(null);
  const [files, setFiles] = useState<File[]>([]);
  const queryClient = useQueryClient();
  const onCompleteRef = useRef(onComplete);
  const mountedRef = useRef(true);

  useEffect(() => {
    onCompleteRef.current = onComplete;
  });

  useEffect(() => {
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const uploadMutation = useUpload();
  const batchQuery = useBatchPoll(batchId);

  const batchData = batchQuery.data?.data ?? null;
  const batchPhotos = batchData?.photos ?? [];
  const finished = batchData?.finished ?? false;

  useEffect(() => {
    if (finished && batchId) {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
      if (mountedRef.current) {
        setBatchId(null);
        setFiles([]);
      }
      onCompleteRef.current?.();
    }
  }, [finished, batchId, queryClient]);

  const upload = useCallback(
    (selectedFiles: File[], meta?: { album_id?: string; tags?: string[] }) => {
      setFiles(selectedFiles);
      const payload: { files: File[]; meta?: { album_id?: string; tags?: string[] } } = {
        files: selectedFiles,
      };
      if (meta) payload.meta = meta;
      uploadMutation.mutate(payload, {
        onSuccess: (response) => {
          setBatchId(response.data.batch_id);
        },
      });
    },
    [uploadMutation],
  );

  const reset = useCallback(() => {
    setBatchId(null);
    setFiles([]);
  }, []);

  return {
    upload,
    isUploading: uploadMutation.isPending,
    batchStatus: batchPhotos,
    progress: batchData
      ? {
          total: batchData.total,
          pending: batchData.pending,
          failed: batchData.failed,
          finished: batchData.finished,
        }
      : null,
    files,
    reset,
  };
}
