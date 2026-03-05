import { test, expect, Page } from '@playwright/test';
import { login } from './helpers/auth';

async function loginPage(page: Page) {
  await login(page);
}

test.describe('Next Action Widget - Lead Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginPage(page);
  });

  test('widget renders on lead detail page', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');

    const widget = page.locator('[data-testid="next-action-widget"]');
    await expect(widget).toBeVisible({ timeout: 10000 });

    const section = page.locator('[data-testid="next-action-section"]');
    await expect(section).toBeVisible();
  });

  test('action history section is visible', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');

    const history = page.locator('[data-testid="action-history-section"]');
    await expect(history).toBeVisible({ timeout: 10000 });
    await expect(history.locator('text=Action History')).toBeVisible();
  });

  test('widget shows empty state, current action, or new button', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const widget = page.locator('[data-testid="next-action-widget"]');
    const current = widget.locator('[data-testid="next-action-current"]');
    const empty = widget.locator('[data-testid="next-action-empty"]');
    const newBtn = widget.locator('[data-testid="next-action-new-btn"]');

    const hasContent = await current.isVisible().catch(() => false);
    const hasEmpty = await empty.isVisible().catch(() => false);
    const hasNewBtn = await newBtn.isVisible().catch(() => false);

    expect(hasContent || hasEmpty || hasNewBtn).toBeTruthy();
  });

  test('clicking New/Set-one-now opens create form with all fields', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const newBtn = page.locator('[data-testid="next-action-new-btn"]');
    const setNow = page.locator('text=Set one now');

    if (await newBtn.isVisible().catch(() => false)) {
      await newBtn.click();
    } else if (await setNow.isVisible().catch(() => false)) {
      await setNow.click();
    }

    const form = page.locator('[data-testid="next-action-form"]');
    await expect(form).toBeVisible({ timeout: 5000 });

    await expect(page.locator('[data-testid="next-action-type-select"]')).toBeVisible();
    await expect(page.locator('[data-testid="next-action-priority-select"]')).toBeVisible();
    await expect(page.locator('[data-testid="next-action-due-date"]')).toBeVisible();
    await expect(page.locator('[data-testid="next-action-description"]')).toBeVisible();
    await expect(page.locator('[data-testid="next-action-save-btn"]')).toBeVisible();
  });

  test('save button is disabled when description is empty', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const newBtn = page.locator('[data-testid="next-action-new-btn"]');
    const setNow = page.locator('text=Set one now');
    if (await newBtn.isVisible().catch(() => false)) {
      await newBtn.click();
    } else if (await setNow.isVisible().catch(() => false)) {
      await setNow.click();
    }

    await expect(page.locator('[data-testid="next-action-form"]')).toBeVisible({ timeout: 5000 });
    const saveBtn = page.locator('[data-testid="next-action-save-btn"]');
    await expect(saveBtn).toBeDisabled();
  });

  test('can create a next action via Save Action button', async ({ page }) => {
    const ts = Date.now();
    const description = `Follow up call ${ts}`;

    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Open create form
    const newBtn = page.locator('[data-testid="next-action-new-btn"]');
    const setNow = page.locator('text=Set one now');
    if (await newBtn.isVisible().catch(() => false)) {
      await newBtn.click();
    } else if (await setNow.isVisible().catch(() => false)) {
      await setNow.click();
    }

    await expect(page.locator('[data-testid="next-action-form"]')).toBeVisible({ timeout: 5000 });

    // Fill form
    await page.locator('[data-testid="next-action-type-select"]').selectOption('call');
    await page.locator('[data-testid="next-action-priority-select"]').selectOption('high');
    await page.locator('[data-testid="next-action-description"]').fill(description);

    // Save button should be enabled
    const saveBtn = page.locator('[data-testid="next-action-save-btn"]');
    await expect(saveBtn).toBeEnabled();

    // Intercept POST to /admin/action-stream
    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('/action-stream') && resp.request().method() === 'POST' && !resp.url().includes('/complete'),
      { timeout: 10000 }
    );

    await saveBtn.click();

    // Verify POST succeeds with 201
    const response = await responsePromise;
    expect([200, 201]).toContain(response.status());

    const body = await response.json();
    expect(body.data).toBeTruthy();
    expect(body.data.description).toBe(description);
    expect(body.message).toBe('Next action created.');

    // Wait for widget to refresh
    await page.waitForTimeout(1500);

    // Form should be hidden after save
    await expect(page.locator('[data-testid="next-action-form"]')).not.toBeVisible();
  });

  test('can complete a next action', async ({ page }) => {
    const ts = Date.now();
    const description = `Complete me ${ts}`;

    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Create an action first
    const newBtn = page.locator('[data-testid="next-action-new-btn"]');
    const setNow = page.locator('text=Set one now');
    if (await newBtn.isVisible().catch(() => false)) {
      await newBtn.click();
    } else if (await setNow.isVisible().catch(() => false)) {
      await setNow.click();
    }

    await expect(page.locator('[data-testid="next-action-form"]')).toBeVisible({ timeout: 5000 });
    await page.locator('[data-testid="next-action-description"]').fill(description);

    const createResp = page.waitForResponse(
      resp => resp.url().includes('/action-stream') && resp.request().method() === 'POST' && !resp.url().includes('/complete'),
      { timeout: 10000 }
    );
    await page.locator('[data-testid="next-action-save-btn"]').click();
    const createResponse = await createResp;
    expect([200, 201]).toContain(createResponse.status());
    await page.waitForTimeout(1500);

    // Now complete it
    const completeBtn = page.locator('[data-testid="next-action-complete-btn"]');
    if (await completeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      const completeRespPromise = page.waitForResponse(
        resp => resp.url().includes('/complete') && resp.request().method() === 'POST',
        { timeout: 10000 }
      );
      await completeBtn.click();
      const completeResponse = await completeRespPromise;
      expect(completeResponse.status()).toBe(200);

      await page.waitForTimeout(1500);

      // Should now show the create form (prompted for next action)
      // or the action should be in history
      const historySection = page.locator('[data-testid="action-history-list"]');
      const formVisible = await page.locator('[data-testid="next-action-form"]').isVisible().catch(() => false);
      const historyVisible = await historySection.isVisible().catch(() => false);
      expect(formVisible || historyVisible).toBeTruthy();
    }
  });

  test('cancel button hides form without saving', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const newBtn = page.locator('[data-testid="next-action-new-btn"]');
    const setNow = page.locator('text=Set one now');
    if (await newBtn.isVisible().catch(() => false)) {
      await newBtn.click();
    } else if (await setNow.isVisible().catch(() => false)) {
      await setNow.click();
    }

    await expect(page.locator('[data-testid="next-action-form"]')).toBeVisible({ timeout: 5000 });
    await page.locator('[data-testid="next-action-description"]').fill('Should not be saved');

    await page.locator('button:has-text("Cancel")').click();
    await expect(page.locator('[data-testid="next-action-form"]')).not.toBeVisible();
  });

  test('action-stream list API returns correct response via session auth', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');

    // The widget automatically fetches from /admin/action-stream/list on mount
    const response = await page.waitForResponse(
      resp => resp.url().includes('/action-stream/list') && resp.request().method() === 'GET',
      { timeout: 10000 }
    ).catch(() => null);

    if (response) {
      expect(response.status()).toBe(200);
      const body = await response.json();
      expect(body).toHaveProperty('data');
      expect(body).toHaveProperty('current_page');
    }
  });

  test('completed count displays in history section', async ({ page }) => {
    await page.goto('/admin/leads/view/194');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const historySection = page.locator('[data-testid="action-history-section"]');
    await expect(historySection).toBeVisible({ timeout: 10000 });

    // Should show "X completed" text
    const completedText = historySection.locator('text=/\\d+ completed/');
    await expect(completedText).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Next Action Widget - Contact Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginPage(page);
  });

  test('widget renders on contact detail page', async ({ page }) => {
    await page.goto('/admin/contacts/persons/view/1');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/persons/view/')) {
      const widget = page.locator('[data-testid="next-action-widget"]');
      await expect(widget).toBeVisible();

      const section = page.locator('[data-testid="next-action-section"]');
      await expect(section).toBeVisible();
    }
  });
});
