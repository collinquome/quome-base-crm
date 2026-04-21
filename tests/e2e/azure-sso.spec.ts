import { test, expect } from '@playwright/test';

test.describe('Azure SSO', () => {
  test('login page loads without SSO button when AZURE_SSO_ENABLED is not set', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Login form should be visible
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    await expect(emailInput).toBeVisible({ timeout: 10000 });

    // SSO button should NOT be visible (not configured in local env)
    const ssoBtn = page.locator('[data-testid="azure-sso-btn"]');
    const ssoVisible = await ssoBtn.isVisible().catch(() => false);

    // In local dev without AZURE_SSO_ENABLED, button should not show
    // This test validates the feature flag works
    expect(ssoVisible).toBe(false);
  });

  test('login page still works normally without SSO', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Standard login form elements should be present
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    const submitButton = page.locator('button[type="submit"], button:has-text("Sign In")').first();

    await expect(emailInput).toBeVisible({ timeout: 10000 });
    await expect(passwordInput).toBeVisible();
    await expect(submitButton).toBeVisible();
  });

  test('azure redirect route exists and redirects to login when not configured', async ({ page }) => {
    // Hitting the redirect endpoint without config should redirect back to login
    const response = await page.goto('/admin/auth/azure/redirect');

    // Should redirect to login page with an error
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/admin/login');
  });

  test('azure callback route exists and redirects to login when not configured', async ({ page }) => {
    // Hitting the callback endpoint without config should redirect back to login
    await page.goto('/admin/auth/azure/callback');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('/admin/login');
  });

  test('standard email/password login still works', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();

    await emailInput.fill('admin@example.com');
    await passwordInput.fill('admin123');

    const submitButton = page.locator('button[type="submit"], button:has-text("Sign In")').first();
    await submitButton.click();

    // Should redirect away from login
    await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });
    expect(page.url()).not.toContain('/admin/login');
  });
});
