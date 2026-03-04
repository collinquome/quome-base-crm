<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkEmailController extends Controller
{
    /**
     * Send a bulk email to multiple contacts.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'subject'        => 'required|string|max:500',
            'body'           => 'required|string',
            'contact_ids'    => 'required|array|min:1',
            'contact_ids.*'  => 'integer|exists:persons,id',
            'send_at'        => 'sometimes|date|after:now',
        ]);

        $user = $request->user();
        $contactIds = $request->input('contact_ids');
        $sendAt = $request->input('send_at');

        // Check daily send limit
        $dailyLimit = (int) config('mail.bulk_daily_limit', 450);
        $todaySent = DB::table('emails')
            ->where('user_type', 'admin')
            ->whereJsonContains('folders', 'bulk')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $remaining = $dailyLimit - $todaySent;
        if (count($contactIds) > $remaining) {
            return response()->json([
                'message' => "Daily send limit exceeded. You can send {$remaining} more emails today (limit: {$dailyLimit}).",
                'data'    => ['daily_limit' => $dailyLimit, 'sent_today' => $todaySent, 'remaining' => $remaining],
            ], 422);
        }

        // Get contacts with their email addresses (emails stored as JSON in persons.emails)
        $contacts = DB::table('persons')
            ->whereIn('id', $contactIds)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'emails')
            ->get()
            ->keyBy('id');

        $created = [];
        $skipped = [];

        foreach ($contactIds as $contactId) {
            $person = $contacts->get($contactId);
            if (! $person) {
                $skipped[] = ['contact_id' => $contactId, 'reason' => 'not_found'];
                continue;
            }

            // Parse emails JSON
            $emails = json_decode($person->emails, true) ?: [];
            $email = $emails[0]['value'] ?? null;

            if (! $email) {
                $skipped[] = ['contact_id' => $contactId, 'reason' => 'no_email'];
                continue;
            }

            // Apply merge fields
            $mergedBody = str_replace(
                ['{{name}}', '{{first_name}}', '{{email}}'],
                [$person->name, explode(' ', $person->name)[0] ?? '', $email],
                $request->input('body')
            );
            $mergedSubject = str_replace(
                ['{{name}}', '{{first_name}}', '{{email}}'],
                [$person->name, explode(' ', $person->name)[0] ?? '', $email],
                $request->input('subject')
            );

            $messageId = '<bulk-' . Str::uuid() . '@' . config('app.url', 'crm.local') . '>';
            $trackingId = Str::uuid()->toString();

            $emailId = DB::table('emails')->insertGetId([
                'subject'     => $mergedSubject,
                'source'      => 'web',
                'user_type'   => 'admin',
                'name'        => $user->name,
                'reply'       => $mergedBody,
                'from'        => json_encode([['address' => $email, 'name' => $person->name]]),
                'sender'      => json_encode([['address' => $user->email, 'name' => $user->name]]),
                'reply_to'    => json_encode([['address' => $user->email, 'name' => $user->name]]),
                'folders'     => json_encode(['bulk', 'outbox']),
                'is_read'     => 1,
                'message_id'  => $messageId,
                'tracking_id' => $trackingId,
                'person_id'   => $contactId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // If scheduled for later, create a scheduled_emails record
            if ($sendAt) {
                DB::table('scheduled_emails')->insert([
                    'email_id'     => $emailId,
                    'scheduled_at' => Carbon::parse($sendAt),
                    'status'       => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $created[] = [
                'email_id'    => $emailId,
                'contact_id'  => $contactId,
                'contact_name' => $person->name,
                'to_email'    => $email,
                'tracking_id' => $trackingId,
            ];
        }

        return response()->json([
            'data' => [
                'sent'    => count($created),
                'skipped' => count($skipped),
                'emails'  => $created,
                'skipped_details' => $skipped,
                'scheduled' => $sendAt ? true : false,
            ],
            'message' => count($created) . ' emails ' . ($sendAt ? 'scheduled' : 'queued') . '.',
        ], 201);
    }

    /**
     * Get bulk email send limits and stats.
     */
    public function limits(Request $request): JsonResponse
    {
        $dailyLimit = (int) config('mail.bulk_daily_limit', 450);
        $todaySent = DB::table('emails')
            ->where('user_type', 'admin')
            ->whereJsonContains('folders', 'bulk')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return response()->json([
            'data' => [
                'daily_limit' => $dailyLimit,
                'sent_today'  => $todaySent,
                'remaining'   => max(0, $dailyLimit - $todaySent),
            ],
        ]);
    }
}
