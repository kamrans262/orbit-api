<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminPermission;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminPermissionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        return AdminApiResponse::success($request, AdminPermission::query()->orderBy('slug')->get([
            'id', 'name', 'slug', 'description', 'is_sensitive',
        ]));
    }
}
