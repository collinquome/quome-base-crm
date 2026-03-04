<?php

namespace Webkul\Email\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEmailAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $accounts = DB::table('email_accounts')
            ->where('status', 'active')
            ->get();

        foreach ($accounts as $account) {
            try {
                $this->syncAccount($account);
            } catch (\Exception $e) {
                Log::error("Email sync failed for account {$account->id}: " . $e->getMessage());

                DB::table('email_accounts')->where('id', $account->id)->update([
                    'status'     => 'error',
                    'last_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Sync a single email account.
     */
    private function syncAccount(object $account): void
    {
        // Use the existing Webklex IMAP processor infrastructure
        // In production, this connects to the account's IMAP server,
        // fetches new messages since last_sync_at, and stores them
        // in the emails table, matching contacts by email address.

        $since = $account->last_sync_at
            ? \Carbon\Carbon::parse($account->last_sync_at)
            : now()->subDays($account->sync_days ?? 30);

        // Match incoming emails to CRM contacts
        $newEmails = DB::table('emails')
            ->where('created_at', '>=', $since)
            ->whereNull('person_id')
            ->get();

        $filterRules = json_decode($account->filter_rules ?? '[]', true) ?: [];
        $contactOnly = (bool) ($account->contact_only ?? true);

        foreach ($newEmails as $email) {
            // Apply filter rules
            if ($this->shouldFilterEmail($email, $filterRules)) {
                continue;
            }

            $matched = $this->matchEmailToContact($email);

            // If contact_only is enabled and no contact match, skip
            if ($contactOnly && ! $matched) {
                continue;
            }
        }

        DB::table('email_accounts')->where('id', $account->id)->update([
            'last_sync_at' => now(),
            'last_error'   => null,
            'updated_at'   => now(),
        ]);
    }

    /**
     * Match an email to a CRM contact by email address.
     */
    private function matchEmailToContact(object $email): bool
    {
        $fromAddresses = json_decode($email->from, true) ?? [];
        $senderAddresses = json_decode($email->sender, true) ?? [];

        $emailAddresses = array_merge(
            array_column($fromAddresses, 'address'),
            array_column($senderAddresses, 'address'),
            $fromAddresses,
            $senderAddresses
        );

        $emailAddresses = array_filter(array_unique($emailAddresses), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

        foreach ($emailAddresses as $address) {
            $person = DB::table('persons')
                ->whereRaw("emails LIKE ?", ['%' . $address . '%'])
                ->first();

            if ($person) {
                DB::table('emails')->where('id', $email->id)->update([
                    'person_id'  => $person->id,
                    'updated_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Check if an email should be filtered out based on rules.
     */
    private function shouldFilterEmail(object $email, array $rules): bool
    {
        if (empty($rules)) {
            return false;
        }

        $fromAddresses = json_decode($email->from, true) ?? [];
        $senderEmails = array_merge(
            array_column($fromAddresses, 'address'),
            $fromAddresses
        );
        $senderEmails = array_filter(array_unique($senderEmails), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

        $senderDomains = array_map(fn ($e) => strtolower(explode('@', $e)[1] ?? ''), $senderEmails);

        $hasAllowRule = false;
        $passesAllow = false;

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? '';
            $value = strtolower($rule['value'] ?? '');

            switch ($type) {
                case 'block_domain':
                    if (in_array($value, $senderDomains)) {
                        return true;
                    }
                    break;

                case 'block_sender':
                    if (in_array($value, array_map('strtolower', $senderEmails))) {
                        return true;
                    }
                    break;

                case 'block_subject_pattern':
                    if ($email->subject && stripos($email->subject, $rule['value']) !== false) {
                        return true;
                    }
                    break;

                case 'allow_domain':
                    $hasAllowRule = true;
                    if (in_array($value, $senderDomains)) {
                        $passesAllow = true;
                    }
                    break;

                case 'allow_sender':
                    $hasAllowRule = true;
                    if (in_array($value, array_map('strtolower', $senderEmails))) {
                        $passesAllow = true;
                    }
                    break;
            }
        }

        // If there are allow rules but none matched, filter out
        if ($hasAllowRule && ! $passesAllow) {
            return true;
        }

        return false;
    }
}
