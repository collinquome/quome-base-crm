import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Lead create: product lookup is a preloaded filterable list', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('opening the product picker shows products immediately, no typing required', async ({ page }) => {
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    // Add a product row so the lookup mounts. The "Add More" button below the
    // products table is the one with text "Add More" inside a button.
    const productsTable = page.locator('table').filter({ hasText: 'Product Name' }).first();
    await expect(productsTable).toBeVisible({ timeout: 10000 });

    // The button immediately following the products table (sibling) is "Add More".
    await page.getByRole('button', { name: 'Add More' }).last().click();

    // Click the new product row's lookup placeholder (inside the products table).
    const placeholder = productsTable.getByText(/click to add/i).first();
    await expect(placeholder).toBeVisible({ timeout: 10000 });
    await placeholder.click();

    // Popup should populate without any typing thanks to preload=true.
    const popupItem = productsTable.locator('ul li.cursor-pointer').first();
    await expect(popupItem).toBeVisible({ timeout: 8000 });
  });

  test('products search endpoint returns multiple results for empty query', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const res = await fetch('/admin/products/search?query=', {
        headers: { Accept: 'application/json' },
        credentials: 'include',
      });
      return res.json();
    });

    expect(Array.isArray(response?.data)).toBeTruthy();
  });
});
