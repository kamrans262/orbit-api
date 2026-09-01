<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentitySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ListIdentitySessionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $sessions = IdentitySession::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->get()
            ->map(function (IdentitySession $session): array {
                $device = Schema::hasTable('devices')
                    ? DB::table('devices')->where('id', $session->device_id)->first()
                    : null;

                return [
                    'id' => $session->id,
                    'device_id' => $session->device_id,
                    'device_name' => $device?->device_name ?? null,
                    'platform' => $device?->platform ?? null,
                    'status' => $session->status,
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                    'access_expires_at' => $session->access_expires_at?->toIso8601String(),
                    'refresh_expires_at' => $session->refresh_expires_at?->toIso8601String(),
                    'revoked_at' => $session->revoked_at?->toIso8601String(),
                    'created_at' => $session->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json(['data' => $sessions]);
    }
}
