<?php

declare(strict_types=1);

use App\Modules\Admin\BackendCompletion\Http\Controllers\AdminBackendCompletionController;
use App\Modules\Admin\BackendCompletion\Http\Controllers\AdminBulkOperationsController;
use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([
    AttachAdminRequestId::class,
    AuthenticateAdmin::class,
    EnsureAdminSessionActive::class,
    AuditAdminMutation::class,
    'throttle:admin-api',
])->group(function (): void {
    Route::get('/dashboard', [AdminBackendCompletionController::class, 'dashboard'])
        ->middleware(EnsureAdminPermission::class.':dashboard.view');
    Route::put('/dashboard/layout', [AdminBackendCompletionController::class, 'updateDashboardLayout'])
        ->middleware(EnsureAdminPermission::class.':dashboard.configure');

    Route::get('/search', [AdminBackendCompletionController::class, 'search'])
        ->middleware(EnsureAdminPermission::class.':global_search.use');

    Route::get('/views', [AdminBackendCompletionController::class, 'views'])
        ->middleware(EnsureAdminPermission::class.':views.view');
    Route::post('/views', [AdminBackendCompletionController::class, 'createView'])
        ->middleware(EnsureAdminPermission::class.':views.manage');
    Route::patch('/views/{id}', [AdminBackendCompletionController::class, 'updateView'])
        ->middleware(EnsureAdminPermission::class.':views.manage');
    Route::delete('/views/{id}', [AdminBackendCompletionController::class, 'deleteView'])
        ->middleware(EnsureAdminPermission::class.':views.manage');

    Route::post('/bulk/reports/assign', [AdminBulkOperationsController::class, 'assignReports'])
        ->middleware(EnsureAdminPermission::class.':reports.assign');
    Route::post('/bulk/support/assign', [AdminBulkOperationsController::class, 'assignSupport'])
        ->middleware(EnsureAdminPermission::class.':support.assign');

    Route::get('/release/readiness', [AdminBackendCompletionController::class, 'readiness'])
        ->middleware(EnsureAdminPermission::class.':release.audit.view');

    Route::get('/security/ip-policies', [AdminBackendCompletionController::class, 'ipPolicies'])
        ->middleware(EnsureAdminPermission::class.':security.ip_policies.manage');
    Route::post('/security/ip-policies', [AdminBackendCompletionController::class, 'createIpPolicy'])
        ->middleware([EnsureAdminPermission::class.':security.ip_policies.manage', RequireRecentAdminReauthentication::class]);
    Route::delete('/security/ip-policies/{id}', [AdminBackendCompletionController::class, 'deleteIpPolicy'])
        ->middleware([EnsureAdminPermission::class.':security.ip_policies.manage', RequireRecentAdminReauthentication::class]);
});
