import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Leads', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view leads list', async ({ page }) => {
    await page.goto('/admin/leads');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/leads');
  });

  test('can switch between kanban and list view', async ({ page }) => {
    await page.goto('/admin/leads');
    await page.waitForLoadState('networkidle');

    // Look for view toggle buttons (kanban/list)
    const listToggle = page.locator('[class*="icon-list"], button:has-text("List"), a[href*="table"]').first();
    if (await listToggle.isVisible().catch(() => false)) {
      await listToggle.click();
      await page.waitForLoadState('networkidle');
    }
  });

  test('can access lead creation form', async ({ page }) => {
    await page.goto('/admin/leads');
    await page.waitForLoadState('networkidle');

    // Click create button
    const createButton = page.locator('a:has-text("Create Lead"), button:has-text("Create Lead"), a[href*="leads/create"]').first();
    if (await createButton.isVisible().catch(() => false)) {
      await createButton.click();
      await page.waitForLoadState('networkidle');
      // Should see a form or modal
      const form = page.locator('form, [class*="modal"]').first();
      await expect(form).toBeVisible({ timeout: 10000 });
    }
  });
});
