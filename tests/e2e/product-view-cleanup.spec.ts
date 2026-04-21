import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Product view page cleanup', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Inventory tab is not rendered', async ({ page }) => {
    await page.goto('/admin/products/view/110');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Something went wrong');

    // The inventory tab label is "Inventories" (translated string). Assert the activity tabs
    // region does NOT contain it. Other tabs (All / Notes / Files / Change log) should still show.
    await expect(page.locator('text=Files').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Inventories')).toHaveCount(0);
  });

  test('admin still sees edit pencils on product attributes (has products.edit)', async ({ page }) => {
    // As admin (role = all), allow-edit evaluates true. Pencils should exist next to attribute rows.
    await page.goto('/admin/products/view/110');
    await page.waitForLoadState('networkidle');

    const pencils = page.locator('.icon-edit');
    // There should be at least one pencil somewhere on the page for admin.
    expect(await pencils.count()).toBeGreaterThan(0);
  });
});
