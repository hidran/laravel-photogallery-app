export interface PhotoUrls {
  thumbnail: string | null;
  medium: string | null;
  large: string | null;
  original?: string; // present only for owner/admin
}

export interface PhotoExif {
  camera: string | null;
  iso: number | null;
  aperture: string | null;
  shutter: string | null;
  focal_length: string | null;
  taken_at: string | null;
}

export type ProcessingStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface PhotoAlbum {
  id: string;
  name: string;
}

export interface PhotoTag {
  id: string;
  name: string;
  slug: string;
}

export interface PhotoOwner {
  id: string;
  name: string;
}

export interface Photo {
  id: string;
  title: string;
  description: string | null;
  filename: string;
  urls: PhotoUrls;
  width: number | null;
  height: number | null;
  file_size: number;
  mime_type: string;
  is_favorite: boolean;
  exif: PhotoExif | null;
  processing_status: ProcessingStatus;
  processing_error: string | null;
  album: PhotoAlbum | null;
  tags: PhotoTag[];
  owner: PhotoOwner;
  created_at: string;
  updated_at: string;
}
