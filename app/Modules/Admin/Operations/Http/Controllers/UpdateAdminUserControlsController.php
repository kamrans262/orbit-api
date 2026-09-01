<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Operations\Http\Requests\UpdateAdminUserControlsRequest;
use App\Modules\Admin\Operations\Services\AdminUserControlService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminUserControlsController
{
    public function __invoke(UpdateAdminUserControlsRequest $request, int $userId, AdminUserControlService $service): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'ADMIN_USER_NOT_FOUND', 404);
        }
        $data = $request->validated();
        $control = $service->updateControls(
            $user, AdminOperationContext::admin($request), AdminOperationContext::session($request),
            array_values($data['feature_restrictions']), isset($data['rate_limit_per_minute']) ? (int) $data['rate_limit_per_minute'] : null,
            (bool) $data['require_reverification'], (string) $data['risk_level'], $data['warning'] ?? null,
            (bool) $data['escalate_trust_safety'], (string) $data['reason'], $request,
        );

        return AdminApiResponse::success($request, $service->snapshot($control), message: 'User controls updated.');
    }
}
