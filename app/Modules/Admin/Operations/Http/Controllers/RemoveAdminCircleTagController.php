<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveAdminCircleTagController
{
    public function __invoke(Request $request, string $circleId, string $tagId, AdminAnnotationService $service): JsonResponse
    {
        if (! Circle::query()->whereKey($circleId)->exists()) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }
        if (! $service->removeTag('circle', $circleId, $tagId, AdminOperationContext::admin($request), AdminOperationContext::session($request), $request)) {
            return AdminApiResponse::error($request, 'Tag not found for this Circle.', 'ADMIN_CIRCLE_TAG_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, ['removed' => true]);
    }
}
