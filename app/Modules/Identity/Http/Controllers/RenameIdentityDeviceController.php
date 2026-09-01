<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\RenameIdentityDeviceRequest;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RenameIdentityDeviceController
{
    public function __invoke(
        RenameIdentityDeviceRequest $request,
        string $deviceId,
        AuditLogger $audit,
    ): JsonResponse {
        if (! Schema::hasTable('devices') || ! Schema::hasColumn('devices', 'device_name')) {
            return response()->json(['error' => ['code' => 'device_rename_unavailable']], 409);
        }

        $updated = DB::table('devices')
            ->where('id', $deviceId)
            ->where('user_id', $request->user()->getKey())
            ->update([
                'device_name' => (string) $request->validated('device_name'),
                'updated_at' => now(),
            ]);

        if ($updated === 0 && ! DB::table('devices')->where('id', $deviceId)->where('user_id', $request->user()->getKey())->exists()) {
            return response()->json(['error' => ['code' => 'device_not_found']], 404);
        }

        $audit->write(
            'identity.device.renamed',
            (int) $request->user()->getKey(),
            targetType: 'device',
            targetId: $deviceId,
            request: $request,
        );

        return response()->json(['data' => ['id' => $deviceId, 'device_name' => (string) $request->validated('device_name')]]);
    }
}
