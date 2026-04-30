import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { uploadPhotos } from '../api/photos';
import { getBatchProgress } from '../api/batch';
import { polling } from '../data/polling';

export function useUpload() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      files,
      meta,
    }: {
      files: File[];
      meta?: { album_id?: string; tags?: string[] };
    }) => uploadPhotos(files, meta),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photos'] });
    },
  });
}

export function useBatchPoll(batchId: string | null) {
  return useQuery({
    queryKey: ['batch', batchId],
    queryFn: () => getBatchProgress(batchId!),
    enabled: batchId !== null && batchId.length > 0,
    refetchInterval: (query) => {
      if (query.state.data?.data.finished) {
        return false;
      }
      return polling.BATCH_MS;
    },
  });
}
