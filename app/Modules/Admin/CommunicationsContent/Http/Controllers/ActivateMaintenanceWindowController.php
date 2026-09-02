<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\MaintenanceWindow;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivateMaintenanceWindowController
{
    public function __invoke(Request $request, string $maintenanceId, AdminAuditLogger $audit): JsonResponse
    {
        $m = MaintenanceWindow::query()->find($maintenanceId);
        if (! $m) {
            return AdminApiResponse::error($request, 'Maintenance window not found.', 'MAINTENANCE_NOT_FOUND', 404);
        } if (in_array($m->status, ['completed', 'cancelled'], true)) {
            return AdminApiResponse::error($request, 'A final maintenance window cannot be activated.', 'MAINTENANCE_FINAL', 409);
        } $m->forceFill(['status' => 'active', 'activated_by_admin_id' => $request->user()->id, 'activated_at' => now(), 'starts_at' => $m->starts_at ?? now()])->save();
        $audit->write('maintenance.window.activated', $request->user(), $request->attributes->get('admin_session'), 'maintenance_window', $m->id, reason: (string) $request->input('reason', 'Maintenance activation'), after: ['status' => 'active', 'sos_available' => true], request: $request);

        return AdminApiResponse::success($request,$m->toArray());
    }
}
