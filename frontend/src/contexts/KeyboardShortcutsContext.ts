import { createContext } from 'react';
import type { ShortcutHandler } from '../hooks/useKeyboardShortcuts';

export interface KeyboardShortcutsContextValue {
  register: (id: string, shortcut: ShortcutHandler) => void;
  unregister: (id: string) => void;
}

export const KeyboardShortcutsContext = createContext<KeyboardShortcutsContextValue | null>(null);
