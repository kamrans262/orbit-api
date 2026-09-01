<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\ApproveIdentityDeviceAction;
use App\Modules\Identity\Http\Requests\ApproveIdentityDeviceRequest;
use Illuminate\Http\JsonResponse;

final class ApproveIdentityDeviceController
{
    public function __invoke(
        ApproveIdentityDeviceRequest $request,
        string $deviceId,
        ApproveIdentityDeviceAction $action,
    ): JsonResponse {
        $trust = $action->handle(
            $request->user(),
            $deviceId,
            (string) $request->validated('approver_device_id'),
            $request,
        );

        return response()->json(['data' => [
            'device_id' => $trust->device_id,
            'status' => $trust->status,
            'approved_by_device_id' => $trust->approved_by_device_id,
        ]]);
    }
}
