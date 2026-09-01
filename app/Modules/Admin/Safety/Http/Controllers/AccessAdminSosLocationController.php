<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Http\Requests\AdminSosSensitiveAccessRequest;
use App\Modules\Admin\Safety\Services\AdminSosSensitiveAccessService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AccessAdminSosLocationController
{
    public function __invoke(AdminSosSensitiveAccessRequest $request, string $sosId, AdminSosSensitiveAccessService $service): JsonResponse
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

        return AdminApiResponse::success($request, $service->location(
            $incident,
            $admin,
            $session,
            (string) $request->validated('purpose'),
            (string) $request->validated('reason'),
            $request,
        ));
    }
}
