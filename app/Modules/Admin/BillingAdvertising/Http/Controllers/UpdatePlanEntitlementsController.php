<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingEntitlement;
use App\Models\BillingPlan;
use App\Models\BillingPlanEntitlement;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdatePlanEntitlementsController
{
    public function __invoke(Request $request, string $planId, AdminAuditLogger $audit): JsonResponse
    {
        $plan = BillingPlan::query()->find($planId);
        if ($plan === null) {
            return AdminApiResponse::error($request, 'Plan not found.', 'BILLING_PLAN_NOT_FOUND', 404);
        }
        $data = $request->validate(['entitlements' => ['required', 'array', 'max:50']]);
        foreach ($data['entitlements'] as $slug => $value) {
            $ent = BillingEntitlement::query()->where('slug', (string) $slug)->first();
            if ($ent === null) {
                continue;
            }BillingPlanEntitlement::query()->updateOrCreate(['plan_id' => $plan->id, 'entitlement_id' => $ent->id], ['value' => ['value' => $value]]);
        }
        $audit->write('billing.plan_entitlements.updated', $request->user(), $request->attributes->get('admin_session'), 'billing_plan', $plan->id, after: ['entitlements' => array_keys($data['entitlements'])], request: $request);

        return AdminApiResponse::success($request, ['id' => $plan->id]);
    }
}
