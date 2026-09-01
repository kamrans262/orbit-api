<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Services\AdminUserOperationsPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminUserController
{
    public function __invoke(Request $request, int $userId, AdminUserOperationsPresenter $presenter): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->detail($user));
    }
}
