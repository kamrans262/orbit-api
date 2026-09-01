<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminCircleController
{
    public function __invoke(Request $request, string $circleId, AdminCircleOperationsPresenter $presenter): JsonResponse
    {
        $circle = Circle::query()->find($circleId);
        if ($circle === null) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->detail($circle));
    }
}
