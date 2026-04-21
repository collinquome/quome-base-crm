import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Admin pages smoke (post-PostHog install)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  const pages = [
    '/admin/quotes',
    '/admin/contacts/persons',
    '/admin/products',
    '/admin/products/view/110',
    '/admin/products/edit/110',
  ];

  for (const path of pages) {
    test(`${path} does not 500`, async ({ page }) => {
      await page.goto(path);
      await page.waitForLoadState('networkidle');
      const bodyText = await page.textContent('body');
      expect(bodyText, `${path} should not render an error page`).not.toContain('Something went wrong');
      expect(bodyText, `${path} should not say Class ... not found`).not.toContain('Class "');
    });
  }
});
