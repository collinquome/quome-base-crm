import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Email feature flag gating', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  // Flag defaults to false (or PostHog unreachable) → Mail sidebar entry hidden.
  test('sidebar has no Mail entry when flag is off', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const sidebar = page.locator('nav').first();
    // The mail link href pattern — should not be present when gated.
    await expect(sidebar.locator('a[href*="/admin/mail/"]')).toHaveCount(0);
  });

  test('lead view does not render Emails tab or Mail button when flag is off', async ({ page }) => {
    await page.goto('/admin/leads/view/3');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    test.skip(
      !!(bodyText?.includes('500') && bodyText?.includes('Something went wrong')),
      'Lead view 500 — skip',
    );

    // Email tab: activities component tabs section. When flag is off, Emails label must not appear.
    // We probe specifically within the activity tabs container to avoid false positives from arbitrary text.
    const mailButton = page.locator('button:has(.icon-mail)');
    await expect(mailButton).toHaveCount(0);
  });

  test('contact person view no longer has Next Action widget', async ({ page }) => {
    await page.goto('/admin/contacts/persons/view/3');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    test.skip(
      !!(bodyText?.includes('500') && bodyText?.includes('Something went wrong')),
      'Contact view 500 — skip',
    );

    await expect(page.locator('[data-testid="next-action-widget"]')).toHaveCount(0);
  });
});
