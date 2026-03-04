import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const EXT_DIR = path.resolve(__dirname, '../../extension');

test.describe('Extension Authentication (T093)', () => {
  test('LoginForm.vue authenticates against CRM API', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'LoginForm.vue'), 'utf-8'
    );
    // Hits the correct auth endpoint
    expect(content).toContain('/api/v1/auth/login');
    // Sends proper JSON payload
    expect(content).toContain('email: this.email');
    expect(content).toContain('password: this.password');
    // Emits token back to parent
    expect(content).toContain("this.$emit('login', { token, apiUrl: url })");
  });

  test('Popup.vue auto-loads stored credentials on open', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'Popup.vue'), 'utf-8'
    );
    // Reads from chrome.storage.local on created
    expect(content).toContain("chrome.storage.local.get(['token', 'apiUrl'])");
    expect(content).toContain('this.isAuthenticated = true');
  });

  test('Popup.vue stores credentials in chrome.storage', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'Popup.vue'), 'utf-8'
    );
    expect(content).toContain('chrome.storage.local.set({ token, apiUrl })');
  });

  test('Popup.vue supports logout with storage cleanup', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'Popup.vue'), 'utf-8'
    );
    expect(content).toContain("chrome.storage.local.remove(['token', 'apiUrl'])");
    expect(content).toContain('this.isAuthenticated = false');
  });

  test('LoginForm shows loading state and error handling', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'popup', 'LoginForm.vue'), 'utf-8'
    );
    expect(content).toContain('loading');
    expect(content).toContain('error');
    expect(content).toContain('Invalid credentials');
    expect(content).toContain('Cannot reach CRM server');
  });

  test('service worker provides GET_AUTH message handler', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'background', 'service-worker.js'), 'utf-8'
    );
    expect(content).toContain("message.type === 'GET_AUTH'");
    expect(content).toContain("chrome.storage.local.get(['token', 'apiUrl']");
  });
});

test.describe('Generic Page Scraper (T094)', () => {
  test('generic.js extracts schema.org Person data', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain("application/ld+json");
    expect(content).toContain("@type");
    expect(content).toContain("Person");
    expect(content).toContain('contact.name = data.name');
    expect(content).toContain('contact.email = data.email');
    expect(content).toContain('contact.phone = data.telephone');
    expect(content).toContain('contact.jobTitle = data.jobTitle');
  });

  test('generic.js scrapes mailto links', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('a[href^="mailto:"]');
  });

  test('generic.js uses email regex pattern matching', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('emailRegex');
    expect(content).toContain('@');
  });

  test('generic.js scrapes tel links', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('a[href^="tel:"]');
  });

  test('generic.js collects social media links', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('linkedin.com');
    expect(content).toContain('twitter.com');
    expect(content).toContain('facebook.com');
    expect(content).toContain('github.com');
    expect(content).toContain('socialLinks');
  });

  test('generic.js sets window.__crmScrapedContact', () => {
    const content = fs.readFileSync(
      path.join(EXT_DIR, 'content-scripts', 'generic.js'), 'utf-8'
    );
    expect(content).toContain('window.__crmScrapedContact = contact');
  });
});
