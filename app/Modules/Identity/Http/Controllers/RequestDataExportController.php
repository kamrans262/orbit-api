<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RequestDataExportAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequestDataExportController
{
    public function __invoke(Request $request, RequestDataExportAction $action): JsonResponse
    {
        $export = $action->handle($request->user(), $request);

        return response()->json(['data' => [
            'id' => $export->id,
            'status' => $export->status,
            'requested_at' => $export->requested_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
        ]], 201);
    }
}
