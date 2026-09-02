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

final class ExtendSubscriptionController
{
    public function __invoke(Request $request, string $subscriptionId, SubscriptionService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $sub = UserSubscription::query()->find($subscriptionId);
        if ($sub === null) {
            return AdminApiResponse::error($request, 'Subscription not found.', 'SUBSCRIPTION_NOT_FOUND', 404);
        }try {
            $sub = $service->extend($sub, (int) $data['days'], $request->user());
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        } $audit->write('subscription.extended', $request->user(), $request->attributes->get('admin_session'), 'user_subscription', $sub->id, reason: $data['reason'], after: ['ends_at' => $sub->ends_at?->toIso8601String()], request: $request);

        return AdminApiResponse::success($request,$presenter->subscription($sub));
    }
}
