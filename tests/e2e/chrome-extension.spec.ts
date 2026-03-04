import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const EXT_DIR = path.resolve(__dirname, '../../extension');

test.describe('Chrome Extension - Project Structure', () => {
  test('manifest.json exists and is valid Manifest V3', () => {
    const manifestPath = path.join(EXT_DIR, 'manifest.json');
    expect(fs.existsSync(manifestPath)).toBeTruthy();

    const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
    expect(manifest.manifest_version).toBe(3);
    expect(manifest.name).toBeTruthy();
    expect(manifest.version).toBeTruthy();
    expect(manifest.action).toBeDefined();
    expect(manifest.action.default_popup).toBeTruthy();
    expect(manifest.background.service_worker).toBeTruthy();
    expect(manifest.background.type).toBe('module');
  });

  test('manifest declares required permissions', () => {
    const manifest = JSON.parse(
      fs.readFileSync(path.join(EXT_DIR, 'manifest.json'), 'utf-8')
    );
    expect(manifest.permissions).toContain('activeTab');
    expect(manifest.permissions).toContain('storage');
    expect(manifest.permissions).toContain('scripting');
  });

  test('manifest has content scripts for LinkedIn, Twitter, Gmail', () => {
    const manifest = JSON.parse(
      fs.readFileSync(path.join(EXT_DIR, 'manifest.json'), 'utf-8')
    );
    const contentScripts = manifest.content_scripts;
    expect(contentScripts).toBeInstanceOf(Array);
    expect(contentScripts.length).toBeGreaterThanOrEqual(3);

    const allMatches = contentScripts.flatMap((cs: any) => cs.matches);
    expect(allMatches.some((m: string) => m.includes('linkedin.com'))).toBeTruthy();
    expect(allMatches.some((m: string) => m.includes('twitter.com') || m.includes('x.com'))).toBeTruthy();
    expect(allMatches.some((m: string) => m.includes('mail.google.com'))).toBeTruthy();
  });

  test('package.json has Vue 3 and Vite dependencies', () => {
    const pkg = JSON.parse(
      fs.readFileSync(path.join(EXT_DIR, 'package.json'), 'utf-8')
    );
    const allDeps = { ...pkg.dependencies, ...pkg.devDependencies };
    expect(allDeps).toHaveProperty('vue');
    expect(allDeps).toHaveProperty('vite');
    expect(allDeps).toHaveProperty('@vitejs/plugin-vue');
    expect(allDeps).toHaveProperty('vitest');
  });

  test('vite.config.js exists with correct entry points', () => {
    const configPath = path.join(EXT_DIR, 'vite.config.js');
    expect(fs.existsSync(configPath)).toBeTruthy();

    const content = fs.readFileSync(configPath, 'utf-8');
    expect(content).toContain('popup');
    expect(content).toContain('service-worker');
    expect(content).toContain('content-linkedin');
    expect(content).toContain('content-twitter');
    expect(content).toContain('content-gmail');
  });

  test('popup Vue components exist', () => {
    const popupFiles = ['main.js', 'Popup.vue', 'LoginForm.vue', 'CaptureForm.vue'];
    for (const file of popupFiles) {
      const filePath = path.join(EXT_DIR, 'popup', file);
      expect(fs.existsSync(filePath), `Missing ${file}`).toBeTruthy();
    }
  });

  test('Popup.vue imports LoginForm and CaptureForm', () => {
    const popupContent = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'Popup.vue'), 'utf-8'
    );
    expect(popupContent).toContain("import LoginForm from");
    expect(popupContent).toContain("import CaptureForm from");
    expect(popupContent).toContain('chrome.storage.local');
  });

  test('LoginForm.vue has CRM auth functionality', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'LoginForm.vue'), 'utf-8'
    );
    expect(content).toContain('api/v1/auth/login');
    expect(content).toContain("emit('login'");
    expect(content).toContain('apiUrl');
    expect(content).toContain('password');
  });

  test('CaptureForm.vue has contact fields and save', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'CaptureForm.vue'), 'utf-8'
    );
    expect(content).toContain('form.name');
    expect(content).toContain('form.email');
    expect(content).toContain('form.phone');
    expect(content).toContain("emit('capture'");
  });

  test('service worker handles messages and API calls', () => {
    const swPath = path.join(EXT_DIR, 'background', 'service-worker.js');
    expect(fs.existsSync(swPath)).toBeTruthy();

    const content = fs.readFileSync(swPath, 'utf-8');
    expect(content).toContain('chrome.runtime.onMessage.addListener');
    expect(content).toContain('SCRAPE_PAGE');
    expect(content).toContain('CREATE_CONTACT');
    expect(content).toContain('GET_AUTH');
    expect(content).toContain('api/v1/contacts');
  });

  test('content scripts exist for all platforms', () => {
    const scripts = ['generic.js', 'linkedin.js', 'twitter.js', 'gmail.js'];
    for (const script of scripts) {
      const scriptPath = path.join(EXT_DIR, 'content-scripts', script);
      expect(fs.existsSync(scriptPath), `Missing ${script}`).toBeTruthy();
    }
  });

  test('LinkedIn scraper extracts profile data', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'linkedin.js'), 'utf-8'
    );
    expect(content).toContain("source: 'linkedin'");
    expect(content).toContain('contact.name');
    expect(content).toContain('contact.jobTitle');
    expect(content).toContain('contact.organization');
    expect(content).toContain('__crmScrapedContact');
  });

  test('generic scraper handles schema.org, emails, phones', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('application/ld+json');
    expect(content).toContain('mailto:');
    expect(content).toContain('emailRegex');
    expect(content).toContain('socialLinks');
  });

  test('popup.html exists with mount point', () => {
    const htmlPath = path.join(EXT_DIR, 'public', 'popup.html');
    expect(fs.existsSync(htmlPath)).toBeTruthy();

    const content = fs.readFileSync(htmlPath, 'utf-8');
    expect(content).toContain('id="app"');
    expect(content).toContain('popup/main.js');
  });
});
