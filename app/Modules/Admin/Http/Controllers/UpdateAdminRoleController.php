<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\UpdateAdminRoleRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminRoleController
{
    public function __invoke(UpdateAdminRoleRequest $request, string $roleId, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */ $actor = $request->user();
        /** @var AdminSession $session */ $session = $request->attributes->get('admin_session');
        $role = AdminRole::query()->find($roleId);
        if ($role === null) {
            return AdminApiResponse::error($request, 'Administrator role not found.', 'ADMIN_ROLE_NOT_FOUND', 404);
        }
        if ($role->is_system) {
            return AdminApiResponse::error($request, 'System role metadata cannot be changed through this endpoint.', 'ADMIN_SYSTEM_ROLE_IMMUTABLE', 409);
        }

        $before = ['name' => $role->name, 'description' => $role->description];
        $role->forceFill(['name' => (string) $request->input('name'), 'description' => $request->input('description')])->save();
        $audit->write('admin.role.updated', $actor, $session, 'admin_role', $role->id,
            reason: (string) $request->input('reason'), before: $before,
            after: ['name' => $role->name, 'description' => $role->description], request: $request);

        return AdminApiResponse::success($request, ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug, 'description' => $role->description]);
    }
}
