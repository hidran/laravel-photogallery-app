import type { PaginatedResponse, SingleResponse, Photo } from '../types';
import { apiClient } from './client';

export interface PhotosIndexParams {
  cursor?: string;
  search?: string;
  tags?: string[];
  album_id?: string;
  is_favorite?: boolean;
  sort?: string;
  order?: 'asc' | 'desc';
}

export interface UpdatePhotoPayload {
  title?: string;
  description?: string | null;
  album_id?: string | null;
  tags?: string[];
}

export async function getPhotos(params: PhotosIndexParams = {}): Promise<PaginatedResponse<Photo>> {
  const { data } = await apiClient.get<PaginatedResponse<Photo>>('/photos', { params });
  return data;
}

export async function getPhoto(id: string): Promise<SingleResponse<Photo>> {
  const { data } = await apiClient.get<SingleResponse<Photo>>(`/photos/${id}`);
  return data;
}

export async function uploadPhotos(
  files: File[],
  meta?: { album_id?: string; tags?: string[] },
): Promise<{ data: { batch_id: string; total: number } }> {
  const formData = new FormData();
  files.forEach((file) => formData.append('files[]', file));
  if (meta?.album_id) {
    formData.append('album_id', meta.album_id);
  }
  meta?.tags?.forEach((tag) => formData.append('tags[]', tag));

  const { data } = await apiClient.post('/photos', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data as { data: { batch_id: string; total: number } };
}

export async function updatePhoto(
  id: string,
  payload: UpdatePhotoPayload,
): Promise<SingleResponse<Photo>> {
  const { data } = await apiClient.patch<SingleResponse<Photo>>(`/photos/${id}`, payload);
  return data;
}

export async function deletePhoto(id: string): Promise<void> {
  await apiClient.delete(`/photos/${id}`);
}

export async function addFavorite(id: string): Promise<void> {
  await apiClient.put(`/photos/${id}/favorite`);
}

export async function removeFavorite(id: string): Promise<void> {
  await apiClient.delete(`/photos/${id}/favorite`);
}
