<?php

declare(strict_types=1);

use App\Modules\Admin\BillingAdvertising\Http\Controllers\CancelSubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ChangeUserSubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CompleteProviderRefundController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateAdvertiserController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateBillingPlanController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateBillingPlanPriceController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateCampaignController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateCreativeController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreatePromotionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\CreateRefundController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\DecideRefundController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ExtendSubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\GrantComplimentarySubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListAdvertisersController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListBillingPlansController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListCampaignsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListPaymentsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListPromotionsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListRefundsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ListSubscriptionsController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\RecordPaymentController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\RestoreSubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\RevenueSummaryController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\ShowUserSubscriptionController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\UpdateAdvertiserController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\UpdateBillingPlanController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\UpdateCampaignController;
use App\Modules\Admin\BillingAdvertising\Http\Controllers\UpdatePlanEntitlementsController;
use App\Modules\Admin\Http\Middleware\AttachAdminRequestId;
use App\Modules\Admin\Http\Middleware\AuditAdminMutation;
use App\Modules\Admin\Http\Middleware\AuthenticateAdmin;
use App\Modules\Admin\Http\Middleware\EnsureAdminPermission;
use App\Modules\Admin\Http\Middleware\EnsureAdminSessionActive;
use App\Modules\Admin\Http\Middleware\RequireRecentAdminReauthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/v1')->middleware([AttachAdminRequestId::class, AuthenticateAdmin::class, EnsureAdminSessionActive::class, AuditAdminMutation::class, 'throttle:admin-api'])->group(function (): void {
    Route::get('/billing/plans', ListBillingPlansController::class)->middleware(EnsureAdminPermission::class.':billing.plans.view');
    Route::post('/billing/plans', CreateBillingPlanController::class)->middleware(EnsureAdminPermission::class.':billing.plans.manage');
    Route::patch('/billing/plans/{planId}', UpdateBillingPlanController::class)->middleware(EnsureAdminPermission::class.':billing.plans.manage');
    Route::post('/billing/plans/{planId}/prices', CreateBillingPlanPriceController::class)->middleware([EnsureAdminPermission::class.':billing.plans.manage', RequireRecentAdminReauthentication::class]);
    Route::put('/billing/plans/{planId}/entitlements', UpdatePlanEntitlementsController::class)->middleware(EnsureAdminPermission::class.':billing.plans.manage');
    Route::get('/billing/promotions', ListPromotionsController::class)->middleware(EnsureAdminPermission::class.':billing.plans.view');
    Route::post('/billing/promotions', CreatePromotionController::class)->middleware(EnsureAdminPermission::class.':billing.plans.manage');

    Route::get('/subscriptions', ListSubscriptionsController::class)->middleware(EnsureAdminPermission::class.':subscriptions.view');
    Route::get('/subscriptions/users/{userId}', ShowUserSubscriptionController::class)->middleware(EnsureAdminPermission::class.':subscriptions.view');
    Route::post('/subscriptions/users/{userId}/change-plan', ChangeUserSubscriptionController::class)->middleware([EnsureAdminPermission::class.':subscriptions.manage', RequireRecentAdminReauthentication::class]);
    Route::post('/subscriptions/users/{userId}/complimentary', GrantComplimentarySubscriptionController::class)->middleware([EnsureAdminPermission::class.':subscriptions.manage', RequireRecentAdminReauthentication::class]);
    Route::post('/subscriptions/{subscriptionId}/extend', ExtendSubscriptionController::class)->middleware([EnsureAdminPermission::class.':subscriptions.manage', RequireRecentAdminReauthentication::class]);
    Route::post('/subscriptions/{subscriptionId}/cancel', CancelSubscriptionController::class)->middleware([EnsureAdminPermission::class.':subscriptions.manage', RequireRecentAdminReauthentication::class]);
    Route::post('/subscriptions/{subscriptionId}/restore', RestoreSubscriptionController::class)->middleware([EnsureAdminPermission::class.':subscriptions.manage', RequireRecentAdminReauthentication::class]);

    Route::get('/payments', ListPaymentsController::class)->middleware(EnsureAdminPermission::class.':payments.view');
    Route::post('/payments/reconcile', RecordPaymentController::class)->middleware([EnsureAdminPermission::class.':payments.reconcile', RequireRecentAdminReauthentication::class]);
    Route::get('/refunds', ListRefundsController::class)->middleware(EnsureAdminPermission::class.':refunds.view');
    Route::post('/payments/{paymentId}/refunds', CreateRefundController::class)->middleware(EnsureAdminPermission::class.':refunds.manage');
    Route::post('/refunds/{refundId}/decision', DecideRefundController::class)->middleware([EnsureAdminPermission::class.':refunds.approve', RequireRecentAdminReauthentication::class]);
    Route::post('/refunds/{refundId}/provider-result', CompleteProviderRefundController::class)->middleware([EnsureAdminPermission::class.':refunds.approve', RequireRecentAdminReauthentication::class]);
    Route::get('/revenue/summary', RevenueSummaryController::class)->middleware(EnsureAdminPermission::class.':revenue.view');

    Route::get('/advertising/advertisers', ListAdvertisersController::class)->middleware(EnsureAdminPermission::class.':advertising.view');
    Route::post('/advertising/advertisers', CreateAdvertiserController::class)->middleware(EnsureAdminPermission::class.':advertising.manage');
    Route::patch('/advertising/advertisers/{advertiserId}', UpdateAdvertiserController::class)->middleware(EnsureAdminPermission::class.':advertising.manage');
    Route::get('/advertising/campaigns', ListCampaignsController::class)->middleware(EnsureAdminPermission::class.':advertising.view');
    Route::post('/advertising/campaigns', CreateCampaignController::class)->middleware(EnsureAdminPermission::class.':advertising.manage');
    Route::patch('/advertising/campaigns/{campaignId}', UpdateCampaignController::class)->middleware(EnsureAdminPermission::class.':advertising.manage');
    Route::post('/advertising/campaigns/{campaignId}/creatives',CreateCreativeController::class)->middleware(EnsureAdminPermission::class.':advertising.manage');
});
