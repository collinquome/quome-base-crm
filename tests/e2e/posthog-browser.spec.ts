import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('PostHog browser instrumentation', () => {
  test('anonymous login page renders posthog meta tags', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Token is rendered as a meta tag (blank allowed — plugin no-ops).
    const token = await page.locator('meta[name="posthog-token"]').getAttribute('content');
    const host = await page.locator('meta[name="posthog-host"]').getAttribute('content');
    const disabled = await page.locator('meta[name="posthog-disabled"]').getAttribute('content');

    expect(token).not.toBeNull();
    expect(host).toContain('posthog.com');
    expect(['true', 'false']).toContain(disabled);
  });

  test('authenticated page carries user-id meta tag', async ({ page }) => {
    await login(page);
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const userId = await page.locator('meta[name="posthog-user-id"]').getAttribute('content');
    expect(userId).toMatch(/^\d+$/);

    const email = await page.locator('meta[name="posthog-user-email"]').getAttribute('content');
    expect(email).toContain('@');
  });

  test('posthog-disabled=true prevents window.posthog from initialising', async ({ page }) => {
    // Local .env has POSTHOG_DISABLED=true, so the plugin should bail out
    // and never attach window.posthog.
    await login(page);
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const disabled = await page.locator('meta[name="posthog-disabled"]').getAttribute('content');
    const hasPosthog = await page.evaluate(() => typeof (window as any).posthog !== 'undefined');

    if (disabled === 'true') {
      expect(hasPosthog).toBe(false);
    } else {
      expect(hasPosthog).toBe(true);
    }
  });
});
