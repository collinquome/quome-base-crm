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

test.describe('Speed Dial (T075)', () => {
  let contactId: number;
  let contact2Id: number;

  test('setup: create test contacts', async () => {
    const res1 = await api.post('/api/v1/contacts', {
      headers: authHeaders(),
      data: {
        name: 'Speed Dial Test 1',
        emails: [{ value: 'speed1@example.com', label: 'work' }],
        contact_numbers: [{ value: '+1234567890', label: 'work' }],
      },
    });
    expect(res1.status()).toBe(201);
    contactId = (await res1.json()).data.id;

    const res2 = await api.post('/api/v1/contacts', {
      headers: authHeaders(),
      data: {
        name: 'Speed Dial Test 2',
        emails: [{ value: 'speed2@example.com', label: 'work' }],
        contact_numbers: [{ value: '+0987654321', label: 'work' }],
      },
    });
    expect(res2.status()).toBe(201);
    contact2Id = (await res2.json()).data.id;
  });

  test('GET /speed-dial returns empty favorites initially', async () => {
    const res = await api.get('/api/v1/speed-dial', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('favorites');
    expect(body.data).toHaveProperty('recent');
    expect(body.data.favorites).toBeInstanceOf(Array);
    expect(body.data.recent).toBeInstanceOf(Array);
  });

  test('POST /speed-dial/favorites adds contact to favorites', async () => {
    expect(contactId).toBeTruthy();
    const res = await api.post('/api/v1/speed-dial/favorites', {
      headers: authHeaders(),
      data: { person_id: contactId },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.id).toBe(contactId);
    expect(body.data.name).toBe('Speed Dial Test 1');
    expect(body.data.source).toBe('favorite');
  });

  test('POST /speed-dial/favorites adds second contact', async () => {
    expect(contact2Id).toBeTruthy();
    const res = await api.post('/api/v1/speed-dial/favorites', {
      headers: authHeaders(),
      data: { person_id: contact2Id },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.sort_order).toBe(2);
  });

  test('POST /speed-dial/favorites rejects duplicate', async () => {
    expect(contactId).toBeTruthy();
    const res = await api.post('/api/v1/speed-dial/favorites', {
      headers: authHeaders(),
      data: { person_id: contactId },
    });
    expect(res.status()).toBe(422);
  });

  test('GET /speed-dial includes favorites', async () => {
    const res = await api.get('/api/v1/speed-dial', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.favorites.length).toBeGreaterThanOrEqual(2);
    expect(body.data.favorites[0].source).toBe('favorite');
  });

  test('PUT /speed-dial/reorder changes order', async () => {
    expect(contactId).toBeTruthy();
    expect(contact2Id).toBeTruthy();
    const res = await api.put('/api/v1/speed-dial/reorder', {
      headers: authHeaders(),
      data: { order: [contact2Id, contactId] },
    });
    expect(res.ok()).toBeTruthy();

    // Verify order changed
    const listRes = await api.get('/api/v1/speed-dial', {
      headers: authHeaders(),
    });
    const body = await listRes.json();
    const favIds = body.data.favorites.map((f: any) => f.id);
    expect(favIds[0]).toBe(contact2Id);
    expect(favIds[1]).toBe(contactId);
  });

  test('POST /speed-dial/quick-call/:personId returns call info', async () => {
    expect(contactId).toBeTruthy();
    const res = await api.post(`/api/v1/speed-dial/quick-call/${contactId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.person_id).toBe(contactId);
    expect(body.data.person_name).toBe('Speed Dial Test 1');
    expect(body.data.phone_number).toBe('+1234567890');
    expect(body.data).toHaveProperty('voip_ready');
  });

  test('POST /speed-dial/quick-call/:personId 404 for non-existent', async () => {
    const res = await api.post('/api/v1/speed-dial/quick-call/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('DELETE /speed-dial/favorites/:personId removes favorite', async () => {
    expect(contactId).toBeTruthy();
    const res = await api.delete(`/api/v1/speed-dial/favorites/${contactId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('DELETE /speed-dial/favorites/:personId 404 for already removed', async () => {
    expect(contactId).toBeTruthy();
    const res = await api.delete(`/api/v1/speed-dial/favorites/${contactId}`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('POST /speed-dial/favorites validates person_id', async () => {
    const res = await api.post('/api/v1/speed-dial/favorites', {
      headers: authHeaders(),
      data: { person_id: 999999 },
    });
    expect(res.status()).toBe(422);
  });

  test('speed-dial requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/speed-dial', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('cleanup: remove remaining favorites and contacts', async () => {
    await api.delete(`/api/v1/speed-dial/favorites/${contact2Id}`, {
      headers: authHeaders(),
    });
    await api.delete(`/api/v1/contacts/${contactId}`, {
      headers: authHeaders(),
    });
    await api.delete(`/api/v1/contacts/${contact2Id}`, {
      headers: authHeaders(),
    });
  });
});
