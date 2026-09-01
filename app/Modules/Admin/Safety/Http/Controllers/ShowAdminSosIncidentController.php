<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Admin\Safety\Services\AdminSosPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAdminSosIncidentController
{
    public function __invoke(Request $request, string $sosId, AdminSosPresenter $presenter): JsonResponse
    {
        $incident = SosEvent::query()
            ->with(['originator:id,name,email', 'circle:id,name', 'adminSafetyControl.assignedAdmin:id,name'])
            ->find($sosId);

        if ($incident === null) {
            return AdminApiResponse::error($request, 'SOS incident not found.', 'ADMIN_SOS_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($request, $presenter->detail($incident));
    }
}
