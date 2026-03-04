<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Email\Models\ScheduledEmail;

class ScheduledEmailController extends Controller
{
    /**
     * List scheduled emails for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:pending,sent,cancelled,failed',
        ]);

        $query = DB::table('scheduled_emails')
            ->join('emails', 'scheduled_emails.email_id', '=', 'emails.id')
            ->where('emails.user_type', 'admin')
            ->select(
                'scheduled_emails.*',
                'emails.subject',
                'emails.from',
                'emails.reply as body',
            )
            ->orderBy('scheduled_emails.scheduled_at');

        if ($status = $request->get('status')) {
            $query->where('scheduled_emails.status', $status);
        }

        $scheduled = $query->get()->map(function ($item) {
            $item->from = json_decode($item->from, true);
            return $item;
        });

        return response()->json(['data' => $scheduled]);
    }

    /**
     * Schedule an email to be sent later.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject'      => 'required|string|max:500',
            'reply'        => 'required|string',
            'to'           => 'required|array|min:1',
            'to.*.address' => 'required|email',
            'to.*.name'    => 'sometimes|string',
            'cc'           => 'sometimes|array',
            'bcc'          => 'sometimes|array',
            'scheduled_at' => 'required|date|after:now',
            'person_id'    => 'sometimes|integer|exists:persons,id',
            'lead_id'      => 'sometimes|integer|exists:leads,id',
        ]);

        $user = $request->user();

        // Create the email record (in draft-like state)
        $email = DB::table('emails')->insertGetId([
            'subject'    => $request->input('subject'),
            'source'     => 'web',
            'user_type'  => 'admin',
            'name'       => $user->name,
            'reply'      => $request->input('reply'),
            'from'       => json_encode($request->input('to')),
            'sender'     => json_encode([['address' => $user->email, 'name' => $user->name]]),
            'reply_to'   => json_encode([['address' => $user->email, 'name' => $user->name]]),
            'cc'         => $request->input('cc') ? json_encode($request->input('cc')) : null,
            'bcc'        => $request->input('bcc') ? json_encode($request->input('bcc')) : null,
            'folders'    => json_encode(['scheduled']),
            'is_read'    => 1,
            'person_id'  => $request->input('person_id'),
            'lead_id'    => $request->input('lead_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the scheduled email record
        $scheduledId = DB::table('scheduled_emails')->insertGetId([
            'email_id'     => $email,
            'scheduled_at' => Carbon::parse($request->input('scheduled_at')),
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $scheduled = DB::table('scheduled_emails')->find($scheduledId);

        return response()->json([
            'data'    => $scheduled,
            'message' => 'Email scheduled successfully.',
        ], 201);
    }

    /**
     * Get a single scheduled email.
     */
    public function show(int $id): JsonResponse
    {
        $scheduled = DB::table('scheduled_emails')
            ->join('emails', 'scheduled_emails.email_id', '=', 'emails.id')
            ->where('scheduled_emails.id', $id)
            ->select(
                'scheduled_emails.*',
                'emails.subject',
                'emails.from',
                'emails.reply as body',
            )
            ->first();

        if (! $scheduled) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $scheduled->from = json_decode($scheduled->from, true);

        return response()->json(['data' => $scheduled]);
    }

    /**
     * Cancel a pending scheduled email.
     */
    public function cancel(int $id): JsonResponse
    {
        $scheduled = DB::table('scheduled_emails')->find($id);

        if (! $scheduled) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($scheduled->status !== 'pending') {
            return response()->json([
                'message' => "Cannot cancel a scheduled email with status '{$scheduled->status}'.",
            ], 422);
        }

        DB::table('scheduled_emails')->where('id', $id)->update([
            'status'     => 'cancelled',
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Scheduled email cancelled.']);
    }

    /**
     * Reschedule a pending email.
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $scheduled = DB::table('scheduled_emails')->find($id);

        if (! $scheduled) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($scheduled->status !== 'pending') {
            return response()->json([
                'message' => "Cannot reschedule a scheduled email with status '{$scheduled->status}'.",
            ], 422);
        }

        DB::table('scheduled_emails')->where('id', $id)->update([
            'scheduled_at' => Carbon::parse($request->input('scheduled_at')),
            'updated_at'   => now(),
        ]);

        $updated = DB::table('scheduled_emails')->find($id);

        return response()->json([
            'data'    => $updated,
            'message' => 'Scheduled email rescheduled.',
        ]);
    }
}
