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

        // Normalize singular to plural
        if ($data['actionable_type'] === 'lead') $data['actionable_type'] = 'leads';
        if ($data['actionable_type'] === 'person') $data['actionable_type'] = 'persons';

        $data['user_id'] = auth()->guard('user')->id();
        $data['status'] = 'pending';

        $action = $this->nextActionRepository->create($data);

        return response()->json(['data' => $action, 'message' => 'Next action created.'], 201);
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
