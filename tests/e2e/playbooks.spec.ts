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

  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: { name: `Playbook Test ${ts}`, emails: [{ value: `pb-${ts}@example.com`, label: 'work' }] },
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

test.describe('Playbooks - CRUD', () => {
  let playbookId: number;

  test('POST /playbooks creates a playbook with steps', async () => {
    const res = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: {
        name: 'New Lead Onboarding',
        description: 'Steps to onboard a new lead',
        trigger_type: 'lead_created',
        steps: [
          { action_type: 'send_email', config: { template: 'welcome' }, delay_days: 0 },
          { action_type: 'create_activity', config: { type: 'call', title: 'Intro call' }, delay_days: 1 },
          { action_type: 'wait', delay_days: 3 },
          { action_type: 'send_email', config: { template: 'follow_up' }, delay_days: 0 },
          { action_type: 'add_tag', config: { tag: 'onboarded' }, delay_days: 0 },
        ],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.name).toBe('New Lead Onboarding');
    expect(body.data.status).toBe('draft');
    expect(body.data.trigger_type).toBe('lead_created');
    playbookId = body.data.id;
  });

  test('GET /playbooks lists playbooks with counts', async () => {
    const res = await api.get('/api/v1/playbooks', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    const pb = body.data.find((p: any) => p.id === playbookId);
    expect(pb).toBeTruthy();
    expect(pb.steps_count).toBe(5);
  });

  test('GET /playbooks supports status filter', async () => {
    const res = await api.get('/api/v1/playbooks?status=draft', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    for (const pb of body.data) {
      expect(pb.status).toBe('draft');
    }
  });

  test('GET /playbooks/{id} shows playbook with steps and execution summary', async () => {
    const res = await api.get(`/api/v1/playbooks/${playbookId}`, { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.name).toBe('New Lead Onboarding');
    expect(body.data.steps).toBeInstanceOf(Array);
    expect(body.data.steps.length).toBe(5);
    expect(body.data.steps[0].action_type).toBe('send_email');
    expect(body.data.steps[0].config).toHaveProperty('template');
    expect(body.data.steps[1].action_type).toBe('create_activity');
    expect(body.data.steps[2].action_type).toBe('wait');
    expect(body.data).toHaveProperty('executions_summary');
  });

  test('PUT /playbooks/{id} updates playbook', async () => {
    const res = await api.put(`/api/v1/playbooks/${playbookId}`, {
      headers: authHeaders(),
      data: { status: 'active', name: 'Lead Onboarding v2' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.status).toBe('active');
    expect(body.data.name).toBe('Lead Onboarding v2');
  });

  test('POST /playbooks/{id}/steps adds a step', async () => {
    const res = await api.post(`/api/v1/playbooks/${playbookId}/steps`, {
      headers: authHeaders(),
      data: {
        action_type: 'update_field',
        config: { field: 'status', value: 'qualified' },
        delay_days: 7,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.action_type).toBe('update_field');
    expect(body.data.config.field).toBe('status');
  });

  test('returns 404 for non-existent playbook', async () => {
    const res = await api.get('/api/v1/playbooks/999999', { headers: authHeaders() });
    expect(res.status()).toBe(404);
  });

  test('DELETE /playbooks/{id} deletes a playbook', async () => {
    const create = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: { name: 'To Delete' },
    });
    const deleteId = (await create.json()).data.id;

    const res = await api.delete(`/api/v1/playbooks/${deleteId}`, { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();

    const show = await api.get(`/api/v1/playbooks/${deleteId}`, { headers: authHeaders() });
    expect(show.status()).toBe(404);
  });
});

test.describe('Playbooks - Execution', () => {
  let playbookId: number;
  let executionId: number;

  test.beforeAll(async () => {
    const res = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: {
        name: 'Exec Test Playbook',
        steps: [
          { action_type: 'send_email', config: { template: 'test' }, delay_days: 0 },
          { action_type: 'wait', delay_days: 1 },
          { action_type: 'create_activity', config: { type: 'note' }, delay_days: 0 },
        ],
      },
    });
    playbookId = (await res.json()).data.id;
  });

  test('POST /playbooks/{id}/execute starts execution on a contact', async () => {
    const res = await api.post(`/api/v1/playbooks/${playbookId}/execute`, {
      headers: authHeaders(),
      data: { entity_type: 'persons', entity_id: contactId },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('execution_id');
    expect(body.data.playbook_id).toBe(playbookId);
    expect(body.data.entity_type).toBe('persons');
    expect(body.data.entity_id).toBe(contactId);
    expect(body.data.status).toBe('running');
    expect(body.data.total_steps).toBe(3);
    executionId = body.data.execution_id;
  });

  test('rejects execution of playbook with no steps', async () => {
    const empty = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: { name: 'Empty Playbook' },
    });
    const emptyId = (await empty.json()).data.id;

    const res = await api.post(`/api/v1/playbooks/${emptyId}/execute`, {
      headers: authHeaders(),
      data: { entity_type: 'persons', entity_id: contactId },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /playbook-executions/{id}/cancel cancels execution', async () => {
    const res = await api.post(`/api/v1/playbook-executions/${executionId}/cancel`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('cannot cancel non-running execution', async () => {
    const res = await api.post(`/api/v1/playbook-executions/${executionId}/cancel`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Playbooks - Validation', () => {
  test('rejects missing name', async () => {
    const res = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: { description: 'No name' },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects invalid trigger_type', async () => {
    const res = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: { name: 'Bad Trigger', trigger_type: 'invalid' },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects invalid action_type in steps', async () => {
    const res = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: {
        name: 'Bad Step',
        steps: [{ action_type: 'invalid_action' }],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('rejects execute with invalid entity_type', async () => {
    const create = await api.post('/api/v1/playbooks', {
      headers: authHeaders(),
      data: {
        name: 'Entity Test',
        steps: [{ action_type: 'wait', delay_days: 1 }],
      },
    });
    const id = (await create.json()).data.id;

    const res = await api.post(`/api/v1/playbooks/${id}/execute`, {
      headers: authHeaders(),
      data: { entity_type: 'invalid', entity_id: 1 },
    });
    expect(res.status()).toBe(422);
  });
});
