import { Page, expect } from '@playwright/test';

export const ADMIN_EMAIL = 'admin@example.com';
export const ADMIN_PASSWORD = 'admin123';
export const BASE_URL = process.env.CRM_URL || 'http://localhost:8190';

export async function login(page: Page, email = ADMIN_EMAIL, password = ADMIN_PASSWORD) {
  await page.goto('/admin/login');

  // Wait for Vue.js to render the login form
  await page.waitForSelector('input[type="email"], input[name="email"]', { timeout: 15000 });

  // Fill in credentials
  const emailInput = page.locator('input[type="email"], input[name="email"]').first();
  const passwordInput = page.locator('input[type="password"], input[name="password"]').first();

  await emailInput.fill(email);
  await passwordInput.fill(password);

  // Click submit — try multiple selectors since it's a Vue SPA
  const submitButton = page.locator(
    'button[type="submit"], button:has-text("Sign In"), button:has-text("Login"), button:has-text("Submit")'
  ).first();
  await submitButton.click();

  // Wait for navigation away from login page
  await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });
}

export async function ensureLoggedIn(page: Page) {
  const url = page.url();
  if (url.includes('/admin/login') || !url.includes('/admin/')) {
    await login(page);
  }
}

export async function navigateTo(page: Page, path: string) {
  await ensureLoggedIn(page);
  await page.goto(`/admin/${path}`);
  await page.waitForLoadState('networkidle');
}
