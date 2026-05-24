import { Heart, Trash2 } from 'lucide-react';
import type { Photo } from '../types';
import { useToggleFavorite } from '../hooks';
import { useUser } from '../contexts/UserContext';
import { copy } from '../data/copy';
import { ProcessingOverlay } from './ProcessingOverlay';

interface PhotoCardProps {
  photo: Photo;
  onClick: () => void;
  onDelete?: (photo: Photo) => void;
}

export function PhotoCard({ photo, onClick, onDelete }: PhotoCardProps) {
  const { user } = useUser();
  const isOwner = user ? photo.owner.id === user.id : false;
  const toggleFavorite = useToggleFavorite();

  const handleFavoriteClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    toggleFavorite.mutate({ id: photo.id, isFavorite: photo.is_favorite });
  };

  const handleDeleteClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    onDelete?.(photo);
  };

  return (
    <div
      className="group relative cursor-pointer overflow-hidden rounded-2xl bg-gray-100 shadow-sm transition-shadow duration-200 hover:shadow-lg"
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
        className="w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
      />

      {/* Hover overlay */}
      <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100" />

      {/* Top-right actions — visible on hover */}
      {isOwner && onDelete && (
        <div className="absolute right-2 top-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
          <button
            type="button"
            onClick={handleDeleteClick}
            aria-label={copy.lightbox.delete}
            className="pointer-events-auto rounded-full bg-black/50 p-1.5 text-white/80 backdrop-blur-sm transition-colors hover:bg-red-600 hover:text-white"
          >
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Bottom info bar — visible on hover */}
      <div className="absolute inset-x-0 bottom-0 translate-y-1 opacity-0 transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:translate-y-0 group-focus-within:opacity-100">
        <div className="flex items-end justify-between p-3">
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-white drop-shadow-sm">
              {photo.title}
            </p>
            {photo.album && <p className="truncate text-xs text-white/70">{photo.album.name}</p>}
          </div>
          <button
            type="button"
            onClick={handleFavoriteClick}
            aria-label={photo.is_favorite ? copy.favorites.remove : copy.favorites.add}
            className="pointer-events-auto relative ml-2 shrink-0 rounded-full p-2 transition-all hover:scale-110 hover:bg-white/20"
          >
            <Heart
              className={`h-5 w-5 drop-shadow-sm transition-colors ${
                photo.is_favorite ? 'fill-red-500 text-red-500' : 'text-white'
              }`}
            />
            {photo.favorites_count > 0 && (
              <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-sm">
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
