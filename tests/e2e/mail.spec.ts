import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Mail', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can view mail inbox', async ({ page }) => {
    await page.goto('/admin/mail');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/mail');
  });

  test('can navigate to sent mail', async ({ page }) => {
    await page.goto('/admin/mail/sent');
    await page.waitForLoadState('networkidle');
    // Page should load without error
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });

  test('can navigate to drafts', async ({ page }) => {
    await page.goto('/admin/mail/draft');
    await page.waitForLoadState('networkidle');
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
  });
});
