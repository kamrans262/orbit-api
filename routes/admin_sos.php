<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use App\Modules\Admin\Safety\Http\Controllers\AccessAdminSosLocationController;
use App\Modules\Admin\Safety\Http\Controllers\AccessAdminSosRecordingController;
use App\Modules\Admin\Safety\Http\Controllers\AddAdminSosNoteController;
use App\Modules\Admin\Safety\Http\Controllers\AdminRealtimeAuthController;
use App\Modules\Admin\Safety\Http\Controllers\CreateAdminSosExportController;
use App\Modules\Admin\Safety\Http\Controllers\ListAdminSosIncidentsController;
use App\Modules\Admin\Safety\Http\Controllers\ListAdminSosSensitiveAccessController;
use App\Modules\Admin\Safety\Http\Controllers\ShowAdminSosIncidentController;
use App\Modules\Admin\Safety\Http\Controllers\UpdateAdminSosAssignmentController;
use App\Modules\Admin\Safety\Http\Controllers\UpdateAdminSosClassificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')
    ->middleware([
        AttachAdminRequestId::class,
        AuthenticateAdmin::class,
        EnsureAdminSessionActive::class,
        AuditAdminMutation::class,
        'throttle:admin-api',
    ])
    ->group(function (): void {
        Route::post('/realtime/auth', AdminRealtimeAuthController::class)
            ->middleware(EnsureAdminPermission::class.':sos.view')
            ->name('api.admin.v1.realtime.auth');

        Route::get('/sos', ListAdminSosIncidentsController::class)
            ->middleware(EnsureAdminPermission::class.':sos.view')
            ->name('api.admin.v1.sos.index');

        Route::get('/sos/{sosId}', ShowAdminSosIncidentController::class)
            ->middleware(EnsureAdminPermission::class.':sos.view')
            ->name('api.admin.v1.sos.show');

        Route::patch('/sos/{sosId}/assignment', UpdateAdminSosAssignmentController::class)
            ->middleware(EnsureAdminPermission::class.':sos.manage')
            ->name('api.admin.v1.sos.assignment.update');

        Route::put('/sos/{sosId}/classification', UpdateAdminSosClassificationController::class)
            ->middleware(EnsureAdminPermission::class.':sos.manage')
            ->name('api.admin.v1.sos.classification.update');

        Route::post('/sos/{sosId}/notes', AddAdminSosNoteController::class)
            ->middleware(EnsureAdminPermission::class.':sos.manage')
            ->name('api.admin.v1.sos.notes.store');

        Route::post('/sos/{sosId}/exports', CreateAdminSosExportController::class)
            ->middleware([EnsureAdminPermission::class.':sos.export', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.sos.exports.store');

        Route::post('/sos/{sosId}/sensitive/location', AccessAdminSosLocationController::class)
            ->middleware([EnsureAdminPermission::class.':sos.location.access', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.sos.sensitive.location');

        Route::post('/sos/{sosId}/sensitive/recording', AccessAdminSosRecordingController::class)
            ->middleware([EnsureAdminPermission::class.':sos.recordings.access', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.sos.sensitive.recording');

        Route::get('/sos/{sosId}/sensitive-access', ListAdminSosSensitiveAccessController::class)
            ->middleware(EnsureAdminPermission::class.':sos.sensitive.audit')
            ->name('api.admin.v1.sos.sensitive-access.index');
    });
