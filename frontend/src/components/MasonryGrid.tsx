import { type ReactNode, useEffect, useRef } from 'react';
import type { Photo } from '../types';
import { PhotoCard } from './PhotoCard';

interface MasonryGridProps {
  photos: Photo[];
  onLoadMore?: () => void;
  hasMore?: boolean;
  onClick?: (photo: Photo) => void;
  onDelete?: (photo: Photo) => void;
  currentUserId?: string;
  renderOverlay?: ((photo: Photo) => ReactNode) | undefined;
}

export function MasonryGrid({
  photos,
  onLoadMore,
  hasMore,
  onClick,
  onDelete,
  currentUserId,
  renderOverlay,
}: MasonryGridProps) {
  const sentinelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!onLoadMore || !hasMore) return;

    const sentinel = sentinelRef.current;
    if (!sentinel) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          onLoadMore();
        }
      },
      { rootMargin: '200px' },
    );

    observer.observe(sentinel);

    return () => {
      observer.disconnect();
    };
  }, [onLoadMore, hasMore]);

  return (
    <div>
      <div className="columns-2 gap-5 md:columns-3 lg:columns-4 xl:columns-5">
        {photos.map((photo) => (
          <div key={photo.id} className="relative mb-5 break-inside-avoid">
            <PhotoCard
              photo={photo}
              onClick={() => onClick?.(photo)}
              {...(onDelete ? { onDelete } : {})}
              {...(currentUserId ? { isOwner: photo.owner.id === currentUserId } : {})}
            />
            {renderOverlay && (
              <div
                className="absolute inset-0 cursor-pointer"
                onClick={() => onClick?.(photo)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onClick?.(photo);
                  }
                }}
                role="button"
                tabIndex={0}
                aria-label={`Select ${photo.title}`}
              >
                {renderOverlay(photo)}
              </div>
            )}
          </div>
        ))}
      </div>
      {hasMore && <div ref={sentinelRef} className="h-1" />}
    </div>
  );
}
