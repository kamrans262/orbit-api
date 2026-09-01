<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Devices\Actions\RevokeDeviceAction;
use App\Modules\Devices\Http\Resources\DeviceResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevokeDeviceController extends Controller
{
    public function __invoke(Request $request, string $deviceId, RevokeDeviceAction $action): JsonResponse
    {
        $device = $action->handle($request->user(), $deviceId);

        if ($device === null) {
            return ApiResponse::error(
                message: 'Device not found.',
                code: 'DEVICE_NOT_FOUND',
                status: 404,
            );
        }

        return ApiResponse::success(
            data: DeviceResource::make($device)->resolve($request),
            message: 'Device revoked successfully.',
        );
    }
}
