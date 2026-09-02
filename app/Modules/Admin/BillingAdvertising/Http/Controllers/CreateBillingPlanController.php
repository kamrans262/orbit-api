<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateBillingPlanController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'alpha_dash', 'max:40', 'unique:billing_plans,slug'], 'name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500'], 'rank' => ['nullable', 'integer', 'min:0', 'max:65535']]);
        $plan = BillingPlan::query()->create([...$data, 'slug' => strtolower($data['slug']), 'status' => 'active']);
        $audit->write('billing.plan.created', $request->user(), $request->attributes->get('admin_session'), 'billing_plan', $plan->id, after: ['slug' => $plan->slug, 'status' => $plan->status], request: $request);

        return AdminApiResponse::success($request, ['id' => $plan->id, 'slug' => $plan->slug], 201);
    }
}
