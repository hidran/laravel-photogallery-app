import type { BatchResponse } from '../types';
import { apiClient } from './client';

export async function getBatchProgress(batchId: string): Promise<BatchResponse> {
  const { data } = await apiClient.get<BatchResponse>(`/photos/batch/${batchId}`);
  return data;
}
