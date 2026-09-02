<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Models\User;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\SubscriptionService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GrantComplimentarySubscriptionController
{
    public function __invoke(Request $request, int $userId, SubscriptionService $service, BillingPresenter $presenter, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['plan_slug' => ['required', 'string', 'max:40'], 'duration_days' => ['required', 'integer', 'min:1', 'max:3650'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $user = User::query()->find($userId);
        $plan = BillingPlan::query()->where('slug', $data['plan_slug'])->first();
        if ($user === null || $plan === null) {
            return AdminApiResponse::error($request, 'User or plan not found.', 'BILLING_TARGET_NOT_FOUND', 404);
        }
        $sub = $service->changePlan($user, $plan, 'monthly', 'USD', $request->user(), true, (int) $data['duration_days']);
        $audit->write('subscription.complimentary_granted', $request->user(), $request->attributes->get('admin_session'), 'user', $userId, reason: $data['reason'], after: ['subscription_id' => $sub->id, 'days' => (int) $data['duration_days']], request: $request);

        return AdminApiResponse::success($request,$presenter->subscription($sub),201);
    }
}
