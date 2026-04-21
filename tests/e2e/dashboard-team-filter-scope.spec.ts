import { test, expect } from '@playwright/test';

test.describe('Dashboard team-member filter scoping', () => {
  test('Producer only sees themselves in the team-members list', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"]', 'producer-probe@example.com');
    await page.fill('input[name="password"]', 'producer123');
    await page.locator('button:has-text("Sign In"), button:has-text("Login"), .primary-button').first().click();
    await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });

    // Hit the members endpoint the dashboard dropdown uses.
    const res = await page.request.get('/admin/team-stream/members', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();

    // Individual users: scoped flag true, member list contains only themselves.
    expect(body.scoped).toBe(true);
    expect(body.data.length).toBe(1);
    expect(body.data[0].email).toBe('producer-probe@example.com');
  });

  test('Admin sees every team member and is not scoped', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'admin123');
    await page.locator('button:has-text("Sign In"), button:has-text("Login"), .primary-button').first().click();
    await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });

    const res = await page.request.get('/admin/team-stream/members', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.scoped).toBe(false);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
  });
});
