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

final class RevokeAllAdminUserSessionsController
{
    public function __invoke(AdminReasonRequest $request, int $userId, AdminConsumerSessionService $service, AdminAuditLogger $audit): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $result = $service->revokeAll($user, 'admin_force_logout');
        $audit->write(
            'admin.user.sessions.revoked_all', AdminOperationContext::admin($request), AdminOperationContext::session($request),
            'user', $userId, reason: (string) $request->validated('reason'), metadata: $result, request: $request,
        );

        return AdminApiResponse::success($request, $result, message: 'All user sessions revoked.');
    }
}
