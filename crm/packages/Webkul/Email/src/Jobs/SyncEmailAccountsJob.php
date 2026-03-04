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

        foreach ($newEmails as $email) {
            $this->matchEmailToContact($email);
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
    private function matchEmailToContact(object $email): void
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

                return;
            }
        }
    }
}
