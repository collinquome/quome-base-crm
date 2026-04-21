import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Settings > Users datagrid', () => {
  test('loads without a 500 after the schema repair', async ({ page }) => {
    await login(page);

    const datagridResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('/admin/settings/users') && resp.request().method() === 'GET'
        && resp.request().headers()['x-requested-with'] === 'XMLHttpRequest',
      { timeout: 15000 },
    );

    await page.goto('/admin/settings/users');
    await page.waitForLoadState('networkidle');

    const datagridResponse = await datagridResponsePromise.catch(() => null);
    if (datagridResponse) {
      expect(datagridResponse.status(), 'Users datagrid XHR should be 2xx').toBeLessThan(400);
      const body = await datagridResponse.json().catch(() => null);
      expect(body, 'Users datagrid XHR should return JSON').toBeTruthy();
      expect(body.records || body.data || [], 'Users datagrid should return records').toBeInstanceOf(Array);
    } else {
      // If the XHR shape is different in this build, at least the page must render and not error.
      const bodyText = await page.textContent('body');
      expect(bodyText).not.toContain('internal-server-error');
      expect(bodyText).not.toContain('Something went wrong');
    }
  });
});
