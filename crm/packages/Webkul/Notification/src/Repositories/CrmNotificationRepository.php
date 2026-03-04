<?php

namespace Webkul\Notification\Repositories;

use Illuminate\Support\Carbon;
use Prettus\Repository\Eloquent\BaseRepository;
use Webkul\Notification\Contracts\CrmNotification;

class CrmNotificationRepository extends BaseRepository
{
    public function model()
    {
        return CrmNotification::class;
    }

    public function getForUser(int $userId, int $perPage = 15)
    {
        return $this->model
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $id): void
    {
        $this->model->where('id', $id)->update(['read_at' => Carbon::now()]);
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?array $data = null): \Webkul\Notification\Models\CrmNotification
    {
        return $this->create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }
}
