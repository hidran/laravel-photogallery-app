import { test, expect } from '@playwright/test';

// Helper: mock the /api/v1/auth/me endpoint as unauthenticated
function mockUnauthenticated(page: import('@playwright/test').Page) {
  return page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
  );
}

// Helper: mock the /api/v1/auth/me endpoint as authenticated
function mockAuthenticated(page: import('@playwright/test').Page, name = 'Test User') {
  return page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: '00000000-0000-0000-0000-000000000001', name, email: 'test@example.com' } }),
    }),
  );
}

test.describe('Authentication', () => {
  test('login page renders with email and password fields', async ({ page }) => {
    await mockUnauthenticated(page);
    await page.goto('/login');

    await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: /log in/i })).toBeVisible();
  });

  test('register form shows name and password confirmation fields', async ({ page }) => {
    await mockUnauthenticated(page);
    await page.goto('/login');

    // Switch to register mode
    await page.getByRole('button', { name: /create account/i }).click();

    await expect(page.getByRole('heading', { name: /create a new account/i })).toBeVisible();
    await expect(page.locator('#name')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#password_confirmation')).toBeVisible();
  });

  test('register flow submits and redirects on success', async ({ page }) => {
    await page.route('**/api/v1/auth/register', (route) =>
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            user: { id: '00000000-0000-0000-0000-000000000001', name: 'New User', email: 'new@example.com' },
            token: 'fake-token-register',
          },
        }),
      }),
    );
    await mockAuthenticated(page, 'New User');
    // Mock photos endpoint for the gallery page after redirect
    await page.route('**/api/v1/photos*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: {}, meta: {} }) }),
    );
    await page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );

    await page.goto('/login');
    await page.getByRole('button', { name: /create account/i }).click();

    await page.locator('#name').fill('New User');
    await page.locator('#email').fill('new@example.com');
    await page.locator('#password').fill('password123');
    await page.locator('#password_confirmation').fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();

    // Should redirect to gallery
    await expect(page).toHaveURL('/');
  });

  test('login flow submits and redirects on success', async ({ page }) => {
    await page.route('**/api/v1/auth/login', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            user: { id: '00000000-0000-0000-0000-000000000001', name: 'Test User', email: 'test@example.com' },
            token: 'fake-token-login',
          },
        }),
      }),
    );
    await mockAuthenticated(page);
    await page.route('**/api/v1/photos*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: {}, meta: {} }) }),
    );
    await page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );

    await page.goto('/login');
    await page.locator('#email').fill('test@example.com');
    await page.locator('#password').fill('password123');
    await page.getByRole('button', { name: /log in/i }).click();

    await expect(page).toHaveURL('/');
  });

  test('login shows validation errors on 422', async ({ page }) => {
    await mockUnauthenticated(page);
    await page.route('**/api/v1/auth/login', (route) =>
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The provided credentials are incorrect.',
          errors: { email: ['The provided credentials are incorrect.'] },
        }),
      }),
    );

    await page.goto('/login');
    await page.locator('#email').fill('bad@example.com');
    await page.locator('#password').fill('wrongpassword');
    await page.getByRole('button', { name: /log in/i }).click();

    await expect(page.getByText('The provided credentials are incorrect.')).toBeVisible();
  });

  test('logout button is visible when authenticated', async ({ page }) => {
    await mockAuthenticated(page);
    await page.route('**/api/v1/photos*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: {}, meta: {} }) }),
    );
    await page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );

    // Set a fake token in localStorage so the app thinks we're logged in
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-token');
    });

    await page.goto('/');
    await expect(page.getByRole('button', { name: /log out/i })).toBeVisible();
  });

  test('unauthenticated user sees login link on gallery page', async ({ page }) => {
    await mockUnauthenticated(page);
    await page.route('**/api/v1/photos*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: {}, meta: {} }) }),
    );
    await page.route('**/api/v1/albums*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/tags*', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );

    await page.goto('/');
    await expect(page.getByRole('link', { name: /log in/i })).toBeVisible();
  });
});
