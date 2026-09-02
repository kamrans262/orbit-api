<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowDashboardSummaryController
{
    public function __invoke(Request $request, DashboardSummaryService $service): JsonResponse
    {
        return response()->json(['data' => $service->forUser($request->user())]);
    }
}
