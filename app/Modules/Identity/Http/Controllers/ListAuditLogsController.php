<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\SecurityAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAuditLogsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $logs = SecurityAuditLog::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (SecurityAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'metadata' => $log->metadata ?? [],
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $logs]);
    }
}
