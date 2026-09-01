<?php

declare(strict_types=1);

use App\Models\CircleMember;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, string $userId): bool {
    return (string) $user->id === $userId;
});

Broadcast::channel('circles.{circleId}', function (User $user, string $circleId): bool {
    return CircleMember::query()
        ->where('circle_id', $circleId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('devices.{deviceId}', function (User $user, string $deviceId): bool {
    return Device::query()
        ->whereKey($deviceId)
        ->where('user_id', $user->id)
        ->whereNull('revoked_at')
        ->exists();
});
