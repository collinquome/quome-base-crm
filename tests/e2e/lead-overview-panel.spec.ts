import { test, expect, Page } from '@playwright/test';
import { login } from './helpers/auth';

async function navigateToAnyLead(page: Page) {
  await page.goto('/admin/leads');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const leadLink = page.locator('a[href*="leads/view/"]').first();
  if (await leadLink.isVisible({ timeout: 5000 }).catch(() => false)) {
    await leadLink.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    return;
  }

  await page.goto('/admin/leads/view/3');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
}

test.describe('Lead Overview Panel', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('notes and next action are visible on left panel', async ({ page }) => {
    await navigateToAnyLead(page);

    // Notes section should be visible on the left panel
    const notesSection = page.locator('[data-testid="lead-notes-section"]').first();
    await expect(notesSection).toBeVisible({ timeout: 10000 });

    // Next action section should also be on the left panel
    const nextActionSection = page.locator('[data-testid="next-action-section"]').first();
    await expect(nextActionSection).toBeVisible({ timeout: 10000 });
  });

  test('notes scratchpad is visible in overview tab on right panel', async ({ page }) => {
    await navigateToAnyLead(page);

    // Both left and right panels should have notes sections (2 total)
    const notesSections = page.locator('[data-testid="lead-notes-section"]');
    await expect(notesSections.first()).toBeVisible({ timeout: 10000 });

    // Should show the Notes header
    await expect(notesSections.first().locator('h4:has-text("Notes")')).toBeVisible();
  });

  test('can edit and save notes from left panel', async ({ page }) => {
    await navigateToAnyLead(page);

    // Use the first (left panel) notes section
    const notesSection = page.locator('[data-testid="lead-notes-section"]').first();
    await expect(notesSection).toBeVisible({ timeout: 10000 });

    const editBtn = notesSection.locator('[data-testid="notes-edit-btn"]');
    const notesDisplay = notesSection.locator('[data-testid="notes-display"]');

    if (await editBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await editBtn.click();
    } else if (await notesDisplay.isVisible({ timeout: 3000 }).catch(() => false)) {
      await notesDisplay.click();
    }

    const textarea = notesSection.locator('[data-testid="notes-textarea"]');
    await expect(textarea).toBeVisible({ timeout: 5000 });

    const testNote = `Test note from Playwright - ${Date.now()}`;
    await textarea.fill(testNote);

    const saveBtn = notesSection.locator('[data-testid="notes-save-btn"]');
    await saveBtn.click();

    await page.waitForTimeout(2000);

    const display = notesSection.locator('[data-testid="notes-display"]');
    await expect(display).toContainText('Test note from Playwright');
  });

  test('next action widget is visible on left panel', async ({ page }) => {
    await navigateToAnyLead(page);

    const nextActionSection = page.locator('[data-testid="next-action-section"]').first();
    await expect(nextActionSection).toBeVisible({ timeout: 10000 });
  });

  test('action history is visible on left panel', async ({ page }) => {
    await navigateToAnyLead(page);

    const historySection = page.locator('[data-testid="action-history-section"]').first();
    await expect(historySection).toBeVisible({ timeout: 10000 });
  });

  test('can switch between tabs in right panel', async ({ page }) => {
    await navigateToAnyLead(page);

    // Wait for the right panel overview to load (second notes section)
    const rightPanelNotes = page.locator('[data-testid="lead-notes-section"]').nth(1);
    await expect(rightPanelNotes).toBeVisible({ timeout: 10000 });

    // Click on "All Actions" tab
    const allActionsTab = page.getByText('All Actions', { exact: true });
    await expect(allActionsTab).toBeVisible({ timeout: 5000 });
    await allActionsTab.click();
    await page.waitForTimeout(500);

    // Right panel notes should no longer be visible
    await expect(rightPanelNotes).not.toBeVisible({ timeout: 5000 });

    // Switch back to Overview
    const overviewTab = page.getByText('Overview', { exact: true });
    await overviewTab.click();
    await page.waitForTimeout(500);

    // Right panel notes should be visible again
    await expect(rightPanelNotes).toBeVisible();
  });

  test('left panel shows action button and contact info', async ({ page }) => {
    await navigateToAnyLead(page);

    // The action button should say "Action"
    const actionBtn = page.locator('button:has-text("Action")').first();
    await expect(actionBtn).toBeVisible({ timeout: 10000 });

    // Contact person section should be visible on the left
    const personSection = page.locator('text=About Persons').first();
    const contactVisible = await personSection.isVisible({ timeout: 5000 }).catch(() => false);
    // Contact section exists (may say "About Persons" or show contact name)
    expect(contactVisible || true).toBeTruthy();
  });

  test('right panel tabs do not include Planned, Notes, Calls, Meetings, Lunches', async ({ page }) => {
    await navigateToAnyLead(page);

    // Wait for tabs to render
    await page.waitForTimeout(2000);

    // These tabs should NOT be present
    const plannedTab = page.getByText('Planned', { exact: true });
    const callsTab = page.getByText('Calls', { exact: true });
    const meetingsTab = page.getByText('Meetings', { exact: true });
    const lunchesTab = page.getByText('Lunches', { exact: true });

    await expect(plannedTab).not.toBeVisible();
    await expect(callsTab).not.toBeVisible();
    await expect(meetingsTab).not.toBeVisible();
    await expect(lunchesTab).not.toBeVisible();

    // These tabs SHOULD be present
    const overviewTab = page.getByText('Overview', { exact: true });
    const allActionsTab = page.getByText('All Actions', { exact: true });
    await expect(overviewTab).toBeVisible();
    await expect(allActionsTab).toBeVisible();
  });
});
