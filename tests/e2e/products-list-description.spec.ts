import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Products list page', () => {
  test('shows description column and a known value', async ({ page }) => {
    await login(page);
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Something went wrong');

    // Column header.
    await expect(page.locator('text=Description').first()).toBeVisible({ timeout: 10000 });

    // At least one of our seeded descriptions should appear on the page. Use the search box to narrow to AUTOP.
    const searchInput = page.locator('input[name="search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill('AUTOP');
      await searchInput.press('Enter');
      await page.waitForLoadState('networkidle');
      await expect(page.locator('text=Personal Auto').first()).toBeVisible({ timeout: 10000 });
    }
  });

  test('/admin/products/110/activities does not 500 after schema repair', async ({ page }) => {
    await login(page);

    const response = await page.request.get('/admin/products/110/activities', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    expect(response.status(), 'activities endpoint should not 500').toBeLessThan(500);
  });
});
