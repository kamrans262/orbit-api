<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Models\AdminSosSensitiveAccess;
use App\Models\SosEvent;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdminSosSensitiveAccessController
{
    public function __invoke(Request $request, string $sosId): JsonResponse
    {
        if (! SosEvent::query()->whereKey($sosId)->exists()) {
            return AdminApiResponse::error($request, 'SOS incident not found.', 'ADMIN_SOS_NOT_FOUND', 404);
        }

        $items = AdminSosSensitiveAccess::query()
            ->where('sos_event_id', $sosId)
            ->latest('occurred_at')
            ->limit(200)
            ->get()
            ->map(fn (AdminSosSensitiveAccess $access): array => [
                'id' => $access->id,
                'admin_user_id' => $access->admin_user_id,
                'admin_session_id' => $access->admin_session_id,
                'access_type' => $access->access_type,
                'purpose' => $access->purpose,
                'reason' => $access->reason,
                'request_id' => $access->request_id,
                'occurred_at' => $access->occurred_at?->toIso8601String(),
            ])->all();

        return AdminApiResponse::success($request, $items);
    }
}
