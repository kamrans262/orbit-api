<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\ApproveIdentityDeviceController;
use App\Modules\Identity\Http\Controllers\CancelAccountDeletionController;
use App\Modules\Identity\Http\Controllers\GetAccountDeletionController;
use App\Modules\Identity\Http\Controllers\IssueIdentitySessionController;
use App\Modules\Identity\Http\Controllers\ListAuditLogsController;
use App\Modules\Identity\Http\Controllers\ListDeviceApprovalsController;
use App\Modules\Identity\Http\Controllers\ListIdentityDevicesController;
use App\Modules\Identity\Http\Controllers\ListIdentitySessionsController;
use App\Modules\Identity\Http\Controllers\LogoutIdentityController;
use App\Modules\Identity\Http\Controllers\PrivacySummaryController;
use App\Modules\Identity\Http\Controllers\RefreshIdentitySessionController;
use App\Modules\Identity\Http\Controllers\RenameIdentityDeviceController;
use App\Modules\Identity\Http\Controllers\RequestAccountDeletionController;
use App\Modules\Identity\Http\Controllers\RequestDataExportController;
use App\Modules\Identity\Http\Controllers\RevokeIdentitySessionController;
use App\Modules\Identity\Http\Controllers\RevokeOtherIdentitySessionsController;
use App\Modules\Identity\Http\Controllers\ShowDataExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/refresh', RefreshIdentitySessionController::class)
        ->middleware('throttle:30,1')
        ->name('api.v1.auth.refresh');

    Route::middleware(['auth:sanctum', 'throttle:240,1'])->group(function (): void {
        Route::get('/me/devices', ListIdentityDevicesController::class)->name('api.v1.me.devices');
        Route::put('/me/devices/{deviceId}/name', RenameIdentityDeviceController::class)->name('api.v1.me.devices.rename');

        Route::post('/identity/sessions', IssueIdentitySessionController::class)->name('api.v1.identity.sessions.issue');
        Route::get('/identity/sessions', ListIdentitySessionsController::class)->name('api.v1.identity.sessions.index');
        Route::delete('/identity/sessions/{sessionId}', RevokeIdentitySessionController::class)->name('api.v1.identity.sessions.destroy');
        Route::post('/identity/sessions/revoke-others', RevokeOtherIdentitySessionsController::class)->name('api.v1.identity.sessions.revoke-others');
        Route::post('/identity/logout', LogoutIdentityController::class)->name('api.v1.identity.logout');

        Route::get('/identity/device-approvals', ListDeviceApprovalsController::class)->name('api.v1.identity.device-approvals.index');
        Route::post('/identity/devices/{deviceId}/approve', ApproveIdentityDeviceController::class)->name('api.v1.identity.devices.approve');

        Route::get('/identity/audit-logs', ListAuditLogsController::class)->name('api.v1.identity.audit-logs.index');
        Route::get('/identity/privacy', PrivacySummaryController::class)->name('api.v1.identity.privacy.show');

        Route::post('/identity/data-exports', RequestDataExportController::class)->name('api.v1.identity.data-exports.store');
        Route::get('/identity/data-exports/{exportId}', ShowDataExportController::class)->name('api.v1.identity.data-exports.show');

        Route::get('/identity/account-deletion', GetAccountDeletionController::class)->name('api.v1.identity.account-deletion.show');
        Route::post('/identity/account-deletion', RequestAccountDeletionController::class)->name('api.v1.identity.account-deletion.store');
        Route::delete('/identity/account-deletion', CancelAccountDeletionController::class)->name('api.v1.identity.account-deletion.cancel');
    });
});
