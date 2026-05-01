import { useCallback, useId } from 'react';
import { createPortal } from 'react-dom';
import FocusLock from 'react-focus-lock';

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  children: React.ReactNode;
  title?: string;
}

export function Modal({ isOpen, onClose, children, title }: ModalProps) {
  const titleId = useId();

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    },
    [onClose],
  );

  const handleBackdropClick = useCallback(
    (e: React.MouseEvent) => {
      if (e.target === e.currentTarget) {
        onClose();
      }
    },
    [onClose],
  );

  if (!isOpen) {
    return null;
  }

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      onClick={handleBackdropClick}
      onKeyDown={handleKeyDown}
    >
      <FocusLock returnFocus>
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby={title ? titleId : undefined}
          className="relative mx-4 max-h-[90vh] w-full max-w-lg overflow-auto rounded-lg bg-white p-6 shadow-xl"
        >
          {title && (
            <h2 id={titleId} className="mb-4 text-lg font-semibold text-gray-900">
              {title}
            </h2>
          )}
          {children}
        </div>
      </FocusLock>
    </div>,
    document.body,
  );
}
