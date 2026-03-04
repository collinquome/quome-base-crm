<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Notification\Repositories\CommentRepository;
use Webkul\Notification\Repositories\CrmNotificationRepository;

class CommentController extends Controller
{
    public function __construct(
        protected CommentRepository $commentRepository,
        protected CrmNotificationRepository $notificationRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type' => 'required|string|in:persons,leads',
            'commentable_id'   => 'required|integer',
        ]);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $comments = $this->commentRepository->getForEntity(
            $request->get('commentable_type'),
            $request->get('commentable_id'),
            $perPage
        );

        return response()->json($comments);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type'   => 'required|string|in:persons,leads',
            'commentable_id'     => 'required|integer',
            'body'               => 'required|string|max:5000',
            'mentioned_user_ids' => 'sometimes|array',
            'mentioned_user_ids.*' => 'integer|exists:users,id',
        ]);

        $data = $request->all();
        $data['user_id'] = $request->user()->id;

        $comment = $this->commentRepository->create($data);

        // Create notifications for mentioned users
        if (! empty($data['mentioned_user_ids'])) {
            $userName = $request->user()->name;

            foreach ($data['mentioned_user_ids'] as $mentionedUserId) {
                if ($mentionedUserId != $request->user()->id) {
                    $this->notificationRepository->notify(
                        $mentionedUserId,
                        'comment_mention',
                        "{$userName} mentioned you in a comment",
                        $data['body'],
                        [
                            'comment_id'      => $comment->id,
                            'commentable_type' => $data['commentable_type'],
                            'commentable_id'   => $data['commentable_id'],
                        ]
                    );
                }
            }
        }

        $comment->load('user');

        return response()->json(['data' => $comment, 'message' => 'Comment created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $comment = $this->commentRepository->update(['body' => $request->get('body')], $id);

        return response()->json(['data' => $comment, 'message' => 'Comment updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->commentRepository->findOrFail($id);
        $this->commentRepository->delete($id);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
