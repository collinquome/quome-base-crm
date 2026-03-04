<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Webkul\ReportBuilder\Repositories\ReportDefinitionRepository;
use Webkul\ReportBuilder\Services\ReportExecutor;

class ReportController extends Controller
{
    public function __construct(
        protected ReportDefinitionRepository $reportRepo,
        protected ReportExecutor $executor
    ) {}

    /**
     * List available entity types and their columns.
     */
    public function schema(): JsonResponse
    {
        return response()->json(['data' => $this->executor->getEntitySchema()]);
    }

    /**
     * List saved report definitions for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = $this->reportRepo->getForUser($request->user()->id);

        return response()->json(['data' => $reports]);
    }

    /**
     * Get a single report definition.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        // Check access
        if ($report->user_id !== $request->user()->id && ! $report->is_public) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $report]);
    }

    /**
     * Save a new report definition.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'entity_type' => 'required|in:leads,contacts,activities,products',
            'columns'     => 'required|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'chart_type'  => 'sometimes|nullable|in:bar,line,pie,table',
            'is_public'   => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name', 'entity_type', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order', 'chart_type', 'is_public',
        ]);
        $data['user_id'] = $request->user()->id;

        $report = $this->reportRepo->create($data);

        return response()->json(['data' => $report, 'message' => 'Report saved.'], 201);
    }

    /**
     * Update an existing report definition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'columns'     => 'sometimes|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'chart_type'  => 'sometimes|nullable|in:bar,line,pie,table',
            'is_public'   => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order', 'chart_type', 'is_public',
        ]);

        $this->reportRepo->update($data, $id);
        $report = $this->reportRepo->find($id);

        return response()->json(['data' => $report, 'message' => 'Report updated.']);
    }

    /**
     * Delete a report definition.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->reportRepo->delete($id);

        return response()->json(['message' => 'Report deleted.']);
    }

    /**
     * Execute a report (either saved or ad-hoc).
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|in:leads,contacts,activities,products',
            'columns'     => 'required|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'limit'       => 'sometimes|integer|min:1|max:10000',
        ]);

        $definition = $request->only([
            'entity_type', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order',
        ]);

        $limit = (int) $request->get('limit', 1000);
        $result = $this->executor->execute($definition, $limit);

        return response()->json(['data' => $result]);
    }

    /**
     * Execute a saved report by ID.
     */
    public function executeSaved(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id && ! $report->is_public) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $limit = (int) $request->get('limit', 1000);
        $result = $this->executor->execute($report->toArray(), $limit);

