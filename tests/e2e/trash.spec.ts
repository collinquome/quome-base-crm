import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8190';
const API = `${BASE_URL}/api/v1`;
const TS = Date.now();

test.describe('Soft Delete & Trash API', () => {
  let ctx: APIRequestContext;

  test.beforeAll(async ({ playwright }) => {
    const tmpCtx = await playwright.request.newContext();
    const loginResp = await tmpCtx.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    const { token } = await loginResp.json();
    await tmpCtx.dispose();

    ctx = await playwright.request.newContext({
      extraHTTPHeaders: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });
  });

  test.afterAll(async () => {
    await ctx?.dispose();
  });

  test('deleted contact appears in trash', async () => {
    // Create a contact
    const createResp = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Trash Test ${TS}`,
        emails: [{ value: `trash-${TS}@example.com`, label: 'work' }],
      },
    });
    const created = await createResp.json();
    const contactId = created.data.id;

    // Delete it (soft delete)
    const deleteResp = await ctx.delete(`${API}/contacts/${contactId}`);
    expect(deleteResp.ok()).toBeTruthy();

    // Verify it's gone from contacts list
    const getResp = await ctx.get(`${API}/contacts/${contactId}`);
    expect(getResp.ok()).toBeFalsy();

    // Verify it appears in trash
    const trashResp = await ctx.get(`${API}/trash?type=contacts`);
    expect(trashResp.ok()).toBeTruthy();
    const trashJson = await trashResp.json();
    const found = trashJson.data.find((c: any) => c.id === contactId);
    expect(found).toBeTruthy();
    expect(found.deleted_at).toBeTruthy();
  });

  test('can restore contact from trash', async () => {
    // Create and delete a contact
    const createResp = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Restore Test ${TS}`,
        emails: [{ value: `restore-${TS}@example.com`, label: 'work' }],
      },
    });
    const created = await createResp.json();
    const contactId = created.data.id;

    await ctx.delete(`${API}/contacts/${contactId}`);

    // Restore it
    const restoreResp = await ctx.post(`${API}/trash/contacts/${contactId}/restore`);
    expect(restoreResp.ok()).toBeTruthy();

    // Verify it's back in contacts
    const getResp = await ctx.get(`${API}/contacts/${contactId}`);
    expect(getResp.ok()).toBeTruthy();
    const json = await getResp.json();
    expect(json.data.name).toContain('Restore Test');
  });

  test('can permanently delete from trash', async () => {
    // Create and delete a contact
    const createResp = await ctx.post(`${API}/contacts`, {
      data: {
        name: `Perm Delete ${TS}`,
        emails: [{ value: `permdelete-${TS}@example.com`, label: 'work' }],
      },
    });
    const created = await createResp.json();
    const contactId = created.data.id;

    await ctx.delete(`${API}/contacts/${contactId}`);

    // Permanently delete
    const forceResp = await ctx.delete(`${API}/trash/contacts/${contactId}`);
    expect(forceResp.ok()).toBeTruthy();

    // Verify it's gone from trash too
    const trashResp = await ctx.get(`${API}/trash?type=contacts`);
    const trashJson = await trashResp.json();
    const found = trashJson.data.find((c: any) => c.id === contactId);
    expect(found).toBeUndefined();
  });

  test('GET /trash returns all trashed items', async () => {
    const response = await ctx.get(`${API}/trash`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toHaveProperty('contacts');
    expect(json.data).toHaveProperty('leads');
    expect(json.data).toHaveProperty('organizations');
  });

  test('GET /trash supports type filter', async () => {
    const response = await ctx.get(`${API}/trash?type=leads`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toBeInstanceOf(Array);
  });

  test('deleted lead appears in trash', async () => {
    // Get pipeline/stage
    const pipelineResp = await ctx.get(`${API}/pipelines`);
    const pipelines = await pipelineResp.json();
    const pipeline = pipelines.data[0];
    const stageResp = await ctx.get(`${API}/pipelines/${pipeline.id}`);
    const pipelineDetail = await stageResp.json();
    const stageId = pipelineDetail.data.stages[0].id;

    // Create a lead
    const createResp = await ctx.post(`${API}/leads`, {
      data: {
        title: `Trash Lead ${TS}`,
        lead_value: 1000,
        lead_pipeline_id: pipeline.id,
        lead_pipeline_stage_id: stageId,
      },
    });
    const created = await createResp.json();
    const leadId = created.data.id;

    // Delete it
    await ctx.delete(`${API}/leads/${leadId}`);

    // Verify in trash
    const trashResp = await ctx.get(`${API}/trash?type=leads`);
    const trashJson = await trashResp.json();
    const found = trashJson.data.find((l: any) => l.id === leadId);
    expect(found).toBeTruthy();
  });
});
