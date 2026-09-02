<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Models\User;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\SubscriptionService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeUserSubscriptionController
{
    public function __invoke(Request $request, int $userId, SubscriptionService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['plan_slug' => ['required', 'string', 'max:40'], 'billing_interval' => ['required', 'in:monthly,annual'], 'currency' => ['required', 'string', 'size:3'], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'promotion_code' => ['nullable', 'string', 'max:64']]);
        $user = User::query()->find($userId);
        $plan = BillingPlan::query()->where('slug', $data['plan_slug'])->first();
        if ($user === null || $plan === null) {
            return AdminApiResponse::error($request, 'User or plan not found.', 'BILLING_TARGET_NOT_FOUND', 404);
        }
        try {
            $promo = isset($data['promotion_code']) ? $service->eligiblePromotion($data['promotion_code'], $user) : null;
            $sub = $service->changePlan($user, $plan, $data['billing_interval'], strtoupper($data['currency']), $request->user(), false, $promo?->duration_days, $promo);
        } catch (BillingAdvertisingDomainException $e) {
            return AdminApiResponse::error($request, $e->getMessage(), $e->errorCode, $e->status);
        }
        $audit->write('subscription.plan_changed', $request->user(), $request->attributes->get('admin_session'), 'user', $userId, reason: $data['reason'], after: ['subscription_id' => $sub->id, 'plan' => $plan->slug], request: $request);

        return AdminApiResponse::success($request,$presenter->subscription($sub));
    }
}
