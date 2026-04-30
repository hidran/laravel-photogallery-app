import { test, expect } from '@playwright/test';

const MOCK_FAVORITE_PHOTOS = [
  {
    id: '00000000-0000-0000-0000-000000000001',
    title: 'Favorite Sunset',
    description: 'A favorite sunset',
    processing_status: 'completed',
    processing_error: null,
    width: 800,
    height: 600,
    is_favorite: true,
    favorites_count: 5,
    urls: { thumbnail: '/fake-thumb-1.jpg', medium: '/fake-medium-1.jpg', large: '/fake-large-1.jpg', original: null },
    exif: null,
    album: null,
    tags: [],
    owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
  {
    id: '00000000-0000-0000-0000-000000000002',
    title: 'Favorite Mountain',
    description: null,
    processing_status: 'completed',
    processing_error: null,
    width: 1024,
    height: 768,
    is_favorite: true,
    favorites_count: 2,
    urls: { thumbnail: '/fake-thumb-2.jpg', medium: '/fake-medium-2.jpg', large: '/fake-large-2.jpg', original: null },
    exif: null,
    album: null,
    tags: [],
    owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
    created_at: '2025-01-02T00:00:00Z',
    updated_at: '2025-01-02T00:00:00Z',
  },
];

function mockFavoritesPage(page: import('@playwright/test').Page) {
  return Promise.all([
    page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-token');
    }),
    page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner', email: 'owner@example.com' } }),
      }),
    ),
    page.route('**/api/v1/photos*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: MOCK_FAVORITE_PHOTOS, links: { next: null }, meta: { current_page: 1 } }),
      }),
    ),
    page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    ),
    page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    ),
  ]);
}

test.describe('Favorites', () => {
  test('favorites page loads and shows title', async ({ page }) => {
    await mockFavoritesPage(page);
    await page.goto('/favorites');

    await expect(page.getByRole('heading', { name: /favorites/i })).toBeVisible();
  });

  test('favorites page shows favorite photos', async ({ page }) => {
    await mockFavoritesPage(page);
    await page.goto('/favorites');

    await expect(page.getByAlt('Favorite Sunset')).toBeVisible();
    await expect(page.getByAlt('Favorite Mountain')).toBeVisible();
  });

  test('heart icon is visible on photo cards', async ({ page }) => {
    await mockFavoritesPage(page);
    await page.goto('/favorites');

    // Heart buttons should be present (one per photo, accessible via aria-label)
    const heartButtons = page.getByRole('button', { name: /remove from favorites/i });
    await expect(heartButtons.first()).toBeVisible();
  });

  test('clicking heart icon triggers unfavorite API call', async ({ page }) => {
    await mockFavoritesPage(page);

    let unfavoriteRequested = false;
    await page.route('**/api/v1/photos/*/favorite', (route) => {
      if (route.request().method() === 'DELETE') {
        unfavoriteRequested = true;
        return route.fulfill({ status: 204 });
      }
      return route.continue();
    });

    await page.goto('/favorites');

    // Hover over the first photo to reveal the heart button
    await page.getByAlt('Favorite Sunset').hover();

    // Click the heart button to unfavorite
    const heartButton = page.getByRole('button', { name: /remove from favorites/i }).first();
    await heartButton.click();

    // Verify the API call was made
    expect(unfavoriteRequested).toBe(true);
  });

  test('empty favorites shows empty state message', async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-token');
    });
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner', email: 'owner@example.com' } }),
      }),
    );
    await page.route('**/api/v1/photos*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [], links: { next: null }, meta: { current_page: 1 } }),
      }),
    );
    await page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );

    await page.goto('/favorites');

    await expect(page.getByText(/no favorite photos yet/i)).toBeVisible();
  });
});
