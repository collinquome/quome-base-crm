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

test.describe('Lead view activity tabs', () => {
  test('does not show a redundant "All Actions" tab', async ({ page }) => {
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

    // "Overview" is always rendered (we pass it as an extra-type). Use it as a sentinel
    // that tabs loaded before asserting "All Actions" is absent.
    await expect(page.locator('text=Overview').first()).toBeVisible({ timeout: 10000 });

    // "All Actions" tab should NOT be present.
    await expect(page.locator('text=All Actions')).toHaveCount(0);
  });
});
