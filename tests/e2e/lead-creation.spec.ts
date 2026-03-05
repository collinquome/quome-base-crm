import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Lead Creation', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can create a lead with all required fields', async ({ page }) => {
    const ts = Date.now();
    await page.goto('/admin/leads/create?stage_id=1');
    await page.waitForLoadState('networkidle');

    // Fill in lead name (attribute code is "title", display label is "Name")
    const nameInput = page.locator('input[name="title"]');
    await expect(nameInput).toBeVisible({ timeout: 10000 });
    await nameInput.fill(`Test Lead ${ts}`);

    // Select Source (required) - native <select> with custom-select styling
    const sourceSelect = page.locator('select[name="lead_source_id"]');
    await sourceSelect.selectOption({ index: 1 }); // First real option (Email)

    // Select Type (required)
    const typeSelect = page.locator('select[name="lead_type_id"]');
    await typeSelect.selectOption({ index: 1 }); // First real option (New Business)

    // Fill lead value (required)
    const leadValueInput = page.locator('input[name="lead_value"]');
    await leadValueInput.fill('10000');

    // Contact Person - click the lookup to open it
    const personLookup = page.locator('text=Click to Add').first();
    await personLookup.click();

    // Wait for search dropdown to appear, type a name
    const searchInput = page.locator('input[placeholder="Search..."]').first();
    await expect(searchInput).toBeVisible({ timeout: 5000 });
    await searchInput.fill(`Person ${ts}`);

    // Click "Add as New" to create a new person inline
    const addAsNew = page.locator('text=Add as New').first();
    await expect(addAsNew).toBeVisible({ timeout: 5000 });
    await addAsNew.click();

    // Fill contact person email (required) - use unique email
    const emailInput = page.locator('input[name="person[emails][0][value]"]');
    await emailInput.fill(`test${ts}@example.com`);

    // Click Save and wait for navigation or response
    const saveButton = page.locator('button[type="submit"]:has-text("Save")').first();

    // Listen for the form submission response
    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('/leads') && resp.request().method() === 'POST',
      { timeout: 15000 }
    ).catch(() => null);

    await saveButton.click();

    // Wait for either redirect or response
    const response = await responsePromise;

    // If we got a response, check the status
    if (response) {
      // 302 redirect or 200 means success
      expect([200, 201, 302]).toContain(response.status());
    }

    // Wait a moment for redirect to complete
    await page.waitForTimeout(2000);

    // Either we're on the leads list/view, or we're still on create with a success message
    const currentUrl = page.url();
    const isRedirected = !currentUrl.includes('/create');
    const hasSuccessMessage = await page.locator('text=/success/i').isVisible().catch(() => false);

    expect(isRedirected || hasSuccessMessage).toBeTruthy();
  });

  test('shows validation errors for missing required fields', async ({ page }) => {
    await page.goto('/admin/leads/create?stage_id=1');
    await page.waitForLoadState('networkidle');

    // Click Save without filling anything
    const saveButton = page.locator('button[type="submit"]:has-text("Save")').first();
    await saveButton.click();

    // Should show validation errors
    await page.waitForTimeout(1000);

    // Check for error messages
    const errors = page.locator('text=/is required/i');
    const errorCount = await errors.count();
    expect(errorCount).toBeGreaterThan(0);
  });

  test('lead appears in leads list after creation', async ({ page }) => {
    const uniqueName = `Lead-${Date.now()}`;

    // Create the lead
    await page.goto('/admin/leads/create?stage_id=1');
    await page.waitForLoadState('networkidle');

    await page.locator('input[name="title"]').fill(uniqueName);
    await page.locator('select[name="lead_source_id"]').selectOption({ index: 1 });
    await page.locator('select[name="lead_type_id"]').selectOption({ index: 1 });
    await page.locator('input[name="lead_value"]').fill('5000');

    // Add contact person with unique email
    await page.locator('text=Click to Add').first().click();
    const searchInput = page.locator('input[placeholder="Search..."]').first();
    await expect(searchInput).toBeVisible({ timeout: 5000 });
    await searchInput.fill(`Person ${Date.now()}`);
    await page.locator('text=Add as New').first().click();
    await page.locator('input[name="person[emails][0][value]"]').fill(`lead${Date.now()}@example.com`);

    const saveBtn = page.locator('button[type="submit"]:has-text("Save")').first();
    const respPromise = page.waitForResponse(
      resp => resp.url().includes('/leads') && resp.request().method() === 'POST',
      { timeout: 15000 }
    ).catch(() => null);
    await saveBtn.click();
    await respPromise;
    await page.waitForTimeout(2000);

    // Navigate to leads list and search for the lead by name
    await page.goto('/admin/leads?pipeline_id=1');
    await page.waitForLoadState('networkidle');

    // Use the search box to find the specific lead
    const searchBox = page.locator('input[placeholder*="Search"], input[name="search"]').first();
    if (await searchBox.isVisible().catch(() => false)) {
      await searchBox.fill(uniqueName);
      await page.waitForTimeout(2000); // Wait for search results
    }

    // The lead name should appear somewhere on the page (kanban cards or list rows)
    const leadCard = page.locator(`text=${uniqueName}`).first();
    await expect(leadCard).toBeVisible({ timeout: 10000 });
  });

  test('native select dropdowns are interactable', async ({ page }) => {
    await page.goto('/admin/leads/create?stage_id=1');
    await page.waitForLoadState('networkidle');

    // Test Source dropdown
    const sourceSelect = page.locator('select[name="lead_source_id"]');
    await expect(sourceSelect).toBeVisible();

    // Verify it has options
    const sourceOptions = sourceSelect.locator('option');
    const sourceCount = await sourceOptions.count();
    expect(sourceCount).toBeGreaterThan(1);

    // Select an option and verify
    await sourceSelect.selectOption({ index: 1 });
    const selectedValue = await sourceSelect.inputValue();
    expect(selectedValue).toBeTruthy();
    expect(selectedValue).not.toBe('');

    // Test Type dropdown
    const typeSelect = page.locator('select[name="lead_type_id"]');
    await expect(typeSelect).toBeVisible();
    await typeSelect.selectOption({ index: 1 });
    const typeValue = await typeSelect.inputValue();
    expect(typeValue).toBeTruthy();
  });

  test('sidebar shows correct menu order', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // Get sidebar menu items in order
    const sidebarItems = page.locator('nav a, aside a, [class*="sidebar"] a').filter({
      hasText: /.+/
    });

    const itemTexts: string[] = [];
    const count = await sidebarItems.count();
    for (let i = 0; i < count; i++) {
      const text = await sidebarItems.nth(i).textContent();
      if (text?.trim()) {
        itemTexts.push(text.trim());
      }
    }

    // Verify Dashboard comes before Leads, Leads before Quotes, etc.
    const expectedOrder = ['Dashboard', 'Leads', 'Quotes', 'Mail', 'Activities', 'Contacts', 'Products', 'Settings', 'Configuration'];
    let lastIndex = -1;
    for (const item of expectedOrder) {
      const found = itemTexts.findIndex(t => t.toLowerCase().includes(item.toLowerCase()));
      if (found >= 0) {
        expect(found).toBeGreaterThan(lastIndex);
        lastIndex = found;
      }
    }
  });
});
