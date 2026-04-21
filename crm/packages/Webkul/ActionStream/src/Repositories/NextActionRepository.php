<?php

namespace Webkul\ActionStream\Repositories;

use Illuminate\Support\Carbon;
use Prettus\Repository\Eloquent\BaseRepository;
use Webkul\ActionStream\Contracts\NextAction;

class NextActionRepository extends BaseRepository
{
    public function model()
    {
        return NextAction::class;
    }

    public function getPrioritizedActions(int $userId, array $filters = [])
    {
        $query = $this->model->where('user_id', $userId)
            ->with([
                'actionable' => function ($morph) {
                    // Eager-load the primary person on lead actionables so the
                    // action stream row can show the contact inline.
                    $morph->morphWith([
                        \Webkul\Lead\Models\Lead::class => ['person'],
                    ]);
                },
            ]);

        if (array_key_exists('status', $filters) && $filters['status'] === '') {
            // Explicit "all statuses" — no status filter applied.
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'pending');
        }

        if (! empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['due_from'])) {
            $query->where('due_date', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->where('due_date', '<=', $filters['due_to']);
        }

        // Order: overdue first, then by priority weight, then by due_date
        return $query->orderByRaw("
            CASE
                WHEN due_date IS NOT NULL AND due_date < CURDATE() THEN 0
                WHEN due_date = CURDATE() THEN 1
                WHEN due_date IS NOT NULL THEN 2
                ELSE 3
            END ASC
        ")
        ->orderByRaw("
            CASE priority
                WHEN 'urgent' THEN 0
                WHEN 'high' THEN 1
                WHEN 'normal' THEN 2
                WHEN 'low' THEN 3
                ELSE 4
            END ASC
        ")
        ->orderBy('due_date', 'asc')
        ->orderBy('created_at', 'asc');
    }

    public function complete(int $id): \Webkul\ActionStream\Models\NextAction
    {
        $action = $this->findOrFail($id);

        $action->update([
            'status'       => 'completed',
            'completed_at' => Carbon::now(),
        ]);

        return $action->fresh();
    }

    public function snooze(int $id, string $until): \Webkul\ActionStream\Models\NextAction
    {
        $action = $this->findOrFail($id);

        $action->update([
            'status'        => 'snoozed',
            'snoozed_until' => Carbon::parse($until),
        ]);

        return $action->fresh();
    }

    public function unsnoozeOverdue(): int
    {
        return $this->model
            ->where('status', 'snoozed')
            ->where('snoozed_until', '<=', Carbon::now())
            ->update([
                'status'        => 'pending',
                'snoozed_until' => null,
            ]);
    }

    public function getOverdueCount(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->count();
    }

    public function getTeamActions(array $userIds, array $filters = [])
    {
        $query = $this->model->whereIn('user_id', $userIds)
            ->with(['actionable', 'user']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'pending');
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['due_from'])) {
            $query->where('due_date', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->where('due_date', '<=', $filters['due_to']);
        }

        return $query->orderByRaw("
            CASE
                WHEN due_date IS NOT NULL AND due_date < CURDATE() THEN 0
                WHEN due_date = CURDATE() THEN 1
                WHEN due_date IS NOT NULL THEN 2
                ELSE 3
            END ASC
        ")
        ->orderByRaw("
            CASE priority
                WHEN 'urgent' THEN 0
                WHEN 'high' THEN 1
                WHEN 'normal' THEN 2
                WHEN 'low' THEN 3
                ELSE 4
            END ASC
        ")
        ->orderBy('due_date', 'asc')
        ->orderBy('created_at', 'asc');
    }

    public function getForEntity(string $entityType, int $entityId)
    {
        return $this->model
            ->where('actionable_type', $entityType)
            ->where('actionable_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCurrentForEntity(string $entityType, int $entityId)
    {
        return $this->model
            ->where('actionable_type', $entityType)
            ->where('actionable_id', $entityId)
            ->where('status', 'pending')
            ->orderBy('due_date', 'asc')
            ->first();
    }
}
