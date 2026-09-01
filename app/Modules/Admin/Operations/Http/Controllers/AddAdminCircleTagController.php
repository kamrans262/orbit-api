<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Modules\Admin\Operations\Http\Requests\AddAdminTagRequest;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AddAdminCircleTagController
{
    public function __invoke(AddAdminTagRequest $request, string $circleId, AdminAnnotationService $service): JsonResponse
    {
        if (! Circle::query()->whereKey($circleId)->exists()) {
            return AdminApiResponse::error($request, 'Circle not found.', 'ADMIN_CIRCLE_NOT_FOUND', 404);
        }
        $tag = $service->addTag('circle', $circleId, trim((string) $request->validated('tag')), AdminOperationContext::admin($request), AdminOperationContext::session($request), $request);

        return AdminApiResponse::success($request, ['id' => $tag->id, 'tag' => $tag->tag], 201, 'Internal tag added.');
    }
}
