import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, login } from './helpers/auth';

test.describe('Dashboard Action Stream respects user-picker', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  });

  test('passes user_id to /admin/action-stream/stream when picker changes', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // The producer-probe user is seeded in this env (see CRM seeders).
    // Find their id via the team-stream/members endpoint, then trigger the
    // shared event bus the user-picker emits and confirm the action stream
    // re-fetches with the right user_id.
    const targetId = await page.evaluate(async () => {
      const res = await fetch('/admin/team-stream/members', { credentials: 'include' });
      const json = await res.json();
      const probe = (json?.data || []).find((u: any) =>
        u.email === 'producer-probe@example.com'
      );
      return probe?.id ?? null;
    });

    if (!targetId) test.skip(true, 'producer-probe user not present in this env');

    const followupRequest = page.waitForRequest(
      (req) =>
        req.method() === 'GET' &&
        req.url().includes('/admin/action-stream/stream') &&
        req.url().includes(`user_id=${targetId}`),
      { timeout: 8000 }
    );

    // Emit on the same Vue $emitter the dashboard filter component uses.
    await page.evaluate((uid) => {
      // @ts-ignore Vue app is exposed globally as `app` in this codebase
      const emitter = window.app?.config?.globalProperties?.$emitter;
      emitter?.emit('reporting-filter-updated', { user_id: uid });
    }, targetId);

    const req = await followupRequest;
    expect(req.url()).toContain(`user_id=${targetId}`);
  });

  test('omits user_id param when no user is selected (default behavior preserved)', async ({ page }) => {
    const initialFetch = page.waitForRequest(
      (req) =>
        req.method() === 'GET' &&
        req.url().includes('/admin/action-stream/stream') &&
        req.url().includes('per_page=10'),
      { timeout: 10000 }
    );
    await page.goto('/admin/dashboard');
    const req = await initialFetch;
    expect(req.url()).not.toContain('user_id=');
  });
});
