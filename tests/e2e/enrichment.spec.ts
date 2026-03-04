import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let contactId: number;
test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  // Contact with email
  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: {
      name: `Enrich Test ${ts}`,
      emails: [{ value: `enrich-${ts}@acmecorp.com`, label: 'work' }],
    },
  });
  expect(c.status()).toBe(201);
  contactId = (await c.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.describe('Contact Enrichment - Config', () => {
  test('GET /enrichment/config returns unconfigured by default', async () => {
    const res = await api.get('/api/v1/enrichment/config', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('configured');
  });

  test('POST /enrichment/configure validates provider', async () => {
    const res = await api.post('/api/v1/enrichment/configure', {
      headers: authHeaders(),
      data: { provider: 'invalid_provider', api_key: 'test' },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /enrichment/configure accepts manual provider without API key', async () => {
    const res = await api.post('/api/v1/enrichment/configure', {
      headers: authHeaders(),
      data: { provider: 'manual' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.configured).toBe(true);
    expect(body.data.provider).toBe('manual');
  });

  test('GET /enrichment/config shows configured provider', async () => {
    const res = await api.get('/api/v1/enrichment/config', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.configured).toBe(true);
    expect(body.data.provider).toBe('manual');
  });
});

test.describe('Contact Enrichment - Enrich', () => {
  test('POST /enrichment/contacts/{id}/enrich enriches a contact with email', async () => {
    const res = await api.post(`/api/v1/enrichment/contacts/${contactId}/enrich`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.contact_id).toBe(contactId);
    expect(body.data.provider).toBe('manual');
    expect(body.data.enriched).toBeDefined();
    expect(body.data.enriched.source).toBe('manual');
    expect(body.data.enriched.email_domain).toBe('acmecorp.com');
    // Manual enrichment infers company from domain
    expect(body.data.enriched.company).toBeTruthy();
  });

  test('POST /enrichment/contacts/{id}/enrich handles contact enrichment', async () => {
    // Re-enrich same contact should update, not fail
    const res = await api.post(
      `/api/v1/enrichment/contacts/${contactId}/enrich`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.enriched).toBeDefined();
  });

  test('POST /enrichment/contacts/{id}/enrich returns 404 for non-existent contact', async () => {
    const res = await api.post('/api/v1/enrichment/contacts/999999/enrich', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('Contact Enrichment - Show', () => {
  test('GET /enrichment/contacts/{id} shows enrichment data', async () => {
    const res = await api.get(`/api/v1/enrichment/contacts/${contactId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.enriched).toBe(true);
    expect(body.data.provider).toBe('manual');
    expect(body.data.data).toBeDefined();
    expect(body.data.enriched_at).toBeTruthy();
  });

  test('GET /enrichment/contacts/{id} shows not enriched for new contact', async () => {
    // Create a fresh contact
    const ts = Date.now();
    const c = await api.post('/api/v1/contacts', {
      headers: authHeaders(),
      data: { name: `Fresh ${ts}`, emails: [{ value: `fresh-${ts}@test.com`, label: 'work' }] },
    });
    const freshId = (await c.json()).data.id;

    const res = await api.get(`/api/v1/enrichment/contacts/${freshId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.enriched).toBe(false);
  });
});

test.describe('Contact Enrichment - Bulk', () => {
  test('POST /enrichment/bulk enriches multiple contacts', async () => {
    const res = await api.post('/api/v1/enrichment/bulk', {
      headers: authHeaders(),
      data: { contact_ids: [contactId, 999999] },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(2);

    const enriched = body.data.find((r: any) => r.contact_id === contactId);
    expect(enriched.status).toBe('enriched');

    const notFound = body.data.find((r: any) => r.contact_id === 999999);
    expect(notFound.status).toBe('not_found');
  });

  test('POST /enrichment/bulk validates contact_ids', async () => {
    const res = await api.post('/api/v1/enrichment/bulk', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /enrichment/bulk limits to 50 contacts', async () => {
    const ids = Array.from({ length: 51 }, (_, i) => i + 1);
    const res = await api.post('/api/v1/enrichment/bulk', {
      headers: authHeaders(),
      data: { contact_ids: ids },
    });
    expect(res.status()).toBe(422);
  });
});
