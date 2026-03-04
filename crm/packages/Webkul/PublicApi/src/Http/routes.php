<?php

use Illuminate\Support\Facades\Route;
use Webkul\PublicApi\Http\Controllers\ActivityController;
use Webkul\PublicApi\Http\Controllers\AuthController;
use Webkul\PublicApi\Http\Controllers\ContactController;
use Webkul\PublicApi\Http\Controllers\LeadController;
use Webkul\PublicApi\Http\Controllers\PipelineController;
use Webkul\PublicApi\Http\Controllers\ActionStreamController;
use Webkul\PublicApi\Http\Controllers\CommentController;
use Webkul\PublicApi\Http\Controllers\NotificationController;
use Webkul\PublicApi\Http\Controllers\DashboardController;
use Webkul\PublicApi\Http\Controllers\PipelineAnalyticsController;
use Webkul\PublicApi\Http\Controllers\ActivityReportController;
use Webkul\PublicApi\Http\Controllers\ScheduledEmailController;
use Webkul\PublicApi\Http\Controllers\ReportController;
use Webkul\PublicApi\Http\Controllers\TagController;
use Webkul\PublicApi\Http\Controllers\TrashController;

Route::prefix('api/v1')->group(function () {
    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1')
        ->name('api.v1.auth.login');

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

        // Contacts (Persons)
        Route::apiResource('contacts', ContactController::class);

        // Leads
        Route::apiResource('leads', LeadController::class);

        // Activities
        Route::apiResource('activities', ActivityController::class);

        // Pipelines
        Route::get('pipelines', [PipelineController::class, 'index'])->name('api.v1.pipelines.index');
        Route::post('pipelines', [PipelineController::class, 'store'])->name('api.v1.pipelines.store');
        Route::get('pipelines/{id}', [PipelineController::class, 'show'])->name('api.v1.pipelines.show');

        // Tags
        Route::apiResource('tags', TagController::class)->except(['show']);

        // Action Stream
        Route::get('action-stream', [ActionStreamController::class, 'index'])->name('api.v1.action-stream.index');
        Route::post('action-stream', [ActionStreamController::class, 'store'])->name('api.v1.action-stream.store');
        Route::get('action-stream/overdue-count', [ActionStreamController::class, 'overdueCount'])->name('api.v1.action-stream.overdue-count');
        Route::get('action-stream/{id}', [ActionStreamController::class, 'show'])->name('api.v1.action-stream.show');
        Route::put('action-stream/{id}', [ActionStreamController::class, 'update'])->name('api.v1.action-stream.update');
        Route::post('action-stream/{id}/complete', [ActionStreamController::class, 'complete'])->name('api.v1.action-stream.complete');
        Route::post('action-stream/{id}/snooze', [ActionStreamController::class, 'snooze'])->name('api.v1.action-stream.snooze');
        Route::delete('action-stream/{id}', [ActionStreamController::class, 'destroy'])->name('api.v1.action-stream.destroy');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.v1.notifications.unread-count');
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.v1.notifications.read-all');
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.v1.notifications.read');

        // Comments
        Route::get('comments', [CommentController::class, 'index'])->name('api.v1.comments.index');
        Route::post('comments', [CommentController::class, 'store'])->name('api.v1.comments.store');
        Route::put('comments/{id}', [CommentController::class, 'update'])->name('api.v1.comments.update');
        Route::delete('comments/{id}', [CommentController::class, 'destroy'])->name('api.v1.comments.destroy');

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('api.v1.dashboard');

        // Pipeline Analytics
        Route::get('analytics/forecast', [PipelineAnalyticsController::class, 'forecast'])->name('api.v1.analytics.forecast');
        Route::get('analytics/velocity', [PipelineAnalyticsController::class, 'velocity'])->name('api.v1.analytics.velocity');
        Route::get('analytics/summary', [PipelineAnalyticsController::class, 'summary'])->name('api.v1.analytics.summary');

        // Reports
        Route::get('reports/schema', [ReportController::class, 'schema'])->name('api.v1.reports.schema');
        Route::post('reports/execute', [ReportController::class, 'execute'])->name('api.v1.reports.execute');
        Route::get('reports', [ReportController::class, 'index'])->name('api.v1.reports.index');
        Route::post('reports', [ReportController::class, 'store'])->name('api.v1.reports.store');
        Route::get('reports/{id}', [ReportController::class, 'show'])->name('api.v1.reports.show');
        Route::put('reports/{id}', [ReportController::class, 'update'])->name('api.v1.reports.update');
        Route::delete('reports/{id}', [ReportController::class, 'destroy'])->name('api.v1.reports.destroy');
        Route::post('reports/{id}/execute', [ReportController::class, 'executeSaved'])->name('api.v1.reports.execute-saved');

        // Scheduled Emails
        Route::get('scheduled-emails', [ScheduledEmailController::class, 'index'])->name('api.v1.scheduled-emails.index');
        Route::post('scheduled-emails', [ScheduledEmailController::class, 'store'])->name('api.v1.scheduled-emails.store');
        Route::get('scheduled-emails/{id}', [ScheduledEmailController::class, 'show'])->name('api.v1.scheduled-emails.show');
        Route::post('scheduled-emails/{id}/cancel', [ScheduledEmailController::class, 'cancel'])->name('api.v1.scheduled-emails.cancel');
        Route::put('scheduled-emails/{id}/reschedule', [ScheduledEmailController::class, 'reschedule'])->name('api.v1.scheduled-emails.reschedule');

        // Activity Reports
        Route::get('reports/activities/summary', [ActivityReportController::class, 'summary'])->name('api.v1.reports.activities.summary');
        Route::get('reports/activities/by-user', [ActivityReportController::class, 'byUser'])->name('api.v1.reports.activities.by-user');
        Route::get('reports/activities/leaderboard', [ActivityReportController::class, 'leaderboard'])->name('api.v1.reports.activities.leaderboard');
        Route::get('reports/activities/trends', [ActivityReportController::class, 'trends'])->name('api.v1.reports.activities.trends');

        // Trash
        Route::get('trash', [TrashController::class, 'index'])->name('api.v1.trash.index');
        Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('api.v1.trash.restore');
        Route::delete('trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('api.v1.trash.force-delete');
    });
});
