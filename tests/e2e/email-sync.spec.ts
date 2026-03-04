import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let accountId: number;

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
  // Clean up created account
  if (accountId) {
    await api.delete(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
  }
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.describe('Email Sync - Account CRUD', () => {
  test('GET /email-accounts returns empty list initially', async () => {
    const res = await api.get('/api/v1/email-accounts', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('POST /email-accounts validates required fields', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /email-accounts creates account with valid data', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'sync-test@example.com',
        display_name: 'Sync Test',
        provider: 'custom',
        imap_host: 'imap.example.com',
        imap_port: 993,
        imap_encryption: 'ssl',
        imap_username: 'sync-test@example.com',
        imap_password: 'test-password',
        smtp_host: 'smtp.example.com',
        smtp_port: 587,
        smtp_encryption: 'tls',
        smtp_username: 'sync-test@example.com',
        smtp_password: 'test-password',
        sync_days: 14,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.email_address).toBe('sync-test@example.com');
    expect(body.data.display_name).toBe('Sync Test');
    expect(body.data.imap_host).toBe('imap.example.com');
    expect(body.data.status).toBe('active');
    expect(body.data.sync_days).toBe(14);
    // Should not expose passwords
    expect(body.data).not.toHaveProperty('imap_password');
    expect(body.data).not.toHaveProperty('smtp_password');
    accountId = body.data.id;
  });

  test('GET /email-accounts lists created account', async () => {
    const res = await api.get('/api/v1/email-accounts', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const found = body.data.find((a: any) => a.id === accountId);
    expect(found).toBeTruthy();
    expect(found.email_address).toBe('sync-test@example.com');
  });

  test('GET /email-accounts/{id} shows single account', async () => {
    const res = await api.get(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(accountId);
  });

  test('PUT /email-accounts/{id} updates account', async () => {
    const res = await api.put(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
      data: { display_name: 'Updated Name', sync_days: 7 },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.display_name).toBe('Updated Name');
    expect(body.data.sync_days).toBe(7);
  });

  test('GET /email-accounts/{id} returns 404 for missing account', async () => {
    const res = await api.get('/api/v1/email-accounts/99999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('Email Sync - Sync Operations', () => {
  test('POST /email-accounts/{id}/sync triggers sync', async () => {
    const res = await api.post(`/api/v1/email-accounts/${accountId}/sync`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.synced).toBe(true);
    expect(body.data.last_sync_at).toBeTruthy();
  });

  test('GET /email-accounts/{id}/status shows sync status', async () => {
    const res = await api.get(`/api/v1/email-accounts/${accountId}/status`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.account_id).toBe(accountId);
    expect(body.data.status).toBe('active');
    expect(body.data.last_sync_at).toBeTruthy();
    expect(typeof body.data.email_count).toBe('number');
  });

  test('GET /email-accounts/{id}/emails returns email list', async () => {
    const res = await api.get(`/api/v1/email-accounts/${accountId}/emails`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('POST /email-accounts/{id}/sync rejects disabled account', async () => {
    // Disable the account first
    await api.put(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
      data: { status: 'disabled' },
    });

    const res = await api.post(`/api/v1/email-accounts/${accountId}/sync`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);

    // Re-enable
    await api.put(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
      data: { status: 'active' },
    });
  });
});

test.describe('Email Sync - Delete', () => {
  test('DELETE /email-accounts/{id} removes account', async () => {
    // Create a temp account to delete
    const create = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'delete-me@example.com',
        imap_host: 'imap.example.com',
        imap_username: 'delete-me@example.com',
        imap_password: 'pass',
        smtp_host: 'smtp.example.com',
        smtp_username: 'delete-me@example.com',
        smtp_password: 'pass',
      },
    });
    const tempId = (await create.json()).data.id;

    const res = await api.delete(`/api/v1/email-accounts/${tempId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    // Verify gone
    const check = await api.get(`/api/v1/email-accounts/${tempId}`, {
      headers: authHeaders(),
    });
    expect(check.status()).toBe(404);
  });
});

test.describe('Email Sync - Auth Required', () => {
  test('unauthenticated request returns 401', async () => {
    const res = await api.get('/api/v1/email-accounts', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
