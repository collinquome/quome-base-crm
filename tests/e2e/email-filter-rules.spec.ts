import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

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

test.describe('Smart Email Filtering (T045)', () => {
  let accountId: number;

  test('POST /email-accounts creates test account', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'filter-test@example.com',
        imap_host: 'imap.example.com',
        imap_username: 'filter-test@example.com',
        imap_password: 'testpass',
        smtp_host: 'smtp.example.com',
        smtp_username: 'filter-test@example.com',
        smtp_password: 'testpass',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    accountId = body.data.id;
    expect(body.data.contact_only).toBe(true);
    expect(body.data.filter_rules).toEqual([]);
  });

  test('GET /email-accounts/:id/filter-rules returns defaults', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.contact_only).toBe(true);
    expect(body.data.filter_rules).toEqual([]);
  });

  test('PUT /email-accounts/:id/filter-rules sets block_domain rule', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.put(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
      data: {
        filter_rules: [
          { type: 'block_domain', value: 'spam.com' },
        ],
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.filter_rules).toHaveLength(1);
    expect(body.data.filter_rules[0].type).toBe('block_domain');
    expect(body.data.filter_rules[0].value).toBe('spam.com');
  });

  test('PUT /email-accounts/:id/filter-rules sets multiple rules', async () => {
    expect(accountId).toBeTruthy();
    const rules = [
      { type: 'block_domain', value: 'spam.com' },
      { type: 'block_sender', value: 'noreply@marketing.com' },
      { type: 'block_subject_pattern', value: 'unsubscribe' },
      { type: 'allow_domain', value: 'trusted.com' },
      { type: 'allow_sender', value: 'vip@important.com' },
    ];
    const res = await api.put(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
      data: { filter_rules: rules },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.filter_rules).toHaveLength(5);
  });

  test('PUT /email-accounts/:id/filter-rules toggles contact_only', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.put(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
      data: { contact_only: false },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.contact_only).toBe(false);
  });

  test('GET /email-accounts/:id/filter-rules reflects updates', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.contact_only).toBe(false);
    expect(body.data.filter_rules.length).toBeGreaterThanOrEqual(1);
  });

  test('GET /email-accounts/:id includes filter fields', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('contact_only');
    expect(body.data).toHaveProperty('filter_rules');
  });

  test('validates filter rule type', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.put(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
      data: {
        filter_rules: [
          { type: 'invalid_type', value: 'test' },
        ],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('validates filter rule value required', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.put(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: authHeaders(),
      data: {
        filter_rules: [
          { type: 'block_domain' },
        ],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('filter-rules requires authentication', async ({ playwright }) => {
    expect(accountId).toBeTruthy();
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get(`/api/v1/email-accounts/${accountId}/filter-rules`, {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('filter-rules 404 for non-existent account', async () => {
    const res = await api.get('/api/v1/email-accounts/999999/filter-rules', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('DELETE /email-accounts/:id cleans up test account', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.delete(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });
});
