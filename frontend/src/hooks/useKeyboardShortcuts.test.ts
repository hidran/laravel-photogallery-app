import { renderHook } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { useKeyboardShortcuts } from './useKeyboardShortcuts';

function fireKey(key: string, target?: EventTarget) {
  const event = new KeyboardEvent('keydown', { key, bubbles: true });
  if (target) {
    Object.defineProperty(event, 'target', { value: target });
  }
  window.dispatchEvent(event);
}

describe('useKeyboardShortcuts', () => {
  it('calls action when the registered key is pressed', () => {
    const action = vi.fn();
    renderHook(() => useKeyboardShortcuts([{ key: 'Escape', action }]));

    fireKey('Escape');
    expect(action).toHaveBeenCalledOnce();
  });

  it('does not call action for unregistered keys', () => {
    const action = vi.fn();
    renderHook(() => useKeyboardShortcuts([{ key: 'Escape', action }]));

    fireKey('Enter');
    expect(action).not.toHaveBeenCalled();
  });

  it('skips actions when focus is in an input element', () => {
    const action = vi.fn();
    renderHook(() => useKeyboardShortcuts([{ key: 'Escape', action }]));

    const input = document.createElement('input');
    document.body.appendChild(input);
    fireKey('Escape', input);

    expect(action).not.toHaveBeenCalled();
    document.body.removeChild(input);
  });

  it('allows "/" shortcut even when focus is in an input', () => {
    const action = vi.fn();
    renderHook(() => useKeyboardShortcuts([{ key: '/', action }]));

    const input = document.createElement('input');
    document.body.appendChild(input);
    fireKey('/', input);

    expect(action).toHaveBeenCalledOnce();
    document.body.removeChild(input);
  });

  it('respects the enabled flag', () => {
    const action = vi.fn();
    renderHook(() => useKeyboardShortcuts([{ key: 'Escape', action, enabled: false }]));

    fireKey('Escape');
    expect(action).not.toHaveBeenCalled();
  });

  it('cleans up the event listener on unmount', () => {
    const action = vi.fn();
    const { unmount } = renderHook(() => useKeyboardShortcuts([{ key: 'Escape', action }]));

    unmount();
    fireKey('Escape');
    expect(action).not.toHaveBeenCalled();
  });
});
