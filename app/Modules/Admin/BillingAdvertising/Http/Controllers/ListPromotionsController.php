<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\BillingPromotion;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListPromotionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        return AdminApiResponse::success($request, BillingPromotion::query()->latest()->paginate(50));
    }
}
