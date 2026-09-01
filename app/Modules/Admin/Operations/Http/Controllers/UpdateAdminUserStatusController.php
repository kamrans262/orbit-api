<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\UpdateAdminUserStatusRequest;
use App\Modules\Admin\Operations\Services\AdminUserControlService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminUserStatusController
{
    public function __invoke(UpdateAdminUserStatusRequest $request, int $userId, AdminUserControlService $service): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $data = $request->validated();
        $control = $data['status'] === 'suspended'
            ? $service->suspend($user, AdminOperationContext::admin($request), AdminOperationContext::session($request), (string) $data['reason'], isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null, $request)
            : $service->reactivate($user, AdminOperationContext::admin($request), AdminOperationContext::session($request), (string) $data['reason'], $request);

        return AdminApiResponse::success($request, $service->snapshot($control), message: 'User operational status updated.');
    }
}
