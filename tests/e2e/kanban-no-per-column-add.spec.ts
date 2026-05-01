import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Kanban stage headers', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('stage column headers no longer have a per-column "+" add button', async ({ page }) => {
    // Wait for the kanban data fetch so stages are guaranteed rendered.
    const kanbanFetch = page.waitForResponse(
      (r) => r.url().includes('/admin/leads/get') && r.request().method() === 'GET',
      { timeout: 15000 }
    );
    await page.goto('/admin/leads?view_type=kanban');
    await kanbanFetch;
    await page.waitForLoadState('networkidle');

    // The removed buttons were anchors with class "icon-add" pointing at
    // /admin/leads/create?stage_id=... — there should be none anywhere on the page.
    const perColumnAddButtons = page.locator('a.icon-add[href*="/admin/leads/create"][href*="stage_id="]');
    await expect(perColumnAddButtons).toHaveCount(0);
  });
});
