<?php

declare(strict_types=1);

namespace App\Modules\Devices\Actions;

use App\Models\Device;
use App\Models\User;

final class RevokeDeviceAction
{
    public function handle(User $user, string $deviceId): ?Device
    {
        /** @var Device|null $device */
        $device = $user->devices()->whereKey($deviceId)->first();

        if ($device === null) {
            return null;
        }

        $device->forceFill([
            'revoked_at' => now(),
            'push_token' => null,
        ])->save();

        return $device->refresh();
    }
}
