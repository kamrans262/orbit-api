<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Http\Requests\UpdateAdminSosAssignmentRequest;
use App\Modules\Admin\Safety\Services\AdminSosOperationsService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateAdminSosAssignmentController
{
    public function __invoke(UpdateAdminSosAssignmentRequest $request, string $sosId, AdminSosOperationsService $service): JsonResponse
    {
        $incident = SosEvent::query()->find($sosId);
        if ($incident === null) {
            return AdminApiResponse::error($request, 'SOS incident not found.', 'ADMIN_SOS_NOT_FOUND', 404);
        }

        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session is unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $assigneeId = $request->validated('assigned_admin_id');
        $assignee = $assigneeId !== null ? AdminUser::query()->find((int) $assigneeId) : null;

        try {
            $control = $service->assign(
                $incident,
                $admin,
                $session,
                $assignee,
                (string) $request->validated('reason'),
                $request,
            );
        } catch (\DomainException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'ADMIN_SOS_INVALID_ASSIGNEE', 409);
        }

        return AdminApiResponse::success($request, $service->snapshot($control));
    }
}
