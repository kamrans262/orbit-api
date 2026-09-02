<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Models\BillingPlanPrice;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateBillingPlanPriceController
{
    public function __invoke(Request $request, string $planId, AdminAuditLogger $audit): JsonResponse
    {
        $plan = BillingPlan::query()->find($planId);
        if ($plan === null) {
            return AdminApiResponse::error($request, 'Plan not found.', 'BILLING_PLAN_NOT_FOUND', 404);
        }
        $data = $request->validate(['billing_interval' => ['required', 'in:monthly,annual'], 'currency' => ['required', 'string', 'size:3'], 'amount_minor' => ['required', 'integer', 'min:0'], 'provider' => ['nullable', 'string', 'max:32'], 'provider_price_ref' => ['nullable', 'string', 'max:190'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']]);
        $price = BillingPlanPrice::query()->create([...$data, 'plan_id' => $plan->id, 'currency' => strtoupper($data['currency']), 'provider' => $data['provider'] ?? 'manual']);
        $audit->write('billing.plan_price.created', $request->user(), $request->attributes->get('admin_session'), 'billing_plan_price', $price->id, after: ['plan_id' => $plan->id, 'amount_minor' => (int) $price->amount_minor, 'currency' => $price->currency], request: $request);

        return AdminApiResponse::success($request, ['id' => $price->id], 201);
    }
}
