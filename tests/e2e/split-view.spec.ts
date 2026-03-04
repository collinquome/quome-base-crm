import { test, expect, Page } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

async function login(page: Page) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin/, { timeout: 15000 });
}

test.describe('Split View - Contacts', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('contacts page loads with split view toggle buttons', async ({ page }) => {
    await page.goto(`${BASE}/admin/contacts/persons`);
    await page.waitForLoadState('networkidle');

    const fullBtn = page.locator('[data-testid="split-view-full-btn"]');
    const splitBtn = page.locator('[data-testid="split-view-split-btn"]');

    await expect(fullBtn).toBeVisible();
    await expect(splitBtn).toBeVisible();
  });

  test('contacts page starts in full view mode', async ({ page }) => {
    await page.goto(`${BASE}/admin/contacts/persons`);
    await page.waitForLoadState('networkidle');

    // Full view button should be active
    const fullBtn = page.locator('[data-testid="split-view-full-btn"]');
    await expect(fullBtn).toHaveClass(/bg-brandColor/);

    // Detail panel should not be visible
    const detailPanel = page.locator('[data-testid="split-view-detail-panel"]');
    await expect(detailPanel).not.toBeVisible();
  });

  test('clicking split view shows detail panel', async ({ page }) => {
    await page.goto(`${BASE}/admin/contacts/persons`);
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="split-view-split-btn"]').click();

    const detailPanel = page.locator('[data-testid="split-view-detail-panel"]');
    await expect(detailPanel).toBeVisible();
    await expect(detailPanel).toContainText('Select a');
  });

  test('toggling back to full view hides detail panel', async ({ page }) => {
    await page.goto(`${BASE}/admin/contacts/persons`);
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="split-view-split-btn"]').click();
    await expect(page.locator('[data-testid="split-view-detail-panel"]')).toBeVisible();

    await page.locator('[data-testid="split-view-full-btn"]').click();
    await expect(page.locator('[data-testid="split-view-detail-panel"]')).not.toBeVisible();
  });
});

test.describe('Split View - Leads (Table View)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('leads table view has split view toggle', async ({ page }) => {
    await page.goto(`${BASE}/admin/leads?view_type=table`);
    await page.waitForLoadState('networkidle');

    const fullBtn = page.locator('[data-testid="split-view-full-btn"]');
    const splitBtn = page.locator('[data-testid="split-view-split-btn"]');

    await expect(fullBtn).toBeVisible();
    await expect(splitBtn).toBeVisible();
  });
});

test.describe('Split View - API Integration', () => {
  test('split view component renders correctly on contacts page', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE}/admin/contacts/persons`);
    await page.waitForLoadState('networkidle');

    // Verify both toggle buttons are rendered
    const fullBtn = page.locator('[data-testid="split-view-full-btn"]');
    await expect(fullBtn).toBeVisible();
    await expect(fullBtn).toContainText('Full View');

    const splitBtn = page.locator('[data-testid="split-view-split-btn"]');
    await expect(splitBtn).toContainText('Split View');
  });
});
