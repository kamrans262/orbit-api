<?php

declare(strict_types=1);

namespace App\Modules\Presence\Services;

use App\Models\CircleMember;
use App\Models\PresenceState;
use App\Models\User;
use App\Modules\Circles\Enums\LocationMode;

final class PresencePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function forOwner(User $user, ?PresenceState $presence): array
    {
        // Treat legacy / test-created NULL values as the safe default: Ghost Mode off.
        $globalGhostMode = (bool) ($user->global_ghost_mode ?? false);
        $mode = $globalGhostMode ? LocationMode::Ghost : LocationMode::Precise;

        return array_merge(
            $this->present($presence, $mode, $globalGhostMode),
            [
                'global_ghost_mode' => $globalGhostMode,
                'device_id' => $globalGhostMode ? null : $presence?->device_id,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forCircle(CircleMember $membership, ?PresenceState $presence): array
    {
        $user = $membership->user;
        $globalGhostMode = (bool) ($user->global_ghost_mode ?? false);
        $mode = $globalGhostMode ? LocationMode::Ghost : $membership->location_mode;

        return $this->present($presence, $mode, $globalGhostMode);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(
        ?PresenceState $presence,
        LocationMode $mode,
        bool $globalGhostMode,
    ): array {
        $isGhost = $globalGhostMode || $mode === LocationMode::Ghost;
        $status = $this->status($presence, $isGhost);
        $hideLocation = $isGhost || $mode === LocationMode::Hidden;

        $latitude = null;
        $longitude = null;
        $accuracy = null;

        if (! $hideLocation && $presence?->latitude !== null && $presence->longitude !== null) {
            if ($mode === LocationMode::Approximate) {
                $precision = (int) config('orbit.presence.approximate_precision_decimals', 2);
                $latitude = round($presence->latitude, $precision);
                $longitude = round($presence->longitude, $precision);
            } else {
                $latitude = $presence->latitude;
                $longitude = $presence->longitude;
                $accuracy = $presence->accuracy_meters;
            }
        }

        return [
            'status' => $status,
            'location' => [
                'mode' => $mode->value,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracy,
                'updated_at' => $isGhost ? null : $presence?->location_updated_at?->toIso8601String(),
            ],
            'battery' => [
                'level' => $isGhost ? null : $presence?->battery_level,
                'is_charging' => $isGhost ? null : $presence?->is_charging,
            ],
            'network_type' => $isGhost ? null : $presence?->network_type?->value,
            'movement_type' => $isGhost || $mode === LocationMode::Hidden
                ? null
                : $presence?->movement_type?->value,
            'last_seen_at' => $isGhost ? null : $presence?->updated_at?->toIso8601String(),
        ];
    }

    private function status(?PresenceState $presence, bool $isGhost): string
    {
        if ($isGhost) {
            return 'ghost';
        }

        if ($presence === null || $presence->updated_at === null) {
            return 'offline';
        }

        $offlineAfter = max(1, (int) config('orbit.presence.offline_after_seconds', 120));

        if ($presence->updated_at->lt(now()->subSeconds($offlineAfter))) {
            return 'offline';
        }

        return $presence->status->value;
    }
}
