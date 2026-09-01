<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMyAdminSessionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $sessions = $admin->sessions()->latest()->limit(100)->get()->map(fn ($session): array => [
            'id' => $session->id,
            'last_seen_at' => $session->last_seen_at->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
            'idle_expires_at' => $session->idle_expires_at->toIso8601String(),
            'revoked_at' => $session->revoked_at?->toIso8601String(),
            'revoke_reason' => $session->revoke_reason,
            'created_at' => $session->created_at?->toIso8601String(),
        ]);

        return AdminApiResponse::success($request, $sessions);
    }
}
