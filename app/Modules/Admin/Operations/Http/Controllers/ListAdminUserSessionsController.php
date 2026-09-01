<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\IdentitySession;
use App\Models\User;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminUserSessionsController
{
    public function __invoke(Request $request, int $userId): JsonResponse
    {
        if (! User::query()->whereKey($userId)->exists()) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $sessions = IdentitySession::query()->where('user_id', $userId)->latest('created_at')->limit(100)->get()->map(fn (IdentitySession $session): array => [
            'id' => $session->id, 'device_id' => $session->device_id, 'status' => $session->status,
            'last_seen_at' => $session->last_seen_at?->toIso8601String(), 'access_expires_at' => $session->access_expires_at?->toIso8601String(),
            'refresh_expires_at' => $session->refresh_expires_at?->toIso8601String(), 'revoked_at' => $session->revoked_at?->toIso8601String(),
            'revoke_reason' => $session->revoke_reason, 'created_at' => $session->created_at?->toIso8601String(),
        ])->all();

        return AdminApiResponse::success($request, $sessions);
    }
}
