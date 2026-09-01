<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Http\Requests\UpdateAdminCircleStatusRequest;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsPresenter;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

final class UpdateAdminCircleStatusController
{
    public function __invoke(UpdateAdminCircleStatusRequest $request, string $circleId, AdminCircleOperationsService $service, AdminCircleOperationsPresenter $presenter): JsonResponse
    {
        $circle = Circle::query()->find($circleId);
        if ($circle === null) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }
        $data = $request->validated();
        try {
            $service->setStatus($circle, AdminOperationContext::admin($request), AdminOperationContext::session($request), (string) $data['status'], (string) $data['reason'], $request);
        } catch (DomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'ADMIN_CIRCLE_STATUS_CONFLICT', 409);
        }

        return AdminApiResponse::success($request, $presenter->summary($circle->refresh()), message: 'Circle operational status updated.');
    }
}
