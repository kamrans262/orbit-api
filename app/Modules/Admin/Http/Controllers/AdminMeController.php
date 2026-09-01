<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminMeController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        /** @var AdminSession|null $session */
        $session = $request->attributes->get('admin_session');
        $roles = $admin->roles()->with('permissions')->get();

        return AdminApiResponse::success($request, [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => $admin->status->value,
            'access_expires_at' => $admin->access_expires_at?->toIso8601String(),
            'roles' => $roles->pluck('slug')->values(),
            'permissions' => $roles->flatMap->permissions->pluck('slug')->unique()->sort()->values(),
            'session' => $session ? [
                'id' => $session->id,
                'expires_at' => $session->expires_at->toIso8601String(),
                'idle_expires_at' => $session->idle_expires_at->toIso8601String(),
                'reauthenticated_at' => $session->reauthenticated_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
