import { Page } from '@playwright/test';

export async function goToContacts(page: Page) {
  await page.goto('/admin/contacts/persons');
  await page.waitForLoadState('networkidle');
}

export async function goToOrganizations(page: Page) {
  await page.goto('/admin/contacts/organizations');
  await page.waitForLoadState('networkidle');
}

export async function goToLeads(page: Page) {
  await page.goto('/admin/leads');
  await page.waitForLoadState('networkidle');
}

export async function goToActivities(page: Page) {
  await page.goto('/admin/activities');
  await page.waitForLoadState('networkidle');
}

export async function goToMail(page: Page) {
  await page.goto('/admin/mail');
  await page.waitForLoadState('networkidle');
}

export async function goToProducts(page: Page) {
  await page.goto('/admin/products');
  await page.waitForLoadState('networkidle');
}

export async function goToQuotes(page: Page) {
  await page.goto('/admin/quotes');
  await page.waitForLoadState('networkidle');
}

export async function goToSettings(page: Page) {
  await page.goto('/admin/settings');
  await page.waitForLoadState('networkidle');
}

export async function goToDashboard(page: Page) {
  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
}
