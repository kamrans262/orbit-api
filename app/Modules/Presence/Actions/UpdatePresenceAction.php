<?php

declare(strict_types=1);

namespace App\Modules\Presence\Actions;

use App\Models\Device;
use App\Models\PresenceState;
use App\Models\User;
use App\Modules\Presence\Events\PresenceUpdated;
use App\Modules\Presence\Exceptions\PresenceException;
use Illuminate\Support\Facades\DB;

final class UpdatePresenceAction
{
    /**
     * @param array{
     *     device_id?: string|null,
     *     status?: string,
     *     latitude?: float|int|null,
     *     longitude?: float|int|null,
     *     accuracy_meters?: float|int|null,
     *     battery_level?: int|null,
     *     is_charging?: bool|null,
     *     network_type?: string|null,
     *     movement_type?: string|null
     * } $data
     */
    public function handle(User $user, array $data): PresenceState
    {
        $presence = DB::transaction(function () use ($user, $data): PresenceState {
            $device = $this->resolveDevice($user, $data);

            $presence = PresenceState::query()->firstOrNew([
                'user_id' => $user->id,
            ]);

            foreach ([
                'status',
                'battery_level',
                'is_charging',
                'network_type',
                'movement_type',
                'accuracy_meters',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $presence->{$field} = $data[$field];
                }
            }

            if (array_key_exists('device_id', $data)) {
                $presence->device_id = $device?->id;
            }

            $locationWasProvided = array_key_exists('latitude', $data)
                && array_key_exists('longitude', $data);

            if ($locationWasProvided) {
                $presence->latitude = $data['latitude'];
                $presence->longitude = $data['longitude'];
                $presence->location_updated_at = $data['latitude'] === null || $data['longitude'] === null
                    ? null
                    : now();

                if ($data['latitude'] === null || $data['longitude'] === null) {
                    $presence->accuracy_meters = null;
                    $presence->movement_type = null;
                }
            }

            if ((bool) ($user->global_ghost_mode ?? false)) {
                $presence->latitude = null;
                $presence->longitude = null;
                $presence->accuracy_meters = null;
                $presence->movement_type = null;
                $presence->location_updated_at = null;
            }

            $presence->reported_at = now();
            $presence->save();

            if ($device !== null) {
                $device->forceFill(['last_seen_at' => now()])->save();
            }

            return $presence->refresh();
        });

        PresenceUpdated::dispatch($user->id);

        return $presence;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDevice(User $user, array $data): ?Device
    {
        if (! array_key_exists('device_id', $data) || $data['device_id'] === null) {
            return null;
        }

        $device = Device::query()
            ->whereKey($data['device_id'])
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        return $device ?? throw PresenceException::invalidDevice();
    }
}
