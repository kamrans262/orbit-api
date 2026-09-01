<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Http\Requests\RevokeAdminSessionRequest;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminSessionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class RevokeManagedAdminSessionController
{
    public function __invoke(RevokeAdminSessionRequest $request, string $adminId, string $sessionId, AdminSessionService $sessions, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $actor */ $actor = $request->user();
        /** @var AdminSession $actorSession */ $actorSession = $request->attributes->get('admin_session');
        $target = AdminSession::query()->whereKey($sessionId)->where('admin_user_id', $adminId)->first();
        if ($target === null) {
            return AdminApiResponse::error($request, 'Administrator session not found.', 'ADMIN_SESSION_NOT_FOUND', 404);
        }

        $sessions->revoke($target, 'revoked_by_admin');
        $audit->write('admin.session.force_revoked', $actor, $actorSession, 'admin_session', $target->id,
            reason: (string) $request->input('reason'), metadata: ['target_admin_id' => $adminId], request: $request);

        return AdminApiResponse::success($request, ['revoked' => true]);
    }
}
