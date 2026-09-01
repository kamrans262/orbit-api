<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\CreateAdminRoleRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateAdminRoleController
{
    public function __invoke(CreateAdminRoleRequest $request, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */ $actor = $request->user();
        /** @var AdminSession $session */ $session = $request->attributes->get('admin_session');
        $permissionSlugs = array_values($request->input('permission_slugs', []));
        if (! in_array('admin.access', $permissionSlugs, true)) {
            $permissionSlugs[] = 'admin.access';
        }

        $role = AdminRole::query()->create([
            'name' => (string) $request->input('name'),
            'slug' => (string) $request->input('slug'),
            'description' => $request->input('description'),
            'is_system' => false,
            'created_by_admin_id' => $actor->id,
        ]);
        $permissionIds = AdminPermission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all();
        $role->permissions()->sync($permissionIds);
        $audit->write(
            'admin.role.created', $actor, $session, 'admin_role', $role->id,
            reason: (string) $request->input('reason'),
            after: ['slug' => $role->slug, 'permissions' => $permissionSlugs],
            request: $request,
        );

        return AdminApiResponse::success($request, [
            'id' => $role->id, 'name' => $role->name, 'slug' => $role->slug,
            'permissions' => $permissionSlugs,
        ], 201);
    }
}
