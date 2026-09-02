<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPromotion;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreatePromotionController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:64', 'unique:billing_promotions,code'], 'name' => ['required', 'string', 'max:120'], 'plan_id' => ['nullable', 'uuid', 'exists:billing_plans,id'], 'percent_off' => ['nullable', 'integer', 'min:1', 'max:100', 'required_without:amount_off_minor'], 'amount_off_minor' => ['nullable', 'integer', 'min:1', 'required_without:percent_off'], 'currency' => ['nullable', 'string', 'size:3'], 'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'], 'max_redemptions' => ['nullable', 'integer', 'min:1'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']]);
        $promo = BillingPromotion::query()->create([...$data, 'code' => strtoupper($data['code']), 'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null, 'status' => 'active']);
        $audit->write('billing.promotion.created', $request->user(), $request->attributes->get('admin_session'), 'billing_promotion', $promo->id, after: ['code' => $promo->code], request: $request);

        return AdminApiResponse::success($request, ['id' => $promo->id, 'code' => $promo->code], 201);
    }
}
