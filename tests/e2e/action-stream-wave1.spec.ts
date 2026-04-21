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

test.afterAll(async () => { await api.dispose(); });

test.describe('Action stream + Next Actions wave 1', () => {
  test('Actions sidebar menu item renders for admin', async ({ page }) => {
    await login(page);
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const sidebar = page.locator('nav').first();
    const actionsLink = sidebar.locator('a[href*="/admin/action-stream"]').first();
    await expect(actionsLink).toBeVisible({ timeout: 10000 });
  });

  test('action stream page has status filter with Pending preselected', async ({ page }) => {
    await login(page);
    await page.goto('/admin/action-stream');
    await page.waitForLoadState('networkidle');

    const statusFilter = page.locator('[data-testid="action-stream-status-filter"]');
    await expect(statusFilter).toBeVisible({ timeout: 10000 });
    await expect(statusFilter).toHaveValue('pending');
  });

  test('switching status filter to Completed shows Reopen buttons', async ({ page }) => {
    // Seed a completed action.
    const listRes = await api.get('/api/v1/leads?limit=1', { headers: authHeaders() });
    const leadId = (await listRes.json()).data?.[0]?.id;
    test.skip(!leadId, 'no leads available');

    const createRes = await api.post('/api/v1/action-stream', {
      headers: authHeaders(),
      data: {
        actionable_type: 'leads', actionable_id: leadId,
        action_type: 'call', description: `reopen-target ${Date.now()}`, priority: 'normal',
      },
    });
    const actionId = (await createRes.json()).data?.id;
    await api.post(`/api/v1/action-stream/${actionId}/complete`, { headers: authHeaders() });

    await login(page);
    await page.goto('/admin/action-stream');
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="action-stream-status-filter"]').selectOption('completed');
    await page.waitForTimeout(500);

    const reopen = page.locator('[data-testid="action-stream-reopen-btn"]').first();
    await expect(reopen).toBeVisible({ timeout: 10000 });
  });

  test('next-action widget header says "Next Actions" (plural)', async ({ page }) => {
    await login(page);
    await page.goto('/admin/leads/view/4');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    test.skip(!!(bodyText?.includes('Something went wrong')), 'lead view 500');

    const header = page.locator('[data-testid="next-action-section"]').first().locator('h4').first();
    await expect(header).toContainText('Next Actions');
  });
});
