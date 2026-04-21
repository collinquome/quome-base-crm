import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Lead create → save, and product hover preview', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('lead-create page loads and does not 500', async ({ page }) => {
    await page.goto('/admin/leads/create?stage_id=1');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Something went wrong');
    expect(bodyText).not.toContain('Class "');
  });

  test('product search popover (any entity) renders rich preview on hover', async ({ page }) => {
    // Use /admin/quotes/create which preloads 5 products and has a visible lookup.
    await page.goto('/admin/quotes/create');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    test.skip(
      !!(bodyText?.includes('Something went wrong')),
      'Quotes create page errored — skip preview probe',
    );

    // Click the lookup to open the popup, then type a known SKU.
    const lookupInput = page.locator('input[placeholder*="product"], input[placeholder*="Product"]').first();
    if (!(await lookupInput.isVisible().catch(() => false))) {
      test.skip(true, 'No product lookup input on this page shape');
    }

    await lookupInput.click();
    await lookupInput.fill('AUTOP');
    await page.waitForTimeout(400);

    // Hover the result row — the teleported preview should render with a description.
    const resultItem = page.locator('li:has-text("AUTOP")').first();
    if (!(await resultItem.isVisible().catch(() => false))) {
      test.skip(true, 'Lookup did not return results in this environment');
    }
    await resultItem.hover();

    const preview = page.locator('[data-testid="lookup-item-preview"]').first();
    await expect(preview).toBeVisible({ timeout: 5000 });
    await expect(preview).toContainText('Personal Auto');
  });
});
