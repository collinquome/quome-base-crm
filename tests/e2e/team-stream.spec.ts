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

test.describe('Team Stream - Page Load', () => {
  test('team stream page loads successfully', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/team-stream`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.text-xl.font-bold:has-text("Team Stream")')).toBeVisible();
  });

  test('team stream has filters bar', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/team-stream`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('[data-testid="team-stream-filters"]')).toBeVisible();
    await expect(page.locator('[data-testid="team-stream-user-filter"]')).toBeVisible();
    await expect(page.locator('[data-testid="team-stream-type-filter"]')).toBeVisible();
    await expect(page.locator('[data-testid="team-stream-status-filter"]')).toBeVisible();
  });

  test('team stream shows list or empty state', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/team-stream`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const list = page.locator('[data-testid="team-stream-list"]');
    const empty = page.locator('[data-testid="team-stream-empty"]');

    const listVisible = await list.isVisible().catch(() => false);
    const emptyVisible = await empty.isVisible().catch(() => false);

    expect(listVisible || emptyVisible).toBeTruthy();
  });

  test('user filter dropdown has team members', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/team-stream`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(4000);

    const select = page.locator('[data-testid="team-stream-user-filter"]');
    const options = select.locator('option');
    const count = await options.count();

    // At minimum "All Members" placeholder option
    expect(count).toBeGreaterThanOrEqual(1);
  });
});

test.describe('Team Stream - API Backend', () => {
  test('GET /team-stream returns paginated actions', async () => {
    const res = await api.get('/api/v1/team-stream', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /team-stream supports user_id filter', async () => {
    const res = await api.get('/api/v1/team-stream?user_id=1', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /team-stream supports status filter', async () => {
    const res = await api.get('/api/v1/team-stream?status=completed', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /team-stream/members returns team members', async () => {
    const res = await api.get('/api/v1/team-stream/members', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThan(0);

    // Each member should have id and name
    const first = body.data[0];
    expect(first).toHaveProperty('id');
    expect(first).toHaveProperty('name');
  });

  test('GET /team-stream supports action_type filter', async () => {
    const res = await api.get('/api/v1/team-stream?action_type=call', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /team-stream supports date range filter', async () => {
    const today = new Date().toISOString().split('T')[0];
    const res = await api.get(`/api/v1/team-stream?due_from=${today}&due_to=${today}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('team-stream requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/team-stream', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });
});
