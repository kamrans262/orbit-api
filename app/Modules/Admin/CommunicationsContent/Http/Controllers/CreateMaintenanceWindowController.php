<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\MaintenanceWindow;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateMaintenanceWindowController
{
    public function __invoke(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['environment' => ['nullable', 'string', 'max:24'], 'service' => ['nullable', 'string', 'max:40'], 'read_only' => ['nullable', 'boolean'], 'title' => ['required', 'string', 'max:180'], 'message' => ['required', 'string', 'max:5000'], 'expected_restoration' => ['nullable', 'string', 'max:5000'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']]);
        $m = MaintenanceWindow::query()->create([...$d, 'environment' => $d['environment'] ?? app()->environment(), 'service' => $d['service'] ?? 'global', 'read_only' => (bool) ($d['read_only'] ?? false), 'status' => 'draft', 'created_by_admin_id' => $request->user()->id]);
        $audit->write('maintenance.window.created', $request->user(), $request->attributes->get('admin_session'), 'maintenance_window', $m->id, after: $m->only(['environment', 'service', 'read_only', 'starts_at', 'ends_at']), request: $request);

        return AdminApiResponse::success($request, $m->toArray(), 201);
    }
}
