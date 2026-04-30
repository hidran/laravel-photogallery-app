import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react';
import { useKeyboardShortcuts, type ShortcutHandler } from '../hooks/useKeyboardShortcuts';
import { KeyboardShortcutsContext } from '../contexts/KeyboardShortcutsContext';

interface KeyboardShortcutsProviderProps {
  children: ReactNode;
}

export function KeyboardShortcutsProvider({ children }: KeyboardShortcutsProviderProps) {
  const shortcutsMapRef = useRef<Map<string, ShortcutHandler>>(new Map());
  const [activeShortcuts, setActiveShortcuts] = useState<ShortcutHandler[]>([]);

  const register = useCallback((id: string, shortcut: ShortcutHandler) => {
    shortcutsMapRef.current.set(id, shortcut);
    setActiveShortcuts(Array.from(shortcutsMapRef.current.values()));
  }, []);

  const unregister = useCallback((id: string) => {
    shortcutsMapRef.current.delete(id);
    setActiveShortcuts(Array.from(shortcutsMapRef.current.values()));
  }, []);

  useKeyboardShortcuts(activeShortcuts);

  const contextValue = useMemo(() => ({ register, unregister }), [register, unregister]);

  return (
    <KeyboardShortcutsContext.Provider value={contextValue}>
      {children}
    </KeyboardShortcutsContext.Provider>
  );
}
