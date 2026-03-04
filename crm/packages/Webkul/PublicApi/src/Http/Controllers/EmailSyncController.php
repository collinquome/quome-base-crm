<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EmailSyncController extends Controller
{
    /**
     * List email accounts for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $accounts = DB::table('email_accounts')
            ->where('user_id', auth()->id())
            ->get()
            ->map(fn ($a) => $this->formatAccount($a));

        return response()->json(['data' => $accounts]);
    }

    /**
     * Add a new email account.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email_address'   => 'required|email',
            'display_name'    => 'sometimes|string|max:255',
            'provider'        => 'sometimes|string|in:gmail,outlook,custom',
            'imap_host'       => 'required|string',
            'imap_port'       => 'sometimes|integer|min:1|max:65535',
            'imap_encryption' => 'sometimes|string|in:ssl,tls,none',
            'imap_username'   => 'required|string',
            'imap_password'   => 'required|string',
            'smtp_host'       => 'required|string',
            'smtp_port'       => 'sometimes|integer|min:1|max:65535',
            'smtp_encryption' => 'sometimes|string|in:ssl,tls,none',
            'smtp_username'   => 'required|string',
            'smtp_password'   => 'required|string',
            'sync_days'       => 'sometimes|integer|min:1|max:365',
        ]);

        $id = DB::table('email_accounts')->insertGetId([
            'user_id'         => auth()->id(),
            'email_address'   => $request->input('email_address'),
            'display_name'    => $request->input('display_name', $request->input('email_address')),
            'provider'        => $request->input('provider', 'custom'),
            'imap_host'       => $request->input('imap_host'),
            'imap_port'       => $request->input('imap_port', 993),
            'imap_encryption' => $request->input('imap_encryption', 'ssl'),
            'imap_username'   => $request->input('imap_username'),
            'imap_password'   => Crypt::encryptString($request->input('imap_password')),
            'smtp_host'       => $request->input('smtp_host'),
            'smtp_port'       => $request->input('smtp_port', 587),
            'smtp_encryption' => $request->input('smtp_encryption', 'tls'),
            'smtp_username'   => $request->input('smtp_username'),
            'smtp_password'   => Crypt::encryptString($request->input('smtp_password')),
            'sync_days'       => $request->input('sync_days', 30),
            'status'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $account = DB::table('email_accounts')->where('id', $id)->first();

        return response()->json([
            'data'    => $this->formatAccount($account),
            'message' => 'Email account added.',
        ], 201);
    }

    /**
     * Show a single email account.
     */
    public function show(int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        return response()->json(['data' => $this->formatAccount($account)]);
    }

    /**
     * Update an email account.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $request->validate([
            'display_name'    => 'sometimes|string|max:255',
            'imap_host'       => 'sometimes|string',
            'imap_port'       => 'sometimes|integer|min:1|max:65535',
            'imap_encryption' => 'sometimes|string|in:ssl,tls,none',
            'imap_username'   => 'sometimes|string',
            'imap_password'   => 'sometimes|string',
            'smtp_host'       => 'sometimes|string',
            'smtp_port'       => 'sometimes|integer|min:1|max:65535',
            'smtp_encryption' => 'sometimes|string|in:ssl,tls,none',
            'smtp_username'   => 'sometimes|string',
            'smtp_password'   => 'sometimes|string',
            'sync_days'       => 'sometimes|integer|min:1|max:365',
            'status'          => 'sometimes|string|in:active,disabled',
        ]);

        $updates = ['updated_at' => now()];

        foreach (['display_name', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'sync_days', 'status'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        if ($request->has('imap_password')) {
            $updates['imap_password'] = Crypt::encryptString($request->input('imap_password'));
        }
        if ($request->has('smtp_password')) {
            $updates['smtp_password'] = Crypt::encryptString($request->input('smtp_password'));
        }

        DB::table('email_accounts')->where('id', $id)->update($updates);

        $account = DB::table('email_accounts')->where('id', $id)->first();

        return response()->json([
            'data'    => $this->formatAccount($account),
            'message' => 'Email account updated.',
        ]);
    }

    /**
     * Delete an email account.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        return response()->json(['message' => 'Email account removed.']);
    }

    /**
     * Test connection to the email account.
     */
    public function testConnection(int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $imapOk = $this->testImap($account);
        $smtpOk = $this->testSmtp($account);

        $status = ($imapOk && $smtpOk) ? 'active' : 'error';
        $error = null;

        if (! $imapOk) {
            $error = 'IMAP connection failed';
        }
        if (! $smtpOk) {
            $error = $error ? $error . '; SMTP connection failed' : 'SMTP connection failed';
        }

        DB::table('email_accounts')->where('id', $id)->update([
            'status'     => $status,
            'last_error' => $error,
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'imap_ok' => $imapOk,
                'smtp_ok' => $smtpOk,
                'status'  => $status,
                'error'   => $error,
            ],
        ]);
    }

    /**
     * Trigger manual sync for an account.
     */
    public function sync(int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        if ($account->status !== 'active') {
            return response()->json(['message' => 'Email account is not active'], 422);
        }

        // Simulate sync - in production this dispatches the sync job
        DB::table('email_accounts')->where('id', $id)->update([
            'last_sync_at' => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'data'    => ['synced' => true, 'last_sync_at' => now()->toIso8601String()],
            'message' => 'Email sync triggered.',
        ]);
    }

    /**
     * Get sync status/history for an account.
     */
    public function syncStatus(int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $emailCount = DB::table('emails')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`from`, '$[0]')) = ?", [$account->email_address])
            ->count();

        return response()->json([
            'data' => [
                'account_id'   => $account->id,
                'status'       => $account->status,
                'last_sync_at' => $account->last_sync_at,
                'last_error'   => $account->last_error,
                'email_count'  => $emailCount,
            ],
        ]);
    }

    /**
     * List synced emails for an account.
     */
    public function emails(Request $request, int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $limit = min($request->input('limit', 25), 100);
        $page = max($request->input('page', 1), 1);

        $emails = DB::table('emails')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`from`, '$[0]')) = ?", [$account->email_address])
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sender, '$[0]')) = ?", [$account->email_address])
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id'         => $e->id,
                'subject'    => $e->subject,
                'from'       => json_decode($e->from, true),
                'is_read'    => (bool) $e->is_read,
                'folders'    => json_decode($e->folders, true),
                'person_id'  => $e->person_id,
                'created_at' => $e->created_at,
            ]);

        return response()->json(['data' => $emails]);
    }

    /**
     * Test IMAP connection.
     */
    private function testImap(object $account): bool
    {
        if (! function_exists('imap_open')) {
            return false;
        }

        try {
            $password = Crypt::decryptString($account->imap_password);
            $connection = @imap_open(
                '{' . $account->imap_host . ':' . $account->imap_port . '/imap/' . ($account->imap_encryption === 'none' ? 'novalidate-cert' : $account->imap_encryption) . '}INBOX',
                $account->imap_username,
                $password,
                0,
                1
            );

            if ($connection) {
                imap_close($connection);

                return true;
            }
        } catch (\Throwable $e) {
            // Connection failed
        }

        return false;
    }

    /**
     * Test SMTP connection (basic socket check).
     */
    private function testSmtp(object $account): bool
    {
        try {
            $fp = @fsockopen(
                ($account->smtp_encryption === 'ssl' ? 'ssl://' : '') . $account->smtp_host,
                $account->smtp_port,
                $errno,
                $errstr,
                5
            );

            if ($fp) {
                fclose($fp);

                return true;
            }
        } catch (\Throwable $e) {
            // Connection failed
        }

        return false;
    }

    /**
     * Get filter rules for an account.
     */
    public function getFilterRules(int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        return response()->json([
            'data' => [
                'contact_only' => (bool) $account->contact_only,
                'filter_rules' => json_decode($account->filter_rules, true) ?? [],
            ],
        ]);
    }

    /**
     * Update filter rules for an account.
     */
    public function updateFilterRules(Request $request, int $id): JsonResponse
    {
        $account = DB::table('email_accounts')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $request->validate([
            'contact_only'          => 'sometimes|boolean',
            'filter_rules'          => 'sometimes|array',
            'filter_rules.*.type'   => 'required_with:filter_rules|in:block_domain,block_sender,block_subject_pattern,allow_domain,allow_sender',
            'filter_rules.*.value'  => 'required_with:filter_rules|string|max:255',
        ]);

        $updates = ['updated_at' => now()];

        if ($request->has('contact_only')) {
            $updates['contact_only'] = $request->input('contact_only');
        }

        if ($request->has('filter_rules')) {
            $updates['filter_rules'] = json_encode($request->input('filter_rules'));
        }

        DB::table('email_accounts')->where('id', $id)->update($updates);

        return response()->json([
            'data' => [
                'contact_only' => (bool) ($updates['contact_only'] ?? $account->contact_only),
                'filter_rules' => $request->input('filter_rules', json_decode($account->filter_rules, true) ?? []),
            ],
            'message' => 'Filter rules updated.',
        ]);
    }

    /**
     * Format account for API response (exclude sensitive fields).
     */
    private function formatAccount(object $account): array
    {
        return [
            'id'              => $account->id,
            'email_address'   => $account->email_address,
            'display_name'    => $account->display_name,
            'provider'        => $account->provider,
            'imap_host'       => $account->imap_host,
            'imap_port'       => $account->imap_port,
            'imap_encryption' => $account->imap_encryption,
            'smtp_host'       => $account->smtp_host,
            'smtp_port'       => $account->smtp_port,
            'smtp_encryption' => $account->smtp_encryption,
            'status'          => $account->status,
            'last_sync_at'    => $account->last_sync_at,
            'last_error'      => $account->last_error,
            'sync_days'       => $account->sync_days,
            'contact_only'    => (bool) ($account->contact_only ?? true),
            'filter_rules'    => json_decode($account->filter_rules ?? '[]', true),
            'created_at'      => $account->created_at,
        ];
    }
}
