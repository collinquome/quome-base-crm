<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Webkul\User\Repositories\UserRepository;

/**
 * Generate a magic-link invite for a demo account on a non-prod environment.
 *
 * Idempotent: if the user already exists, we just mint a fresh token.
 * Refuses to run in production unless DEMO_INVITES_ALLOWED=true is also set,
 * so a stray invocation (or copy-pasted CLI) can't conjure access to prod.
 *
 * Run on dev:
 *   railway run --service crm-app --environment dev \
 *     php artisan demo:invite alice@example.com --role=Manager
 */
class DemoInvite extends Command
{
    protected $signature = 'demo:invite
                            {email : Email address to invite}
                            {--role=Producer : Role name (Administrator, Manager, Producer, Auditor, Focused User)}
                            {--name= : Optional display name (defaults to the email local-part)}';

    protected $description = 'Generate a magic-link invite for a demo account (non-production only).';

    public function handle(UserRepository $userRepository): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $roleName = (string) $this->option('role');
        $name = (string) ($this->option('name') ?: Str::of($email)->before('@')->title());

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email: {$email}");

            return self::FAILURE;
        }

        $role = DB::table('roles')->where('name', $roleName)->first();
        if (! $role) {
            $available = DB::table('roles')->pluck('name')->implode(', ');
            $this->error("Unknown role '{$roleName}'. Available: {$available}");

            return self::FAILURE;
        }

        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            $admin = $userRepository->findOrFail($existing->id);
            $this->info("Existing user found (id={$admin->id}, role_id={$admin->role_id}). Minting a fresh magic link.");
        } else {
            $admin = $userRepository->create([
                'name'            => $name,
                'email'           => $email,
                // Random unusable password — they'll set their own via the magic link.
                'password'        => bcrypt(Str::random(40)),
                'role_id'         => $role->id,
                'status'          => 1,
                // Administrators / Managers default to global view so they're not
                // accidentally trapped seeing only themselves.
                'view_permission' => in_array($roleName, ['Administrator', 'Manager'], true)
                    ? 'global'
                    : 'individual',
            ]);

            $admin->groups()->sync([]);

            $this->info("Created user (id={$admin->id}) as {$roleName}.");
        }

        $token = Password::broker('users')->createToken($admin);
        $url = route('admin.reset_password.create', $token).'?email='.urlencode($admin->email);

        $this->newLine();
        $this->line('  Email: '.$admin->email);
        $this->line('  Role:  '.$roleName);
        $this->line('  Link:  '.$url);
        $this->newLine();
        $this->comment('Token is valid for ~1 week (config/auth.php passwords expire).');

        return self::SUCCESS;
    }

    protected function guardEnvironment(): bool
    {
        $env = app()->environment();

        if ($env === 'production' && ! filter_var(env('DEMO_INVITES_ALLOWED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->error("Refusing to run in APP_ENV=production. Set DEMO_INVITES_ALLOWED=true if you really mean it.");

            return false;
        }

        if ($env !== 'production' && $env !== 'local' && ! filter_var(env('DEMO_INVITES_ALLOWED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->error("APP_ENV={$env} is not 'local'. Set DEMO_INVITES_ALLOWED=true to opt this environment in.");

            return false;
        }

        return true;
    }
}
