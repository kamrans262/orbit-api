<?php

declare(strict_types=1);

use App\Modules\Admin\CommunicationsContent\Http\Controllers\ActivateMaintenanceWindowController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CancelCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CancelMaintenanceWindowController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateAnnouncementController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateContentItemController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateLegalDocumentController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateMaintenanceWindowController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\CreateTemplateController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListAnnouncementsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListAppVersionPoliciesController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListCampaignsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListContentItemsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListLegalDocumentsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListMaintenanceWindowsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListRegionsController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ListTemplatesController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PreviewCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PreviewTemplateController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PublishAnnouncementController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PublishContentItemController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PublishLegalDocumentController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\PublishTemplateController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ScheduleCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\SendCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\ShowCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\TestSendCampaignController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertAnnouncementTranslationController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertAppVersionPolicyController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertContentTranslationController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertLegalTranslationController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertRegionController;
use App\Modules\Admin\CommunicationsContent\Http\Controllers\UpsertTemplateTranslationController;
use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([AttachAdminRequestId::class, AuthenticateAdmin::class, EnsureAdminSessionActive::class, AuditAdminMutation::class, 'throttle:admin-api'])->group(function (): void {
    Route::get('/communications/campaigns', ListCampaignsController::class)->middleware(EnsureAdminPermission::class.':communications.view');
    Route::post('/communications/campaigns', CreateCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.manage');
    Route::get('/communications/campaigns/{campaignId}', ShowCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.view');
    Route::get('/communications/campaigns/{campaignId}/preview', PreviewCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.view');
    Route::post('/communications/campaigns/{campaignId}/test-send', TestSendCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.manage');
    Route::post('/communications/campaigns/{campaignId}/schedule', ScheduleCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.manage');
    Route::post('/communications/campaigns/{campaignId}/send', SendCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.manage');
    Route::post('/communications/campaigns/{campaignId}/cancel', CancelCampaignController::class)->middleware(EnsureAdminPermission::class.':communications.manage');

    Route::get('/templates', ListTemplatesController::class)->middleware(EnsureAdminPermission::class.':templates.view');
    Route::post('/templates', CreateTemplateController::class)->middleware(EnsureAdminPermission::class.':templates.manage');
    Route::put('/templates/{templateId}/translations/{locale}', UpsertTemplateTranslationController::class)->middleware(EnsureAdminPermission::class.':templates.manage');
    Route::post('/templates/{templateId}/preview', PreviewTemplateController::class)->middleware(EnsureAdminPermission::class.':templates.view');
    Route::post('/templates/{templateId}/publish', PublishTemplateController::class)->middleware(EnsureAdminPermission::class.':templates.manage');

    Route::get('/announcements', ListAnnouncementsController::class)->middleware(EnsureAdminPermission::class.':announcements.view');
    Route::post('/announcements', CreateAnnouncementController::class)->middleware(EnsureAdminPermission::class.':announcements.manage');
    Route::put('/announcements/{announcementId}/translations/{locale}', UpsertAnnouncementTranslationController::class)->middleware(EnsureAdminPermission::class.':announcements.manage');
    Route::post('/announcements/{announcementId}/publish', PublishAnnouncementController::class)->middleware(EnsureAdminPermission::class.':announcements.manage');

    Route::get('/content', ListContentItemsController::class)->middleware(EnsureAdminPermission::class.':content.view');
    Route::post('/content', CreateContentItemController::class)->middleware(EnsureAdminPermission::class.':content.manage');
    Route::put('/content/{contentId}/translations/{locale}', UpsertContentTranslationController::class)->middleware(EnsureAdminPermission::class.':content.manage');
    Route::post('/content/{contentId}/publish', PublishContentItemController::class)->middleware(EnsureAdminPermission::class.':content.manage');

    Route::get('/legal/documents', ListLegalDocumentsController::class)->middleware(EnsureAdminPermission::class.':legal.view');
    Route::post('/legal/documents', CreateLegalDocumentController::class)->middleware(EnsureAdminPermission::class.':legal.manage');
    Route::put('/legal/documents/{legalId}/translations/{locale}', UpsertLegalTranslationController::class)->middleware(EnsureAdminPermission::class.':legal.manage');
    Route::post('/legal/documents/{legalId}/publish', PublishLegalDocumentController::class)->middleware([EnsureAdminPermission::class.':legal.manage', RequireRecentAdminReauthentication::class]);

    Route::get('/regions', ListRegionsController::class)->middleware(EnsureAdminPermission::class.':regions.view');
    Route::put('/regions/{countryCode}', UpsertRegionController::class)->middleware([EnsureAdminPermission::class.':regions.manage', RequireRecentAdminReauthentication::class]);

    Route::get('/app-versions', ListAppVersionPoliciesController::class)->middleware(EnsureAdminPermission::class.':app_versions.view');
    Route::put('/app-versions/{platform}', UpsertAppVersionPolicyController::class)->middleware([EnsureAdminPermission::class.':app_versions.manage', RequireRecentAdminReauthentication::class]);

    Route::get('/maintenance', ListMaintenanceWindowsController::class)->middleware(EnsureAdminPermission::class.':maintenance.view');
    Route::post('/maintenance', CreateMaintenanceWindowController::class)->middleware(EnsureAdminPermission::class.':maintenance.manage');
    Route::post('/maintenance/{maintenanceId}/activate', ActivateMaintenanceWindowController::class)->middleware([EnsureAdminPermission::class.':maintenance.manage', RequireRecentAdminReauthentication::class]);
    Route::post('/maintenance/{maintenanceId}/cancel', CancelMaintenanceWindowController::class)->middleware([EnsureAdminPermission::class.':maintenance.manage', RequireRecentAdminReauthentication::class]);
});
