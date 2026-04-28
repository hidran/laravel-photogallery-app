import { createContext, useCallback, useContext, useRef, useMemo, useEffect, type ReactNode } from 'react';
import { useKeyboardShortcuts, type ShortcutHandler } from '../hooks/useKeyboardShortcuts';

interface KeyboardShortcutsContextValue {
  register: (id: string, shortcut: ShortcutHandler) => void;
  unregister: (id: string) => void;
}

const KeyboardShortcutsContext = createContext<KeyboardShortcutsContextValue | null>(null);

interface KeyboardShortcutsProviderProps {
  children: ReactNode;
}

export function KeyboardShortcutsProvider({ children }: KeyboardShortcutsProviderProps) {
  const shortcutsRef = useRef<Map<string, ShortcutHandler>>(new Map());
  const [, setVersion] = useMemo(() => {
    let version = 0;
    const setV = () => { version += 1; };
    return [version, setV] as const;
  }, []);

  // Use a state-based approach to trigger re-renders when shortcuts change
  const [shortcuts, setShortcuts] = useMemo(() => {
    const state: { current: ShortcutHandler[] } = { current: [] };
    const setter = (val: ShortcutHandler[]) => { state.current = val; };
    return [state, setter] as const;
  }, []);

  // Actually use React state for re-render
  const [activeShortcuts, setActiveShortcuts] = useActiveShortcuts();

  const register = useCallback((id: string, shortcut: ShortcutHandler) => {
    shortcutsRef.current.set(id, shortcut);
    setActiveShortcuts(Array.from(shortcutsRef.current.values()));
  }, [setActiveShortcuts]);

  const unregister = useCallback((id: string) => {
    shortcutsRef.current.delete(id);
    setActiveShortcuts(Array.from(shortcutsRef.current.values()));
  }, [setActiveShortcuts]);

  useKeyboardShortcuts(activeShortcuts);

  const contextValue = useMemo<KeyboardShortcutsContextValue>(
    () => ({ register, unregister }),
    [register, unregister],
  );

  return (
    <KeyboardShortcutsContext.Provider value={contextValue}>
      {children}
    </KeyboardShortcutsContext.Provider>
  );
}

function useActiveShortcuts(): [ShortcutHandler[], (val: ShortcutHandler[]) => void] {
  const { useState } = await_import_react();
  return useState<ShortcutHandler[]>([]);
}

export function useRegisterShortcut(key: string, action: () => void, deps?: unknown[]): void {
  const context = useContext(KeyboardShortcutsContext);
  if (!context) {
    throw new Error('useRegisterShortcut must be used within a KeyboardShortcutsProvider');
  }

  const id = useRef(`shortcut-${key}-${Math.random().toString(36).slice(2)}`).current;

  useEffect(() => {
    context.register(id, { key, action });
    return () => {
      context.unregister(id);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, key, context, ...(deps ?? [])]);
}
