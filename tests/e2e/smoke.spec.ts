import { test, expect } from '@playwright/test';
import { login, ADMIN_EMAIL } from './helpers/auth';

test.describe('Smoke Tests', () => {
  test('login page loads', async ({ page }) => {
    await page.goto('/admin/login');
    await expect(page).toHaveTitle(/Sign In|Login|Quome/i);
  });

  test('can login with admin credentials', async ({ page }) => {
    await login(page);
    // Should be redirected away from login
    expect(page.url()).not.toContain('/login');
  });

  test('dashboard loads after login', async ({ page }) => {
    await login(page);
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    // Dashboard should have some content
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });

  test('contacts page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/contacts/persons');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/contacts/persons');
  });

  test('leads page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/leads');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/leads');
  });

  test('activities page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/activities');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/activities');
  });

  test('mail page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/mail');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/mail');
  });

  test('products page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/products');
  });

  test('settings page loads', async ({ page }) => {
    await login(page);
    await page.goto('/admin/settings');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/settings');
  });
});
