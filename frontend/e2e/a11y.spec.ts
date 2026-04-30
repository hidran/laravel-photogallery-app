import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

function mockGalleryApis(page: import('@playwright/test').Page) {
  return Promise.all([
    page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
    ),
    page.route('**/api/v1/photos*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '00000000-0000-0000-0000-000000000001',
              title: 'Test Photo',
              description: 'A test photo',
              processing_status: 'completed',
              processing_error: null,
              width: 800,
              height: 600,
              is_favorite: false,
              favorites_count: 0,
              urls: { thumbnail: '/fake-thumb.jpg', medium: '/fake-medium.jpg', large: '/fake-large.jpg', original: null },
              exif: null,
              album: null,
              tags: [],
              owner: { id: '00000000-0000-0000-0000-000000000099', name: 'Owner' },
              created_at: '2025-01-01T00:00:00Z',
              updated_at: '2025-01-01T00:00:00Z',
            },
          ],
          links: { next: null },
          meta: { current_page: 1 },
        }),
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

test.describe('Accessibility', () => {
  test('gallery page has no critical accessibility violations', async ({ page }) => {
    await mockGalleryApis(page);
    await page.goto('/');

    // Wait for content to load
    await page.getByAlt('Test Photo').waitFor();

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    // Filter to only critical and serious violations
    const criticalViolations = results.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );

    expect(criticalViolations).toEqual([]);
  });

  test('login page has no critical accessibility violations', async ({ page }) => {
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
    );

    await page.goto('/login');

    // Wait for the form to render
    await page.getByRole('heading', { name: /sign in/i }).waitFor();

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const criticalViolations = results.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );

    expect(criticalViolations).toEqual([]);
  });
});
