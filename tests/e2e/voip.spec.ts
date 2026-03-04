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
      name: `VoIP Test ${ts}`,
      emails: [{ value: `voip-${ts}@example.com`, label: 'work' }],
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

test.describe('VoIP - Status', () => {
  test('GET /integrations/voip/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/voip/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(false);
  });
});

test.describe('VoIP - Configure', () => {
  test('POST /integrations/voip/configure validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/voip/configure', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/voip/configure validates provider enum', async () => {
    const res = await api.post('/api/v1/integrations/voip/configure', {
      headers: authHeaders(),
      data: {
        voip_provider: 'invalid',
        account_sid: 'test-sid',
        auth_token: 'test-token',
        phone_number: '+15551234567',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/voip/configure succeeds with valid data', async () => {
    const res = await api.post('/api/v1/integrations/voip/configure', {
      headers: authHeaders(),
      data: {
        voip_provider: 'twilio',
        account_sid: 'ACtest123',
        auth_token: 'test-auth-token',
        phone_number: '+15551234567',
        recording: true,
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(true);
    expect(body.data.voip_provider).toBe('twilio');
  });

  test('GET /integrations/voip/status returns connected after configure', async () => {
    const res = await api.get('/api/v1/integrations/voip/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(true);
    expect(body.data.voip_provider).toBe('twilio');
  });

  test('POST /integrations/voip/disconnect works', async () => {
    const res = await api.post('/api/v1/integrations/voip/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('VoIP - Click-to-Call', () => {
  test('POST /integrations/voip/call validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/voip/call', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/voip/call rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/voip/call', {
      headers: authHeaders(),
      data: { contact_id: contactId, phone: '+15559876543' },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('VoIP - Webhook', () => {
  test('POST /integrations/voip/webhook validates call_sid', async () => {
    const res = await api.post('/api/v1/integrations/voip/webhook', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/voip/webhook returns 404 for unknown call', async () => {
    const res = await api.post('/api/v1/integrations/voip/webhook', {
      headers: authHeaders(),
      data: { call_sid: 'nonexistent-sid', status: 'completed' },
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('VoIP - Contact Call History', () => {
  test('GET /integrations/voip/contacts/{id}/calls returns empty', async () => {
    const res = await api.get(
      `/api/v1/integrations/voip/contacts/${contactId}/calls`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(0);
  });
});

test.describe('VoIP - Recording', () => {
  test('GET /integrations/voip/recordings/{id} returns 404 for invalid id', async () => {
    const res = await api.get('/api/v1/integrations/voip/recordings/99999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('VoIP - Auth Required', () => {
  test('unauthenticated request returns 401', async () => {
    const res = await api.get('/api/v1/integrations/voip/status', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
