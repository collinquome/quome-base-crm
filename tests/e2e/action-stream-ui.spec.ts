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

test.describe('Action Stream UI - Page Load', () => {
  test('action stream page loads successfully', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.text-xl.font-bold:has-text("Action Stream")')).toBeVisible();
  });

  test('action stream page has filter controls', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    const filters = page.locator('[data-testid="action-stream-filters"]');
    await expect(filters).toBeVisible();

    const typeFilter = page.locator('[data-testid="action-stream-type-filter"]');
    await expect(typeFilter).toBeVisible();

    const priorityFilter = page.locator('[data-testid="action-stream-priority-filter"]');
    await expect(priorityFilter).toBeVisible();

    const sortFilter = page.locator('[data-testid="action-stream-sort"]');
    await expect(sortFilter).toBeVisible();
  });

  test('action stream shows empty state when no actions', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    // Wait for loading to finish
    await page.waitForTimeout(2000);

    // Should show either the list or empty state
    const emptyState = page.locator('[data-testid="action-stream-empty"]');
    const list = page.locator('[data-testid="action-stream-list"]');

    // One of these should be visible
    const hasEmpty = await emptyState.isVisible();
    const hasList = await list.isVisible();
    expect(hasEmpty || hasList).toBe(true);
  });

  test('action stream has create button', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    const createBtn = page.locator('[data-testid="action-stream-create-btn"]');
    await expect(createBtn).toBeVisible();
    await expect(createBtn).toContainText('New Action');
  });
});

test.describe('Action Stream UI - Navigation', () => {
  test('action stream appears in sidebar navigation', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/dashboard`);
    await page.waitForLoadState('networkidle');

    // Check sidebar has the Action Stream link
    const sidebarLink = page.locator('a[href*="action-stream"]');
    await expect(sidebarLink).toBeVisible();
  });
});

test.describe('Action Stream UI - Filters', () => {
  test('type filter has all expected options', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    const typeFilter = page.locator('[data-testid="action-stream-type-filter"]');
    const options = typeFilter.locator('option');

    // Should have: All Types + call, email, meeting, task, custom = 6
    await expect(options).toHaveCount(6);
  });

  test('priority filter has all expected options', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/action-stream`);
    await page.waitForLoadState('networkidle');

    const priorityFilter = page.locator('[data-testid="action-stream-priority-filter"]');
    const options = priorityFilter.locator('option');

    // Should have: All Priorities + urgent, high, normal, low = 5
    await expect(options).toHaveCount(5);
  });
});

test.describe('Action Stream - API Backend', () => {
  test('GET /action-stream returns paginated data', async () => {
    const res = await api.get('/api/v1/action-stream', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    // Paginated response should have meta fields
    expect(typeof body.current_page).toBe('number');
  });

  test('GET /action-stream/overdue-count returns count', async () => {
    const res = await api.get('/api/v1/action-stream/overdue-count', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(typeof body.data.overdue_count).toBe('number');
  });
});
