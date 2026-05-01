import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { login } from './helpers/auth';

test.describe('Bulk lead import (Datalot CSV)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Import button on /admin/leads goes to the import page', async ({ page }) => {
    await page.goto('/admin/leads');
    await page.waitForLoadState('networkidle');

    const btn = page.getByTestId('leads-import-btn');
    await expect(btn).toBeVisible({ timeout: 10000 });
    await btn.click();

    await expect(page).toHaveURL(/\/admin\/leads\/import$/);
    await expect(page.getByText(/Bulk import from CSV \/ XLSX/i)).toBeVisible();
    await expect(page.getByTestId('leads-import-template')).toBeVisible();
  });

  test('Template download returns a CSV with the documented header columns', async ({ page }) => {
    await page.goto('/admin/leads/import');
    await page.waitForLoadState('networkidle');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('leads-import-template').click(),
    ]);

    const tmp = path.join(os.tmpdir(), `template-${Date.now()}.csv`);
    await download.saveAs(tmp);
    const content = fs.readFileSync(tmp, 'utf8').split('\n')[0].trim();
    fs.unlinkSync(tmp);

    for (const col of [
      'first_name', 'last_name', 'email', 'phone',
      'street_address', 'city', 'state', 'zip',
      'date_of_birth', 'vertical', 'lead_cost', 'lead_id', 'notes',
    ]) {
      expect(content, `template should expose column "${col}"`).toContain(col);
    }
  });

  test('Uploading a Datalot-style CSV creates a Person + Lead per row', async ({ page }) => {
    // Build a fixture: two leads, one auto vertical and one life vertical.
    const stamp = Date.now();
    const csv = [
      'first_name,last_name,email,phone,street_address,city,state,zip,date_of_birth,vertical,lead_cost,lead_id,notes',
      `Bulk,Importable-${stamp}-A,bulk-a-${stamp}@example.com,555-100-${String(stamp).slice(-4)},1 Pine St,Bellevue,WA,98005,1980-01-01,auto,9.50,DL-A-${stamp},from datalot`,
      `Bulk,Importable-${stamp}-B,bulk-b-${stamp}@example.com,555-200-${String(stamp).slice(-4)},2 Cedar Ave,Seattle,WA,98101,1990-02-02,life,14.00,DL-B-${stamp},vip`,
    ].join('\n');
    const fixturePath = path.join(os.tmpdir(), `bulk-import-${stamp}.csv`);
    fs.writeFileSync(fixturePath, csv);

    await page.goto('/admin/leads/import');
    await page.waitForLoadState('networkidle');

    await page.setInputFiles('[data-testid="leads-import-file"]', fixturePath);

    await Promise.all([
      page.waitForURL(/\/admin\/leads(\?|$)/, { timeout: 15000 }),
      page.getByTestId('leads-import-submit').click(),
    ]);
    fs.unlinkSync(fixturePath);

    // The leads list page should show our newly created lead titles. Search
    // for the unique stamp via the kanban search to confirm both rows landed.
    await page.goto(`/admin/leads?view_type=kanban`);
    await page.waitForLoadState('networkidle');

    const search = page.locator('input[name="search"]').first();
    await expect(search).toBeVisible({ timeout: 10000 });
    await search.fill(`Importable-${stamp}`);
    await page.waitForResponse(
      (r) => r.url().includes('/admin/leads/get') && r.url().includes(encodeURIComponent(`Importable-${stamp}`)),
      { timeout: 10000 }
    );

    // Two cards for the two rows we imported, each appearing as a lead title.
    const matches = page.locator(`text=Importable-${stamp}`);
    await expect(matches.first()).toBeVisible({ timeout: 10000 });
    expect(await matches.count()).toBeGreaterThanOrEqual(2);
  });
});
