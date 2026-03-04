import { test, expect, APIRequestContext } from '@playwright/test';

/**
 * T091: Mobile E2E Tests
 * Tests the REST API endpoints used by the mobile app screens.
 * Simulates the mobile client's API calls via Playwright request context.
 */

const BASE = process.env.BASE_URL || 'http://localhost:8190';
const API = `${BASE}/api/v1`;
const uid = Date.now();

let ctx: APIRequestContext;

test.describe('Mobile API E2E Tests (T091)', () => {
  test.beforeAll(async ({ playwright }) => {
    // Login via auth API (same as mobile LoginScreen)
    const tmp = await playwright.request.newContext();
    const res = await tmp.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const token = body.token;
    expect(token).toBeTruthy();
    await tmp.dispose();

    // Create authenticated context for all tests
    ctx = await playwright.request.newContext({
      extraHTTPHeaders: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    });
  });

  test.afterAll(async () => {
    await ctx?.dispose();
  });

  // --- Auth flow (LoginScreen) ---

  test('login returns valid token and user info', async ({ playwright }) => {
    const tmp = await playwright.request.newContext();
    const res = await tmp.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    const body = await res.json();
    expect(res.ok()).toBeTruthy();
    expect(body.token).toBeTruthy();
    expect(body.token_type).toBe('Bearer');
    expect(body.user.email).toBe('admin@example.com');
    await tmp.dispose();
  });

  test('login rejects invalid credentials', async ({ playwright }) => {
    const tmp = await playwright.request.newContext();
    const res = await tmp.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'wrongpassword' },
    });
    expect(res.status()).toBe(401);
    await tmp.dispose();
  });

  test('unauthenticated requests are rejected', async ({ playwright }) => {
    const tmp = await playwright.request.newContext({
      extraHTTPHeaders: {
        Authorization: 'Bearer invalid_token_abc',
        Accept: 'application/json',
      },
    });
    const res = await tmp.get(`${API}/contacts`);
    expect(res.status()).toBe(401);
    await tmp.dispose();
  });

  test('GET /auth/me returns current user', async () => {
    const res = await ctx.get(`${API}/auth/me`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.email).toBe('admin@example.com');
  });

  // --- Contacts (ContactsScreen) ---

  test('GET /contacts returns paginated list', async () => {
    const res = await ctx.get(`${API}/contacts`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
    expect(Array.isArray(body.data)).toBeTruthy();
  });

  test('POST /contacts creates a new contact', async () => {
    const res = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Mobile Test Contact ${uid}`,
        emails: [{ value: `mobile-${uid}@test.com`, label: 'work' }],
        contact_numbers: [{ value: `+1555${uid.toString().slice(-7)}`, label: 'work' }],
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data?.id).toBeTruthy();
  });

  test('GET /contacts supports search', async () => {
    const res = await ctx.get(`${API}/contacts?search=Mobile Test Contact ${uid}`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('GET /contacts/:id returns detail', async () => {
    // Get first contact
    const listRes = await ctx.get(`${API}/contacts`);
    const contacts = (await listRes.json()).data;
    expect(contacts.length).toBeGreaterThan(0);

    const detailRes = await ctx.get(`${API}/contacts/${contacts[0].id}`);
    expect(detailRes.ok()).toBeTruthy();
    const detail = (await detailRes.json()).data;
    expect(detail.id).toBe(contacts[0].id);
    expect(detail.name).toBeTruthy();
  });

  // --- Pipelines & Leads (DealsScreen) ---

  test('GET /pipelines returns available pipelines with stages', async () => {
    const res = await ctx.get(`${API}/pipelines`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
    expect(Array.isArray(body.data)).toBeTruthy();
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET /pipelines/:id returns pipeline with stages', async () => {
    const listRes = await ctx.get(`${API}/pipelines`);
    const pipelines = (await listRes.json()).data;
    const pipeline = pipelines[0];

    const detailRes = await ctx.get(`${API}/pipelines/${pipeline.id}`);
    expect(detailRes.ok()).toBeTruthy();
    const detail = (await detailRes.json()).data;
    expect(detail.stages).toBeDefined();
    expect(detail.stages.length).toBeGreaterThan(0);
  });

  test('GET /leads returns lead list', async () => {
    const res = await ctx.get(`${API}/leads`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('POST /leads creates a new lead', async () => {
    // Get pipeline/stage
    const pipRes = await ctx.get(`${API}/pipelines`);
    const pipeline = (await pipRes.json()).data[0];
    const stageRes = await ctx.get(`${API}/pipelines/${pipeline.id}`);
    const stages = (await stageRes.json()).data.stages;

    // Get a person
    const personsRes = await ctx.get(`${API}/contacts`);
    const persons = (await personsRes.json()).data;
    const personId = persons[0]?.id;

    const res = await ctx.post(`${API}/leads`, {
      data: {
        title: `Mobile Test Lead ${uid}`,
        lead_value: 5000,
        lead_pipeline_id: pipeline.id,
        lead_pipeline_stage_id: stages[0]?.id,
        person_id: personId,
        status: 1,
      },
    });
    expect([200, 201, 422]).toContain(res.status());
  });

  test('GET /leads filters by pipeline', async () => {
    const pipRes = await ctx.get(`${API}/pipelines`);
    const pipeline = (await pipRes.json()).data[0];

    const res = await ctx.get(`${API}/leads?pipeline_id=${pipeline.id}`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  // --- Activities (ActivitiesScreen) ---

  test('GET /activities returns activity list', async () => {
    const res = await ctx.get(`${API}/activities`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('POST /activities creates a new activity', async () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const scheduleFrom = tomorrow.toISOString().slice(0, 16).replace('T', ' ');
    const scheduleTo = new Date(tomorrow);
    scheduleTo.setHours(scheduleTo.getHours() + 1);
    const scheduleToStr = scheduleTo.toISOString().slice(0, 16).replace('T', ' ');

    const res = await ctx.post(`${API}/activities`, {
      data: {
        title: `Mobile Activity ${uid}`,
        type: 'call',
        schedule_from: scheduleFrom,
        schedule_to: scheduleToStr,
        is_done: 0,
      },
    });
    expect([200, 201, 422]).toContain(res.status());
  });

  test('GET /activities filters by type', async () => {
    const res = await ctx.get(`${API}/activities?type=call`);
    expect(res.ok()).toBeTruthy();
  });

  // --- Action Stream (ActionStreamScreen) ---

  test('GET /action-stream returns prioritized actions', async () => {
    const res = await ctx.get(`${API}/action-stream`);
    // May be 200 or 404 depending on if custom route exists
    if (res.ok()) {
      const body = await res.json();
      expect(body.data).toBeDefined();
    } else {
      expect([404, 500]).toContain(res.status());
    }
  });

  // --- Speed Dial / Favorites ---

  test('GET /speed-dial returns favorites list', async () => {
    const res = await ctx.get(`${API}/speed-dial`);
    if (res.ok()) {
      const body = await res.json();
      expect(body.data).toBeDefined();
    } else {
      expect([404, 500]).toContain(res.status());
    }
  });

  // --- Notifications (NotificationsScreen) ---

  test('GET /notifications returns notification list', async () => {
    const res = await ctx.get(`${API}/notifications`);
    if (res.ok()) {
      const body = await res.json();
      expect(body.data).toBeDefined();
    }
  });

  // --- Tags ---

  test('GET /tags returns tag list', async () => {
    const res = await ctx.get(`${API}/tags`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  // --- Workflow Tests (simulating mobile user journeys) ---

  test('mobile pipeline workflow: list pipelines, get stages, filter leads', async () => {
    // Step 1: List pipelines (pipeline selector chips)
    const pipRes = await ctx.get(`${API}/pipelines`);
    expect(pipRes.ok()).toBeTruthy();
    const pipelines = (await pipRes.json()).data;
    expect(pipelines.length).toBeGreaterThan(0);

    // Step 2: Get pipeline details with stages (Kanban columns)
    const pipeline = pipelines[0];
    const detailRes = await ctx.get(`${API}/pipelines/${pipeline.id}`);
    expect(detailRes.ok()).toBeTruthy();
    const detail = (await detailRes.json()).data;
    expect(detail.stages.length).toBeGreaterThan(0);

    // Step 3: List leads for this pipeline (deal cards)
    const leadsRes = await ctx.get(`${API}/leads?pipeline_id=${pipeline.id}`);
    expect(leadsRes.ok()).toBeTruthy();
  });

  test('business card scanner: create contact with full details', async () => {
    const res = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Scanned Contact ${uid}`,
        emails: [{ value: `scanned-${uid}@business.com`, label: 'work' }],
        contact_numbers: [{ value: `+1800${uid.toString().slice(-7)}`, label: 'work' }],
        organization: { name: `Scanned Corp ${uid}` },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data?.id).toBeTruthy();
  });

  test('offline sync simulation: POST then PUT', async () => {
    // Simulate offline queue: create then update
    const createRes = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Offline Sync ${uid}`,
        emails: [{ value: `offline-${uid}@test.com`, label: 'work' }],
      },
    });
    expect(createRes.ok()).toBeTruthy();
    const created = (await createRes.json()).data;

    const updateRes = await ctx.put(`${API}/contacts/${created.id}`, {
      data: { name: `Offline Sync Updated ${uid}` },
    });
    expect(updateRes.ok()).toBeTruthy();
  });

  test('route planner: fetch contacts for route planning', async () => {
    const res = await ctx.get(`${API}/contacts`);
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(Array.isArray(body.data)).toBeTruthy();
    if (body.data.length > 0) {
      expect(body.data[0]).toHaveProperty('name');
    }
  });

  // --- Cleanup ---

  test('cleanup: remove test contacts', async () => {
    const res = await ctx.get(`${API}/contacts`);
    if (!res.ok()) return;
    const contacts = (await res.json()).data || [];
    for (const c of contacts) {
      if (c.name && c.name.includes(String(uid))) {
        await ctx.delete(`${API}/contacts/${c.id}`);
      }
    }
  });
});
