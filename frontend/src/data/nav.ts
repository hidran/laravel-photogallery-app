export interface NavEntry {
  readonly label: string;
  readonly path: string;
  readonly icon?: string;
}

export const nav: readonly NavEntry[] = [
  { label: 'All Photos', path: '/', icon: 'images' },
  { label: 'Favorites', path: '/favorites', icon: 'heart' },
] as const;
