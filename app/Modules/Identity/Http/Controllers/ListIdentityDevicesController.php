<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\IdentityDeviceTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ListIdentityDevicesController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! Schema::hasTable('devices')) {
            return response()->json(['data' => []]);
        }

        $devices = DB::table('devices')
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->get()
            ->map(function (object $device) use ($request): array {
                $trust = IdentityDeviceTrust::query()
                    ->where('user_id', $request->user()->getKey())
                    ->where('device_id', (string) $device->id)
                    ->first();

                return [
                    'id' => (string) $device->id,
                    'client_device_id' => $device->client_device_id ?? null,
                    'device_name' => $device->device_name ?? null,
                    'platform' => $device->platform ?? null,
                    'last_seen_at' => $device->last_seen_at ?? null,
                    'trust_status' => $trust?->status ?? 'legacy_unverified',
                ];
            })
            ->values();

        return response()->json(['data' => $devices]);
    }
}
