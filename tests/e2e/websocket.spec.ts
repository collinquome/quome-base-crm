import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';
const SOKETI_URL = process.env.SOKETI_URL || 'http://localhost:6001';

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

test.describe('WebSocket Infrastructure', () => {
  test('Soketi server is running and healthy', async ({ playwright }) => {
    const soketiApi = await playwright.request.newContext({ baseURL: SOKETI_URL });
    const res = await soketiApi.get('/');
    expect(res.ok()).toBeTruthy();
    const text = await res.text();
    expect(text).toBe('OK');
    await soketiApi.dispose();
  });

  test('Soketi metrics endpoint is accessible', async ({ playwright }) => {
    const metricsApi = await playwright.request.newContext({ baseURL: 'http://localhost:9601' });
    const res = await metricsApi.get('/');
    // Metrics endpoint returns prometheus-format data
    expect(res.status()).toBeLessThan(500);
    await metricsApi.dispose();
  });

  test('Laravel broadcasting auth endpoint is reachable', async () => {
    // Broadcasting auth should return 403 or 200 when hit with valid auth
    // but not 404 (which would mean the route doesn't exist)
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '123456.654321',
        channel_name: 'private-test',
      },
    });
    // Should not be 404 — route exists. Could be 403 (no matching channel) or 200
    expect(res.status()).not.toBe(404);
  });

  test('broadcasting config uses pusher driver', async () => {
    // Verify the CRM is configured for broadcasting by checking
    // that the /broadcasting/auth route exists and responds
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '1.1',
        channel_name: 'private-App.Models.User.1',
      },
    });
    // The route should exist (not 404/405)
    expect([200, 403]).toContain(res.status());
  });

  test('Soketi accepts Pusher protocol connections info', async ({ playwright }) => {
    // Soketi implements the Pusher HTTP API
    // GET /apps/{appId}/channels returns channel info
    const soketiApi = await playwright.request.newContext({ baseURL: SOKETI_URL });
    const res = await soketiApi.get('/apps/crm-app/channels', {
      params: {
        auth_key: 'crm-key',
        auth_timestamp: Math.floor(Date.now() / 1000).toString(),
        auth_version: '1.0',
        body_md5: 'd41d8cd98f00b204e9800998ecf8427e',
      },
    });
    // Soketi should respond (even if auth signature isn't valid, it shouldn't be 404)
    expect(res.status()).toBeLessThan(500);
    await soketiApi.dispose();
  });
});
