export interface AlbumCoverPhoto {
  id: string;
  urls: {
    thumbnail: string | null;
  };
}

export interface AlbumOwner {
  id: string;
  name: string;
}

export interface Album {
  id: string;
  name: string;
  description: string | null;
  cover_photo: AlbumCoverPhoto | null;
  photos_count: number;
  owner: AlbumOwner;
  created_at: string;
  updated_at: string;
}
