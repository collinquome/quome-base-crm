import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Products fixes', () => {
  test('product edit page loads without 500 after schema repair', async ({ page }) => {
    await login(page);

    // Find any seeded SKU ID via the products datagrid search.
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');

    // Use the first seeded product ID (110 is the last MOPRO SKU from the seed).
    await page.goto('/admin/products/edit/110');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Something went wrong');
    expect(bodyText).not.toContain('internal-server-error');
  });

  test('product inline search returns results for lowercase query', async ({ page }) => {
    await login(page);

    // The products search endpoint honors ?query=, case-insensitive via MySQL ci collation.
    const response = await page.request.get('/admin/products/search?query=auto', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    const items = body.data || [];

    expect(items.length, 'search should return at least one match for lowercase "auto"').toBeGreaterThan(0);
    // All seeded SKUs are uppercase, so at least one name should contain AUTO.
    const hasAuto = items.some((p: any) => (p.name || '').toUpperCase().includes('AUTO'));
    expect(hasAuto, 'at least one returned product should have AUTO in the name').toBeTruthy();
  });

  test('product inline search is case-insensitive (same count for AUTO vs auto)', async ({ page }) => {
    await login(page);

    const upper = await page.request.get('/admin/products/search?query=AUTO', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    const lower = await page.request.get('/admin/products/search?query=auto', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });

    const upperBody = await upper.json();
    const lowerBody = await lower.json();

    expect((upperBody.data || []).length).toBe((lowerBody.data || []).length);
  });
});
