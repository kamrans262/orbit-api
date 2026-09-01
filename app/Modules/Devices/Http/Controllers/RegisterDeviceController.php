<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Devices\Actions\RegisterDeviceAction;
use App\Modules\Devices\Http\Requests\RegisterDeviceRequest;
use App\Modules\Devices\Http\Resources\DeviceResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegisterDeviceController extends Controller
{
    public function __invoke(RegisterDeviceRequest $request, RegisterDeviceAction $action): JsonResponse
    {
        $device = $action->handle($request->user(), $request->validated());

        return ApiResponse::success(
            data: DeviceResource::make($device)->resolve($request),
            message: 'Device registered successfully.',
        );
    }
}
