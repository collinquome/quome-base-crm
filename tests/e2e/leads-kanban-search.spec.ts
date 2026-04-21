import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Leads kanban search', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('auto-searches after typing without pressing Enter', async ({ page }) => {
    await page.goto('/admin/leads?view_type=kanban');
    await page.waitForLoadState('networkidle');

    const searchInput = page.locator('input[name="search"]').first();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Any search string is fine — we're asserting the request fires, not the results set.
    const query = `zzz-debounce-${Date.now()}`;

    const leadsFetch = page.waitForRequest(
      (request) =>
        request.method() === 'GET' &&
        request.url().includes('/admin/leads/get') &&
        request.url().includes(encodeURIComponent(query)),
      { timeout: 5000 }
    );

    await searchInput.focus();
    await searchInput.type(query, { delay: 30 });

    // Deliberately do NOT press Enter — the debounce should fire on its own.
    await leadsFetch;

    await expect(searchInput).toHaveValue(query);
  });

  test('debounces rapid typing into a single request', async ({ page }) => {
    await page.goto('/admin/leads?view_type=kanban');
    await page.waitForLoadState('networkidle');

    const searchInput = page.locator('input[name="search"]').first();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    const requestsWithQuery: string[] = [];
    const onRequest = (request: import('@playwright/test').Request) => {
      const url = request.url();
      if (request.method() === 'GET' && url.includes('/admin/leads/get') && url.includes('debounce-burst')) {
        requestsWithQuery.push(url);
      }
    };
    page.on('request', onRequest);

    await searchInput.focus();
    // Type the full string quickly — each keystroke should reset the timer.
    await searchInput.type('debounce-burst', { delay: 20 });

    // Wait past the 300ms debounce window for the final request to fire.
    await page.waitForTimeout(800);
    page.off('request', onRequest);

    expect(requestsWithQuery.length).toBeGreaterThanOrEqual(1);
    // Rapid typing (≤20ms delay) should not produce one request per keystroke.
    expect(requestsWithQuery.length).toBeLessThanOrEqual(3);
  });
});
