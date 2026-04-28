import type { PaginatedResponse, SingleResponse, Album } from '../types';
import { apiClient } from './client';

export interface CreateAlbumPayload {
  name: string;
  description?: string;
}

export interface UpdateAlbumPayload {
  name?: string;
  description?: string | null;
  cover_photo_id?: string | null;
}

export async function getAlbums(params: { cursor?: string } = {}): Promise<PaginatedResponse<Album>> {
  const { data } = await apiClient.get<PaginatedResponse<Album>>('/albums', { params });
  return data;
}

export async function getAlbum(id: string): Promise<SingleResponse<Album>> {
  const { data } = await apiClient.get<SingleResponse<Album>>(`/albums/${id}`);
  return data;
}

export async function createAlbum(payload: CreateAlbumPayload): Promise<SingleResponse<Album>> {
  const { data } = await apiClient.post<SingleResponse<Album>>('/albums', payload);
  return data;
}

export async function updateAlbum(
  id: string,
  payload: UpdateAlbumPayload,
): Promise<SingleResponse<Album>> {
  const { data } = await apiClient.patch<SingleResponse<Album>>(`/albums/${id}`, payload);
  return data;
}

export async function deleteAlbum(id: string): Promise<void> {
  await apiClient.delete(`/albums/${id}`);
}
