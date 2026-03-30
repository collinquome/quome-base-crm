<?php

namespace Webkul\ActionStream\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\ActionStream\Repositories\NextActionRepository;
use Webkul\Notification\Repositories\CrmNotificationRepository;

class SendActionReminders extends Command
{
    protected $signature = 'actions:send-reminders';

    protected $description = 'Send notifications for overdue and due-today actions, unsnooze expired actions';

    public function __construct(
        protected NextActionRepository $nextActionRepository,
        protected CrmNotificationRepository $notificationRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // 1. Unsnooze any expired snoozed actions
        $unsnoozed = $this->nextActionRepository->unsnoozeOverdue();
        if ($unsnoozed > 0) {
            $this->info("Unsnoozed {$unsnoozed} expired actions.");
        }

        // 2. Get all users with overdue or due-today actions
        $users = DB::table('next_actions')
            ->select('user_id')
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->toDateString())
            ->groupBy('user_id')
            ->get();

        $notified = 0;

        foreach ($users as $user) {
            $overdueCount = $this->nextActionRepository->getOverdueCount($user->user_id);

            $dueTodayCount = DB::table('next_actions')
                ->where('user_id', $user->user_id)
                ->where('status', 'pending')
                ->whereDate('due_date', now()->toDateString())
                ->count();

            // Skip if no overdue or due-today actions
            if ($overdueCount === 0 && $dueTodayCount === 0) {
                continue;
            }

            // Don't send duplicate notifications — check if we already notified today
            $alreadyNotified = DB::table('crm_notifications')
                ->where('user_id', $user->user_id)
                ->where('type', 'action_reminder')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            // Build notification message
            $parts = [];
            if ($overdueCount > 0) {
                $parts[] = "{$overdueCount} overdue";
            }
            if ($dueTodayCount > 0) {
                $parts[] = "{$dueTodayCount} due today";
            }

            $title = 'Action Reminder: ' . implode(', ', $parts);
            $body = "You have {$overdueCount} overdue and {$dueTodayCount} due-today actions. Check your Action Stream.";

            $this->notificationRepository->notify(
                $user->user_id,
                'action_reminder',
                $title,
                $body,
                ['overdue_count' => $overdueCount, 'due_today_count' => $dueTodayCount]
            );

            $notified++;
        }

        $this->info("Sent reminders to {$notified} users.");

        return self::SUCCESS;
    }
}
