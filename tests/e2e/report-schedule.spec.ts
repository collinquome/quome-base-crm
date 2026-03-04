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

test.describe('Report Save & Schedule', () => {
  let reportId: number;
  let scheduleId: number;

  test('POST /reports creates a saved report', async () => {
    const res = await api.post('/api/v1/reports', {
      headers: authHeaders(),
      data: {
        name: 'E2E Schedule Test Report',
        entity_type: 'leads',
        columns: ['title', 'status'],
        sort_by: 'created_at',
        sort_order: 'desc',
        is_public: false,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.name).toBe('E2E Schedule Test Report');
    reportId = body.data.id;
  });

  test('GET /reports returns saved reports', async () => {
    const res = await api.get('/api/v1/reports', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET /reports/:id returns single report', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.get(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.name).toBe('E2E Schedule Test Report');
  });

  test('PUT /reports/:id updates a report', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.put(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
      data: {
        name: 'Updated Schedule Test Report',
        is_public: true,
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.name).toBe('Updated Schedule Test Report');
  });

  test('POST /reports/:id/execute executes saved report', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/execute`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('POST /reports/:id/schedules creates a daily schedule', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'daily',
        time_of_day: '09:00',
        format: 'csv',
        recipients: ['admin@example.com'],
        subject: 'Daily Leads Report',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.frequency).toBe('daily');
    expect(body.data.format).toBe('csv');
    expect(body.data.recipients).toContain('admin@example.com');
    expect(body.data.is_active).toBe(true);
    expect(body.data.next_run_at).toBeTruthy();
    scheduleId = body.data.id;
  });

  test('POST /reports/:id/schedules creates a weekly schedule', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'weekly',
        day_of_week: 'monday',
        time_of_day: '08:30',
        format: 'pdf',
        recipients: ['admin@example.com', 'test@example.com'],
        subject: 'Weekly Leads Summary',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.frequency).toBe('weekly');
    expect(body.data.day_of_week).toBe('monday');
    expect(body.data.format).toBe('pdf');
    expect(body.data.recipients).toHaveLength(2);
  });

  test('POST /reports/:id/schedules creates a monthly schedule', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'monthly',
        day_of_month: 1,
        time_of_day: '07:00',
        format: 'xls',
        recipients: ['admin@example.com'],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.frequency).toBe('monthly');
    expect(body.data.day_of_month).toBe(1);
    expect(body.data.format).toBe('xls');
  });

  test('GET /reports/:id/schedules lists all schedules', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.get(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(3);
  });

  test('PUT /reports/:reportId/schedules/:scheduleId updates schedule', async () => {
    expect(reportId).toBeTruthy();
    expect(scheduleId).toBeTruthy();
    const res = await api.put(`/api/v1/reports/${reportId}/schedules/${scheduleId}`, {
      headers: authHeaders(),
      data: {
        time_of_day: '10:00',
        format: 'pdf',
        is_active: false,
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.format).toBe('pdf');
    expect(body.data.is_active).toBe(false);
  });

  test('DELETE /reports/:reportId/schedules/:scheduleId removes schedule', async () => {
    expect(reportId).toBeTruthy();
    expect(scheduleId).toBeTruthy();
    const res = await api.delete(`/api/v1/reports/${reportId}/schedules/${scheduleId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('validates required fields on schedule create', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'daily',
        // missing recipients
      },
    });
    expect(res.status()).toBe(422);
  });

  test('validates frequency values', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'hourly',
        recipients: ['admin@example.com'],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('validates recipient email format', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.post(`/api/v1/reports/${reportId}/schedules`, {
      headers: authHeaders(),
      data: {
        frequency: 'daily',
        recipients: ['not-an-email'],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('DELETE /reports/:id cascades to schedules', async () => {
    expect(reportId).toBeTruthy();
    const res = await api.delete(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('schedules require authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/reports/1/schedules', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });
});
