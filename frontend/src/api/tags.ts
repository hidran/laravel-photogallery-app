import type { SingleResponse, Tag } from '../types';
import { apiClient } from './client';

export interface TagsIndexParams {
  sort?: 'count_desc' | 'name_asc';
}

export async function getTags(params: TagsIndexParams = {}): Promise<SingleResponse<Tag[]>> {
  const { data } = await apiClient.get<SingleResponse<Tag[]>>('/tags', { params });
  return data;
}
