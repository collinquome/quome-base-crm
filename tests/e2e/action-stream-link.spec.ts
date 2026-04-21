import { test, expect, APIRequestContext } from '@playwright/test';
import { login } from './helpers/auth';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

function authHeaders() {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}

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

test.describe('Action Stream row links', () => {
  test('clicking an action row navigates to the linked lead', async ({ page }) => {
    const stamp = Date.now();

    const listRes = await api.get('/api/v1/leads?limit=1', { headers: authHeaders() });
    const list = await listRes.json();
    const leadId = list.data?.[0]?.id;
    test.skip(!leadId, 'No existing leads available in this environment');

    const description = `Click me to jump ${stamp}`;
    const actionRes = await api.post('/api/v1/action-stream', {
      headers: authHeaders(),
      data: {
        actionable_type: 'leads',
        actionable_id: leadId,
        action_type: 'call',
        description,
        priority: 'urgent',
      },
    });
    expect(actionRes.ok() || actionRes.status() === 201).toBeTruthy();

    await login(page);
    await page.goto('/admin/action-stream');
    await page.waitForLoadState('networkidle');

    const row = page.locator('[data-testid="action-stream-item"]', { hasText: description }).first();
    await expect(row).toBeVisible({ timeout: 10000 });

    const link = row.locator('[data-testid="action-stream-item-link"]');
    await expect(link).toHaveAttribute('href', `/admin/leads/view/${leadId}`);

    await link.click();
    await page.waitForURL(new RegExp(`/admin/leads/view/${leadId}$`), { timeout: 10000 });
    expect(page.url()).toContain(`/admin/leads/view/${leadId}`);
  });
});
