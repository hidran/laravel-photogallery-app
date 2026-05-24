import { Trash2, X } from 'lucide-react';
import { copy } from '../../data/copy';

interface SelectionToolbarProps {
  selectedCount: number;
  onDelete: () => void;
  onCancel: () => void;
  isPending: boolean;
}

export function SelectionToolbar({
  selectedCount,
  onDelete,
  onCancel,
  isPending,
}: SelectionToolbarProps) {
  return (
    <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-100 px-4 py-2.5">
      <span className="text-sm font-medium text-gray-700">
        {copy.gallery.selectedCount(selectedCount)}
      </span>
      <div className="ml-auto flex items-center gap-2">
        <button
          type="button"
          onClick={onDelete}
          disabled={selectedCount === 0 || isPending}
          className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-40"
        >
          <Trash2 className="h-3.5 w-3.5" />
          {copy.gallery.deleteSelected}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50"
        >
          <X className="h-3.5 w-3.5" />
          {copy.gallery.cancelSelect}
        </button>
      </div>
    </div>
  );
}
