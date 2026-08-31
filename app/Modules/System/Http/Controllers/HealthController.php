<?php

declare(strict_types=1);

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'service' => 'orbit-api',
                'status' => 'ok',
                'api_version' => 'v1',
            ],
            message: 'Orbit API is healthy.',
        );
    }
}
