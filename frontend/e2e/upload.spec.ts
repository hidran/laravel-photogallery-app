import { test, expect } from '@playwright/test';

function mockAuthenticatedApp(page: import('@playwright/test').Page) {
  return Promise.all([
    page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-token');
    }),
    page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: '00000000-0000-0000-0000-000000000001', name: 'Test User', email: 'test@example.com' } }),
      }),
    ),
    page.route('**/api/v1/photos*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: {}, meta: {} }) }),
    ),
    page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    ),
    page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    ),
  ]);
}

test.describe('Upload', () => {
  test('upload button opens the upload modal', async ({ page }) => {
    await mockAuthenticatedApp(page);
    await page.goto('/');

    // Click the "Upload Photos" button in the navbar
    await page.getByRole('button', { name: /upload photos/i }).click();

    // Modal should appear with the drop zone text
    await expect(page.getByText(/drag and drop photos here/i)).toBeVisible();
  });

  test('drop zone is visible inside the upload modal', async ({ page }) => {
    await mockAuthenticatedApp(page);
    await page.goto('/');

    await page.getByRole('button', { name: /upload photos/i }).click();

    // The drop zone has role="button" and contains the drag-drop instruction
    const dropZone = page.getByRole('button', { name: /drag and drop/i });
    await expect(dropZone).toBeVisible();
  });

  test('file input accepts only jpeg, png, and webp', async ({ page }) => {
    await mockAuthenticatedApp(page);
    await page.goto('/');

    await page.getByRole('button', { name: /upload photos/i }).click();

    // The hidden file input should have the correct accept attribute
    const fileInput = page.locator('input[type="file"]');
    await expect(fileInput).toHaveAttribute('accept', 'image/jpeg,image/png,image/webp');
  });

  test('shows error toast when file type is invalid', async ({ page }) => {
    await mockAuthenticatedApp(page);
    await page.goto('/');

    await page.getByRole('button', { name: /upload photos/i }).click();

    // Upload an invalid file via the file input
    const fileInput = page.locator('input[type="file"]');
    // Create a fake text file to trigger the type validation
    const buffer = Buffer.from('not an image');
    await fileInput.setInputFiles({
      name: 'test.txt',
      mimeType: 'text/plain',
      buffer,
    });

    // The error toast should appear with the file type message
    await expect(page.getByText(/only jpeg, png, and webp/i)).toBeVisible();
  });

  test('shows error toast when file exceeds size limit', async ({ page }) => {
    await mockAuthenticatedApp(page);
    await page.goto('/');

    await page.getByRole('button', { name: /upload photos/i }).click();

    // Create a buffer larger than 10 MB
    const largeBuffer = Buffer.alloc(11 * 1024 * 1024);
    const fileInput = page.locator('input[type="file"]');
    await fileInput.setInputFiles({
      name: 'large.jpg',
      mimeType: 'image/jpeg',
      buffer: largeBuffer,
    });

    // The error toast should appear with the size message
    await expect(page.getByText(/10 mb or smaller/i)).toBeVisible();
  });
});
