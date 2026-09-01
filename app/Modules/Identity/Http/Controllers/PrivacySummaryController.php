<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Services\PrivacySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrivacySummaryController
{
    public function __invoke(Request $request, PrivacySummaryService $service): JsonResponse
    {
        return response()->json(['data' => $service->forUser($request->user())]);
    }
}
