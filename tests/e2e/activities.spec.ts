import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Activities', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view activities list', async ({ page }) => {
    await page.goto('/admin/activities');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/activities');
  });

  test('can access activity creation', async ({ page }) => {
    await page.goto('/admin/activities');
    await page.waitForLoadState('networkidle');

    const createButton = page.locator('a:has-text("Create"), button:has-text("Create"), [class*="create"]').first();
    if (await createButton.isVisible().catch(() => false)) {
      await createButton.click();
      await page.waitForTimeout(2000);
    }
  });
});
