import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('New user defaults', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('create-user form defaults View Permission to Individual', async ({ page }) => {
    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');

    // Open the create-user modal.
    const createButton = page
      .locator('button:has-text("Create User"), button:has-text("Create user"), a:has-text("Create User")')
      .first();
    await expect(createButton).toBeVisible({ timeout: 10000 });
    await createButton.click();

    // Wait for the modal to render with the form fields.
    const viewPermissionSelect = page.locator('select[name="view_permission"]');
    await expect(viewPermissionSelect).toBeVisible({ timeout: 10000 });

    await expect(viewPermissionSelect).toHaveValue('individual');
  });
});
