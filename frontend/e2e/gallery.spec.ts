import { test, expect } from '@playwright/test';

const MOCK_PHOTOS = [
  {
    id: '00000000-0000-0000-0000-000000000001',
    title: 'Sunset Beach',
    description: 'A beautiful sunset',
    processing_status: 'completed',
    processing_error: null,
    width: 800,
    height: 600,
    is_favorite: false,
    favorites_count: 0,
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
    title: 'Mountain View',
    description: null,
    processing_status: 'completed',
    processing_error: null,
    width: 1024,
    height: 768,
    is_favorite: true,
    favorites_count: 3,
    urls: { thumbnail: '/fake-thumb-2.jpg', medium: '/fake-medium-2.jpg', large: '/fake-large-2.jpg', original: null },
    exif: null,
    album: { id: 'album-1', name: 'Nature' },
    tags: [{ id: 'tag-1', name: 'landscape', slug: 'landscape' }],
    owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
    created_at: '2025-01-02T00:00:00Z',
    updated_at: '2025-01-02T00:00:00Z',
  },
];

function mockGalleryPage(page: import('@playwright/test').Page, photos = MOCK_PHOTOS) {
  return Promise.all([
    page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
    ),
    page.route('**/api/v1/photos*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: photos, links: { next: null }, meta: { current_page: 1 } }),
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

test.describe('Gallery', () => {
  test('gallery page loads and shows photos', async ({ page }) => {
    await mockGalleryPage(page);
    await page.goto('/');

    // Photos should be rendered as images
    await expect(page.getByAlt('Sunset Beach')).toBeVisible();
    await expect(page.getByAlt('Mountain View')).toBeVisible();
  });

  test('masonry grid container is visible', async ({ page }) => {
    await mockGalleryPage(page);
    await page.goto('/');

    // The MasonryGrid renders photo cards; verify the images are present within the page
    const images = page.locator('img[alt]');
    await expect(images).toHaveCount(2);
  });

  test('sort dropdown opens and shows options', async ({ page }) => {
    await mockGalleryPage(page);
    await page.goto('/');

    // Click the sort dropdown button (shows "Newest" by default)
    await page.getByRole('button', { name: /newest/i }).click();

    // Sort menu should show all options
    await expect(page.getByRole('menuitem', { name: /oldest/i })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /title a/i })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /title z/i })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /favorites first/i })).toBeVisible();
  });

  test('selecting a sort option updates URL params', async ({ page }) => {
    await mockGalleryPage(page);
    await page.goto('/');

    await page.getByRole('button', { name: /newest/i }).click();
    await page.getByRole('menuitem', { name: /oldest/i }).click();

    await expect(page).toHaveURL(/sort=created_at/);
    await expect(page).toHaveURL(/order=asc/);
  });

  test('search input is present and updates URL after debounce', async ({ page }) => {
    await mockGalleryPage(page);
    await page.goto('/');

    const searchInput = page.getByPlaceholder(/search photos/i);
    await expect(searchInput).toBeVisible();

    await searchInput.fill('sunset');

    // Wait for debounce (300ms) and URL update
    await expect(page).toHaveURL(/search=sunset/, { timeout: 2000 });
  });

  test('select mode toggle shows selection toolbar', async ({ page }) => {
    await mockGalleryPage(page);

    // Need auth for select mode to show
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner', email: 'owner@example.com' } }),
      }),
    );
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-token');
    });

    await page.goto('/');

    // Click "Select" button to enter select mode
    const selectButton = page.getByRole('button', { name: /^select$/i });
    await expect(selectButton).toBeVisible();
    await selectButton.click();

    // The selection toolbar should appear with "0 selected" and "Delete selected"
    await expect(page.getByText(/0 selected/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /delete selected/i })).toBeVisible();
  });

  test('empty state shows when no photos', async ({ page }) => {
    await mockGalleryPage(page, []);
    await page.goto('/');

    await expect(page.getByText(/your gallery is empty/i)).toBeVisible();
  });

  test('empty state with search filter shows "no results"', async ({ page }) => {
    // Mock with empty result for a search query
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
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

    await page.goto('/?search=nonexistent');

    await expect(page.getByText(/no results/i)).toBeVisible();
  });
});
