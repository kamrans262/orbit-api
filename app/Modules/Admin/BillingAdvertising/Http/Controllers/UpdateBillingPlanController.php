<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdateBillingPlanController
{
    public function __invoke(Request $request, string $planId, AdminAuditLogger $audit): JsonResponse
    {
        $plan = BillingPlan::query()->find($planId);
        if ($plan === null) {
            return AdminApiResponse::error($request, 'Plan not found.', 'BILLING_PLAN_NOT_FOUND', 404);
        }
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500'], 'status' => ['sometimes', 'in:active,archived'], 'rank' => ['sometimes', 'integer', 'min:0', 'max:65535']]);
        $before = $plan->only(['name', 'description', 'status', 'rank']);
        $plan->fill($data)->save();
        $audit->write('billing.plan.updated', $request->user(), $request->attributes->get('admin_session'), 'billing_plan', $plan->id, before: $before, after: $plan->only(['name', 'description', 'status', 'rank']), request: $request);

        return AdminApiResponse::success($request, ['id' => $plan->id, 'status' => $plan->status]);
    }
}
