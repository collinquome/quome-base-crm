import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Action stream create modal + priority colors', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('New Action button opens the inline modal with entity picker', async ({ page }) => {
    await page.goto('/admin/action-stream');
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="action-stream-create-btn"]').click();

    const modal = page.locator('[data-testid="action-stream-create-modal"]');
    await expect(modal).toBeVisible({ timeout: 5000 });

    await expect(page.locator('[data-testid="action-stream-entity-search"]')).toBeVisible();

    // Save should be disabled until entity + description are filled.
    const submit = page.locator('[data-testid="action-stream-create-submit"]');
    await expect(submit).toBeDisabled();
  });

  test('priority select has a colored left border reflecting choice', async ({ page }) => {
    await page.goto('/admin/action-stream');
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="action-stream-create-btn"]').click();

    const prioritySelect = page.locator('[data-testid="action-stream-create-modal"] select').nth(1);
    await expect(prioritySelect).toBeVisible();

    await prioritySelect.selectOption('urgent');
    const urgentBorder = await prioritySelect.evaluate((el) => window.getComputedStyle(el).borderLeftColor);
    expect(urgentBorder).toBe('rgb(239, 68, 68)'); // #ef4444

    await prioritySelect.selectOption('low');
    const lowBorder = await prioritySelect.evaluate((el) => window.getComputedStyle(el).borderLeftColor);
    expect(lowBorder).toBe('rgb(156, 163, 175)'); // #9ca3af
  });
});
