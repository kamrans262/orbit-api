<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\AdminReasonRequest;
use App\Modules\Admin\Operations\Services\AdminConsumerSessionService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class RevokeAdminUserSessionController
{
    public function __invoke(AdminReasonRequest $request, int $userId, string $sessionId, AdminConsumerSessionService $service, AdminAuditLogger $audit): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $service->revokeSession($user, $sessionId, 'admin_session_revoked')) {
            return AdminApiResponse::error($request, 'Session not found for this user.', 'ADMIN_USER_SESSION_NOT_FOUND', 404);
        }
        $audit->write(
            'admin.user.session.revoked', AdminOperationContext::admin($request), AdminOperationContext::session($request),
            'identity_session', $sessionId, reason: (string) $request->validated('reason'), metadata: ['user_id' => $userId], request: $request,
        );

        return AdminApiResponse::success($request, ['id' => $sessionId, 'status' => 'revoked'], message: 'User session revoked.');
    }
}
