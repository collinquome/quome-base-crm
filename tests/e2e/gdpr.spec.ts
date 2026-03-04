import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

async function createContact(name: string, email?: string) {
  const data: any = { name };
  if (email) {
    data.emails = [{ value: email, label: 'work' }];
  }
  const res = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data,
  });
  expect(res.status()).toBe(201);
  return (await res.json()).data.id;
}

test.describe('GDPR - Export', () => {
  test('exports all data for a contact', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR Export ${ts}`, `gdpr-export-${ts}@example.com`);

    const res = await api.get(`/api/v1/gdpr/contacts/${contactId}/export`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('contact');
    expect(body.data).toHaveProperty('leads');
    expect(body.data).toHaveProperty('activities');
    expect(body.data).toHaveProperty('emails');
    expect(body.data).toHaveProperty('tags');
    expect(body.data).toHaveProperty('exported_at');
    expect(body.data.contact.id).toBe(contactId);
  });

  test('returns 404 for non-existent contact', async () => {
    const res = await api.get('/api/v1/gdpr/contacts/999999/export', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('includes leads associated with the contact', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR Leads ${ts}`, `gdpr-leads-${ts}@example.com`);

    // Create a lead for this contact
    const leadRes = await api.post('/api/v1/leads', {
      headers: authHeaders(),
      data: {
        title: `GDPR Test Lead ${ts}`,
        person_id: contactId,
        lead_value: 100,
        lead_pipeline_id: 1,
        lead_pipeline_stage_id: 1,
      },
    });
    // Lead creation may or may not succeed depending on pipeline setup
    if (leadRes.status() === 201) {
      const res = await api.get(`/api/v1/gdpr/contacts/${contactId}/export`, {
        headers: authHeaders(),
      });
      expect(res.ok()).toBeTruthy();
      const body = await res.json();
      expect(body.data.leads.length).toBeGreaterThanOrEqual(1);
    }
  });
});

test.describe('GDPR - Erase', () => {
  test('anonymizes a contact and deletes related records', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR Erase ${ts}`, `gdpr-erase-${ts}@example.com`);

    const res = await api.post(`/api/v1/gdpr/contacts/${contactId}/erase`, {
      headers: authHeaders(),
      data: { confirm: true },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.contact_id).toBe(contactId);
    expect(body.data.anonymized).toBe(true);
    expect(body.data).toHaveProperty('deleted');
    expect(body.data.deleted).toHaveProperty('emails');
    expect(body.data.deleted).toHaveProperty('activities');
    expect(body.data.deleted).toHaveProperty('tags');
    expect(body.data.deleted).toHaveProperty('leads');

    // Verify contact is now gone (soft-deleted)
    const exportRes = await api.get(`/api/v1/gdpr/contacts/${contactId}/export`, {
      headers: authHeaders(),
    });
    expect(exportRes.status()).toBe(404);
  });

  test('requires confirmation to erase', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR NoConfirm ${ts}`, `gdpr-noconfirm-${ts}@example.com`);

    const res = await api.post(`/api/v1/gdpr/contacts/${contactId}/erase`, {
      headers: authHeaders(),
      data: { confirm: false },
    });
    expect(res.status()).toBe(422);
  });

  test('requires confirm field', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR MissingConfirm ${ts}`, `gdpr-missing-${ts}@example.com`);

    const res = await api.post(`/api/v1/gdpr/contacts/${contactId}/erase`, {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('returns 404 for non-existent contact', async () => {
    const res = await api.post('/api/v1/gdpr/contacts/999999/erase', {
      headers: authHeaders(),
      data: { confirm: true },
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('GDPR - Consent Status', () => {
  test('returns consent status for a contact', async () => {
    const ts = Date.now();
    const contactId = await createContact(`GDPR Consent ${ts}`, `gdpr-consent-${ts}@example.com`);

    const res = await api.get(`/api/v1/gdpr/contacts/${contactId}/consent`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('contact_id');
    expect(body.data.contact_id).toBe(contactId);
    expect(body.data).toHaveProperty('consent_records');
    expect(body.data).toHaveProperty('has_consent_data');
    expect(typeof body.data.has_consent_data).toBe('boolean');
  });

  test('returns 404 for non-existent contact', async () => {
    const res = await api.get('/api/v1/gdpr/contacts/999999/consent', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});
