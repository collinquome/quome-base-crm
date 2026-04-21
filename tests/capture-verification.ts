import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8190';

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // Login
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin/, { timeout: 15000 });
  console.log('Logged in');

  // 1. Dashboard with timeframe buttons + user filter
  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/verification/dashboard-analytics.png', fullPage: false });
  console.log('1. Dashboard captured');

  // 2. Action Stream page
  await page.goto(`${BASE}/admin/action-stream`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/verification/action-stream.png', fullPage: false });
  console.log('2. Action Stream captured');

  // 3. Leads kanban (pipeline stages)
  await page.goto(`${BASE}/admin/leads`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'test-results/verification/pipeline-stages-kanban.png', fullPage: false });
  console.log('3. Pipeline Kanban captured');

  // 4. Lead creation form (shows Premium, Lead Type, Source dropdowns)
  await page.goto(`${BASE}/admin/leads/create`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/verification/lead-create-form.png', fullPage: true });
  console.log('4. Lead Create Form captured');

  // 5. Products list (simplified - no SKU, no inventory columns)
  await page.goto(`${BASE}/admin/products`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/verification/products-simplified.png', fullPage: false });
  console.log('5. Products list captured');

  // 6. Settings > Roles (Producer + Manager)
  await page.goto(`${BASE}/admin/settings/roles`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/verification/roles-list.png', fullPage: false });
  console.log('6. Roles captured');

  // 7. Login page (shows Union Bay Risk branding)
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/verification/branding-login.png', fullPage: false });
  console.log('7. Branding/Login captured');

  // 8. Team Stream page
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin/, { timeout: 15000 });
  await page.goto(`${BASE}/admin/team-stream`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/verification/team-stream.png', fullPage: false });
  console.log('8. Team Stream captured');

  await browser.close();
  console.log('All screenshots captured!');
}

main().catch(console.error);
