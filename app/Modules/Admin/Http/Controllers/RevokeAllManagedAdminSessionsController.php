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

final class RevokeAllManagedAdminSessionsController
{
    public function __invoke(
        RevokeAdminSessionRequest $request,
        string $adminId,
        AdminSessionService $sessions,
        AdminAuditLogger $audit,
    ): JsonResponse {
        /** @var AdminUser $actor */
        $actor = $request->user();
        /** @var AdminSession $actorSession */
        $actorSession = $request->attributes->get('admin_session');
        $target = AdminUser::query()->find($adminId);

        if ($target === null) {
            return AdminApiResponse::error($request, 'Administrator not found.', 'ADMIN_NOT_FOUND', 404);
        }
        if ($target->id === $actor->id) {
            return AdminApiResponse::error(
                $request,
                'Use the administrator self-session endpoints to revoke your own sessions.',
                'ADMIN_SELF_FORCE_LOGOUT_FORBIDDEN',
                409,
            );
        }

        $revoked = $sessions->revokeAll($target, 'force_logout_by_admin');
        $audit->write(
            'admin.sessions.force_revoked_all',
            $actor,
            $actorSession,
            'admin_user',
            $target->id,
            reason: (string) $request->input('reason'),
            after: ['sessions_revoked' => $revoked],
            request: $request,
        );

        return AdminApiResponse::success($request, [
            'admin_id' => $target->id,
            'sessions_revoked' => $revoked,
        ]);
    }
}
