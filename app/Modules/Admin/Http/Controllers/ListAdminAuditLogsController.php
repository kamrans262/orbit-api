<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Modules\Admin\Http\Requests\ListAdminAuditLogsRequest;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminAuditLogsController
{
    public function __invoke(ListAdminAuditLogsRequest $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $query = AdminAuditLog::query();
        foreach (['admin_user_id', 'action', 'request_id', 'target_type', 'target_id', 'result'] as $filter) {
            if (is_string($request->query($filter)) && $request->query($filter) !== '') {
                $query->where($filter, (string) $request->query($filter));
            }
        }
        if (is_string($request->query('from')) && $request->query('from') !== '') {
            $query->where('occurred_at', '>=', (string) $request->query('from'));
        }
        if (is_string($request->query('to')) && $request->query('to') !== '') {
            $query->where('occurred_at', '<=', (string) $request->query('to'));
        }
        $paginator = $query->latest('occurred_at')->paginate($limit);

        return AdminApiResponse::success($request, [
            'items' => collect($paginator->items())->map(fn (AdminAuditLog $log): array => [
                'id' => $log->id,
                'admin_user_id' => $log->admin_user_id,
                'admin_session_id' => $log->admin_session_id,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'result' => $log->result,
                'reason' => $log->reason,
                'request_id' => $log->request_id,
                'ip_hash' => $log->ip_hash,
                'before' => $log->before_state,
                'after' => $log->after_state,
                'metadata' => $log->metadata,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ])->values(),
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
        ]);
    }
}
