import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Multi-address repeater on contacts', () => {
  test('person edit page renders the repeater + payload input', async ({ page }) => {
    await login(page);
    await page.goto('/admin/contacts/persons');
    await page.waitForLoadState('networkidle');

    // Open the first person in the list by clicking the edit icon.
    // Fall back to creating one if the datagrid has no rows — keeps the
    // test portable across envs that haven't seen any contacts yet.
    const editButton = page.locator('a[href*="/admin/contacts/persons/edit/"]').first();
    if (await editButton.count() > 0) {
      await editButton.click();
    } else {
      await page.goto('/admin/contacts/persons/create');
    }

    await page.waitForLoadState('networkidle');

    // The payload hidden input is the single source of truth for the form post.
    const payload = page.locator('input[data-testid="address-payload"]');
    await expect(payload).toHaveCount(1);

    // Start empty.
    const emptyState = page.locator('[data-testid="address-empty"]');
    // Either empty state is visible OR there are pre-existing rows.
    const rowCount = await page.locator('[data-testid^="address-row-"]').count();
    if (rowCount === 0) {
      await expect(emptyState).toBeVisible();
    }

    // Add two addresses.
    await page.locator('[data-testid="address-add"]').click();
    await page.locator('[data-testid="address-add"]').click();

    const rowsAfterAdd = await page.locator('[data-testid^="address-row-"]').count();
    expect(rowsAfterAdd).toBeGreaterThanOrEqual(rowCount + 2);

    // Country defaults to US on new rows.
    const country0 = page.locator('[data-testid="address-country-' + rowCount + '"]');
    await expect(country0).toHaveValue('US');

    // Fill the first new row.
    await page.locator('[data-testid="address-line1-' + rowCount + '"]').fill('123 Main St');
    await page.locator('[data-testid="address-city-' + rowCount + '"]').fill('Seattle');
    await page.locator('[data-testid="address-state-' + rowCount + '"]').fill('WA');
    await page.locator('[data-testid="address-postcode-' + rowCount + '"]').fill('98101');

    // Payload reflects live state.
    const payloadValue = await payload.inputValue();
    const parsed = JSON.parse(payloadValue);
    expect(Array.isArray(parsed)).toBe(true);
    const fresh = parsed.find((a: any) => a.address_line_1 === '123 Main St');
    expect(fresh).toBeTruthy();
    expect(fresh.city).toBe('Seattle');
    expect(fresh.country).toBe('US');

    // Remove the second newly-added row.
    await page.locator('[data-testid="address-remove-' + (rowCount + 1) + '"]').click();
    const rowsAfterRemove = await page.locator('[data-testid^="address-row-"]').count();
    expect(rowsAfterRemove).toBe(rowsAfterAdd - 1);
  });

  test('lead create page renders the repeater under the contact section with person prefix', async ({ page }) => {
    await login(page);
    await page.goto('/admin/leads/create');
    await page.waitForLoadState('networkidle');

    // The lead-create variant uses testid-prefix="lead-create-address"
    // and name-prefix="person" so the hidden input is person[addresses_payload].
    const payload = page.locator('input[data-testid="lead-create-address-payload"]');
    await expect(payload).toHaveCount(1);
    await expect(payload).toHaveAttribute('name', 'person[addresses_payload]');

    // Empty state visible.
    await expect(page.locator('[data-testid="lead-create-address-empty"]')).toBeVisible();

    // Adding a row should remove the empty state.
    await page.locator('[data-testid="lead-create-address-add"]').click();
    await expect(page.locator('[data-testid="lead-create-address-empty"]')).toHaveCount(0);
    await expect(page.locator('[data-testid="lead-create-address-row-0"]')).toBeVisible();

    // Country defaults to US on the new row.
    await expect(page.locator('[data-testid="lead-create-address-country-0"]')).toHaveValue('US');
  });
});
