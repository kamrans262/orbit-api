<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Http\Requests\UpdateAdminCircleControlsRequest;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminCircleControlsController
{
    public function __invoke(UpdateAdminCircleControlsRequest $request, string $circleId, AdminCircleOperationsService $service): JsonResponse
    {
        $circle = Circle::query()->find($circleId);
        if ($circle === null) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }
        $data = $request->validated();
        $control = $service->updateControls($circle, AdminOperationContext::admin($request), AdminOperationContext::session($request), array_values($data['feature_restrictions']), (string) $data['reason'], $request);

        return AdminApiResponse::success($request, $service->snapshot($control), message: 'Circle controls updated.');
    }
}
