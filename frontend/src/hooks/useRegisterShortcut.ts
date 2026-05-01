import { useContext, useEffect, useId, useRef } from 'react';
import { KeyboardShortcutsContext } from '../contexts/KeyboardShortcutsContext';

export function useRegisterShortcut(key: string, action: () => void): void {
  const context = useContext(KeyboardShortcutsContext);
  if (!context) {
    throw new Error('useRegisterShortcut must be used within a KeyboardShortcutsProvider');
  }

  const id = useId();
  const stableId = `shortcut-${key}-${id}`;
  const actionRef = useRef(action);

  useEffect(() => {
    actionRef.current = action;
  });

  useEffect(() => {
    const currentId = stableId;
    context.register(currentId, { key, action: () => actionRef.current() });
    return () => {
      context.unregister(currentId);
    };
  }, [key, context, stableId]);
}
