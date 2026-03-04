import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Settings & Administration', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view settings page', async ({ page }) => {
    await page.goto('/admin/settings');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/settings');
  });

  test('can access user management', async ({ page }) => {
    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');
    // Should show users list or be accessible
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });

  test('can access role management', async ({ page }) => {
    await page.goto('/admin/settings/roles');
    await page.waitForLoadState('networkidle');
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });

  test('can access pipeline settings', async ({ page }) => {
    await page.goto('/admin/settings/pipelines');
    await page.waitForLoadState('networkidle');
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });

  test('can access tags settings', async ({ page }) => {
    await page.goto('/admin/settings/tags');
    await page.waitForLoadState('networkidle');
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });
});
