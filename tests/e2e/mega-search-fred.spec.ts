import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Mega-search: persons tab honors typed query', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('searching "Fred" returns Fred and not unrelated names', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // Hit the persons search endpoint the way the mega-search builds the URL.
    const result = await page.evaluate(async () => {
      const params = new URLSearchParams({
        search: 'name:Fred;job_title:Fred;user.name:Fred;organization.name:Fred;',
        searchFields: 'name:like;job_title:like;user.name:like;organization.name:like;',
      });
      const res = await fetch(`/admin/contacts/persons/search?${params.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'include',
      });
      return res.json();
    });

    const names: string[] = (result?.data || []).map((p: any) => p.name);
    // Every returned row must contain "Fred" (case-insensitive). Pre-fix, this
    // returned the first 25 persons alphabetically regardless of the search term.
    for (const name of names) {
      expect(name.toLowerCase(), `"${name}" should match "Fred"`).toContain('fred');
    }
    // And we expect at least one match (the seeded Fred Insurance contact).
    expect(names.length, 'expected at least one matching person').toBeGreaterThan(0);
  });
});
