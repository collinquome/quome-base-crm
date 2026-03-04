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

test.describe('Backup System', () => {
  let backupId: number;

  test('POST /backups creates a new database backup', async () => {
    const res = await api.post('/api/v1/backups', {
      headers: authHeaders(),
      data: { disk: 'local' },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data).toHaveProperty('filename');
    expect(body.data).toHaveProperty('size_bytes');
    expect(body.data.filename).toContain('backup_');
    expect(body.data.filename).toContain('.sql.gz');
    expect(body.data.size_bytes).toBeGreaterThan(0);
    expect(body.data.status).toBe('completed');
    backupId = body.data.id;
  });

  test('GET /backups lists all backups', async () => {
    const res = await api.get('/api/v1/backups', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('data');
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
  });

  test('GET /backups/{id} shows a specific backup', async () => {
    // First ensure we have a backup
    if (!backupId) {
      const createRes = await api.post('/api/v1/backups', {
        headers: authHeaders(),
        data: { disk: 'local' },
      });
      backupId = (await createRes.json()).data.id;
    }

    const res = await api.get(`/api/v1/backups/${backupId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(backupId);
    expect(body.data).toHaveProperty('filename');
    expect(body.data).toHaveProperty('path');
    expect(body.data).toHaveProperty('size_bytes');
  });

  test('GET /backups/{id} returns 404 for non-existent backup', async () => {
    const res = await api.get('/api/v1/backups/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('DELETE /backups/{id} deletes a backup', async () => {
    // Create a backup to delete
    const createRes = await api.post('/api/v1/backups', {
      headers: authHeaders(),
      data: { disk: 'local' },
    });
    const deleteId = (await createRes.json()).data.id;

    const res = await api.delete(`/api/v1/backups/${deleteId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    // Verify it's gone
    const showRes = await api.get(`/api/v1/backups/${deleteId}`, {
      headers: authHeaders(),
    });
    expect(showRes.status()).toBe(404);
  });

  test('DELETE /backups/{id} returns 404 for non-existent backup', async () => {
    const res = await api.delete('/api/v1/backups/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('GET /backups supports pagination', async () => {
    const res = await api.get('/api/v1/backups?per_page=1', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('current_page');
    expect(body).toHaveProperty('per_page');
    expect(body.per_page).toBe(1);
  });
});
