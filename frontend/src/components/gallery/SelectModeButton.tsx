import { MousePointer2 } from 'lucide-react';
import { copy } from '../../data/copy';

interface SelectModeButtonProps {
  onClick: () => void;
}

export function SelectModeButton({ onClick }: SelectModeButtonProps) {
  return (
    <div className="mb-4 flex items-center">
      <button
        type="button"
        onClick={onClick}
        className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700"
      >
        <MousePointer2 className="h-3.5 w-3.5" />
        {copy.gallery.selectMode}
      </button>
    </div>
  );
}
