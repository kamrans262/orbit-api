<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListPaymentsController
{
    public function __invoke(Request $request, BillingPresenter $presenter): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string', 'max:32'], 'provider' => ['nullable', 'string', 'max:32'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = PaymentTransaction::query()->latest('occurred_at');
        foreach (['user_id', 'status', 'provider'] as $k) {
            if (isset($data[$k])) {
                $q->where($k, $data[$k]);
            }
        }$p = $q->paginate($data['per_page'] ?? 25);

        return AdminApiResponse::success($request, ['items' => collect($p->items())->map(fn ($x) => $presenter->transaction($x))->all(), 'pagination' => ['total' => $p->total(), 'current_page' => $p->currentPage(), 'last_page' => $p->lastPage()]]);
    }
}
