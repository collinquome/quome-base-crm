<?php

namespace Webkul\Admin\Http\Controllers\ActionStream;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\ActionStream\Repositories\NextActionRepository;
use Webkul\User\Repositories\UserRepository;

class TeamStreamController extends Controller
{
    public function __construct(
        protected NextActionRepository $nextActionRepository,
        protected UserRepository $userRepository
    ) {}

    public function index(): View
    {
        return view('admin::action-stream.team');
    }

    public function members(): JsonResponse
    {
        // Scope the member list by the caller's view_permission. Producers
        // (individual) see only themselves; Group sees their groups; Global/
        // Administrator sees everyone. This prevents individual-scoped users
        // from picking other reps from the dashboard dropdown.
        $authorized = bouncer()->getAuthorizedUserIds();

        $query = $this->userRepository->scopeQuery(function ($q) use ($authorized) {
            $q = $q->where('status', 1);
            if ($authorized !== null) {
                $q = $q->whereIn('id', $authorized);
            }
            return $q;
        });

        $users = $query->get(['id', 'name', 'email']);

        return response()->json([
            'data'          => $users,
            'current_user'  => [
                'id'   => auth()->guard('user')->id(),
                'name' => auth()->guard('user')->user()?->name,
            ],
            // When non-null, the dashboard knows to hide "All Team Members"
            // and preselect the current user (no cross-user visibility).
            'scoped'        => $authorized !== null,
        ]);
    }

    public function stream(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'priority', 'due_from', 'due_to', 'user_id', 'status']);

        $userIds = $this->userRepository->findWhere([['status', '=', 1]])->pluck('id')->toArray();

        if (empty($userIds)) {
            $userIds = [auth()->guard('user')->id()];
        }

        $query = $this->nextActionRepository->getTeamActions($userIds, $filters);
        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }
}
