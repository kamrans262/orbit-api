<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Services\AdminDeviceOperationsService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminUserDevicesController
{
    public function __invoke(Request $request, int $userId, AdminDeviceOperationsService $service): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $user->devices()->latest('last_seen_at')->get()->map(fn ($device): array => $service->present($device))->all());
    }
}
