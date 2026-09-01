<?php

declare(strict_types=1);

namespace App\Modules\Devices\Actions;

use App\Models\Device;
use App\Models\User;

final class RegisterDeviceAction
{
    /**
     * @param array{
     *     client_device_id: string,
     *     platform: string,
     *     name?: string|null,
     *     app_version?: string|null,
     *     os_version?: string|null,
     *     public_identity_key?: string|null,
     *     push_token?: string|null
     * } $data
     */
    public function handle(User $user, array $data): Device
    {
        /** @var Device $device */
        $device = $user->devices()->updateOrCreate(
            ['client_device_id' => $data['client_device_id']],
            [
                'platform' => $data['platform'],
                'name' => $data['name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'public_identity_key' => $data['public_identity_key'] ?? null,
                'push_token' => $data['push_token'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $device->refresh();
    }
}
