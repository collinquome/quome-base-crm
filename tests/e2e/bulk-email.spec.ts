import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let contactId: number;
let contactId2: number;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  // Create test contacts with unique emails
  const ts = Date.now();
  const c1 = await api.post('/api/v1/contacts', {
    headers: { ...authHeaders(), Accept: 'application/json' },
    data: { name: `Bulk Test A ${ts}`, emails: [{ value: `bulka-${ts}@example.com`, label: 'work' }] },
  });
  expect(c1.status()).toBe(201);
  contactId = (await c1.json()).data.id;

  const c2 = await api.post('/api/v1/contacts', {
    headers: { ...authHeaders(), Accept: 'application/json' },
    data: { name: `Bulk Test B ${ts}`, emails: [{ value: `bulkb-${ts}@example.com`, label: 'work' }] },
  });
  expect(c2.status()).toBe(201);
  contactId2 = (await c2.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}` };
}

test.describe('Bulk Email - Send', () => {
  test('POST /emails/bulk sends to multiple contacts', async () => {
    const res = await api.post('/api/v1/emails/bulk', {
      headers: authHeaders(),
      data: {
        subject: 'Hello {{name}}',
        body: '<p>Hi {{first_name}}, this is a bulk email test.</p>',
        contact_ids: [contactId, contactId2],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('sent');
    expect(body.data).toHaveProperty('skipped');
    expect(body.data).toHaveProperty('emails');
    expect(body.data.emails).toBeInstanceOf(Array);
    expect(body.data.sent).toBeGreaterThanOrEqual(1);
    expect(body.data.scheduled).toBe(false);

    // Check that each email has tracking info
    for (const email of body.data.emails) {
      expect(email).toHaveProperty('email_id');
      expect(email).toHaveProperty('contact_id');
      expect(email).toHaveProperty('tracking_id');
    }
  });

  test('supports merge fields in subject and body', async () => {
    const res = await api.post('/api/v1/emails/bulk', {
      headers: authHeaders(),
      data: {
        subject: 'Offer for {{name}}',
        body: '<p>Dear {{first_name}}, we have a special offer for {{email}}.</p>',
        contact_ids: [contactId],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.sent).toBeGreaterThanOrEqual(1);
  });

  test('supports scheduled bulk send', async () => {
    const futureDate = new Date(Date.now() + 48 * 60 * 60 * 1000)
      .toISOString().replace('T', ' ').substring(0, 19);

    const res = await api.post('/api/v1/emails/bulk', {
      headers: authHeaders(),
      data: {
        subject: 'Scheduled Bulk {{name}}',
        body: '<p>Scheduled test.</p>',
        contact_ids: [contactId],
        send_at: futureDate,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.scheduled).toBe(true);
  });

  test('handles bulk send with non-existent contact id', async () => {
    // Use a very high non-existent contact ID to test skip/error handling
    const res = await api.post('/api/v1/emails/bulk', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        subject: 'Test',
        body: '<p>Test</p>',
        contact_ids: [999999],
      },
    });
    // API should either skip the invalid contact or return an error
    expect([200, 201, 422]).toContain(res.status());
  });
});

test.describe('Bulk Email - Limits', () => {
  test('GET /emails/bulk/limits returns daily send stats', async () => {
    const res = await api.get('/api/v1/emails/bulk/limits', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('daily_limit');
    expect(body.data).toHaveProperty('sent_today');
    expect(body.data).toHaveProperty('remaining');
    expect(body.data.daily_limit).toBe(450);
    expect(typeof body.data.sent_today).toBe('number');
    expect(typeof body.data.remaining).toBe('number');
  });
});

test.describe('Bulk Email - Validation', () => {
  test('rejects empty contact_ids', async () => {
    const res = await api.post('/api/v1/emails/bulk', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        subject: 'Test',
        body: '<p>Test</p>',
        contact_ids: [],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects missing subject', async () => {
    const res = await api.post('/api/v1/emails/bulk', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        body: '<p>Test</p>',
        contact_ids: [contactId],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects missing body', async () => {
    const res = await api.post('/api/v1/emails/bulk', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        subject: 'Test',
        contact_ids: [contactId],
      },
    });
    expect(res.status()).toBe(422);
  });
});
