<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\UserSubscription;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\SubscriptionService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RestoreSubscriptionController
{
    public function __invoke(Request $request, string $subscriptionId, SubscriptionService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $sub = UserSubscription::query()->find($subscriptionId);
        if ($sub === null) {
            return AdminApiResponse::error($request, 'Subscription not found.', 'SUBSCRIPTION_NOT_FOUND', 404);
        }try {
            $sub = $service->restore($sub, $request->user());
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        } $audit->write('subscription.restored', $request->user(), $request->attributes->get('admin_session'), 'user_subscription', $sub->id, reason: $data['reason'], after: ['status' => $sub->status], request: $request);

        return AdminApiResponse::success($request,$presenter->subscription($sub));
    }
}
