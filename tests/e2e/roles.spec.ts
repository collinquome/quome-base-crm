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

test.describe('Roles', () => {
  test('GET /roles lists all roles', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(3);

    const roleNames = body.data.map((r: any) => r.name);
    expect(roleNames).toContain('Administrator');
    expect(roleNames).toContain('Producer');
    expect(roleNames).toContain('Manager');
  });

  test('Administrator role has full permissions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const adminRole = body.data.find((r: any) => r.name === 'Administrator');
    expect(adminRole).toBeTruthy();
    expect(adminRole.permission_type).toBe('all');
  });

  test('GET /roles/{id} shows a specific role', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const producer = body.data.find((r: any) => r.name === 'Producer');

    const showRes = await api.get(`/api/v1/roles/${producer.id}`, {
      headers: authHeaders(),
    });
    expect(showRes.ok()).toBeTruthy();
    const showBody = await showRes.json();
    expect(showBody.data.name).toBe('Producer');
    expect(showBody.data).toHaveProperty('users_count');
  });

  test('GET /roles/{id} returns 404 for non-existent role', async () => {
    const res = await api.get('/api/v1/roles/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('Insurance Roles (Producer & Manager)', () => {
  test('Producer role exists with correct permissions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const producer = body.data.find((r: any) => r.name === 'Producer');
    expect(producer).toBeTruthy();
    expect(producer.permission_type).toBe('custom');

    // Producer should have sales permissions
    expect(producer.permissions).toContain('dashboard');
    expect(producer.permissions).toContain('leads');
    expect(producer.permissions).toContain('leads.create');
    expect(producer.permissions).toContain('contacts');
    expect(producer.permissions).toContain('activities');
    expect(producer.permissions).toContain('products.view');

    // Producer should NOT have delete or settings access
    expect(producer.permissions).not.toContain('leads.delete');
    expect(producer.permissions).not.toContain('settings');
    expect(producer.permissions).not.toContain('configuration');
  });

  test('Manager role exists with elevated permissions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const manager = body.data.find((r: any) => r.name === 'Manager');
    expect(manager).toBeTruthy();
    expect(manager.permission_type).toBe('custom');

    // Manager should have full CRUD on leads and contacts
    expect(manager.permissions).toContain('leads.delete');
    expect(manager.permissions).toContain('contacts.persons.delete');

    // Manager should have settings access
    expect(manager.permissions).toContain('settings');
    expect(manager.permissions).toContain('settings.user');
    expect(manager.permissions).toContain('settings.user.users');
    expect(manager.permissions).toContain('configuration');

    // Manager should have team management
    expect(manager.permissions).toContain('settings.user.groups');
    expect(manager.permissions).toContain('settings.lead.pipelines');
  });

  test('all three role types exist', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const names = body.data.map((r: any) => r.name);
    expect(names).toContain('Administrator');
    expect(names).toContain('Producer');
    expect(names).toContain('Manager');
  });
});
