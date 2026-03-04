<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PlaybookController extends Controller
{
    /**
     * List all playbooks.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('playbooks')->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $playbooks = $query->get()->map(function ($pb) {
            $pb->steps_count = DB::table('playbook_steps')
                ->where('playbook_id', $pb->id)->count();
            $pb->active_executions = DB::table('playbook_executions')
                ->where('playbook_id', $pb->id)->where('status', 'running')->count();

            return $pb;
        });

        return response()->json(['data' => $playbooks]);
    }

    /**
     * Create a new playbook.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'sometimes|string',
            'trigger_type' => 'sometimes|string|in:manual,lead_created,stage_changed,contact_created',
            'steps'        => 'sometimes|array',
            'steps.*.action_type' => 'required_with:steps|string|in:create_activity,send_email,update_field,wait,add_tag,remove_tag',
            'steps.*.config'      => 'sometimes|array',
            'steps.*.delay_days'  => 'sometimes|integer|min:0',
            'steps.*.delay_hours' => 'sometimes|integer|min:0|max:23',
        ]);

        $playbookId = DB::table('playbooks')->insertGetId([
            'name'         => $request->input('name'),
            'description'  => $request->input('description'),
            'trigger_type' => $request->input('trigger_type', 'manual'),
            'status'       => 'draft',
            'created_by'   => $request->user()->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        if ($request->has('steps')) {
            foreach ($request->input('steps') as $i => $step) {
                DB::table('playbook_steps')->insert([
                    'playbook_id' => $playbookId,
                    'position'    => $i,
                    'action_type' => $step['action_type'],
                    'config'      => json_encode($step['config'] ?? []),
                    'delay_days'  => $step['delay_days'] ?? 0,
                    'delay_hours' => $step['delay_hours'] ?? 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $playbook = DB::table('playbooks')->where('id', $playbookId)->first();

        return response()->json(['data' => $playbook], 201);
    }

    /**
     * Show a playbook with steps.
     */
    public function show(int $id): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $id)->first();

        if (! $playbook) {
            return response()->json(['message' => 'Playbook not found'], 404);
        }

        $playbook->steps = DB::table('playbook_steps')
            ->where('playbook_id', $id)
            ->orderBy('position')
            ->get()
            ->map(function ($step) {
                $step->config = json_decode($step->config, true);

                return $step;
            });

        $playbook->executions_summary = [
            'total'     => DB::table('playbook_executions')->where('playbook_id', $id)->count(),
            'running'   => DB::table('playbook_executions')->where('playbook_id', $id)->where('status', 'running')->count(),
            'completed' => DB::table('playbook_executions')->where('playbook_id', $id)->where('status', 'completed')->count(),
            'failed'    => DB::table('playbook_executions')->where('playbook_id', $id)->where('status', 'failed')->count(),
        ];

        return response()->json(['data' => $playbook]);
    }

    /**
     * Update a playbook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $id)->first();

        if (! $playbook) {
            return response()->json(['message' => 'Playbook not found'], 404);
        }

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'sometimes|string',
            'status'       => 'sometimes|string|in:draft,active,archived',
            'trigger_type' => 'sometimes|string|in:manual,lead_created,stage_changed,contact_created',
        ]);

        DB::table('playbooks')->where('id', $id)->update(array_filter([
            'name'         => $request->input('name'),
            'description'  => $request->input('description'),
            'status'       => $request->input('status'),
            'trigger_type' => $request->input('trigger_type'),
            'updated_at'   => now(),
        ]));

        return response()->json([
            'data' => DB::table('playbooks')->where('id', $id)->first(),
        ]);
    }

    /**
     * Delete a playbook.
     */
    public function destroy(int $id): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $id)->first();

        if (! $playbook) {
            return response()->json(['message' => 'Playbook not found'], 404);
        }

        DB::table('playbooks')->where('id', $id)->delete();

        return response()->json(['message' => 'Playbook deleted.']);
    }

    /**
     * Add a step to a playbook.
     */
    public function addStep(Request $request, int $playbookId): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $playbookId)->first();

        if (! $playbook) {
            return response()->json(['message' => 'Playbook not found'], 404);
        }

        $request->validate([
            'action_type' => 'required|string|in:create_activity,send_email,update_field,wait,add_tag,remove_tag',
            'config'      => 'sometimes|array',
            'delay_days'  => 'sometimes|integer|min:0',
            'delay_hours' => 'sometimes|integer|min:0|max:23',
            'position'    => 'sometimes|integer|min:0',
        ]);

        $maxPosition = DB::table('playbook_steps')
            ->where('playbook_id', $playbookId)
            ->max('position') ?? -1;

        $stepId = DB::table('playbook_steps')->insertGetId([
            'playbook_id' => $playbookId,
            'position'    => $request->input('position', $maxPosition + 1),
            'action_type' => $request->input('action_type'),
            'config'      => json_encode($request->input('config', [])),
            'delay_days'  => $request->input('delay_days', 0),
            'delay_hours' => $request->input('delay_hours', 0),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $step = DB::table('playbook_steps')->where('id', $stepId)->first();
        $step->config = json_decode($step->config, true);

        return response()->json(['data' => $step], 201);
    }

    /**
     * Execute a playbook on an entity.
     */
    public function execute(Request $request, int $playbookId): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $playbookId)->first();

        if (! $playbook) {
            return response()->json(['message' => 'Playbook not found'], 404);
        }

        $request->validate([
            'entity_type' => 'required|string|in:persons,leads',
            'entity_id'   => 'required|integer',
        ]);

        $steps = DB::table('playbook_steps')
            ->where('playbook_id', $playbookId)
            ->orderBy('position')
            ->get();

        if ($steps->isEmpty()) {
            return response()->json(['message' => 'Playbook has no steps'], 422);
        }

        $firstStep = $steps->first();
        $nextActionAt = now()
            ->addDays($firstStep->delay_days)
            ->addHours($firstStep->delay_hours);

        $executionId = DB::table('playbook_executions')->insertGetId([
            'playbook_id'   => $playbookId,
            'entity_type'   => $request->input('entity_type'),
            'entity_id'     => $request->input('entity_id'),
            'status'        => 'running',
            'current_step'  => 0,
            'next_action_at' => $nextActionAt,
            'log'           => json_encode([['step' => 0, 'status' => 'started', 'at' => now()->toIso8601String()]]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'data' => [
                'execution_id'  => $executionId,
                'playbook_id'   => $playbookId,
                'entity_type'   => $request->input('entity_type'),
                'entity_id'     => $request->input('entity_id'),
                'status'        => 'running',
                'total_steps'   => $steps->count(),
                'next_action_at' => $nextActionAt->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Cancel a playbook execution.
     */
    public function cancelExecution(int $executionId): JsonResponse
    {
        $execution = DB::table('playbook_executions')->where('id', $executionId)->first();

        if (! $execution) {
            return response()->json(['message' => 'Execution not found'], 404);
        }

        if ($execution->status !== 'running') {
            return response()->json(['message' => 'Only running executions can be cancelled'], 422);
        }

        DB::table('playbook_executions')->where('id', $executionId)->update([
            'status'     => 'cancelled',
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Execution cancelled.']);
    }
}
