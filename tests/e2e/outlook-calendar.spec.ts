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

test.describe('Outlook Calendar - Status', () => {
  test('GET /integrations/outlook-calendar/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/outlook-calendar/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(false);
  });
});

test.describe('Outlook Calendar - OAuth', () => {
  test('POST /integrations/outlook-calendar/auth-url validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/auth-url', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/outlook-calendar/auth-url returns authorization URL', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/auth-url', {
      headers: authHeaders(),
      data: {
        client_id: 'test-outlook-client',
        client_secret: 'test-outlook-secret',
        redirect_uri: 'http://localhost:8190/api/v1/integrations/outlook-calendar/callback',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.auth_url).toContain('login.microsoftonline.com');
    expect(body.data.auth_url).toContain('test-outlook-client');
  });

  test('POST /integrations/outlook-calendar/auth-url supports tenant_id', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/auth-url', {
      headers: authHeaders(),
      data: {
        client_id: 'test-outlook-client',
        client_secret: 'test-outlook-secret',
        redirect_uri: 'http://localhost:8190/api/v1/integrations/outlook-calendar/callback',
        tenant_id: 'my-tenant-id',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.auth_url).toContain('my-tenant-id');
  });

  test('POST /integrations/outlook-calendar/callback validates code', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/callback', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/outlook-calendar/disconnect works', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('Outlook Calendar - Events', () => {
  test('GET /integrations/outlook-calendar/events rejects when not connected', async () => {
    const res = await api.get('/api/v1/integrations/outlook-calendar/events', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Outlook Calendar - Sync Activity', () => {
  test('POST /integrations/outlook-calendar/sync-activity validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/sync-activity', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/outlook-calendar/sync-activity rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/outlook-calendar/sync-activity', {
      headers: authHeaders(),
      data: { activity_id: 1 },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Outlook Calendar - Auth Required', () => {
  test('unauthenticated request returns 401', async () => {
    const res = await api.get('/api/v1/integrations/outlook-calendar/status', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
