<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Devices\Http\Resources\DeviceResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListDevicesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $devices = $request->user()
            ->devices()
            ->latest('last_seen_at')
            ->latest('created_at')
            ->get();

        return ApiResponse::success(
            DeviceResource::collection($devices)->resolve($request),
        );
    }
}
