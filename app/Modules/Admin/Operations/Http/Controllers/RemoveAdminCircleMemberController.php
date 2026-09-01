<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Controllers;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Modules\Admin\Operations\Http\Requests\AdminReasonRequest;
use App\Modules\Admin\Operations\Services\AdminCircleOperationsService;
use App\Modules\Admin\Operations\Support\AdminOperationContext;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class RemoveAdminCircleMemberController
{
    public function __invoke(AdminReasonRequest $request, string $circleId, string $membershipId, AdminCircleOperationsService $service): JsonResponse
    {
        $circle = Circle::query()->find($circleId);
        $membership = $circle ? CircleMember::query()->whereKey($membershipId)->where('circle_id', $circleId)->first() : null;
        if ($circle === null || $membership === null) {
            return AdminApiResponse::error($request, 'Circle membership not found.', 'ADMIN_CIRCLE_MEMBERSHIP_NOT_FOUND', 404);
        }
        if (! $service->removeMember($circle, $membership, AdminOperationContext::admin($request), AdminOperationContext::session($request), (string) $request->validated('reason'), $request)) {
            return AdminApiResponse::error($request, 'Circle owners cannot be removed through member enforcement. Archive or remove the Circle, or transfer ownership first.', 'ADMIN_CIRCLE_OWNER_REMOVAL_BLOCKED', 409);
        }

        return AdminApiResponse::success($request, ['removed' => true]);
    }
}
