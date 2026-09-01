<?php

declare(strict_types=1);

namespace App\Modules\Presence\Actions;

use App\Models\PresenceState;
use App\Models\User;
use App\Modules\Presence\Events\PresenceUpdated;
use Illuminate\Support\Facades\DB;

final class UpdatePresenceSettingsAction
{
    public function handle(User $user, bool $globalGhostMode): User
    {
        $updatedUser = DB::transaction(function () use ($user, $globalGhostMode): User {
            $user->forceFill([
                'global_ghost_mode' => $globalGhostMode,
            ])->save();

            if ($globalGhostMode) {
                PresenceState::query()
                    ->where('user_id', $user->id)
                    ->update([
                        'latitude' => null,
                        'longitude' => null,
                        'accuracy_meters' => null,
                        'movement_type' => null,
                        'location_updated_at' => null,
                    ]);
            }

            return $user->refresh();
        });

        PresenceUpdated::dispatch($user->id);

        return $updatedUser;
    }
}
