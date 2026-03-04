import { test, expect, APIRequestContext } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

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

test.describe('WebSocket Client - Echo Plugin', () => {
  test('echo.js plugin file exists with correct exports', () => {
    const pluginPath = path.resolve(
      __dirname,
      '../../crm/packages/Webkul/Admin/src/Resources/assets/js/plugins/echo.js'
    );
    expect(fs.existsSync(pluginPath)).toBeTruthy();
    const content = fs.readFileSync(pluginPath, 'utf-8');
    expect(content).toContain("import Echo from");
    expect(content).toContain("import Pusher from");
    expect(content).toContain("install(app)");
    expect(content).toContain("$echo");
    expect(content).toContain("window.Echo");
  });

  test('echo.js is registered in app.js', () => {
    const appPath = path.resolve(
      __dirname,
      '../../crm/packages/Webkul/Admin/src/Resources/assets/js/app.js'
    );
    const content = fs.readFileSync(appPath, 'utf-8');
    expect(content).toContain('import EchoPlugin from "./plugins/echo"');
    expect(content).toContain('EchoPlugin');
  });

  test('laravel-echo and pusher-js are installed', () => {
    const pkgPath = path.resolve(
      __dirname,
      '../../crm/packages/Webkul/Admin/package.json'
    );
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf-8'));
    const allDeps = { ...pkg.dependencies, ...pkg.devDependencies };
    expect(allDeps).toHaveProperty('laravel-echo');
    expect(allDeps).toHaveProperty('pusher-js');
  });

  test('VITE_PUSHER env vars are in .env.example', () => {
    const envPath = path.resolve(__dirname, '../../crm/.env.example');
    const content = fs.readFileSync(envPath, 'utf-8');
    expect(content).toContain('VITE_PUSHER_APP_KEY');
    expect(content).toContain('VITE_PUSHER_HOST');
    expect(content).toContain('VITE_PUSHER_PORT');
    expect(content).toContain('VITE_PUSHER_SCHEME');
  });

  test('Soketi WebSocket server is running and reachable', async () => {
    const res = await api.get('http://localhost:6001');
    expect(res.ok()).toBeTruthy();
    const text = await res.text();
    expect(text).toContain('OK');
  });

  test('broadcasting auth endpoint accepts Sanctum tokens', async () => {
    const res = await api.post(`${BASE}/broadcasting/auth`, {
      headers: {
        ...authHeaders(),
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      form: {
        socket_id: '12345.67890',
        channel_name: `private-user.1`,
      },
    });
    // Should return 200 with auth signature (user 1 = admin)
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('auth');
  });

  test('broadcasting auth rejects unauthorized channel', async () => {
    const res = await api.post(`${BASE}/broadcasting/auth`, {
      headers: {
        ...authHeaders(),
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      form: {
        socket_id: '12345.67890',
        channel_name: 'private-user.99999',
      },
    });
    // Admin user (id=1) should not be authorized for user.99999
    expect(res.status()).toBe(403);
  });

  test('broadcast test endpoint fires event via Soketi', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: {
        event: 'notification',
        data: { message: 'echo-client-test' },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('Broadcast');
    expect(body.data.fired).toBeTruthy();
  });
});
