import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

// `php artisan demo:invite` is exposed via Docker. We run it through
// `docker compose exec` so the test exercises the same path the user
// will run via `railway run`.
function runDemoInvite(args: string, env: Record<string, string> = {}): { code: number; out: string } {
  const envFlags = Object.entries(env)
    .map(([k, v]) => `-e ${k}=${v}`)
    .join(' ');
  try {
    const out = execSync(
      `docker compose exec -T ${envFlags} app php artisan demo:invite ${args} 2>&1`,
      { encoding: 'utf8' }
    );
    return { code: 0, out };
  } catch (e: any) {
    return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') };
  }
}

test.describe('demo:invite artisan command', () => {
  test('refuses to run in production without DEMO_INVITES_ALLOWED', () => {
    const stamp = Date.now();
    const r = runDemoInvite(`refuse-${stamp}@example.com --role=Producer`);
    expect(r.out).toContain('Refusing to run');
  });

  test('opted-in invocation creates a user and prints a magic link', () => {
    const email = `inviteme-${Date.now()}@example.com`;
    const r = runDemoInvite(`${email} --role=Manager`, { DEMO_INVITES_ALLOWED: 'true' });
    expect(r.out, 'should report success').toMatch(/Created user/);
    expect(r.out).toContain(email);
    expect(r.out).toMatch(/admin\/reset-password\/[a-f0-9]{20,}/);
  });

  test('re-running for the same email reuses the user (idempotent)', () => {
    const email = `idempotent-${Date.now()}@example.com`;
    runDemoInvite(`${email} --role=Producer`, { DEMO_INVITES_ALLOWED: 'true' });
    const second = runDemoInvite(`${email} --role=Producer`, { DEMO_INVITES_ALLOWED: 'true' });
    expect(second.out).toMatch(/Existing user found/);
    expect(second.out).toMatch(/admin\/reset-password\/[a-f0-9]{20,}/);
  });

  test('rejects an unknown role with a helpful message', () => {
    const r = runDemoInvite(`bad-role-${Date.now()}@example.com --role=Pumpkin`, { DEMO_INVITES_ALLOWED: 'true' });
    expect(r.out).toMatch(/Unknown role 'Pumpkin'/);
    expect(r.out).toMatch(/Available:/);
  });
});
