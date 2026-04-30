import { useContext, useEffect, useId } from 'react';
import { KeyboardShortcutsContext } from '../contexts/KeyboardShortcutsContext';

export function useRegisterShortcut(key: string, action: () => void, deps?: unknown[]): void {
  const context = useContext(KeyboardShortcutsContext);
  if (!context) {
    throw new Error('useRegisterShortcut must be used within a KeyboardShortcutsProvider');
  }

  const id = useId();
  const stableId = `shortcut-${key}-${id}`;

  useEffect(() => {
    const currentId = stableId;
    context.register(currentId, { key, action });
    return () => {
      context.unregister(currentId);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, context, stableId, ...(deps ?? [])]);
}
