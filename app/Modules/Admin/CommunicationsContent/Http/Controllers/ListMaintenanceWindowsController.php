<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\MaintenanceWindow;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMaintenanceWindowsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', 'string', 'max:24'], 'environment' => ['nullable', 'string', 'max:24']]);
        $q = MaintenanceWindow::query()->latest();
        foreach (['status', 'environment'] as $k) {
            if (! empty($d[$k])) {
                $q->where($k, $d[$k]);
            }
        }

return AdminApiResponse::success($request, $q->limit(100)->get()->toArray());
    }
}
