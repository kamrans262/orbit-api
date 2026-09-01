<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Models\CircleMember;
use App\Models\Device;
use Illuminate\Support\Collection;

final class RecipientDeviceSet
{
    /**
     * @return Collection<int, Device>
     */
    public function forCircle(string $circleId): Collection
    {
        $userIds = CircleMember::query()
            ->where('circle_id', $circleId)
            ->where('can_message', true)
            ->pluck('user_id');

        return Device::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->whereNotNull('public_identity_key')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, array{recipient_device_id:string, algorithm:string, encrypted_key:string}>  $envelopes
     */
    public function matchesCurrentSet(string $circleId, array $envelopes): bool
    {
        $expected = $this->forCircle($circleId)->pluck('id')->sort()->values()->all();
        $provided = collect($envelopes)
            ->pluck('recipient_device_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $expected === $provided;
    }
}
