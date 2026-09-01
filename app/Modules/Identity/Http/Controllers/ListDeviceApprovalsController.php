<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentityDeviceTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListDeviceApprovalsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $rows = IdentityDeviceTrust::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('requested_at')
            ->get()
            ->map(fn (IdentityDeviceTrust $trust): array => [
                'id' => $trust->id,
                'device_id' => $trust->device_id,
                'status' => $trust->status,
                'requested_at' => $trust->requested_at?->toIso8601String(),
                'expires_at' => $trust->expires_at?->toIso8601String(),
                'decided_at' => $trust->decided_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }
}
