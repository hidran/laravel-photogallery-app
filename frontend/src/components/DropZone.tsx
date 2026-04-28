import { useCallback, useRef, useState } from 'react';
import { Upload } from 'lucide-react';
import { copy } from '../data/copy';

const ACCEPTED_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const MAX_FILE_COUNT = 20;

interface DropZoneProps {
  onFilesSelected: (files: File[]) => void;
  onError: (message: string) => void;
  disabled?: boolean;
}

export function DropZone({ onFilesSelected, onError, disabled = false }: DropZoneProps) {
  const [dragState, setDragState] = useState<'idle' | 'dragover' | 'invalid'>('idle');
  const inputRef = useRef<HTMLInputElement>(null);
  const dragCounterRef = useRef(0);

  const validate = useCallback(
    (files: File[]): File[] | null => {
      if (files.length > MAX_FILE_COUNT) {
        onError(copy.upload.errorMaxFiles);
        return null;
      }

      const invalidType = files.find((f) => !ACCEPTED_TYPES.has(f.type));
      if (invalidType) {
        onError(copy.upload.errorFileType);
        return null;
      }

      const tooLarge = files.find((f) => f.size > MAX_FILE_SIZE);
      if (tooLarge) {
        onError(copy.upload.errorMaxSize);
        return null;
      }

      return files;
    },
    [onError],
  );

  const handleDragEnter = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      e.stopPropagation();
      if (disabled) return;
      dragCounterRef.current += 1;
      if (dragCounterRef.current === 1) {
        const items = Array.from(e.dataTransfer.items);
        const hasInvalid = items.some((item) => item.kind === 'file' && !ACCEPTED_TYPES.has(item.type));
        setDragState(hasInvalid ? 'invalid' : 'dragover');
      }
    },
    [disabled],
  );

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounterRef.current -= 1;
    if (dragCounterRef.current === 0) {
      setDragState('idle');
    }
  }, []);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      e.stopPropagation();
      dragCounterRef.current = 0;
      setDragState('idle');
      if (disabled) return;

      const files = Array.from(e.dataTransfer.files);
      const valid = validate(files);
      if (valid) {
        onFilesSelected(valid);
      }
    },
    [disabled, onFilesSelected, validate],
  );

  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      const valid = validate(files);
      if (valid) {
        onFilesSelected(valid);
      }
      // Reset input so the same file can be selected again
      e.target.value = '';
    },
    [onFilesSelected, validate],
  );

  const handleClick = useCallback(() => {
    if (!disabled) {
      inputRef.current?.click();
    }
  }, [disabled]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleClick();
      }
    },
    [handleClick],
  );

  const borderColor =
    dragState === 'dragover'
      ? 'border-blue-500 bg-blue-50'
      : dragState === 'invalid'
        ? 'border-red-500 bg-red-50'
        : 'border-gray-300 hover:border-gray-400';

  return (
    <div
      role="button"
      tabIndex={disabled ? -1 : 0}
      className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors ${borderColor} ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
      onDragEnter={handleDragEnter}
      onDragLeave={handleDragLeave}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
      onClick={handleClick}
      onKeyDown={handleKeyDown}
    >
      <Upload className="mb-2 h-8 w-8 text-gray-400" />
      <p className="text-center text-sm text-gray-600">{copy.upload.dragDrop}</p>
      <input
        ref={inputRef}
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={handleInputChange}
        disabled={disabled}
      />
    </div>
  );
}