        return response()->json(['data' => $result]);
    }

    /**
     * List schedules for a report.
     */
    public function schedules(int $reportId, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($reportId);

        if ($report->user_id !== $request->user()->id && ! $report->is_public) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $schedules = DB::table('report_schedules')
            ->where('report_definition_id', $reportId)
            ->get()
            ->map(fn ($s) => $this->formatSchedule($s));

        return response()->json(['data' => $schedules]);
    }

    /**
     * Create a schedule for a report.
     */
    public function createSchedule(int $reportId, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($reportId);

        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'frequency'    => 'required|in:daily,weekly,monthly',
            'day_of_week'  => 'required_if:frequency,weekly|nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'required_if:frequency,monthly|nullable|integer|min:1|max:28',
            'time_of_day'  => 'sometimes|date_format:H:i',
            'format'       => 'sometimes|in:csv,pdf,xls',
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'email',
            'subject'      => 'sometimes|nullable|string|max:255',
        ]);

        $nextRun = $this->calculateNextRun(
            $request->input('frequency'),
            $request->input('time_of_day', '08:00'),
            $request->input('day_of_week'),
            $request->input('day_of_month')
        );

        $id = DB::table('report_schedules')->insertGetId([
            'report_definition_id' => $reportId,
            'frequency'            => $request->input('frequency'),
            'day_of_week'          => $request->input('day_of_week'),
            'day_of_month'         => $request->input('day_of_month'),
            'time_of_day'          => $request->input('time_of_day', '08:00') . ':00',
            'format'               => $request->input('format', 'csv'),
            'recipients'           => json_encode($request->input('recipients')),
            'subject'              => $request->input('subject'),
            'is_active'            => true,
            'next_run_at'          => $nextRun,
            'user_id'              => $request->user()->id,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $schedule = DB::table('report_schedules')->where('id', $id)->first();

        return response()->json([
            'data'    => $this->formatSchedule($schedule),
            'message' => 'Schedule created.',
        ], 201);
    }

    /**
     * Update a schedule.
     */
    public function updateSchedule(int $reportId, int $scheduleId, Request $request): JsonResponse
    {
        $schedule = DB::table('report_schedules')
            ->where('id', $scheduleId)
            ->where('report_definition_id', $reportId)
            ->first();

        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        if ($schedule->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'frequency'    => 'sometimes|in:daily,weekly,monthly',
            'day_of_week'  => 'sometimes|nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'sometimes|nullable|integer|min:1|max:28',
            'time_of_day'  => 'sometimes|date_format:H:i',
            'format'       => 'sometimes|in:csv,pdf,xls',
            'recipients'   => 'sometimes|array|min:1',
            'recipients.*' => 'email',
            'subject'      => 'sometimes|nullable|string|max:255',
            'is_active'    => 'sometimes|boolean',
        ]);

        $updates = ['updated_at' => now()];

        foreach (['frequency', 'day_of_week', 'day_of_month', 'format', 'subject', 'is_active'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        if ($request->has('time_of_day')) {
            $updates['time_of_day'] = $request->input('time_of_day') . ':00';
        }

        if ($request->has('recipients')) {
            $updates['recipients'] = json_encode($request->input('recipients'));
        }

        // Recalculate next_run_at if scheduling fields changed
        if ($request->hasAny(['frequency', 'time_of_day', 'day_of_week', 'day_of_month'])) {
            $updates['next_run_at'] = $this->calculateNextRun(
                $request->input('frequency', $schedule->frequency),
                $request->input('time_of_day', substr($schedule->time_of_day, 0, 5)),
                $request->input('day_of_week', $schedule->day_of_week),
                $request->input('day_of_month', $schedule->day_of_month)
            );
        }

        DB::table('report_schedules')->where('id', $scheduleId)->update($updates);

        $schedule = DB::table('report_schedules')->where('id', $scheduleId)->first();

        return response()->json([
            'data'    => $this->formatSchedule($schedule),
            'message' => 'Schedule updated.',
        ]);
    }

    /**
     * Delete a schedule.
     */
    public function deleteSchedule(int $reportId, int $scheduleId, Request $request): JsonResponse
    {
        $schedule = DB::table('report_schedules')
            ->where('id', $scheduleId)
            ->where('report_definition_id', $reportId)
            ->first();

        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        if ($schedule->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::table('report_schedules')->where('id', $scheduleId)->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    /**
     * Calculate the next run timestamp.
     */
    private function calculateNextRun(string $frequency, string $time, ?string $dayOfWeek = null, ?int $dayOfMonth = null): string
    {
        $now = now();
        $timeparts = explode(':', $time);
        $hour = (int) ($timeparts[0] ?? 8);
        $minute = (int) ($timeparts[1] ?? 0);

        switch ($frequency) {
            case 'daily':
                $next = $now->copy()->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addDay();
                }

                return $next->toDateTimeString();

            case 'weekly':
                $dayMap = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0];
                $targetDay = $dayMap[$dayOfWeek] ?? 1;
                $next = $now->copy()->next($targetDay)->setTime($hour, $minute);

                return $next->toDateTimeString();

            case 'monthly':
                $day = min($dayOfMonth ?? 1, 28);
                $next = $now->copy()->day($day)->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addMonth();
                }

                return $next->toDateTimeString();

            default:
                return $now->addDay()->setTime($hour, $minute)->toDateTimeString();
        }
    }

    /**
     * Format a schedule for API response.
     */
    private function formatSchedule(object $schedule): array
    {
        return [
            'id'                    => $schedule->id,
            'report_definition_id'  => $schedule->report_definition_id,
            'frequency'             => $schedule->frequency,
            'day_of_week'           => $schedule->day_of_week,
            'day_of_month'          => $schedule->day_of_month,
            'time_of_day'           => $schedule->time_of_day,
            'format'                => $schedule->format,
            'recipients'            => json_decode($schedule->recipients, true),
            'subject'               => $schedule->subject,
            'is_active'             => (bool) $schedule->is_active,
            'last_run_at'           => $schedule->last_run_at,
            'next_run_at'           => $schedule->next_run_at,
            'user_id'               => $schedule->user_id,
            'created_at'            => $schedule->created_at,
        ];
    }
}
