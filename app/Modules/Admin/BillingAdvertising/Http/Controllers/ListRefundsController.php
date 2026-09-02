<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\PaymentRefund;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListRefundsController
{
    public function __invoke(Request $request, BillingPresenter $presenter): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'max:32'], 'user_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = PaymentRefund::query()->latest('requested_at');
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }$p = $q->paginate($data['per_page'] ?? 25);

        return AdminApiResponse::success($request, ['items' => collect($p->items())->map(fn ($x) => $presenter->refund($x))->all(), 'pagination' => ['total' => $p->total(), 'current_page' => $p->currentPage(), 'last_page' => $p->lastPage()]]);
    }
}
