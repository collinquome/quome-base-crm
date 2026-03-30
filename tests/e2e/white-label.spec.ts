import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('White Label Settings', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can fetch white-label settings via API', async ({ page }) => {
    const response = await page.request.get('/admin/api/white-label');
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toBeTruthy();
    expect(json.data.app_name).toBeTruthy();
    expect(json.data.primary_color).toBeTruthy();
  });

  test('can update app name via API', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        app_name: 'Test CRM Brand',
      },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.app_name).toBe('Test CRM Brand');

    // Reset back
    await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: { app_name: 'CRM' },
    });
  });

  test('can update brand colors via API', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: '#FF5733',
        secondary_color: '#33FF57',
        accent_color: '#3357FF',
      },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.primary_color).toBe('#FF5733');
    expect(json.data.secondary_color).toBe('#33FF57');
    expect(json.data.accent_color).toBe('#3357FF');

    // Reset
    await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: '#1E40AF',
        secondary_color: '#7C3AED',
        accent_color: '#F59E0B',
      },
    });
  });

  test('dynamic CSS endpoint returns custom properties', async ({ page }) => {
    const response = await page.request.get('/white-label/css');
    expect(response.ok()).toBeTruthy();
    const css = await response.text();
    expect(css).toContain('--wl-primary-color');
    expect(css).toContain('--wl-secondary-color');
    expect(css).toContain('--wl-accent-color');
  });

  test('CSS custom properties render in page after color change', async ({ page }) => {
    // Update primary color
    await page.request.post('/admin/api/white-label', {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      data: { primary_color: '#E11D48' },
    });

    // Load dashboard and check CSS vars are in the page
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const html = await page.content();
    expect(html).toContain('--wl-primary-color: #E11D48');
    expect(html).toContain('--wl-secondary-color');
    expect(html).toContain('--wl-accent-color');

    // Reset
    await page.request.post('/admin/api/white-label', {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      data: { primary_color: '#1E40AF' },
    });
  });

  test('login page reflects white-label branding', async ({ page }) => {
    // Set a custom app name
    await page.request.post('/admin/api/white-label', {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      data: { app_name: 'MyBrand CRM' },
    });

    // Check login page (need a fresh non-authed page)
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    const html = await page.content();
    // The CSS vars should be present on login page too
    expect(html).toContain('--wl-primary-color');

    // Reset
    await page.request.post('/admin/api/white-label', {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      data: { app_name: 'CRM' },
    });
  });

  test('rejects invalid color format', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: 'not-a-color-this-is-way-too-long',
      },
    });
    // Should fail validation (max:7)
    expect(response.status()).toBe(422);
  });

  test('Union Bay Risk branding can be applied and verified', async ({ page }) => {
    // Apply Union Bay Risk branding
    const applyRes = await page.request.post('/admin/api/white-label', {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      data: {
        app_name: 'Union Bay Risk',
        primary_color: '#1E3A5F',
        secondary_color: '#2C5282',
        accent_color: '#E5A100',
        email_sender_name: 'Union Bay Risk',
      },
    });
    expect(applyRes.ok()).toBeTruthy();

    // Verify it stuck
    const response = await page.request.get('/admin/api/white-label');
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.app_name).toBe('Union Bay Risk');
    expect(json.data.primary_color).toBe('#1E3A5F');
  });

  test('Union Bay Risk logo files exist', async ({ page }) => {
    const logo = await page.request.get('/demo-brand/logo.png');
    expect(logo.ok()).toBeTruthy();
    expect(logo.headers()['content-type']).toContain('image/png');

    const favicon = await page.request.get('/demo-brand/favicon.png');
    expect(favicon.ok()).toBeTruthy();
  });
});
