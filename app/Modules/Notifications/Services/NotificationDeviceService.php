<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NotificationDeviceService
{
    /** @return list<array{id:string, platform:string}> */
    public function pushReadyDevices(int $userId): array
    {
        $query = DB::table('devices')->where('user_id', $userId);

        if (Schema::hasColumn('devices', 'push_token')) {
            $query->whereNotNull('push_token')->where('push_token', '!=', '');
        } else {
            return [];
        }

        if (Schema::hasColumn('devices', 'revoked_at')) {
            $query->whereNull('revoked_at');
        }

        return $query->orderBy('id')->get()->map(function (object $device): array {
            return [
                'id' => (string) $device->id,
                'platform' => strtolower((string) ($device->platform ?? 'unknown')),
            ];
        })->all();
    }
}
