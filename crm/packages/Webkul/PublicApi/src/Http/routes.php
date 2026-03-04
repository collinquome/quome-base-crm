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
use Webkul\PublicApi\Http\Controllers\BulkEmailController;
use Webkul\PublicApi\Http\Controllers\EmailTrackingController;
use Webkul\PublicApi\Http\Controllers\ScheduledEmailController;
use Webkul\PublicApi\Http\Controllers\ReportController;
use Webkul\PublicApi\Http\Controllers\TagController;
use Webkul\PublicApi\Http\Controllers\TrashController;
use Webkul\PublicApi\Http\Controllers\GdprController;
use Webkul\PublicApi\Http\Controllers\BroadcastController;
use Webkul\PublicApi\Http\Controllers\BackupController;
use Webkul\PublicApi\Http\Controllers\EmailSequenceController;
use Webkul\PublicApi\Http\Controllers\RoleController;
use Webkul\PublicApi\Http\Controllers\PlaybookController;
use Webkul\PublicApi\Http\Controllers\MailchimpController;
use Webkul\PublicApi\Http\Controllers\EnrichmentController;

Route::prefix('api/v1')->group(function () {
    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1')
        ->name('api.v1.auth.login');

    // Public tracking endpoints (no auth - these are hit by email clients)
    Route::get('track/open/{trackingId}', [EmailTrackingController::class, 'trackOpen'])->name('api.v1.track.open');
    Route::get('track/click/{trackingId}', [EmailTrackingController::class, 'trackClick'])->name('api.v1.track.click');

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

        // Bulk Email
        Route::post('emails/bulk', [BulkEmailController::class, 'send'])->name('api.v1.emails.bulk');
        Route::get('emails/bulk/limits', [BulkEmailController::class, 'limits'])->name('api.v1.emails.bulk.limits');

        // Email Tracking (specific routes before parameterized)
        Route::post('emails/tracking/generate', [EmailTrackingController::class, 'generateTracking'])->name('api.v1.emails.tracking.generate');
        Route::get('emails/tracking/summary', [EmailTrackingController::class, 'summary'])->name('api.v1.emails.tracking.summary');
        Route::get('emails/{emailId}/tracking', [EmailTrackingController::class, 'events'])->where('emailId', '[0-9]+')->name('api.v1.emails.tracking');

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

        // GDPR
        Route::get('gdpr/contacts/{contactId}/export', [GdprController::class, 'export'])->where('contactId', '[0-9]+')->name('api.v1.gdpr.export');
        Route::post('gdpr/contacts/{contactId}/erase', [GdprController::class, 'erase'])->where('contactId', '[0-9]+')->name('api.v1.gdpr.erase');
        Route::get('gdpr/contacts/{contactId}/consent', [GdprController::class, 'consentStatus'])->where('contactId', '[0-9]+')->name('api.v1.gdpr.consent');

        // Playbooks
        Route::get('playbooks', [PlaybookController::class, 'index'])->name('api.v1.playbooks.index');
        Route::post('playbooks', [PlaybookController::class, 'store'])->name('api.v1.playbooks.store');
        Route::get('playbooks/{id}', [PlaybookController::class, 'show'])->where('id', '[0-9]+')->name('api.v1.playbooks.show');
        Route::put('playbooks/{id}', [PlaybookController::class, 'update'])->where('id', '[0-9]+')->name('api.v1.playbooks.update');
        Route::delete('playbooks/{id}', [PlaybookController::class, 'destroy'])->where('id', '[0-9]+')->name('api.v1.playbooks.destroy');
        Route::post('playbooks/{id}/steps', [PlaybookController::class, 'addStep'])->where('id', '[0-9]+')->name('api.v1.playbooks.add-step');
        Route::post('playbooks/{id}/execute', [PlaybookController::class, 'execute'])->where('id', '[0-9]+')->name('api.v1.playbooks.execute');
        Route::post('playbook-executions/{id}/cancel', [PlaybookController::class, 'cancelExecution'])->where('id', '[0-9]+')->name('api.v1.playbook-executions.cancel');

        // Roles
        Route::get('roles', [RoleController::class, 'index'])->name('api.v1.roles.index');
        Route::get('roles/{id}', [RoleController::class, 'show'])->where('id', '[0-9]+')->name('api.v1.roles.show');

        // Email Sequences
        Route::get('email-sequences', [EmailSequenceController::class, 'index'])->name('api.v1.email-sequences.index');
        Route::post('email-sequences', [EmailSequenceController::class, 'store'])->name('api.v1.email-sequences.store');
        Route::get('email-sequences/{id}', [EmailSequenceController::class, 'show'])->where('id', '[0-9]+')->name('api.v1.email-sequences.show');
        Route::put('email-sequences/{id}', [EmailSequenceController::class, 'update'])->where('id', '[0-9]+')->name('api.v1.email-sequences.update');
        Route::delete('email-sequences/{id}', [EmailSequenceController::class, 'destroy'])->where('id', '[0-9]+')->name('api.v1.email-sequences.destroy');
        Route::post('email-sequences/{id}/steps', [EmailSequenceController::class, 'addStep'])->where('id', '[0-9]+')->name('api.v1.email-sequences.add-step');
        Route::post('email-sequences/{id}/enroll', [EmailSequenceController::class, 'enroll'])->where('id', '[0-9]+')->name('api.v1.email-sequences.enroll');
        Route::post('email-sequences/{sequenceId}/unenroll/{contactId}', [EmailSequenceController::class, 'unenroll'])->where(['sequenceId' => '[0-9]+', 'contactId' => '[0-9]+'])->name('api.v1.email-sequences.unenroll');
        Route::get('email-sequences/{id}/performance', [EmailSequenceController::class, 'performance'])->where('id', '[0-9]+')->name('api.v1.email-sequences.performance');

        // Backups
        Route::get('backups', [BackupController::class, 'index'])->name('api.v1.backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('api.v1.backups.store');
        Route::get('backups/{id}', [BackupController::class, 'show'])->where('id', '[0-9]+')->name('api.v1.backups.show');
        Route::delete('backups/{id}', [BackupController::class, 'destroy'])->where('id', '[0-9]+')->name('api.v1.backups.destroy');

        // Broadcasting
        Route::post('broadcast/test', [BroadcastController::class, 'test'])->name('api.v1.broadcast.test');
        Route::get('broadcast/channels', [BroadcastController::class, 'channels'])->name('api.v1.broadcast.channels');

        // Contact Enrichment
        Route::get('enrichment/config', [EnrichmentController::class, 'config'])->name('api.v1.enrichment.config');
        Route::post('enrichment/configure', [EnrichmentController::class, 'configure'])->name('api.v1.enrichment.configure');
        Route::post('enrichment/contacts/{contactId}/enrich', [EnrichmentController::class, 'enrich'])->where('contactId', '[0-9]+')->name('api.v1.enrichment.enrich');
        Route::get('enrichment/contacts/{contactId}', [EnrichmentController::class, 'show'])->where('contactId', '[0-9]+')->name('api.v1.enrichment.show');
        Route::post('enrichment/bulk', [EnrichmentController::class, 'bulkEnrich'])->name('api.v1.enrichment.bulk');

        // Mailchimp
        Route::get('integrations/mailchimp/status', [MailchimpController::class, 'status'])->name('api.v1.mailchimp.status');
        Route::post('integrations/mailchimp/connect', [MailchimpController::class, 'connect'])->name('api.v1.mailchimp.connect');
        Route::post('integrations/mailchimp/disconnect', [MailchimpController::class, 'disconnect'])->name('api.v1.mailchimp.disconnect');
        Route::get('integrations/mailchimp/audiences', [MailchimpController::class, 'audiences'])->name('api.v1.mailchimp.audiences');
        Route::post('integrations/mailchimp/subscribe', [MailchimpController::class, 'subscribe'])->name('api.v1.mailchimp.subscribe');
        Route::post('integrations/mailchimp/unsubscribe', [MailchimpController::class, 'unsubscribe'])->name('api.v1.mailchimp.unsubscribe');
        Route::get('integrations/mailchimp/contacts/{contactId}/status', [MailchimpController::class, 'contactStatus'])->where('contactId', '[0-9]+')->name('api.v1.mailchimp.contact-status');
        Route::get('integrations/mailchimp/contacts/{contactId}/campaigns', [MailchimpController::class, 'campaignStats'])->where('contactId', '[0-9]+')->name('api.v1.mailchimp.campaign-stats');

        // Trash
        Route::get('trash', [TrashController::class, 'index'])->name('api.v1.trash.index');
        Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('api.v1.trash.restore');
        Route::delete('trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('api.v1.trash.force-delete');
    });
});
