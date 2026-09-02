<?php

declare(strict_types=1);
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\AnalyticsController;
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\FeatureFlagController;
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\OperationsRealtimeAuthController;
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\RemoteConfigController;
use App\Modules\Admin\AnalyticsOperations\Http\Controllers\SystemOperationsController;
use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([AttachAdminRequestId::class, AuthenticateAdmin::class, EnsureAdminSessionActive::class, AuditAdminMutation::class, 'throttle:admin-api'])->group(function (): void {
    Route::get('/analytics', [AnalyticsController::class, 'center'])->middleware(EnsureAdminPermission::class.':analytics.view');
    Route::get('/analytics/reports', [AnalyticsController::class, 'reports'])->middleware(EnsureAdminPermission::class.':analytics.reports.view');
    Route::post('/analytics/reports', [AnalyticsController::class, 'create'])->middleware(EnsureAdminPermission::class.':analytics.reports.manage');
    Route::post('/analytics/reports/{id}/run', [AnalyticsController::class, 'run'])->middleware(EnsureAdminPermission::class.':analytics.reports.view');
    Route::post('/analytics/reports/{id}/exports', [AnalyticsController::class, 'export'])->middleware(EnsureAdminPermission::class.':analytics.exports.create');
    Route::get('/analytics/exports/{id}/download', [AnalyticsController::class, 'download'])->middleware(EnsureAdminPermission::class.':analytics.exports.create');
    Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->middleware(EnsureAdminPermission::class.':feature_flags.view');
    Route::post('/feature-flags', [FeatureFlagController::class, 'create'])->middleware([EnsureAdminPermission::class.':feature_flags.modify', RequireRecentAdminReauthentication::class]);
    Route::patch('/feature-flags/{id}', [FeatureFlagController::class, 'update'])->middleware([EnsureAdminPermission::class.':feature_flags.modify', RequireRecentAdminReauthentication::class]);
    Route::post('/feature-flags/{id}/clone', [FeatureFlagController::class, 'clone'])->middleware([EnsureAdminPermission::class.':feature_flags.modify', RequireRecentAdminReauthentication::class]);
    Route::get('/feature-flags/evaluate/users/{userId}', [FeatureFlagController::class, 'evaluate'])->middleware(EnsureAdminPermission::class.':feature_flags.view');
    Route::get('/remote-config', [RemoteConfigController::class, 'index'])->middleware(EnsureAdminPermission::class.':remote_config.view');
    Route::put('/remote-config/{key}', [RemoteConfigController::class, 'upsert'])->middleware(EnsureAdminPermission::class.':remote_config.manage');
    Route::get('/system/health', [SystemOperationsController::class, 'health'])->middleware(EnsureAdminPermission::class.':operations.view');
    Route::get('/system/queues', [SystemOperationsController::class, 'queues'])->middleware(EnsureAdminPermission::class.':queues.view');
    Route::post('/system/queues/failed/{uuid}/actions', [SystemOperationsController::class, 'queueAction'])->middleware([EnsureAdminPermission::class.':queues.manage', RequireRecentAdminReauthentication::class]);
    Route::get('/system/incidents', [SystemOperationsController::class, 'incidents'])->middleware(EnsureAdminPermission::class.':incidents.view');
    Route::get('/system/incidents/{id}', [SystemOperationsController::class, 'showIncident'])->middleware(EnsureAdminPermission::class.':incidents.view');
    Route::post('/system/incidents', [SystemOperationsController::class, 'createIncident'])->middleware(EnsureAdminPermission::class.':incidents.manage');
    Route::patch('/system/incidents/{id}', [SystemOperationsController::class, 'updateIncident'])->middleware(EnsureAdminPermission::class.':incidents.manage');
    Route::post('/system/incidents/{id}/notes', [SystemOperationsController::class, 'note'])->middleware(EnsureAdminPermission::class.':incidents.manage');
    Route::get('/system/integrations', [SystemOperationsController::class, 'integrations'])->middleware(EnsureAdminPermission::class.':integrations.view');
    Route::put('/system/integrations/{service}/{provider}', [SystemOperationsController::class, 'upsertIntegration'])->middleware([EnsureAdminPermission::class.':integrations.manage', RequireRecentAdminReauthentication::class]);
    Route::get('/system/webhooks', [SystemOperationsController::class, 'webhooks'])->middleware(EnsureAdminPermission::class.':webhooks.view');
    Route::post('/system/webhooks/{id}/retry', [SystemOperationsController::class, 'retryWebhook'])->middleware([EnsureAdminPermission::class.':webhooks.retry', RequireRecentAdminReauthentication::class]);
    Route::get('/system/alerts', [SystemOperationsController::class, 'alerts'])->middleware(EnsureAdminPermission::class.':operations.view');
    Route::post('/system/alerts/{id}/acknowledge', [SystemOperationsController::class, 'acknowledgeAlert'])->middleware(EnsureAdminPermission::class.':operations.manage');
    Route::post('/system/telemetry/websocket', [SystemOperationsController::class, 'websocket'])->middleware(EnsureAdminPermission::class.':operations.telemetry.ingest');
    Route::get('/system/security-summary', [SystemOperationsController::class, 'security'])->middleware(EnsureAdminPermission::class.':security.view');
    Route::post('/system/realtime/auth',OperationsRealtimeAuthController::class)->middleware(EnsureAdminPermission::class.':operations.view');
});
