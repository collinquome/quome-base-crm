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
  return { Authorization: `Bearer ${token}` };
}

// Schedule a date in the future for test emails
function futureDate(hoursFromNow = 24): string {
  const d = new Date(Date.now() + hoursFromNow * 60 * 60 * 1000);
  return d.toISOString().replace('T', ' ').substring(0, 19);
}

test.describe('Scheduled Emails - CRUD', () => {
  let scheduledId: number;

  test('POST /scheduled-emails creates a scheduled email', async () => {
    const res = await api.post('/api/v1/scheduled-emails', {
      headers: authHeaders(),
      data: {
        subject: `Test Scheduled ${Date.now()}`,
        reply: '<p>This is a scheduled test email.</p>',
        to: [{ address: 'test@example.com', name: 'Test User' }],
        scheduled_at: futureDate(48),
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.status).toBe('pending');
    expect(body.data).toHaveProperty('scheduled_at');
    scheduledId = body.data.id;
  });

  test('GET /scheduled-emails lists scheduled emails', async () => {
    const res = await api.get('/api/v1/scheduled-emails', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    const found = body.data.find((e: any) => e.id === scheduledId);
    expect(found).toBeTruthy();
    expect(found.status).toBe('pending');
  });

  test('GET /scheduled-emails supports status filter', async () => {
    const res = await api.get('/api/v1/scheduled-emails?status=pending', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    for (const item of body.data) {
      expect(item.status).toBe('pending');
    }
  });

  test('GET /scheduled-emails/:id returns single scheduled email', async () => {
    const res = await api.get(`/api/v1/scheduled-emails/${scheduledId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(scheduledId);
    expect(body.data).toHaveProperty('subject');
    expect(body.data).toHaveProperty('body');
  });

  test('PUT /scheduled-emails/:id/reschedule updates the send time', async () => {
    const newDate = futureDate(72);
    const res = await api.put(`/api/v1/scheduled-emails/${scheduledId}/reschedule`, {
      headers: authHeaders(),
      data: { scheduled_at: newDate },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.status).toBe('pending');
  });

  test('POST /scheduled-emails/:id/cancel cancels a pending email', async () => {
    const res = await api.post(`/api/v1/scheduled-emails/${scheduledId}/cancel`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('cancelled');
  });

  test('cannot cancel an already-cancelled email', async () => {
    const res = await api.post(`/api/v1/scheduled-emails/${scheduledId}/cancel`, {
      headers: { ...authHeaders(), Accept: 'application/json' },
    });
    expect(res.status()).toBe(422);
  });

  test('cannot reschedule a cancelled email', async () => {
    const res = await api.put(`/api/v1/scheduled-emails/${scheduledId}/reschedule`, {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: { scheduled_at: futureDate(96) },
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Scheduled Emails - Validation', () => {
  test('rejects scheduling in the past', async () => {
    const res = await api.post('/api/v1/scheduled-emails', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        subject: 'Past email',
        reply: '<p>Body</p>',
        to: [{ address: 'test@example.com', name: 'Test' }],
        scheduled_at: '2020-01-01 00:00:00',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects missing required fields', async () => {
    const res = await api.post('/api/v1/scheduled-emails', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: { subject: 'Missing fields' },
    });
    expect(res.status()).toBe(422);
  });

  test('GET non-existent scheduled email returns 404', async () => {
    const res = await api.get('/api/v1/scheduled-emails/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});
