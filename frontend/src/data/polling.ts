export const polling = {
  BATCH_MS: 1000, // poll batch progress
  PHOTO_MS: 3000, // legacy fallback if batch endpoint absent
  QUEUE_MS: 5000, // admin queue widget
  RECENT_MS: 10000, // admin recent uploads widget
  DEBOUNCE_MS: 300, // search input
} as const;
