import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8190';

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // Login
  await page.goto(`${BASE}/admin/login`);
  await page.waitForSelector('input[name="email"]', { timeout: 15000 });
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin\/(?!login)/, { timeout: 15000 });
  console.log('Logged in');

  const dir = 'test-results/verification';

  // 1. Dashboard with analytics, timeframe buttons, and user filter
  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${dir}/01-dashboard-analytics.png`, fullPage: false });
  console.log('01. Dashboard Analytics captured');

  // 2. Action Stream page
  await page.goto(`${BASE}/admin/action-stream`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/02-action-stream.png`, fullPage: false });
  console.log('02. Action Stream captured');

  // 3. Team Stream page
  await page.goto(`${BASE}/admin/team-stream`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/03-team-stream.png`, fullPage: false });
  console.log('03. Team Stream captured');

  // 4. Leads kanban (pipeline stages)
  await page.goto(`${BASE}/admin/leads`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${dir}/04-pipeline-stages-kanban.png`, fullPage: false });
  console.log('04. Pipeline Kanban captured');

  // 5. Lead creation form (Premium field, Lead Type, Source dropdowns)
  await page.goto(`${BASE}/admin/leads/create`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/05-lead-create-form.png`, fullPage: true });
  console.log('05. Lead Create Form captured');

  // 6. Products list (simplified - no SKU, no inventory columns)
  await page.goto(`${BASE}/admin/products`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/06-products-simplified.png`, fullPage: false });
  console.log('06. Products list captured');

  // 7. Settings > Roles (Producer + Manager)
  await page.goto(`${BASE}/admin/settings/roles`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/07-roles-list.png`, fullPage: false });
  console.log('07. Roles captured');

  // 8. Login page (shows Union Bay Risk branding)
  const loginContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const loginPage = await loginContext.newPage();
  await loginPage.goto(`${BASE}/admin/login`);
  await loginPage.waitForLoadState('networkidle');
  await loginPage.waitForTimeout(1000);
  await loginPage.screenshot({ path: `${dir}/08-branding-login.png`, fullPage: false });
  console.log('08. Branding/Login captured');
  await loginContext.close();

  // 9. Forgot Password page (branding)
  const fpContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const fpPage = await fpContext.newPage();
  await fpPage.goto(`${BASE}/admin/forget-password`);
  await fpPage.waitForLoadState('networkidle');
  await fpPage.waitForTimeout(1000);
  await fpPage.screenshot({ path: `${dir}/09-branding-forgot-password.png`, fullPage: false });
  console.log('09. Forgot Password branding captured');
  await fpContext.close();

  // 10. Lead detail page - shows timeline, lead type toggle, premium field
  // First get a lead ID
  await page.goto(`${BASE}/admin/leads`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  // Try to click on the first lead in the kanban
  const leadLink = page.locator('.lead-card a, .kanban-item a, [class*="lead"] a').first();
  if (await leadLink.count() > 0) {
    await leadLink.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${dir}/10-lead-detail-timeline.png`, fullPage: true });
    console.log('10. Lead Detail/Timeline captured');
  } else {
    // Try navigating to leads list view and clicking first lead
    await page.goto(`${BASE}/admin/leads?view_type=table`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    const tableLink = page.locator('table a, .datagrid a, td a').first();
    if (await tableLink.count() > 0) {
      await tableLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);
      await page.screenshot({ path: `${dir}/10-lead-detail-timeline.png`, fullPage: true });
      console.log('10. Lead Detail/Timeline captured');
    } else {
      console.log('10. SKIP - no leads found for detail view');
    }
  }

  // 11. Report builder page
  await page.goto(`${BASE}/admin/reports/builder`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/11-report-builder.png`, fullPage: false });
  console.log('11. Report Builder captured');

  // 12. Settings > Sources (custom source types)
  await page.goto(`${BASE}/admin/settings/sources`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/12-source-types.png`, fullPage: false });
  console.log('12. Source Types captured');

  // 13. Settings > Pipelines
  await page.goto(`${BASE}/admin/settings/pipelines`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${dir}/13-pipeline-settings.png`, fullPage: false });
  console.log('13. Pipeline Settings captured');

  // 14. 404 error page (branding)
  await page.goto(`${BASE}/admin/nonexistent-page-for-screenshot`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: `${dir}/14-error-page-branding.png`, fullPage: false });
  console.log('14. Error page branding captured');

  await browser.close();
  console.log('\nAll screenshots captured!');
}

main().catch(console.error);
