<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminRole;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminRolesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $roles = AdminRole::query()->with('permissions:id,slug,name,is_sensitive')->orderBy('name')->get()->map(fn (AdminRole $role): array => [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->map(fn ($permission): array => [
                'slug' => $permission->slug,
                'name' => $permission->name,
                'is_sensitive' => $permission->is_sensitive,
            ])->values(),
        ])->values();

        return AdminApiResponse::success($request, $roles);
    }
}
