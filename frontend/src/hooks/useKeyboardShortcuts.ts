import { useEffect } from 'react';

export interface ShortcutHandler {
  key: string;
  action: () => void;
  enabled?: boolean;
}

function isEditableElement(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;
  const tagName = target.tagName.toLowerCase();
  if (tagName === 'input' || tagName === 'textarea') return true;
  if (target.isContentEditable) return true;
  return false;
}

export function useKeyboardShortcuts(shortcuts: ShortcutHandler[]): void {
  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent): void {
      for (const shortcut of shortcuts) {
        if (shortcut.enabled === false) continue;
        if (event.key !== shortcut.key) continue;

        // Allow "/" to steal focus even from editable elements
        if (shortcut.key === '/' && isEditableElement(event.target)) {
          event.preventDefault();
          shortcut.action();
          return;
        }

        // Ignore keystrokes when focus is in input/textarea/contenteditable
        if (isEditableElement(event.target)) return;

        event.preventDefault();
        shortcut.action();
        return;
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [shortcuts]);
}
