<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPlan;
use App\Models\UserSubscription;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListSubscriptionsController
{
    public function __invoke(Request $request, BillingPresenter $presenter): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:active,cancel_pending,cancelled,expired'], 'plan' => ['nullable', 'string', 'max:40'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = UserSubscription::query()->latest('started_at');
        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        } if (isset($data['status'])) {
            $q->where('status', $data['status']);
        } if (isset($data['plan'])) {
            $plan = BillingPlan::query()->where('slug', $data['plan'])->first();
            $q->where('plan_id', $plan?->id ?? '__missing__');
        }
        $p = $q->paginate($data['per_page'] ?? 25);

        return AdminApiResponse::success($request, ['items' => collect($p->items())->map(fn ($s) => $presenter->subscription($s))->all(), 'pagination' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }
}
