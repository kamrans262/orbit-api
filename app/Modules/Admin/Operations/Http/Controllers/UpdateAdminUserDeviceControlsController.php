<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\UpdateAdminDeviceControlsRequest;
use App\Modules\Admin\Operations\Services\AdminDeviceOperationsService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminUserDeviceControlsController
{
    public function __invoke(UpdateAdminDeviceControlsRequest $request, int $userId, string $deviceId, AdminDeviceOperationsService $service): JsonResponse
    {
        $user = User::query()->find($userId);
        $device = $user ? $service->findOwned($user, $deviceId) : null;
        if ($user === null || $device === null) {
            return AdminApiResponse::error($request, 'Device not found for this user.', 'ADMIN_USER_DEVICE_NOT_FOUND', 404);
        }
        $data = $request->validated();
        $service->updateControl(
            $user, $device, AdminOperationContext::admin($request), AdminOperationContext::session($request),
            (bool) $data['suspicious'], (bool) $data['require_verification'], (string) $data['reason'], $request,
        );

        return AdminApiResponse::success($request, $service->present($device->refresh()), message: 'Device controls updated.');
    }
}
