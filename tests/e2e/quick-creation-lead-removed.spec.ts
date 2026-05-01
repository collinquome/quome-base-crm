import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Quick-creation speed dial', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('does not include the redundant Lead quick-create option', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // Open the speed-dial. There are two copies in the header (mobile + desktop);
    // click whichever is visible.
    const toggle = page.locator('button:has(i.icon-add)').first();
    await expect(toggle).toBeVisible({ timeout: 10000 });
    await toggle.click();

    // The dropdown should have the other quick-create entries...
    await expect(page.getByText('Quote', { exact: true }).first()).toBeVisible({ timeout: 5000 });

    // ...but no Lead create link.
    const leadCreateLink = page.locator('a[href$="/admin/leads/create"]');
    await expect(leadCreateLink).toHaveCount(0);
  });
});
