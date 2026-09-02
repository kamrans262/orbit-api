<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use App\Modules\Admin\PrivacySupport\Http\Controllers\AddAdminPrivacyNoteController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\AssignAdminPrivacyRequestController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\AssignAdminSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\CancelAdminAccountDeletionController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\CreateAdminExportDeliveryLinkController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\CreateAdminSupportMessageController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\CreateAdminSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\FinalizeAdminAccountDeletionController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\GenerateAdminDataExportController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\LinkAdminSupportResourceController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListAdminAccountDeletionsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListAdminDataExportsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListAdminPrivacyRequestsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListAdminSupportTicketsController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ListAdminUserContactHistoryController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\RegenerateAdminDataExportController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\RevokeAdminExportDeliveryLinkController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowAdminAccountDeletionController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowAdminDataExportController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowAdminPrivacyRequestController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\ShowAdminSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\UpdateAdminPrivacyRequestController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\UpdateAdminSupportTicketController;
use App\Modules\Admin\PrivacySupport\Http\Controllers\VerifyAdminPrivacyIdentityController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([
    AttachAdminRequestId::class,
    AuthenticateAdmin::class,
    EnsureAdminSessionActive::class,
    AuditAdminMutation::class,
    'throttle:admin-api',
])->group(function (): void {
    Route::get('/privacy/requests', ListAdminPrivacyRequestsController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.requests.index');
    Route::get('/privacy/requests/{privacyRequestId}', ShowAdminPrivacyRequestController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.requests.show');
    Route::patch('/privacy/requests/{privacyRequestId}', UpdateAdminPrivacyRequestController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.manage')
        ->name('api.admin.v1.privacy.requests.update');
    Route::patch('/privacy/requests/{privacyRequestId}/assignment', AssignAdminPrivacyRequestController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.assign')
        ->name('api.admin.v1.privacy.requests.assignment');
    Route::post('/privacy/requests/{privacyRequestId}/notes', AddAdminPrivacyNoteController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.manage')
        ->name('api.admin.v1.privacy.requests.notes.store');
    Route::post('/privacy/requests/{privacyRequestId}/identity-verification', VerifyAdminPrivacyIdentityController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.identity.verify', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.requests.identity.verify');

    Route::post('/privacy/requests/{privacyRequestId}/generate-export', GenerateAdminDataExportController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.exports.manage', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.requests.generate-export');

    Route::get('/privacy/data-exports', ListAdminDataExportsController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.exports.index');
    Route::get('/privacy/data-exports/{exportId}', ShowAdminDataExportController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.exports.show');
    Route::post('/privacy/data-exports/{exportId}/delivery-links', CreateAdminExportDeliveryLinkController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.exports.deliver', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.exports.delivery-links.store');
    Route::delete('/privacy/data-exports/{exportId}/delivery-links/{linkId}', RevokeAdminExportDeliveryLinkController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.exports.deliver', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.exports.delivery-links.destroy');
    Route::post('/privacy/data-exports/{exportId}/regenerate', RegenerateAdminDataExportController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.exports.manage', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.exports.regenerate');

    Route::get('/privacy/account-deletions', ListAdminAccountDeletionsController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.deletions.index');
    Route::get('/privacy/account-deletions/{deletionId}', ShowAdminAccountDeletionController::class)
        ->middleware(EnsureAdminPermission::class.':privacy.view')
        ->name('api.admin.v1.privacy.deletions.show');
    Route::post('/privacy/account-deletions/{deletionId}/finalize', FinalizeAdminAccountDeletionController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.deletions.manage', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.deletions.finalize');
    Route::post('/privacy/account-deletions/{deletionId}/cancel', CancelAdminAccountDeletionController::class)
        ->middleware([EnsureAdminPermission::class.':privacy.deletions.manage', RequireRecentAdminReauthentication::class])
        ->name('api.admin.v1.privacy.deletions.cancel');

    Route::get('/support/tickets', ListAdminSupportTicketsController::class)
        ->middleware(EnsureAdminPermission::class.':support.view')
        ->name('api.admin.v1.support.tickets.index');
    Route::post('/support/tickets', CreateAdminSupportTicketController::class)
        ->middleware(EnsureAdminPermission::class.':support.manage')
        ->name('api.admin.v1.support.tickets.store');
    Route::get('/support/tickets/{ticketId}', ShowAdminSupportTicketController::class)
        ->middleware(EnsureAdminPermission::class.':support.view')
        ->name('api.admin.v1.support.tickets.show');
    Route::patch('/support/tickets/{ticketId}', UpdateAdminSupportTicketController::class)
        ->middleware(EnsureAdminPermission::class.':support.manage')
        ->name('api.admin.v1.support.tickets.update');
    Route::patch('/support/tickets/{ticketId}/assignment', AssignAdminSupportTicketController::class)
        ->middleware(EnsureAdminPermission::class.':support.assign')
        ->name('api.admin.v1.support.tickets.assignment');
    Route::post('/support/tickets/{ticketId}/messages', CreateAdminSupportMessageController::class)
        ->middleware(EnsureAdminPermission::class.':support.view')
        ->name('api.admin.v1.support.tickets.messages.store');
    Route::post('/support/tickets/{ticketId}/links', LinkAdminSupportResourceController::class)
        ->middleware(EnsureAdminPermission::class.':support.notes.manage')
        ->name('api.admin.v1.support.tickets.links.store');

    Route::get('/users/{userId}/contact-history', ListAdminUserContactHistoryController::class)
        ->middleware(EnsureAdminPermission::class.':contact_history.view')
        ->name('api.admin.v1.users.contact-history.index');
});
