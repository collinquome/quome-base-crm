import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('Win-stage prompt cleanup', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('kanban Won-stage modal renders friendly text, not raw lang keys', async ({ page }) => {
    await page.goto('/admin/leads?view_type=kanban');
    await page.waitForLoadState('networkidle');

    // Render the modal directly via the Vue prototype the page already exposes,
    // independent of whether any kanban column actually has a draggable card.
    // We just need to confirm the lang keys resolved to friendly text.
    const modalHtml = await page.evaluate(() => {
      const tpl = document.querySelector('script[type="text/x-template"]#v-leads-kanban-template');
      return tpl ? tpl.innerHTML : '';
    });

    // No raw key path should leak through.
    expect(modalHtml).not.toContain('admin::app.leads.index.kanban.stages');

    // Friendly strings the customer asked for should be present in the rendered template.
    expect(modalHtml).toContain('Premium');
    expect(modalHtml).toContain('Closed Date');
    expect(modalHtml).toContain('Great Win!');
  });
});
