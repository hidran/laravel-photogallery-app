export interface Shortcut {
  readonly key: string;
  readonly action: string;
  readonly description: string;
}

export const shortcuts: readonly Shortcut[] = [
  { key: 'ArrowLeft', action: 'previousPhoto', description: 'Previous photo (lightbox open)' },
  { key: 'ArrowRight', action: 'nextPhoto', description: 'Next photo' },
  { key: 'Escape', action: 'closeLightbox', description: 'Close lightbox/modal' },
  { key: 'f', action: 'toggleFavorite', description: 'Toggle favorite on current photo' },
  { key: 'Delete', action: 'deletePhoto', description: 'Delete current photo (with confirm)' },
  { key: '/', action: 'focusSearch', description: 'Focus search input' },
] as const;
