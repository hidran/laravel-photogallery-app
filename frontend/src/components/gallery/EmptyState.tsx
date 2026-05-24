import { ImageOff } from 'lucide-react';
import { eventBus } from '../../lib/eventBus';
import { copy } from '../../data/copy';

interface EmptyStateProps {
  hasFilters: boolean;
}

export function EmptyState({ hasFilters }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-4 py-24 text-center">
      <div className="rounded-2xl bg-gray-100 p-6">
        <ImageOff className="h-10 w-10 text-gray-400" />
      </div>
      <div>
        <p className="text-lg font-semibold text-gray-800">
          {hasFilters ? 'No results' : 'Your gallery is empty'}
        </p>
        <p className="mt-1 text-sm text-gray-500">
          {hasFilters ? 'Try different filters or search terms' : copy.gallery.emptyState}
        </p>
      </div>
      {!hasFilters && (
        <button
          type="button"
          onClick={() => eventBus.emit('upload-modal:open', undefined)}
          className="mt-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700"
        >
          {copy.upload.title}
        </button>
      )}
    </div>
  );
}
