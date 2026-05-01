import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, login } from './helpers/auth';

const PRODUCER_EMAIL = 'producer-probe@example.com';
const PRODUCER_PASSWORD = 'producer123';

test.describe('Calendar user-picker', () => {
  test('Administrator sees the picker and selecting a user passes user_id to /activities/get', async ({ page }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await page.goto('/admin/activities?view-type=calendar');
    await page.waitForLoadState('networkidle');

    const picker = page.getByTestId('calendar-user-picker-select');
    await expect(picker).toBeVisible({ timeout: 10000 });

    const targetId = await page.evaluate(async () => {
      const res = await fetch('/admin/team-stream/members', { credentials: 'include' });
      const json = await res.json();
      const probe = (json?.data || []).find((u: any) => u.email === 'producer-probe@example.com');
      return probe?.id ? String(probe.id) : null;
    });
    if (!targetId) test.skip(true, 'producer-probe not present');

    const followup = page.waitForRequest(
      (req) =>
        req.method() === 'GET' &&
        req.url().includes('/admin/activities/get') &&
        req.url().includes(`user_id=${targetId}`),
      { timeout: 8000 }
    );

    await picker.selectOption(targetId);
    const req = await followup;
    expect(req.url()).toContain(`user_id=${targetId}`);
  });

  test('Producer (scoped) does NOT see the picker', async ({ page }) => {
    try {
      await login(page, PRODUCER_EMAIL, PRODUCER_PASSWORD);
    } catch {
      test.skip(true, 'producer-probe login not available');
    }
    await page.goto('/admin/activities?view-type=calendar');
    await page.waitForLoadState('networkidle');

    // Either the picker is absent, or it's hidden because users.length <= 1.
    const picker = page.getByTestId('calendar-user-picker');
    await expect(picker).toHaveCount(0);
  });
});
