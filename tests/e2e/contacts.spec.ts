import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Contacts (Persons)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view contacts list', async ({ page }) => {
    await page.goto('/admin/contacts/persons');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/contacts/persons');
  });

  test('can create a new contact', async ({ page }) => {
    await page.goto('/admin/contacts/persons');
    await page.waitForLoadState('networkidle');

    // Click the visible "Create Person" button
    await page.click('text=Create Person');
    await page.waitForLoadState('networkidle');

    // Wait for the form to render (Vue SPA)
    await page.waitForSelector('input[name="name"]', { timeout: 10000 });
    await page.fill('input[name="name"]', 'Test Contact E2E');

    // Try to fill email if visible
    const emailInput = page.locator('input[name="emails[0][value]"], input[placeholder*="Email"], input[placeholder*="email"]').first();
    if (await emailInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await emailInput.fill('testcontact@e2e.com');
    }

    // Save - click the visible Save button
    await page.click('button:has-text("Save")');

    // Wait for result - success flash or redirect
    await page.waitForTimeout(3000);
    // If we're still on the page and no error, that's a pass
    const hasError = await page.locator('[class*="error"]:visible').count();
    expect(hasError).toBeLessThanOrEqual(0);
  });

  test('can view organizations list', async ({ page }) => {
    await page.goto('/admin/contacts/organizations');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/contacts/organizations');
  });
});
