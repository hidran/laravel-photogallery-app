import { Camera, FolderOpen, Tag } from 'lucide-react';
import { copy } from '../../data/copy';
import type { Album } from '../../types';

interface StatsBarProps {
  albums: Album[];
  tagCount: number;
}

export function StatsBar({ albums, tagCount }: StatsBarProps) {
  const totalPhotos = albums.reduce((sum, album) => sum + album.photos_count, 0);

  return (
    <div className="border-t border-white/10 px-3 py-3">
      <div className="flex items-center justify-around text-center">
        <div className="flex flex-col items-center">
          <Camera className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
          <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
            {totalPhotos}
          </span>
          <span className="text-[10px] text-sidebar-text/50">{copy.stats.photos}</span>
        </div>
        <div className="flex flex-col items-center">
          <FolderOpen className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
          <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
            {albums.length}
          </span>
          <span className="text-[10px] text-sidebar-text/50">{copy.stats.albums}</span>
        </div>
        <div className="flex flex-col items-center">
          <Tag className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
          <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
            {tagCount}
          </span>
          <span className="text-[10px] text-sidebar-text/50">{copy.stats.tags}</span>
        </div>
      </div>
    </div>
  );
}
