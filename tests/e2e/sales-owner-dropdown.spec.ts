import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, login } from './helpers/auth';

const PRODUCER_EMAIL = 'producer-probe@example.com';
const PRODUCER_PASSWORD = 'producer123';

async function getSalesOwnerOptionCount(page: import('@playwright/test').Page): Promise<number> {
  await page.goto('/admin/leads/view/3');
  await page.waitForLoadState('networkidle');

  const row = page.locator('div.label', { hasText: 'Sales Owner' }).first()
    .locator('xpath=ancestor::div[1]');
  if ((await row.count()) === 0) return -1;

  await row.hover();
  const editIcon = row.locator('i.icon-edit').first();
  await editIcon.click({ force: true });

  const select = row.locator('select').first();
  await expect(select).toBeVisible({ timeout: 5000 });

  return await select.locator('option').count();
}

test.describe('Sales Owner reassignment dropdown', () => {
  test('Administrator sees the full user list (regression: was collapsing to self)', async ({ page }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    const count = await getSalesOwnerOptionCount(page);
    if (count < 0) test.skip(true, 'Sales Owner row not present in this env');
    expect(count, 'admin should see more than just themselves').toBeGreaterThan(1);
  });

  test('Producer (individual view) still sees only themselves — scoping preserved', async ({ page }) => {
    try {
      await login(page, PRODUCER_EMAIL, PRODUCER_PASSWORD);
    } catch {
      test.skip(true, 'producer-probe@example.com login not available');
    }
    const count = await getSalesOwnerOptionCount(page);
    if (count < 0) test.skip(true, 'Sales Owner row not present for producer');
    expect(count).toBe(1);
  });
});
