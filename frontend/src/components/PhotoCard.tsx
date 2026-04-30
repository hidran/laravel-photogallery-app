import { Heart } from 'lucide-react';
import type { Photo } from '../types';
import { useToggleFavorite } from '../hooks';
import { copy } from '../data/copy';
import { ProcessingOverlay } from './ProcessingOverlay';

interface PhotoCardProps {
  photo: Photo;
  onClick: () => void;
}

export function PhotoCard({ photo, onClick }: PhotoCardProps) {
  const toggleFavorite = useToggleFavorite();

  const handleFavoriteClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    toggleFavorite.mutate({ id: photo.id, isFavorite: photo.is_favorite });
  };

  return (
    <div
      className="group relative cursor-pointer overflow-hidden rounded-lg"
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
    >
      <img
        src={photo.urls.medium ?? photo.urls.thumbnail ?? ''}
        srcSet={
          photo.urls.thumbnail && photo.urls.medium
            ? `${photo.urls.thumbnail} 300w, ${photo.urls.medium} 800w`
            : undefined
        }
        sizes="(max-width: 768px) 50vw, 25vw"
        alt={photo.title}
        loading="lazy"
        decoding="async"
        width={photo.width ?? undefined}
        height={photo.height ?? undefined}
        className="w-full object-cover"
      />

      {/* Hover overlay */}
      <div className="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100">
        <div className="flex items-center justify-between p-3">
          <span className="truncate text-sm font-medium text-white">{photo.title}</span>
          <button
            type="button"
            onClick={handleFavoriteClick}
            aria-label={photo.is_favorite ? copy.favorites.remove : copy.favorites.add}
            className="relative shrink-0 rounded-full p-1.5 transition-colors hover:bg-white/20"
          >
            <Heart
              className={`h-5 w-5 ${
                photo.is_favorite ? 'fill-red-500 text-red-500' : 'text-white'
              }`}
            />
            {photo.favorites_count > 0 && (
              <span className="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                {photo.favorites_count}
              </span>
            )}
          </button>
        </div>
      </div>

      {/* Processing overlay */}
      {photo.processing_status !== 'completed' && (
        <ProcessingOverlay status={photo.processing_status} error={photo.processing_error} />
      )}
    </div>
  );
}
