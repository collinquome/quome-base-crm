import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('User invite flow (magic link)', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('backend returns a magic link and the reset page accepts it', async ({ page, context }) => {
    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');

    const stamp = Date.now();
    const body = await page.evaluate(async (stamp) => {
      const fd = new FormData();
      fd.append('name', `Invite Probe ${stamp}`);
      fd.append('email', `probe-${stamp}@example.com`);
      fd.append('role_id', '1');
      fd.append('view_permission', 'individual');
      fd.append('status', '1');
      fd.append('invite', '1');
      const token = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
      const res = await fetch('/admin/settings/users/create', {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
        },
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    }, stamp);

    expect(body.status).toBe(200);
    expect(body.body.invite_link).toMatch(/\/admin\/reset-password\/[a-f0-9]{40,}\?email=/);

    // Open the magic link in a clean browser context (no admin session).
    const anonCtx = await context.browser()!.newContext();
    const anonPage = await anonCtx.newPage();
    await anonPage.goto(body.body.invite_link);
    await anonPage.waitForLoadState('networkidle');

    const pageText = await anonPage.textContent('body');
    expect(pageText?.toLowerCase()).toContain('password');

    await anonCtx.close();
  });

  test('invite toggle hides password fields on the create form', async ({ page }) => {
    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');

    await page.locator('button:has-text("Create User"), a:has-text("Create User")').first().click();

    const password = page.locator('input[name="password"]').first();
    await expect(password).toBeVisible({ timeout: 5000 });

    await page.locator('[data-testid="user-invite-toggle"]').check();
    await expect(password).toBeHidden({ timeout: 3000 });

    await page.locator('[data-testid="user-invite-toggle"]').uncheck();
    await expect(password).toBeVisible({ timeout: 3000 });
  });

  test('re-invite endpoint generates a fresh link for an existing user', async ({ page }) => {
    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');

    const body = await page.evaluate(async () => {
      const token = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
      const res = await fetch('/admin/settings/users/1/invite', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
        },
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(body.status).toBe(200);
    expect(body.body.invite_link).toMatch(/\/admin\/reset-password\/[a-f0-9]{40,}\?email=/);
  });
});
