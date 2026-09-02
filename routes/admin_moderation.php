<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use App\Modules\Admin\Moderation\Http\Controllers\AddModerationCaseNoteController;
use App\Modules\Admin\Moderation\Http\Controllers\ApplyModerationEnforcementController;
use App\Modules\Admin\Moderation\Http\Controllers\AssignModerationAppealController;
use App\Modules\Admin\Moderation\Http\Controllers\AssignModerationReportController;
use App\Modules\Admin\Moderation\Http\Controllers\CreateRiskSignalController;
use App\Modules\Admin\Moderation\Http\Controllers\ListModerationAppealsController;
use App\Modules\Admin\Moderation\Http\Controllers\ListModerationReportsController;
use App\Modules\Admin\Moderation\Http\Controllers\ListRiskProfilesController;
use App\Modules\Admin\Moderation\Http\Controllers\ModerationRealtimeAuthController;
use App\Modules\Admin\Moderation\Http\Controllers\ResolveRiskSignalController;
use App\Modules\Admin\Moderation\Http\Controllers\ReviewModerationAppealController;
use App\Modules\Admin\Moderation\Http\Controllers\SecondReviewModerationAppealController;
use App\Modules\Admin\Moderation\Http\Controllers\ShowModerationAppealController;
use App\Modules\Admin\Moderation\Http\Controllers\ShowModerationReportController;
use App\Modules\Admin\Moderation\Http\Controllers\ShowRiskProfileController;
use App\Modules\Admin\Moderation\Http\Controllers\UpdateModerationReportWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([
    AttachAdminRequestId::class, AuthenticateAdmin::class, EnsureAdminSessionActive::class, AuditAdminMutation::class, 'throttle:admin-api',
])->group(function (): void {
    Route::post('/moderation/realtime/auth', ModerationRealtimeAuthController::class)->middleware(EnsureAdminPermission::class.':reports.view')->name('api.admin.v1.moderation.realtime.auth');
    Route::get('/reports', ListModerationReportsController::class)->middleware(EnsureAdminPermission::class.':reports.view')->name('api.admin.v1.reports.index');
    Route::get('/reports/{reportId}', ShowModerationReportController::class)->middleware(EnsureAdminPermission::class.':reports.view')->name('api.admin.v1.reports.show');
    Route::patch('/reports/{reportId}', UpdateModerationReportWorkflowController::class)->middleware(EnsureAdminPermission::class.':reports.review')->name('api.admin.v1.reports.update');
    Route::patch('/reports/{reportId}/assignment', AssignModerationReportController::class)->middleware(EnsureAdminPermission::class.':reports.assign')->name('api.admin.v1.reports.assignment');
    Route::post('/reports/{reportId}/notes', AddModerationCaseNoteController::class)->middleware(EnsureAdminPermission::class.':reports.notes.manage')->name('api.admin.v1.reports.notes.store');
    Route::post('/reports/{reportId}/enforcements', ApplyModerationEnforcementController::class)->middleware([EnsureAdminPermission::class.':reports.enforce', RequireRecentAdminReauthentication::class])->name('api.admin.v1.reports.enforcements.store');

    Route::get('/appeals', ListModerationAppealsController::class)->middleware(EnsureAdminPermission::class.':appeals.view')->name('api.admin.v1.appeals.index');
    Route::get('/appeals/{appealId}', ShowModerationAppealController::class)->middleware(EnsureAdminPermission::class.':appeals.view')->name('api.admin.v1.appeals.show');
    Route::patch('/appeals/{appealId}/assignment', AssignModerationAppealController::class)->middleware(EnsureAdminPermission::class.':appeals.assign')->name('api.admin.v1.appeals.assignment');
    Route::post('/appeals/{appealId}/review', ReviewModerationAppealController::class)->middleware([EnsureAdminPermission::class.':appeals.review', RequireRecentAdminReauthentication::class])->name('api.admin.v1.appeals.review');
    Route::post('/appeals/{appealId}/second-review', SecondReviewModerationAppealController::class)->middleware([EnsureAdminPermission::class.':appeals.second_review', RequireRecentAdminReauthentication::class])->name('api.admin.v1.appeals.second-review');

    Route::get('/risk', ListRiskProfilesController::class)->middleware(EnsureAdminPermission::class.':risk.view')->name('api.admin.v1.risk.index');
    Route::get('/risk/users/{userId}', ShowRiskProfileController::class)->middleware(EnsureAdminPermission::class.':risk.view')->name('api.admin.v1.risk.users.show');
    Route::post('/risk/users/{userId}/signals', CreateRiskSignalController::class)->middleware(EnsureAdminPermission::class.':risk.manage')->name('api.admin.v1.risk.signals.store');
    Route::post('/risk/signals/{signalId}/resolve', ResolveRiskSignalController::class)->middleware(EnsureAdminPermission::class.':risk.manage')->name('api.admin.v1.risk.signals.resolve');
});
