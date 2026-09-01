<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\AcceptAdminInvitationController;
use App\Modules\Admin\Http\Controllers\AdminLogoutController;
use App\Modules\Admin\Http\Controllers\AdminMeController;
use App\Modules\Admin\Http\Controllers\ConfirmAdminMfaSetupController;
use App\Modules\Admin\Http\Controllers\CreateAdminInvitationController;
use App\Modules\Admin\Http\Controllers\CreateAdminRoleController;
use App\Modules\Admin\Http\Controllers\ListAdminAuditLogsController;
use App\Modules\Admin\Http\Controllers\ListAdminLoginEventsController;
use App\Modules\Admin\Http\Controllers\ListAdminPermissionsController;
use App\Modules\Admin\Http\Controllers\ListAdminRolesController;
use App\Modules\Admin\Http\Controllers\ListAdminsController;
use App\Modules\Admin\Http\Controllers\ListManagedAdminSessionsController;
use App\Modules\Admin\Http\Controllers\ListMyAdminSessionsController;
use App\Modules\Admin\Http\Controllers\LoginAdminController;
use App\Modules\Admin\Http\Controllers\ReauthenticateAdminController;
use App\Modules\Admin\Http\Controllers\RegenerateAdminRecoveryCodesController;
use App\Modules\Admin\Http\Controllers\RevokeAllManagedAdminSessionsController;
use App\Modules\Admin\Http\Controllers\RevokeManagedAdminSessionController;
use App\Modules\Admin\Http\Controllers\RevokeMyAdminSessionController;
use App\Modules\Admin\Http\Controllers\UpdateAdminRoleController;
use App\Modules\Admin\Http\Controllers\UpdateAdminRolePermissionsController;
use App\Modules\Admin\Http\Controllers\UpdateAdminRolesController;
use App\Modules\Admin\Http\Controllers\UpdateAdminStatusController;
use App\Modules\Admin\Http\Controllers\VerifyAdminMfaController;
use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use App\Modules\Admin\Operations\Http\Controllers\AddAdminCircleNoteController;
use App\Modules\Admin\Operations\Http\Controllers\AddAdminCircleTagController;
use App\Modules\Admin\Operations\Http\Controllers\AddAdminUserNoteController;
use App\Modules\Admin\Operations\Http\Controllers\AddAdminUserTagController;
use App\Modules\Admin\Operations\Http\Controllers\ListAdminCirclesController;
use App\Modules\Admin\Operations\Http\Controllers\ListAdminUserDevicesController;
use App\Modules\Admin\Operations\Http\Controllers\ListAdminUsersController;
use App\Modules\Admin\Operations\Http\Controllers\ListAdminUserSessionsController;
use App\Modules\Admin\Operations\Http\Controllers\RemoveAdminCircleMemberController;
use App\Modules\Admin\Operations\Http\Controllers\RemoveAdminCircleTagController;
use App\Modules\Admin\Operations\Http\Controllers\RemoveAdminUserTagController;
use App\Modules\Admin\Operations\Http\Controllers\RevokeAdminUserDeviceController;
use App\Modules\Admin\Operations\Http\Controllers\RevokeAdminUserSessionController;
use App\Modules\Admin\Operations\Http\Controllers\RevokeAllAdminUserSessionsController;
use App\Modules\Admin\Operations\Http\Controllers\RotateAdminUserDeviceTokenController;
use App\Modules\Admin\Operations\Http\Controllers\ShowAdminCircleController;
use App\Modules\Admin\Operations\Http\Controllers\ShowAdminUserController;
use App\Modules\Admin\Operations\Http\Controllers\UpdateAdminCircleControlsController;
use App\Modules\Admin\Operations\Http\Controllers\UpdateAdminCircleStatusController;
use App\Modules\Admin\Operations\Http\Controllers\UpdateAdminUserControlsController;
use App\Modules\Admin\Operations\Http\Controllers\UpdateAdminUserDeviceControlsController;
use App\Modules\Admin\Operations\Http\Controllers\UpdateAdminUserStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware(AttachAdminRequestId::class)->group(function (): void {
    Route::post('/auth/login', LoginAdminController::class)->middleware('throttle:admin-login')->name('api.admin.v1.auth.login');
    Route::post('/auth/mfa/verify', VerifyAdminMfaController::class)->middleware('throttle:admin-mfa')->name('api.admin.v1.auth.mfa.verify');
    Route::post('/auth/invitations/accept', AcceptAdminInvitationController::class)->middleware('throttle:admin-mfa')->name('api.admin.v1.auth.invitations.accept');
    Route::post('/auth/mfa/setup/confirm', ConfirmAdminMfaSetupController::class)->middleware('throttle:admin-mfa')->name('api.admin.v1.auth.mfa.setup.confirm');

    Route::middleware([AuthenticateAdmin::class, EnsureAdminSessionActive::class, AuditAdminMutation::class, 'throttle:admin-api'])->group(function (): void {
        Route::get('/auth/me', AdminMeController::class)->name('api.admin.v1.auth.me');
        Route::post('/auth/logout', AdminLogoutController::class)->name('api.admin.v1.auth.logout');
        Route::post('/auth/reauthenticate', ReauthenticateAdminController::class)->middleware('throttle:admin-mfa')->name('api.admin.v1.auth.reauthenticate');
        Route::get('/auth/sessions', ListMyAdminSessionsController::class)->name('api.admin.v1.auth.sessions.index');
        Route::delete('/auth/sessions/{sessionId}', RevokeMyAdminSessionController::class)->name('api.admin.v1.auth.sessions.destroy');
        Route::post('/auth/recovery-codes/regenerate', RegenerateAdminRecoveryCodesController::class)
            ->middleware(RequireRecentAdminReauthentication::class)
            ->name('api.admin.v1.auth.recovery-codes.regenerate');

        Route::get('/admins', ListAdminsController::class)
            ->middleware(EnsureAdminPermission::class.':admins.view')->name('api.admin.v1.admins.index');
        Route::post('/admins/invitations', CreateAdminInvitationController::class)
            ->middleware([EnsureAdminPermission::class.':admins.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.admins.invitations.store');
        Route::patch('/admins/{adminId}/status', UpdateAdminStatusController::class)
            ->middleware([EnsureAdminPermission::class.':admins.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.admins.status.update');
        Route::put('/admins/{adminId}/roles', UpdateAdminRolesController::class)
            ->middleware([EnsureAdminPermission::class.':admins.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.admins.roles.update');

        Route::get('/admins/{adminId}/sessions', ListManagedAdminSessionsController::class)
            ->middleware(EnsureAdminPermission::class.':sessions.view')->name('api.admin.v1.admins.sessions.index');
        Route::delete('/admins/{adminId}/sessions/{sessionId}', RevokeManagedAdminSessionController::class)
            ->middleware([EnsureAdminPermission::class.':sessions.revoke', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.admins.sessions.destroy');
        Route::post('/admins/{adminId}/sessions/revoke-all', RevokeAllManagedAdminSessionsController::class)
            ->middleware([EnsureAdminPermission::class.':sessions.revoke', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.admins.sessions.revoke-all');

        Route::get('/roles', ListAdminRolesController::class)
            ->middleware(EnsureAdminPermission::class.':roles.view')->name('api.admin.v1.roles.index');
        Route::get('/permissions', ListAdminPermissionsController::class)
            ->middleware(EnsureAdminPermission::class.':roles.view')->name('api.admin.v1.permissions.index');
        Route::post('/roles', CreateAdminRoleController::class)
            ->middleware([EnsureAdminPermission::class.':roles.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.roles.store');
        Route::put('/roles/{roleId}', UpdateAdminRoleController::class)
            ->middleware([EnsureAdminPermission::class.':roles.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.roles.update');
        Route::put('/roles/{roleId}/permissions', UpdateAdminRolePermissionsController::class)
            ->middleware([EnsureAdminPermission::class.':roles.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.roles.permissions.update');

        Route::get('/audit', ListAdminAuditLogsController::class)
            ->middleware(EnsureAdminPermission::class.':audit.view')->name('api.admin.v1.audit.index');
        Route::get('/security/login-events', ListAdminLoginEventsController::class)
            ->middleware(EnsureAdminPermission::class.':security.view')->name('api.admin.v1.security.login-events.index');

        Route::get('/users', ListAdminUsersController::class)
            ->middleware(EnsureAdminPermission::class.':users.view')->name('api.admin.v1.users.index');
        Route::get('/users/{userId}', ShowAdminUserController::class)
            ->middleware(EnsureAdminPermission::class.':users.view')->name('api.admin.v1.users.show');
        Route::patch('/users/{userId}/status', UpdateAdminUserStatusController::class)
            ->middleware([EnsureAdminPermission::class.':users.controls.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.status.update');
        Route::put('/users/{userId}/controls', UpdateAdminUserControlsController::class)
            ->middleware([EnsureAdminPermission::class.':users.controls.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.controls.update');
        Route::get('/users/{userId}/devices', ListAdminUserDevicesController::class)
            ->middleware(EnsureAdminPermission::class.':users.devices.view')->name('api.admin.v1.users.devices.index');
        Route::delete('/users/{userId}/devices/{deviceId}', RevokeAdminUserDeviceController::class)
            ->middleware([EnsureAdminPermission::class.':users.devices.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.devices.destroy');
        Route::put('/users/{userId}/devices/{deviceId}/controls', UpdateAdminUserDeviceControlsController::class)
            ->middleware([EnsureAdminPermission::class.':users.devices.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.devices.controls.update');
        Route::post('/users/{userId}/devices/{deviceId}/rotate-token', RotateAdminUserDeviceTokenController::class)
            ->middleware([EnsureAdminPermission::class.':users.devices.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.devices.rotate-token');
        Route::get('/users/{userId}/sessions', ListAdminUserSessionsController::class)
            ->middleware(EnsureAdminPermission::class.':users.sessions.view')->name('api.admin.v1.users.sessions.index');
        Route::delete('/users/{userId}/sessions/{sessionId}', RevokeAdminUserSessionController::class)
            ->middleware([EnsureAdminPermission::class.':users.sessions.revoke', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.sessions.destroy');
        Route::post('/users/{userId}/sessions/revoke-all', RevokeAllAdminUserSessionsController::class)
            ->middleware([EnsureAdminPermission::class.':users.sessions.revoke', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.users.sessions.revoke-all');
        Route::post('/users/{userId}/notes', AddAdminUserNoteController::class)
            ->middleware(EnsureAdminPermission::class.':users.notes.manage')->name('api.admin.v1.users.notes.store');
        Route::post('/users/{userId}/tags', AddAdminUserTagController::class)
            ->middleware(EnsureAdminPermission::class.':users.notes.manage')->name('api.admin.v1.users.tags.store');
        Route::delete('/users/{userId}/tags/{tagId}', RemoveAdminUserTagController::class)
            ->middleware(EnsureAdminPermission::class.':users.notes.manage')->name('api.admin.v1.users.tags.destroy');

        Route::get('/circles', ListAdminCirclesController::class)
            ->middleware(EnsureAdminPermission::class.':circles.view')->name('api.admin.v1.circles.index');
        Route::get('/circles/{circleId}', ShowAdminCircleController::class)
            ->middleware(EnsureAdminPermission::class.':circles.view')->name('api.admin.v1.circles.show');
        Route::patch('/circles/{circleId}/status', UpdateAdminCircleStatusController::class)
            ->middleware([EnsureAdminPermission::class.':circles.controls.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.circles.status.update');
        Route::put('/circles/{circleId}/controls', UpdateAdminCircleControlsController::class)
            ->middleware([EnsureAdminPermission::class.':circles.controls.manage', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.circles.controls.update');
        Route::delete('/circles/{circleId}/members/{membershipId}', RemoveAdminCircleMemberController::class)
            ->middleware([EnsureAdminPermission::class.':circles.members.enforce', RequireRecentAdminReauthentication::class])
            ->name('api.admin.v1.circles.members.destroy');
        Route::post('/circles/{circleId}/notes', AddAdminCircleNoteController::class)
            ->middleware(EnsureAdminPermission::class.':circles.notes.manage')->name('api.admin.v1.circles.notes.store');
        Route::post('/circles/{circleId}/tags', AddAdminCircleTagController::class)
            ->middleware(EnsureAdminPermission::class.':circles.notes.manage')->name('api.admin.v1.circles.tags.store');
        Route::delete('/circles/{circleId}/tags/{tagId}', RemoveAdminCircleTagController::class)
            ->middleware(EnsureAdminPermission::class.':circles.notes.manage')->name('api.admin.v1.circles.tags.destroy');
    });
});
