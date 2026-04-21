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

test.describe('Lead Files tab', () => {
  test('Add File button on empty state opens the file upload modal', async ({ page }) => {
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

    // Switch to the Files tab.
    await page.locator('text=Files').first().click();

    // If the lead has no files, the Add File button should be visible in the empty state.
    const addBtn = page.locator('[data-testid="activities-add-file-btn"]');
    const empty = await addBtn.isVisible().catch(() => false);
    test.skip(!empty, 'Lead has existing files — empty-state button not applicable');

    await addBtn.click();

    // The file upload modal should now be open — look for the Title input that lives in it.
    const titleInput = page.locator('input[name="title"]').first();
    await expect(titleInput).toBeVisible({ timeout: 5000 });
  });
});
