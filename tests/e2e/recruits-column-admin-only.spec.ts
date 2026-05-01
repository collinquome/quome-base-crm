import { test, expect, request as pwRequest } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, BASE_URL, login } from './helpers/auth';

const PRODUCER_EMAIL = 'producer-probe@example.com';
const PRODUCER_PASSWORD = 'producer123';

async function fetchKanbanStages(page: import('@playwright/test').Page): Promise<string[]> {
  const json = await page.evaluate(async () => {
    const res = await fetch('/admin/leads/get', {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });
    return res.json();
  });
  // get() returns an object keyed by sort_order with stage objects
  return Object.values(json as Record<string, { code?: string }>).map((s) => s.code || '');
}

test.describe('Recruits kanban column visibility', () => {
  test('Administrator sees the Recruits stage', async ({ page }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    const codes = await fetchKanbanStages(page);
    expect(codes, `admin should see recruits, got ${JSON.stringify(codes)}`).toContain('recruits');
  });

  test('Producer does NOT see the Recruits stage', async ({ page }) => {
    // Best-effort: if the producer login fails (no such user in this env), skip.
    try {
      await login(page, PRODUCER_EMAIL, PRODUCER_PASSWORD);
    } catch {
      test.skip(true, 'producer-probe@example.com not provisioned with known password in this env');
    }
    const codes = await fetchKanbanStages(page);
    expect(codes, `producer should not see recruits, got ${JSON.stringify(codes)}`).not.toContain('recruits');
  });
});
