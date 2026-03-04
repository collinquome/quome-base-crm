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

test.describe('Google Calendar - Status', () => {
  test('GET /integrations/google-calendar/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/google-calendar/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(false);
  });
});

test.describe('Google Calendar - OAuth', () => {
  test('POST /integrations/google-calendar/auth-url validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/auth-url', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/google-calendar/auth-url returns authorization URL', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/auth-url', {
      headers: authHeaders(),
      data: {
        client_id: 'test-google-client',
        client_secret: 'test-google-secret',
        redirect_uri: 'http://localhost:8190/api/v1/integrations/google-calendar/callback',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.auth_url).toContain('accounts.google.com');
    expect(body.data.auth_url).toContain('test-google-client');
  });

  test('POST /integrations/google-calendar/callback validates code', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/callback', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/google-calendar/disconnect works', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('Google Calendar - Events', () => {
  test('GET /integrations/google-calendar/events rejects when not connected', async () => {
    const res = await api.get('/api/v1/integrations/google-calendar/events', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Google Calendar - Sync Activity', () => {
  test('POST /integrations/google-calendar/sync-activity validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/sync-activity', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/google-calendar/sync-activity rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/google-calendar/sync-activity', {
      headers: authHeaders(),
      data: { activity_id: 1 },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Google Calendar - Auth Required', () => {
  test('unauthenticated request returns 401', async () => {
    const res = await api.get('/api/v1/integrations/google-calendar/status', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
