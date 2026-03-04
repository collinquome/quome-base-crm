<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\ActionStream\Repositories\NextActionRepository;
use Webkul\User\Repositories\UserRepository;

class ActionStreamController extends Controller
{
    public function __construct(
        protected NextActionRepository $nextActionRepository,
        protected UserRepository $userRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'priority', 'due_from', 'due_to']);

        $query = $this->nextActionRepository->getPrioritizedActions(
            $request->user()->id,
            $filters
        );

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $action = $this->nextActionRepository->findOrFail($id);

        $action->load('actionable');

        return response()->json(['data' => $action]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'actionable_type' => 'required|string|in:persons,leads',
            'actionable_id'   => 'required|integer',
            'action_type'     => 'sometimes|string|in:call,email,meeting,task,custom',
            'description'     => 'sometimes|string|max:1000',
            'due_date'        => 'sometimes|date',
            'due_time'        => 'sometimes|date_format:H:i',
            'priority'        => 'sometimes|string|in:urgent,high,normal,low',
        ]);

        $data = $request->all();
        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';

        $action = $this->nextActionRepository->create($data);

        return response()->json(['data' => $action, 'message' => 'Next action created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action_type' => 'sometimes|string|in:call,email,meeting,task,custom',
            'description' => 'sometimes|string|max:1000',
            'due_date'    => 'sometimes|date',
            'due_time'    => 'sometimes|date_format:H:i',
            'priority'    => 'sometimes|string|in:urgent,high,normal,low',
        ]);

        $action = $this->nextActionRepository->update($request->all(), $id);

        return response()->json(['data' => $action, 'message' => 'Next action updated.']);
    }

    public function complete(int $id): JsonResponse
    {
        $action = $this->nextActionRepository->complete($id);

        return response()->json(['data' => $action, 'message' => 'Action completed.']);
    }

    public function snooze(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'snoozed_until' => 'required|date|after:now',
        ]);

        $action = $this->nextActionRepository->snooze($id, $request->get('snoozed_until'));

        return response()->json(['data' => $action, 'message' => 'Action snoozed.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->nextActionRepository->findOrFail($id);
        $this->nextActionRepository->delete($id);

        return response()->json(['message' => 'Next action deleted.']);
    }

    public function teamStream(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'priority', 'due_from', 'due_to', 'user_id', 'status']);

        // Get all active user IDs
        $userIds = $this->userRepository->findWhere([['status', '=', 1]])->pluck('id')->toArray();

        if (empty($userIds)) {
            $userIds = [$request->user()->id];
        }

        $query = $this->nextActionRepository->getTeamActions($userIds, $filters);
        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function teamMembers(Request $request): JsonResponse
    {
        $users = $this->userRepository->findWhere([['status', '=', 1]], ['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    public function overdueCount(Request $request): JsonResponse
    {
        $count = $this->nextActionRepository->getOverdueCount($request->user()->id);

        return response()->json(['data' => ['overdue_count' => $count]]);
    }
}
