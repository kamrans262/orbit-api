<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\UpdateAdminRolesRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminRolesController
{
    public function __invoke(UpdateAdminRolesRequest $request, string $adminId, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user();
        /** @var AdminSession $session */
        $session = $request->attributes->get('admin_session');
        $target = AdminUser::query()->with('roles')->find($adminId);
        if ($target === null) {
            return AdminApiResponse::error($request, 'Administrator not found.', 'ADMIN_NOT_FOUND', 404);
        }
        if ($target->id === $actor->id) {
            return AdminApiResponse::error($request, 'Administrators cannot change their own roles.', 'ADMIN_SELF_ROLE_CHANGE_FORBIDDEN', 409);
        }

        $before = $target->roles->pluck('slug')->sort()->values()->all();
        $roles = AdminRole::query()->whereIn('slug', array_values($request->input('role_slugs', [])))->get();
        $target->roles()->sync($roles->pluck('id')->all());
        $after = $roles->pluck('slug')->sort()->values()->all();
        $audit->write(
            'admin.roles.changed',
            $actor,
            $session,
            'admin_user',
            $target->id,
            reason: (string) $request->input('reason'),
            before: ['roles' => $before],
            after: ['roles' => $after],
            request: $request,
        );

        return AdminApiResponse::success($request, ['id' => $target->id, 'roles' => $after]);
    }
}
