<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\IdentityDeviceTrust;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class DeviceTrustService
{
    public function assertOwnedDevice(int $userId, string $deviceId): object
    {
        if (! Schema::hasTable('devices')) {
            throw ValidationException::withMessages(['device_id' => 'Device registration is unavailable.']);
        }

        $device = DB::table('devices')
            ->where('id', $deviceId)
            ->where('user_id', $userId)
            ->first();

        if (! $device) {
            throw ValidationException::withMessages(['device_id' => 'The device does not belong to the authenticated user.']);
        }

        return $device;
    }

    public function ensureTrustState(int $userId, string $deviceId): IdentityDeviceTrust
    {
        return DB::transaction(function () use ($userId, $deviceId): IdentityDeviceTrust {
            // Serialize first-device trust establishment so two concurrent registrations
            // cannot both become the initial trusted device.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            $existing = IdentityDeviceTrust::query()
                ->where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $trustedCount = IdentityDeviceTrust::query()
                ->where('user_id', $userId)
                ->where('status', 'trusted')
                ->count();

            return IdentityDeviceTrust::query()->create([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'status' => $trustedCount === 0 ? 'trusted' : 'pending',
                'requested_at' => now(),
                'expires_at' => $trustedCount === 0 ? null : now()->addHours(24),
                'decided_at' => $trustedCount === 0 ? now() : null,
                'approved_by_device_id' => $trustedCount === 0 ? $deviceId : null,
            ]);
        }, 3);
    }

    public function devicePublicKeyFingerprint(object $device, IdentityTokenService $tokens): ?string
    {
        $publicKey = null;

        if (property_exists($device, 'public_identity_key')) {
            $publicKey = $device->public_identity_key;
        } elseif (property_exists($device, 'public_key')) {
            $publicKey = $device->public_key;
        }

        return $tokens->fingerprint(is_string($publicKey) ? $publicKey : null);
    }
}
