<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminLoginEvent;
use App\Modules\Admin\Http\Requests\ListAdminLoginEventsRequest;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminLoginEventsController
{
    public function __invoke(ListAdminLoginEventsRequest $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $query = AdminLoginEvent::query();
        if (is_string($request->query('admin_user_id')) && $request->query('admin_user_id') !== '') {
            $query->where('admin_user_id', (string) $request->query('admin_user_id'));
        }
        if ($request->query('suspicious') !== null) {
            $query->where('suspicious', filter_var($request->query('suspicious'), FILTER_VALIDATE_BOOL));
        }
        $paginator = $query->latest('occurred_at')->paginate($limit);

        return AdminApiResponse::success($request, [
            'items' => collect($paginator->items())->map(fn (AdminLoginEvent $event): array => [
                'id' => $event->id,
                'admin_user_id' => $event->admin_user_id,
                'event_type' => $event->event_type,
                'success' => $event->success,
                'suspicious' => $event->suspicious,
                'ip_hash' => $event->ip_hash,
                'failure_code' => $event->failure_code,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])->values(),
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
        ]);
    }
}
