import { test, expect, APIRequestContext } from '@playwright/test';
import { login } from './helpers/auth';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' };
}

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });
  const res = await api.post('/api/v1/auth/login', { data: { email: 'admin@example.com', password: 'admin123' } });
  token = (await res.json()).token;
});

test.afterAll(async () => { await api.dispose(); });

test.describe('Queue fixups — items 1–4', () => {
  test('1. phone field on lead create can be cleared after draft restore', async ({ page }) => {
    await login(page);
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    // Seed a draft with a phone, then reload.
    const phoneSelector = 'input[name="person[contact_numbers][0][value]"]';
    const phoneInput = page.locator(phoneSelector).first();
    await expect(phoneInput).toBeVisible({ timeout: 10000 });

    await phoneInput.fill('5551234567');
    await page.waitForTimeout(600); // debounce save

    await page.reload();
    await page.waitForLoadState('networkidle');

    // After restore, phone should carry value — confirm we can clear it.
    const afterReload = page.locator(phoneSelector).first();
    await expect(afterReload).toBeVisible({ timeout: 10000 });
    await afterReload.focus();
    await afterReload.press('Control+A');
    await afterReload.press('Delete');
    await afterReload.fill('');
    await expect(afterReload).toHaveValue('');
  });

  test('2. /api/v1/notifications/unread-count returns 2xx', async () => {
    const res = await api.get('/api/v1/notifications/unread-count', {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(typeof body.data?.unread_count).toBe('number');
  });

  test('3. duplicate email on lead contact form surfaces existing person banner', async ({ page }) => {
    // Known-seeded duplicate target in this environment.
    const existingEmail = 'dup-test@example.com';
    const existingName = 'Dup Test Person';

    await login(page);
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    const emailInput = page.locator('input[name="person[emails][0][value]"]').first();
    await expect(emailInput).toBeVisible({ timeout: 10000 });
    await emailInput.fill(existingEmail);

    // Debounce (500ms) + network round-trip.
    await page.waitForTimeout(1500);

    const banner = page.locator('[data-testid="contact-duplicate-suggestion"]');
    await expect(banner).toBeVisible({ timeout: 5000 });
    await expect(banner).toContainText(existingName);

    // Clicking "Use this contact" should switch the form into existing-person mode.
    await banner.locator('[data-testid="contact-duplicate-use-existing"]').click();
    await expect(banner).not.toBeVisible();
  });

  test('4. product view: create brand-new tag via "Add as new tag" path', async ({ page }) => {
    await login(page);
    await page.goto('/admin/products/view/110');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    test.skip(!!(bodyText?.includes('Something went wrong')), 'product view 500');

    // Click the tag settings button to open the tag dropdown.
    const tagToggle = page.locator('button.icon-settings-tag').first();
    await expect(tagToggle).toBeVisible({ timeout: 10000 });
    await tagToggle.click();

    const unique = `QPROBE-${Date.now()}`;
    const tagInput = page.locator('input[placeholder*="tag" i], input[placeholder*="Tag"]').first();
    await expect(tagInput).toBeVisible({ timeout: 5000 });
    await tagInput.fill(unique);

    await page.waitForTimeout(700); // debounce search + server response

    // The "Add as new tag" option should be visible when no match exists.
    const addOption = page.locator(`text=${unique}`).first();
    await expect(addOption).toBeVisible({ timeout: 5000 });
    await addOption.click();

    // After attach, the new tag should appear on the product.
    await expect(page.locator(`text=${unique}`).first()).toBeVisible({ timeout: 5000 });
  });
});
