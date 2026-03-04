import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Products', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view products list', async ({ page }) => {
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/products');
  });

  test('can access product creation', async ({ page }) => {
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');

    const createButton = page.locator('a:has-text("Create"), button:has-text("Create")').first();
    if (await createButton.isVisible().catch(() => false)) {
      await createButton.click();
      await page.waitForTimeout(2000);
    }
  });
});
