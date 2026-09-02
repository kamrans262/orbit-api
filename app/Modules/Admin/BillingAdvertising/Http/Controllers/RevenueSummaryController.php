<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Modules\Admin\BillingAdvertising\Services\RevenueAnalyticsService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevenueSummaryController
{
    public function __invoke(Request $request, RevenueAnalyticsService $service): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return AdminApiResponse::success($request, $service->summary($data['from'] ?? null, $data['to'] ?? null));
    }
}
