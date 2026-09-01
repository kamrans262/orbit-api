<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\DataExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowDataExportController
{
    public function __invoke(Request $request, string $exportId): JsonResponse
    {
        $export = DataExportRequest::query()
            ->whereKey($exportId)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if (! $export) {
            return response()->json(['error' => ['code' => 'data_export_not_found']], 404);
        }

        return response()->json(['data' => [
            'id' => $export->id,
            'status' => $export->status,
            'payload' => $export->expires_at?->isFuture() ? $export->payload : null,
            'requested_at' => $export->requested_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
        ]]);
    }
}
