<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\UpdateAdminRolePermissionsRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminRolePermissionsController
{
    public function __invoke(UpdateAdminRolePermissionsRequest $request, string $roleId, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */ $actor = $request->user();
        /** @var AdminSession $session */ $session = $request->attributes->get('admin_session');
        $role = AdminRole::query()->with('permissions')->find($roleId);
        if ($role === null) {
            return AdminApiResponse::error($request, 'Administrator role not found.', 'ADMIN_ROLE_NOT_FOUND', 404);
        }
        if ($role->admins()->whereKey($actor->id)->exists()) {
            return AdminApiResponse::error($request, 'An administrator cannot modify permissions on a role currently assigned to their own account.', 'ADMIN_SELF_ROLE_PERMISSION_CHANGE_FORBIDDEN', 409);
        }

        $slugs = array_values($request->input('permission_slugs', []));
        if (! in_array('admin.access', $slugs, true)) {
            return AdminApiResponse::error($request, 'Every active administrator role must retain admin.access.', 'ADMIN_ACCESS_PERMISSION_REQUIRED', 422);
        }
        $before = $role->permissions->pluck('slug')->sort()->values()->all();
        $permissions = AdminPermission::query()->whereIn('slug', $slugs)->get();
        $role->permissions()->sync($permissions->pluck('id')->all());
        $after = $permissions->pluck('slug')->sort()->values()->all();
        $audit->write('admin.role.permissions_changed', $actor, $session, 'admin_role', $role->id,
            reason: (string) $request->input('reason'), before: ['permissions' => $before], after: ['permissions' => $after], request: $request);

        return AdminApiResponse::success($request, ['id' => $role->id, 'permissions' => $after]);
    }
}
