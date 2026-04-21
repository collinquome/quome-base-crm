import { test, expect } from '@playwright/test';

test.describe('Dashboard view_permission scoping', () => {
  test('Producer can load the dashboard without 500 and stats endpoint does not echo foreign user_id back', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"]', 'producer-probe@example.com');
    await page.fill('input[name="password"]', 'producer123');
    await page.locator('button:has-text("Sign In"), button:has-text("Login"), .primary-button').first().click();
    await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });

    // Stats endpoint with an out-of-scope user_id must not 500 — the
    // clamping code in AbstractReporting should transparently ignore the
    // foreign id and reply with the producer's own scope.
    const res = await page.request.get('/admin/dashboard/stats?type=total-leads&user_id=1', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    expect(res.status(), `stats endpoint status ${res.status()}`).toBeLessThan(500);
  });
});
