<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Notification\Repositories\CrmNotificationRepository;

class NotificationController extends Controller
{
    public function __construct(
        protected CrmNotificationRepository $notificationRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $notifications = $this->notificationRepository->getForUser($request->user()->id, $perPage);

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationRepository->getUnreadCount($request->user()->id);

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    public function markAsRead(int $id): JsonResponse
    {
        $this->notificationRepository->markAsRead($id);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationRepository->markAllAsRead($request->user()->id);

        return response()->json(['message' => "$count notifications marked as read."]);
    }
}
