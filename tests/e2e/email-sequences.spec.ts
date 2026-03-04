import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let contactId: number;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  // Create a test contact
  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: { name: `Seq Test ${ts}`, emails: [{ value: `seq-${ts}@example.com`, label: 'work' }] },
  });
  expect(c.status()).toBe(201);
  contactId = (await c.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.describe('Email Sequences - CRUD', () => {
  let sequenceId: number;

  test('POST /email-sequences creates a sequence with steps', async () => {
    const res = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: {
        name: 'Welcome Sequence',
        description: 'Onboarding drip campaign',
        steps: [
          { subject: 'Welcome {{name}}!', body: '<p>Welcome aboard.</p>', delay_days: 0 },
          { subject: 'Getting Started', body: '<p>Here are some tips.</p>', delay_days: 3 },
          { subject: 'Check In', body: '<p>How is everything going?</p>', delay_days: 7 },
        ],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.name).toBe('Welcome Sequence');
    expect(body.data.status).toBe('draft');
    sequenceId = body.data.id;
  });

  test('GET /email-sequences lists sequences', async () => {
    const res = await api.get('/api/v1/email-sequences', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    // Should include counts
    const seq = body.data.find((s: any) => s.id === sequenceId);
    expect(seq).toBeTruthy();
    expect(seq.steps_count).toBe(3);
  });

  test('GET /email-sequences/{id} shows sequence with steps', async () => {
    const res = await api.get(`/api/v1/email-sequences/${sequenceId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.name).toBe('Welcome Sequence');
    expect(body.data.steps).toBeInstanceOf(Array);
    expect(body.data.steps.length).toBe(3);
    expect(body.data.steps[0].subject).toBe('Welcome {{name}}!');
    expect(body.data.steps[1].delay_days).toBe(3);
    expect(body.data.steps[2].delay_days).toBe(7);
  });

  test('PUT /email-sequences/{id} updates sequence', async () => {
    const res = await api.put(`/api/v1/email-sequences/${sequenceId}`, {
      headers: authHeaders(),
      data: { status: 'active', name: 'Welcome Drip' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.status).toBe('active');
    expect(body.data.name).toBe('Welcome Drip');
  });

  test('POST /email-sequences/{id}/steps adds a step', async () => {
    const res = await api.post(`/api/v1/email-sequences/${sequenceId}/steps`, {
      headers: authHeaders(),
      data: {
        subject: 'Final Follow-up',
        body: '<p>Last chance to connect.</p>',
        delay_days: 14,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.subject).toBe('Final Follow-up');
    expect(body.data.delay_days).toBe(14);
  });

  test('returns 404 for non-existent sequence', async () => {
    const res = await api.get('/api/v1/email-sequences/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('DELETE /email-sequences/{id} deletes sequence', async () => {
    // Create one to delete
    const create = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: { name: 'To Delete' },
    });
    const deleteId = (await create.json()).data.id;

    const res = await api.delete(`/api/v1/email-sequences/${deleteId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    const show = await api.get(`/api/v1/email-sequences/${deleteId}`, {
      headers: authHeaders(),
    });
    expect(show.status()).toBe(404);
  });
});

test.describe('Email Sequences - Enrollment', () => {
  let sequenceId: number;

  test.beforeAll(async () => {
    const res = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: {
        name: 'Enrollment Test Seq',
        steps: [
          { subject: 'Step 1', body: '<p>First.</p>', delay_days: 0 },
          { subject: 'Step 2', body: '<p>Second.</p>', delay_days: 2 },
        ],
      },
    });
    sequenceId = (await res.json()).data.id;
  });

  test('POST /email-sequences/{id}/enroll enrolls contacts', async () => {
    const res = await api.post(`/api/v1/email-sequences/${sequenceId}/enroll`, {
      headers: authHeaders(),
      data: { contact_ids: [contactId] },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.enrolled).toBe(1);
    expect(body.data.skipped).toBe(0);
    expect(body.data.enrolled_ids).toContain(contactId);
  });

  test('skips already enrolled contacts', async () => {
    const res = await api.post(`/api/v1/email-sequences/${sequenceId}/enroll`, {
      headers: authHeaders(),
      data: { contact_ids: [contactId] },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.enrolled).toBe(0);
    expect(body.data.skipped).toBe(1);
    expect(body.data.skipped_details[0].reason).toBe('already_enrolled');
  });

  test('POST /email-sequences/{id}/unenroll/{contactId} stops enrollment', async () => {
    const res = await api.post(
      `/api/v1/email-sequences/${sequenceId}/unenroll/${contactId}`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
  });

  test('unenroll returns 404 for non-enrolled contact', async () => {
    const res = await api.post(
      `/api/v1/email-sequences/${sequenceId}/unenroll/${contactId}`,
      { headers: authHeaders() }
    );
    expect(res.status()).toBe(404);
  });
});

test.describe('Email Sequences - Performance', () => {
  let sequenceId: number;

  test.beforeAll(async () => {
    const res = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: {
        name: 'Perf Test Seq',
        steps: [
          { subject: 'Step 1', body: '<p>First.</p>', delay_days: 0 },
        ],
      },
    });
    sequenceId = (await res.json()).data.id;

    // Enroll a contact
    await api.post(`/api/v1/email-sequences/${sequenceId}/enroll`, {
      headers: authHeaders(),
      data: { contact_ids: [contactId] },
    });
  });

  test('GET /email-sequences/{id}/performance returns metrics', async () => {
    const res = await api.get(`/api/v1/email-sequences/${sequenceId}/performance`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('sequence_id');
    expect(body.data).toHaveProperty('total_steps');
    expect(body.data).toHaveProperty('total_enrolled');
    expect(body.data).toHaveProperty('active');
    expect(body.data).toHaveProperty('completed');
    expect(body.data).toHaveProperty('stopped');
    expect(body.data).toHaveProperty('replied');
    expect(body.data).toHaveProperty('completion_rate');
    expect(body.data).toHaveProperty('reply_rate');
    expect(body.data.total_steps).toBe(1);
    expect(body.data.total_enrolled).toBeGreaterThanOrEqual(1);
  });

  test('returns 404 for non-existent sequence performance', async () => {
    const res = await api.get('/api/v1/email-sequences/999999/performance', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });
});

test.describe('Email Sequences - Validation', () => {
  test('rejects missing name', async () => {
    const res = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: { description: 'No name' },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects invalid status on update', async () => {
    const create = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: { name: 'Validation Test' },
    });
    const id = (await create.json()).data.id;

    const res = await api.put(`/api/v1/email-sequences/${id}`, {
      headers: authHeaders(),
      data: { status: 'invalid_status' },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects enroll with empty contact_ids', async () => {
    const create = await api.post('/api/v1/email-sequences', {
      headers: authHeaders(),
      data: { name: 'Empty Enroll Test' },
    });
    const id = (await create.json()).data.id;

    const res = await api.post(`/api/v1/email-sequences/${id}/enroll`, {
      headers: authHeaders(),
      data: { contact_ids: [] },
    });
    expect(res.status()).toBe(422);
  });
});
