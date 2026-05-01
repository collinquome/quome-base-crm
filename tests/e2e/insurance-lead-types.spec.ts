import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Insurance lead types', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('lead type dropdown on create form lists all four insurance types', async ({ page }) => {
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    const select = page.locator('select[name="lead_type_id"]').first();
    await expect(select).toBeVisible({ timeout: 10000 });

    const optionTexts = await select.locator('option').allTextContents();
    const normalized = optionTexts.map((t) => t.trim());

    for (const name of ['Personal', 'Commercial', 'Cross-sell', 'Life/Health']) {
      expect(normalized, `expected "${name}" in lead type dropdown`).toContain(name);
    }
  });
});
