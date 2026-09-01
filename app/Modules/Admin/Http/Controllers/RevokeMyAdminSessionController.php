<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminSessionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevokeMyAdminSessionController
{
    public function __invoke(Request $request, string $sessionId, AdminSessionService $sessions, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $session = AdminSession::query()->whereKey($sessionId)->where('admin_user_id', $admin->id)->first();
        if ($session === null) {
            return AdminApiResponse::error($request, 'Administrator session not found.', 'ADMIN_SESSION_NOT_FOUND', 404);
        }

        $sessions->revoke($session, 'self_revoked');
        $audit->write('admin.session.revoked', $admin, $request->attributes->get('admin_session'), 'admin_session', $session->id, request: $request);

        return AdminApiResponse::success($request, ['revoked' => true]);
    }
}
