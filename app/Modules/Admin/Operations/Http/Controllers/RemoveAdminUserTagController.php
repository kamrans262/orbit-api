<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveAdminUserTagController
{
    public function __invoke(Request $request, int $userId, string $tagId, AdminAnnotationService $service): JsonResponse
    {
        if (! User::query()->whereKey($userId)->exists()) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        if (! $service->removeTag('user', (string) $userId, $tagId, AdminOperationContext::admin($request), AdminOperationContext::session($request), $request)) {
            return AdminApiResponse::error($request, 'Tag not found for this user.', 'ADMIN_USER_TAG_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, ['removed' => true]);
    }
}
