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
    is_favorite: false,
    favorites_count: 0,
    urls: { thumbnail: '/fake-thumb-2.jpg', medium: '/fake-medium-2.jpg', large: '/fake-large-2.jpg', original: null },
    exif: null,
    album: null,
    tags: [],
    owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
    created_at: '2025-01-02T00:00:00Z',
    updated_at: '2025-01-02T00:00:00Z',
  },
  {
    id: '00000000-0000-0000-0000-000000000003',
    title: 'Forest Trail',
    description: null,
    processing_status: 'completed',
    processing_error: null,
    width: 640,
    height: 480,
    is_favorite: false,
    favorites_count: 0,
    urls: { thumbnail: '/fake-thumb-3.jpg', medium: '/fake-medium-3.jpg', large: '/fake-large-3.jpg', original: null },
    exif: null,
    album: null,
    tags: [],
    owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
    created_at: '2025-01-03T00:00:00Z',
    updated_at: '2025-01-03T00:00:00Z',
  },
];

function mockLightboxPage(page: import('@playwright/test').Page) {
  return Promise.all([
    page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
    ),
    page.route('**/api/v1/photos*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: MOCK_PHOTOS, links: { next: null }, meta: { current_page: 1 } }),
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

test.describe('Lightbox', () => {
  test('clicking a photo opens the lightbox dialog', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    // Click the first photo card
    await page.getByAlt('Sunset Beach').click();

    // The lightbox dialog should appear
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    // The photo title should be visible in the lightbox
    await expect(dialog.getByText('Sunset Beach')).toBeVisible();
  });

  test('lightbox shows the large image', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    await page.getByAlt('Sunset Beach').click();

    // The lightbox should display the large version of the image
    const lightboxImage = page.getByRole('dialog').locator('img');
    await expect(lightboxImage).toHaveAttribute('src', '/fake-large-1.jpg');
  });

  test('arrow keys navigate between photos', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    // Open lightbox on the first photo
    await page.getByAlt('Sunset Beach').click();
    await expect(page.getByRole('dialog').getByText('Sunset Beach')).toBeVisible();

    // Press right arrow to go to the next photo
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('dialog').getByText('Mountain View')).toBeVisible();

    // Press right arrow again
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('dialog').getByText('Forest Trail')).toBeVisible();

    // Press left arrow to go back
    await page.keyboard.press('ArrowLeft');
    await expect(page.getByRole('dialog').getByText('Mountain View')).toBeVisible();
  });

  test('Escape key closes the lightbox', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    await page.getByAlt('Sunset Beach').click();
    await expect(page.getByRole('dialog')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
  });

  test('close button closes the lightbox', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    await page.getByAlt('Sunset Beach').click();
    await expect(page.getByRole('dialog')).toBeVisible();

    await page.getByRole('button', { name: /close/i }).click();
    await expect(page.getByRole('dialog')).not.toBeVisible();
  });

  test('URL updates with photo query param when lightbox opens', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    await page.getByAlt('Sunset Beach').click();

    // The URL should contain a ?photo= parameter
    await expect(page).toHaveURL(/photo=00000000-0000-0000-0000-000000000001/);
  });

  test('navigation buttons are visible for prev/next photos', async ({ page }) => {
    await mockLightboxPage(page);
    await page.goto('/');

    // Open lightbox on the second photo (has both prev and next)
    await page.getByAlt('Mountain View').click();
    await expect(page.getByRole('dialog')).toBeVisible();

    await expect(page.getByRole('button', { name: /previous photo/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /next photo/i })).toBeVisible();
  });
});
