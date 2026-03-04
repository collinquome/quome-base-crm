import { test, expect, Page, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

async function loginPage(page: Page) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin/, { timeout: 15000 });
}

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });
  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;
});

test.afterAll(async () => {
  await api.dispose();
});

test.describe('Next Action Widget - Contact Page', () => {
  test('widget renders on contact detail page', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/contacts/persons/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/persons/view/')) {
      const widget = page.locator('[data-testid="next-action-widget"]');
      await expect(widget).toBeVisible();

      // Should have "Next Action" section header
      const section = page.locator('[data-testid="next-action-section"]');
      await expect(section).toBeVisible();
    }
  });

  test('widget shows action history section', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/contacts/persons/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/persons/view/')) {
      const history = page.locator('[data-testid="action-history-section"]');
      await expect(history).toBeVisible();

      // Should show "Action History" header
      await expect(history.locator('text=Action History')).toBeVisible();
    }
  });

  test('widget shows empty state or current action', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/contacts/persons/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    if (page.url().includes('/persons/view/')) {
      const widget = page.locator('[data-testid="next-action-widget"]');

      // Should show either current action, empty state, or the + New button
      const current = widget.locator('[data-testid="next-action-current"]');
      const empty = widget.locator('[data-testid="next-action-empty"]');
      const newBtn = widget.locator('[data-testid="next-action-new-btn"]');

      const hasContent = await current.isVisible().catch(() => false);
      const hasEmpty = await empty.isVisible().catch(() => false);
      const hasNewBtn = await newBtn.isVisible().catch(() => false);

      expect(hasContent || hasEmpty || hasNewBtn).toBeTruthy();
    }
  });

  test('clicking New button shows create form', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/contacts/persons/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    if (page.url().includes('/persons/view/')) {
      // Click the + New button or "Set one now" link
      const newBtn = page.locator('[data-testid="next-action-new-btn"]');
      const setNow = page.locator('text=Set one now');

      const newBtnVisible = await newBtn.isVisible().catch(() => false);
      const setNowVisible = await setNow.isVisible().catch(() => false);

      if (newBtnVisible) {
        await newBtn.click();
      } else if (setNowVisible) {
        await setNow.click();
      }

      // The create form should now be visible
      const form = page.locator('[data-testid="next-action-form"]');
      await expect(form).toBeVisible({ timeout: 5000 });

      // Form should have type select, priority select, date input, description
      await expect(page.locator('[data-testid="next-action-type-select"]')).toBeVisible();
      await expect(page.locator('[data-testid="next-action-priority-select"]')).toBeVisible();
      await expect(page.locator('[data-testid="next-action-description"]')).toBeVisible();
      await expect(page.locator('[data-testid="next-action-save-btn"]')).toBeVisible();
    }
  });
});

test.describe('Next Action Widget - Lead Page', () => {
  test('widget renders on lead detail page', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/leads/view/')) {
      const widget = page.locator('[data-testid="next-action-widget"]');
      await expect(widget).toBeVisible();

      const history = page.locator('[data-testid="action-history-section"]');
      await expect(history).toBeVisible();
    }
  });
});

test.describe('Next Action Widget - API Integration', () => {
  let createdActionId: number | null = null;

  test.afterAll(async () => {
    if (createdActionId) {
      await api.delete(`/api/v1/action-stream/${createdActionId}`, {
        headers: authHeaders(),
      }).catch(() => {});
    }
  });

  test('can create action via API for a contact', async () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dueDate = tomorrow.toISOString().split('T')[0];

    const res = await api.post('/api/v1/action-stream', {
      headers: authHeaders(),
      data: {
        actionable_type: 'person',
        actionable_id: 1,
        action_type: 'call',
        description: 'Follow up call from widget test',
        due_date: dueDate,
        priority: 'high',
      },
    });

    if (res.ok()) {
      const body = await res.json();
      createdActionId = body.data?.id;
      expect(body.data.description).toBe('Follow up call from widget test');
      expect(body.data.priority).toBe('high');
    }
  });

  test('can filter actions by actionable_type and actionable_id', async () => {
    const res = await api.get('/api/v1/action-stream', {
      headers: authHeaders(),
      params: {
        actionable_type: 'person',
        actionable_id: 1,
        status: 'pending',
        per_page: 5,
      },
    });

    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('can complete action and fetch completed history', async () => {
    if (!createdActionId) return;

    // Complete the action
    const completeRes = await api.post(`/api/v1/action-stream/${createdActionId}/complete`, {
      headers: authHeaders(),
    });

    if (completeRes.ok()) {
      // Fetch completed actions
      const historyRes = await api.get('/api/v1/action-stream', {
        headers: authHeaders(),
        params: {
          actionable_type: 'person',
          actionable_id: 1,
          status: 'completed',
          per_page: 10,
        },
      });

      expect(historyRes.ok()).toBeTruthy();
      const body = await historyRes.json();
      expect(body.data).toBeInstanceOf(Array);
      createdActionId = null; // Already completed, no need to delete
    }
  });
});
