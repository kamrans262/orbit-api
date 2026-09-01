<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Http\Requests\CreateAdminSosExportRequest;
use App\Modules\Admin\Safety\Services\AdminSosOperationsService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateAdminSosExportController
{
    public function __invoke(CreateAdminSosExportRequest $request, string $sosId, AdminSosOperationsService $service): JsonResponse
    {
        $incident = SosEvent::query()
            ->with(['originator:id,name,email', 'circle:id,name', 'adminSafetyControl.assignedAdmin:id,name'])
            ->find($sosId);

        if ($incident === null) {
            return AdminApiResponse::error($request, 'SOS incident not found.', 'ADMIN_SOS_NOT_FOUND', 404);
        }

        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        if (! $admin instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($request, 'Administrator session is unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }

        $export = $service->createExport(
            $incident,
            $admin,
            $session,
            (string) $request->validated('format'),
            (string) $request->validated('reason'),
            $request,
        );

        return AdminApiResponse::success($request, [
            'id' => $export->id,
            'format' => $export->format,
            'status' => $export->status,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'snapshot' => $export->snapshot,
        ], 201);
    }
}
