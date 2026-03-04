<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmailSequenceController extends Controller
{
    /**
     * List all sequences.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('email_sequences')->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $sequences = $query->get()->map(function ($seq) {
            $seq->steps_count = DB::table('email_sequence_steps')
                ->where('sequence_id', $seq->id)->count();
            $seq->enrolled_count = DB::table('email_sequence_enrollments')
                ->where('sequence_id', $seq->id)->where('status', 'active')->count();

            return $seq;
        });

        return response()->json(['data' => $sequences]);
    }

    /**
     * Create a new sequence.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|string',
            'steps'       => 'sometimes|array',
            'steps.*.subject'     => 'required_with:steps|string|max:500',
            'steps.*.body'        => 'required_with:steps|string',
            'steps.*.delay_days'  => 'sometimes|integer|min:0',
            'steps.*.delay_hours' => 'sometimes|integer|min:0|max:23',
        ]);

        $sequenceId = DB::table('email_sequences')->insertGetId([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'status'      => 'draft',
            'created_by'  => $request->user()->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Create steps if provided
        if ($request->has('steps')) {
            foreach ($request->input('steps') as $i => $step) {
                DB::table('email_sequence_steps')->insert([
                    'sequence_id' => $sequenceId,
                    'position'    => $i,
                    'subject'     => $step['subject'],
                    'body'        => $step['body'],
                    'delay_days'  => $step['delay_days'] ?? 0,
                    'delay_hours' => $step['delay_hours'] ?? 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $sequence = DB::table('email_sequences')->where('id', $sequenceId)->first();

        return response()->json(['data' => $sequence], 201);
    }

    /**
     * Show a sequence with its steps.
     */
    public function show(int $id): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $id)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        $sequence->steps = DB::table('email_sequence_steps')
            ->where('sequence_id', $id)
            ->orderBy('position')
            ->get();

        $sequence->enrollments = DB::table('email_sequence_enrollments')
            ->where('sequence_id', $id)
            ->get();

        return response()->json(['data' => $sequence]);
    }

    /**
     * Update a sequence.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $id)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status'      => 'sometimes|string|in:draft,active,paused,archived',
        ]);

        DB::table('email_sequences')->where('id', $id)->update(array_filter([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'status'      => $request->input('status'),
            'updated_at'  => now(),
        ]));

        return response()->json([
            'data' => DB::table('email_sequences')->where('id', $id)->first(),
        ]);
    }

    /**
     * Delete a sequence.
     */
    public function destroy(int $id): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $id)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        DB::table('email_sequences')->where('id', $id)->delete();

        return response()->json(['message' => 'Sequence deleted.']);
    }

    /**
     * Add a step to a sequence.
     */
    public function addStep(Request $request, int $sequenceId): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $sequenceId)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        $request->validate([
            'subject'     => 'required|string|max:500',
            'body'        => 'required|string',
            'delay_days'  => 'sometimes|integer|min:0',
            'delay_hours' => 'sometimes|integer|min:0|max:23',
            'position'    => 'sometimes|integer|min:0',
        ]);

        $maxPosition = DB::table('email_sequence_steps')
            ->where('sequence_id', $sequenceId)
            ->max('position') ?? -1;

        $stepId = DB::table('email_sequence_steps')->insertGetId([
            'sequence_id' => $sequenceId,
            'position'    => $request->input('position', $maxPosition + 1),
            'subject'     => $request->input('subject'),
            'body'        => $request->input('body'),
            'delay_days'  => $request->input('delay_days', 0),
            'delay_hours' => $request->input('delay_hours', 0),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $step = DB::table('email_sequence_steps')->where('id', $stepId)->first();

        return response()->json(['data' => $step], 201);
    }

    /**
     * Enroll a contact in a sequence.
     */
    public function enroll(Request $request, int $sequenceId): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $sequenceId)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        $request->validate([
            'contact_ids'   => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:persons,id',
        ]);

        $enrolled = [];
        $skipped = [];

        $firstStep = DB::table('email_sequence_steps')
            ->where('sequence_id', $sequenceId)
            ->orderBy('position')
            ->first();

        foreach ($request->input('contact_ids') as $contactId) {
            // Check if already enrolled
            $existing = DB::table('email_sequence_enrollments')
                ->where('sequence_id', $sequenceId)
                ->where('person_id', $contactId)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                $skipped[] = ['contact_id' => $contactId, 'reason' => 'already_enrolled'];

                continue;
            }

            $nextSendAt = now();
            if ($firstStep) {
                $nextSendAt = now()
                    ->addDays($firstStep->delay_days)
                    ->addHours($firstStep->delay_hours);
            }

            DB::table('email_sequence_enrollments')->insert([
                'sequence_id'  => $sequenceId,
                'person_id'    => $contactId,
                'status'       => 'active',
                'current_step' => 0,
                'next_send_at' => $nextSendAt,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $enrolled[] = $contactId;
        }

        return response()->json([
            'data' => [
                'enrolled'        => count($enrolled),
                'skipped'         => count($skipped),
                'enrolled_ids'    => $enrolled,
                'skipped_details' => $skipped,
            ],
        ], 201);
    }

    /**
     * Stop a contact's enrollment.
     */
    public function unenroll(Request $request, int $sequenceId, int $contactId): JsonResponse
    {
        $enrollment = DB::table('email_sequence_enrollments')
            ->where('sequence_id', $sequenceId)
            ->where('person_id', $contactId)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'Active enrollment not found'], 404);
        }

        DB::table('email_sequence_enrollments')
            ->where('id', $enrollment->id)
            ->update([
                'status'     => 'stopped',
                'stopped_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Contact unenrolled from sequence.']);
    }

    /**
     * Get sequence performance metrics.
     */
    public function performance(int $sequenceId): JsonResponse
    {
        $sequence = DB::table('email_sequences')->where('id', $sequenceId)->first();

        if (! $sequence) {
            return response()->json(['message' => 'Sequence not found'], 404);
        }

        $enrollments = DB::table('email_sequence_enrollments')
            ->where('sequence_id', $sequenceId)
            ->get();

        $totalSteps = DB::table('email_sequence_steps')
            ->where('sequence_id', $sequenceId)
            ->count();

        $statusCounts = $enrollments->groupBy('status')->map->count();

        return response()->json([
            'data' => [
                'sequence_id'     => $sequenceId,
                'total_steps'     => $totalSteps,
                'total_enrolled'  => $enrollments->count(),
                'active'          => $statusCounts->get('active', 0),
                'completed'       => $statusCounts->get('completed', 0),
                'stopped'         => $statusCounts->get('stopped', 0),
                'replied'         => $statusCounts->get('replied', 0),
                'completion_rate' => $enrollments->count() > 0
                    ? round($statusCounts->get('completed', 0) / $enrollments->count() * 100, 1)
                    : 0,
                'reply_rate'      => $enrollments->count() > 0
                    ? round($statusCounts->get('replied', 0) / $enrollments->count() * 100, 1)
                    : 0,
            ],
        ]);
    }
}
