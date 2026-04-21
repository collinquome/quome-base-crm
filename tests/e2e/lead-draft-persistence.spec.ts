import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Lead create — draft persistence + sticky save', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
    // Clear any leftover draft from prior runs.
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => localStorage.removeItem('crm-lead-draft-v1'));
  });

  test('header with Save button is sticky', async ({ page }) => {
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    const saveBtn = page.locator('button.primary-button', { hasText: 'Save' }).first();
    await expect(saveBtn).toBeVisible({ timeout: 10000 });

    // The header wrapper on the create form carries the sticky class + top offset.
    const header = saveBtn.locator('xpath=ancestor::div[contains(@class, "sticky")]').first();
    const styles = await header.evaluate((el) => {
      const cs = window.getComputedStyle(el);
      return { position: cs.position, top: cs.top };
    });
    expect(styles.position).toBe('sticky');
    expect(styles.top).not.toBe('auto');
  });

  test('typing in the Title field persists across reload', async ({ page }) => {
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    const titleInput = page.locator('input[name="title"]').first();
    await expect(titleInput).toBeVisible({ timeout: 10000 });

    const draftValue = `Draft lead ${Date.now()}`;
    await titleInput.fill(draftValue);

    // Wait past the debounce window so localStorage writes.
    await page.waitForTimeout(600);

    // Confirm localStorage has the draft.
    const saved = await page.evaluate(() => localStorage.getItem('crm-lead-draft-v1'));
    expect(saved).toBeTruthy();
    expect(saved!).toContain(draftValue);

    // Reload — after restoration, the title should still be the draft value and the indicator should appear.
    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="title"]').first()).toHaveValue(draftValue, { timeout: 10000 });
    await expect(page.locator('[data-testid="lead-draft-indicator"]')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('[data-testid="lead-draft-clear-btn"]')).toBeVisible();
  });

  test('Discard draft button clears localStorage', async ({ page }) => {
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    await page.locator('input[name="title"]').first().fill('About to discard');
    await page.waitForTimeout(600);

    // Confirm draft saved.
    expect(await page.evaluate(() => localStorage.getItem('crm-lead-draft-v1'))).toBeTruthy();

    // Intercept confirm() before clicking.
    page.on('dialog', (d) => d.accept());
    await page.locator('[data-testid="lead-draft-clear-btn"]').click();
    await page.waitForLoadState('networkidle');

    expect(await page.evaluate(() => localStorage.getItem('crm-lead-draft-v1'))).toBeFalsy();
    await expect(page.locator('input[name="title"]').first()).toHaveValue('');
  });
});
