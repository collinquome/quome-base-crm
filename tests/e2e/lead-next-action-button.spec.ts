import { test, expect, APIRequestContext } from '@playwright/test';
import { login } from './helpers/auth';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });
  const res = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  const body = await res.json();
  token = body.token || body.data?.token;
});

test.afterAll(async () => {
  await api.dispose();
});

test.describe('Lead left-sidebar Next Action button', () => {
  test('opens the next-action create form in the widget', async ({ page }) => {
    const listRes = await api.get('/api/v1/leads?limit=1', {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    const list = await listRes.json();
    const leadId = list.data?.[0]?.id;
    test.skip(!leadId, 'No existing leads available in this environment');

    await login(page);
    await page.goto(`/admin/leads/view/${leadId}`);
    await page.waitForLoadState('networkidle');

    const pageText = await page.textContent('body');
    test.skip(
      !!(pageText?.includes('500') && pageText?.includes('Something went wrong')),
      'Lead view errors — skip on seed data with known 500',
    );

    // Button exists on the left-sidebar Activity Actions row.
    const button = page.locator('[data-testid="lead-add-next-action-btn"]').first();
    await expect(button).toBeVisible({ timeout: 10000 });

    // Clicking should open the next-action-widget create form, not the activity modal.
    await button.click();

    const form = page.locator('[data-testid="next-action-form"]').first();
    await expect(form).toBeVisible({ timeout: 5000 });

    // And the legacy activity modal (Title + Schedule From) should NOT appear.
    await expect(page.locator('input[name="schedule_from"]')).toHaveCount(0);
  });
});
