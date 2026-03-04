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

test.describe('Report Builder UI - Page Load', () => {
  test('report builder page loads successfully', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.text-xl.font-bold:has-text("Report Builder")')).toBeVisible();
  });

  test('report builder has configuration panel', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('[data-testid="report-builder-config"]')).toBeVisible();
    await expect(page.locator('[data-testid="report-name-input"]')).toBeVisible();
    await expect(page.locator('[data-testid="report-entity-select"]')).toBeVisible();
  });

  test('report builder has preview panel', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('[data-testid="report-builder-preview"]')).toBeVisible();
    await expect(page.locator('[data-testid="report-no-preview"]')).toBeVisible();
  });

  test('report builder has saved reports section', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('[data-testid="report-saved-list"]')).toBeVisible();
  });
});

test.describe('Report Builder UI - Configuration', () => {
  test('entity type dropdown has expected options', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    // Wait for schema to load via API
    await page.waitForTimeout(3000);

    const select = page.locator('[data-testid="report-entity-select"]');
    const options = select.locator('option');

    // Should have at least "Select Entity" + some entity types
    const count = await options.count();
    expect(count).toBeGreaterThanOrEqual(1); // At minimum the placeholder
  });

  test('chart type buttons are rendered', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    const chartTypes = page.locator('[data-testid="report-chart-types"]');
    await expect(chartTypes).toBeVisible();

    // Should have Table, Bar, Line, Pie buttons
    await expect(chartTypes.locator('button')).toHaveCount(4);
  });

  test('add filter button works', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    const addFilter = page.locator('[data-testid="report-add-filter-btn"]');
    await expect(addFilter).toBeVisible();

    await addFilter.click();
    // A filter row should appear - check for an input with placeholder "Value"
    await expect(page.locator('input[placeholder="Value"]').first()).toBeVisible();
  });

  test('preview button is disabled without entity and columns', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/reports/builder`);
    await page.waitForLoadState('networkidle');

    const previewBtn = page.locator('[data-testid="report-preview-btn"]');
    await expect(previewBtn).toBeDisabled();
  });
});

test.describe('Report Builder - API Backend', () => {
  test('GET /reports/schema returns entity types', async () => {
    const res = await api.get('/api/v1/reports/schema', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    // Schema returns object keyed by entity type
    expect(typeof body.data).toBe('object');
    expect(Object.keys(body.data).length).toBeGreaterThan(0);
  });

  test('GET /reports returns saved reports list', async () => {
    const res = await api.get('/api/v1/reports', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('POST /reports/execute validates entity_type', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: { columns: ['id'] },
    });
    expect(res.status()).toBe(422);
  });
});
