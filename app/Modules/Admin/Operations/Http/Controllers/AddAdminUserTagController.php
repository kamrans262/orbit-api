<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\AddAdminTagRequest;
use App\Modules\Admin\Operations\Services\AdminAnnotationService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AddAdminUserTagController
{
    public function __invoke(AddAdminTagRequest $request, int $userId, AdminAnnotationService $service): JsonResponse
    {
        if (! User::query()->whereKey($userId)->exists()) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $tag = $service->addTag('user', (string) $userId, trim((string) $request->validated('tag')), AdminOperationContext::admin($request), AdminOperationContext::session($request), $request);

        return AdminApiResponse::success($request, ['id' => $tag->id, 'tag' => $tag->tag], 201, 'Internal tag added.');
    }
}
