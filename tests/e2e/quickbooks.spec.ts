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

  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: {
      name: `QB Test ${ts}`,
      emails: [{ value: `qb-${ts}@example.com`, label: 'work' }],
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

test.describe('QuickBooks Integration - Status', () => {
  test('GET /integrations/quickbooks/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/quickbooks/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(false);
  });
});

test.describe('QuickBooks Integration - OAuth', () => {
  test('POST /integrations/quickbooks/auth-url validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/auth-url', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/quickbooks/auth-url returns authorization URL', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/auth-url', {
      headers: authHeaders(),
      data: {
        client_id: 'test-client-id',
        client_secret: 'test-client-secret',
        redirect_uri: 'http://localhost:8190/api/v1/integrations/quickbooks/callback',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.auth_url).toContain('appcenter.intuit.com');
    expect(body.data.auth_url).toContain('test-client-id');
    expect(body.data.auth_url).toContain('oauth2');
  });

  test('POST /integrations/quickbooks/callback validates code and realm_id', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/callback', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/quickbooks/disconnect works', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('QuickBooks Integration - Invoices', () => {
  test('POST /integrations/quickbooks/invoices validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/invoices', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/quickbooks/invoices rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/invoices', {
      headers: authHeaders(),
      data: {
        contact_id: contactId,
        line_items: [
          { description: 'Consulting', amount: 1000 },
        ],
      },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });

  test('POST /integrations/quickbooks/invoices validates line_items structure', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/invoices', {
      headers: authHeaders(),
      data: {
        contact_id: contactId,
        line_items: [{ invalid: true }],
      },
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('QuickBooks Integration - Customer Sync', () => {
  test('POST /integrations/quickbooks/sync-customer validates contact_id', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/sync-customer', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/quickbooks/sync-customer rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/quickbooks/sync-customer', {
      headers: authHeaders(),
      data: { contact_id: contactId },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('QuickBooks Integration - Contact Syncs', () => {
  test('GET /integrations/quickbooks/contacts/{id}/syncs returns empty for new contact', async () => {
    const res = await api.get(
      `/api/v1/integrations/quickbooks/contacts/${contactId}/syncs`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(0);
  });
});

test.describe('QuickBooks Integration - Auth Required', () => {
  test('endpoints require authentication', async () => {
    const res = await api.get('/api/v1/integrations/quickbooks/status', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
