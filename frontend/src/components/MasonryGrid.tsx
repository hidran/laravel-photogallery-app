import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
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

/** Breakpoint → column count mapping (matches Tailwind's default breakpoints). */
function getColumnCount(width: number): number {
  if (width >= 1280) return 5; // xl
  if (width >= 1024) return 4; // lg
  if (width >= 768) return 3; // md
  return 2;
}

/**
 * Distribute photos left-to-right across columns using a shortest-column
 * algorithm. This preserves chronological order visually (row by row)
 * instead of the top-to-bottom flow that CSS `columns-*` produces.
 */
function distributePhotos(photos: Photo[], columnCount: number): Photo[][] {
  const columns: Photo[][] = Array.from({ length: columnCount }, () => []);
  const heights = new Array<number>(columnCount).fill(0);

  for (const photo of photos) {
    // Find the shortest column to place the next photo
    let shortest = 0;
    for (let i = 1; i < columnCount; i++) {
      if ((heights[i] ?? 0) < (heights[shortest] ?? 0)) {
        shortest = i;
      }
    }
    columns[shortest]?.push(photo);
    // Estimate aspect ratio from dimensions; default to 4:3 if unknown
    const aspect = photo.width && photo.height ? photo.height / photo.width : 0.75;
    heights[shortest] = (heights[shortest] ?? 0) + aspect;
  }

  return columns;
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
  const containerRef = useRef<HTMLDivElement>(null);
  const [columnCount, setColumnCount] = useState(() => getColumnCount(window.innerWidth));

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const observer = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect.width ?? window.innerWidth;
      setColumnCount(getColumnCount(width));
    });

    observer.observe(container);
    return () => observer.disconnect();
  }, []);

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

  const columns = useMemo(
    () => distributePhotos(photos, columnCount),
    [photos, columnCount],
  );

  return (
    <div ref={containerRef}>
      <div className="flex gap-5">
        {columns.map((columnPhotos, colIndex) => (
          <div key={colIndex} className="flex flex-1 flex-col gap-5">
            {columnPhotos.map((photo) => (
              <div key={photo.id} className="relative">
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
                  >
                    {renderOverlay(photo)}
                  </div>
                )}
              </div>
            ))}
          </div>
        ))}
      </div>
      {hasMore && <div ref={sentinelRef} className="h-1" />}
    </div>
  );
}
