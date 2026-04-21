<?php

namespace Webkul\Admin\Http\Controllers\ActionStream;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\ActionStream\Repositories\NextActionRepository;

class ActionStreamController extends Controller
{
    public function __construct(
        protected NextActionRepository $nextActionRepository
    ) {}

    /**
     * Display the action stream page.
     */
    public function index(): View
    {
        return view('admin::action-stream.index');
    }

    /**
     * Get the global prioritized action stream for the current user.
     */
    public function stream(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'priority', 'due_from', 'due_to', 'status']);

        $query = $this->nextActionRepository->getPrioritizedActions(
            auth()->guard('user')->id(),
            $filters
        );

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Get the overdue action count for the current user.
     */
    public function overdueCount(): JsonResponse
    {
        $count = $this->nextActionRepository->getOverdueCount(
            auth()->guard('user')->id()
        );

        return response()->json(['data' => ['overdue_count' => $count]]);
    }

    /**
     * Snooze an action.
     */
    public function snooze(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'snooze_until' => 'required|date|after:now',
        ]);

        $action = $this->nextActionRepository->snooze($id, $request->get('snooze_until'));

        return response()->json(['data' => $action, 'message' => 'Action snoozed.']);
    }

    /**
     * Get actions for a given entity (used by next-action-widget).
     */
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'actionable_type' => 'required|string|in:persons,leads,lead,person',
            'actionable_id'   => 'required|integer',
            'status'          => 'sometimes|string|in:pending,completed,snoozed',
        ]);

        $type = $request->get('actionable_type');
        // Normalize singular to plural
        if ($type === 'lead') $type = 'leads';
        if ($type === 'person') $type = 'persons';

        $status = $request->get('status', 'pending');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = $this->nextActionRepository->scopeQuery(function ($q) use ($type, $request, $status) {
            return $q->where('actionable_type', $type)
                ->where('actionable_id', $request->get('actionable_id'))
                ->where('status', $status)
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
                ->orderBy('due_date', 'asc');
        });

        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a new action (used by next-action-widget and stage prompt).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'actionable_type' => 'required|string|in:persons,leads,lead,person',
            'actionable_id'   => 'required|integer',
            'action_type'     => 'sometimes|string|in:call,email,meeting,task,custom',
            'description'     => 'sometimes|string|max:1000',
            'due_date'        => 'sometimes|nullable|date',
            'due_time'        => 'sometimes|nullable|date_format:H:i',
            'priority'        => 'sometimes|string|in:urgent,high,normal,low',
        ]);

        $data = $request->all();

        // Strip empty strings that would fail date validation
        if (array_key_exists('due_date', $data) && $data['due_date'] === '') {
            unset($data['due_date']);
        }
        if (array_key_exists('due_time', $data) && $data['due_time'] === '') {
            unset($data['due_time']);
        }

        // Normalize singular to plural
        if ($data['actionable_type'] === 'lead') $data['actionable_type'] = 'leads';
        if ($data['actionable_type'] === 'person') $data['actionable_type'] = 'persons';

        $data['user_id'] = auth()->guard('user')->id();
        $data['status'] = 'pending';

        $action = $this->nextActionRepository->create($data);

        return response()->json(['data' => $action, 'message' => 'Next action created.'], 201);
    }

    /**
     * Update an existing action.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action_type' => 'sometimes|string|in:call,email,meeting,task,custom',
            'description' => 'sometimes|string|max:1000',
            'due_date'    => 'sometimes|nullable|date',
            'due_time'    => 'sometimes|nullable|date_format:H:i',
            'priority'    => 'sometimes|string|in:urgent,high,normal,low',
            'status'      => 'sometimes|string|in:pending,completed,snoozed',
        ]);

        $data = $request->only(['action_type', 'description', 'due_date', 'due_time', 'priority', 'status']);

        if (array_key_exists('due_date', $data) && $data['due_date'] === '') {
            $data['due_date'] = null;
        }
        if (array_key_exists('due_time', $data) && $data['due_time'] === '') {
            $data['due_time'] = null;
        }

        // Reopening: when status flips back to pending, clear completed_at.
        if (array_key_exists('status', $data) && $data['status'] === 'pending') {
            $data['completed_at'] = null;
        }

        $action = $this->nextActionRepository->findOrFail($id);
        $action->update($data);

        return response()->json(['data' => $action->fresh(), 'message' => 'Action updated.']);
    }

    /**
     * Mark an action as completed.
     */
    public function complete(int $id): JsonResponse
    {
        $action = $this->nextActionRepository->complete($id);

        return response()->json(['data' => $action, 'message' => 'Action completed.']);
    }
}
