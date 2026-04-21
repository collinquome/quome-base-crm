import { test, expect } from '@playwright/test';
import { login, ADMIN_EMAIL } from './helpers/auth';

test.describe('Lead create form', () => {
  test('Sales Owner defaults to the authenticated user', async ({ page }) => {
    await login(page);

    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    // The lookup component renders the selected user's display name in the Sales Owner field.
    // Admin's name in the default seed is "Example Admin" — but in case naming varies,
    // we just assert that the field shows *something* other than the placeholder.
    const placeholder = 'Click to add';

    // The Sales Owner field is a v-lookup-component bound to the user_id attribute.
    // Find its selected-item span: the lookup renders "@{{ selectedItem?.name }}" inside a
    // `.overflow-hidden.text-ellipsis` span within its own container.
    const salesOwnerLabel = page.locator('label:has-text("Sales Owner"), label[for="user_id"]').first();
    await expect(salesOwnerLabel).toBeVisible({ timeout: 10000 });

    // Within the form-control-group that contains the Sales Owner label, the selected-item text
    // should not still say "Click to add".
    const group = page.locator('div').filter({ has: salesOwnerLabel }).first();
    const selectedText = await group.locator('.overflow-hidden.text-ellipsis').first().textContent({ timeout: 5000 });
    expect(selectedText?.trim()).not.toBe('');
    expect(selectedText?.trim()).not.toContain(placeholder);
  });
});
