import { test, expect, APIRequestContext } from '@playwright/test';

/**
 * T102: Performance Testing
 * Tests API response times under load and verifies performance characteristics.
 * Measures: response time, pagination efficiency, search performance, concurrent requests.
 */

const BASE = process.env.BASE_URL || 'http://localhost:8190';
const API = `${BASE}/api/v1`;
const uid = Date.now();

// Performance thresholds (milliseconds)
const THRESHOLDS = {
  LIST_ENDPOINT: 3000, // List endpoints should respond within 3s
  DETAIL_ENDPOINT: 2000, // Detail endpoints within 2s
  CREATE_ENDPOINT: 3000, // Create endpoints within 3s
  SEARCH_ENDPOINT: 3000, // Search within 3s
  AUTH_ENDPOINT: 2000, // Auth within 2s
};

let ctx: APIRequestContext;

test.describe('Performance Testing (T102)', () => {
  test.beforeAll(async ({ playwright }) => {
    const tmp = await playwright.request.newContext();
    const res = await tmp.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    expect(res.ok()).toBeTruthy();
    const token = (await res.json()).token;
    await tmp.dispose();

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

  // --- Response Time Tests ---

  test('auth login responds within threshold', async ({ playwright }) => {
    const tmp = await playwright.request.newContext();
    const start = Date.now();
    const res = await tmp.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.AUTH_ENDPOINT);
    await tmp.dispose();
  });

  test('GET /contacts list responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/contacts`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  test('GET /leads list responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/leads`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  test('GET /activities list responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/activities`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  test('GET /pipelines list responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/pipelines`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  test('GET /tags responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/tags`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  // --- Pagination Performance ---

  test('paginated contacts maintain consistent response times', async () => {
    const times: number[] = [];
    for (let page = 1; page <= 3; page++) {
      const start = Date.now();
      const res = await ctx.get(`${API}/contacts?page=${page}&limit=25`);
      times.push(Date.now() - start);
      expect(res.ok()).toBeTruthy();
    }
    // Each page should be fast
    for (const t of times) {
      expect(t).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
    }
    // Pages should be roughly similar in speed (no N+1 regression on later pages)
    if (times.length >= 2) {
      const maxTime = Math.max(...times);
      const minTime = Math.min(...times);
      // Last page shouldn't be more than 5x slower than the fastest
      expect(maxTime).toBeLessThan(minTime * 5 + 500);
    }
  });

  test('paginated leads maintain consistent response times', async () => {
    const times: number[] = [];
    for (let page = 1; page <= 3; page++) {
      const start = Date.now();
      const res = await ctx.get(`${API}/leads?page=${page}&limit=25`);
      times.push(Date.now() - start);
      expect(res.ok()).toBeTruthy();
    }
    for (const t of times) {
      expect(t).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
    }
  });

  // --- Search Performance ---

  test('contact search responds within threshold', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/contacts?search=admin`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.SEARCH_ENDPOINT);
  });

  // --- Bulk Data Seeding and Testing ---

  test('bulk create 50 contacts within reasonable time', async () => {
    const start = Date.now();
    const promises: Promise<any>[] = [];

    for (let i = 0; i < 50; i++) {
      promises.push(
        ctx.post(`${API}/contacts`, {
          data: {
            name: `PerfTest ${uid} Contact ${i}`,
            emails: [{ value: `perf-${uid}-${i}@loadtest.com`, label: 'work' }],
          },
        }),
      );

      // Batch in groups of 10 to avoid overwhelming
      if (promises.length >= 10) {
        await Promise.all(promises);
        promises.length = 0;
      }
    }
    if (promises.length > 0) await Promise.all(promises);

    const elapsed = Date.now() - start;
    // 50 contacts should be created within 30 seconds
    expect(elapsed).toBeLessThan(30000);
  });

  test('list endpoint still fast after bulk insert', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/contacts?limit=25`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.LIST_ENDPOINT);
  });

  test('search after bulk insert still fast', async () => {
    const start = Date.now();
    const res = await ctx.get(`${API}/contacts?search=PerfTest ${uid}`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.SEARCH_ENDPOINT);
  });

  // --- Concurrent Request Handling ---

  test('handles 10 concurrent API requests', async () => {
    const start = Date.now();
    const requests = Array.from({ length: 10 }, (_, i) =>
      ctx.get(`${API}/contacts?page=${(i % 3) + 1}&limit=10`),
    );
    const results = await Promise.all(requests);
    const elapsed = Date.now() - start;

    for (const res of results) {
      expect(res.ok()).toBeTruthy();
    }
    // All 10 concurrent requests should complete within 10 seconds
    expect(elapsed).toBeLessThan(10000);
  });

  test('handles mixed concurrent operations', async () => {
    const start = Date.now();
    const requests = [
      ctx.get(`${API}/contacts?limit=5`),
      ctx.get(`${API}/leads?limit=5`),
      ctx.get(`${API}/activities?limit=5`),
      ctx.get(`${API}/pipelines`),
      ctx.get(`${API}/tags`),
    ];
    const results = await Promise.all(requests);
    const elapsed = Date.now() - start;

    for (const res of results) {
      expect(res.ok()).toBeTruthy();
    }
    expect(elapsed).toBeLessThan(5000);
  });

  // --- Contact Detail with Relations ---

  test('contact detail endpoint performs well', async () => {
    const listRes = await ctx.get(`${API}/contacts?limit=1`);
    const contacts = (await listRes.json()).data;
    if (contacts.length === 0) return;

    const start = Date.now();
    const res = await ctx.get(`${API}/contacts/${contacts[0].id}`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.DETAIL_ENDPOINT);
  });

  test('pipeline detail with stages performs well', async () => {
    const listRes = await ctx.get(`${API}/pipelines`);
    const pipelines = (await listRes.json()).data;
    if (pipelines.length === 0) return;

    const start = Date.now();
    const res = await ctx.get(`${API}/pipelines/${pipelines[0].id}`);
    const elapsed = Date.now() - start;
    expect(res.ok()).toBeTruthy();
    expect(elapsed).toBeLessThan(THRESHOLDS.DETAIL_ENDPOINT);
  });

  // --- Cleanup ---

  test('cleanup: remove performance test contacts', async () => {
    let page = 1;
    let hasMore = true;
    while (hasMore) {
      const res = await ctx.get(`${API}/contacts?search=PerfTest ${uid}&page=${page}&limit=50`);
      if (!res.ok()) break;
      const contacts = (await res.json()).data || [];
      if (contacts.length === 0) {
        hasMore = false;
        break;
      }
      const deletes = contacts
        .filter((c: any) => c.name?.includes(`PerfTest ${uid}`))
        .map((c: any) => ctx.delete(`${API}/contacts/${c.id}`));
      await Promise.all(deletes);
      page++;
      if (page > 5) break; // Safety limit
    }
  });
});
