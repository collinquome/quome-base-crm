<?php

namespace Webkul\Notification\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Webkul\Notification\Contracts\Comment;

class CommentRepository extends BaseRepository
{
    public function model()
    {
        return Comment::class;
    }

    public function getForEntity(string $entityType, int $entityId, int $perPage = 15)
    {
        return $this->model
            ->where('commentable_type', $entityType)
            ->where('commentable_id', $entityId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
