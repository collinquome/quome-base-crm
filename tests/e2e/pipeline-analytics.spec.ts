import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';
const TS = Date.now();

let api: APIRequestContext;
let token: string;
let leadId: number;
let pipelineId: number;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  // Login
  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const loginBody = await login.json();
  token = loginBody.token || loginBody.data?.token;

  // Get default pipeline and first stage
  const pipelines = await api.get('/api/v1/pipelines', {
    headers: { Authorization: `Bearer ${token}` },
  });
  expect(pipelines.ok()).toBeTruthy();
  const pipelinesData = await pipelines.json();
  pipelineId = pipelinesData.data[0].id;

  const pipelineDetail = await api.get(`/api/v1/pipelines/${pipelineId}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  expect(pipelineDetail.ok()).toBeTruthy();
  const stageId = (await pipelineDetail.json()).data.stages[0].id;

  // Create a contact for leads
  const contact = await api.post('/api/v1/contacts', {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      name: `Analytics Contact ${TS}`,
      emails: [{ value: `analytics-${TS}@example.com`, label: 'work' }],
    },
  });
  expect(contact.ok()).toBeTruthy();
  const personId = (await contact.json()).data.id;

  // Create a lead
  const lead = await api.post('/api/v1/leads', {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      title: `Analytics Lead ${TS}`,
      lead_value: 5000,
      lead_pipeline_id: pipelineId,
      lead_pipeline_stage_id: stageId,
      person_id: personId,
    },
  });
  expect(lead.ok()).toBeTruthy();
  leadId = (await lead.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}` };
}

test.describe('Pipeline Analytics - Forecast', () => {
  test('GET /analytics/forecast returns forecast data', async () => {
    const res = await api.get('/api/v1/analytics/forecast', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('stages');
    expect(body.data).toHaveProperty('forecast_total');
    expect(body.data).toHaveProperty('pipeline_total');
    expect(body.data).toHaveProperty('won_this_period');
    expect(body.data).toHaveProperty('won_all_time');
    expect(body.data).toHaveProperty('period');
    expect(body.data.period).toBe('month');
  });

  test('GET /analytics/forecast with pipeline_id filter', async () => {
    const res = await api.get(`/api/v1/analytics/forecast?pipeline_id=${pipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.stages).toBeInstanceOf(Array);
    // Should have at least one stage with our lead
    if (body.data.stages.length > 0) {
      const stage = body.data.stages[0];
      expect(stage).toHaveProperty('stage_id');
      expect(stage).toHaveProperty('stage_name');
      expect(stage).toHaveProperty('deal_count');
      expect(stage).toHaveProperty('total_value');
      expect(stage).toHaveProperty('probability');
      expect(stage).toHaveProperty('weighted_value');
    }
  });

  test('GET /analytics/forecast with period=quarter', async () => {
    const res = await api.get('/api/v1/analytics/forecast?period=quarter', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.period).toBe('quarter');
  });

  test('GET /analytics/forecast with period=year', async () => {
    const res = await api.get('/api/v1/analytics/forecast?period=year', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.period).toBe('year');
  });

  test('forecast_total <= pipeline_total (weighted is always less)', async () => {
    const res = await api.get(`/api/v1/analytics/forecast?pipeline_id=${pipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.forecast_total).toBeLessThanOrEqual(body.data.pipeline_total);
  });
});

test.describe('Pipeline Analytics - Velocity', () => {
  test('GET /analytics/velocity returns velocity data', async () => {
    const res = await api.get('/api/v1/analytics/velocity', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('stages');
    expect(body.data).toHaveProperty('avg_cycle_days');
    expect(body.data.stages).toBeInstanceOf(Array);
  });

  test('GET /analytics/velocity with pipeline_id filter', async () => {
    const res = await api.get(`/api/v1/analytics/velocity?pipeline_id=${pipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('stages');
    expect(body.data).toHaveProperty('avg_cycle_days');
    expect(body.data).toHaveProperty('bottleneck');
  });
});

test.describe('Pipeline Analytics - Summary', () => {
  test('GET /analytics/summary returns summary data', async () => {
    const res = await api.get('/api/v1/analytics/summary', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('total_leads');
    expect(body.data).toHaveProperty('active_leads');
    expect(body.data).toHaveProperty('total_value');
    expect(body.data).toHaveProperty('won_leads');
    expect(body.data).toHaveProperty('lost_leads');
    expect(body.data).toHaveProperty('win_rate');
    expect(typeof body.data.total_leads).toBe('number');
    expect(typeof body.data.win_rate).toBe('number');
  });

  test('GET /analytics/summary with pipeline_id filter', async () => {
    const res = await api.get(`/api/v1/analytics/summary?pipeline_id=${pipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.total_leads).toBeGreaterThanOrEqual(1);
    expect(body.data.active_leads).toBeGreaterThanOrEqual(1);
  });

  test('summary values are non-negative', async () => {
    const res = await api.get('/api/v1/analytics/summary', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.total_leads).toBeGreaterThanOrEqual(0);
    expect(body.data.active_leads).toBeGreaterThanOrEqual(0);
    expect(body.data.total_value).toBeGreaterThanOrEqual(0);
    expect(body.data.won_leads).toBeGreaterThanOrEqual(0);
    expect(body.data.lost_leads).toBeGreaterThanOrEqual(0);
    expect(body.data.win_rate).toBeGreaterThanOrEqual(0);
    expect(body.data.win_rate).toBeLessThanOrEqual(100);
  });
});

test.describe('Pipeline Analytics - Auth', () => {
  test('analytics endpoints require auth', async () => {
    const endpoints = ['/api/v1/analytics/forecast', '/api/v1/analytics/velocity', '/api/v1/analytics/summary'];
    for (const endpoint of endpoints) {
      const res = await api.get(endpoint, {
        headers: { Accept: 'application/json' },
      });
      expect(res.status()).toBe(401);
    }
  });
});
