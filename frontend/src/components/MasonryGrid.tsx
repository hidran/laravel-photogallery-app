import { useEffect, useRef } from 'react';
import type { Photo } from '../types';
import { PhotoCard } from './PhotoCard';

interface MasonryGridProps {
  photos: Photo[];
  onLoadMore?: () => void;
  hasMore?: boolean;
  onClick?: (photo: Photo) => void;
}

export function MasonryGrid({ photos, onLoadMore, hasMore, onClick }: MasonryGridProps) {
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
      { rootMargin: '200px' }
    );

    observer.observe(sentinel);

    return () => {
      observer.disconnect();
    };
  }, [onLoadMore, hasMore]);

  return (
    <div>
      <div className="columns-2 gap-4 md:columns-3 lg:columns-4">
        {photos.map((photo) => (
          <div key={photo.id} className="mb-4 break-inside-avoid">
            <PhotoCard photo={photo} onClick={() => onClick?.(photo)} />
          </div>
        ))}
      </div>
      {hasMore && <div ref={sentinelRef} className="h-1" />}
    </div>
  );
}
