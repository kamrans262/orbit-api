<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListManagedAdminSessionsController
{
    public function __invoke(Request $request, string $adminId): JsonResponse
    {
        $target = AdminUser::query()->find($adminId);
        if ($target === null) {
            return AdminApiResponse::error($request, 'Administrator not found.', 'ADMIN_NOT_FOUND', 404);
        }

        $items = AdminSession::query()->where('admin_user_id', $target->id)->latest()->limit(100)->get()->map(fn (AdminSession $session): array => [
            'id' => $session->id,
            'last_seen_at' => $session->last_seen_at->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
            'idle_expires_at' => $session->idle_expires_at->toIso8601String(),
            'revoked_at' => $session->revoked_at?->toIso8601String(),
            'revoke_reason' => $session->revoke_reason,
            'created_at' => $session->created_at?->toIso8601String(),
        ])->values();

        return AdminApiResponse::success($request, ['admin_id' => $target->id, 'items' => $items]);
    }
}
